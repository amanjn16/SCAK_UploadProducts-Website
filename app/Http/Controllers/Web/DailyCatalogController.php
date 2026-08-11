<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DailyCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyCatalogController extends Controller
{
    public function show(Request $request): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists(DailyCatalogService::FILE_PATH), 404);

        $headers = ['Content-Type' => 'application/pdf', 'Cache-Control' => 'public, max-age=900'];
        $name = 'SCAK-Daily-Catalog-'.now()->format('Y-m-d').'.pdf';

        return $request->boolean('download')
            ? Storage::disk('local')->download(DailyCatalogService::FILE_PATH, $name, $headers)
            : Storage::disk('local')->response(DailyCatalogService::FILE_PATH, $name, $headers);
    }
}
