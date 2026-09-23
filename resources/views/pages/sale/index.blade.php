@extends('layouts.app')
@section('title', 'Penjualan / Kasir')
@push('style')
    <link rel="stylesheet" href="{{ asset('library/datatables/media/css/jquery.dataTables.min.css') }}">
@endpush
@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Penjualan / Kasir</h1>
                <div class="section-header-button">
                    <a href="{{ route('sale.create') }}" class="btn btn-primary">Transaksi Baru</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Transaksi</a></div>
                    <div class="breadcrumb-item">Penjualan</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-light">
                            Penjualan menu otomatis mengurangi stok bahan baku sesuai resep (FEFO per batch).
                        </div>
                        <div class="card">
                            <div class="card-header"><h4>Filter Penjualan</h4></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Nomor Transaksi</label>
                                            <input type="text" id="filter-code" class="form-control" placeholder="mis. TRX-20260924">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Tanggal Dari</label>
                                            <input type="date" id="filter-date-from" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Tanggal Sampai</label>
                                            <input type="date" id="filter-date-to" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Status</label>
                                            <select id="filter-status" class="form-control">
                                                <option value="">-- Semua --</option>
                                                <option value="1">Selesai</option>
                                                <option value="-1">Dibatalkan</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <div class="form-group w-100">
                                            <button type="button" id="btn-filter" class="btn btn-primary">Filter</button>
                                            <button type="button" id="btn-reset" class="btn btn-secondary">Reset</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table-striped table" id="table-sales">
                                        <thead>
                                            <tr>
                                                <th>#</th><th>Nomor</th><th>Tanggal</th><th>Item</th><th>Total</th><th>Bayar</th><th>Status</th><th>Kasir</th><th>Aksi</th>
                                            </tr>
                                        </thead>
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
        $(document).ready(function () {
            let table = $('#table-sales').DataTable({
                processing: true, serverSide: true, responsive: true,
                ajax: {
                    url: '{{ route("sale.data") }}',
                    data: function (d) {
                        d.code = $('#filter-code').val();
                        d.date_from = $('#filter-date-from').val();
                        d.date_to = $('#filter-date-to').val();
                        d.status = $('#filter-status').val();
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'code', name: 'code' },
                    { data: 'sale_date', name: 'sale_date' },
                    { data: 'item_count', name: 'item_count', orderable: false, searchable: false },
                    { data: 'total', name: 'total' },
                    { data: 'payment_method', name: 'payment_method', orderable: false, searchable: false },
                    { data: 'status', name: 'status', orderable: false, searchable: false },
                    { data: 'created_by', name: 'created_by', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ]
            });
            $('#btn-filter').on('click', function () { table.ajax.reload(); });
            $('#btn-reset').on('click', function () {
                $('#filter-code, #filter-date-from, #filter-date-to, #filter-status').val('');
                table.ajax.reload();
            });
            $(document).on('submit', '.delete-form', function (e) {
                if (!confirm('Batalkan transaksi ini? Stok bahan baku akan dikembalikan.')) { e.preventDefault(); }
            });
        });
    </script>
@endpush
