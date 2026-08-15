<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupMediaCommand extends Command
{
    protected $signature = 'scak:cleanup-media
        {--days-exports= : Delete generated exports older than this many days}
        {--dry-run : Show what would be deleted without deleting anything}';

    protected $description = 'Remove stale exports and orphaned media files to keep storage usage in check.';

    public function handle(): int
    {
        $diskName = config('scak.storage.disk', 'products');
        $disk = Storage::disk($diskName);
        $dryRun = (bool) $this->option('dry-run');
        $days = (int) ($this->option('days-exports') ?: config('scak.retention.export_days', 14));

        $referenced = ProductImage::query()
            ->get(['path', 'medium_path', 'thumb_path'])
            ->flatMap(fn ($image) => array_filter([$image->path, $image->medium_path, $image->thumb_path]))
            ->unique()
            ->values();

        $allFiles = collect($disk->allFiles());
        $staleExports = $allFiles->filter(function (string $path) use ($disk, $days): bool {
            if (! str_starts_with($path, 'exports/')) {
                return false;
            }

            $lastModified = $disk->lastModified($path);

            return $lastModified < now()->subDays($days)->timestamp;
        })->values();

        $orphanedFiles = $allFiles
            ->reject(fn (string $path) => str_starts_with($path, 'exports/'))
            ->reject(fn (string $path) => $referenced->contains($path))
            ->values();

        $this->table(
            ['Bucket', 'Count'],
            [
                ['stale_exports', $staleExports->count()],
                ['orphaned_files', $orphanedFiles->count()],
            ],
        );

        if ($dryRun) {
            $this->info('Dry run completed.');

            return self::SUCCESS;
        }

        if ($staleExports->isNotEmpty()) {
            $disk->delete($staleExports->all());
        }

        if ($orphanedFiles->isNotEmpty()) {
            $disk->delete($orphanedFiles->all());
        }

        $this->info('Media cleanup completed.');

        return self::SUCCESS;
    }
}
