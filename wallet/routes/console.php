<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('media:warm-public-images {--w=900} {--q=65} {--limit=0}', function () {
    $w = max(64, min(2400, (int) $this->option('w')));
    $q = max(30, min(90, (int) $this->option('q')));
    $limit = max(0, (int) $this->option('limit'));

    $disk = Storage::disk('public');
    $files = $disk->allFiles();
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (! function_exists('imagecreatetruecolor')) {
        $this->error('GD is not available; cannot generate optimized variants.');
        return 1;
    }

    $disk->makeDirectory('.imgcache');

    $processed = 0;
    $skipped = 0;
    foreach ($files as $rel) {
        if (str_starts_with($rel, '.imgcache/')) { $skipped++; continue; }

        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (! in_array($ext, $allowed, true)) { $skipped++; continue; }

        $hash = sha1($rel.'|w='.$w.'|q='.$q);
        $cacheRel = '.imgcache/'.$hash.'.jpg';
        if ($disk->exists($cacheRel)) { $skipped++; continue; }

        $sourcePath = $disk->path($rel);
        [$srcW, $srcH, $type] = @getimagesize($sourcePath) ?: [0, 0, null];
        if (! $srcW || ! $srcH) { $skipped++; continue; }

        $dstW = min($w, (int) $srcW);
        $dstH = (int) round($srcH * ($dstW / $srcW));

        $srcImg = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            IMAGETYPE_GIF  => @imagecreatefromgif($sourcePath),
            default => null,
        };
        if (! $srcImg) { $skipped++; continue; }

        $dstImg = imagecreatetruecolor($dstW, $dstH);
        $white = imagecolorallocate($dstImg, 255, 255, 255);
        imagefilledrectangle($dstImg, 0, 0, $dstW, $dstH, $white);
        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, (int) $srcW, (int) $srcH);

        $outPath = $disk->path($cacheRel);
        @imagejpeg($dstImg, $outPath, $q);

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        $processed++;
        if ($processed % 50 === 0) {
            $this->info("Processed {$processed} images…");
        }
        if ($limit > 0 && $processed >= $limit) {
            break;
        }
    }

    $this->info("Done. Processed={$processed}, skipped={$skipped}. Cache: storage/app/public/.imgcache");
    return 0;
})->purpose('Warm optimized image cache for public disk');
