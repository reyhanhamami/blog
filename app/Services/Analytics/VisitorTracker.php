<?php

namespace App\Services\Analytics;

use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VisitorTracker
{
    public function eligible(Request $request): bool
    {
        $purpose = strtolower($request->header('Purpose', '').' '.$request->header('Sec-Purpose', '').' '.$request->header('X-Moz', ''));

        return config('analytics.enabled') && $request->header('DNT') !== '1' && $request->header('Sec-GPC') !== '1'
            && ! str_contains($purpose, 'prefetch') && ! str_contains($purpose, 'prerender')
            && ! ($request->user() && $request->user()->role !== 'reader')
            && $request->userAgent() && ! preg_match('/bot|crawler|spider|slurp|bingpreview|headless|lighthouse|pagespeed|curl|wget|python|postman/i', $request->userAgent());
    }

    public function hash(string $visitor): string
    {
        return hash_hmac('sha256', 'visitor-analytics|'.$visitor, config('app.key'));
    }

    public function receipt(string $visitor, string $path, ?int $postId): string
    {
        return Crypt::encryptString(json_encode([
            'visitor' => $this->hash($visitor), 'path' => $path, 'post' => $postId,
            'expires' => now('UTC')->addHours(config('analytics.receipt_hours'))->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    public function confirm(Request $request): void
    {
        if (! $this->eligible($request) || $request->header('Sec-Fetch-Site') === 'cross-site') {
            return;
        }
        $visitor = $request->cookie(config('analytics.cookie'));
        if (! is_string($visitor) || ! preg_match('/\A[a-f0-9]{64}\z/', $visitor)
            || ! is_string($request->input('id')) || ! preg_match('/\A[a-f0-9-]{36}\z/', $request->input('id'))
            || ! is_string($request->input('receipt')) || strlen($request->input('receipt')) > 4096) {
            return;
        }
        try {
            $receipt = json_decode(Crypt::decryptString($request->input('receipt')), true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return;
        }
        if (($receipt['expires'] ?? 0) < now('UTC')->timestamp || ! hash_equals($this->hash($visitor), $receipt['visitor'] ?? '')
            || ! is_string($receipt['path'] ?? null) || ! str_starts_with($receipt['path'], '/')) {
            return;
        }
        if ($receipt['post'] && ! Post::published()->whereKey($receipt['post'])->exists()) {
            return;
        }
        $this->record($receipt['visitor'], $receipt['path'], $receipt['post'], hash('sha256', $request->input('receipt').'|'.$request->input('id')));
    }

    public function record(string $visitorHash, string $path, ?int $postId, string $receiptId): void
    {
        $now = CarbonImmutable::now('UTC');
        $stamp = $now->format('Y-m-d H:i:s');
        $date = $now->setTimezone(config('analytics.timezone'))->toDateString();
        $page = hash('sha256', $path);
        DB::transaction(function () use ($visitorHash, $postId, $receiptId, $now, $stamp, $date, $page) {
            if (! DB::table('analytics_receipts')->insertOrIgnore(['id' => $receiptId, 'expires_at' => $now->addHours(config('analytics.receipt_hours'))->format('Y-m-d H:i:s')])) {
                return;
            }
            if ($postId && ! Post::published()->whereKey($postId)->lockForUpdate()->first(['id'])) {
                return;
            }
            $identity = ['date' => $date, 'page_key' => $page, 'visitor_hash' => $visitorHash];
            DB::table('analytics_page_visitors')->insertOrIgnore($identity + ['post_id' => $postId, 'views' => 0]);
            $previous = DB::table('analytics_page_visitors')->where($identity)->lockForUpdate()->first();
            if ($previous->last_seen_at && CarbonImmutable::parse($previous->last_seen_at, 'UTC')->gt($now->subSeconds(config('analytics.repeat_seconds')))) {
                return;
            }
            $newPageVisitor = $previous->views === 0 || (int) $previous->views === 0;
            DB::table('analytics_page_visitors')->where($identity)->increment('views', 1, ['last_seen_at' => $stamp]);
            $newSiteVisitor = DB::table('analytics_site_visitors')->insertOrIgnore(['date' => $date, 'visitor_hash' => $visitorHash]);
            foreach (['site' => (bool) $newSiteVisitor, $page => $newPageVisitor] as $key => $isNew) {
                $daily = ['date' => $date, 'page_key' => $key];
                DB::table('analytics_daily')->insertOrIgnore($daily + ['post_id' => $key === 'site' ? null : $postId]);
                DB::table('analytics_daily')->where($daily)->incrementEach(['views' => 1, 'visitors' => $isNew ? 1 : 0], ['last_seen_at' => $stamp]);
            }
            if ($postId) {
                DB::table('posts')->where('id', $postId)->incrementEach(['views' => 1, 'tracked_views' => 1], ['last_viewed_at' => $stamp]);
            }
        }, 3);
    }

    public static function failure(Throwable $exception): void
    {
        Log::error('Visitor analytics write failed', ['exception_type' => get_class($exception), 'code' => (string) $exception->getCode()]);
    }
}
