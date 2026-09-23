<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\Satuan;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class BahanBakuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $bahanBakus = BahanBaku::when($request->input('name'), function ($query, $name) {
            $query->where('name', 'like', '%' . $name . '%');
        })->orderBy('id', 'desc')->paginate(10);

        return view('pages.bahan_baku.index', compact('bahanBakus'));
    }

    /**
     * DataTables server-side.
     */
    public function data(Request $request)
    {
        $query = BahanBaku::with(['satuan', 'creator', 'updater'])->where('status', '!=', -1);

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('name', function (BahanBaku $bahanBaku) {
                return e($bahanBaku->name);
            })

            ->editColumn('code', function (BahanBaku $bahanBaku) {
                return $bahanBaku->code ? e($bahanBaku->code) : '-';
            })

            // Image
            ->editColumn('image', function (BahanBaku $bahanBaku) {
                if (empty($bahanBaku->image)) {
                    return '-';
                }

                // Jika image berupa URL lengkap, gunakan langsung.
                // Jika hanya nama file, gunakan folder images/bahan_baku.
                $imageUrl = filter_var($bahanBaku->image, FILTER_VALIDATE_URL)
                    ? $bahanBaku->image
                    : asset('images/bahan_baku/' . $bahanBaku->image);

                return '<img src="' . e($imageUrl) . '"
                            width="50"
                            height="50"
                            style="object-fit: cover; border-radius: 5px;"
                            alt="Bahan Baku Image">';
            })

            ->editColumn('satuan_id', function (BahanBaku $bahanBaku) {
                return $bahanBaku->satuan ? e($bahanBaku->satuan->name) : '-';
            })

            ->editColumn('price', function (BahanBaku $bahanBaku) {
                return e(number_format($bahanBaku->price, 2));
            })

            ->editColumn('stock', function (BahanBaku $bahanBaku) {
                return e(number_format($bahanBaku->stock, 2));
            })

            ->editColumn('min_stock', function (BahanBaku $bahanBaku) {
                return e(number_format($bahanBaku->min_stock, 2));
            })

            ->editColumn('status', function (BahanBaku $bahanBaku) {
                return $bahanBaku->status == 1
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })

            ->editColumn('created_by', function (BahanBaku $bahanBaku) {
                return $bahanBaku->creator ? e($bahanBaku->creator->name) : '-';
            })

            ->editColumn('updated_by', function (BahanBaku $bahanBaku) {
                return $bahanBaku->updater ? e($bahanBaku->updater->name) : '-';
            })

            ->editColumn('created_at', function (BahanBaku $bahanBaku) {
                return $bahanBaku->created_at
                    ? $bahanBaku->created_at->format('d F Y')
                    : '-';
            })

            // Action
            ->addColumn('action', function (BahanBaku $bahanBaku) {
                return '<a href="' . route('bahan_baku.edit', $bahanBaku->id) . '"
                            class="btn btn-sm btn-info btn-icon">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <form action="' . route('bahan_baku.destroy', $bahanBaku->id) . '"
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
            ->rawColumns(['image', 'status', 'action'])
            ->toJson();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $satuans = Satuan::where('status', '!=', -1)->orderBy('name')->get();
        return view('pages.bahan_baku.create', compact('satuans'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'satuan_id' => ['nullable', 'integer'],
            'price' => ['nullable', 'numeric'],
            'stock' => ['nullable', 'numeric'],
            'min_stock' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'status' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $status = 0;
        if ($request->status) {
            $status = $request->status;
        }

        // upload image
        $imageName = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '-' . Str::random(6) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/bahan_baku'), $imageName);
        }

        BahanBaku::create([
            'name' => $request->name,
            'code' => $request->code,
            'satuan_id' => $request->satuan_id,
            'price' => StoreMoney($request->price) ?? 0,
            'stock' => $request->stock ?? 0,
            'min_stock' => $request->min_stock ?? 0,
            'description' => $request->description,
            'image' => $imageName,
            'status' => $status,
            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
        ]);

        return redirect()->route('bahan_baku.index')->with('success', 'Bahan Baku created successfully');
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
        $bahanBaku = BahanBaku::findOrFail($id);
        $satuans = Satuan::where('status', '!=', -1)->orderBy('name')->get();
        return view('pages.bahan_baku.edit', compact('bahanBaku', 'satuans'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'satuan_id' => ['nullable', 'integer'],
            'price' => ['nullable', 'numeric'],
            'stock' => ['nullable', 'numeric'],
            'min_stock' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'status' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $status = 0;
        if ($request->status) {
            $status = $request->status;
        }

        $bahanBaku = BahanBaku::find($id);

        // upload image (delete gambar lama jika ada gambar baru)
        $imageName = $bahanBaku->image;
        if ($request->hasFile('image')) {
            if ($bahanBaku->image) {
                $imagePath = public_path('images/bahan_baku/' . $bahanBaku->image);
                if (File::exists($imagePath)) {
                    File::delete($imagePath);
                }
            }
            $image = $request->file('image');
            $imageName = time() . '-' . Str::random(6) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/bahan_baku'), $imageName);
        }

        $bahanBaku->update([
            'name' => $request->name,
            'code' => $request->code,
            'satuan_id' => $request->satuan_id,
            'price' => StoreMoney($request->price) ?? 0,
            'stock' => $request->stock ?? 0,
            'min_stock' => $request->min_stock ?? 0,
            'description' => $request->description,
            'image' => $imageName,
            'status' => $status,
            'updated_by' => Auth::user()->id,
        ]);

        return redirect()->route('bahan_baku.index')->with('success', 'Bahan Baku updated successfully');
    }

    /**
     * Remove the specified resource from storage (soft delete via status -1).
     */
    public function destroy(string $id)
    {
        BahanBaku::find($id)->update([
            'status' => -1,
            'updated_by' => Auth::user()->id,
        ]);
        return redirect()->route('bahan_baku.index')->with('success', 'Bahan Baku deleted successfully');
    }
}