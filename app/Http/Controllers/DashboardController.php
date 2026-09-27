<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\Menu;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Dashboard tanpa tombol: seluruh isinya grafik & angka ringkas.
     *
     * Rentang waktu tiap grafik dibuat konstanta supaya judul di view
     * selalu jujur soal periodenya (mis. "14 Hari Terakhir").
     */
    private const TREN_HARI = 14;

    private const RINGKAS_HARI = 30;

    private const TREN_BULAN = 6;

    /** Jam buka -> jam tutup untuk grafik "jam tersibuk". */
    private const JAM_BUKA = 8;

    private const JAM_TUTUP = 21;

    public function index()
    {
        $today = Carbon::today();
        $startMonth = Carbon::now()->startOfMonth()->toDateString();
        $endMonth = Carbon::now()->endOfMonth()->toDateString();
        $startRingkas = $today->copy()->subDays(self::RINGKAS_HARI - 1)->toDateString();

        // --- Angka utama hari ini ---
        $salesToday = $this->salesSelesai()->whereDate('sale_date', $today);
        $omzetToday = (float) $salesToday->clone()->sum('total');
        $trxToday = (int) $salesToday->clone()->count();

        // Kemarin sebagai pembanding
        $salesKemarin = $this->salesSelesai()->whereDate('sale_date', $today->copy()->subDay());
        $omzetKemarin = (float) $salesKemarin->clone()->sum('total');
        $trxKemarin = (int) $salesKemarin->clone()->count();
        $growth = $this->persenChange($omzetKemarin, $omzetToday);
        $growthTrx = $this->persenChange($trxKemarin, $trxToday);

        // --- Bulan berjalan ---
        // Catatan: pakai whereDate (bukan whereBetween) karena sale_date di
        // SQLite tersimpan sebagai "Y-m-d H:i:s", sehingga perbandingan string
        // dengan tanggal saja bisa menghilangkan data di hari terakhir.
        $omzetMonth = (float) $this->salesSelesai()
            ->whereDate('sale_date', '>=', $startMonth)
            ->whereDate('sale_date', '<=', $endMonth)->sum('total');
        $trxMonth = (int) $this->salesSelesai()
            ->whereDate('sale_date', '>=', $startMonth)
            ->whereDate('sale_date', '<=', $endMonth)->count();

        // Pembelian bulan ini + draft yang masih menunggu
        $purchaseMonth = (float) Purchase::where('status', Purchase::STATUS_RECEIVED)
            ->whereDate('purchase_date', '>=', $startMonth)
            ->whereDate('purchase_date', '<=', $endMonth)->sum('total');
        $purchaseDraft = Purchase::where('status', Purchase::STATUS_DRAFT)->count();

        // --- Stok: aman, menipis, habis, dan nilai persediaan ---
        $bahanAktif = BahanBaku::where('status', '!=', -1)->get();
        $stokHabis = $bahanAktif->filter(fn ($b) => (float) $b->stock <= 0);
        $stokMenipis = $bahanAktif->filter(fn ($b) => (float) $b->stock > 0 && (float) $b->stock <= (float) $b->min_stock);
        $stokAman = $bahanAktif->count() - $stokHabis->count() - $stokMenipis->count();
        $totalBahan = $bahanAktif->count();
        $nilaiStok = $bahanAktif->sum(fn ($b) => (float) $b->stock * (float) $b->price);

        $batchSegeraEd = PurchaseItem::with('bahanBaku')
            ->where('remaining_qty', '>', 0)
            ->whereNotNull('expired_date')
            ->whereDate('expired_date', '<=', $today->copy()->addDays(30))
            ->orderBy('expired_date')->limit(6)->get();
        $jumlahBatchSegeraEd = PurchaseItem::where('remaining_qty', '>', 0)
            ->whereNotNull('expired_date')
            ->whereDate('expired_date', '<=', $today->copy()->addDays(30))
            ->count();

        // Jumlah hal yang perlu ditangani, dipakai view untuk menampilkan
        // pesan "semua aman" tanpa menulis ulang 4 kondisi di blade.
        $totalPerhatian = $stokHabis->count() + $stokMenipis->count() + $jumlahBatchSegeraEd + $purchaseDraft;

        // Total menu aktif
        $menuAktif = Menu::where('status', 1)->count();

        // --- Tren harian: omzet, transaksi, belanja ---
        $hari = collect(range(self::TREN_HARI - 1, 0))->map(fn ($i) => $today->copy()->subDays($i));
        $tanggalAwal = $hari->first()->toDateString();

        $trenSale = $this->salesSelesai()
            ->whereDate('sale_date', '>=', $tanggalAwal)
            ->selectRaw('sale_date, COALESCE(SUM(total), 0) as omzet, COUNT(*) as trx')
            ->groupBy('sale_date')->get()
            ->keyBy(fn ($row) => Carbon::parse($row->sale_date)->toDateString());

        $trenBeli = Purchase::where('status', Purchase::STATUS_RECEIVED)
            ->whereDate('purchase_date', '>=', $tanggalAwal)
            ->selectRaw('purchase_date, COALESCE(SUM(total), 0) as belanja')
            ->groupBy('purchase_date')->get()
            ->keyBy(fn ($row) => Carbon::parse($row->purchase_date)->toDateString());

        $trenLabels = $hari->map(fn ($d) => $d->format('d M'))->values();
        $trenOmzet = $hari->map(fn ($d) => (float) ($trenSale[$d->toDateString()]->omzet ?? 0))->values();
        $trenTrx = $hari->map(fn ($d) => (int) ($trenSale[$d->toDateString()]->trx ?? 0))->values();
        $trenBelanja = $hari->map(fn ($d) => (float) ($trenBeli[$d->toDateString()]->belanja ?? 0))->values();

        // --- Jam tersibuk hari ini (jam saat transaksi dibuat) ---
        $perJam = $this->salesSelesai()->whereDate('sale_date', $today)
            ->get(['total', 'created_at'])
            ->groupBy(fn (Sale $s) => $s->created_at?->format('H'))
            ->map(fn ($group) => ['trx' => $group->count(), 'omzet' => (float) $group->sum('total')]);

        $jamLabels = $jamOmzet = $jamTrx = collect();
        for ($jam = self::JAM_BUKA; $jam <= self::JAM_TUTUP; $jam++) {
            $key = str_pad((string) $jam, 2, '0', STR_PAD_LEFT);
            $jamLabels->push($key);
            $jamOmzet->push((float) ($perJam[$key]['omzet'] ?? 0));
            $jamTrx->push((int) ($perJam[$key]['trx'] ?? 0));
        }

        // --- Metode pembayaran (30 hari terakhir) ---
        $bayar = $this->salesSelesai()->whereDate('sale_date', '>=', $startRingkas)
            ->selectRaw('payment_method, COALESCE(SUM(total), 0) as omzet')
            ->groupBy('payment_method')->orderByDesc('omzet')
            ->pluck('omzet', 'payment_method');
        $bayarLabels = $bayar->keys()->map(fn ($k) => $this->labelBayar((string) $k))->values();
        $bayarNilai = $bayar->values()->map(fn ($v) => (float) $v)->values();

        // --- Menu terlaris (30 hari terakhir) ---
        $terlaris = $this->detailPenjualan($startRingkas, $today->toDateString())
            ->selectRaw('sale_items.menu_id, SUM(sale_items.quantity) as qty, SUM(sale_items.subtotal) as omzet')
            ->with('menu')
            ->groupBy('sale_items.menu_id')->orderByDesc('qty')->limit(7)->get();
        $terlarisLabels = $terlaris->map(fn ($t) => $t->menu?->name ?? 'Menu dihapus')->values();
        $terlarisQty = $terlaris->map(fn ($t) => (float) $t->qty)->values();
        $terlarisOmzet = $terlaris->map(fn ($t) => (float) $t->omzet)->values();

        // --- Penjualan per kategori (30 hari terakhir) ---
        $kategori = $this->detailPenjualan($startRingkas, $today->toDateString())
            ->leftJoin('menus', 'menus.id', '=', 'sale_items.menu_id')
            ->leftJoin('categories', 'categories.id', '=', 'menus.category_id')
            ->selectRaw("COALESCE(categories.name, 'Tanpa Kategori') as kategori, SUM(sale_items.subtotal) as omzet")
            ->groupBy('categories.id', 'categories.name')->orderByDesc('omzet')->limit(6)->get();
        $kategoriLabels = $kategori->pluck('kategori')->values();
        $kategoriOmzet = $kategori->map(fn ($k) => (float) $k->omzet)->values();

        // --- Tren bulanan: omzet, belanja, dan selisihnya (6 bulan) ---
        $bulan = collect(range(self::TREN_BULAN - 1, 0))->map(fn ($i) => Carbon::now()->subMonths($i));
        $awalBulan = $bulan->first()->startOfMonth()->toDateString();

        $omzetBulan = $this->salesSelesai()->whereDate('sale_date', '>=', $awalBulan)
            ->get(['sale_date', 'total'])
            ->groupBy(fn (Sale $s) => $s->sale_date->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('total'));
        $belanjaBulan = Purchase::where('status', Purchase::STATUS_RECEIVED)
            ->whereDate('purchase_date', '>=', $awalBulan)
            ->get(['purchase_date', 'total'])
            ->groupBy(fn (Purchase $p) => $p->purchase_date->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('total'));

        $bulanLabels = $bulan->map(fn ($b) => $b->format('M Y'))->values();
        $omzetBulanUmum = $bulan->map(fn ($b) => (float) ($omzetBulan[$b->format('Y-m')] ?? 0))->values();
        $belanjaBulanUmum = $bulan->map(fn ($b) => (float) ($belanjaBulan[$b->format('Y-m')] ?? 0))->values();
        $selisihBulan = $omzetBulanUmum->zip($belanjaBulanUmum)
            ->map(fn ($pasangan) => round($pasangan[0] - $pasangan[1], 2))->values();

        return view('pages.dashboard', compact(
            'omzetToday', 'trxToday', 'growth', 'growthTrx',
            'omzetMonth', 'trxMonth', 'purchaseMonth', 'purchaseDraft', 'nilaiStok',
            'stokHabis', 'stokMenipis', 'stokAman', 'totalBahan', 'totalPerhatian',
            'batchSegeraEd', 'jumlahBatchSegeraEd', 'menuAktif',
            'trenLabels', 'trenOmzet', 'trenTrx', 'trenBelanja',
            'jamLabels', 'jamOmzet', 'jamTrx',
            'bayarLabels', 'bayarNilai',
            'terlarisLabels', 'terlarisQty', 'terlarisOmzet',
            'kategoriLabels', 'kategoriOmzet',
            'bulanLabels', 'omzetBulanUmum', 'belanjaBulanUmum', 'selisihBulan',
            'today'
        ));
    }

    /**
     * Base query penjualan yang sudah selesai (belum dibatalkan).
     */
    private function salesSelesai(): Builder
    {
        return Sale::where('status', Sale::STATUS_COMPLETED);
    }

    /**
     * Base query detail penjualan selesai pada rentang tanggal tertentu.
     * Dipakai untuk menu terlaris dan penjualan per kategori.
     */
    private function detailPenjualan(string $dari, string $sampai): Builder
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereDate('sales.sale_date', '>=', $dari)
            ->whereDate('sales.sale_date', '<=', $sampai);
    }

    /**
     * Persentase perubahan antara dua nilai. Basis 0 diartikan 100% naik
     * kalau ada nilai sekarang, atau 0% kalau semuanya nol.
     */
    private function persenChange(float $sebelum, float $sekarang): float
    {
        if ($sebelum > 0) {
            return round(($sekarang - $sebelum) / $sebelum * 100, 1);
        }

        return $sekarang > 0 ? 100.0 : 0.0;
    }

    /**
     * Nama metode pembayaran yang lebih enak dibaca di grafik.
     */
    private function labelBayar(string $metode): string
    {
        return match ($metode) {
            'cash' => 'Cash',
            'qris' => 'QRIS',
            'transfer' => 'Transfer',
            'debit' => 'Kartu Debit',
            'credit' => 'Kartu Kredit',
            'ewallet' => 'E-Wallet',
            default => ucfirst($metode),
        };
    }
}
