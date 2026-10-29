<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validated file storage.
 *
 * Product/banner images: real content type detected with finfo + getimagesize,
 * size and dimension limits, decoded and RE-ENCODED with GD to WebP at fixed
 * widths (strips EXIF/GPS and any embedded payload), random filenames, stored
 * under public/uploads (PHP execution disabled there by .htaccess).
 *
 * Bank-transfer receipts: images (re-encoded) or PDF, stored OUTSIDE the web
 * root in storage/private/receipts and only served to authorised staff.
 */
final class Uploads
{
    public const SIZES = [400, 800, 1600];
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_DIMENSION = 8000;
    private const MIN_DIMENSION = 200;

    public static function root(): string
    {
        return PUBLIC_PATH . '/uploads';
    }

    private static function checkUpload(array $file, int $maxBytes): void
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw ValidationException::one('file', match ($err) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
                UPLOAD_ERR_PARTIAL => 'The upload did not finish. Please try again.',
                UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
                default => 'The file could not be uploaded.',
            });
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp) || (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp))) {
            throw ValidationException::one('file', 'Invalid upload.');
        }
        $size = filesize($tmp) ?: 0;
        if ($size <= 0 || $size > $maxBytes) {
            throw ValidationException::one('file', 'Files must be smaller than ' . round($maxBytes / 1048576) . ' MB.');
        }
    }

    private static function detect(string $tmp): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return (string) $finfo->file($tmp);
    }

    /**
     * Stores an image in all sizes. Returns ['path' => 'products/2026/10/abc…', 'width', 'height'].
     */
    public static function storeImage(array $file, string $folder = 'products'): array
    {
        self::checkUpload($file, (int) Config::get('uploads.max_image_bytes', 8 * 1024 * 1024));
        $tmp = (string) $file['tmp_name'];
        $mime = self::detect($tmp);
        if (!isset(self::IMAGE_TYPES[$mime])) {
            throw ValidationException::one('file', 'Only JPG, PNG or WebP images are allowed.');
        }
        $info = @getimagesize($tmp);
        if (!$info || !in_array($info['mime'] ?? '', array_keys(self::IMAGE_TYPES), true)) {
            throw ValidationException::one('file', 'This file is not a valid image.');
        }
        [$w, $h] = $info;
        if ($w > self::MAX_DIMENSION || $h > self::MAX_DIMENSION) {
            throw ValidationException::one('file', 'Images must be at most ' . self::MAX_DIMENSION . ' px on each side.');
        }
        if ($w < self::MIN_DIMENSION || $h < self::MIN_DIMENSION) {
            throw ValidationException::one('file', 'Images must be at least ' . self::MIN_DIMENSION . ' px on each side.');
        }
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png' => @imagecreatefrompng($tmp),
            'image/webp' => @imagecreatefromwebp($tmp),
        };
        if (!$src) {
            throw ValidationException::one('file', 'This image could not be processed.');
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $src = self::orient($src, $tmp);
            [$w, $h] = [imagesx($src), imagesy($src)];
        }

        $folder = preg_replace('/[^a-z0-9_-]/', '', $folder) ?: 'misc';
        $rel = $folder . '/' . gmdate('Y/m') . '/' . bin2hex(random_bytes(12));
        $dir = self::root() . '/' . dirname($rel);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create upload directory');
        }
        foreach (self::SIZES as $target) {
            $nw = min($target, $w);
            $nh = (int) round($h * $nw / $w);
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            if (!imagewebp($dst, self::root() . '/' . $rel . '-' . $target . '.webp', 84)) {
                throw new \RuntimeException('Image encode failed');
            }
            imagedestroy($dst);
        }
        imagedestroy($src);
        return ['path' => $rel, 'width' => $w, 'height' => $h];
    }

    private static function orient(\GdImage $img, string $file): \GdImage
    {
        $exif = @exif_read_data($file);
        $o = (int) ($exif['Orientation'] ?? 1);
        $rotated = match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
        return $rotated ?: $img;
    }

    public static function deleteImage(string $rel): void
    {
        if (!preg_match('#^[a-z0-9_-]+/\d{4}/\d{2}/[a-f0-9]{24}$#', $rel)) {
            return;
        }
        foreach (self::SIZES as $s) {
            $f = self::root() . '/' . $rel . '-' . $s . '.webp';
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    /** Stores a payment receipt privately. Returns the stored file name. */
    public static function storeReceipt(array $file): string
    {
        self::checkUpload($file, (int) Config::get('uploads.max_receipt_bytes', 5 * 1024 * 1024));
        $tmp = (string) $file['tmp_name'];
        $mime = self::detect($tmp);
        $dir = STORAGE_PATH . '/private/receipts';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $name = bin2hex(random_bytes(16));
        if ($mime === 'application/pdf') {
            $head = (string) file_get_contents($tmp, false, null, 0, 5);
            if ($head !== '%PDF-') {
                throw ValidationException::one('receipt', 'This PDF could not be read.');
            }
            if (!copy($tmp, $dir . '/' . $name . '.pdf')) {
                throw new \RuntimeException('Receipt store failed');
            }
            return $name . '.pdf';
        }
        if (!isset(self::IMAGE_TYPES[$mime]) || !@getimagesize($tmp)) {
            throw ValidationException::one('receipt', 'Upload a JPG, PNG, WebP or PDF receipt.');
        }
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png' => @imagecreatefrompng($tmp),
            'image/webp' => @imagecreatefromwebp($tmp),
        };
        if (!$src) {
            throw ValidationException::one('receipt', 'This image could not be processed.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $nw = min(2000, $w);
        $dst = imagecreatetruecolor($nw, (int) round($h * $nw / $w));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, (int) round($h * $nw / $w), $w, $h);
        imagejpeg($dst, $dir . '/' . $name . '.jpg', 85);
        return $name . '.jpg';
    }

    public static function receiptPath(string $name): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|pdf)$/', $name)) {
            return null;
        }
        $f = STORAGE_PATH . '/private/receipts/' . $name;
        return is_file($f) ? $f : null;
    }
}
