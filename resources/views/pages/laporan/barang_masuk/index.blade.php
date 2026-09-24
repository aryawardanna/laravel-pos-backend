@extends('layouts.app')
@section('title', 'Laporan Barang Masuk')
@push('style')
<link rel="stylesheet" href="{{ asset('library/datatables/media/css/jquery.dataTables.min.css') }}">
@endpush
@section('main')
<div class="main-content">
<section class="section">
<div class="section-header">
<h1>Laporan Barang Masuk</h1>
<div class="section-header-breadcrumb">
<div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
<div class="breadcrumb-item"><a href="#">Laporan</a></div>
<div class="breadcrumb-item">Barang Masuk</div>
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
<label>Supplier</label>
<select id="filter-supplier" class="form-control">
<option value="">-- Semua Supplier --</option>
@foreach ($suppliers as $supplier)
<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
@endforeach
</select>
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Bahan Baku</label>
<select id="filter-bahan" class="form-control">
<option value="">-- Semua Bahan Baku --</option>
@foreach ($bahanBakus as $bahan)
<option value="{{ $bahan->id }}">{{ $bahan->name }}</option>
@endforeach
</select>
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Status</label>
<select id="filter-status" class="form-control">
<option value="">-- Semua --</option>
<option value="1">Diterima</option>
<option value="0">Draft</option>
<option value="-1">Dibatalkan</option>
</select>
</div></div>
<div class="col-md-3"><div class="form-group">
<label>No. Pembelian</label>
<input type="text" id="filter-code" class="form-control" placeholder="mis. PB-20260924">
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Cari Bahan Baku</label>
<input type="text" id="filter-search-bahan" class="form-control" placeholder="nama bahan...">
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
<div class="card-icon bg-primary"><i class="fas fa-shopping-cart"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Total Pembelian</h4></div>
<div class="card-body" id="stat-pembelian">-</div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-success"><i class="fas fa-check-circle"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Diterima</h4></div>
<div class="card-body" id="stat-diterima">-</div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-warning"><i class="fas fa-boxes"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Total Baris Item</h4></div>
<div class="card-body" id="stat-item">-</div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-danger"><i class="fas fa-money-bill-wave"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Nilai Barang Masuk</h4></div>
<div class="card-body" id="stat-nilai">-</div></div>
</div>
</div>
</div>
<div class="row mt-2">
<div class="col-12">
<div class="card">
<div class="card-header"><h4>Detail Barang Masuk per Batch</h4></div>
<div class="card-body">
<div class="table-responsive">
<table class="table-striped table" id="table-laporan">
<thead><tr>
<th>#</th><th>Tanggal</th><th>No. Pembelian</th><th>Supplier</th><th>Bahan Baku</th><th>Satuan</th><th>Batch</th><th>Qty</th><th>Harga</th><th>Subtotal</th><th>Expired</th><th>Penerima</th><th>Status</th>
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
supplier_id: $('#filter-supplier').val(),
bahan_baku_id: $('#filter-bahan').val(),
status: $('#filter-status').val(),
code: $('#filter-code').val(),
search_bahan: $('#filter-search-bahan').val()
};
}
function loadSummary() {
$.get('{{ route("laporan.barang_masuk.summary") }}', filters(), function (res) {
$('#stat-pembelian').text(res.total_pembelian);
$('#stat-diterima').text(res.pembelian_diterima);
$('#stat-item').text(res.total_item);
$('#stat-nilai').text(res.total_nilai_formatted);
});
}
let table = $('#table-laporan').DataTable({
processing: true, serverSide: true, responsive: true,
ajax: { url: '{{ route("laporan.barang_masuk.data") }}', data: function (d) { Object.assign(d, filters()); } },
columns: [
{ data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
{ data: 'purchase_date', name: 'purchases.purchase_date' },
{ data: 'code', name: 'purchases.code' },
{ data: 'supplier_name', name: 'suppliers.name', orderable: false, searchable: false },
{ data: 'bahan_name', name: 'bahan_bakus.name' },
{ data: 'satuan_name', name: 'satuan_name', orderable: false, searchable: false },
{ data: 'batch_code', name: 'purchase_items.batch_code' },
{ data: 'quantity', name: 'quantity', orderable: false, searchable: false },
{ data: 'unit_price', name: 'unit_price', orderable: false, searchable: false },
{ data: 'subtotal', name: 'subtotal', orderable: false, searchable: false },
{ data: 'expired_date', name: 'purchase_items.expired_date', orderable: false, searchable: false },
{ data: 'penerima', name: 'penerima', orderable: false, searchable: false },
{ data: 'status', name: 'purchases.status', orderable: false, searchable: false }
]
});
function reload() { table.ajax.reload(); loadSummary(); }
$('#btn-filter').on('click', reload);
$('#btn-reset').on('click', function () {
$('#filter-supplier, #filter-bahan, #filter-status, #filter-code, #filter-search-bahan').val('');
$('#filter-date-from').val('{{ $defaultFrom }}');
$('#filter-date-to').val('{{ $defaultTo }}');
reload();
});
$('#btn-export').on('click', function () {
window.location = '{{ route("laporan.barang_masuk.export") }}?' + $.param(filters());
});
$('#btn-pdf').on('click', function () {
window.open('{{ route("laporan.barang_masuk.print") }}?' + $.param(filters()), '_blank');
});
loadSummary();
});
</script>
@endpush
