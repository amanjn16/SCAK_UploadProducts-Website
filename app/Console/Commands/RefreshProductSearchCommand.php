<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductUpsertService;
use Illuminate\Console\Command;

class RefreshProductSearchCommand extends Command
{
    protected $signature = 'scak:refresh-product-search {--chunk=100}';

    protected $description = 'Refresh denormalized product search text for fast title/tag/SKU lookups.';

    public function handle(ProductUpsertService $productUpsertService): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $count = 0;

        Product::query()
            ->with(['tags:id,name', 'supplier:id,name', 'city:id,name', 'category:id,name', 'topFabric:id,name', 'dupattaFabric:id,name', 'sizes:id,name', 'features:id,name'])
            ->chunkById($chunk, function ($products) use (&$count, $productUpsertService): void {
                foreach ($products as $product) {
                    $productUpsertService->syncSearchText($product);
                    $count++;
                }
            });

        $this->info("Refreshed search text for {$count} products.");

        return self::SUCCESS;
    }
}
