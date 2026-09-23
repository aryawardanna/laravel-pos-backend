<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Kartu stok: riwayat pergerakan stok bahan baku.
 *
 * Setiap pembelian yang diterima tercatat sebagai stok masuk per batch,
 * sedangkan pembatalan/koreksi pembelian tercatat sebagai stok keluar.
 */
class KartuStokController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bahanBakus = BahanBaku::where('status', '!=', -1)->orderBy('name')->get();

        return view('pages.kartu_stok.index', compact('bahanBakus'));
    }

    /**
     * DataTables server-side (filter bahan baku, jenis, referensi, dan rentang tanggal).
     */
    public function data(Request $request)
    {
        $query = StockMovement::with(['bahanBaku.satuan', 'creator', 'purchase'])
            ->orderBy('movement_date', 'asc')
            ->orderBy('id', 'asc');

        if ($request->filled('bahan_baku_id')) {
            $query->where('bahan_baku_id', $request->input('bahan_baku_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('reference')) {
            $query->where('reference', 'like', '%' . $request->input('reference') . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('movement_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('movement_date', '<=', $request->input('date_to'));
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('movement_date', function (StockMovement $movement) {
                return $movement->movement_date
                    ? $movement->movement_date->format('d F Y')
                    : '-';
            })

            ->editColumn('bahan_baku_id', function (StockMovement $movement) {
                return $movement->bahanBaku ? e($movement->bahanBaku->name) : '-';
            })

            ->addColumn('satuan', function (StockMovement $movement) {
                return $movement->bahanBaku && $movement->bahanBaku->satuan
                    ? e($movement->bahanBaku->satuan->name)
                    : '-';
            })

            ->editColumn('batch_code', function (StockMovement $movement) {
                return $movement->batch_code ? e($movement->batch_code) : '-';
            })

            ->editColumn('type', function (StockMovement $movement) {
                if ($movement->type === StockMovement::TYPE_IN) {
                    return '<span class="badge badge-success">Masuk</span>';
                }

                if ($movement->type === StockMovement::TYPE_OUT) {
                    return '<span class="badge badge-danger">Keluar</span>';
                }

                return '<span class="badge badge-warning">Penyesuaian</span>';
            })

            ->editColumn('quantity_in', function (StockMovement $movement) {
                return (float) $movement->quantity_in > 0
                    ? e(FormatQty($movement->quantity_in))
                    : '-';
            })

            ->editColumn('quantity_out', function (StockMovement $movement) {
                return (float) $movement->quantity_out > 0
                    ? e(FormatQty($movement->quantity_out))
                    : '-';
            })

            ->editColumn('balance', function (StockMovement $movement) {
                return e(FormatQty($movement->balance));
            })

            ->editColumn('unit_price', function (StockMovement $movement) {
                return (float) $movement->unit_price > 0
                    ? e(FormatMoney($movement->unit_price))
                    : '-';
            })

            // referensi dokumen: tautan ke detail pembelian bila tersedia
            ->addColumn('reference_link', function (StockMovement $movement) {
                if (!$movement->purchase) {
                    return $movement->reference ? e($movement->reference) : '-';
                }

                return '<a href="' . route('purchase.show', $movement->purchase->id) . '">'
                    . e($movement->reference) . '</a>';
            })

            ->editColumn('description', function (StockMovement $movement) {
                return $movement->description ? e($movement->description) : '-';
            })

            ->editColumn('created_by', function (StockMovement $movement) {
                return $movement->creator ? e($movement->creator->name) : '-';
            })

            ->rawColumns(['type', 'reference_link'])
            ->toJson();
    }
}
