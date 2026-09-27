<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/**
 * Membaca definisi menu dari config/menu.php lalu menyaringnya sesuai
 * hak akses user, sehingga sidebar, route guard, dan halaman "Akses Menu"
 * selalu memakai satu sumber data yang sama.
 */
class Menu
{
    /**
     * Cache per instance (instance di-resolve ulang tiap request).
     */
    private ?array $definitions = null;

    private ?bool $configured = null;

    /**
     * Definisi menu yang sudah dinormalisasi (key, label, icon, route, uri,
     * abilities, permission) tanpa ikut disaring berdasarkan user.
     */
    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $defaultAbilities = (array) config('menu.abilities_default', ['view']);
        $definitions = [];

        foreach ((array) config('menu.menus', []) as $menu) {
            $definition = $this->normalize($menu, $defaultAbilities, $menu['label'] ?? '');
            $definition['items'] = [];

            if (! empty($menu['items'])) {
                $definition['items'] = array_map(
                    fn (array $item) => $this->normalize($item, $defaultAbilities, $definition['label']),
                    $menu['items']
                );
            }

            $definitions[] = $definition;
        }

        return $this->definitions = $definitions;
    }

    /**
     * Menu yang boleh diakses user. Grup tanpa child yang terlihat dihapus.
     */
    public function tree(?Authenticatable $user): array
    {
        $tree = [];

        foreach ($this->definitions() as $definition) {
            if (empty($definition['items'])) {
                if ($this->can($user, $definition['permission'])) {
                    $tree[] = $definition;
                }

                continue;
            }

            $items = array_values(array_filter(
                $definition['items'],
                fn (array $item) => $this->can($user, $item['permission'])
            ));

            if ($items !== []) {
                $definition['items'] = $items;
                $tree[] = $definition;
            }
        }

        return $tree;
    }

    /**
     * Daftar modul (leaf) untuk ditampilkan pada halaman "Akses Menu".
     *
     * @return array<int, array{key: string, label: string, group: string, abilities: array<int, array{ability: string, permission: string, label: string}>}>
     */
    public function modules(): array
    {
        $modules = [];

        foreach ($this->definitions() as $definition) {
            foreach ($definition['items'] ?: [$definition] as $item) {
                $modules[] = [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'group' => $definition['items'] ? $definition['label'] : 'Umum',
                    'abilities' => $this->abilities($item),
                ];
            }
        }

        foreach ((array) config('menu.hidden_modules', []) as $key => $module) {
            $modules[] = [
                'key' => $key,
                'label' => $module['label'] ?? $key,
                'group' => 'Modul Tanpa Menu',
                'abilities' => $this->abilities([
                    'key' => $key,
                    'abilities' => $module['abilities'] ?? config('menu.abilities_default'),
                ]),
            ];
        }

        return $modules;
    }

    /**
     * Seluruh nama permission yang dikenal aplikasi ini.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->modules() as $module) {
            foreach ($module['abilities'] as $ability) {
                $permissions[] = $ability['permission'];
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * User punya akses ke sebuah permission?
     *
     * Mengikuti aturan yang sama dengan middleware "permission" (Gate),
     * sehingga menu di sidebar dan route yang dibuka selalu konsisten.
     */
    public function can(?Authenticatable $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isSuperAdmin($user) || ! $this->isConfigured()) {
            return true;
        }

        return Gate::forUser($user)->allows($permission);
    }

    /**
     * User dengan role super admin (lihat config/menu.php).
     */
    public function isSuperAdmin(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        $superAdmin = (string) config('menu.super_admin_role');

        return (string) $user->getAttribute('role') === $superAdmin
            || $user->hasRole($superAdmin);
    }

    /**
     * Sudah ada permission yang tersimpan di database?
     *
     * Jika tabel permission masih kosong (mis. instalasi baru yang belum
     * di-seed), semua akses dibuka agar user tidak terkunci dari aplikasi.
     */
    public function isConfigured(): bool
    {
        if ($this->configured !== null) {
            return $this->configured;
        }

        try {
            return $this->configured = Permission::query()->count() > 0;
        } catch (QueryException) {
            // Tabel permission belum ada (migrasi belum dijalankan).
            return $this->configured = false;
        }
    }

    /**
     * Bersihkan cache internal (dipanggil setelah seeding).
     */
    public function flush(): void
    {
        $this->definitions = null;
        $this->configured = null;
    }

    /**
     * Lengkapi entri menu dengan uri, abilities, dan nama permission.
     *
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $defaultAbilities
     * @return array<string, mixed>
     */
    private function normalize(array $item, array $defaultAbilities, string $groupLabel): array
    {
        return [
            'key' => $item['key'],
            'label' => $item['label'] ?? $item['key'],
            'group' => $groupLabel,
            'icon' => $item['icon'] ?? '',
            'route' => $item['route'] ?? null,
            'uri' => $this->uri($item['route'] ?? null),
            'abilities' => (array) ($item['abilities'] ?? $defaultAbilities),
            'permission' => $item['key'].'.view',
        ];
    }

    /**
     * Daftar kemampuan sebuah modul beserta nama permission-nya.
     *
     * @param  array<string, mixed>  $item
     * @return array<int, array{ability: string, permission: string, label: string}>
     */
    private function abilities(array $item): array
    {
        $abilities = (array) ($item['abilities'] ?? config('menu.abilities_default', ['view']));

        return array_map(fn (string $ability) => [
            'ability' => $ability,
            'permission' => $item['key'].'.'.$ability,
            'label' => $this->abilityLabel($ability),
        ], $abilities);
    }

    /**
     * URI dari nama route, dipakai untuk menandai menu yang sedang aktif.
     */
    private function uri(?string $routeName): ?string
    {
        if (! $routeName || ! Route::has($routeName)) {
            return null;
        }

        return Route::getRoutes()->getByName($routeName)?->uri();
    }

    private function abilityLabel(string $ability): string
    {
        return match ($ability) {
            'view' => 'Lihat',
            'create' => 'Tambah',
            'edit' => 'Ubah',
            'delete' => 'Hapus',
            'export' => 'Export',
            'update' => 'Kelola',
            default => ucfirst($ability),
        };
    }
}
