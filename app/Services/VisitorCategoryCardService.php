<?php

namespace App\Services;

use App\Models\VisitorCategory;
use App\Models\VerifiedVisitor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class VisitorCategoryCardService
{
    private const DIRECTORY = 'visitor-category-cards';

    /** Resolve linked categories and older records that only saved the category label. */
    public function categoryFor(VerifiedVisitor $visitor): ?VisitorCategory
    {
        $visitor->loadMissing('visitorCategory');
        if ($visitor->visitorCategory) {
            return $visitor->visitorCategory;
        }

        $label = trim((string) $visitor->category);
        if ($label === '') {
            return null;
        }

        return VisitorCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($label)])
            ->orWhere('code', Str::slug($label))
            ->first();
    }

    /** Store new category artwork and remove the superseded file. */
    public function replace(VisitorCategory $category, UploadedFile $image): void
    {
        $extension = strtolower($image->guessExtension() ?: $image->getClientOriginalExtension() ?: 'png');
        $filename = 'category-'.$category->id.'-'.Str::uuid().'.'.$extension;
        $path = $image->storeAs(self::DIRECTORY, $filename, $this->diskName());
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The visitor category card image could not be stored.');
        }

        $oldPath = $category->card_image_path;

        try {
            $category->forceFill([
                'card_image_path' => $path,
                'card_image_name' => $image->getClientOriginalName(),
                'card_image_mime' => $image->getMimeType() ?: $image->getClientMimeType(),
            ])->save();
        } catch (\Throwable $exception) {
            $this->deletePath($path);

            throw $exception;
        }

        if (filled($oldPath) && $oldPath !== $path) {
            $this->deletePath($oldPath);
        }
    }

    /** Remove the stored artwork and restore the system card design fallback. */
    public function remove(VisitorCategory $category): void
    {
        $path = $category->card_image_path;
        $category->forceFill([
            'card_image_path' => null,
            'card_image_name' => null,
            'card_image_mime' => null,
        ])->save();

        if (filled($path)) {
            $this->deletePath($path);
        }
    }

    public function hasArtwork(?VisitorCategory $category): bool
    {
        $path = $category?->card_image_path;

        return $this->isManagedPath($path) && Storage::disk($this->diskName())->exists($path);
    }

    public function bytes(?VisitorCategory $category): ?string
    {
        if (! $this->hasArtwork($category)) {
            return null;
        }

        return Storage::disk($this->diskName())->get($category->card_image_path);
    }

    public function response(VisitorCategory $category)
    {
        abort_unless($this->hasArtwork($category), 404);

        return Storage::disk($this->diskName())->response($category->card_image_path, null, [
            'Content-Type' => $category->card_image_mime ?: 'image/png',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteFile(VisitorCategory $category): void
    {
        if (filled($category->card_image_path)) {
            $this->deletePath($category->card_image_path);
        }
    }

    private function deletePath(?string $path): void
    {
        if ($this->isManagedPath($path)) {
            Storage::disk($this->diskName())->delete($path);
        }
    }

    private function isManagedPath(?string $path): bool
    {
        $path = str_replace('\\', '/', trim((string) $path));

        return str_starts_with($path, self::DIRECTORY.'/') && ! str_contains($path, '..');
    }

    private function diskName(): string
    {
        return (string) config('vms.category_card_disk', 'public');
    }
}
