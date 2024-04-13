<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('pages.categories.index');
    }

    public function json()
    {
        $data = Category::get();
		$dt = DataTables::of($data)
		->addColumn('name', function($row){
			return $row->name;
        })
		->addColumn('created_at', function($row){
			return date('d-m-Y', strtotime($row->created_at));
        })
		->addColumn('aksi', function($row){
			return '
			<span data-toggle="tooltip" title="Edit">
			<a href="'.route('categories.edit', $row->id).'" class="btn btn-flat btn-sm btn-primary" > <i class="fa fa-pencil"></i></a></span>

			<span data-toggle="tooltip" title="Hapus"><a href="javascript:void(0)" data-id="'.$row->id.'" data-toggle="modal" data-target="#delItem"  class="btn btn-flat btn-danger btn-sm mr-1 btn-delete"><i class="fa fa-trash"></i></a></span>
		';
		})
        ->rawColumns(['aksi'])
        ->addIndexColumn()
        ->make();
		echo json_encode($dt->original);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.categories.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $category = new Category;
        $category->name = $request->name;
        $category->description = $request->description;

        // save image
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $image->storeAs('public/categories', $category->id. '.'. $image->getClientOriginalExtension());
            $category->image = $image->hashName();
        }
        $category->save();

        return redirect()->route('categories.index')->with('success', 'Category created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return view('pages.categories.show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $category = Category::findOrFail($id);
        return view('pages.categories.edit', compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required',
            'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $category = Category::findOrFail($id);
        $category->name = $request->name;
        $category->description = $request->description;

        // save image
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $image->storeAs('public/categories', $category->id. '.'. $image->getClientOriginalExtension());
            $category->image = $image->hashName();
            // $category->save();
        }
        $category->save();

        return redirect()->route('categories.index')->with('success', 'Category updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::find($id);
        $category->delete();
        return response()->json(['success' => 'Data berhasil dihapus!']);
    }
}
