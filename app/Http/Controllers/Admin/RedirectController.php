<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RedirectController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-system');

        return view('admin.redirects', ['redirects' => DB::table('redirects')->latest()->paginate(20)]);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-system');
        $data = $request->validate(['from_path' => ['required', 'string', 'max:191', 'unique:redirects,from_path'], 'to_path' => ['required', 'string', 'max:191'], 'status_code' => ['required', 'in:301,302']]);
        foreach (['from_path', 'to_path'] as $field) {
            if (! str_starts_with($data[$field], '/') || str_starts_with($data[$field], '//') || str_contains($data[$field], chr(92)) || preg_match('/[[:cntrl:][:space:]]/', $data[$field])) {
                throw ValidationException::withMessages([$field => 'Gunakan path lokal yang diawali satu garis miring.']);
            }
        }
        if ($data['from_path'] === $data['to_path']) {
            throw ValidationException::withMessages(['to_path' => 'Tujuan harus berbeda dari sumber.']);
        }
        $visited = [$data['from_path'] => true];
        $cursor = $data['to_path'];
        for ($hop = 0; $hop < 20; $hop++) {
            if (isset($visited[$cursor])) {
                throw ValidationException::withMessages(['to_path' => 'Tujuan membuat loop redirect.']);
            }
            $visited[$cursor] = true;
            $next = DB::table('redirects')->where('from_path', $cursor)->value('to_path');
            if (! $next) {
                break;
            }
            $cursor = $next;
        }
        if ($hop === 20) {
            throw ValidationException::withMessages(['to_path' => 'Rantai redirect terlalu panjang.']);
        }
        DB::table('redirects')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Redirect dibuat.');
    }

    public function destroy(int $redirect)
    {
        Gate::authorize('manage-system');
        DB::table('redirects')->where('id', $redirect)->delete();

        return back()->with('success', 'Redirect dihapus.');
    }
}
