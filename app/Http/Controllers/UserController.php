<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function __construct(protected Menu $menu) {}

    /**
     * Daftar role yang tersedia (dinamis, dari tabel roles).
     */
    private function availableRoles(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Role boleh dipilih pada form user.
     */
    private function roleRules(): array
    {
        return [
            'required',
            'string',
            Rule::exists('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'web')),
        ];
    }

    // index
    public function index()
    {
        return view('pages.user.index', [
            'roles' => $this->availableRoles(),
        ]);
    }

    public function data(Request $request)
    {
        $query = User::where('status', '!=', -1);

        // Filter role dari dropdown
        if ($request->get('role')) {
            $query->where('role', $request->input('role'));
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('name', function (User $user) {
                return e($user->name);
            })

            ->editColumn('email', function (User $user) {
                return e($user->email);
            })

            ->editColumn('role', function (User $user) {
                $role = (string) $user->role;

                // Role bisa dinamis, warna mengikuti role bawaan yang sudah ada
                $color = match ($role) {
                    'admin' => 'primary',
                    'staff' => 'warning',
                    default => 'info',
                };

                return '<span class="badge badge-'.$color.'">'.e($role).'</span>';
            })

            ->addColumn('status', function (User $user) {
                if ($user->status === 1) {
                    return '<span class="badge badge-success">Active</span>';
                }
                if ($user->status === 0) {
                    return '<span class="badge badge-danger">Nonactive</span>';
                }

                return '<span class="badge badge-secondary">Deleted</span>';
            })

            ->editColumn('created_at', function (User $user) {
                return $user->created_at
                    ? $user->created_at->format('d F Y')
                    : '-';
            })

            ->addColumn('action', function (User $user) {
                return '<a href="'.route('user.edit', $user->id).'"
                            class="btn btn-sm btn-info btn-icon">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <form action="'.route('user.destroy', $user->id).'"
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

            ->rawColumns(['role', 'status', 'action'])
            ->toJson();
    }

    // create
    public function create()
    {
        return view('pages.user.create', [
            'roles' => $this->availableRoles(),
        ]);
    }

    // store
    public function store(Request $request)
    {
        //  validasi
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => $this->roleRules(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // simpan data
        $user = new User;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->status = 1;
        $user->created_by = Auth::user()->id;
        $user->password = Hash::make($request->password);
        $user->save();

        // Samakan role Spatie dengan kolom role
        $user->syncRoleFromColumn();

        return redirect()->route('user.index')->with('success', 'User created successfully');
    }

    // show
    public function show($id)
    {
        return view('pages.user.show', [
            'user' => User::find($id),
        ]);
    }

    // edit
    public function edit($id)
    {
        $user = User::findOrFail($id);
        $roles = $this->availableRoles();

        // Role milik user ini tetap selectable walau role-nya dihapus
        if ($user->role && ! in_array($user->role, $roles, true)) {
            $roles[] = $user->role;
        }

        return view('pages.user.edit', [
            'user' => $user,
            'roles' => $roles,
            'menuModules' => $this->menu->modules(),
        ]);
    }

    // update
    public function update(Request $request, $id)
    {
        // validasi
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => $this->roleRules(),
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // simpan data
        $user = User::find($id);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->status = $request->status ?? $user->status;
        $user->updated_by = Auth::user()->id;
        $user->save();

        if ($request->password) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        // Role menentukan hak akses bawaan...
        $user->syncRoleFromColumn();

        // ...sedangkan daftar di bawah adalah akses menu tambahan untuk user ini.
        $user->syncPermissions(
            array_values(array_intersect(
                (array) $request->input('permissions', []),
                $this->menu->permissions()
            ))
        );

        return redirect()->route('user.index')->with('success', 'User updated successfully');
    }

    // delete
    public function destroy($id)
    {
        // User::findOrFail($id)->delete();
        // update status -1
        User::find($id)->update([
            'status' => -1,
            'updated_by' => Auth::user()->id,
        ]);

        return redirect()->route('user.index')->with('success', 'User deleted successfully');
    }
}
