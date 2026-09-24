@extends('layouts.app')
@section('title', 'Dashboard')
@push('style')
<style>
.hero-card { background: linear-gradient(135deg, #6777ef 0%, #3b4fd8 60%, #2b3a9e 100%); border: none; color: #fff; overflow: hidden; position: relative; }
.hero-card .card-body { position: relative; z-index: 2; }
.hero-card::after { content: '\f54e'; font-family: 'Font Awesome 5 Free'; font-weight: 900; position: absolute; right: -10px; bottom: -40px; font-size: 200px; opacity: .12; z-index: 1; }
.hero-card h2 { color: #fff; font-weight: 700; }
.hero-card p { color: rgba(255,255,255,.85); }
.hero-actions .btn { margin-right: 8px; margin-bottom: 8px; }
.rank-bar { height: 8px; border-radius: 99px; background: #f0f0f0; overflow: hidden; }
.rank-bar > div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #6777ef, #47c363); }
.menu-thumb { width: 44px; height: 44px; object-fit: cover; border-radius: 10px; background: #f2f2f2; }
.quick-link { display: block; border: 1px dashed #ddd; border-radius: 12px; padding: 14px; text-align: center; transition: .2s; color: #34395e; }
.quick-link:hover { border-color: #6777ef; color: #6777ef; text-decoration: none; transform: translateY(-2px); }
.quick-link i { font-size: 26px; display: block; margin-bottom: 6px; }
</style>
@endpush
@section('main')
<div class="main-content">
<section class="section">
<div class="section-header"><h1>Dashboard</h1></div>
<div class="section-body">
<div class="row">
<div class="col-12">
<div class="card hero-card">
<div class="card-body">
<div class="row align-items-center">
<div class="col-md-8">
<h2>Halo, {{ Auth::user()->name ?? 'Kasir' }}!</h2>
<p class="mb-1">{{ $today->translatedFormat('l, d F Y') }} - Omzet hari ini <strong>Rp {{ FormatMoney($omzetToday) }}</strong> dari <strong>{{ $trxToday }} transaksi</strong>.</p>
<p class="mb-3">
@if ($growth >= 0)
<span class="badge badge-success">Naik +{{ $growth }}% vs kemarin</span>
@else
<span class="badge badge-danger">Turun {{ $growth }}% vs kemarin</span>
@endif
<span class="badge badge-light ml-1">{{ $menuAktif }} menu aktif</span>
</p>
<div class="hero-actions">
<a href="{{ route('sale.create') }}" class="btn btn-light">Kasir Baru</a>
<a href="{{ route('purchase.create') }}" class="btn btn-outline-light">Beli Bahan</a>
<a href="{{ route('laporan.penjualan.index') }}" class="btn btn-outline-light">Laporan</a>
</div>
</div>
<div class="col-md-4 text-center d-none d-md-block">
<div style="font-size:64px">🍜</div>
<div style="color:rgba(255,255,255,.85)">Semangat jualan hari ini!</div>
</div>
</div>
</div>
</div>
</div>
</div>
<div class="row">
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-primary"><i class="fas fa-money-bill-wave"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Omzet Hari Ini</h4></div>
<div class="card-body">Rp {{ FormatMoney($omzetToday) }}</div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-success"><i class="fas fa-receipt"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Transaksi Hari Ini</h4></div>
<div class="card-body">{{ $trxToday }} <small class="text-muted">/ bln {{ $trxMonth }}</small></div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-warning"><i class="fas fa-chart-line"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Omzet Bulan Ini</h4></div>
<div class="card-body">Rp {{ FormatMoney($omzetMonth) }}</div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-danger"><i class="fas fa-boxes"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Nilai Stok</h4></div>
<div class="card-body">Rp {{ FormatMoney($nilaiStok) }}</div></div>
</div>
</div>
</div>
<div class="row">
<div class="col-lg-8 col-md-12 col-12">
<div class="card">
<div class="card-header"><h4>Grafik Omzet 14 Hari Terakhir</h4></div>
<div class="card-body"><canvas id="chart-omzet" height="120"></canvas></div>
</div>
</div>
<div class="col-lg-4 col-md-12 col-12">
<div class="card">
<div class="card-header"><h4>Metode Bayar Hari Ini</h4></div>
<div class="card-body"><canvas id="chart-pay" height="180"></canvas>
@if ($payMap->isEmpty())
<div class="text-center text-muted mt-2">Belum ada transaksi hari ini.</div>
@endif
</div>
</div>
</div>
<div class="row">
<div class="col-lg-4 col-md-12 col-12">
<div class="card">
<div class="card-header"><h4>Menu Terlaris Bulan Ini</h4></div>
<div class="card-body">
@forelse ($topMenus as $top)
@php $pct = $maxQty > 0 ? round($top->qty / $maxQty * 100) : 0; @endphp
<div class="mb-3">
<div class="d-flex align-items-center">
@if ($top->menu && $top->menu->image)
<img src="{{ filter_var($top->menu->image, FILTER_VALIDATE_URL) ? $top->menu->image : asset('images/menu/' . $top->menu->image) }}" class="menu-thumb mr-2" alt="">
@else
<div class="menu-thumb mr-2 d-flex align-items-center justify-content-center"><i class="fas fa-utensils text-muted"></i></div>
@endif
<div class="flex-grow-1">
<div class="font-weight-bold">{{ $top->menu->name ?? '-' }}</div>
<small class="text-muted">{{ FormatQty($top->qty) }} porsi - Rp {{ FormatMoney($top->omzet) }}</small>
</div>
<span class="badge badge-primary">#{{ $loop->iteration }}</span>
</div>
<div class="rank-bar mt-2"><div style="width: {{ $pct }}%"></div></div>
</div>
@empty
<div class="text-center text-muted">Belum ada penjualan bulan ini.</div>
@endforelse
</div>
</div>
</div>
<div class="col-lg-4 col-md-12 col-12">
<div class="card">
<div class="card-header"><h4>Transaksi Terakhir</h4>
<div class="card-header-action"><a href="{{ route('sale.index') }}" class="btn btn-primary btn-sm">Lihat Semua</a></div>
</div>
<div class="card-body p-0">
<ul class="list-group list-group-flush">
@forelse ($recentSales as $sale)
<li class="list-group-item d-flex justify-content-between align-items-center">
<div>
<div class="font-weight-bold">{{ $sale->code }}</div>
<small class="text-muted">{{ $sale->sale_date?->format('d M') }} - {{ $sale->creator->name ?? '-' }}</small>
</div>
<div class="text-right">
<div class="font-weight-bold">Rp {{ FormatMoney($sale->total) }}</div>
@if ($sale->isCompleted())
<span class="badge badge-success">Selesai</span>
@else
<span class="badge badge-danger">Batal</span>
@endif
</div>
</li>
@empty
<li class="list-group-item text-center text-muted">Belum ada transaksi.</li>
@endforelse
</ul>
</div>
</div>
</div>
<div class="col-lg-4 col-md-12 col-12">
<div class="card">
<div class="card-header"><h4>Perlu Perhatian</h4></div>
<div class="card-body">
@if ($stokHabis->count() > 0)
<div class="alert alert-danger py-2"><strong>{{ $stokHabis->count() }} bahan habis:</strong> {{ $stokHabis->take(4)->pluck('name')->join(', ') }}</div>
@endif
@if ($stokMenipis->count() > 0)
<div class="alert alert-warning py-2"><strong>{{ $stokMenipis->count() }} bahan menipis:</strong> {{ $stokMenipis->take(4)->pluck('name')->join(', ') }}</div>
@endif
@if ($batchSegeraEd->count() > 0)
<div class="font-weight-bold mb-2">Batch segera kedaluwarsa:</div>
<ul class="list-unstyled">
@foreach ($batchSegeraEd as $b)
<li class="mb-1"><span class="badge badge-warning">{{ $b->expired_date->format('d M Y') }}</span> {{ $b->bahanBaku->name ?? '-' }}</li>
@endforeach
</ul>
@endif
@if ($purchaseDraft > 0)
<div class="alert alert-info py-2"><strong>{{ $purchaseDraft }} pembelian draft</strong> menunggu. <a href="{{ route('purchase.index') }}">Proses</a></div>
@endif
@if ($stokHabis->count() === 0 && $stokMenipis->count() === 0 && $batchSegeraEd->count() === 0 && $purchaseDraft === 0)
<div class="text-center text-success py-3">Semua aman. Stok terkendali.</div>
@endif
<div class="mt-2">
<a href="{{ route('laporan.stok.index') }}" class="btn btn-sm btn-outline-danger">Cek Stok</a>
<a href="{{ route('batch_bahan_baku.index') }}" class="btn btn-sm btn-outline-warning">Cek Batch</a>
</div>
</div>
</div>
</div>
</div>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header"><h4>Aksi Cepat</h4></div>
<div class="card-body">
<div class="row">
<div class="col-md-3 col-6 mb-2"><a class="quick-link" href="{{ route('sale.create') }}"><i class="fas fa-cash-register"></i>Kasir Baru</a></div>
<div class="col-md-3 col-6 mb-2"><a class="quick-link" href="{{ route('purchase.create') }}"><i class="fas fa-shopping-cart"></i>Beli Bahan</a></div>
<div class="col-md-3 col-6 mb-2"><a class="quick-link" href="{{ route('menu.create') }}"><i class="fas fa-utensils"></i>Tambah Menu</a></div>
<div class="col-md-3 col-6 mb-2"><a class="quick-link" href="{{ route('stock_opname.index') }}"><i class="fas fa-clipboard-check"></i>Stock Opname</a></div>
</div>
<div class="text-muted mt-2">Bulan ini: omzet <strong>Rp {{ FormatMoney($omzetMonth) }}</strong> ({{ $trxMonth }} trx) - belanja <strong>Rp {{ FormatMoney($purchaseMonth) }}</strong>.</div>
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
var omzet = document.getElementById('chart-omzet');
if (omzet) {
new Chart(omzet, { type: 'line',
data: { labels: {!! json_encode($chartLabels) !!},
datasets: [
{ label: 'Omzet (Rp)', data: {!! json_encode($chartOmzet) !!}, borderColor: '#6777ef', backgroundColor: 'rgba(103,119,239,.15)', fill: true, borderWidth: 2 },
{ label: 'Transaksi', data: {!! json_encode($chartTrx) !!}, borderColor: '#47c363', fill: false, borderWidth: 2 }
] },
options: { responsive: true } });
}
var pay = document.getElementById('chart-pay');
if (pay) {
new Chart(pay, { type: 'doughnut',
data: { labels: {!! json_encode($payMap->keys()->map(fn ($k) => ucfirst($k))->values()) !!},
datasets: [{ data: {!! json_encode($payMap->values()->values()) !!}, backgroundColor: ['#6777ef', '#47c363', '#ffa426', '#fc544b', '#3abaf4'] }] },
options: { responsive: true, legend: { position: 'bottom' } } });
}
});
</script>
@endpush

</div>

