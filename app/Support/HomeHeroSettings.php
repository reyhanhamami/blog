<?php

namespace App\Support;

use App\Models\Setting;

class HomeHeroSettings
{
    public const DEFAULTS = [
        'home_hero_eyebrow' => 'BESOFTON INSIGHTS',
        'home_hero_heading_line_1' => 'IDE.',
        'home_hero_heading_line_2' => 'PANDUAN.',
        'home_hero_heading_highlight' => 'INSIGHTS.',
        'home_hero_description' => 'Ide, Panduan, Insights.',
        'home_hero_image' => '',
        'home_hero_image_alt' => '',
        'home_hero_gold_note' => 'Insight untuk Pertumbuhan Digital',
        'home_hero_black_label' => "BESOFTON\nINSIGHTS",
        'home_hero_white_notes' => "AI for Developers\nLaravel Development",
        'home_hero_search_placeholder' => 'Cari topik atau artikel...',
    ];

    public static function rawValues(): array
    {
        $values = [];
        foreach (self::DEFAULTS as $key => $default) {
            $values[$key] = Setting::valueFor($key, $default);
        }

        return $values;
    }

    public static function content(): array
    {
        $values = self::rawValues();
        $hero = [];
        foreach ($values as $key => $value) {
            $hero[substr($key, strlen('home_hero_'))] = $value;
        }
        $hero['image_url'] = $hero['image'];
        $hero['image_alt'] = $hero['image_alt'] ?: 'Besofton Insights';
        $hero['black_label_lines'] = self::lines($hero['black_label']);
        $hero['white_notes'] = array_slice(self::lines($hero['white_notes']), 0, 4);

        return $hero;
    }

    public static function lines(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $value) ?: []), fn ($line) => $line !== ''));
    }
}
