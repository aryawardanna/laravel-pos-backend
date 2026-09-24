<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\Menu;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $startMonth = Carbon::now()->startOfMonth()->toDateString();
        $endMonth = Carbon::now()->endOfMonth()->toDateString();

        // --- Angka utama hari ini ---
        $salesToday = Sale::where('status', Sale::STATUS_COMPLETED)
            ->whereDate('sale_date', $today)->get();
        $omzetToday = (float) $salesToday->sum('total');
        $trxToday = $salesToday->count();

        $salesMonth = Sale::where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [$startMonth, $endMonth])->get();
        $omzetMonth = (float) $salesMonth->sum('total');
        $trxMonth = $salesMonth->count();

        // Kemarin untuk perbandingan
        $omzetYesterday = (float) Sale::where('status', Sale::STATUS_COMPLETED)
            ->whereDate('sale_date', $today->copy()->subDay())->sum('total');
        $growth = $omzetYesterday > 0
            ? round(($omzetToday - $omzetYesterday) / $omzetYesterday * 100, 1)
            : ($omzetToday > 0 ? 100 : 0);

        // Pembelian bulan ini + draft menunggu
        $purchaseMonth = (float) Purchase::where('status', Purchase::STATUS_RECEIVED)
            ->whereBetween('purchase_date', [$startMonth, $endMonth])->sum('total');
        $purchaseDraft = Purchase::where('status', Purchase::STATUS_DRAFT)->count();

        // Stok: nilai, habis, menipis, batch segera ED
        $bahanAktif = BahanBaku::where('status', '!=', -1)->get();
        $nilaiStok = $bahanAktif->sum(fn ($b) => (float) $b->stock * (float) $b->price);
        $stokHabis = $bahanAktif->filter(fn ($b) => (float) $b->stock <= 0);
        $stokMenipis = $bahanAktif->filter(fn ($b) => (float) $b->stock > 0 && (float) $b->stock <= (float) $b->min_stock);
        $batchSegeraEd = PurchaseItem::with('bahanBaku')
            ->where('remaining_qty', '>', 0)
            ->whereNotNull('expired_date')
            ->whereDate('expired_date', '<=', $today->copy()->addDays(30))
            ->orderBy('expired_date')->limit(6)->get();

        // Total menu aktif
        $menuAktif = Menu::where('status', 1)->count();

        // --- Grafik omzet 14 hari terakhir ---
        $days = collect(range(13, 0))->map(fn ($i) => $today->copy()->subDays($i));
        $omzetMap = Sale::where('status', Sale::STATUS_COMPLETED)
            ->whereDate('sale_date', '>=', $days->first()->toDateString())
            ->selectRaw('sale_date, SUM(total) as omzet, COUNT(*) as trx')
            ->groupBy('sale_date')->pluck('omzet', 'sale_date');
        $trxMap = Sale::where('status', Sale::STATUS_COMPLETED)
            ->whereDate('sale_date', '>=', $days->first()->toDateString())
            ->selectRaw('sale_date, COUNT(*) as trx')
            ->groupBy('sale_date')->pluck('trx', 'sale_date');
        $chartLabels = $days->map(fn ($d) => $d->format('d M'))->values();
        $chartOmzet = $days->map(fn ($d) => (float) ($omzetMap[$d->toDateString()] ?? 0))->values();
        $chartTrx = $days->map(fn ($d) => (int) ($trxMap[$d->toDateString()] ?? 0))->values();

        // --- Menu terlaris bulan ini ---
        $topMenus = SaleItem::selectRaw('menu_id, SUM(quantity) as qty, SUM(subtotal) as omzet')
            ->whereHas('sale', fn ($q) => $q->where('status', Sale::STATUS_COMPLETED)
                ->whereBetween('sale_date', [$startMonth, $endMonth]))
            ->with('menu.category')
            ->groupBy('menu_id')->orderByDesc('qty')->limit(5)->get();
        $maxQty = (float) ($topMenus->max('qty') ?? 1);

        // --- Transaksi terakhir hari ini ---
        $recentSales = Sale::with(['creator', 'items.menu'])
            ->orderBy('id', 'desc')->limit(6)->get();

        // --- Pembayaran hari ini (donat) ---
        $payMap = Sale::where('status', Sale::STATUS_COMPLETED)
            ->whereDate('sale_date', $today)
            ->selectRaw('payment_method, SUM(total) as omzet')
            ->groupBy('payment_method')->pluck('omzet', 'payment_method');

        return view('pages.dashboard', compact(
            'omzetToday', 'trxToday', 'omzetMonth', 'trxMonth', 'growth',
            'purchaseMonth', 'purchaseDraft', 'nilaiStok',
            'stokHabis', 'stokMenipis', 'batchSegeraEd', 'menuAktif',
            'chartLabels', 'chartOmzet', 'chartTrx',
            'topMenus', 'maxQty', 'recentSales', 'payMap', 'today'
        ));
    }
}
