<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use Illuminate\Http\Request;

class BahanBakuController extends Controller
{
    public function index(Request $request)
    {
        $query = BahanBaku::where('status', '!=', -1);

        if ($request->filled('q') || $request->filled('name')) {
            $search = $request->input('q', $request->input('name'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        if ($request->boolean('active_only')) {
            $query->where('status', 1);
        }

        if ($request->filled('satuan_id')) {
            $query->where('satuan_id', $request->input('satuan_id'));
        }

        if ($request->filled('min_stock')) {
            $query->where('min_stock', '>=', $request->input('min_stock'));
        }

        if ($request->filled('search_stock_below_min')) {
            $query->where('stock', '<=', 'min_stock');
        }

        $sortField = $request->input('sort', 'id');
        $sortDir = $request->input('order', 'desc');

        $data = $query->orderBy($sortField, $sortDir)
            ->paginate(min((int) $request->input('per_page', 20), 100));

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    public function show($id)
    {
        $bahanBaku = BahanBaku::with(['satuan', 'creator', 'updater'])
            ->where('status', '!=', -1)
            ->find($id);

        if (!$bahanBaku) {
            return response()->json([
                'success' => false,
                'message' => 'Bahan baku tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $bahanBaku,
        ], 200);
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'satuan_id' => ['required', 'exists:satuans,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'boolean'],
            'code' => ['sometimes', 'string', 'max:255'],
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['name', 'satuan_id', 'price', 'stock', 'min_stock', 'description', 'status', 'code']);
        $data['price'] = $data['price'] ?? 0;
        $data['stock'] = $data['stock'] ?? 0;
        $data['min_stock'] = $data['min_stock'] ?? 0;
        $data['status'] = $data['status'] ?? 1;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $bahanBaku = BahanBaku::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Bahan baku berhasil dibuat.',
            'data' => $bahanBaku->fresh(['satuan']),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $bahanBaku = BahanBaku::find($id);

        if (!$bahanBaku) {
            return response()->json([
                'success' => false,
                'message' => 'Bahan baku tidak ditemukan.',
            ], 404);
        }

        if ($bahanBaku->status === -1) {
            return response()->json([
                'success' => false,
                'message' => 'Bahan baku sudah dihapus.',
            ], 400);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'satuan_id' => ['required', 'exists:satuans,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'boolean'],
            'code' => ['sometimes', 'string', 'max:255'],
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['name', 'satuan_id', 'price', 'min_stock', 'description', 'status', 'code']);
        $data['price'] = $data['price'] ?? $bahanBaku->price;
        $data['min_stock'] = $data['min_stock'] ?? $bahanBaku->min_stock;
        $data['updated_by'] = auth()->id();

        $bahanBaku->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Bahan baku berhasil diupdate.',
            'data' => $bahanBaku->fresh(['satuan']),
        ], 200);
    }

    public function destroy($id)
    {
        $bahanBaku = BahanBaku::find($id);

        if (!$bahanBaku) {
            return response()->json([
                'success' => false,
                'message' => 'Bahan baku tidak ditemukan.',
            ], 404);
        }

        if ($bahanBaku->status === -1) {
            return response()->json([
                'success' => false,
                'message' => 'Bahan baku sudah dihapus.',
            ], 400);
        }

        $bahanBaku->update([
            'status' => -1,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bahan baku berhasil dihapus.',
        ], 200);
    }
}
