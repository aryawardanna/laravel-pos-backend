<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MenuAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuAccessSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_create_a_dynamic_role(): void
    {
        $this->actingAs($this->admin())
            ->post(route('role.store'), ['name' => 'Kasir Toko'])
            ->assertRedirect(route('role.index'));

        $this->assertDatabaseHas('roles', ['name' => 'Kasir Toko', 'guard_name' => 'web']);
    }

    public function test_role_name_must_be_unique(): void
    {
        $this->actingAs($this->admin())
            ->post(route('role.store'), ['name' => 'staff'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Role::where('name', 'staff')->count());
    }

    public function test_user_forms_list_dynamic_roles(): void
    {
        Role::create(['name' => 'Gudang', 'guard_name' => 'web']);
        $user = User::factory()->create(['role' => 'staff']);

        $this->actingAs($this->admin())
            ->get(route('user.create'))
            ->assertOk()
            ->assertSee('Gudang');

        $this->actingAs($this->admin())
            ->get(route('user.edit', $user->id))
            ->assertOk()
            ->assertSee('Gudang');
    }

    public function test_assigning_dynamic_role_to_user_applies_its_permissions(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'user']);

        Role::findByName('user')->syncPermissions(['dashboard.view']);

        $this->actingAs($user->fresh())->get(route('satuan.index'))->assertForbidden();

        // Buat role baru + hak akses, lalu assign ke user lewat form Users
        $this->actingAs($admin)
            ->post(route('role.store'), ['name' => 'Gudang'])
            ->assertRedirect(route('role.index'));

        Role::findByName('Gudang')->syncPermissions(['dashboard.view', 'satuan.view', 'satuan.create']);

        $this->actingAs($admin)
            ->put(route('user.update', $user->id), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'Gudang',
                'status' => 1,
            ])
            ->assertRedirect(route('user.index'));

        $this->assertSame('Gudang', $user->fresh()->role);
        $this->assertTrue($user->fresh()->hasRole('Gudang'));

        $target = $user->fresh();

        $this->actingAs($target)->get(route('satuan.index'))->assertOk();
        $this->actingAs($target)->get(route('satuan.create'))->assertOk();
        $this->actingAs($target)->get(route('user.index'))->assertForbidden();
    }

    public function test_renaming_a_role_updates_users(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'staff']);
        $role = Role::findByName('staff');

        $this->actingAs($admin)
            ->put(route('role.update', $role->id), ['name' => 'Kasir'])
            ->assertRedirect(route('role.index'));

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Kasir']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'Kasir']);

        // Permission role tidak ikut hilang saat di-rename
        $this->assertGreaterThan(0, $role->fresh()->permissions()->count());
    }

    public function test_unused_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'Temporary', 'guard_name' => 'web']);

        $this->actingAs($this->admin())
            ->delete(route('role.destroy', $role->id))
            ->assertRedirect(route('role.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $role = Role::findByName('staff');

        $this->actingAs($this->admin())
            ->delete(route('role.destroy', $role->id))
            ->assertRedirect(route('role.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
        $this->assertSame('staff', $user->fresh()->role);
    }

    public function test_super_admin_role_is_protected(): void
    {
        $admin = $this->admin();
        $role = Role::findByName('admin');

        $this->actingAs($admin)
            ->put(route('role.update', $role->id), ['name' => 'Superuser'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('role.destroy', $role->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_roles_menu_is_protected_by_permission(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Role::findByName('staff')->syncPermissions(['dashboard.view']);

        $this->actingAs($staff->fresh())->get(route('role.index'))->assertForbidden();
        $this->actingAs($staff->fresh())->get(route('role.data'))->assertForbidden();
    }
}
