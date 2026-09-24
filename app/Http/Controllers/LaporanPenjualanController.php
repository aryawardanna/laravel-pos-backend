<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\Facades\DataTables;

class LaporanPenjualanController extends Controller
{
    public function index(Request $request)
    {
        $menus = Menu::orderBy('name')->get(['id', 'name', 'code']);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $defaultFrom = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $defaultTo = $request->input('date_to', Carbon::now()->endOfMonth()->toDateString());

        return view('pages.laporan.penjualan.index', compact('menus', 'categories', 'defaultFrom', 'defaultTo'));
    }

    protected function baseQuery(Request $request)
    {
        $query = SaleItem::query()
            ->select('sale_items.*')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('menus', 'menus.id', '=', 'sale_items.menu_id')
            ->leftJoin('categories', 'categories.id', '=', 'menus.category_id')
            ->with(['sale.creator', 'menu.category'])
            ->orderBy('sales.sale_date', 'desc')
            ->orderBy('sales.id', 'desc')
            ->orderBy('sale_items.id', 'desc');

        if ($request->filled('date_from')) {
            $query->whereDate('sales.sale_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sales.sale_date', '<=', $request->input('date_to'));
        }
        if ($request->filled('menu_id')) {
            $query->where('sale_items.menu_id', $request->input('menu_id'));
        }
        if ($request->filled('category_id')) {
            $query->where('menus.category_id', $request->input('category_id'));
        }
        if ($request->filled('payment_method')) {
            $query->where('sales.payment_method', $request->input('payment_method'));
        }
        if ($request->filled('status')) {
            $query->where('sales.status', $request->input('status'));
        }
        if ($request->filled('code')) {
            $query->where('sales.code', 'like', '%' . $request->input('code') . '%');
        }
        if ($request->filled('search_menu')) {
            $query->where('menus.name', 'like', '%' . $request->input('search_menu') . '%');
        }

        return $query;
    }

    public function data(Request $request)
    {
        $query = $this->baseQuery($request);

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('sale_date', fn (SaleItem $row) => $row->sale && $row->sale->sale_date
                ? $row->sale->sale_date->format('d/m/Y')
                : '-')
            ->addColumn('code', function (SaleItem $row) {
                if (! $row->sale) {
                    return '-';
                }

                return '<a href="' . route('sale.show', $row->sale->id) . '">' . e($row->sale->code) . '</a>';
            })
            ->addColumn('menu_name', fn (SaleItem $row) => $row->menu ? e($row->menu->name) : '-')
            ->addColumn('category_name', fn (SaleItem $row) => $row->menu && $row->menu->category
                ? e($row->menu->category->name)
                : '-')
            ->addColumn('quantity', fn (SaleItem $row) => e(FormatQty($row->quantity)))
            ->addColumn('unit_price', fn (SaleItem $row) => e(FormatMoney($row->unit_price)))
            ->addColumn('subtotal', fn (SaleItem $row) => e(FormatMoney($row->subtotal)))
            ->addColumn('payment_method', fn (SaleItem $row) => $row->sale && $row->sale->payment_method
                ? e(ucfirst($row->sale->payment_method))
                : '-')
            ->addColumn('kasir', fn (SaleItem $row) => $row->sale && $row->sale->creator
                ? e($row->sale->creator->name)
                : '-')
            ->addColumn('status', function (SaleItem $row) {
                if (! $row->sale) {
                    return '-';
                }

                return $row->sale->isCompleted()
                    ? '<span class="badge badge-success">Selesai</span>'
                    : '<span class="badge badge-danger">Dibatalkan</span>';
            })
            ->rawColumns(['code', 'status'])
            ->toJson();
    }

    public function summary(Request $request)
    {
        $rows = $this->baseQuery($request)->get();
        $completed = $rows->filter(fn ($r) => $r->sale && $r->sale->isCompleted());

        return response()->json([
            'total_transaksi' => $rows->pluck('sale_id')->unique()->count(),
            'transaksi_selesai' => $completed->pluck('sale_id')->unique()->count(),
            'total_porsi_formatted' => FormatQty($completed->sum('quantity')),
            'omzet_formatted' => 'Rp ' . FormatMoney($completed->sum('subtotal')),
        ]);
    }

    public function export(Request $request)
    {
        $data = $this->reportData($request);
        $filename = 'laporan-penjualan-' . Carbon::now()->format('Ymd-His') . '.xls';

        return response()
            ->view('pages.laporan.penjualan.export', $data, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
    }

    /**
     * Halaman cetak PDF (Print to PDF via browser).
     *
     * Sengaja memakai window.print() agar tanpa dependency tambahan:
     * user pilih "Save as PDF" di dialog print browser. Layout A4
     * landscape sudah dioptimasi untuk cetak.
     */
    public function print(Request $request)
    {
        return view('pages.laporan.penjualan.print', $this->reportData($request));
    }

    /**
     * Download PDF via DomPDF (barryvdh/laravel-dompdf).
     * Membutuhkan: composer install agar vendor tersedia.
     */
    public function pdf(Request $request)
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return redirect()->route('laporan.penjualan.print', $request->query())
                ->with('error', 'Library PDF (barryvdh/laravel-dompdf) belum terinstal. Jalankan: composer install. Halaman cetak dibuka sebagai gantinya.');
        }

        $data = $this->reportData($request);
        $filename = 'laporan-penjualan-' . Carbon::now()->format('Ymd-His') . '.pdf';

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pages.laporan.penjualan.pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    protected function reportData(Request $request): array
    {
        $rows = $this->baseQuery($request)->get();
        $completed = $rows->filter(fn ($r) => $r->sale && $r->sale->isCompleted());

        $periode = 'Semua Periode';
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $periode = $request->input('date_from', '...') . ' s/d ' . $request->input('date_to', '...');
        }

        return [
            'rows' => $rows,
            'totalQty' => (float) $completed->sum('quantity'),
            'totalOmzet' => (float) $completed->sum('subtotal'),
            'totalTransaksi' => $rows->pluck('sale_id')->unique()->count(),
            'transaksiSelesai' => $completed->pluck('sale_id')->unique()->count(),
            'periode' => $periode,
            'generatedAt' => Carbon::now()->format('d F Y H:i'),
            'filters' => $request->only(['date_from', 'date_to', 'menu_id', 'category_id', 'payment_method', 'status', 'code', 'search_menu']),
        ];
    }
}
