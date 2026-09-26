<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MenuController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-system');
        Menu::firstOrCreate(['location' => 'header'], ['name' => 'Header Menu']);
        Menu::firstOrCreate(['location' => 'footer'], ['name' => 'Footer Menu']);

        return view('admin.menus.index', ['menus' => Menu::with('items')->get()]);
    }

    public function create(Menu $menu)
    {
        Gate::authorize('manage-system');

        return view('admin.menus.form', ['menu' => $menu->load('items'), 'item' => new MenuItem]);
    }

    public function edit(Menu $menu, MenuItem $item)
    {
        Gate::authorize('manage-system');
        abort_unless($item->menu_id === $menu->id, 404);

        return view('admin.menus.form', ['menu' => $menu->load('items'), 'item' => $item]);
    }

    public function store(Request $request, Menu $menu)
    {
        Gate::authorize('manage-system');
        $this->save($request, $menu, new MenuItem);

        return redirect()->route('admin.menus.index')->with('success', 'Item menu dibuat.');
    }

    public function update(Request $request, Menu $menu, MenuItem $item)
    {
        Gate::authorize('manage-system');
        abort_unless($item->menu_id === $menu->id, 404);
        $this->save($request, $menu, $item);

        return redirect()->route('admin.menus.index')->with('success', 'Item menu diperbarui.');
    }

    public function destroy(Menu $menu, MenuItem $item)
    {
        Gate::authorize('manage-system');
        abort_unless($item->menu_id === $menu->id, 404);
        $item->delete();
        Cache::forget('menu:'.$menu->location);

        return back()->with('success', 'Item menu dihapus.');
    }

    private function save(Request $request, Menu $menu, MenuItem $item): void
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:191'], 'url' => ['required', 'string', 'max:2048'], 'sort_order' => ['required', 'integer', 'min:0'], 'parent_id' => ['nullable', 'exists:menu_items,id']]);
        if (! (str_starts_with($data['url'], '/') && ! str_starts_with($data['url'], '//')) && ! preg_match('~^https://~i', $data['url'])) {
            throw ValidationException::withMessages(['url' => 'Gunakan path lokal atau URL HTTPS.']);
        }
        if (($data['parent_id'] ?? null) && (! $menu->items()->whereKey($data['parent_id'])->exists() || $item->id == $data['parent_id'])) {
            throw ValidationException::withMessages(['parent_id' => 'Induk menu tidak valid.']);
        }
        $item->fill($data + ['menu_id' => $menu->id]);
        $item->is_active = $request->boolean('is_active');
        $item->save();
        Cache::forget('menu:'.$menu->location);
    }
}
