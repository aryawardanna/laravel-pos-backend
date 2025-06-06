<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use File;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $categories = Category::get();

        $products = Product::when($request->input('name'), function ($query, $name) {
            $query->where('name', 'like', '%' . $name . '%');
        })
        ->when($request->input('category_id'), function ($query, $categoryId) {
            $query->where('category_id', $categoryId);
        })
        ->orderBy('id', 'desc')
        ->paginate(10);

        return view('pages.product.index', compact('categories', 'products'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::get();
        return view('pages.product.create', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // validator
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required'],
            'stock' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'status' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $status = 0;
        if ($request->status) {
            $status = $request->status;
        }

        $isFavorite = 0;
        if ($request->is_favorite) {
            $isFavorite = $request->is_favorite;
        }

        // upload image
        $imageName = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/product'), $imageName);
        }

        // simpan data
        Product::create([
            'name' => $request->name,
            'price' => StoreMoney($request->price),
            'stock' => $request->stock,
            'category_id' => $request->category_id,
            'description' => $request->description,
            'image' => $imageName,
            'status' => $status,
            'is_favorite' => $isFavorite,
        ]);

        return redirect()->route('product.index')->with('success', 'Product created successfully');
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
        $product = Product::findOrFail($id);
        $categories = Category::get();
        return view('pages.product.edit', compact('product', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required'],
            'stock' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'status' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // upload image
        $product = Product::find($id);
        $imageName = $product->image;
        if ($request->hasFile('image')) {
            // delete image old
            $product = Product::find($id);
            if ($product->image) {
                $imagePath = public_path('images/product/' . $product->image);
                if (File::exists($imagePath)) {
                    File::delete($imagePath);
                }
            }
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/product'), $imageName);
        }

        $status = 0;
        if ($request->status) {
            $status = $request->status;
        }

        $isFavorite = 0;
        if ($request->is_favorite) {
            $isFavorite = $request->is_favorite;
        }

        // simpan data
        Product::find($id)->update([
            'name' => $request->name,
            'price' =>StoreMoney($request->price),
            'stock' => $request->stock,
            'category_id' => $request->category_id,
            'description' => $request->description,
            'image' => $imageName,
            'status' => $status,
            'is_favorite' => $isFavorite,
        ]);

        return redirect()->route('product.index')->with('success', 'Product updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // delete img old
        $product = Product::find($id);
        if ($product->image) {
            $imagePath = public_path('images/product/' . $product->image);
            if (File::exists($imagePath)) {
                File::delete($imagePath);
            }
        }

        Product::find($id)->delete();
        return redirect()->route('product.index')->with('success', 'Product deleted successfully');
    }
}
