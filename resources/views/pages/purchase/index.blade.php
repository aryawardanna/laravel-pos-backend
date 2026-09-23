@extends('layouts.app')

@section('title', 'Pembelian Bahan Baku')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/datatables/media/css/jquery.dataTables.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Pembelian Bahan Baku</h1>
                <div class="section-header-button">
                    <a href="{{ route('purchase.create') }}" class="btn btn-primary">Tambah Pembelian</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Transaksi</a></div>
                    <div class="breadcrumb-item">Pembelian</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Filter Pembelian</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Nomor Pembelian</label>
                                            <input type="text" id="filter-code" class="form-control"
                                                placeholder="mis. PB-20261221">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Supplier</label>
                                            <select id="filter-supplier" class="form-control">
                                                <option value="">-- Semua Supplier --</option>
                                                @foreach ($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                                @endforeach
                                            </select>
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
                                                <option value="">-- Semua Status --</option>
                                                <option value="1">Diterima</option>
                                                <option value="0">Draft</option>
                                                <option value="-1">Dibatalkan</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12 text-right">
                                        <button type="button" id="btn-filter" class="btn btn-primary">Cari</button>
                                        <button type="button" id="btn-reset" class="btn btn-secondary">Reset</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table-striped table" id="table-purchases">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Nomor Pembelian</th>
                                                <th>Tanggal</th>
                                                <th>Supplier</th>
                                                <th>Total</th>
                                                <th>Batch</th>
                                                <th>Status</th>
                                                <th>Dibuat Oleh</th>
                                                <th>Actions</th>
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
    <!-- JS Libraies -->
    <script src="{{ asset('library/datatables/media/js/jquery.dataTables.min.js') }}"></script>

    <!-- Page Specific JS File -->
    <script src="{{ asset('js/page/features-posts.js') }}"></script>
    <script>
        $(document).ready(function () {
            let table = $('#table-purchases').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                paging: 25,
                ajax: {
                    url: '{{ route("purchase.data") }}',
                    data: function (d) {
                        d.code = $('#filter-code').val();
                        d.supplier_id = $('#filter-supplier').val();
                        d.date_from = $('#filter-date-from').val();
                        d.date_to = $('#filter-date-to').val();
                        d.status = $('#filter-status').val();
                    }
                },

                columns: [
                    {
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'code',
                        name: 'code'
                    },
                    {
                        data: 'purchase_date',
                        name: 'purchase_date'
                    },
                    {
                        data: 'supplier_id',
                        name: 'supplier_id',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'total',
                        name: 'total'
                    },
                    {
                        data: 'item_count',
                        name: 'item_count',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'created_by',
                        name: 'created_by',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            // Filter & reset
            $('#btn-filter').on('click', function () {
                table.ajax.reload();
            });

            $('#btn-reset').on('click', function () {
                $('#filter-code, #filter-supplier, #filter-date-from, #filter-date-to, #filter-status').val('');
                table.ajax.reload();
            });

            // Konfirmasi pembatalan pembelian
            $(document).on('submit', '.delete-form', function (e) {
                if (!confirm('Batalkan pembelian ini? Stok bahan baku akan dikembalikan.')) {
                    e.preventDefault();
                }
            });

            // Konfirmasi penerimaan pembelian draft
            $(document).on('submit', '.receive-form', function (e) {
                if (!confirm('Terima pembelian ini? Stok bahan baku akan bertambah.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
@endpush
