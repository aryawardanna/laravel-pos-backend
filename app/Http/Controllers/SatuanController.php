<?php

namespace App\Http\Controllers;

use App\Models\Satuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class SatuanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $satuans = Satuan::when($request->input('name'), function ($query, $name) {
            $query->where('name', 'like', '%' . $name . '%');
        })->orderBy('id', 'desc')->paginate(10);

        return view('pages.satuan.index', compact('satuans'));
    }

    /**
     * DataTables server-side.
     */
    public function data(Request $request)
    {
        $query = Satuan::with(['creator', 'updater'])->where('status', '!=', -1);

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('name', function (Satuan $satuan) {
                return e($satuan->name);
            })

            ->editColumn('code', function (Satuan $satuan) {
                return $satuan->code ? e($satuan->code) : '-';
            })

            ->editColumn('description', function (Satuan $satuan) {
                return $satuan->description
                    ? \Illuminate\Support\Str::limit(e($satuan->description), 50)
                    : '-';
            })

            ->editColumn('status', function (Satuan $satuan) {
                return $satuan->status == 1
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })

            ->editColumn('created_at', function (Satuan $satuan) {
                return $satuan->created_at
                    ? $satuan->created_at->format('d F Y')
                    : '-';
            })

            ->editColumn('created_by', function (Satuan $satuan) {
                return $satuan->creator ? e($satuan->creator->name) : '-';
            })

            ->editColumn('updated_by', function (Satuan $satuan) {
                return $satuan->updater ? e($satuan->updater->name) : '-';
            })

            // Action
            ->addColumn('action', function (Satuan $satuan) {
                return '<a href="' . route('satuan.edit', $satuan->id) . '"
                            class="btn btn-sm btn-info btn-icon">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <form action="' . route('satuan.destroy', $satuan->id) . '"
                            method="POST"
                            class="d-inline ml-2 delete-form">
                            ' . csrf_field() . '
                            ' . method_field('DELETE') . '
                            <button type="submit"
                                    class="btn btn-sm btn-danger btn-icon confirm-delete">
                                <i class="fas fa-times"></i> Delete
                            </button>
                        </form>';
            })

            // Izinkan HTML hanya untuk kolom status dan action
            ->rawColumns(['status', 'action'])
            ->toJson();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.satuan.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $status = 0;
        if ($request->status) {
            $status = $request->status;
        }

        Satuan::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'status' => $status,
            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
        ]);

        return redirect()->route('satuan.index')->with('success', 'Satuan created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $satuan = Satuan::findOrFail($id);
        return view('pages.satuan.edit', compact('satuan'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $status = 0;
        if ($request->status) {
            $status = $request->status;
        }

        Satuan::find($id)->update([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'status' => $status,
            'updated_by' => Auth::user()->id
        ]);

        return redirect()->route('satuan.index')->with('success', 'Satuan updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Satuan::findOrFail($id)->delete();
        // update status -1
        Satuan::find($id)->update([
            'status' => -1,
            'updated_by' => Auth::user()->id
        ]);
        return redirect()->route('satuan.index')->with('success', 'Satuan deleted successfully');
    }
}
