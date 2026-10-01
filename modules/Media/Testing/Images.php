<?php

namespace Modules\Media\Testing;

use Illuminate\Http\UploadedFile;

/** Real image bytes for upload tests, made with GD. */
final class Images
{
    public static function jpeg(int $width = 800, int $height = 1000): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 200, 120, 40));
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    public static function png(int $width = 400, int $height = 500): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public static function gif(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagegif($image);

        return (string) ob_get_clean();
    }

    /** A JPEG carrying an EXIF segment with a fake GPS marker right after the start of image. */
    public static function jpegWithExif(): string
    {
        $jpeg = self::jpeg();
        $payload = "Exif\0\0GPSLatitude=36.8065;GPSLongitude=10.1815";
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    /** A PNG whose header claims 20000 × 20000 pixels (a decompression bomb). */
    public static function hugePngHeader(): string
    {
        $png = self::png(10, 10);

        return substr($png, 0, 16).pack('NN', 20000, 20000).substr($png, 24);
    }

    public static function upload(string $bytes, string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }
}
