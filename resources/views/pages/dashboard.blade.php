@extends('layouts.app')
@section('title', 'Dashboard')
@push('style')
<style>
/* =====================================================================
   Dashboard — halaman baca-saja: tanpa tombol, tanpa tautan.
   Isinya grafik + keterangan singkat supaya langsung bisa dipahami.
   ===================================================================== */

/* --- Hero: sapaan + 4 angka ringkas --------------------------------- */
.dash-hero {
    background: linear-gradient(135deg, #6777ef 0%, #3b4fd8 55%, #2b3a9e 100%);
    border: none; color: #fff; overflow: hidden; position: relative;
}
.dash-hero .card-body { position: relative; z-index: 2; }
.dash-hero::after {
    content: '\f54e'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
    position: absolute; right: -10px; bottom: -40px; font-size: 210px;
    opacity: .12; z-index: 1;
}
.dash-hero h2 { color: #fff; font-weight: 700; }
.dash-hero p { color: rgba(255, 255, 255, .85); }
.dash-hero-emoji { font-size: 64px; }
.dash-hero-tagline { color: rgba(255, 255, 255, .85); }
.dash-hero .dash-kpi { border-left: 3px solid rgba(255, 255, 255, .35); padding-left: 12px; }
.dash-hero .dash-kpi h6 {
    color: rgba(255, 255, 255, .75); font-weight: 600; text-transform: uppercase;
    font-size: 11px; letter-spacing: .4px; margin-bottom: 2px;
}
.dash-hero .dash-kpi .val { color: #fff; font-weight: 700; font-size: 20px; line-height: 1.2; }
.dash-hero .dash-kpi .sub { color: rgba(255, 255, 255, .7); font-size: 12px; }

/* --- Judul halaman -------------------------------------------------- */
.dash-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.dash-page-sub { font-size: 13px; color: #99a4c2; margin: 2px 0 0; }

/* --- Kartu grafik: satu baris = satu tinggi ------------------------ */
.dash-row > [class*="col-"] { display: flex; }
.dash-row .dash-card { width: 100%; }
.dash-card { height: 100%; display: flex; flex-direction: column; }
.dash-card .card-header { border-bottom: 0; padding-bottom: 2px; }
.dash-card .card-title { font-weight: 700; font-size: 15px; margin: 0; }
.dash-card .dash-hint { font-size: 12px; color: #99a4c2; margin: 2px 0 0; }
.dash-card .card-body {
    padding-top: 10px; flex: 1 1 auto;
    display: flex; flex-direction: column; justify-content: center;
}

/* --- Area grafik: tinggi seragam untuk semua chart ----------------- */
.dash-chart { position: relative; width: 100%; height: 300px; }
.dash-empty {
    display: flex; align-items: center; justify-content: center; height: 100%;
    color: #a3aed0; font-size: 13px; text-align: center;
}

/* --- Legenda mendatar (tidak menambah tinggi kartu) ---------------- */
.dash-legend { display: flex; flex-wrap: wrap; list-style: none; padding: 0; margin: 10px 0 0; }
.dash-legend li { display: flex; align-items: center; font-size: 12px; color: #666; margin-right: 16px; }
.dash-legend .dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: 6px; }
.dash-legend .dot--aman { background: #47c363; }
.dash-legend .dot--menipis { background: #ffa426; }
.dash-legend .dot--habis { background: #fc544b; }
.dash-legend .val { font-weight: 700; color: #34395e; margin-left: 4px; }

/* --- Kartu "Perlu Perhatian" --------------------------------------- */
.dash-note {
    border-radius: 12px; padding: 10px 14px; font-size: 13px; margin-bottom: 8px;
    display: flex; align-items: center;
}
.dash-note i { margin-right: 10px; font-size: 16px; }
.dash-note.alert-danger { background: #fdeaea; border: 0; color: #c0392b; }
.dash-note.alert-warning { background: #fff6e0; border: 0; color: #b8791b; }
.dash-note.alert-info { background: #e8f4fe; border: 0; color: #2b7cb8; }
.dash-note.alert-success { background: #e9f8ef; border: 0; color: #2e8b57; }
.dash-note-batch { list-style: none; margin-bottom: 8px; padding-left: 32px !important; }
.dash-note-batch li { margin-bottom: 2px; }
.dash-summary { margin-top: 8px; font-size: 13px; color: #99a4c2; }

/* =====================================================================
   Responsif
   Template memakai padding 30px per sisi, sehingga di layar sempit ruang
   chart benar-benar jadi sempit. Padding dikecilkan dan tinggi canvas
   diturunkan — tetap seragam, hanya dikecilkan — lalu angka hero diperkecil
   supaya tidak meluber keluar kartu.
   ===================================================================== */
@media (max-width: 1023.98px) {
    .dash-page .section-body { padding-left: 12px; padding-right: 12px; }
    .dash-page .row { margin-left: -6px; margin-right: -6px; }
    .dash-page .row > [class*="col-"] { padding-left: 6px; padding-right: 6px; }
    .dash-chart { height: 260px; }
}

@media (max-width: 767.98px) {
    .dash-page .section-body { padding-left: 8px; padding-right: 8px; }
    .dash-page-title { font-size: 18px; }
    .dash-chart { height: 240px; }
    .dash-card .card-header { padding: 14px 14px 4px; }
    .dash-card .card-body { padding-left: 14px; padding-right: 14px; }
    .dash-card .card-title { font-size: 14px; }
    .dash-card .dash-hint { font-size: 11.5px; }

    .dash-hero .card-body { padding: 18px 16px; }
    .dash-hero h2 { font-size: 19px; margin-bottom: 4px; }
    .dash-hero p { font-size: 13px; }
    .dash-hero::after { font-size: 130px; bottom: -20px; right: 0; }
    .dash-hero .dash-kpi { padding-left: 8px; }
    .dash-hero .dash-kpi .val { font-size: 15px; letter-spacing: -.2px; overflow-wrap: break-word; }
    .dash-hero .dash-kpi h6 { font-size: 10px; }
    .dash-hero .dash-kpi .sub { font-size: 11px; }

    .dash-note { align-items: flex-start; padding: 10px 12px; }
    .dash-note i { margin-top: 2px; }
    .dash-legend li { margin-right: 12px; }
}

@media (max-width: 359.98px) {
    .dash-chart { height: 220px; }
    .dash-hero .dash-kpi .val { font-size: 13.5px; }
    .dash-legend li { font-size: 11px; margin-right: 8px; }
}
</style>
@endpush
@section('main')
<div class="main-content">
<section class="section dash-page">
<div class="section-body">



{{-- Hero: sapaan + 4 angka utama --}}
<div class="row">
    <div class="col-12">
        <div class="card dash-hero">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-12 col-md-8">
                        <h2>Halo, {{ Auth::user()->name ?? 'Kasir' }}!</h2>
                        <p class="mb-3">Berikut ringkasan usaha Anda, diperbarui otomatis setiap kali halaman ini dibuka.</p>
                        <div class="row">
                            <div class="col-6 col-md-3 mb-2">
                                <div class="dash-kpi">
                                    <h6>Omzet Hari Ini</h6>
                                    <div class="val">Rp {{ FormatMoney($omzetToday, 0) }}</div>
                                    <div class="sub">
                                        @if ($growth > 0)
                                            <span class="badge badge-success mb-0">&#9650; {{ $growth }}%</span> vs kemarin
                                        @elseif ($growth < 0)
                                            <span class="badge badge-danger mb-0">&#9660; {{ abs($growth) }}%</span> vs kemarin
                                        @else
                                            <span class="badge badge-light mb-0">Sama</span> vs kemarin
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="dash-kpi">
                                    <h6>Transaksi Hari Ini</h6>
                                    <div class="val">{{ $trxToday }}</div>
                                    <div class="sub">
                                        @if ($growthTrx != 0)
                                            {{ $growthTrx > 0 ? '&#9650; +' : '&#9660; ' }}{{ $growthTrx }}% vs kemarin
                                        @else
                                            Sama seperti kemarin
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="dash-kpi">
                                    <h6>Omzet Bulan Ini</h6>
                                    <div class="val">Rp {{ FormatMoney($omzetMonth, 0) }}</div>
                                    <div class="sub">{{ $trxMonth }} transaksi</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="dash-kpi">
                                    <h6>Nilai Persediaan</h6>
                                    <div class="val">Rp {{ FormatMoney($nilaiStok, 0) }}</div>
                                    <div class="sub">{{ $totalBahan }} bahan baku aktif</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center d-none d-md-block">
                        <div class="dash-hero-emoji">&#127836;</div>
                        <div class="dash-hero-tagline">Semangat jualan hari ini!</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


{{-- Baris 1: tren harian & metode pembayaran --}}
<div class="row dash-row">
    <div class="col-12 col-lg-8">
        <div class="card dash-card">
            <div class="card-header">
                <h4 class="card-title">Tren Omzet &amp; Belanja Bahan</h4>
                <p class="dash-hint">14 hari terakhir. Garis = omzet penjualan, batang = belanja bahan baku. Kalau batang lebih tinggi dari garis, beban belanja sedang besar.</p>
            </div>
            <div class="card-body">
                @include('components.dashboard.chart-area', [
                    'id' => 'chart-tren',
                    'adaData' => $trenOmzet->sum() > 0 || $trenBelanja->sum() > 0,
                    'pesan' => 'Belum ada transaksi atau pembelian pada 14 hari terakhir.',
                ])
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card dash-card">
            <div class="card-header">
                <h4 class="card-title">Metode Pembayaran</h4>
                <p class="dash-hint">30 hari terakhir. Makin besar satu potongan, makin besar peluang kasir perlu ditambah.</p>
            </div>
            <div class="card-body">
                @include('components.dashboard.chart-area', [
                    'id' => 'chart-bayar',
                    'adaData' => $bayarNilai->sum() > 0,
                    'pesan' => 'Belum ada transaksi pada 30 hari terakhir.',
                ])
            </div>
        </div>
    </div>
</div>

{{-- Baris 2: menu terlaris, kategori, kondisi persediaan --}}
<div class="row dash-row">
    <div class="col-12 col-lg-4">
        <div class="card dash-card">
            <div class="card-header">
                <h4 class="card-title">Menu Terlaris</h4>
                <p class="dash-hint">30 hari terakhir. Panjang batang = jumlah porsi terjual, jadi batang terpanjang adalah menu paling laris.</p>
            </div>
            <div class="card-body">
                @include('components.dashboard.chart-area', [
                    'id' => 'chart-terlaris',
                    'adaData' => $terlarisQty->sum() > 0,
                    'pesan' => 'Belum ada penjualan pada 30 hari terakhir.',
                ])
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card dash-card">
            <div class="card-header">
                <h4 class="card-title">Penjualan per Kategori</h4>
                <p class="dash-hint">30 hari terakhir. Porsi omzet tiap kategori, agar jelas kategori mana yang paling ramai.</p>
            </div>
            <div class="card-body">
                @include('components.dashboard.chart-area', [
                    'id' => 'chart-kategori',
                    'adaData' => $kategoriOmzet->sum() > 0,
                    'pesan' => 'Belum ada penjualan pada 30 hari terakhir.',
                ])
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card dash-card">
            <div class="card-header">
                <h4 class="card-title">Kondisi Persediaan</h4>
                <p class="dash-hint">Jumlah bahan baku per status. Hijau = aman, kuning = tinggal sedikit, merah = habis.</p>
            </div>
            <div class="card-body">
                @include('components.dashboard.chart-area', [
                    'id' => 'chart-stok',
                    'adaData' => $totalBahan > 0,
                    'pesan' => 'Belum ada data bahan baku.',
                ])
                <ul class="dash-legend">
                    <li><span class="dot dot--aman"></span> Aman <span class="val">{{ $stokAman }} bahan</span></li>
                    <li><span class="dot dot--menipis"></span> Menipis <span class="val">{{ $stokMenipis->count() }} bahan</span></li>
                    <li><span class="dot dot--habis"></span> Habis <span class="val">{{ $stokHabis->count() }} bahan</span></li>
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Baris 3: tren bulanan & jam tersibuk --}}
<div class="row dash-row">
    <div class="col-12 col-lg-8">
        <div class="card dash-card">
            <div class="card-header">
                <h4 class="card-title">Tren 6 Bulan: Omzet, Belanja, dan Selisihnya</h4>
                <p class="dash-hint">Batang biru = omzet, batang oranye = belanja bahan. Selisihnya = omzet dikurangi belanja, jadi makin tinggi berarti makin untung.</p>
            </div>
            <div class="card-body">
                @include('components.dashboard.chart-area', [
                    'id' => 'chart-bulan',
                    'adaData' => $omzetBulanUmum->sum() > 0 || $belanjaBulanUmum->sum() > 0,
                    'pesan' => 'Belum ada transaksi atau pembelian pada 6 bulan terakhir.',
                ])
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card dash-card">
            <div class="card-header">
                <h4 class="card-title">Jam Tersibuk Hari Ini</h4>
                <p class="dash-hint">Omzet per jam. Jam paling tinggi biasanya paling ramai &mdash; siapkan kasir lebih banyak di jam itu.</p>
            </div>
            <div class="card-body">
                @include('components.dashboard.chart-area', [
                    'id' => 'chart-jam',
                    'adaData' => $jamOmzet->sum() > 0,
                    'pesan' => 'Belum ada transaksi hari ini.',
                ])
            </div>
        </div>
    </div>
</div>

{{-- Perlu Perhatian: baris info tanpa tombol --}}
<div class="row">
    <div class="col-12">
        <div class="card dash-card">
            <div class="card-header">
                <h4 class="card-title">Perlu Perhatian</h4>
                <p class="dash-hint">Hal-hal yang perlu ditangani hari ini, diurut dari yang paling mendesak.</p>
            </div>
            <div class="card-body">
                @if ($stokHabis->count() > 0)
                    <div class="dash-note alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><strong>{{ $stokHabis->count() }} bahan habis:</strong> {{ $stokHabis->take(4)->pluck('name')->join(', ') }}</span>
                    </div>
                @endif

                @if ($stokMenipis->count() > 0)
                    <div class="dash-note alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><strong>{{ $stokMenipis->count() }} bahan menipis:</strong> {{ $stokMenipis->take(4)->pluck('name')->join(', ') }}</span>
                    </div>
                @endif

                @if ($jumlahBatchSegeraEd > 0)
                    <div class="dash-note alert-warning">
                        <i class="fas fa-calendar-times"></i>
                        <span><strong>{{ $jumlahBatchSegeraEd }} batch segera kedaluwarsa</strong> dalam 30 hari ke depan.</span>
                    </div>
                    <ul class="dash-note-batch">
                        @foreach ($batchSegeraEd as $batch)
                            <li><span class="badge badge-warning">{{ $batch->expired_date->format('d M Y') }}</span> {{ $batch->bahanBaku->name ?? '-' }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($purchaseDraft > 0)
                    <div class="dash-note alert-info">
                        <i class="fas fa-file-alt"></i>
                        <span><strong>{{ $purchaseDraft }} pembelian draft</strong> masih menunggu diselesaikan.</span>
                    </div>
                @endif

                @if ($totalPerhatian === 0)
                    <div class="dash-note alert-success mb-0">
                        <i class="fas fa-check-circle"></i>
                        <span>Semua aman. Stok terkendali dan tidak ada batch yang perlu buru-buru dipakai.</span>
                    </div>
                @endif

                <div class="dash-summary">
                    Bulan ini: omzet <strong>Rp {{ FormatMoney($omzetMonth, 0) }}</strong> dari
                    {{ $trxMonth }} transaksi &mdash; belanja bahan <strong>Rp {{ FormatMoney($purchaseMonth, 0) }}</strong>.
                </div>
            </div>
        </div>
    </div>
</div>

</section>
</div>
@endsection
@push('scripts')
<script src="{{ asset('library/chart.js/dist/Chart.min.js') }}"></script>
<script>
$(function () {
    /* Chart dibuat setelah CSS/padding kolom final (requestAnimationFrame ganda)
       supaya tinggi pembungkus .dash-chart (flex column) sudah terukur — kalau
       dibuat terlalu awal, tinggi terukur 0 dan grafik tampil kosong sampai
       ada interaksi (mis. klik legenda). */
    function gambarSaatSiap(gambarFn) {
        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(function () {
                requestAnimationFrame(gambarFn);
            });
        } else {
            setTimeout(gambarFn, 50);
        }
    }

    // Warna & opsi dasar yang dipakai semua grafik.
    var WARNA = { indigo: '#6777ef', hijau: '#47c363', oranye: '#ffa426', merah: '#fc544b', biru: '#3abaf4', ungu: '#9c6ade' };
    var legendaBawah = {
        position: 'bottom',
        labels: { usePointStyle: true, boxWidth: 8, padding: 14, fontColor: '#666', fontSize: 12 }
    };
    var tooltipRupiah = {
        backgroundColor: 'rgba(52, 57, 94, .92)',
        padding: 10,
        titleFontSize: 13,
        bodyFontSize: 13,
        callbacks: {
            label: function (item, data) {
                var nama = data.datasets[item.datasetIndex].label || '';
                var angka = item.yLabel !== undefined ? item.yLabel : item.value;
                return ' ' + nama + ': Rp ' + Number(angka).toLocaleString('id-ID');
            }
        }
    };
    var sumbuRupiah = {
        gridLines: { color: '#eef1f7', drawBorder: false },
        ticks: {
            fontColor: '#99a4c2',
            callback: function (nilai) {
                if (nilai >= 1000000) return (nilai / 1000000).toFixed(1).replace('.0', '') + ' jt';
                if (nilai >= 1000) return Math.round(nilai / 1000) + ' rb';
                return nilai;
            }
        }
    };
    var sumbuBiasa = {
        gridLines: { color: '#eef1f7', drawBorder: false },
        ticks: { fontColor: '#99a4c2', precision: 0 }
    };
    /* Di layar sempit, 14 label tanggal/jam tidak muat dan akan saling
       tumpuk. Jadi jumlah tick dibatasi sesuai lebar jendela, dan aturan
       yang sama diterapkan ulang tiap kali layar berubah ukuran. */
    var DAFTAR_CHART = [];
    function maxTick(jumlahLabel) {
        return window.innerWidth < 768 ? Math.min(6, jumlahLabel) : jumlahLabel;
    }
    function sumbuXAdaptif(jumlahLabel) {
        return { gridLines: { display: false }, ticks: { fontColor: '#99a4c2', fontSize: 11, maxRotation: 0, minRotation: 0, autoSkip: true, maxTicksLimit: maxTick(jumlahLabel) } };
    }
    function daftarChart(chart, jumlahLabel) {
        DAFTAR_CHART.push({ chart: chart, jumlah: jumlahLabel });
    }
    function terapkanUkuranSumbu() {
        DAFTAR_CHART.forEach(function (item) {
            var axes = item.chart.options.scales && item.chart.options.scales.xAxes;
            if (!axes || !axes.length) { return; }
            axes[0].ticks.maxTicksLimit = maxTick(item.jumlah);
            item.chart.update('none');
        });
    }
    var jedaResize;
    $(window).on('resize', function () {
        clearTimeout(jedaResize);
        jedaResize = setTimeout(terapkanUkuranSumbu, 200);
    });

    // 1. Tren 14 hari: omzet (garis) vs belanja bahan (batang).
    // Dataset "Transaksi" dihapus karena skalanya (satuan) tidak sebanding
    // dengan omzet (rupiah) dan sulurnya menempel nol sehingga grafik
    // terlihat kosong tanpa batang yang tampil.
    function gambarTren() {
        var tren = document.getElementById('chart-tren');
        if (tren) {
            var chartTren = new Chart(tren, {
                // type top-level dipakai Chart.js 2.x sebagai dasar grafik campuran.
                type: 'bar',
                data: {
                    labels: {!! json_encode($trenLabels) !!},
                    datasets: [
                        {
                            type: 'bar', label: 'Belanja bahan', yAxisID: 'y',
                            data: {!! json_encode($trenBelanja) !!},
                            backgroundColor: 'rgba(255,164,38,.8)', borderRadius: 4, barPercentage: 0.6
                        },
                        {
                            type: 'line', label: 'Omzet', yAxisID: 'y',
                            data: {!! json_encode($trenOmzet) !!},
                            borderColor: WARNA.indigo, backgroundColor: 'rgba(103,119,239,.12)',
                            fill: true, tension: 0.35, pointRadius: 3, pointHoverRadius: 6, borderWidth: 3
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    legend: legendaBawah, tooltips: tooltipRupiah,
                    scales: {
                        // Satu skala rupiah dipakai bersama agar garis omzet dan
                        // batang belanja benar-benar sebanding satu sama lain.
                        yAxes: [
                            { position: 'left', id: 'y', gridLines: { color: '#eef1f7', drawBorder: false }, ticks: { fontColor: '#99a4c2', beginAtZero: true, callback: function (n) { return n >= 1000000 ? (n / 1000000).toFixed(1).replace('.0', '') + ' jt' : (n >= 1000 ? Math.round(n / 1000) + ' rb' : n); } } }
                        ],
                        xAxes: [sumbuXAdaptif(14)]
                    }
                }
            });

            daftarChart(chartTren, 14);
        }
    }

    /* Grafik selain tren dibuat di dalam fungsi ini, dipanggil lewat
       gambarSaatSiap (setelah layout final) supaya kanvas tidak terukur 0
       dan langsung tergambar tanpa perlu klik legenda. */
    function gambarSemuaGrafik() {
    // 2. Metode pembayaran (30 hari)
    var bayar = document.getElementById('chart-bayar');
    if (bayar) {
        new Chart(bayar, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($bayarLabels) !!},
                datasets: [{
                    data: {!! json_encode($bayarNilai) !!},
                    backgroundColor: [WARNA.indigo, WARNA.hijau, WARNA.oranye, WARNA.merah, WARNA.biru, WARNA.ungu],
                    borderWidth: 2, borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                legend: legendaBawah, cutoutPercentage: '58%',
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            var total = data.datasets[0].data.reduce(function (a, b) { return a + b; }, 0);
                            var persen = total > 0 ? Math.round(item.value / total * 100) : 0;
                            return ' ' + item.label + ': Rp ' + Number(item.value).toLocaleString('id-ID') + ' (' + persen + '%)';
                        }
                    }
                }
            }
        });
    }

    // 3. Menu terlaris (30 hari) - batang horizontal
    var terlaris = document.getElementById('chart-terlaris');
    if (terlaris) {
        new Chart(terlaris, {
            type: 'horizontalBar',
            data: {
                labels: {!! json_encode($terlarisLabels) !!},
                datasets: [{
                    label: 'Porsi terjual',
                    data: {!! json_encode($terlarisQty) !!},
                    backgroundColor: [WARNA.indigo, WARNA.biru, WARNA.hijau, WARNA.oranye, WARNA.ungu, WARNA.merah, '#63c7ea'],
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                legend: { display: false },
                tooltips: { callbacks: { label: function (item) { return ' ' + item.value + ' porsi'; } } },
                scales: {
                    xAxes: [sumbuBiasa],
                    yAxes: [{ gridLines: { display: false }, ticks: { fontColor: '#666', fontSize: 11 } }]
                }
            }
        });
    }

    // 4. Penjualan per kategori (30 hari)
    var kategori = document.getElementById('chart-kategori');
    if (kategori) {
        new Chart(kategori, {
            type: 'polarArea',
            data: {
                labels: {!! json_encode($kategoriLabels) !!},
                datasets: [{
                    data: {!! json_encode($kategoriOmzet) !!},
                    backgroundColor: ['rgba(103,119,239,.75)', 'rgba(71,195,99,.75)', 'rgba(255,164,38,.75)', 'rgba(252,84,75,.75)', 'rgba(58,186,244,.75)', 'rgba(156,106,222,.75)']
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                legend: legendaBawah,
                scale: {
                    ticks: { beginAtZero: true, backdropColor: 'transparent', fontColor: '#99a4c2' },
                    gridLines: { color: '#eef1f7' }
                },
                tooltips: { callbacks: { label: function (item) { return ' Rp ' + Number(item.value).toLocaleString('id-ID'); } } }
            }
        });
    }

    // 5. Kondisi persediaan bahan baku (aman / menipis / habis)
    var stok = document.getElementById('chart-stok');
    if (stok) {
        new Chart(stok, {
            type: 'doughnut',
            data: {
                labels: ['Aman', 'Menipis', 'Habis'],
                datasets: [{
                    data: [{{ $stokAman }}, {{ $stokMenipis->count() }}, {{ $stokHabis->count() }}],
                    backgroundColor: [WARNA.hijau, WARNA.oranye, WARNA.merah],
                    borderWidth: 2, borderColor: '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                legend: { display: false }, cutoutPercentage: '62%',
                tooltips: { callbacks: { label: function (item) { return ' ' + item.label + ': ' + item.value + ' bahan'; } } }
            }
        });
    }

    // 6. Tren 6 bulan: omzet vs belanja bahan, dengan garis selisih
    var bulan = document.getElementById('chart-bulan');
    if (bulan) {
        var chartBulan = new Chart(bulan, {
            type: 'bar',
            data: {
                labels: {!! json_encode($bulanLabels) !!},
                datasets: [
                    { label: 'Omzet', data: {!! json_encode($omzetBulanUmum) !!}, backgroundColor: WARNA.indigo, borderRadius: 4 },
                    { label: 'Belanja bahan', data: {!! json_encode($belanjaBulanUmum) !!}, backgroundColor: WARNA.oranye, borderRadius: 4 },
                    {
                        type: 'line', label: 'Selisih', data: {!! json_encode($selisihBulan) !!},
                        borderColor: WARNA.hijau, backgroundColor: WARNA.hijau, borderWidth: 3, tension: 0.3, fill: false, pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                legend: legendaBawah, tooltips: tooltipRupiah,
                scales: { yAxes: [sumbuRupiah], xAxes: [sumbuXAdaptif(6)] }
            }
        });

        daftarChart(chartBulan, 6);
    }

    // 7. Jam tersibuk hari ini
    var jam = document.getElementById('chart-jam');
    if (jam) {
        var chartJam = new Chart(jam, {
            type: 'bar',
            data: {
                labels: {!! json_encode($jamLabels) !!},
                datasets: [{
                    label: 'Omzet', data: {!! json_encode($jamOmzet) !!},
                    backgroundColor: WARNA.indigo, borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                legend: { display: false }, tooltips: tooltipRupiah,
                scales: { yAxes: [sumbuRupiah], xAxes: [sumbuXAdaptif(14)] }
            }
        });

        daftarChart(chartJam, 14);
    }
    } // akhir gambarSemuaGrafik()

    /* Paksa resize + update semua chart. Dipakai setelah layout final dan
       saat window load, supaya kanvas yang sempat terukur 0 langsung benar
       tanpa perlu klik legenda. */
    function gambarUlangSemua() {
        Object.keys(Chart.instances || {}).forEach(function (id) {
            var c = Chart.instances[id];
            if (c && typeof c.resize === 'function') { c.resize(); }
            if (c && typeof c.update === 'function') { c.update(); }
        });
        terapkanUkuranSumbu();
    }

    /* Gambar semua chart setelah layout final, lalu update sekali lagi saat
       seluruh aset selesai dimuat, supaya kanvas tidak terukur 0 di awal. */
    gambarSaatSiap(function () {
        gambarSemuaGrafik();
        gambarTren();
        gambarUlangSemua();
    });

    $(window).on('load', function () {
        gambarUlangSemua();
    });
});
</script>
@endpush

