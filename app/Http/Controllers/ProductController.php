<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // show all products
        $products = Product::with('category')->paginate(10);
        return view('pages.products.index', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::get();
        return view('pages.products.create', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'price' => 'required',
            'category_id' => 'required|exists:categories,id',
            'description' => 'required',
            'stock' => 'required|numeric',
            'status' => 'required|boolean',
            'is_favorite' => 'required|boolean',
        ]);

        // dd($request->all());
        // store product
        $product = new Product;
        $product->name = $request->name;
        $product->price = $request->price;
        $product->description = $request->description;
        $product->category_id = $request->category_id;
        $product->stock = $request->stock;
        $product->status = $request->status;
        $product->is_favorite = $request->is_favorite;
        $product->save();


        // save image
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $image->storeAs('public/products', $product->id. '.'. $image->getClientOriginalExtension());
            $product->image = 'storage/products/'. $product->id. '.'. $image->getClientOriginalExtension();
            $product->save();
        }

        return redirect()->route('products.index')->with('success', 'Product created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // show product
        return view('pages.products.show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // edit product
        $product = Product::findOrFail($id);
        $categories = Category::get();
        return view('pages.products.edit', compact('product','categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required',
            'price' => 'required',
            'category_id' => 'required',
            'description' => 'required',
            'stock' => 'required|numeric',
            'status' => 'required|boolean',
            'is_favorite' => 'required|boolean',
        ]);
        // update product
        $product = Product::find($id);
        $product->name = $request->name;
        $product->price = $request->price;
        $product->description = $request->description;
        $product->category_id = $request->category_id;
        $product->stock = $request->stock;
        $product->status = $request->status;
        $product->is_favorite = $request->is_favorite;
        $product->save();


        // save image and delete old image
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $image->storeAs('public/products', $product->id. '.'. $image->getClientOriginalExtension());
            $product->image = 'storage/products/'. $product->id. '.'. $image->getClientOriginalExtension();
            $product->save();
        }

        return redirect()->route('products.index')->with('success', 'Product updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // delete product
        $product = Product::findOrFail($id);
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Product deleted successfully');
    }
}
