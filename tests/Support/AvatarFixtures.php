<?php

namespace Tests\Support;

use GdImage;
use RuntimeException;

final class AvatarFixtures
{
    public static function image(string $extension, int $width = 8, int $height = 8, bool $progressive = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('Synthetic image creation failed.');
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 21, 80, 160, 40));
        imageinterlace($image, $progressive);
        ob_start();
        try {
            $ok = match ($extension) {
                'png' => imagepng($image),
                'jpg' => imagejpeg($image, null, 85),
                'webp' => imagewebp($image, null, 85),
            };
            $bytes = ob_get_contents();
            if (! $ok || ! is_string($bytes) || $bytes === '') {
                throw new RuntimeException('Synthetic encode failed.');
            }

            return $bytes;
        } finally {
            ob_end_clean();
        }
    }

    public static function chunk(string $tag, string $payload): string
    {
        return pack('N', strlen($payload)).$tag.$payload.pack('N', crc32($tag.$payload));
    }

    public static function pngOfSize(int $size): string
    {
        $png = self::image('png');
        $prefix = "Comment\0SYNTHETIC-AVATAR-METADATA";
        $payload = $prefix.str_repeat('x', $size - strlen($png) - 12 - strlen($prefix));

        return substr($png, 0, -12).self::chunk('tEXt', $payload).substr($png, -12);
    }

    public static function restartJpeg(): string
    {
        // Independent libjpeg fixture (Pillow 12.3.0, random seed123,
        // 32x32 RGB, quality65, restart_marker_blocks=1); synthetic only.
        $bytes = base64_decode(trim(file_get_contents(__DIR__.'/fixtures/avatar-restart-jpeg.base64')), true);
        if (! is_string($bytes) || strlen($bytes) !== 1159 || hash('sha256', $bytes) !== 'a47ea278093479ec5540aac70043730e778430243073ba3746c5ce3fc214e1e6') {
            throw new RuntimeException('Synthetic JPEG fixture integrity failed.');
        }

        return $bytes;
    }
}
