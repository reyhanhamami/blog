<?php

namespace App\Services\Analytics;

use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AnalyticsReport
{
    public function summary(ReportPeriod $period): array
    {
        $from = $period->from->toDateString();
        $to = $period->to->toDateString();
        $daily = DB::table('analytics_daily')->where('page_key', 'site')->whereBetween('date', [$from, $to])->orderBy('date')->get()->keyBy('date');
        $series = collect($period->dates())->map(fn ($date) => ['date' => $date, 'views' => (int) ($daily[$date]->views ?? 0), 'visitors' => (int) ($daily[$date]->visitors ?? 0)])->all();
        $views = (int) $daily->sum('views');
        $unique = $this->unique($from, $to);
        $articleViews = (int) DB::table('analytics_daily')->whereNotNull('post_id')->whereBetween('date', [$from, $to])->sum('views');
        $today = CarbonImmutable::today(config('analytics.timezone'));
        $windows = [];
        foreach (['today' => [$today, $today], 'yesterday' => [$today->subDay(), $today->subDay()], 'seven' => [$today->subDays(6), $today], 'thirty' => [$today->subDays(29), $today]] as $key => [$start, $end]) {
            $start = $start->max($period->from);
            $end = $end->min($period->to);
            $windows[$key] = $start->gt($end) ? 0 : $this->unique($start->toDateString(), $end->toDateString());
        }
        $previousFrom = $period->from->subDays($period->days());
        $previousTo = $period->from->subDay();
        $comparisonAvailable = $period->days() > 0 && $previousFrom->gte($period->startedAt->startOfDay()) && $previousFrom->gte($today->subDays(config('analytics.detail_days') - 1));
        $previous = $comparisonAvailable ? (int) DB::table('analytics_daily')->where('page_key', 'site')->whereBetween('date', [$previousFrom->toDateString(), $previousTo->toDateString()])->sum('views') : null;
        $growth = $previous === null || ($previous === 0 && $views > 0) ? null : ($previous === 0 ? 0 : round(($views - $previous) / $previous * 100, 1));
        $monthly = [];
        foreach ($series as $day) {
            $key = substr($day['date'], 0, 7);
            $monthly[$key] ??= ['month' => $key, 'views' => 0];
            $monthly[$key]['views'] += $day['views'];
        }
        foreach ($monthly as &$month) {
            $start = CarbonImmutable::parse($month['month'].'-01', config('analytics.timezone'))->max($period->from);
            $end = $start->endOfMonth()->min($period->to);
            $month['visitors'] = $this->unique($start->toDateString(), $end->toDateString());
        }
        unset($month);
        $top = $this->articles($period)->where('v.period_views', '>', 0)->orderByDesc('period_views')->orderBy('posts.id')->limit(10)->get();
        $categories = DB::table('analytics_daily as d')->join('posts', 'posts.id', '=', 'd.post_id')->leftJoin('categories', 'categories.id', '=', 'posts.category_id')
            ->whereBetween('d.date', [$from, $to])->selectRaw('categories.name, SUM(d.views) as views')->groupBy('categories.id', 'categories.name')->orderByDesc('views')->limit(10)->get();

        return compact('views', 'unique', 'articleViews', 'series', 'windows', 'previous', 'growth', 'monthly', 'top', 'categories') + [
            'averageVisitors' => $period->days() ? $daily->sum('visitors') / $period->days() : 0,
            'averageViews' => $period->days() ? $views / $period->days() : 0,
            'legacyViews' => (int) Post::withTrashed()->sum('legacy_views'),
            'allTimeViews' => (int) Post::withTrashed()->sum('views'),
            'lastPruned' => DB::table('analytics_state')->where('id', 1)->value('last_pruned_at'),
        ];
    }

    public function unique(string $from, string $to): int
    {
        return DB::table('analytics_site_visitors')->whereBetween('date', [$from, $to])->distinct()->count('visitor_hash');
    }

    public function articles(ReportPeriod $period, ?int $postId = null): Builder
    {
        $range = [$period->from->toDateString(), $period->to->toDateString()];
        $views = DB::table('analytics_daily')->whereNotNull('post_id')->whereBetween('date', $range)
            ->when($postId, fn ($query) => $query->where('post_id', $postId))->groupBy('post_id')->selectRaw('post_id, SUM(views) as period_views');
        $visitors = DB::table('analytics_page_visitors')->whereNotNull('post_id')->whereBetween('date', $range)
            ->when($postId, fn ($query) => $query->where('post_id', $postId))->groupBy('post_id')->selectRaw('post_id, COUNT(DISTINCT visitor_hash) as period_unique');

        return Post::query()->with('category')->leftJoinSub($views, 'v', 'v.post_id', '=', 'posts.id')->leftJoinSub($visitors, 'u', 'u.post_id', '=', 'posts.id')
            ->select('posts.*')->selectRaw('COALESCE(v.period_views, 0) as period_views, COALESCE(u.period_unique, 0) as period_unique');
    }

    public function article(Post $post): array
    {
        $to = CarbonImmutable::today(config('analytics.timezone'));
        $from = $to->subDays(29);
        $started = CarbonImmutable::parse(DB::table('analytics_state')->where('id', 1)->value('started_at'), 'UTC')->setTimezone(config('analytics.timezone'))->startOfDay();
        $daily = DB::table('analytics_daily')->where('post_id', $post->id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()->keyBy('date');
        $series = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $series[] = ['date' => $day->toDateString(), 'views' => $day->lt($started) ? null : (int) ($daily[$day->toDateString()]->views ?? 0)];
        }
        $unique = DB::table('analytics_page_visitors')->where('post_id', $post->id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->distinct()->count('visitor_hash');
        $days = (int) $from->max($started)->diffInDays($to) + 1;

        return ['total' => $post->views, 'legacy' => $post->legacy_views, 'unique' => $unique, 'today' => (int) ($daily[$to->toDateString()]->views ?? 0), 'seven' => (int) $daily->where('date', '>=', $to->subDays(6)->toDateString())->sum('views'), 'thirty' => (int) $daily->sum('views'), 'average' => $daily->sum('views') / max(1, $days), 'series' => $series, 'started' => $started->toDateString()];
    }
}
