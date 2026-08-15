<?php

namespace App\Console\Commands;

use App\Services\WordPressImportService;
use Illuminate\Console\Command;

class ImportWordPressDataCommand extends Command
{
    protected $signature = 'scak:import-wordpress
        {--days= : Import only records newer than the given number of days}
        {--full-history : Import all available historical records}
        {--basis=modified : Use modified, created, or published date for product selection}
        {--import-users : Import legacy admin whitelist and verified users}
        {--import-visitors : Import legacy visitor sessions}
        {--import-analytics : Import legacy analytics events}
        {--archive-analytics : Export legacy analytics and visitor history to archive files}
        {--dry-run : Show counts without writing any data}';

    protected $description = 'Import legacy WordPress / WooCommerce data into the new SCAK platform.';

    public function __construct(private readonly WordPressImportService $wordPressImportService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting WordPress import...');

        $summary = $this->wordPressImportService->import([
            'days' => $this->option('days'),
            'full_history' => (bool) $this->option('full-history'),
            'basis' => (string) $this->option('basis'),
            'import_users' => (bool) $this->option('import-users'),
            'import_visitors' => (bool) $this->option('import-visitors'),
            'import_analytics' => (bool) $this->option('import-analytics'),
            'archive_analytics' => (bool) $this->option('archive-analytics'),
            'dry_run' => (bool) $this->option('dry-run'),
        ]);

        foreach ($summary as $section => $result) {
            if (is_array($result)) {
                $this->line($section.': '.json_encode($result, JSON_UNESCAPED_SLASHES));
                continue;
            }

            $this->line($section.': '.$result);
        }

        $this->info('WordPress import completed.');

        return self::SUCCESS;
    }
}
