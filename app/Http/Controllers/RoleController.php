<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class RoleController extends Controller
{
    /**
     * Role yang tidak boleh diubah / dihapus (lihat config/menu.php).
     */
    private function protectedRole(): string
    {
        return (string) config('menu.super_admin_role');
    }

    // index + form tambah role
    public function index()
    {
        return view('pages.role.index', [
            'superAdminRole' => $this->protectedRole(),
        ]);
    }

    public function data()
    {
        $query = Role::query()->where('guard_name', 'web');

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('name', function (Role $role) {
                return e($role->name);
            })

            ->addColumn('users', function (Role $role) {
                $count = User::where('role', $role->name)->count();

                return $count > 0
                    ? '<span class="badge badge-info">'.$count.' user</span>'
                    : '<span class="badge badge-secondary">0 user</span>';
            })

            ->addColumn('permissions', function (Role $role) {
                return '<span class="badge badge-light">'.$role->permissions()->count().' permission</span>';
            })

            ->addColumn('action', function (Role $role) {
                if ($role->name === $this->protectedRole()) {
                    return '<span class="badge badge-primary">Super Admin</span>';
                }

                return '<a href="'.route('role.edit', $role->id).'"
                            class="btn btn-sm btn-info btn-icon">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <form action="'.route('role.destroy', $role->id).'"
                            method="POST"
                            class="d-inline ml-2 delete-form">
                            '.csrf_field().'
                            '.method_field('DELETE').'
                            <button type="submit"
                                    class="btn btn-sm btn-danger btn-icon confirm-delete">
                                <i class="fas fa-times"></i> Delete
                            </button>
                        </form>';
            })

            ->rawColumns(['users', 'permissions', 'action'])
            ->toJson();
    }

    // create
    public function create()
    {
        return redirect()->route('role.index');
    }

    // store
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9 _.-]+$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
        ], [
            'name.regex' => 'Nama role hanya boleh huruf, angka, spasi, titik, garis, atau garis bawah.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        return redirect()
            ->route('role.index')
            ->with('success', 'Role berhasil ditambahkan. Atur hak aksesnya di menu Akses Menu.');
    }

    // edit
    public function edit($id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === $this->protectedRole()) {
            return redirect()
                ->route('role.index')
                ->with('error', 'Role super admin tidak dapat diubah.');
        }

        return view('pages.role.edit', ['role' => $role]);
    }

    // update (rename)
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === $this->protectedRole()) {
            return redirect()
                ->route('role.index')
                ->with('error', 'Role super admin tidak dapat diubah.');
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9 _.-]+$/',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($role->id),
            ],
        ], [
            'name.regex' => 'Nama role hanya boleh huruf, angka, spasi, titik, garis, atau garis bawah.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $oldName = $role->name;
        $newName = $request->name;

        $role->update(['name' => $newName]);

        // Kolom users.role ikut diperbarui agar tetap sinkron dengan role Spatie
        if ($oldName !== $newName) {
            User::where('role', $oldName)->update(['role' => $newName, 'updated_by' => Auth::id()]);
        }

        return redirect()->route('role.index')->with('success', 'Role updated successfully');
    }

    // destroy
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === $this->protectedRole()) {
            return redirect()
                ->route('role.index')
                ->with('error', 'Role super admin tidak dapat dihapus.');
        }

        // Role yang masih dipakai user tidak boleh dihapus agar aksesnya tidak hilang.
        $users = User::where('role', $role->name)->count();

        if ($users > 0) {
            return redirect()
                ->route('role.index')
                ->with('error', "Role masih dipakai oleh {$users} user. Pindahkan user tersebut ke role lain terlebih dahulu.");
        }

        $role->delete();

        return redirect()->route('role.index')->with('success', 'Role deleted successfully');
    }
}
