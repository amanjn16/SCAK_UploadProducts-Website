<?php

namespace App\Jobs;

use App\Services\DailyCatalogService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDailyCatalogJob
{
    use Dispatchable, Queueable;

    public function handle(DailyCatalogService $service): void
    {
        $service->generate();
    }
}
