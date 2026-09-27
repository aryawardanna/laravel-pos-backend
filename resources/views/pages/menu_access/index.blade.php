@extends('layouts.app')

@section('title', 'Akses Menu')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Akses Menu</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Pengaturan</a></div>
                    <div class="breadcrumb-item">Akses Menu</div>
                </div>
            </div>
            <div class="section-body">
                <form action="{{ route('menu-access.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-info mb-4">
                                Centang modul yang boleh diakses tiap role. Perubahan langsung
                                berlaku: menu di sidebar dan halaman modul ikut menyesuaikan.
                                Role <strong>{{ $superAdminRole }}</strong> adalah super admin dan
                                selalu punya akses penuh.
                            </div>

                            <div class="table-responsive">
                                <table class="table table-striped" id="table-menu-access">
                                    <thead>
                                        <tr>
                                            <th style="width: 40%">Modul</th>
                                            @foreach ($roles as $role)
                                                <th class="text-center">
                                                    <div>{{ ucfirst($role->name) }}</div>
                                                    @if ($role->name === $superAdminRole)
                                                        <span class="badge badge-primary">Super Admin</span>
                                                    @else
                                                        <input type="checkbox" class="checkbox js-check-all"
                                                            data-role="{{ $role->name }}">
                                                        <small>pilih semua</small>
                                                    @endif
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $currentGroup = null; @endphp
                                        @foreach ($modules as $module)
                                            @if ($module['group'] !== $currentGroup)
                                                @php $currentGroup = $module['group']; @endphp
                                                <tr class="bg-light">
                                                    <td colspan="{{ $roles->count() + 1 }}">
                                                        <strong>{{ $currentGroup }}</strong>
                                                    </td>
                                                </tr>
                                            @endif

                                            @foreach ($module['abilities'] as $ability)
                                                <tr>
                                                    <td>
                                                        {{ $module['label'] }}
                                                        <span class="badge badge-light">{{ $ability['label'] }}</span>
                                                        <div class="text-muted" style="font-size: 12px;">
                                                            {{ $ability['permission'] }}
                                                        </div>
                                                    </td>
                                                    @foreach ($roles as $role)
                                                        <td class="text-center">
                                                            @if ($role->name === $superAdminRole)
                                                                <input type="checkbox" class="checkbox" checked disabled>
                                                            @else
                                                                <input type="checkbox" class="checkbox"
                                                                    name="permissions[{{ $role->name }}][]"
                                                                    value="{{ $ability['permission'] }}"
                                                                    data-role="{{ $role->name }}"
                                                                    @checked(in_array($ability['permission'], $granted[$role->name] ?? [], true))>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Simpan</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        // Centang / tidak centang seluruh modul untuk satu role
        $(document).on('change', '.js-check-all', function () {
            var role = $(this).data('role');
            $('input[name="permissions[' + role + '][]"]').prop('checked', this.checked);
        });
    </script>
@endpush
