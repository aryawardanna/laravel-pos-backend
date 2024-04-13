<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\DataTables;

class UserController extends Controller
{
    //index
    public function index(Request $request)
    {
        // get all users with pagination
        return view('pages.users.index');
    }

    public function json()
    {
        $data = User::get();
		$dt = DataTables::of($data)
		->addColumn('name', function($row){
			return $row->name;
        })
        ->addColumn('email', function($row){
			return $row->email;
        })
        ->addColumn('role', function($row){
			return $row->role;
        })
		->addColumn('created_at', function($row){
			return date('d-m-Y', strtotime($row->created_at));
        })
		->addColumn('aksi', function($row){
			return '
			<span data-toggle="tooltip" title="Edit">
			<a href="'.route('user.edit', $row->id).'" class="btn btn-flat btn-sm btn-primary" > <i class="fa fa-pencil"></i></a></span>

			<span data-toggle="tooltip" title="Hapus"><a href="javascript:void(0)" data-id="'.$row->id.'" data-toggle="modal" data-target="#delItem" class="btn btn-flat btn-danger btn-sm mr-1 btn-delete"><i class="fa fa-trash"></i></a></span>
		';
		})
        ->rawColumns(['aksi'])
        ->addIndexColumn()
        ->make();
		echo json_encode($dt->original);
    }

    //create
    public function create()
    {
        return view('pages.users.create');
    }

    //store
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,user,staff',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->role = $request->role;
        $user->save();

        return redirect()->route('user.index')->with('success', 'User created successfully');
    }

    // show
    public function show($id)
    {
        return view('pages.users.show');
    }

    // edit
    public function edit($id)
    {
        // get user by id
        $user = User::findOrFail($id);
        return view('pages.users.edit', compact('user'));
    }

    // update
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required',
            'password' => 'nullable|min:8',
            'role' => 'required|in:admin,user,staff',
        ]);

        $user = User::find($id);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->save();

        // if password is not empty
        if (!empty($request->password)) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        return redirect()->route('user.index')->with('success', 'User updated successfully');
    }

    // destroy
    public function destroy($id)
    {
        $user = User::find($id);
        $user->delete();

        return response()->json(['success' => 'Data berhasil dihapus!']);
    }
}
