<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\ProductUpsertService;
use Illuminate\Console\Command;

class OptimizeProductImagesCommand extends Command
{
    protected $signature = 'scak:optimize-product-images
        {--dry-run : Show how many images would be processed without writing}
        {--chunk=100 : Number of images to process per batch}';

    protected $description = 'Resize and recompress stored product images to save disk space.';

    public function __construct(private readonly ProductUpsertService $productUpsertService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->warn('GD image functions are not available on this server. Skipping optimization.');

            return self::SUCCESS;
        }

        $chunkSize = max(10, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $query = ProductImage::query()->with('product:id,sku');
        $summary = [
            'selected' => $query->count(),
            'processed' => 0,
            'optimized' => 0,
            'missing' => 0,
            'bytes_saved' => 0,
        ];

        $this->info($dryRun ? 'Running product image optimization dry run...' : 'Optimizing product images...');

        $query->chunkById($chunkSize, function ($images) use (&$summary, $dryRun) {
            foreach ($images as $image) {
                $summary['processed']++;

                if ($dryRun) {
                    continue;
                }

                $result = $this->productUpsertService->optimizeStoredImage($image);

                if ($result['missing'] ?? false) {
                    $summary['missing']++;
                    continue;
                }

                if ($result['optimized'] ?? false) {
                    $summary['optimized']++;
                    $summary['bytes_saved'] += (int) ($result['bytes_saved'] ?? 0);
                }
            }
        });

        $this->table(
            ['Selected', 'Processed', 'Optimized', 'Missing', 'Saved MB'],
            [[
                $summary['selected'],
                $summary['processed'],
                $summary['optimized'],
                $summary['missing'],
                number_format($summary['bytes_saved'] / 1048576, 2),
            ]]
        );

        $this->info($dryRun ? 'Dry run completed.' : 'Product image optimization completed.');

        return self::SUCCESS;
    }
}
