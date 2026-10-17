<?php
namespace App\Helpers;

/**
 * Safe image upload (N1).
 *
 * Never trusts anything the client sends about the file: the extension is
 * derived from the detected image type, the stored name is random, and the
 * content must decode as a real jpg/png/webp image.
 */
class ImageUpload {

    public const MAX_BYTES = 2 * 1024 * 1024; // 2 MB
    public const MAX_PIXELS_SIDE = 6000;      // guards against decompression bombs

    // Detected MIME type => extension we store it under.
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @param array  $file    One entry of $_FILES.
     * @param string $subdir  Folder under public/uploads/, e.g. 'equipment'.
     * @return array ['ok' => bool, 'path' => 'uploads/<subdir>/<name>' | null, 'error' => string|null]
     */
    public static function save(array $file, string $subdir): array {
        if (!preg_match('/^[a-z0-9_-]+$/i', $subdir)) {
            return self::fail('Invalid upload folder.');
        }

        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return self::fail(self::sizeMessage());
        }
        if ($err !== UPLOAD_ERR_OK) {
            return self::fail('Image upload failed. Please try again.');
        }

        $tmp = $file['tmp_name'] ?? '';
        if (!is_string($tmp) || !is_uploaded_file($tmp)) {
            return self::fail('Image upload failed. Please try again.');
        }

        $size = filesize($tmp);
        if ($size === false || $size <= 0) {
            return self::fail('The image file is empty.');
        }
        if ($size > self::MAX_BYTES) {
            return self::fail(self::sizeMessage());
        }

        // Detect the real type from file contents, twice, and require agreement.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $info = @getimagesize($tmp); // false if it is not a decodable image
        if ($info === false || !isset(self::ALLOWED[$mime]) || ($info['mime'] ?? '') !== $mime) {
            return self::fail('Only JPG, PNG or WebP images are allowed.');
        }

        if (($info[0] ?? 0) > self::MAX_PIXELS_SIDE || ($info[1] ?? 0) > self::MAX_PIXELS_SIDE) {
            return self::fail('Image dimensions are too large (max ' . self::MAX_PIXELS_SIDE . ' px per side).');
        }

        $dir = __DIR__ . '/../../public/uploads/' . $subdir . '/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log('ImageUpload: cannot create ' . $dir);
            return self::fail('Image upload failed. Please try again.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime];
        $dest = $dir . $name;

        // Preferred: decode and re-encode, which drops anything appended to or
        // hidden inside the file and strips EXIF data such as GPS location.
        $re = self::reencode($tmp, $mime, $dest);
        if ($re === 'corrupt') {
            return self::fail('The image could not be read. Please use a different file.');
        }
        if ($re === 'unavailable') {
            // GD (or its WebP support) is missing: store the validated file as is.
            // The random name, safe extension and uploads/.htaccess still apply.
            if (!move_uploaded_file($tmp, $dest)) {
                error_log('ImageUpload: move_uploaded_file failed for ' . $dest);
                return self::fail('Image upload failed. Please try again.');
            }
        } elseif ($re !== 'ok') {
            error_log('ImageUpload: re-encode failed for ' . $dest);
            return self::fail('Image upload failed. Please try again.');
        }
        @chmod($dest, 0644);

        return ['ok' => true, 'path' => 'uploads/' . $subdir . '/' . $name, 'error' => null];
    }

    /** @return string 'ok' | 'corrupt' | 'unavailable' | 'error' */
    private static function reencode(string $tmp, string $mime, string $dest): string {
        if (!function_exists('imagecreatefromstring')) {
            return 'unavailable';
        }
        if ($mime === 'image/webp' && !function_exists('imagewebp')) {
            return 'unavailable';
        }
        $data = file_get_contents($tmp);
        $img = $data === false ? false : @imagecreatefromstring($data);
        if ($img === false) {
            return 'corrupt';
        }
        switch ($mime) {
            case 'image/jpeg':
                $ok = imagejpeg($img, $dest, 88);
                break;
            case 'image/png':
                imagealphablending($img, false);
                imagesavealpha($img, true);
                $ok = imagepng($img, $dest, 6);
                break;
            default: // image/webp
                $ok = imagewebp($img, $dest, 85);
        }
        imagedestroy($img);
        return $ok ? 'ok' : 'error';
    }

    private static function sizeMessage(): string {
        return 'Image is too large. Maximum size is ' . (self::MAX_BYTES / 1024 / 1024) . ' MB.';
    }

    private static function fail(string $msg): array {
        return ['ok' => false, 'path' => null, 'error' => $msg];
    }
}
