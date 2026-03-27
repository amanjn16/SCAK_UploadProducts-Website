<?php

namespace App\Jobs;

use App\Models\GeneratedExport;
use App\Models\Product;
use App\Services\ProductPdfService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class GenerateProductPdfJob
{
    use Dispatchable, Queueable;

    public function __construct(
        private readonly int $generatedExportId,
        private readonly array $productIds,
    ) {}

    public function handle(ProductPdfService $productPdfService): void
    {
        $generatedExport = GeneratedExport::query()->find($this->generatedExportId);

        if (! $generatedExport) {
            return;
        }

        $generatedExport->update([
            'status' => GeneratedExport::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        try {
            $products = Product::query()
                ->with(['supplier', 'city', 'category', 'images', 'tags'])
                ->whereIn('id', $this->productIds)
                ->get();

            $pdf = $productPdfService->generate($products);

            $generatedExport->update([
                'status' => GeneratedExport::STATUS_COMPLETED,
                'result_disk' => $pdf['disk'],
                'result_path' => $pdf['path'],
                'result_url' => $pdf['url'],
                'completed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $generatedExport->update([
                'status' => GeneratedExport::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }
}
