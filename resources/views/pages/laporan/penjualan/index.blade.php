@extends('layouts.app')
@section('title', 'Laporan Penjualan')
@push('style')
<link rel="stylesheet" href="{{ asset('library/datatables/media/css/jquery.dataTables.min.css') }}">
@endpush
@section('main')
<div class="main-content">
<section class="section">
<div class="section-header">
<h1>Laporan Penjualan</h1>
<div class="section-header-breadcrumb">
<div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
<div class="breadcrumb-item"><a href="#">Laporan</a></div>
<div class="breadcrumb-item">Penjualan</div>
</div>
</div>
<div class="section-body">
<div class="row mt-4">
<div class="col-12">
<div class="card">
<div class="card-header"><h4>Filter Laporan</h4></div>
<div class="card-body">
<div class="row">
<div class="col-md-3"><div class="form-group">
<label>Tanggal Dari</label>
<input type="date" id="filter-date-from" class="form-control" value="{{ $defaultFrom }}">
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Tanggal Sampai</label>
<input type="date" id="filter-date-to" class="form-control" value="{{ $defaultTo }}">
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Menu</label>
<select id="filter-menu" class="form-control">
<option value="">-- Semua Menu --</option>
@foreach ($menus as $menu)
<option value="{{ $menu->id }}">{{ $menu->name }}</option>
@endforeach
</select>
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Kategori</label>
<select id="filter-category" class="form-control">
<option value="">-- Semua Kategori --</option>
@foreach ($categories as $category)
<option value="{{ $category->id }}">{{ $category->name }}</option>
@endforeach
</select>
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Metode Bayar</label>
<select id="filter-payment" class="form-control">
<option value="">-- Semua --</option>
<option value="cash">Cash</option>
<option value="qris">Qris</option>
<option value="transfer">Transfer</option>
<option value="debit">Debit</option>
</select>
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Status</label>
<select id="filter-status" class="form-control">
<option value="">-- Semua --</option>
<option value="1">Selesai</option>
<option value="-1">Dibatalkan</option>
</select>
</div></div>
<div class="col-md-3"><div class="form-group">
<label>No. Transaksi</label>
<input type="text" id="filter-code" class="form-control" placeholder="mis. TRX-20260924">
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Cari Menu</label>
<input type="text" id="filter-search-menu" class="form-control" placeholder="nama menu...">
</div></div>
</div>
<div class="row"><div class="col-12">
<button type="button" id="btn-filter" class="btn btn-primary">Filter</button>
<button type="button" id="btn-reset" class="btn btn-secondary">Reset</button>
<button type="button" id="btn-export" class="btn btn-success">Export Excel</button>
<button type="button" id="btn-pdf" class="btn btn-danger">Download PDF</button>
</div></div>
</div>
</div>
</div>
</div>
<div class="row">
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-primary"><i class="fas fa-receipt"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Total Transaksi</h4></div>
<div class="card-body" id="stat-transaksi">-</div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-success"><i class="fas fa-check-circle"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Transaksi Selesai</h4></div>
<div class="card-body" id="stat-selesai">-</div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-warning"><i class="fas fa-utensils"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Total Porsi</h4></div>
<div class="card-body" id="stat-porsi">-</div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-danger"><i class="fas fa-money-bill-wave"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Omzet</h4></div>
<div class="card-body" id="stat-omzet">-</div></div>
</div>
</div>
</div>
</div>
<div class="row mt-2">
<div class="col-12">
<div class="card">
<div class="card-header"><h4>Detail Penjualan per Menu</h4></div>
<div class="card-body">
<div class="table-responsive">
<table class="table-striped table" id="table-laporan">
<thead><tr>
<th>#</th><th>Tanggal</th><th>No. Transaksi</th><th>Menu</th><th>Kategori</th><th>Qty</th><th>Harga</th><th>Subtotal</th><th>Bayar</th><th>Kasir</th><th>Status</th>
</tr></thead>
</table>
</div>
</div>
</div>
</div>
</div>
</div>
</section>
</div>
@endsection
@push('scripts')
<script src="{{ asset('library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
<script>
$(function () {
function filters() {
return {
date_from: $('#filter-date-from').val(),
date_to: $('#filter-date-to').val(),
menu_id: $('#filter-menu').val(),
category_id: $('#filter-category').val(),
payment_method: $('#filter-payment').val(),
status: $('#filter-status').val(),
code: $('#filter-code').val(),
search_menu: $('#filter-search-menu').val()
};
}
function loadSummary() {
$.get('{{ route("laporan.penjualan.summary") }}', filters(), function (res) {
$('#stat-transaksi').text(res.total_transaksi);
$('#stat-selesai').text(res.transaksi_selesai);
$('#stat-porsi').text(res.total_porsi_formatted);
$('#stat-omzet').text(res.omzet_formatted);
});
}
let table = $('#table-laporan').DataTable({
processing: true, serverSide: true, responsive: true,
ajax: { url: '{{ route("laporan.penjualan.data") }}', data: function (d) { Object.assign(d, filters()); } },
columns: [
{ data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
{ data: 'sale_date', name: 'sales.sale_date' },
{ data: 'code', name: 'sales.code' },
{ data: 'menu_name', name: 'menus.name' },
{ data: 'category_name', name: 'categories.name', orderable: false, searchable: false },
{ data: 'quantity', name: 'quantity', orderable: false, searchable: false },
{ data: 'unit_price', name: 'unit_price', orderable: false, searchable: false },
{ data: 'subtotal', name: 'subtotal', orderable: false, searchable: false },
{ data: 'payment_method', name: 'sales.payment_method', orderable: false, searchable: false },
{ data: 'kasir', name: 'kasir', orderable: false, searchable: false },
{ data: 'status', name: 'sales.status', orderable: false, searchable: false }
]
});
function reload() { table.ajax.reload(); loadSummary(); }
$('#btn-filter').on('click', reload);
$('#btn-reset').on('click', function () {
$('#filter-menu, #filter-category, #filter-payment, #filter-status, #filter-code, #filter-search-menu').val('');
$('#filter-date-from').val('{{ $defaultFrom }}');
$('#filter-date-to').val('{{ $defaultTo }}');
reload();
});
$('#btn-export').on('click', function () {
window.location = '{{ route("laporan.penjualan.export") }}?' + $.param(filters());
});
$('#btn-pdf').on('click', function () {
window.open('{{ route("laporan.penjualan.print") }}?' + $.param(filters()), '_blank');
});
loadSummary();
});
</script>
@endpush

