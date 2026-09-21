@extends('layouts.app')

@section('title', 'Users')

@push('style')
    <link rel="stylesheet" href="{{ asset('library/datatables/media/css/jquery.dataTables.min.css') }}">
@endpush

@section('main')
    @php
        $role = "";
    @endphp
    @if(isset($_GET['role']))
        @php
            $role = $_GET['role'];
        @endphp
    @endif
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Users</h1>
                <div class="section-header-button">
                    <a href="{{ route('user.create') }}" class="btn btn-primary">Add New</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Users</a></div>
                    <div class="breadcrumb-item">All Users</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="mb-3">
                                    <select id="filter-role" class="form-control mb-2 mr-3" style="width:auto; display:inline-block;">
                                        <option value="">-- Pilih Role --</option>
                                        <option value="admin" {{ $role == 'admin' ? 'selected' : '' }}>Admin</option>
                                        <option value="staff" {{ $role == 'staff' ? 'selected' : '' }}>Staff</option>
                                        <option value="user" {{ $role == 'user' ? 'selected' : '' }}>User</option>
                                    </select>
                                </div>

                                <div class="table-responsive">
                                    <table id="table-users" class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Created At</th>
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
    <script>
        $(document).ready(function () {

            $('#table-users').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: '{{ route("user.data") }}',
                    data: function (d) {
                        d.role = '{{ $role }}';
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
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'email',
                        name: 'email'
                    },
                    {
                        data: 'role',
                        name: 'role'
                    },
                    {
                        data: 'created_at',
                        name: 'created_at'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            $('#role-filter').on('change', function () {
                $('#table-users').DataTable().ajax.reload();
            });
            $('#filter-role').prependTo('#table-users_wrapper .dataTables_length');

            // onchange role window location
            $('#filter-role').on('change', function () {
                var role = $(this).val();
                var url = "{{ route('user.index') }}?role=" + role;
                window.location.href = url;
            });



            // Delete confirmation (event delegation, karena baris dimuat via AJAX)
            $(document).on('submit', '.delete-form', function (e) {
                if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
@endpush
