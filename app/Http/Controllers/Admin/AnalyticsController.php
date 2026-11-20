<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Analytics\AnalyticsReport;
use App\Services\Analytics\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private function data(Request $request, AnalyticsReport $report): array
    {
        abort_unless($request->user()?->role === 'superadmin', 403);
        $period = ReportPeriod::fromRequest($request);
        $summary = $report->summary($period);
        $articles = $report->articles($period)
            ->when($request->filled('q'), fn ($query) => $query->where('posts.title', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('category'), fn ($query) => $query->where('posts.category_id', $request->integer('category')))
            ->orderByDesc(($request->input('sort') ?: 'period_views') === 'views' ? 'posts.views' : ($request->input('sort') ?: 'period_views'))->orderBy('posts.id');

        return compact('period', 'summary', 'articles');
    }

    public function index(Request $request, AnalyticsReport $report)
    {
        $data = $this->data($request, $report);
        $data['articles'] = $data['articles']->paginate(15)->withQueryString();

        return response()->view('admin.analytics', $data + ['categories' => Category::orderBy('name')->get()])->header('Cache-Control', 'private, no-store');
    }

    public function print(Request $request, AnalyticsReport $report)
    {
        return response()->view('admin.analytics-print', $this->data($request, $report))->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, AnalyticsReport $report)
    {
        // Validate before sending download headers, then read one consistent snapshot.
        abort_unless($request->user()?->role === 'superadmin', 403);
        $period = ReportPeriod::fromRequest($request);

        return response()->streamDownload(function () use ($request, $report) {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) && DB::transactionLevel() === 0) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            DB::transaction(function () use ($request, $report) {
                $this->writeCsv($this->data($request, $report));
            });
        }, 'besofton-analytics-'.$period->to->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function writeCsv(array $data): void
    {
        $stream = fopen('php://output', 'w');
        fwrite($stream, "\xEF\xBB\xBF");
        $write = function (array $cells) use ($stream) {
            $cells = array_map(function ($cell) {
                if (is_string($cell) && preg_match('/^[\s]*[=+@\-]/u', $cell)) {
                    return "'".$cell;
                }

                return $cell;
            }, $cells);
            fputcsv($stream, $cells, ';', '"', '');
        };
        $write(['Besofton Insights', 'Visitor Analytics']);
        $write(['Dibuat WIB', now('Asia/Jakarta')->format('Y-m-d H:i:s')]);
        $write(['Periode tercatat', $data['period']->from->toDateString(), $data['period']->to->toDateString()]);
        $write(['Analytics tersedia sejak', $data['period']->startedAt->toIso8601String()]);
        $write(['Definisi', 'Unique visitors adalah estimasi browser dengan identifier anonim, DISTINCT selama periode; bukan penjumlahan unique harian.']);
        $write(['Definisi', 'Page views adalah kunjungan tampil terkonfirmasi; pengulangan halaman/browser <10 detik dikecualikan. Article views bukan ad impressions.']);
        $write(['Rumus rata-rata', 'Average daily visitors = jumlah unique harian / hari tercatat; average daily views = total views / hari tercatat. Hari nol disertakan.']);
        $write(['Jumlah hari tercatat', $data['period']->days()]);
        foreach (['views', 'unique', 'articleViews', 'averageVisitors', 'averageViews', 'legacyViews', 'allTimeViews'] as $key) {
            $write([$key, $data['summary'][$key]]);
        }
        $write(['previous_period_views', $data['summary']['previous'] ?? 'N/A: cakupan pembanding belum lengkap']);
        $write(['growth_percent', $data['summary']['growth'] ?? 'N/A: basis nol atau cakupan pembanding belum lengkap']);
        $write(['Bulan (irisan periode)', 'Monthly Page Views', 'Monthly Unique Visitors (estimasi)']);
        foreach ($data['summary']['monthly'] as $month) {
            $write(array_values($month));
        }
        $write(['Kategori saat ini', 'Views periode']);
        foreach ($data['summary']['categories'] as $category) {
            $write([$category->name ?? 'Tanpa kategori', $category->views]);
        }
        $write(['Tanggal WIB', 'Page Views', 'Daily Unique Visitors']);
        foreach ($data['summary']['series'] as $day) {
            $write(array_values($day));
        }
        $write(['Artikel', 'Kategori saat ini', 'Terbit', 'Total all-time', 'Views periode', 'Unique periode (estimasi)', 'Average daily views', 'Last viewed UTC']);
        foreach ($data['articles']->lazy(200) as $post) {
            $write([$post->title, $post->category?->name ?? '', $post->published_at?->toDateString() ?? '', $post->views, $post->period_views, $post->period_unique, $post->period_views / max(1, $data['period']->days()), $post->last_viewed_at ?? '']);
        }
        fclose($stream);
    }
}
