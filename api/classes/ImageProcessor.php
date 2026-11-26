<?php
// Comprime las fotos de los comprobantes y genera miniaturas.
// Si el servidor no tiene GD o el formato no es soportado, devuelve null
// y el llamador guarda el archivo original como respaldo.
class ImageProcessor {
    const MAX_FULL = 1600;
    const MAX_THUMB = 420;
    const QUALITY_FULL = 82;
    const QUALITY_THUMB = 75;

    public static function process($tmpPath, $destDir, $baseName) {
        if (!function_exists('imagecreatetruecolor') || !@getimagesize($tmpPath)) {
            return null;
        }
        @ini_set('memory_limit', '256M');

        $info = @getimagesize($tmpPath);
        $src = self::open($tmpPath, $info[2]);
        if (!$src) return null;

        $src = self::autoOrient($src, $tmpPath);
        $w = imagesx($src);
        $h = imagesy($src);

        $destDir = rtrim($destDir, '/');
        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);

        $full = self::resize($src, $w, $h, self::MAX_FULL);
        $fullPath = $destDir . '/' . $baseName . '.jpg';
        if (!@imagejpeg($full, $fullPath, self::QUALITY_FULL)) {
            imagedestroy($full);
            imagedestroy($src);
            return null;
        }
        imagedestroy($full);

        $thumbDir = $destDir . '/thumbs';
        if (!is_dir($thumbDir)) @mkdir($thumbDir, 0755, true);
        $thumb = self::resize($src, $w, $h, self::MAX_THUMB);
        @imagejpeg($thumb, $thumbDir . '/' . $baseName . '.jpg', self::QUALITY_THUMB);
        imagedestroy($thumb);
        imagedestroy($src);

        return $baseName . '.jpg';
    }

    public static function thumbName($filePath) {
        return pathinfo(basename($filePath), PATHINFO_FILENAME) . '.jpg';
    }

    private static function open($path, $type) {
        switch ($type) {
            case IMAGETYPE_JPEG: return @imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:  return @imagecreatefrompng($path);
            case IMAGETYPE_GIF:  return @imagecreatefromgif($path);
            case IMAGETYPE_WEBP: return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
            default: return null;
        }
    }

    private static function autoOrient($src, $path) {
        if (!function_exists('exif_read_data')) return $src;
        $exif = @exif_read_data($path);
        if (empty($exif['Orientation'])) return $src;
        $deg = 0;
        switch ((int)$exif['Orientation']) {
            case 3: $deg = 180; break;
            case 6: $deg = -90; break;
            case 8: $deg = 90; break;
            default: return $src;
        }
        $rotated = @imagerotate($src, $deg, 0);
        if ($rotated) {
            imagedestroy($src);
            return $rotated;
        }
        return $src;
    }

    private static function resize($src, $w, $h, $max) {
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }
}
