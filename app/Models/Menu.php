<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Menu extends Model
{
    protected $guarded = ['id'];

    public function items()
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    public static function links(string $location)
    {
        return Cache::remember('menu:'.$location, 3600, function () use ($location) {
            $menu = static::where('location', $location)->first();

            return $menu ? $menu->items()->where('is_active', true)->get() : collect();
        });
    }
}
