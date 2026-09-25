<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use File;
use Yajra\DataTables\Facades\DataTables;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return view('pages.category.index');
    }


    public function data(Request $request)
    {
        $hasStatus = Schema::hasColumn('categories', 'status');
        $hasCreatedBy = Schema::hasColumn('categories', 'created_by');
        $hasUpdatedBy = Schema::hasColumn('categories', 'updated_by');

        $query = Category::query();

        $with = [];
        if ($hasCreatedBy) {
            $with[] = 'creator';
        }
        if ($hasUpdatedBy) {
            $with[] = 'updater';
        }
        if (! empty($with)) {
            $query->with($with);
        }

        // Tabel lama belum punya kolom status -> jangan filter pakai status agar tidak SQL error.
        // Setelah migrasi add_status jalan, filter soft-delete (-1) otomatis aktif.
        if ($hasStatus) {
            $query->where('status', '!=', -1);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('name', function (Category $category) {
                return e($category->name);
            })

            ->addColumn('description', function (Category $category) {
                return $category->description
                    ? Str::limit(e($category->description), 50)
                    : '-';
            })

            // Image
            ->editColumn('image', function (Category $category) {
                if (empty($category->image)) {
                    return '-';
                }

                // Jika image berupa URL lengkap, gunakan langsung.
                // Jika hanya nama file, gunakan folder images/category.
                $imageUrl = filter_var($category->image, FILTER_VALIDATE_URL)
                    ? $category->image
                    : asset('images/category/' . $category->image);

                return '<img src="' . e($imageUrl) . '"
                            width="50"
                            height="50"
                            style="object-fit: cover; border-radius: 5px;"
                            alt="Category Image">';
            })

            ->addColumn('status', function (Category $category) {
                // Tabel lama belum punya kolom status -> anggap Active
                $status = $category->getAttribute('status') ?? 1;

                return (int) $status === 1
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })

            ->addColumn('created_by', function (Category $category) use ($hasCreatedBy) {
                if (! $hasCreatedBy) {
                    return '-';
                }

                return $category->creator ? e($category->creator->name) : '-';
            })

            ->addColumn('updated_by', function (Category $category) use ($hasUpdatedBy) {
                if (! $hasUpdatedBy) {
                    return '-';
                }

                return $category->updater ? e($category->updater->name) : '-';
            })

            ->editColumn('created_at', function (Category $category) {
                return $category->created_at
                    ? $category->created_at->format('d F Y')
                    : '-';
            })

            // Action
            ->addColumn('action', function (Category $category) {
                return '<a href="' . route('category.edit', $category->id) . '"
                            class="btn btn-sm btn-info btn-icon">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <form action="' . route('category.destroy', $category->id) . '"
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

            // Izinkan HTML hanya untuk kolom image, status, dan action
            ->rawColumns(['image', 'status', 'action'])
            ->toJson();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.category.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // upload image
        $imageName = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/category'), $imageName);
        }

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'image' => $imageName,
        ];

        if (Schema::hasColumn('categories', 'created_by')) {
            $data['created_by'] = Auth::user()->id;
        }

        if (Schema::hasColumn('categories', 'status')) {
            $data['status'] = $request->has('status') ? (int) $request->status : 1;
        }
        if (Schema::hasColumn('categories', 'updated_by')) {
            $data['updated_by'] = Auth::user()->id;
        }

        Category::create($data);

        return redirect()->route('category.index')->with('success', 'Category created successfully');
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
        $category = Category::findOrFail($id);
        return view('pages.category.edit', compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $category = Category::findOrFail($id);

        // upload image
        $imageName = $category->image;
        if ($request->hasFile('image')) {
            // delete image old
            if ($category->image) {
                $imagePath = public_path('images/category/' . $category->image);
                if (File::exists($imagePath)) {
                    File::delete($imagePath);
                }
            }
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/category'), $imageName);
        }

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'image' => $imageName,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('categories', 'updated_by')) {
            $data['updated_by'] = Auth::user()->id;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('categories', 'status')) {
            $data['status'] = $request->has('status') ? (int) $request->status : (int) ($category->getAttribute('status') ?? 1);
        }

        $category->update($data);

        return redirect()->route('category.index')->with('success', 'Category updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // delete img old
        $category = Category::findOrFail($id);
        if ($category->image) {

            $imagePath = public_path('images/category/' . $category->image);
            if (File::exists($imagePath)) {
                File::delete($imagePath);
            }
        }

        // Kalau kolom status belum ada (DB lama), hapus permanen.
        // Kalau sudah ada, soft-delete via status -1 seperti modul lain.
        if (\Illuminate\Support\Facades\Schema::hasColumn('categories', 'status')) {
            $category->update([
                'status' => -1,
                'updated_by' => Auth::user()->id,
            ]);
        } else {
            $category->delete();
        }

        return redirect()->route('category.index')->with('success', 'Category deleted successfully');
    }
}
