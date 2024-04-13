@extends('layouts.app')

@section('title', 'Categories')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
    <link href="{{ url('library/datatables.net-bs/css/dataTables.bootstrap4.min.css') }}" rel="stylesheet">
    <link href="{{ url('library/datatables.net-fixedheader-bs/css/fixedHeader.bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ url('library/datatables.net-responsive-bs/css/responsive.bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('library/izitoast/dist/css/iziToast.min.css') }}">
@endpush

@section('main')
    <style>
        #dttable_paginate {
            float: right;
        }

        #dttable_filter {
            float: right;
        }

        #dttable_length {
            float: left;
        }

        /* Styling for pagination buttons */
        .dataTables_paginate.paging_simple_numbers.pull-right .paginate_button {
            display: inline-block;
            padding: 5px 10px;
            margin: 0 2px;
            background-color: #ccc;
            color: #333;
            border: 1px solid #ccc;
            border-radius: 3px;
            text-decoration: none;
            cursor: pointer;
        }

        /* Styling for current page button */
        .dataTables_paginate.paging_simple_numbers.pull-right .paginate_button.current {
            background-color: #007bff;
            color: #fff;
            border-color: #007bff;
        }

        /* Styling for disabled button */
        .dataTables_paginate.paging_simple_numbers.pull-right .paginate_button.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Styling for previous and next buttons */
        .dataTables_paginate.paging_simple_numbers.pull-right .paginate_button.previous,
        .dataTables_paginate.paging_simple_numbers.pull-right .paginate_button.next {
            font-weight: bold;
        }
    </style>
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1 class="section-title">Categories</h1>
                <div class="section-header-button">
                    <a href="{{ route('categories.create') }}" class="btn btn-primary">Add New</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Categories</a></div>
                    <div class="breadcrumb-item">All Categories</div>
                </div>
            </div>
            <div class="section-body">

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <table id="dttable"
                                    class="table display nowrap table-sm table-striped row-border cell-border hover order-column dt-responsive compact"
                                    cellspacing="0" cellpadding="0">
                                    <thead style="color:#2A3F54;background-color:#f2f2f2">
                                        <tr style="height:35px">
                                            <th class="all" style="text-align:left">No.</th>
                                            <th class="all" style="text-align:left">Nama</th>
                                            <th class="all" style="text-align:left">Created At</th>
                                            <th class="all" style="text-align:left">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer pb-0 pt-0">
                                <div class="row flex-column-reverse flex-md-row">
                                    <div class="col-md-7">

                                    </div>
                                    <div class="col-md-5 paginate pt-2 pb-2">
                                        <div class="dataTables_paginate paging_simple_numbers" id="dttable_paginate">
                                        </div>
                                    </div>
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
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>

    <!-- Page Specific JS File -->
    <script src="{{ asset('js/page/features-posts.js') }}"></script>
    <script src="{{ asset('library/datatables/media/js/jquery.datatables.min.js') }}"></script>
    <script src="{{ asset('library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            @if (Session::has('success'))
                iziToast.success({
                    title: 'Success!',
                    message: '{{ Session::get('success') }}',
                    position: 'topRight'
                });
            @endif
            var table = $('#dttable').DataTable();
            $('#dttable').empty();
            table.destroy();
            var tbl = $('#dttable').DataTable({
                scrollX: true,
                scrollCollapse: true,
                paging: true,
                pageLength: 10,
                lengthChange: true,
                searching: true,
                ordering: true,
                info: false,
                autoWidth: false,
                language: {
                    search: ""
                },
                bProcessing: true,
                bAutoWidth: false,

                ajax: {
                    'url': '{{ route('categories.json') }}',
                    data: function(d) {
                        console.log('data async');
                    }
                },
                aoColumns: [{
                        mData: "id",
                        render: function(data, type, row, meta) {
                            return meta.row + 1;
                        },
                        sWidth: '5%'
                    },
                    {
                        mData: "name",
                        name: "name",
                        sWidth: '20%'
                    },
                    {
                        mData: 'created_at',
                        sWidth: '20%'
                    },
                    {
                        mData: 'aksi',
                        sWidth: '5%'
                    },
                ],
                initComplete: function(settings, json) {
                    $(".search_table").html("");
                    //search
                    $('#dttable_filter input[type="search"]').attr('placeholder', 'Cari data...');

                    //paginate
                    $("#dttable_wrapper .dataTables_paginate").addClass("pull-right");
                    $("#dttable_wrapper .dataTables_paginate").prependTo(".paginate");
                    $('.search_table .btn').css({
                        "font-size": "12px",
                        "height": "30px"
                    });
                },

            });


            $('.dataTables_empty').html(
                '<i class="fa fa-spinner fa-3x fa-spin fa-fw"></i> <span>Loading...</span>');
            $('table').on('click', 'tr:not(.details-control)', function(e) {
                $(".dataTables_scrollBody").getNiceScroll().resize();
            });

            $('#delItem').on('show.bs.modal', function(e) {
                $id = $(e.relatedTarget).data('id');
                console.log($id);
                $("[name=id]").val($id);
            });

            $(document).on('click', '.btn-delete', function() {
                var id = $(this).data('id');
                iziToast.question({
                    timeout: false,
                    close: false,
                    overlay: true,
                    displayMode: 'once',
                    id: 'question',
                    zindex: 999,
                    title: 'Hapus data',
                    message: 'Anda yakin ingin menghapus data ini?',
                    position: 'center',
                    buttons: [
                        ['<button><b>Ya</b></button>', function(instance, toast) {
                            instance.hide({
                                transitionOut: 'fadeOut'
                            }, toast, 'button');
                            // Lakukan penghapusan data melalui AJAX
                            $.ajax({
                                url: '{{ route('categories.destroy', ['category' => ':id']) }}'
                                    .replace(':id', id),
                                type: 'DELETE',
                                dataType: 'json',
                                data: {
                                    _token: '{{ csrf_token() }}'
                                },
                                success: function(response) {
                                    console.log(response);
                                    if (response.success) {
                                        // Jika penghapusan berhasil, tampilkan pesan sukses
                                        iziToast.success({
                                            title: 'Berhasil',
                                            message: response.success,
                                            position: 'topRight'
                                        });
                                        // Perbarui DataTables
                                        $('#dttable').DataTable().ajax
                                            .reload();
                                    } else {
                                        // Jika terjadi kesalahan, tampilkan pesan error
                                        iziToast.error({
                                            title: 'Error',
                                            message: response.error,
                                            position: 'topRight'
                                        });
                                    }
                                },
                                error: function(xhr, status, error) {
                                    // Jika terjadi kesalahan saat melakukan request AJAX, tampilkan pesan error
                                    console.log(xhr.responseText);
                                    iziToast.error({
                                        title: 'Error',
                                        message: 'Terjadi kesalahan saat menghapus data.',
                                        position: 'topRight'
                                    });
                                }
                            });
                        }, true], // true untuk menutup iziToast setelah tombol diklik
                        ['<button>Tidak</button>', function(instance, toast) {
                            instance.hide({
                                transitionOut: 'fadeOut'
                            }, toast, 'button');
                        }]
                    ]

                });
            });
        });
    </script>
@endpush
