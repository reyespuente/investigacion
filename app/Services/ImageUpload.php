<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUpload
{
    public function store($file, string $folder, int $maxWidth = 512): string
    {
        $image = imagecreatefromstring(file_get_contents($file->getRealPath()));
        abort_unless($image, 422, 'La imagen no se puede procesar.');
        $scaled = imagescale($image, min($maxWidth, imagesx($image)));
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        ob_start();
        imagewebp($scaled, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($image);
        imagedestroy($scaled);
        $path = 'repository/'.$folder.'/'.Str::uuid().'.webp';
        abort_unless(Storage::put($path, $bytes), 503, 'No se pudo guardar la imagen.');

        return $path;
    }
}
