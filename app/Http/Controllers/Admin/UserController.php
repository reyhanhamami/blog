<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manage-system');
        $users = User::query()->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%'))->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        Gate::authorize('manage-system');

        return view('admin.users.form', ['user' => new User]);
    }

    public function edit(User $user)
    {
        Gate::authorize('manage-system');

        return view('admin.users.form', compact('user'));
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-system');
        $user = new User;
        $this->save($request, $user);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna dibuat.');
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('manage-system');
        $this->save($request, $user);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna diperbarui.');
    }

    public function destroy(User $user)
    {
        Gate::authorize('manage-system');
        abort_if($user->id === auth()->id() || $user->role === 'superadmin', 403);
        $user->delete();

        return back()->with('success', 'Pengguna dihapus.');
    }

    private function save(Request $request, User $user): void
    {
        $roles = $request->user()->role === 'superadmin' ? ['superadmin', 'admin', 'editor', 'author', 'reader'] : ['admin', 'editor', 'author', 'reader'];
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in($roles)],
            'password' => [$user->exists ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
        if ($user->role === 'superadmin' && $request->user()->role !== 'superadmin') {
            abort(403);
        }
        if ($data['password'] ?? null) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->fill($data)->save();
    }
}
