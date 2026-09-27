<?php

namespace App\Http\Controllers;

use App\Support\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class MenuAccessController extends Controller
{
    public function __construct(protected Menu $menu) {}

    /**
     * Halaman "Akses Menu": matriks permission untuk setiap role.
     */
    public function index()
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('id')
            ->get();

        $granted = [];

        foreach ($roles as $role) {
            $granted[$role->name] = $role->permissions()->pluck('name')->all();
        }

        return view('pages.menu_access.index', [
            'modules' => $this->menu->modules(),
            'roles' => $roles,
            'granted' => $granted,
            'superAdminRole' => config('menu.super_admin_role'),
        ]);
    }

    /**
     * Simpan permission per role.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $known = $this->menu->permissions();
        $submitted = $request->input('permissions', []);
        $superAdminRole = (string) config('menu.super_admin_role');

        foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
            // Role super admin selalu punya akses penuh, tidak perlu diatur.
            if ($role->name === $superAdminRole) {
                continue;
            }

            $permissions = array_values(array_intersect(
                $submitted[$role->name] ?? [],
                $known
            ));

            $role->syncPermissions($permissions);
        }

        return redirect()
            ->route('menu-access.index')
            ->with('success', 'Hak akses menu berhasil disimpan');
    }
}
