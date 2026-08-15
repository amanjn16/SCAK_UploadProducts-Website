<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductPdfService
{
    public function generate(Collection $products): array
    {
        $disk = config('scak.storage.disk', 'products');
        $fileName = 'exports/scak-products-'.Str::uuid().'.pdf';

        $pdf = Pdf::loadView('pdf.products', [
            'products' => $products,
        ]);

        Storage::disk($disk)->put($fileName, $pdf->output());

        return [
            'disk' => $disk,
            'path' => $fileName,
            'url' => Storage::disk($disk)->url($fileName),
        ];
    }
}
