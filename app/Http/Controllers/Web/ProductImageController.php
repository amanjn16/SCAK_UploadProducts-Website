<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageController extends Controller
{
    public function show(Request $request, ProductImage $image): StreamedResponse
    {
        $variant = $request->string('variant')->toString();
        $path = match ($variant) {
            'thumb' => $image->thumb_path ?: $image->medium_path ?: $image->path,
            'medium' => $image->medium_path ?: $image->path,
            default => $image->path,
        };

        abort_unless($path && Storage::disk($image->disk)->exists($path), 404);

        return Storage::disk($image->disk)->response(
            $path,
            $image->original_name ?: basename($image->path),
            [
                'Cache-Control' => 'public, max-age=31536000',
                'Content-Type' => $image->mime_type ?: 'application/octet-stream',
            ],
        );
    }
}
