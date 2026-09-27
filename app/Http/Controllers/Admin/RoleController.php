<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        Gate::authorize('roles.view');

        return redirect()->route('admin.users.index', ['tab' => 'roles']);
    }

    public function create()
    {
        Gate::authorize('roles.manage');

        return $this->form(new Role);
    }

    public function edit(Role $role)
    {
        Gate::authorize('roles.manage');
        abort_if($role->name === 'superadmin', 403);

        return $this->form($role);
    }

    private function form(Role $role)
    {
        $role->load('permissions');

        return view('admin.roles.form', [
            'role' => $role,
            'groups' => PermissionCatalog::groups(),
            'selected' => $role->permissions->pluck('name')->all(),
            'descriptions' => config('permissions.descriptions'),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('roles.manage');
        $data = $request->validate([
            'name' => ['required', 'regex:/^[a-z][a-z0-9_-]*$/', 'max:20', Rule::unique('roles', 'name')],
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::names())],
        ]);
        $names = PermissionCatalog::normalize($data['permissions'] ?? []);
        $role = DB::transaction(function () use ($data, $names, $request) {
            $role = Role::create([
                'name' => $data['name'], 'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null, 'is_system' => false,
            ]);
            $role->permissions()->sync(Permission::whereIn('name', $names)->pluck('id'));
            $this->activity($request, 'role_created', $role, ['permissions' => $names]);

            return $role;
        });

        return redirect()->route('admin.roles.edit', $role)->with('success', 'Role berhasil dibuat.');
    }

    public function update(Request $request, Role $role)
    {
        Gate::authorize('roles.manage');
        abort_if($role->name === 'superadmin', 403);
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::names())],
        ]);
        $names = PermissionCatalog::normalize($data['permissions'] ?? []);
        DB::transaction(function () use ($role, $data, $names, $request) {
            $before = $role->permissions()->pluck('name')->all();
            $role->update(['display_name' => $data['display_name'], 'description' => $data['description'] ?? null]);
            $role->permissions()->sync(Permission::whereIn('name', $names)->pluck('id'));
            $this->activity($request, 'role_updated', $role, ['before' => $before, 'after' => $names]);
        });

        return redirect()->route('admin.roles.edit', $role)->with('success', 'Permission role berhasil diperbarui.');
    }

    public function destroy(Request $request, Role $role)
    {
        Gate::authorize('roles.manage');
        abort_if($role->is_system, 403);
        if ($role->users()->exists()) {
            return back()->with('error', 'Role ini masih digunakan pengguna. Pindahkan role mereka terlebih dahulu.');
        }
        DB::transaction(function () use ($role, $request) {
            $this->activity($request, 'role_deleted', $role, ['name' => $role->name]);
            $role->delete();
        });

        return redirect()->route('admin.users.index', ['tab' => 'roles'])->with('success', 'Role berhasil dihapus.');
    }

    private function activity(Request $request, string $action, Role $role, array $metadata): void
    {
        DB::table('activities')->insert([
            'user_id' => $request->user()->id, 'action' => $action,
            'subject_type' => Role::class, 'subject_id' => $role->id,
            'metadata' => json_encode($metadata), 'created_at' => now(),
        ]);
    }
}
