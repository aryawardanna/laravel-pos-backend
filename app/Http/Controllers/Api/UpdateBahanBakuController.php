<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use Illuminate\Http\Request;

class UpdateBahanBakuController extends Controller
{
    public function __invoke(Request $request, $id)
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
}
