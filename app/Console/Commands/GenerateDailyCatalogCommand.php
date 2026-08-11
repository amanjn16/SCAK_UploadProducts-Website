<?php

namespace App\Console\Commands;

use App\Services\DailyCatalogService;
use Illuminate\Console\Command;

class GenerateDailyCatalogCommand extends Command
{
    protected $signature = 'scak:generate-daily-catalog';
    protected $description = 'Generate the balanced customer PDF catalog within the 4.2 MB limit.';

    public function handle(DailyCatalogService $service): int
    {
        $meta = $service->generate();
        $this->info("Daily catalog generated with {$meta['products_count']} products ({$meta['size_mb']} MB).");

        return self::SUCCESS;
    }
}
