@extends('layouts.app')

@section('title', 'Batch / Lot Stok Bahan Baku')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/datatables/media/css/jquery.dataTables.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Batch / Lot Stok Bahan Baku</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventory</a></div>
                    <div class="breadcrumb-item">Batch / Lot Stok</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-light">
                            Setiap pembelian yang sudah diterima menyimpan batchnya sendiri, lengkap dengan
                            stok batch, harga beli, tanggal penerimaan, dan tanggal kedaluwarsa.
                            Urutan batch mengikuti FEFO (yang paling cepat kedaluwarsa di atas).
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <h4>Filter Batch</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Bahan Baku</label>
                                            <select id="filter-bahan-baku" class="form-control">
                                                <option value="">-- Semua Bahan Baku --</option>
                                                @foreach ($bahanBakus as $bahanBaku)
                                                    <option value="{{ $bahanBaku->id }}">{{ $bahanBaku->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Kedaluwarsa Dari</label>
                                            <input type="date" id="filter-expired-from" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Kedaluwarsa Sampai</label>
                                            <input type="date" id="filter-expired-to" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Sisa Stok</label>
                                            <select id="filter-only-available" class="form-control">
                                                <option value="">Semua Batch</option>
                                                <option value="1">Hanya yang Masih Ada</option>
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
                                    <table class="table-striped table" id="table-batches">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Batch</th>
                                                <th>Sumber</th>
                                                <th>Bahan Baku</th>
                                                <th>Satuan</th>
                                                <th>Qty Awal</th>
                                                <th>Sisa Batch</th>
                                                <th>Harga Beli</th>
                                                <th>Nilai Sisa</th>
                                                <th>Tanggal Terima</th>
                                                <th>Kedaluwarsa</th>
                                                <th>Supplier</th>
                                                <th>No. Pembelian</th>
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
            let table = $('#table-batches').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                paging: 25,
                ajax: {
                    url: '{{ route("batch_bahan_baku.data") }}',
                    data: function (d) {
                        d.bahan_baku_id = $('#filter-bahan-baku').val();
                        d.expired_from = $('#filter-expired-from').val();
                        d.expired_to = $('#filter-expired-to').val();
                        d.only_available = $('#filter-only-available').val();
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
                        data: 'batch_code',
                        name: 'batch_code'
                    },
                    {
                        data: 'source',
                        name: 'source',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'bahan_baku_id',
                        name: 'bahan_baku_id',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'satuan',
                        name: 'satuan',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'quantity',
                        name: 'quantity',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'remaining_qty',
                        name: 'remaining_qty',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'unit_price',
                        name: 'unit_price',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'remaining_value',
                        name: 'remaining_value',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'received_date',
                        name: 'received_date',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'expired_date',
                        name: 'expired_date',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'supplier',
                        name: 'supplier',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'purchase',
                        name: 'purchase',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            $('#btn-filter').on('click', function () {
                table.ajax.reload();
            });

            $('#btn-reset').on('click', function () {
                $('#filter-bahan-baku, #filter-expired-from, #filter-expired-to, #filter-only-available').val('');
                table.ajax.reload();
            });
        });
    </script>
@endpush
