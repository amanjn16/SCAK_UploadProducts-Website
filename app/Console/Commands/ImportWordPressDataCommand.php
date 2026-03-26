<?php

namespace App\Console\Commands;

use App\Services\WordPressImportService;
use Illuminate\Console\Command;

class ImportWordPressDataCommand extends Command
{
    protected $signature = 'scak:import-wordpress {--archive-analytics : Export legacy analytics and visitor history to archive files}';

    protected $description = 'Import products, users, and attribute data from the legacy WordPress / WooCommerce database.';

    public function __construct(private readonly WordPressImportService $wordPressImportService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting WordPress import...');

        $summary = $this->wordPressImportService->import(
            archiveAnalytics: (bool) $this->option('archive-analytics'),
        );

        foreach ($summary as $section => $result) {
            if (is_array($result)) {
                $this->line($section.': '.json_encode($result));
                continue;
            }

            $this->line($section.': '.$result);
        }

        $this->info('WordPress import completed.');

        return self::SUCCESS;
    }
}
