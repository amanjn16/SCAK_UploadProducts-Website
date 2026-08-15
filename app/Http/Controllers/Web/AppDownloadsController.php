<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AppReleaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppDownloadsController extends Controller
{
    public function __construct(
        private readonly AppReleaseService $appReleaseService,
    ) {}

    public function index(Request $request): View
    {
        return view('storefront.apps', [
            'customer' => $request->user(),
            'releases' => $this->appReleaseService->all($request),
        ]);
    }

    public function download(Request $request, string $platform): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $file = $this->appReleaseService->fileForPlatform($platform);
        abort_unless($file !== null, 404);

        return Storage::disk($file['disk'])->download($file['path'], $file['name'], [
            'Content-Type' => $file['mime'],
        ]);
    }
}

