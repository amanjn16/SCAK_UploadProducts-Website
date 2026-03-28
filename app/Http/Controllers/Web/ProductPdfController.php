<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductPdfController extends Controller
{
    public function show(Request $request, Product $product): StreamedResponse|BinaryFileResponse
    {
        abort_unless(filled($product->pdf_path), 404);

        $disk = $product->pdf_disk ?: config('scak.storage.disk', 'products');
        abort_unless(Storage::disk($disk)->exists($product->pdf_path), 404);

        $fileName = $product->pdf_name ?: basename((string) $product->pdf_path);

        if ($request->boolean('download')) {
            return Storage::disk($disk)->download($product->pdf_path, $fileName, [
                'Content-Type' => $product->pdf_mime_type ?: 'application/pdf',
                'Cache-Control' => 'public, max-age=31536000',
            ]);
        }

        return Storage::disk($disk)->response($product->pdf_path, $fileName, [
            'Content-Type' => $product->pdf_mime_type ?: 'application/pdf',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
