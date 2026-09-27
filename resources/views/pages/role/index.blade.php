@extends('layouts.app')

@section('title', 'Roles')

@push('style')
    <link rel="stylesheet" href="{{ asset('library/datatables/media/css/jquery.dataTables.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Roles</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Pengaturan</a></div>
                    <div class="breadcrumb-item">Roles</div>
                </div>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="card">
                            <form action="{{ route('role.store') }}" method="POST">
                                @csrf
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="name">Nama Role</label>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                                            id="name" name="name" value="{{ old('name') }}"
                                            placeholder="Contoh: Kasir Toko">
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <p class="text-muted" style="font-size: 13px;">
                                        Role baru belum punya hak akses apa pun. Buka menu
                                        <a href="{{ route('menu-access.index') }}">Akses Menu</a>
                                        untuk mencentang modul yang boleh diakses.
                                    </p>
                                </div>
                                <div class="card-footer text-right">
                                    <button class="btn btn-primary">Simpan Role</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="table-roles" class="table-striped table" style="width: 100%">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Role</th>
                                                <th>User</th>
                                                <th>Permission</th>
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

    <script>
        $(document).ready(function () {
            $('#table-roles').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: '{{ route("role.data") }}'
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'name', name: 'name' },
                    { data: 'users', name: 'users', orderable: false, searchable: false },
                    { data: 'permissions', name: 'permissions', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ]
            });

            // Delete confirmation (event delegation, karena baris dimuat via AJAX)
            $(document).on('submit', '.delete-form', function (e) {
                if (!confirm('Apakah Anda yakin ingin menghapus role ini?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
@endpush
