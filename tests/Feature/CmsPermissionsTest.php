<?php

use App\Http\Middleware\EnsureAdminPermission;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('every protected admin route has an explicit permission mapping', function () {
    $method = new ReflectionMethod(EnsureAdminPermission::class, 'permissions');
    $unmapped = collect(Route::getRoutes())->map(fn ($route) => $route->getName())
        ->filter(fn ($name) => $name && str_starts_with($name, 'admin.') && ! in_array($name, ['admin.login', 'admin.login.store', 'admin.logout']))
        ->filter(fn ($name) => $method->invoke(new EnsureAdminPermission, $name) === [])
        ->values()->all();

    expect($unmapped)->toBe([]);
});

test('public CMS shortcut follows cms access permission for each role', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('Masuk ke CMS');
    foreach (['superadmin', 'admin', 'editor', 'author'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route('home'))->assertOk()->assertSee('Masuk ke CMS');
    }
    $this->actingAs(User::factory()->create(['role' => 'reader']))->get(route('home'))->assertOk()->assertDontSee('Masuk ke CMS');
});

test('sidebar groups only show permitted modules and child routes keep active item', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $this->actingAs($editor)->get(route('admin.dashboard'))->assertOk()
        ->assertSee('Content')->assertSee('Articles')->assertSee('Learning')
        ->assertDontSee('href="'.route('admin.users.index').'"', false)->assertDontSee('>Management</p>', false);
    $this->actingAs($editor)->get(route('admin.posts.create'))->assertOk()->assertSee('aria-current="page"', false);

    $author = User::factory()->create(['role' => 'author']);
    $this->actingAs($author)->get(route('admin.dashboard'))->assertOk()
        ->assertSee('Articles')->assertDontSee('Analytics')->assertDontSee('>Management</p>', false);
});

test('direct admin endpoints enforce action permissions', function () {
    $reader = User::factory()->create(['role' => 'reader']);
    $this->actingAs($reader)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($reader)->get(route('admin.users.index'))->assertForbidden();

    $editor = User::factory()->create(['role' => 'editor']);
    $this->actingAs($editor)->get(route('admin.posts.create'))->assertOk();
    $this->actingAs($editor)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.roles.create'))->assertForbidden();
    $this->actingAs($editor)->patch(route('admin.settings.update'), ['site_name' => 'Changed'])->assertForbidden();

    $viewerRole = Role::create(['name' => 'viewer', 'display_name' => 'Viewer', 'is_system' => false]);
    $viewerRole->permissions()->sync(Permission::whereIn('name', ['cms.access', 'dashboard.view', 'articles.view'])->pluck('id'));
    $viewer = User::factory()->create(['role' => 'viewer']);
    $this->actingAs($viewer)->get(route('admin.posts.index'))->assertOk()->assertDontSee('+ Buat artikel');
    $this->actingAs($viewer)->get(route('admin.posts.create'))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.users.index'))->assertForbidden();
    $post = Post::create(['title' => 'Tidak boleh dihapus', 'slug' => 'tidak-boleh-dihapus', 'content_type' => 'article', 'status' => 'draft']);
    $this->actingAs($viewer)->delete(route('admin.posts.destroy', $post))->assertForbidden();
});

test('admin permissions can be reduced without removing superadmin access', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $admin = User::factory()->create(['role' => 'admin']);
    $role = Role::where('name', 'admin')->firstOrFail();
    $this->actingAs($superadmin)->patch(route('admin.roles.update', $role), [
        'display_name' => 'Admin', 'permissions' => ['cms.access', 'dashboard.view', 'articles.view'],
    ])->assertRedirect();
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.posts.create'))->assertForbidden();
    $this->actingAs($admin)->patch(route('admin.settings.update'), ['site_name' => 'Changed'])->assertForbidden();
    $this->actingAs($superadmin)->get(route('admin.users.index'))->assertOk();
});

test('dashboard does not expose article data without article view permission', function () {
    $role = Role::create(['name' => 'dashboard_only', 'display_name' => 'Dashboard Only', 'is_system' => false]);
    $role->permissions()->sync(Permission::whereIn('name', ['cms.access', 'dashboard.view'])->pluck('id'));
    Post::create(['title' => 'Private Draft Title', 'slug' => 'private-draft-title', 'content_type' => 'article', 'status' => 'draft']);

    $this->actingAs(User::factory()->create(['role' => 'dashboard_only']))
        ->get(route('admin.dashboard'))->assertOk()
        ->assertSee('Belum ada ringkasan yang tersedia')->assertDontSee('Private Draft Title')->assertDontSee('Artikel terbaru');
});

test('author can create and update own article but not another authors article', function () {
    $author = User::factory()->create(['role' => 'author']);
    $other = User::factory()->create(['role' => 'author']);
    $own = Post::create(['title' => 'Artikel Saya', 'slug' => 'artikel-saya', 'content_type' => 'article', 'status' => 'draft', 'created_by' => $author->id]);
    $theirs = Post::create(['title' => 'Artikel Lain', 'slug' => 'artikel-lain', 'content_type' => 'article', 'status' => 'draft', 'created_by' => $other->id]);
    $this->actingAs($author)->get(route('admin.posts.create'))->assertOk();
    $this->actingAs($author)->get(route('admin.posts.edit', $own))->assertOk();
    $this->actingAs($author)->get(route('admin.posts.edit', $theirs))->assertForbidden();
    $this->actingAs($author)->get(route('admin.roles.index'))->assertForbidden();
});

test('custom role permissions persist with view and CMS dependencies', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($superadmin)->post(route('admin.roles.store'), [
        'name' => 'copy_editor', 'display_name' => 'Copy Editor', 'description' => 'Menulis konten',
        'permissions' => ['articles.create'],
    ])->assertRedirect();
    $role = Role::where('name', 'copy_editor')->firstOrFail();
    expect($role->permissions()->pluck('name')->all())->toContain('articles.create', 'articles.view', 'cms.access', 'dashboard.view');
    $this->actingAs($superadmin)->get(route('admin.roles.edit', $role))->assertOk()->assertSee('Permissions')->assertSee('Buat artikel');
    $this->actingAs($superadmin)->patch(route('admin.roles.update', $role), [
        'display_name' => 'Copy Editor', 'permissions' => ['articles.view'],
    ])->assertRedirect();
    expect($role->fresh()->permissions()->pluck('name')->all())->not->toContain('articles.create');

    $this->actingAs($superadmin)->post(route('admin.roles.store'), ['name' => 'invalid_role', 'display_name' => 'Invalid', 'permissions' => ['fake.permission']])->assertSessionHasErrors('permissions.0');
});

test('system and in-use roles cannot be deleted and last superadmin cannot be downgraded', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($superadmin)->delete(route('admin.users.destroy', $superadmin))->assertForbidden();
    $this->actingAs($superadmin)->patch(route('admin.users.update', $superadmin), [
        'name' => $superadmin->name, 'email' => $superadmin->email, 'role' => 'admin',
    ])->assertSessionHasErrors('role');
    $this->actingAs($superadmin)->delete(route('admin.roles.destroy', Role::where('name', 'admin')->firstOrFail()))->assertForbidden();

    $role = Role::create(['name' => 'writer', 'display_name' => 'Writer', 'is_system' => false]);
    User::factory()->create(['role' => 'writer']);
    $this->actingAs($superadmin)->delete(route('admin.roles.destroy', $role))->assertSessionHas('error');
    expect(Role::where('name', 'writer')->exists())->toBeTrue();
});

test('admin user listing filters by role and public login stays in the public account', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    User::factory()->create(['name' => 'Editor Besofton', 'role' => 'editor']);
    User::factory()->create(['name' => 'Reader Besofton', 'role' => 'reader']);
    $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'editor']))->assertOk()
        ->assertSee('Editor Besofton')->assertDontSee('Reader Besofton');
    $this->actingAs($admin)->get(route('admin.users.index', ['tab' => 'roles']))->assertOk()->assertSee('Roles & Permissions', false);
});

test('user create and edit use page forms and restrict role escalation', function () {
    $superadmin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($superadmin)->get(route('admin.users.create'))->assertOk()->assertSee('Simpan User');
    $this->actingAs($superadmin)->post(route('admin.users.store'), [
        'name' => 'Penulis Baru', 'email' => 'penulis@example.test', 'password' => 'password123', 'role' => 'author',
    ])->assertRedirect(route('admin.users.index'));
    $author = User::where('email', 'penulis@example.test')->firstOrFail();
    $this->actingAs($superadmin)->get(route('admin.users.edit', $author))->assertOk()->assertSee('Kosongkan jika tidak ingin mengganti password');

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->patch(route('admin.users.update', $author), [
        'name' => $author->name, 'email' => $author->email, 'role' => 'superadmin',
    ])->assertSessionHasErrors('role');
    expect($author->fresh()->role)->toBe('author');
});

test('public login keeps a CMS capable user on the public account', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $this->post(route('reader.login'), ['email' => $editor->email, 'password' => 'password'])
        ->assertRedirect(route('reader.account'));
});
