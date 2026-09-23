@extends('layouts.app')

@section('title', 'Stock Opname Bahan Baku')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/datatables/media/css/jquery.dataTables.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Stock Opname Bahan Baku</h1>
                <div class="section-header-button">
                    <a href="{{ route('stock_opname.create') }}" class="btn btn-primary">Tambah Opname</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventory</a></div>
                    <div class="breadcrumb-item">Stock Opname</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-light">
                            Stock opname membandingkan stok sistem dengan hasil hitung fisik. Selisih baru
                            diterapkan ke stok ketika opname <strong>diselesaikan</strong>, dan otomatis
                            tercatat di <a href="{{ route('kartu_stok.index') }}">kartu stok</a> sebagai penyesuaian.
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <h4>Filter Opname</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Nomor Opname</label>
                                            <input type="text" id="filter-code" class="form-control"
                                                placeholder="mis. SO-20260924">
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
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Status</label>
                                            <select id="filter-status" class="form-control">
                                                <option value="">-- Semua Status --</option>
                                                <option value="1">Selesai</option>
                                                <option value="0">Draft</option>
                                                <option value="-1">Dibatalkan</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div>
                                                <button type="button" id="btn-filter" class="btn btn-primary">Cari</button>
                                                <button type="button" id="btn-reset" class="btn btn-secondary">Reset</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table-striped table" id="table-stock-opnames">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Nomor Opname</th>
                                                <th>Tanggal</th>
                                                <th>Item</th>
                                                <th>Ringkasan Selisih</th>
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
            let table = $('#table-stock-opnames').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                paging: 25,
                ajax: {
                    url: '{{ route("stock_opname.data") }}',
                    data: function (d) {
                        d.code = $('#filter-code').val();
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
                        data: 'opname_date',
                        name: 'opname_date'
                    },
                    {
                        data: 'item_count',
                        name: 'item_count',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'difference_summary',
                        name: 'difference_summary',
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

            $('#btn-filter').on('click', function () {
                table.ajax.reload();
            });

            $('#btn-reset').on('click', function () {
                $('#filter-code, #filter-date-from, #filter-date-to, #filter-status').val('');
                table.ajax.reload();
            });

            // Konfirmasi penyelesaian opname
            $(document).on('submit', '.finalize-form', function (e) {
                if (!confirm('Selesaikan stock opname ini? Stok bahan baku akan disesuaikan dengan hasil hitung fisik.')) {
                    e.preventDefault();
                }
            });

            // Konfirmasi pembatalan opname
            $(document).on('submit', '.delete-form', function (e) {
                if (!confirm('Batalkan stock opname ini? Penyesuaian stok akan dikembalikan.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
@endpush
