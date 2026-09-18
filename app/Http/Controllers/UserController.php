<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // index
    public function index()
    {
        return view('pages.user.index');
    }

    // DataTables (server-side processing)
    public function data(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = trim((string) $request->input('search.value', ''));
        $orderColumn = (int) $request->input('order.0.column', 4);
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $role = trim((string) $request->input('columns.3.search.value', ''));

        $sortableColumns = ['id', 'name', 'email', 'role', 'created_at'];
        $sortColumn = $sortableColumns[$orderColumn] ?? 'id';

        $query = User::query();

        // Global search (DataTables built-in search box)
        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('role', 'like', '%' . $search . '%');
            });
        }

        // Filter role (dari dropdown di view)
        if ($role !== '') {
            $query->where('role', $role);
        }

        $recordsTotal = User::count();
        $recordsFiltered = (clone $query)->count();

        if ($length > 0) {
            $users = $query->orderBy($sortColumn, $orderDir)->offset($start)->limit($length)->get();
        } else {
            $users = $query->orderBy($sortColumn, $orderDir)->get();
        }

        $data = $users->map(function (User $user, $key) use ($start) {
            return [
                'no' => $start + $key + 1,
                'name' => e($user->name),
                'email' => e($user->email),
                'role' => match ($user->role) {
                    'admin' => '<span class="badge badge-primary">admin</span>',
                    'staff' => '<span class="badge badge-warning">staff</span>',
                    default => '<span class="badge badge-secondary">' . e($user->role) . '</span>',
                },
                'created_at' => date('d F Y', strtotime($user->created_at)),
                'action' => '<a href="' . route('user.edit', $user->id) . '" class="btn btn-sm btn-info btn-icon"><i class="fas fa-edit"></i> Edit</a> '
                    . '<form action="' . route('user.destroy', $user->id) . '" method="POST" class="d-inline ml-2 delete-form">'
                    . '<input type="hidden" name="_method" value="DELETE">'
                    . '<input type="hidden" name="_token" value="' . csrf_token() . '">'
                    . '<button class="btn btn-sm btn-danger btn-icon confirm-delete"><i class="fas fa-times"></i> Delete</button>'
                    . '</form>',
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values()->all(),
        ]);
    }

    // create
    public function create()
    {
        return view('pages.user.create');
    }

    // store
    public function store(Request $request)
    {
        //  validasi
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:admin,staff,user'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // simpan data
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->route('user.index')->with('success', 'User created successfully');
    }

    // show
    public function show($id)
    {
        return view('pages.user.show', [
            'user' => User::find($id)
        ]);
    }

    // edit
    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('pages.user.edit', compact('user'));
    }

    // update
    public function update(Request $request, $id)
    {
        // validasi
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:admin,staff,user'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // simpan data
        $user = User::find($id);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->save();

        if ($request->password) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        return redirect()->route('user.index')->with('success', 'User updated successfully');
    }

    // delete
    public function destroy($id)
    {
        User::findOrFail($id)->delete();
        return redirect()->route('user.index')->with('success', 'User deleted successfully');
    }
}
