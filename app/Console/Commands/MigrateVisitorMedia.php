<?php

namespace App\Console\Commands;

use App\Models\VerifiedVisitor;
use App\Services\VisitorMediaService;
use Illuminate\Console\Command;

class MigrateVisitorMedia extends Command
{
    protected $signature = 'visitor-media:migrate {--dry-run : Report files without copying them}';

    protected $description = 'Copy legacy visitor images to the persistent visitor-media disk';

    public function handle(VisitorMediaService $media): int
    {
        $copied = 0;
        $skipped = 0;
        $missing = 0;
        $dryRun = (bool) $this->option('dry-run');

        VerifiedVisitor::query()
            ->select(['id', 'photo_path', 'back_photo_path', 'selfie_path'])
            ->orderBy('id')
            ->each(function (VerifiedVisitor $visitor) use ($media, $dryRun, &$copied, &$skipped, &$missing): void {
                foreach ([$visitor->photo_path, $visitor->back_photo_path, $visitor->selfie_path] as $path) {
                    $path = str_replace('\\', '/', trim((string) $path));
                    if ($path === '' || ! str_starts_with($path, 'verified-visitors/') || str_contains($path, '..')) {
                        continue;
                    }

                    $target = $media->disk(config('vms.media_disk', 'visitor-media'));
                    if ($target->exists($path)) {
                        $skipped++;
                        continue;
                    }

                    $source = null;
                    foreach (['local', 'public'] as $diskName) {
                        $disk = $media->disk($diskName);
                        if ($disk->exists($path)) {
                            $source = $disk;
                            break;
                        }
                    }

                    if ($source === null) {
                        $missing++;
                        $this->warn("Missing: {$path}");
                        continue;
                    }

                    if (! $dryRun) {
                        $target->put($path, $source->get($path));
                    }
                    $copied++;
                }
            });

        $action = $dryRun ? 'Would copy' : 'Copied';
        $this->info("{$action}: {$copied}; already persistent: {$skipped}; not found: {$missing}.");

        return self::SUCCESS;
    }
}
