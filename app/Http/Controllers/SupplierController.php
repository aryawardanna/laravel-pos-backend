<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $suppliers = Supplier::when($request->input('name'), function ($query, $name) {
            $query->where('name', 'like', '%' . $name . '%');
        })->orderBy('id', 'desc')->paginate(10);

        return view('pages.supplier.index', compact('suppliers'));
    }

    /**
     * DataTables server-side.
     */
    public function data(Request $request)
    {
        $query = Supplier::with(['creator', 'updater']);

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('name', function (Supplier $supplier) {
                return e($supplier->name);
            })

            ->editColumn('phone', function (Supplier $supplier) {
                return $supplier->phone ? e($supplier->phone) : '-';
            })

            ->editColumn('email', function (Supplier $supplier) {
                return $supplier->email ? e($supplier->email) : '-';
            })

            ->editColumn('address', function (Supplier $supplier) {
                return $supplier->address
                    ? \Illuminate\Support\Str::limit(e($supplier->address), 50)
                    : '-';
            })

            ->editColumn('status', function (Supplier $supplier) {
                return $supplier->status == 1
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })

            ->editColumn('created_by', function (Supplier $supplier) {
                return $supplier->creator ? e($supplier->creator->name) : '-';
            })

            ->editColumn('updated_by', function (Supplier $supplier) {
                return $supplier->updater ? e($supplier->updater->name) : '-';
            })

            ->editColumn('created_at', function (Supplier $supplier) {
                return $supplier->created_at
                    ? $supplier->created_at->format('d F Y')
                    : '-';
            })

            // Action
            ->addColumn('action', function (Supplier $supplier) {
                return '<a href="' . route('supplier.edit', $supplier->id) . '"
                            class="btn btn-sm btn-info btn-icon">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <form action="' . route('supplier.destroy', $supplier->id) . '"
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
        return view('pages.supplier.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
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

        Supplier::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'description' => $request->description,
            'status' => $status,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('supplier.index')->with('success', 'Supplier created successfully');
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
        $supplier = Supplier::findOrFail($id);
        return view('pages.supplier.edit', compact('supplier'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
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

        Supplier::find($id)->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'description' => $request->description,
            'status' => $status,
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('supplier.index')->with('success', 'Supplier updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Supplier::findOrFail($id)->delete();
        return redirect()->route('supplier.index')->with('success', 'Supplier deleted successfully');
    }
}