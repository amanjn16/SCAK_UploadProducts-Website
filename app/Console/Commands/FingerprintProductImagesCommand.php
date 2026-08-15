<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FingerprintProductImagesCommand extends Command
{
    protected $signature = 'scak:fingerprint-product-images {--force : Recalculate existing fingerprints}';

    protected $description = 'Calculate exact SHA-256 fingerprints for product images';

    public function handle(): int
    {
        $scanned = $missing = $failed = 0;
        ProductImage::query()
            ->when(! $this->option('force'), fn ($query) => $query->whereNull('sha256'))
            ->orderBy('id')->chunkById(100, function ($images) use (&$scanned, &$missing, &$failed): void {
                foreach ($images as $image) {
                    try {
                        $disk = Storage::disk($image->disk);
                        if (! $disk->exists($image->path)) {
                            $missing++;

                            continue;
                        }
                        $stream = $disk->readStream($image->path);
                        if (! is_resource($stream)) {
                            $failed++;

                            continue;
                        }
                        $context = hash_init('sha256');
                        hash_update_stream($context, $stream);
                        fclose($stream);
                        $image->forceFill(['sha256' => hash_final($context), 'fingerprinted_at' => now()])->save();
                        $scanned++;
                    } catch (\Throwable) {
                        $failed++;
                    }
                }
            });

        $groups = ProductImage::query()->whereNotNull('sha256')->select('sha256')
            ->groupBy('sha256')->havingRaw('COUNT(DISTINCT product_id) > 1')->get()->count();
        $this->info("Fingerprint scan complete: {$scanned} updated, {$missing} missing, {$failed} failed, {$groups} duplicate groups.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
