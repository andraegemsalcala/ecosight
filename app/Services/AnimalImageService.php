<?php

namespace App\Services;

use App\Models\Animal;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AnimalImageService
{
    public const MAX_BYTES = 4_194_304;

    public const MIN_SHORT_SIDE = 400;

    public const MAX_LONG_SIDE = 1200;

    public const MAX_PIXELS = 40_000_000;

    private const DISK = 'public';

    /**
     * @param  array{alt?: ?string, credit?: ?string, license?: ?string, source_url?: ?string, reviewed?: bool}  $metadata
     * @return array{path: string, width: int, height: int}
     */
    public function storeUploadedFile(UploadedFile $file, Animal $animal, array $metadata = []): array
    {
        if (! $file->isValid()) {
            throw new RuntimeException('The image upload did not finish. Please try again.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('Images must be 4 MB or smaller.');
        }

        return $this->storeBinary((string) file_get_contents($file->getRealPath()), $animal, $metadata);
    }

    /**
     * @param  array{alt?: ?string, credit?: ?string, license?: ?string, source_url?: ?string, reviewed?: bool}  $metadata
     * @return array{path: string, width: int, height: int}
     */
    public function storeBinary(string $contents, Animal $animal, array $metadata = []): array
    {
        if (strlen($contents) > self::MAX_BYTES) {
            throw new RuntimeException('Images must be 4 MB or smaller.');
        }

        $image = $this->normalize($contents);
        $path = $this->pathFor($animal);

        try {
            ob_start();
            imagewebp($image['resource'], null, 82);
            $encoded = (string) ob_get_clean();
        } finally {
            imagedestroy($image['resource']);
        }

        if ($encoded === '') {
            throw new RuntimeException('The image could not be converted to WebP.');
        }

        $disk = Storage::disk(self::DISK);
        $oldPath = $animal->image_path;

        if (! $disk->put($path, $encoded)) {
            throw new RuntimeException('The image could not be saved.');
        }

        try {
            $animal->forceFill([
                'image_path' => $path,
                'image_url' => null,
                'image_alt' => filled($metadata['alt'] ?? null) ? $metadata['alt'] : 'Photo of a '.$animal->common_name,
                'image_credit' => $metadata['credit'] ?? null,
                'image_license' => $metadata['license'] ?? null,
                'image_source_url' => $metadata['source_url'] ?? null,
                'image_reviewed_at' => ($metadata['reviewed'] ?? false) ? now() : null,
            ])->save();
        } catch (\Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        if ($oldPath && $oldPath !== $path) {
            $disk->delete($oldPath);
        }

        return [
            'path' => $path,
            'width' => $image['width'],
            'height' => $image['height'],
        ];
    }

    public function remove(Animal $animal): void
    {
        if (filled($animal->image_path)) {
            Storage::disk(self::DISK)->delete($animal->image_path);
        }

        $animal->forceFill([
            'image_path' => null,
            'image_url' => null,
            'image_alt' => null,
            'image_credit' => null,
            'image_license' => null,
            'image_source_url' => null,
            'image_reviewed_at' => null,
        ])->save();
    }

    /**
     * @return array{resource: GdImage, width: int, height: int}
     */
    private function normalize(string $contents): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Images must be JPG, PNG, or WebP.');
        }

        $info = @getimagesizefromstring($contents);

        if ($info === false) {
            throw new RuntimeException('That file is not a readable image.');
        }

        [$width, $height, $type] = $info;

        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('Images must be JPG, PNG, or WebP.');
        }

        if ($width * $height > self::MAX_PIXELS) {
            throw new RuntimeException('That image has too many pixels.');
        }

        if (min($width, $height) < self::MIN_SHORT_SIDE) {
            throw new RuntimeException('Images must be at least 400 pixels on the short side.');
        }

        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('That image could not be decoded.');
        }

        $scale = min(1, self::MAX_LONG_SIDE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $target instanceof GdImage) {
            imagedestroy($source);

            throw new RuntimeException('The image could not be prepared.');
        }

        $white = imagecolorallocate($target, 255, 255, 255);
        imagefill($target, 0, 0, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        return ['resource' => $target, 'width' => $targetWidth, 'height' => $targetHeight];
    }

    private function pathFor(Animal $animal): string
    {
        $disk = Storage::disk(self::DISK);
        $base = Str::slug($animal->common_name) ?: 'animal';

        do {
            $path = 'animals/'.$base.'-'.Str::lower(Str::random(8)).'.webp';
        } while ($disk->exists($path));

        return $path;
    }
}
