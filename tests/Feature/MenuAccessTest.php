<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use Database\Seeders\MenuAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MenuAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuAccessSeeder::class);
    }

    public function test_sidebar_only_shows_menus_granted_to_the_role(): void
    {
        $staff = $this->createUser('staff');

        Role::findByName('staff')->syncPermissions(['dashboard.view', 'sale.view']);

        $sidebar = $this->sidebarFor($staff);

        $this->assertStringContainsString('Dashboard', $sidebar);
        $this->assertStringContainsString('Penjualan / Kasir', $sidebar);

        // Modul yang tidak dicentang tidak muncul, grupnya ikut hilang
        $this->assertStringNotContainsString('Pembelian', $sidebar);
        $this->assertStringNotContainsString('Master Data', $sidebar);
        $this->assertStringNotContainsString('Laporan', $sidebar);
        $this->assertStringNotContainsString('Akses Menu', $sidebar);
    }

    public function test_sidebar_group_appears_when_one_of_its_menus_is_granted(): void
    {
        $user = $this->createUser('user');

        Role::findByName('user')->syncPermissions(['dashboard.view']);
        $this->assertStringNotContainsString('Master Data', $this->sidebarFor($user));

        Role::findByName('user')->givePermissionTo('satuan.view');
        $sidebar = $this->sidebarFor($user);

        $this->assertStringContainsString('Master Data', $sidebar);
        $this->assertStringContainsString('Satuan', $sidebar);
        $this->assertStringNotContainsString('Bahan Baku', $sidebar);
    }

    public function test_module_page_is_forbidden_without_permission(): void
    {
        $user = $this->createUser('user');

        Role::findByName('user')->syncPermissions(['dashboard.view']);

        $this->actingAs($user)->get(route('home'))->assertOk();
        $this->actingAs($user)->get(route('satuan.index'))->assertForbidden();
        $this->actingAs($user)->get(route('user.data'))->assertForbidden();
    }

    public function test_create_and_update_routes_need_their_own_permission(): void
    {
        $staff = $this->createUser('staff');

        // Hanya boleh melihat data satuan, tidak boleh menambah
        Role::findByName('staff')->syncPermissions(['dashboard.view', 'satuan.view']);

        $this->actingAs($staff)->get(route('satuan.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('satuan.store'), [
            'name' => 'Liter',
            'code' => 'Ltr',
        ])->assertForbidden();

        Role::findByName('staff')->givePermissionTo('satuan.create');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($staff)->get(route('satuan.create'))->assertOk();
    }

    public function test_super_admin_keeps_full_access_even_without_granted_permissions(): void
    {
        $admin = $this->createUser('admin');

        Role::findByName('admin')->syncPermissions([]);

        $this->actingAs($admin)->get(route('satuan.index'))->assertOk();
        $this->assertStringContainsString('Satuan', $this->sidebarFor($admin));
    }

    public function test_admin_can_save_role_permissions_from_menu_access_page(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->get(route('menu-access.index'))
            ->assertOk()
            ->assertSee('Akses Menu');

        $this->actingAs($admin)
            ->put(route('menu-access.update'), [
                'permissions' => [
                    'staff' => ['dashboard.view', 'satuan.view'],
                    'user' => ['dashboard.view'],
                ],
            ])
            ->assertRedirect(route('menu-access.index'));

        $this->assertEqualsCanonicalizing(
            ['dashboard.view', 'satuan.view'],
            Role::findByName('staff')->permissions()->pluck('name')->all()
        );

        $staff = $this->createUser('staff');

        $this->actingAs($staff)->get(route('satuan.index'))->assertOk();
        $this->actingAs($staff)->get(route('user.index'))->assertForbidden();
    }

    public function test_unknown_permission_submitted_is_ignored(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->put(route('menu-access.update'), [
                'permissions' => [
                    'staff' => ['dashboard.view', 'hacker.super'],
                ],
            ])
            ->assertRedirect(route('menu-access.index'));

        $this->assertSame(
            ['dashboard.view'],
            Role::findByName('staff')->permissions()->pluck('name')->all()
        );
    }

    public function test_direct_permission_grants_menu_to_a_single_user(): void
    {
        $user = $this->createUser('user');

        Role::findByName('user')->syncPermissions(['dashboard.view']);

        $this->actingAs($user)->get(route('laporan.penjualan.index'))->assertForbidden();

        $user->givePermissionTo('laporan.penjualan.view');

        $this->actingAs($user)->get(route('laporan.penjualan.index'))->assertOk();

        $sidebar = $this->sidebarFor($user);
        $this->assertStringContainsString('Laporan', $sidebar);
        $this->assertStringNotContainsString('Barang Masuk', $sidebar);
    }

    public function test_role_change_updates_menu_access(): void
    {
        $user = $this->createUser('user');

        Role::findByName('staff')->syncPermissions(['dashboard.view', 'satuan.view']);
        Role::findByName('user')->syncPermissions(['dashboard.view']);

        $this->actingAs($user)->get(route('satuan.index'))->assertForbidden();

        // Role diubah (mis. dari form Users), middleware menyinkronkan role Spatie
        $user->update(['role' => 'staff']);

        $this->actingAs($user)->get(route('satuan.index'))->assertOk();
        $this->assertTrue($user->fresh()->hasRole('staff'));
    }

    public function test_changing_role_from_user_form_updates_menu_access(): void
    {
        $admin = $this->createUser('admin');
        $target = $this->createUser('user');

        Role::findByName('staff')->syncPermissions(['dashboard.view', 'satuan.view']);
        Role::findByName('user')->syncPermissions(['dashboard.view']);

        $this->actingAs($target)->get(route('satuan.index'))->assertForbidden();

        // Role diubah dari form Users (butuh kolom users.status)
        $this->actingAs($admin)
            ->put(route('user.update', $target->id), [
                'name' => $target->name,
                'email' => $target->email,
                'role' => 'staff',
                'status' => 1,
            ])
            ->assertRedirect(route('user.index'));

        $this->assertSame(1, $target->fresh()->status);

        // Tiap request di aplikasi memuat ulang user dari session, jadi
        // simulasi dengan user yang di-refresh (bukan instance lama).
        $this->actingAs($target->fresh())->get(route('satuan.index'))->assertOk();
        $this->assertTrue($target->fresh()->hasRole('staff'));
    }

    public function test_only_one_sidebar_menu_is_active_per_page(): void
    {
        $admin = $this->createUser('admin');

        // "menu" (Produk / Menu) dan "menu-access" (Akses Menu) sama-sama
        // diawali "menu", jadi tidak boleh saling menyalakan.
        $this->assertSame(
            [route('menu.index')],
            $this->activeMenusOn(route('menu.index'), $admin)
        );

        $this->assertSame(
            [route('menu-access.index')],
            $this->activeMenusOn(route('menu-access.index'), $admin)
        );

        // Sub-halaman modul tetap menyalakan menu induknya
        $menu = Menu::create(['name' => 'Nasi Goreng', 'price' => 20000, 'status' => 1]);
        $this->assertSame(
            [route('menu.index')],
            $this->activeMenusOn(route('menu.edit', $menu->id), $admin)
        );

        $this->assertSame(
            [route('laporan.penjualan.index')],
            $this->activeMenusOn(route('laporan.penjualan.index'), $admin)
        );

        $this->assertSame(
            [route('home')],
            $this->activeMenusOn(route('home'), $admin)
        );
    }

    private function createUser(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * Daftar URL menu sidebar yang sedang aktif pada sebuah halaman.
     *
     * @return array<int, string>
     */
    private function activeMenusOn(string $url, User $user): array
    {
        $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

        preg_match_all('/<li class="active">\s*<a class="nav-link" href="([^"]*)"/', $html, $matches);

        return $matches[1];
    }

    /**
     * Ambil isi sidebar saja supaya assertion tidak ikut kena konten halaman.
     */
    private function sidebarFor(User $user): string
    {
        $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

        // Menu grup berisi <ul> bersarang, jadi ambil </ul> terakhir sebelum aside
        preg_match('/<ul class="sidebar-menu">(.*)<\/ul>\s*<\/aside>/s', $html, $matches);

        return $matches[1] ?? '';
    }
}
