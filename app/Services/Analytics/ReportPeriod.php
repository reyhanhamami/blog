<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportPeriod
{
    public function __construct(public CarbonImmutable $from, public CarbonImmutable $to, public CarbonImmutable $startedAt, public string $preset) {}

    public static function fromRequest(Request $request): self
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['today', 'yesterday', '7', '30', '90', 'custom'])],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in(['period_views', 'period_unique', 'views'])],
        ]);
        $tz = config('analytics.timezone');
        $today = CarbonImmutable::today($tz);
        $preset = $data['period'] ?? '30';
        $to = $preset === 'custom' ? CarbonImmutable::parse($data['to'], $tz) : ($preset === 'yesterday' ? $today->subDay() : $today);
        $from = $preset === 'custom' ? CarbonImmutable::parse($data['from'], $tz) : $to->subDays(in_array($preset, ['today', 'yesterday']) ? 0 : (int) $preset - 1);
        if ($to->gt($today) || $from->lt($today->subDays(config('analytics.detail_days') - 1)) || $from->diffInDays($to) >= config('analytics.detail_days')) {
            throw ValidationException::withMessages(['from' => 'Pilih rentang maksimal 90 hari dalam retensi yang tersedia, tanpa tanggal masa depan.']);
        }
        $start = CarbonImmutable::parse(DB::table('analytics_state')->where('id', 1)->value('started_at'), 'UTC')->setTimezone($tz);

        return new self($from->max($start->startOfDay()), $to, $start, $preset);
    }

    public function days(): int
    {
        return $this->from->gt($this->to) ? 0 : (int) $this->from->diffInDays($this->to) + 1;
    }

    public function dates(): array
    {
        $dates = [];
        for ($date = $this->from; $date->lte($this->to); $date = $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }
}
