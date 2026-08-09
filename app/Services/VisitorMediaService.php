<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps visitor identity images on the persistent media disk.
 *
 * The local and public disks remain read fallbacks so existing registrations
 * continue to work until their files have been migrated.
 */
class VisitorMediaService
{
    public function put(string $path, string $contents): bool
    {
        return $this->currentDisk()->put($path, $contents);
    }

    public function storeAs(UploadedFile $file, string $directory, string $filename): string|false
    {
        return $file->storeAs($directory, $filename, $this->currentDiskName());
    }

    public function exists(string $path): bool
    {
        return $this->diskContaining($path) !== null;
    }

    public function response(string $path, ?string $mime = null, array $headers = [])
    {
        return $this->diskContaining($path)?->response($path, null, array_filter([
            'Content-Type' => $mime ?: 'image/jpeg',
        ]) + $headers);
    }

    /** @return string[] */
    public function diskNames(): array
    {
        return array_values(array_unique([
            $this->currentDiskName(),
            'local',
            'public',
        ]));
    }

    public function disk(string $name): FilesystemAdapter
    {
        return Storage::disk($name);
    }

    private function currentDisk(): FilesystemAdapter
    {
        return $this->disk($this->currentDiskName());
    }

    private function currentDiskName(): string
    {
        return (string) config('vms.media_disk', 'visitor-media');
    }

    private function diskContaining(string $path): ?FilesystemAdapter
    {
        foreach ($this->diskNames() as $diskName) {
            $disk = $this->disk($diskName);
            if ($disk->exists($path)) {
                return $disk;
            }
        }

        return null;
    }
}
