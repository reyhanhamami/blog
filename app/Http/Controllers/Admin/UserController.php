<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'roles' || ! $request->user()->can('users.view') ? 'roles' : 'users';
        Gate::authorize($tab === 'roles' ? 'roles.view' : 'users.view');
        $roles = Role::withCount('users')->withCount('permissions')->orderBy('display_name')->get();
        $superadminCount = User::where('role', 'superadmin')->count();
        $users = null;
        if ($tab === 'users') {
            $users = User::query()->with('roleRecord.permissions')
                ->when($request->filled('q'), fn ($query) => $query->where(fn ($q) => $q
                    ->where('name', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')))
                ->when($request->filled('role') && $roles->contains('name', $request->role), fn ($query) => $query->where('role', $request->role))
                ->when($request->query('access') === 'cms', fn ($query) => $query->where(fn ($q) => $q->where('role', 'superadmin')->orWhereHas('roleRecord.permissions', fn ($permissions) => $permissions->where('name', 'cms.access'))))
                ->when($request->query('access') === 'public', fn ($query) => $query->where('role', '!=', 'superadmin')->whereDoesntHave('roleRecord.permissions', fn ($permissions) => $permissions->where('name', 'cms.access')))
                ->latest()->paginate(15)->withQueryString();
        }

        return view('admin.users.index', compact('users', 'roles', 'tab', 'superadminCount'));
    }

    public function create(Request $request)
    {
        Gate::authorize('users.create');

        return view('admin.users.form', ['user' => new User, 'roles' => $this->assignableRoles($request)]);
    }

    public function edit(Request $request, User $user)
    {
        Gate::authorize('users.update');
        abort_if($user->role === 'superadmin' && $request->user()->role !== 'superadmin', 403);

        return view('admin.users.form', ['user' => $user, 'roles' => $this->assignableRoles($request)]);
    }

    public function store(Request $request)
    {
        Gate::authorize('users.create');
        $user = new User;
        $this->save($request, $user);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna dibuat.');
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('users.update');
        $this->save($request, $user);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        Gate::authorize('users.delete');
        abort_if($user->id === $request->user()->id, 403);
        abort_if($user->role === 'superadmin' && ($request->user()->role !== 'superadmin' || User::where('role', 'superadmin')->count() <= 1), 403);
        DB::transaction(function () use ($user, $request) {
            $this->activity($request, 'user_deleted', $user, ['role' => $user->role]);
            $user->delete();
        });

        return back()->with('success', 'Pengguna dihapus.');
    }

    private function assignableRoles(Request $request)
    {
        $actor = $request->user();
        $roles = Role::with('permissions')->orderBy('display_name')->get();
        if ($actor->role === 'superadmin') {
            return $roles;
        }

        $allowed = $actor->roleRecord?->permissions->pluck('name')->all() ?? [];

        return $roles->filter(fn (Role $role) => $role->name !== 'superadmin' && $role->permissions->pluck('name')->diff($allowed)->isEmpty());
    }

    private function save(Request $request, User $user): void
    {
        $actor = $request->user();
        if ($user->exists && $user->role === 'superadmin' && $actor->role !== 'superadmin') {
            abort(403);
        }

        $assignable = $this->assignableRoles($request)->pluck('name')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in($assignable)],
            'password' => [$user->exists ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
        if ($user->role === 'superadmin' && $data['role'] !== 'superadmin' && User::where('role', 'superadmin')->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'Superadmin terakhir tidak dapat diturunkan.']);
        }
        $oldRole = $user->role;
        if ($data['password'] ?? null) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data, $oldRole, $request) {
            $user->fill($data)->save();
            if ($oldRole !== $user->role) {
                $this->activity($request, 'user_role_changed', $user, ['old_role' => $oldRole, 'new_role' => $user->role]);
            }
        });
    }

    private function activity(Request $request, string $action, User $user, array $metadata): void
    {
        DB::table('activities')->insert([
            'user_id' => $request->user()->id, 'action' => $action,
            'subject_type' => User::class, 'subject_id' => $user->id,
            'metadata' => json_encode($metadata), 'created_at' => now(),
        ]);
    }
}
