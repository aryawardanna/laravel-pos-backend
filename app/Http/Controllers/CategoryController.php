<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use File;
use Illuminate\Support\Facades\Auth;
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
        $query = Category::where('status', '!=', -1);

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('name', function (Category $category) {
                return e($category->name);
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

            // Izinkan HTML hanya untuk kolom image dan action
            ->rawColumns(['image', 'action'])
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

        Category::create([
            'name' => $request->name,
            'description' => $request->description,
            'image' => $imageName,
            'created_by' => Auth::user()->id
        ]);

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
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // upload image
        if ($request->hasFile('image')) {
            // delete image old
            $category = Category::find($id);
            if ($category->image) {
                $imagePath = public_path('images/category' . $category->image);
                if (File::exists($imagePath)) {
                    File::delete($imagePath);
                }
            }
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/category'), $imageName);
        }

        Category::find($id)->update([
            'name' => $request->name,
            'description' => $request->description,
            'image' => $imageName,
            'updated_by' => Auth::user()->id
        ]);

        return redirect()->route('category.index')->with('success', 'Category updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // delete img old
        $category = Category::find($id);
        if ($category->image) {

            $imagePath = public_path('images/category/' . $category->image);
            if (File::exists($imagePath)) {
                File::delete($imagePath);
            }
        }

        Category::find($id)->update([
            'status' => -1,
            'updated_by' => Auth::user()->id
        ]);
        return redirect()->route('category.index')->with('success', 'Category deleted successfully');
    }
}
