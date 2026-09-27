<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Menu;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membuat permission, role, dan hak akses awal sesuai config/menu.php.
 *
 * Setelah data ini ada, pengaturan hak akses bisa dilakukan sendiri lewat
 * halaman "Akses Menu" tanpa menyentuh kode.
 */
class MenuAccessSeeder extends Seeder
{
    public function __construct(protected Menu $menu) {}

    public function run(): void
    {
        $guard = 'web';
        $permissions = $this->menu->permissions();
        $superAdminRole = (string) config('menu.super_admin_role');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Buat seluruh permission yang dipakai aplikasi (config/menu.php)
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        // 2. Buat role + hak akses awal
        foreach ((array) config('menu.default_role_permissions', []) as $roleName => $granted) {
            $role = Role::findOrCreate($roleName, $guard);

            if ($roleName === $superAdminRole || $granted === '*') {
                $role->syncPermissions($permissions);

                continue;
            }

            $role->syncPermissions(
                array_values(array_intersect((array) $granted, $permissions))
            );
        }

        // 3. Samakan role Spatie milik user yang sudah ada dengan kolom `role`
        User::query()->whereNotNull('role')->chunkById(100, function ($users) {
            $users->each(fn (User $user) => $user->syncRoleFromColumn());
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->menu->flush();
    }
}
