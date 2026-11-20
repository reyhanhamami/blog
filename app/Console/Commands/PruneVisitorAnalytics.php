<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneVisitorAnalytics extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Prune anonymous daily membership after 90 days and aggregates after three years';

    public function handle(): int
    {
        $today = CarbonImmutable::today(config('analytics.timezone'));
        foreach (['analytics_page_visitors', 'analytics_site_visitors'] as $table) {
            // Small transactions bound lock time on shared MySQL hosting.
            do {
                $deleted = DB::table($table)->where('date', '<', $today->subDays(config('analytics.detail_days') - 1)->toDateString())->limit(500)->delete();
            } while ($deleted === 500);
        }
        foreach (['analytics_receipts' => ['expires_at', now('UTC')->format('Y-m-d H:i:s')], 'analytics_daily' => ['date', $today->subDays(config('analytics.aggregate_days') - 1)->toDateString()]] as $table => [$column, $cutoff]) {
            do {
                $deleted = DB::table($table)->where($column, '<', $cutoff)->limit(500)->delete();
            } while ($deleted === 500);
        }
        DB::table('analytics_state')->where('id', 1)->update(['last_pruned_at' => now('UTC')->format('Y-m-d H:i:s')]);
        $this->info('Analytics retention applied; legacy and all-time article views preserved.');

        return self::SUCCESS;
    }
}
