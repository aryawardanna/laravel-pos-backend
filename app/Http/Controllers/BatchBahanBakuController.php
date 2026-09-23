<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Daftar batch/lot stok bahan baku.
 *
 * Setiap baris purchase_items (dari pembelian berstatus diterima) adalah satu batch,
 * sehingga satu bahan baku dapat memiliki banyak batch dengan harga beli,
 * tanggal penerimaan, dan tanggal kedaluwarsa yang berbeda.
 */
class BatchBahanBakuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bahanBakus = BahanBaku::where('status', '!=', -1)->orderBy('name')->get();

        return view('pages.batch_bahan_baku.index', compact('bahanBakus'));
    }

    /**
     * DataTables server-side (filter bahan baku, sisa stok, dan kedaluwarsa).
     */
    public function data(Request $request)
    {
        $query = PurchaseItem::with(['bahanBaku.satuan', 'purchase.supplier'])
            ->where(function ($batchQuery) {
                // batch dari pembelian yang sudah diterima + lot penyesuaian
                // (stok awal, stock opname, koreksi stok manual)
                $batchQuery->where('source', PurchaseItem::SOURCE_ADJUSTMENT)
                    ->orWhereHas('purchase', function ($purchaseQuery) {
                        $purchaseQuery->where('status', Purchase::STATUS_RECEIVED);
                    });
            })
            // FEFO: batch yang paling cepat kedaluwarsa tampil lebih dahulu
            ->orderByRaw('expired_date is null, expired_date asc')
            ->orderBy('id');

        if ($request->filled('bahan_baku_id')) {
            $query->where('bahan_baku_id', $request->input('bahan_baku_id'));
        }

        if ($request->input('only_available') === '1') {
            $query->where('remaining_qty', '>', 0);
        }

        if ($request->filled('expired_from')) {
            $query->whereDate('expired_date', '>=', $request->input('expired_from'));
        }

        if ($request->filled('expired_to')) {
            $query->whereDate('expired_date', '<=', $request->input('expired_to'));
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('batch_code', function (PurchaseItem $item) {
                return $item->batch_code ? e($item->batch_code) : '-';
            })

            // sumber batch: pembelian atau lot penyesuaian (stok awal/opname)
            ->editColumn('source', function (PurchaseItem $item) {
                return $item->isAdjustment()
                    ? '<span class="badge badge-warning">' . e($item->sourceLabel()) . '</span>'
                    : '<span class="badge badge-info">' . e($item->sourceLabel()) . '</span>';
            })

            ->editColumn('bahan_baku_id', function (PurchaseItem $item) {
                return $item->bahanBaku ? e($item->bahanBaku->name) : '-';
            })

            ->addColumn('satuan', function (PurchaseItem $item) {
                return $item->bahanBaku && $item->bahanBaku->satuan
                    ? e($item->bahanBaku->satuan->name)
                    : '-';
            })

            ->editColumn('quantity', function (PurchaseItem $item) {
                return e(FormatQty($item->quantity));
            })

            ->editColumn('remaining_qty', function (PurchaseItem $item) {
                if ((float) $item->remaining_qty <= 0) {
                    return '<span class="badge badge-secondary">0 (habis)</span>';
                }

                return '<span class="badge badge-info">' . e(FormatQty($item->remaining_qty)) . '</span>';
            })

            ->editColumn('unit_price', function (PurchaseItem $item) {
                return e(FormatMoney($item->unit_price));
            })

            ->addColumn('remaining_value', function (PurchaseItem $item) {
                return e(FormatMoney((float) $item->remaining_qty * (float) $item->unit_price));
            })

            ->editColumn('expired_date', function (PurchaseItem $item) {
                if (empty($item->expired_date)) {
                    return '-';
                }

                if ($item->expired_date->isPast()) {
                    return '<span class="badge badge-danger">'
                        . $item->expired_date->format('d F Y') . ' (Kedaluwarsa)</span>';
                }

                if ($item->expired_date->lessThanOrEqualTo(now()->addDays(30))) {
                    return '<span class="badge badge-warning">'
                        . $item->expired_date->format('d F Y') . ' (Segera)</span>';
                }

                return $item->expired_date->format('d F Y');
            })

            ->addColumn('supplier', function (PurchaseItem $item) {
                return $item->purchase && $item->purchase->supplier
                    ? e($item->purchase->supplier->name)
                    : '-';
            })

            ->addColumn('purchase', function (PurchaseItem $item) {
                if (!$item->purchase) {
                    return '-';
                }

                return '<a href="' . route('purchase.show', $item->purchase->id) . '">'
                    . e($item->purchase->code) . '</a>';
            })

            ->addColumn('received_date', function (PurchaseItem $item) {
                if ($item->purchase && $item->purchase->purchase_date) {
                    return $item->purchase->purchase_date->format('d F Y');
                }

                // lot penyesuaian tidak punya pembelian: pakai tanggal lot dibuat
                return $item->created_at ? $item->created_at->format('d F Y') : '-';
            })

            ->rawColumns(['remaining_qty', 'expired_date', 'purchase', 'source'])
            ->toJson();
    }
}
