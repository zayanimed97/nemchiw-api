<?php

namespace Modules\Media\Images;

use Modules\Shared\Errors\ApiErrorCode;
use Modules\Shared\Errors\ApiException;

/**
 * Turns an uploaded photo into a clean JPEG we produced ourselves: only JPEG and PNG
 * accepted, size checked from the header before decoding (no decompression bombs),
 * orientation applied, scaled down, and re-encoded so EXIF/GPS and anything smuggled
 * into the file never reach the disk.
 */
final class Reencoder
{
    // Far above what the app sends (1080 × 1350) yet bounded: GD needs ~5 bytes a pixel
    // and more while scaling, and shared hosting gives PHP a few hundred MB at most.
    private const MAX_SIDE = 4096;

    private const MAX_PIXELS = 16_000_000;

    private const LONG_SIDE = 1600;

    private const QUALITY = 85;

    public function toJpeg(string $bytes, int $longSide = self::LONG_SIDE): string
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw self::invalid();
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || max($width, $height) > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            throw self::invalid();
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw self::invalid();
        }

        // Scale first, then rotate: rotating the full-size image would double the memory.
        if (max($width, $height) > $longSide) {
            $landscape = $width >= $height;
            $image = imagescale(
                $image,
                $landscape ? $longSide : (int) round($width * $longSide / $height),
                $landscape ? (int) round($height * $longSide / $width) : $longSide,
            );
        }
        $image = $this->upright($image, $bytes, $info[2]);

        ob_start();
        imagejpeg($image, null, self::QUALITY);

        return (string) ob_get_clean();
    }

    /** Phones store "rotate me" in EXIF; bake it in, since EXIF is about to be dropped. */
    private function upright(\GdImage $image, string $bytes, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    private static function invalid(): ApiException
    {
        return new ApiException(ApiErrorCode::Validation, 'The photo must be a JPEG or PNG image');
    }
}
