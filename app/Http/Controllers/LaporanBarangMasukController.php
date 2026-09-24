<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\Facades\DataTables;

class LaporanBarangMasukController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::where('status', '!=', -1)->orderBy('name')->get(['id', 'name']);
        $bahanBakus = BahanBaku::where('status', '!=', -1)->orderBy('name')->get(['id', 'name']);
        $defaultFrom = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $defaultTo = $request->input('date_to', Carbon::now()->endOfMonth()->toDateString());

        return view('pages.laporan.barang_masuk.index', compact('suppliers', 'bahanBakus', 'defaultFrom', 'defaultTo'));
    }

    protected function baseQuery(Request $request)
    {
        $query = PurchaseItem::query()
            ->select('purchase_items.*')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->leftJoin('bahan_bakus', 'bahan_bakus.id', '=', 'purchase_items.bahan_baku_id')
            ->leftJoin('satuans', 'satuans.id', '=', 'bahan_bakus.satuan_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->with(['purchase.supplier', 'purchase.creator', 'bahanBaku.satuan'])
            ->orderBy('purchases.purchase_date', 'desc')
            ->orderBy('purchases.id', 'desc')
            ->orderBy('purchase_items.id', 'desc');

        if ($request->filled('date_from')) {
            $query->whereDate('purchases.purchase_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('purchases.purchase_date', '<=', $request->input('date_to'));
        }
        if ($request->filled('supplier_id')) {
            $query->where('purchases.supplier_id', $request->input('supplier_id'));
        }
        if ($request->filled('bahan_baku_id')) {
            $query->where('purchase_items.bahan_baku_id', $request->input('bahan_baku_id'));
        }
        if ($request->filled('status')) {
            $query->where('purchases.status', $request->input('status'));
        }
        if ($request->filled('code')) {
            $query->where('purchases.code', 'like', '%' . $request->input('code') . '%');
        }
        if ($request->filled('search_bahan')) {
            $query->where('bahan_bakus.name', 'like', '%' . $request->input('search_bahan') . '%');
        }

        return $query;
    }

    public function data(Request $request)
    {
        $query = $this->baseQuery($request);

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('purchase_date', fn (PurchaseItem $row) => $row->purchase && $row->purchase->purchase_date
                ? $row->purchase->purchase_date->format('d/m/Y')
                : '-')
            ->addColumn('code', function (PurchaseItem $row) {
                if (! $row->purchase) {
                    return '-';
                }

                return '<a href="' . route('purchase.show', $row->purchase->id) . '">' . e($row->purchase->code) . '</a>';
            })
            ->addColumn('supplier_name', fn (PurchaseItem $row) => $row->purchase && $row->purchase->supplier
                ? e($row->purchase->supplier->name)
                : '-')
            ->addColumn('bahan_name', fn (PurchaseItem $row) => $row->bahanBaku ? e($row->bahanBaku->name) : '-')
            ->addColumn('satuan_name', fn (PurchaseItem $row) => $row->bahanBaku && $row->bahanBaku->satuan
                ? e($row->bahanBaku->satuan->name)
                : '-')
            ->addColumn('batch_code', fn (PurchaseItem $row) => $row->batch_code ? e($row->batch_code) : '-')
            ->addColumn('quantity', fn (PurchaseItem $row) => e(FormatQty($row->quantity)))
            ->addColumn('unit_price', fn (PurchaseItem $row) => e(FormatMoney($row->unit_price)))
            ->addColumn('subtotal', fn (PurchaseItem $row) => e(FormatMoney($row->subtotal)))
            ->addColumn('expired_date', fn (PurchaseItem $row) => $row->expired_date
                ? $row->expired_date->format('d/m/Y')
                : '-')
            ->addColumn('penerima', fn (PurchaseItem $row) => $row->purchase && $row->purchase->creator
                ? e($row->purchase->creator->name)
                : '-')
            ->addColumn('status', function (PurchaseItem $row) {
                if (! $row->purchase) {
                    return '-';
                }
                if ($row->purchase->isReceived()) {
                    return '<span class="badge badge-success">Diterima</span>';
                }
                if ($row->purchase->isCancelled()) {
                    return '<span class="badge badge-danger">Dibatalkan</span>';
                }

                return '<span class="badge badge-warning">Draft</span>';
            })
            ->rawColumns(['code', 'status'])
            ->toJson();
    }

    public function summary(Request $request)
    {
        $rows = $this->baseQuery($request)->get();
        $received = $rows->filter(fn ($r) => $r->purchase && $r->purchase->isReceived());

        return response()->json([
            'total_pembelian' => $rows->pluck('purchase_id')->unique()->count(),
            'pembelian_diterima' => $received->pluck('purchase_id')->unique()->count(),
            'total_item' => $rows->count(),
            'total_nilai_formatted' => 'Rp ' . FormatMoney($received->sum('subtotal')),
        ]);
    }

    public function export(Request $request)
    {
        $data = $this->reportData($request);
        $filename = 'laporan-barang-masuk-' . Carbon::now()->format('Ymd-His') . '.xls';

        return response()
            ->view('pages.laporan.barang_masuk.export', $data, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
    }

    public function print(Request $request)
    {
        return view('pages.laporan.barang_masuk.print', $this->reportData($request));
    }

    protected function reportData(Request $request): array
    {
        $rows = $this->baseQuery($request)->get();
        $received = $rows->filter(fn ($r) => $r->purchase && $r->purchase->isReceived());

        $periode = 'Semua Periode';
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $periode = $request->input('date_from', '...') . ' s/d ' . $request->input('date_to', '...');
        }

        return [
            'rows' => $rows,
            'totalNilai' => (float) $received->sum('subtotal'),
            'totalPembelian' => $rows->pluck('purchase_id')->unique()->count(),
            'pembelianDiterima' => $received->pluck('purchase_id')->unique()->count(),
            'totalItem' => $rows->count(),
            'periode' => $periode,
            'generatedAt' => Carbon::now()->format('d F Y H:i'),
        ];
    }
}
