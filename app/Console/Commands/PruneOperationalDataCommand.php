<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\GeneratedExport;
use App\Models\LegacyAnalyticsEvent;
use App\Models\VisitorSession;
use Illuminate\Console\Command;

class PruneOperationalDataCommand extends Command
{
    protected $signature = 'scak:prune-operational-data
        {--activity-days= : Retain audit logs for this many days}
        {--visitor-days= : Retain non-legacy visitor sessions for this many days}
        {--analytics-days= : Retain legacy analytics events for this many days}
        {--dry-run : Show counts without deleting anything}';

    protected $description = 'Prune old operational activity, visitor, analytics, and failed export data.';

    public function handle(): int
    {
        $activityDays = (int) ($this->option('activity-days') ?: config('scak.retention.activity_days', 180));
        $visitorDays = (int) ($this->option('visitor-days') ?: config('scak.retention.visitor_days', 365));
        $analyticsDays = (int) ($this->option('analytics-days') ?: config('scak.retention.analytics_days', 365));
        $dryRun = (bool) $this->option('dry-run');

        $activityQuery = AuditLog::query()->where('created_at', '<', now()->subDays($activityDays));
        $visitorQuery = VisitorSession::query()
            ->where('is_legacy_import', false)
            ->where('last_activity_at', '<', now()->subDays($visitorDays));
        $analyticsQuery = LegacyAnalyticsEvent::query()->where('occurred_at', '<', now()->subDays($analyticsDays));
        $exportsQuery = GeneratedExport::query()
            ->whereIn('status', [GeneratedExport::STATUS_COMPLETED, GeneratedExport::STATUS_FAILED])
            ->where('created_at', '<', now()->subDays((int) config('scak.retention.export_days', 14)));

        $counts = [
            'activity_logs' => $activityQuery->count(),
            'visitor_sessions' => $visitorQuery->count(),
            'legacy_analytics' => $analyticsQuery->count(),
            'generated_exports' => $exportsQuery->count(),
        ];

        $this->table(['Bucket', 'Rows'], collect($counts)->map(fn ($rows, $bucket) => [$bucket, $rows])->all());

        if ($dryRun) {
            $this->info('Dry run completed.');

            return self::SUCCESS;
        }

        $activityQuery->delete();
        $visitorQuery->delete();
        $analyticsQuery->delete();
        $exportsQuery->delete();

        $this->info('Operational data pruned successfully.');

        return self::SUCCESS;
    }
}
