<?php

namespace Modules\Identity\Services\Avatar;

use App\Support\ApiResponse;
use GdImage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AvatarImageNormalizer
{
    public function fromUpload(#[SensitiveParameter] UploadedFile $file): NormalizedAvatar
    {
        $extension = AvatarFormat::extension($file->getClientOriginalName());
        if ($extension === null || ! $file->isValid()) {
            $this->invalid();
        }
        $size = $file->getSize();
        if (! is_int($size) || $size < 1 || $size > AvatarFormat::LIMIT) {
            $this->invalid();
        }
        // Bound the read itself, not just the reported size.
        $bytes = @file_get_contents($file->getPathname(), false, null, 0, AvatarFormat::LIMIT + 1);
        if (! is_string($bytes) || strlen($bytes) !== $size) {
            $this->invalid();
        }

        return $this->normalize($bytes, $extension);
    }

    public function normalize(#[SensitiveParameter] string $bytes, string $extension): NormalizedAvatar
    {
        if (! AvatarFormat::structure($bytes, $extension)) {
            $this->invalid();
        }
        $mime = AvatarFormat::mime($extension);
        $warning = false;
        set_error_handler(static function () use (&$warning): bool {
            $warning = true;

            return true;
        });
        try {
            $header = getimagesizefromstring($bytes);
            if ($warning || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $mime
                || ! is_array($header) || ($header['mime'] ?? null) !== $mime
                || $header[0] < 1 || $header[1] < 1 || $header[0] > 2048 || $header[1] > 2048
                || $header[0] * $header[1] > 4194304) {
                $this->invalid();
            }
            $source = imagecreatefromstring($bytes);
            if ($warning || ! $source instanceof GdImage || imagesx($source) !== $header[0] || imagesy($source) !== $header[1]) {
                $this->invalid();
            }
            $width = imagesx($source);
            $height = imagesy($source);
            $ratio = min(1, 512 / max($width, $height));
            $newWidth = max(1, (int) round($width * $ratio));
            $newHeight = max(1, (int) round($height * $ratio));
            $output = imagecreatetruecolor($newWidth, $newHeight);
            if (! $output instanceof GdImage || ! imagealphablending($output, false) || ! imagesavealpha($output, true)
                || ! imagefill($output, 0, 0, imagecolorallocatealpha($output, 0, 0, 0, 127))
                || ! imagecopyresampled($output, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height) || $warning) {
                throw new RuntimeException('Avatar normalization failed.');
            }
            unset($source);
            ob_start();
            try {
                $ok = match ($extension) {
                    'jpg' => imagejpeg($output, null, 85),
                    'png' => imagepng($output, null, 6),
                    'webp' => imagewebp($output, null, 85),
                };
                $encoded = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            unset($output);
            if (! $ok || $warning || ! is_string($encoded) || ! AvatarFormat::structure($encoded, $extension)
                || (new \finfo(FILEINFO_MIME_TYPE))->buffer($encoded) !== $mime) {
                throw new RuntimeException('Avatar encoding failed.');
            }
            $result = getimagesizefromstring($encoded);
            if ($warning || ! is_array($result) || $result[0] !== $newWidth || $result[1] !== $newHeight) {
                throw new RuntimeException('Avatar encoded dimensions invalid.');
            }

            return new NormalizedAvatar($extension, $encoded);
        } finally {
            restore_error_handler();
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['avatar' => ApiResponse::text('api.errors.validation')]);
    }
}
