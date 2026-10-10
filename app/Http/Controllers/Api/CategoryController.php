<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::with(['creator', 'updater'])->where('status', '!=', -1);

        if ($request->filled('q') || $request->filled('name')) {
            $search = $request->input('q', $request->input('name'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->boolean('active_only')) {
            $query->where('status', 1);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $sortField = $request->input('sort', 'id');
        $sortDir = $request->input('order', 'desc');

        $data = $query->orderBy($sortField, $sortDir)
            ->paginate(min((int) $request->input('per_page', 20), 100));

        $data->getCollection()->transform(fn (Category $c) => $this->withExtras($c));

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    public function show($id)
    {
        $category = Category::with(['creator', 'updater'])
            ->where('status', '!=', -1)
            ->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->withExtras($category),
        ], 200);
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:' . implode(',', array_keys(Category::types()))],
            'status' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $imageName = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/category'), $imageName);
        }

        $category = Category::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->input('type', Category::TYPE_LAINNYA),
            'image' => $imageName,
            'status' => $request->input('status', 1),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dibuat.',
            'data' => $this->withExtras($category->fresh(['creator', 'updater'])),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan.',
            ], 404);
        }

        if ((int) $category->status === -1) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori sudah dihapus.',
            ], 400);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:' . implode(',', array_keys(Category::types()))],
            'status' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $imageName = $category->image;
        if ($request->hasFile('image')) {
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

        $category->update([
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->input('type', $category->type ?: Category::TYPE_LAINNYA),
            'image' => $imageName,
            'status' => $request->input('status', $category->status),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diupdate.',
            'data' => $this->withExtras($category->fresh(['creator', 'updater'])),
        ], 200);
    }

    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan.',
            ], 404);
        }

        if ((int) $category->status === -1) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori sudah dihapus.',
            ], 400);
        }

        $category->update([
            'status' => -1,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus.',
        ], 200);
    }

    /**
     * Tambahkan attribute turunan: label type & URL gambar.
     */
    protected function withExtras(Category $category): Category
    {
        $type = $category->type ?: Category::TYPE_LAINNYA;

        $category->type_label = Category::types()[$type] ?? ucfirst($type);

        if (empty($category->image)) {
            $category->image_url = null;
        } elseif (filter_var($category->image, FILTER_VALIDATE_URL)) {
            $category->image_url = $category->image;
        } else {
            $category->image_url = asset('images/category/' . $category->image);
        }

        return $category;
    }
}
