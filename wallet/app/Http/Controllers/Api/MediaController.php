<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    /**
     * Serve an optimized JPEG variant for public-disk files referenced via /storage/<path>.
     *
     * Query:
     * - path: string (required) relative path inside public disk
     * - w: int (optional) max width, default 900
     * - q: int (optional) jpeg quality 1..90, default 65
     */
    public function image(Request $request): Response
    {
        $rel = ltrim((string) $request->query('path', ''), '/');
        if ($rel === '' || str_contains($rel, '..')) {
            abort(400, 'Invalid path.');
        }

        $w = (int) $request->query('w', 900);
        $w = max(64, min(2400, $w));

        $q = (int) $request->query('q', 65);
        $q = max(30, min(90, $q));

        $disk = Storage::disk('public');
        if (! $disk->exists($rel)) {
            abort(404);
        }

        $sourcePath = $disk->path($rel);
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (! in_array($ext, $allowed, true)) {
            // For non-images (or unknown), just 404 to avoid proxying arbitrary files.
            abort(404);
        }

        // If GD isn't available, return original file as-is.
        if (! function_exists('imagecreatetruecolor')) {
            return response()->file($sourcePath, [
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $hash = sha1($rel.'|w='.$w.'|q='.$q);
        $cacheRel = '.imgcache/'.$hash.'.jpg';

        if ($disk->exists($cacheRel)) {
            return response()->file($disk->path($cacheRel), [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        [$srcW, $srcH, $type] = @getimagesize($sourcePath) ?: [0, 0, null];
        if (! $srcW || ! $srcH) {
            abort(404);
        }

        $dstW = min($w, (int) $srcW);
        $dstH = (int) round($srcH * ($dstW / $srcW));

        $srcImg = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            IMAGETYPE_GIF  => @imagecreatefromgif($sourcePath),
            default => null,
        };

        if (! $srcImg) {
            abort(404);
        }

        $dstImg = imagecreatetruecolor($dstW, $dstH);
        // Fill with white background (for PNG transparency etc.)
        $white = imagecolorallocate($dstImg, 255, 255, 255);
        imagefilledrectangle($dstImg, 0, 0, $dstW, $dstH, $white);

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, (int) $srcW, (int) $srcH);

        // Ensure cache directory exists (public disk path points to storage/app/public).
        $disk->makeDirectory('.imgcache');
        $outPath = $disk->path($cacheRel);
        @imagejpeg($dstImg, $outPath, $q);

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return response()->file($outPath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}

