<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    /**
     * Daftar menu (GET /api/menus).
     *
     * Query opsional:
     * - q / name      : cari pada name, code, description
     * - active_only   : hanya menu aktif (status = 1)
     * - category_id   : filter berdasarkan kategori
     * - type          : filter berdasarkan jenis kategori (makanan/minuman/lainnya)
     * - min_price     : harga minimum
     * - max_price     : harga maksimum
     * - sort / order  : kolom & arah pengurutan (default id desc)
     * - per_page      : jumlah data per halaman (maks 100, default 20)
     */
    public function index(Request $request)
    {
        $query = Menu::with(['category', 'creator', 'updater'])
            ->withCount('bahanBakus')
            ->where('status', '!=', -1);

        if ($request->filled('q') || $request->filled('name')) {
            $search = $request->input('q', $request->input('name'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->boolean('active_only')) {
            $query->where('status', 1);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('type')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('type', $request->input('type'));
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        // Batasi kolom pengurutan yang diizinkan untuk keamanan.
        $allowedSorts = ['id', 'name', 'code', 'price', 'status', 'created_at', 'updated_at'];
        $sortField = $request->input('sort', 'id');
        $sortField = in_array($sortField, $allowedSorts, true) ? $sortField : 'id';
        $sortDir = strtolower($request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $data = $query->orderBy($sortField, $sortDir)
            ->paginate(min((int) $request->input('per_page', 20), 100));

        $data->getCollection()->transform(fn (Menu $menu) => $this->withExtras($menu));

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Detail satu menu beserta resep (GET /api/menus/{id}).
     */
    public function show($id)
    {
        $menu = Menu::with(['category', 'bahanBakus.satuan', 'creator', 'updater'])
            ->withCount('bahanBakus')
            ->where('status', '!=', -1)
            ->find($id);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->withExtras($menu),
        ], 200);
    }

    /**
     * Simpan menu baru (POST /api/menus).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

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
            'status' => $request->input('status', 1),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        // sync resep: menu -> banyak bahan baku (pivot menu_bahan_bakus)
        $menu->bahanBakus()->sync($this->extractIngredients($request));

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil dibuat.',
            'data' => $this->withExtras($menu->fresh(['category', 'bahanBakus.satuan', 'creator', 'updater'])),
        ], 201);
    }

    /**
     * Update menu (PUT/PATCH /api/menus/{id}).
     */
    public function update(Request $request, $id)
    {
        $menu = Menu::find($id);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu tidak ditemukan.',
            ], 404);
        }

        if ((int) $menu->status === -1) {
            return response()->json([
                'success' => false,
                'message' => 'Menu sudah dihapus.',
            ], 400);
        }

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // upload image baru & hapus gambar lama bila ada
        $imageName = $menu->image;
        if ($request->hasFile('image')) {
            $this->deleteImage($menu->image);
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
            'status' => $request->input('status', $menu->status),
            'updated_by' => auth()->id(),
        ]);

        // sync resep: detach yang dihapus, update quantity, attach baru
        $menu->bahanBakus()->sync($this->extractIngredients($request));

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil diupdate.',
            'data' => $this->withExtras($menu->fresh(['category', 'bahanBakus.satuan', 'creator', 'updater'])),
        ], 200);
    }

    /**
     * Hapus menu (soft delete via status -1) (DELETE /api/menus/{id}).
     */
    public function destroy($id)
    {
        $menu = Menu::find($id);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu tidak ditemukan.',
            ], 404);
        }

        if ((int) $menu->status === -1) {
            return response()->json([
                'success' => false,
                'message' => 'Menu sudah dihapus.',
            ], 400);
        }

        $menu->update([
            'status' => -1,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil dihapus.',
        ], 200);
    }

    /**
     * Tambahkan attribute turunan: URL gambar & label jenis kategori.
     */
    protected function withExtras(Menu $menu): Menu
    {
        $menu->image_url = MenuImageUrl($menu);

        if ($menu->relationLoaded('category') && $menu->category) {
            $type = $menu->category->type ?: Category::TYPE_LAINNYA;
            $menu->category->type_label = Category::types()[$type] ?? ucfirst($type);
        }

        return $menu;
    }

    /**
     * Aturan validasi untuk store & update.
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'status' => ['sometimes', 'boolean'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.bahan_baku_id' => ['required_with:ingredients', 'integer', 'exists:bahan_bakus,id'],
            'ingredients.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Konversi input resep menjadi payload pivot: [bahan_baku_id => ['quantity' => x]].
     *
     * Menerima format array objek: ingredients[].{bahan_baku_id, quantity}
     * (lebih natural untuk API selain format form encoded bawaan web).
     */
    protected function extractIngredients(Request $request): array
    {
        $payload = [];
        $ingredients = $request->input('ingredients') ?? [];

        foreach ($ingredients as $item) {
            $id = is_array($item) ? ($item['bahan_baku_id'] ?? null) : null;
            if (empty($id)) {
                continue;
            }

            $payload[(int) $id] = [
                'quantity' => (float) (is_array($item) ? ($item['quantity'] ?? 0) : 0),
            ];
        }

        return $payload;
    }

    /**
     * Hapus file gambar menu lama bila ada di disk.
     */
    protected function deleteImage(?string $image): void
    {
        if ($image) {
            $imagePath = public_path('images/menu/' . $image);
            if (File::exists($imagePath)) {
                File::delete($imagePath);
            }
        }
    }
}
