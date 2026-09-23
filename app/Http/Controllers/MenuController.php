<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\Category;
use App\Models\Menu;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class MenuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $menus = Menu::when($request->input('name'), function ($query, $name) {
            $query->where('name', 'like', '%' . $name . '%');
        })->orderBy('id', 'desc')->paginate(10);

        return view('pages.menu.index', compact('menus'));
    }

    /**
     * DataTables server-side.
     */
    public function data(Request $request)
    {
        $query = Menu::with(['category', 'bahanBakus', 'creator', 'updater'])->where('status', '!=', -1);

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('name', function (Menu $menu) {
                return e($menu->name);
            })

            ->editColumn('code', function (Menu $menu) {
                return $menu->code ? e($menu->code) : '-';
            })

            // Image (memakai gambar default bila menu belum punya gambar)
            ->editColumn('image', function (Menu $menu) {
                return '<img src="' . e(MenuImageUrl($menu)) . '"
                            width="50"
                            height="50"
                            style="object-fit: cover; border-radius: 5px;"
                            alt="Menu Image">';
            })

            ->editColumn('category_id', function (Menu $menu) {
                return $menu->category ? e($menu->category->name) : '-';
            })

            ->editColumn('price', function (Menu $menu) {
                return e(number_format($menu->price, 2));
            })

            ->editColumn('bahan_baku_count', function (Menu $menu) {
                $total = $menu->bahanBakus->count();
                return $total > 0
                    ? '<span class="badge badge-info">' . $total . ' Bahan Baku</span>'
                    : '<span class="badge badge-secondary">0</span>';
            })

            ->editColumn('status', function (Menu $menu) {
                return $menu->status == 1
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })

            ->editColumn('created_by', function (Menu $menu) {
                return $menu->creator ? e($menu->creator->name) : '-';
            })

            ->editColumn('updated_by', function (Menu $menu) {
                return $menu->updater ? e($menu->updater->name) : '-';
            })

            ->editColumn('created_at', function (Menu $menu) {
                return $menu->created_at
                    ? $menu->created_at->format('d F Y')
                    : '-';
            })

            // Action
            ->addColumn('action', function (Menu $menu) {
                return '<a href="' . route('menu.edit', $menu->id) . '"
                            class="btn btn-sm btn-info btn-icon">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <form action="' . route('menu.destroy', $menu->id) . '"
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

            ->rawColumns(['image', 'bahan_baku_count', 'status', 'action'])
            ->toJson();
    }

    /**
     * Konversi input bahan baku (ingredients[bahan_baku_id][] + ingredients[quantity][])
     * menjadi payload pivot: [bahan_baku_id => ['quantity' => x]].
     */
    private function extractIngredients(Request $request): array
    {
        $payload = [];
        $ingredients = $request->input('ingredients') ?? [];
        $ids = $ingredients['bahan_baku_id'] ?? [];
        $quantities = $ingredients['quantity'] ?? [];

        foreach ($ids as $index => $id) {
            if (empty($id)) {
                continue;
            }

            $payload[(int) $id] = [
                'quantity' => (float) ($quantities[$index] ?? 0),
            ];
        }

        return $payload;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $bahanBakus = BahanBaku::with('satuan')->where('status', '!=', -1)->orderBy('name')->get();
        return view('pages.menu.create', compact('categories', 'bahanBakus'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'integer'],
            'price' => ['nullable', 'numeric'],
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
            $image->move(public_path('images/menu'), $imageName);
        }

        $menu = Menu::create([
            'name' => $request->name,
            'code' => $request->code,
            'category_id' => $request->category_id,
            'price' => StoreMoney($request->price) ?? 0,
            'description' => $request->description,
            'image' => $imageName,
            'status' => $status,
            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
        ]);

        // sync resep: menu -> banyak bahan baku (pivot menu_bahan_bakus)
        $menu->bahanBakus()->sync($this->extractIngredients($request));

        return redirect()->route('menu.index')->with('success', 'Menu created successfully');
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
        $menu = Menu::with(['bahanBakus.satuan'])->findOrFail($id);
        $categories = Category::orderBy('name')->get();
        $bahanBakus = BahanBaku::with('satuan')->where('status', '!=', -1)->orderBy('name')->get();
        return view('pages.menu.edit', compact('menu', 'categories', 'bahanBakus'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'integer'],
            'price' => ['nullable', 'numeric'],
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

        $menu = Menu::find($id);

        // upload image (delete gambar lama jika ada gambar baru)
        $imageName = $menu->image;
        if ($request->hasFile('image')) {
            if ($menu->image) {
                $imagePath = public_path('images/menu/' . $menu->image);
                if (File::exists($imagePath)) {
                    File::delete($imagePath);
                }
            }
            $image = $request->file('image');
            $imageName = time() . '-' . Str::random(6) . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/menu'), $imageName);
        }

        $menu->update([
            'name' => $request->name,
            'code' => $request->code,
            'category_id' => $request->category_id,
            'price' => StoreMoney($request->price) ?? 0,
            'description' => $request->description,
            'image' => $imageName,
            'status' => $status,
            'updated_by' => Auth::user()->id,
        ]);

        // sync resep: detach bahan baku yang hapus, update quantity, attach baru
        $menu->bahanBakus()->sync($this->extractIngredients($request));

        return redirect()->route('menu.index')->with('success', 'Menu updated successfully');
    }

    /**
     * Remove the specified resource from storage (soft delete via status -1).
     */
    public function destroy(string $id)
    {
        Menu::find($id)->update([
            'status' => -1,
            'updated_by' => Auth::user()->id,
        ]);
        return redirect()->route('menu.index')->with('success', 'Menu deleted successfully');
    }
}