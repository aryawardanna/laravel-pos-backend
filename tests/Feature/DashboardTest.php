<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Satuan;
use App\Models\User;
use Database\Seeders\MenuAccessSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dashboard home: halaman baca-saja yang isinya grafik.
 *
 * Dua hal dijaga di sini:
 * 1. tidak ada satu pun tombol / tautan di body dashboard;
 * 2. angka & data grafik sesuai penjualan yang benar-benar tercatat.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Role + permission dibuat supaya pengujian hak akses dashboard punya makna.
        $this->seed(MenuAccessSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** Sales + item + kategori supaya semua grafik punya isi. */
    private function isiSemuaGrafik(): void
    {
        $satuan = Satuan::create(['name' => 'Liter', 'code' => 'Ltr', 'status' => 1]);
        $kategori = Category::create(['name' => 'Minuman', 'status' => 1]);
        $menu = Menu::create([
            'name' => 'Es Jeruk',
            'code' => 'ESJ',
            'category_id' => $kategori->id,
            'price' => 10000,
            'status' => 1,
        ]);

        BahanBaku::create(['name' => 'Gula', 'code' => 'GULA', 'satuan_id' => $satuan->id, 'price' => 15000, 'stock' => 20, 'min_stock' => 5, 'status' => 1]);

        $this->buatPenjualan(Carbon::today(), 40000, 'qris', $menu->id);
    }

    private function buatPenjualan(Carbon $tanggal, float $total, string $bayar = 'cash', ?int $menuId = null): Sale
    {
        $sale = Sale::create([
            'code' => 'TRX-'.$tanggal->format('Ymd').'-'.(Sale::count() + 1),
            'sale_date' => $tanggal->toDateString(),
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'paid' => $total,
            'change_amount' => 0,
            'payment_method' => $bayar,
            'status' => Sale::STATUS_COMPLETED,
            'created_by' => User::first()?->id,
        ]);

        if ($menuId !== null) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'menu_id' => $menuId,
                'quantity' => 2,
                'unit_price' => $total / 2,
                'subtotal' => $total,
            ]);
        }

        return $sale;
    }

    public function test_dashboard_bisa_diakses_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_dashboard_tidak_ada_tombol_atau_tautan(): void
    {
        $body = $this->ambilBody($this->isiHalaman());

        $this->assertStringNotContainsString('<a ', $body, 'Dashboard tidak boleh memuat tautan.');
        $this->assertStringNotContainsString('<button', $body, 'Dashboard tidak boleh memuat tombol.');
        $this->assertStringNotContainsString('quick-link', $body);
        $this->assertStringNotContainsString('class="btn', $body);

        foreach (['Kasir Baru', 'Beli Bahan', 'Aksi Cepat', 'Lihat Semua', 'Cek Stok', 'Cek Batch'] as $label) {
            $this->assertStringNotContainsString($label, $body, "Tombol lama masih ada: {$label}");
        }
    }

    public function test_semua_grafik_dirender(): void
    {
        $this->isiSemuaGrafik();
        $html = $this->isiHalaman();

        foreach (['chart-tren', 'chart-bayar', 'chart-terlaris', 'chart-kategori', 'chart-stok', 'chart-bulan', 'chart-jam'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, "Grafik #{$id} tidak ada.");
        }
    }

    public function test_tinggi_semua_grafik_seragam(): void
    {
        $this->isiSemuaGrafik();
        $html = $this->isiHalaman();

        // Hanya boleh ada satu definisi tinggi canvas (tidak ada varian kecil).
        $this->assertStringNotContainsString('dash-chart-sm', $html);

        preg_match('/\.dash-chart\s*\{[^}]*height:\s*(\d+)px/', $html, $m);
        $this->assertNotEmpty($m, 'Aturan .dash-chart dengan height px tidak ditemukan.');

        // Semua container canvas memakai kelas yang sama itu.
        preg_match_all('/class="(dash-chart[^"]*)"[^>]*>\s*<canvas/', $html, $c);
        $this->assertNotEmpty($c[1]);
        foreach ($c[1] as $kelas) {
            $this->assertSame('dash-chart', $kelas, "Kelas chart tidak seragam: {$kelas}");
        }

        // Baris kartu grafik memakai .dash-row supaya tinggi kartunya sama.
        preg_match_all('/<div class="row dash-row">/', $html, $r);
        $this->assertCount(3, $r[0], 'Baris kartu grafik harus memakai kelas dash-row.');
    }

    public function test_layout_dashboard_rapi_untuk_hp(): void
    {
        $this->isiSemuaGrafik();
        $html = $this->isiHalaman();

        // Pembungkus halaman harus memakai kelas dash-page (pusat aturan mobile).
        $this->assertStringContainsString('<section class="section dash-page">', $html);

        // Ada judul halaman sendiri, bukan hanya andalkan breadcrumb sidebar.
        $this->assertStringContainsString('dash-page-title', $html);

        // Harus ada aturan khusus layar kecil.
        $this->assertStringContainsString('@media (max-width: 767.98px)', $html);
        $this->assertStringContainsString('@media (max-width: 1023.98px)', $html);

        // Tinggi canvas diturunkan di layar kecil, tapi tetap seragam:
        // hanya boleh ada satu aturan .dash-chart di dalam blok media query.
        preg_match('/@media \(max-width: 767\.98px\)(.*?)@media/s', $html, $m);
        $this->assertNotEmpty($m, 'Blok media query 767.98px tidak ditemukan.');
        $this->assertSame(1, substr_count($m[1], '.dash-chart {'), 'Aturan .dash-chart di media query harus tetap satu.');

        // Label sumbu X dibatasi saat layar sempit, dan dihitung ulang saat resize.
        $this->assertStringContainsString('maxTicksLimit', $html);
        $this->assertStringContainsString('sumbuXAdaptif(14)', $html);
        $this->assertStringContainsString("on('resize'", $html);

        // Semua kartu grafik tetap satu tinggi.
        $this->assertStringNotContainsString('dash-chart-sm', $html);
    }

    public function test_blade_dashboard_nesting_div_seimbang(): void
    {
        // Dicek dari file blade (bukan hasil render) karena output HTML sudah
        // dipengaruhi tag liar milik template Stisla.
        $src = file_get_contents(resource_path('views/pages/dashboard.blade.php'));

        $mulai = strpos($src, "@section('main')");
        $selesai = strpos($src, '@endsection', $mulai);
        $this->assertNotFalse($mulai, "Blok @section('main') tidak ditemukan.");
        $this->assertNotFalse($selesai, 'Blok @endsection tidak ditemukan.');

        // main-content dibuka di dalam section ini tapi ditutup setelahnya,
        // jadi dalam potongan ini memang kurang satu </div>.
        $body = substr($src, $mulai, $selesai - $mulai);
        $buka = preg_match_all('/<div\b[^>]*>/i', $body);
        $tutup = preg_match_all('/<\/div>/i', $body);

        $this->assertSame(
            $buka - 1,
            $tutup,
            "Div tidak seimbang di dalam section: {$buka} <div> vs {$tutup} </div>."
        );

        // Bagian luar section harus seimbang penuh, tanpa div menggantung.
        $luar = substr($src, 0, $mulai).substr($src, $selesai);
        $bukaLuar = preg_match_all('/<div\b[^>]*>/i', $luar);
        $tutupLuar = preg_match_all('/<\/div>/i', $luar);

        $this->assertSame($bukaLuar, $tutupLuar, "Ada <div> menggantung di luar section: {$bukaLuar} vs {$tutupLuar}.");
    }

    public function test_baris_kartu_grafik_tidak_saling_bersarang(): void
    {
        $this->isiSemuaGrafik();

        $doc = new DOMDocument;
        @$doc->loadHTML($this->isiHalaman());
        $xpath = new DOMXPath($doc);

        $baris = $xpath->query(
            '//section[contains(@class,"dash-page")]'
            .'//div[contains(concat(" ", normalize-space(@class), " "), " dash-row ")]'
        );

        $this->assertCount(3, $baris);

        foreach ($baris as $no => $satuBaris) {
            $bersarang = $xpath->query('./div[contains(@class,"dash-row")]', $satuBaris);
            $kolom = $xpath->query('./div[contains(@class,"col-")]', $satuBaris);

            $this->assertSame(0, $bersarang->length, 'Baris '.($no + 1).' punya baris lain di dalamnya.');
            $this->assertGreaterThanOrEqual(2, $kolom->length, 'Baris kartu harus punya minimal 2 kolom.');
        }
    }

    public function test_angka_hero_sesuai_data(): void
    {
        $today = Carbon::today();
        Carbon::setTestNow($today->copy()->setTime(10, 0));

        $this->buatPenjualan($today, 150000, 'cash');
        $this->buatPenjualan($today, 50000, 'qris');
        $this->buatPenjualan($today->copy()->subDay(), 200000, 'cash');

        $html = $this->isiHalaman();

        // Rp 200.000 dari 2 transaksi hari ini
        $this->assertStringContainsString('200.000', $html);
        $this->assertStringContainsString('Omzet Bulan Ini', $html);
        $this->assertStringContainsString('vs kemarin', $html);

        Carbon::setTestNow();
    }

    public function test_grafik_menampilkan_data_penjualan(): void
    {
        $this->isiSemuaGrafik();

        $html = $this->isiHalaman();

        $this->assertStringContainsString('Es Jeruk', $html, 'Label menu terlaris tidak muncul.');
        $this->assertStringContainsString('Minuman', $html, 'Label kategori tidak muncul.');
        $this->assertStringContainsString('QRIS', $html, 'Label metode pembayaran tidak muncul.');
    }

    public function test_status_persediaan_menghitung_aman_menipis_habis(): void
    {
        $satuan = Satuan::create(['name' => 'Liter', 'code' => 'Ltr', 'status' => 1]);

        BahanBaku::create(['name' => 'Gula', 'code' => 'GULA', 'satuan_id' => $satuan->id, 'price' => 15000, 'stock' => 20, 'min_stock' => 5, 'status' => 1]);
        BahanBaku::create(['name' => 'Susu', 'code' => 'SUSU', 'satuan_id' => $satuan->id, 'price' => 30000, 'stock' => 2, 'min_stock' => 5, 'status' => 1]);
        BahanBaku::create(['name' => 'Teh', 'code' => 'TEH', 'satuan_id' => $satuan->id, 'price' => 10000, 'stock' => 0, 'min_stock' => 5, 'status' => 1]);

        // 1 aman, 1 menipis, 1 habis -> data grafik persediaan
        $this->assertMatchesRegularExpression(
            '/data:\s*\[\s*1\s*,\s*1\s*,\s*1\s*\]/',
            $this->isiHalaman()
        );
    }

    public function test_halaman_aman_saat_belum_ada_data_apa_pun(): void
    {
        $html = $this->isiHalaman();

        $this->assertStringContainsString('Belum ada transaksi', $html);
        $this->assertStringContainsString('Semua aman', $html);
    }

    public function test_dashboard_terlindungi_izin_dashboard_view(): void
    {
        Role::findByName('user')->syncPermissions([]);

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('home'))
            ->assertForbidden();
    }

    public function test_grafik_tren_punya_type_top_level(): void
    {
        $this->isiSemuaGrafik();
        $html = $this->isiHalaman();

        // Chart.js 2.x membaca type top-level konfigurasi. Tanpanya grafik
        // tren tidak tergambar saat halaman dimuat (kosong sampai di-klik).
        $this->assertMatchesRegularExpression(
            "/var\\s+chartTren\\s*=\\s*new\\s+Chart\\s*\\(\\s*tren,\\s*\\{[^}]*type:\\s*'(bar|line)'/s",
            $html,
            'Konfigurasi chart-tren harus menyertakan type top-level agar tergambar saat halaman dimuat.'
        );

        // Dataset harus memplot data tren sesuai judul & keterangan kartu:
        // garis Omzet dan batang Belanja bahan (bukan Transaksi satuan).
        $this->assertStringContainsString('Belanja bahan', $html);
        $this->assertDoesNotMatchRegularExpression(
            "/new\\s+Chart\\s*\\(\\s*tren,[\\s\\S]*?label:\\s*'Transaksi'/",
            $html,
            'Dataset Transaksi (satuan) tidak boleh dipetakan ke skala rupiah chart-tren.'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/new\\s+Chart\\s*\\(\\s*tren,[\\s\\S]*?yAxisID:\\s*'y1'/",
            $html,
            'chart-tren harus memakai satu skala rupiah (sumbu y tunggal).'
        );
    }

    public function test_grafik_digambar_setelah_layout_siap(): void
    {
        $this->isiSemuaGrafik();
        $html = $this->isiHalaman();

        // Chart dibuat lewat penunda (rAF) dan di-update lagi saat load,
        // supaya grafik langsung tampil tanpa perlu klik legenda.
        $this->assertStringContainsString('gambarSaatSiap', $html);
        $this->assertStringContainsString('requestAnimationFrame', $html);
        $this->assertStringContainsString("on('load'", $html);
        $this->assertStringContainsString('gambarUlangSemua', $html);
    }

    private function isiHalaman(): string
    {
        return $this->actingAs($this->admin())
            ->get(route('home'))
            ->assertOk()
            ->getContent();
    }

    /**
     * Ambil isi <div class="main-content"> saja (sampai penutup section),
     * supaya tombol yang memang ada di header/sidebar template tidak ikut
     * terhitung.
     */
    private function ambilBody(string $html): string
    {
        $mulai = strpos($html, '<div class="main-content">');
        $selesai = strpos($html, '</section>', $mulai === false ? 0 : $mulai);

        if ($mulai === false || $selesai === false) {
            return '';
        }

        return substr($html, $mulai, $selesai - $mulai);
    }
}
