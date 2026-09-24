@extends('layouts.app')
@section('title', 'Laporan Stok')
@push('style')
<link rel="stylesheet" href="{{ asset('library/datatables/media/css/jquery.dataTables.min.css') }}">
@endpush
@section('main')
<div class="main-content">
<section class="section">
<div class="section-header">
<h1>Laporan Stok Bahan Baku</h1>
<div class="section-header-breadcrumb">
<div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
<div class="breadcrumb-item"><a href="#">Laporan</a></div>
<div class="breadcrumb-item">Stok</div>
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
<label>Satuan</label>
<select id="filter-satuan" class="form-control">
<option value="">-- Semua Satuan --</option>
@foreach ($satuans as $satuan)
<option value="{{ $satuan->id }}">{{ $satuan->name }}</option>
@endforeach
</select>
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Kondisi Stok</label>
<select id="filter-kondisi" class="form-control">
<option value="">-- Semua --</option>
<option value="aman">Aman</option>
<option value="menipis">Menipis (stok &lt;= min)</option>
<option value="habis">Habis (stok &lt;= 0)</option>
</select>
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Status</label>
<select id="filter-status" class="form-control">
<option value="">-- Semua --</option>
<option value="1">Aktif</option>
<option value="0">Nonaktif</option>
</select>
</div></div>
<div class="col-md-3"><div class="form-group">
<label>Cari Bahan / Kode</label>
<input type="text" id="filter-search" class="form-control" placeholder="nama atau kode...">
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
<div class="card-icon bg-primary"><i class="fas fa-boxes"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Total Bahan Baku</h4></div>
<div class="card-body" id="stat-bahan">-</div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-danger"><i class="fas fa-times-circle"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Stok Habis</h4></div>
<div class="card-body" id="stat-habis">-</div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Stok Menipis</h4></div>
<div class="card-body" id="stat-menipis">-</div></div>
</div>
</div>
<div class="col-lg-3 col-md-6 col-12">
<div class="card card-statistic-1">
<div class="card-icon bg-success"><i class="fas fa-money-bill-wave"></i></div>
<div class="card-wrap"><div class="card-header"><h4>Nilai Persediaan</h4></div>
<div class="card-body" id="stat-nilai">-</div></div>
</div>
</div>
</div>
<div class="row mt-2">
<div class="col-12">
<div class="card">
<div class="card-header"><h4>Stok Bahan Baku per Item</h4></div>
<div class="card-body">
<div class="table-responsive">
<table class="table-striped table" id="table-laporan">
<thead><tr>
<th>#</th><th>Bahan Baku</th><th>Kode</th><th>Satuan</th><th>Stok</th><th>Min. Stok</th><th>Harga</th><th>Nilai</th><th>Batch</th><th>Kondisi</th><th>Status</th>
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
satuan_id: $('#filter-satuan').val(),
kondisi: $('#filter-kondisi').val(),
status: $('#filter-status').val(),
search: $('#filter-search').val()
};
}
function loadSummary() {
$.get('{{ route("laporan.stok.summary") }}', filters(), function (res) {
$('#stat-bahan').text(res.total_bahan);
$('#stat-habis').text(res.stok_habis);
$('#stat-menipis').text(res.stok_menipis);
$('#stat-nilai').text(res.nilai_formatted);
});
}
let table = $('#table-laporan').DataTable({
processing: true, serverSide: true, responsive: true,
ajax: { url: '{{ route("laporan.stok.data") }}', data: function (d) { Object.assign(d, filters()); } },
columns: [
{ data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
{ data: 'name', name: 'bahan_bakus.name' },
{ data: 'code', name: 'bahan_bakus.code' },
{ data: 'satuan_name', name: 'satuans.name', orderable: false, searchable: false },
{ data: 'stock', name: 'bahan_bakus.stock' },
{ data: 'min_stock', name: 'bahan_bakus.min_stock', orderable: false, searchable: false },
{ data: 'price', name: 'bahan_bakus.price', orderable: false, searchable: false },
{ data: 'nilai', name: 'nilai', orderable: false, searchable: false },
{ data: 'batch_info', name: 'batch_info', orderable: false, searchable: false },
{ data: 'kondisi', name: 'kondisi', orderable: false, searchable: false },
{ data: 'status', name: 'bahan_bakus.status', orderable: false, searchable: false }
]
});
function reload() { table.ajax.reload(); loadSummary(); }
$('#btn-filter').on('click', reload);
$('#btn-reset').on('click', function () {
$('#filter-satuan, #filter-kondisi, #filter-status, #filter-search').val('');
reload();
});
$('#btn-export').on('click', function () {
window.location = '{{ route("laporan.stok.export") }}?' + $.param(filters());
});
$('#btn-pdf').on('click', function () {
window.open('{{ route("laporan.stok.print") }}?' + $.param(filters()), '_blank');
});
loadSummary();
});
</script>
@endpush