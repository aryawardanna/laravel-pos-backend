@extends('layouts.app')

@section('title', 'Kartu Stok Bahan Baku')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/datatables/media/css/jquery.dataTables.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Kartu Stok Bahan Baku</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventory</a></div>
                    <div class="breadcrumb-item">Kartu Stok</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-light">
                            Kartu stok mencatat setiap pergerakan stok bahan baku. Pembelian yang diterima
                            tercatat sebagai <strong>stok masuk</strong> per batch, sedangkan pembatalan atau
                            koreksi pembelian tercatat sebagai <strong>stok keluar</strong>. Kolom saldo adalah
                            stok bahan baku setelah pergerakan tersebut.
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <h4>Filter Kartu Stok</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
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
                                            <label>Jenis</label>
                                            <select id="filter-type" class="form-control">
                                                <option value="">-- Semua --</option>
                                                <option value="in">Masuk</option>
                                                <option value="out">Keluar</option>
                                                <option value="adjustment">Penyesuaian</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Nomor Pembelian</label>
                                            <input type="text" id="filter-reference" class="form-control"
                                                placeholder="mis. PB-20261221">
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
                                    <table class="table-striped table" id="table-kartu-stok">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Tanggal</th>
                                                <th>Bahan Baku</th>
                                                <th>Satuan</th>
                                                <th>Batch</th>
                                                <th>Referensi</th>
                                                <th>Jenis</th>
                                                <th>Masuk</th>
                                                <th>Keluar</th>
                                                <th>Saldo</th>
                                                <th>Harga Beli</th>
                                                <th>Keterangan</th>
                                                <th>Dicatat Oleh</th>
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
            let table = $('#table-kartu-stok').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                paging: 25,
                ajax: {
                    url: '{{ route("kartu_stok.data") }}',
                    data: function (d) {
                        d.bahan_baku_id = $('#filter-bahan-baku').val();
                        d.type = $('#filter-type').val();
                        d.reference = $('#filter-reference').val();
                        d.date_from = $('#filter-date-from').val();
                        d.date_to = $('#filter-date-to').val();
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
                        data: 'movement_date',
                        name: 'movement_date'
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
                        data: 'batch_code',
                        name: 'batch_code'
                    },
                    {
                        data: 'reference_link',
                        name: 'reference_link',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'type',
                        name: 'type',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'quantity_in',
                        name: 'quantity_in',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'quantity_out',
                        name: 'quantity_out',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'balance',
                        name: 'balance',
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
                        data: 'description',
                        name: 'description',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'created_by',
                        name: 'created_by',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            $('#btn-filter').on('click', function () {
                table.ajax.reload();
            });

            $('#btn-reset').on('click', function () {
                $('#filter-bahan-baku, #filter-type, #filter-reference, #filter-date-from, #filter-date-to').val('');
                table.ajax.reload();
            });
        });
    </script>
@endpush
