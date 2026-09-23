@extends('layouts.app')

@section('title', 'Edit Menu')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/select2/dist/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-tagsinput/dist/bootstrap-tagsinput.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h2 class="section-title m-0">Menus</h2>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Forms</a></div>
                    <div class="breadcrumb-item">Menus</div>
                </div>
            </div>

            <div class="section-body">
                <div class="card">
                    <form action="{{ route('menu.update', $menu->id) }}" method="POST" enctype="multipart/form-data" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="card-body">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text"
                                    class="form-control @error('name')
                                is-invalid
                            @enderror"
                                    name="name" value="{{ $menu->name }}">
                                @error('name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Code</label>
                                <input type="text"
                                    class="form-control @error('code')
                                is-invalid
                            @enderror"
                                    name="code" value="{{ $menu->code }}">
                                @error('code')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category_id" class="form-control">
                                    <option value="">-- Pilih Kategori --</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ $menu->category_id == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Price</label>
                                <input type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control @error('price')
                                is-invalid
                            @enderror"
                                    name="price" value="{{ $menu->price }}">
                                @error('price')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                    name="description"
                                    placeholder=".....">{{ $menu->description }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Image</label>
                                <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                                @error('image')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                                <div class="mb-2 mt-2">
                                    <img src="{{ MenuImageUrl($menu) }}" id="menu-image-preview" width="100"
                                        style="object-fit: cover; border-radius: 5px;" alt="Gambar menu">
                                    <small class="d-block text-muted">
                                        {{ $menu->image ? 'Gambar saat ini.' : 'Belum ada gambar, memakai gambar default.' }}
                                    </small>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <div class="selectgroup w-100">
                                    <label class="selectgroup-item">
                                        <input type="radio" name="status" value="1" class="selectgroup-input"
                                            @if ($menu->status == 1) checked @endif>
                                        <span class="selectgroup-button">Active</span>
                                    </label>
                                    <label class="selectgroup-item">
                                        <input type="radio" name="status" value="0" class="selectgroup-input"
                                            @if ($menu->status == 0) checked @endif>
                                        <span class="selectgroup-button">Inactive</span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Bahan Baku (Resep)</label>
                                <small class="form-text text-muted mb-2">Tambahkan bahan baku yang dibutuhkan untuk membuat menu ini beserta jumlahnya.</small>
                                <div id="bahan-baku-rows">
                                    @forelse($menu->bahanBakus as $bahanBaku)
                                        <div class="row bahan-baku-row mb-2">
                                            <div class="col-6">
                                                <select name="ingredients[bahan_baku_id][]" class="form-control">
                                                    <option value="">-- Pilih Bahan Baku --</option>
                                                    @foreach($bahanBakus as $bb)
                                                        <option value="{{ $bb->id }}" {{ $bahanBaku->id == $bb->id ? 'selected' : '' }}>
                                                            {{ $bb->name }} ({{ $bb->code ?: '-' }}){{ $bb->satuan?->name ? ' - ' . $bb->satuan->name : '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <div class="input-group">
                                                    <input type="number" step="0.001" min="0"
                                                        name="ingredients[quantity][]"
                                                        class="form-control"
                                                        placeholder="Quantity"
                                                        value="{{ $bahanBaku->pivot->quantity }}">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text satuan-badge">{{ $bahanBaku->satuan?->name ?? '-' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-2">
                                                <button type="button" class="btn btn-sm btn-danger remove-bahan-baku"><i class="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted" id="no-bahan-baku">Belum ada bahan baku. Tambahkan di bawah.</p>
                                    @endforelse
                                </div>
                                <button type="button" class="btn btn-sm btn-success mt-2" id="add-bahan-baku">
                                    <i class="fas fa-plus"></i> Tambah Bahan Baku
                                </button>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    @php
    $bahanBakuJson = json_encode($bahanBakus->map(fn ($b) => [
        'id' => $b->id,
        'name' => $b->name,
        'code' => $b->code,
        'satuan' => $b->satuan?->name,
    ]));
@endphp
    <script>
        var bahanBakuOptions = {!! $bahanBakuJson !!};

        function bahanBakuById(id) {
            for (var i = 0; i < bahanBakuOptions.length; i++) {
                if (bahanBakuOptions[i].id == id) {
                    return bahanBakuOptions[i];
                }
            }
            return null;
        }

        function bahanBakuSelectHtml(selectedId) {
            var options = '<option value="">-- Pilih Bahan Baku --</option>';
            bahanBakuOptions.forEach(function (b) {
                var sel = (b.id == selectedId) ? ' selected' : '';
                options += '<option value="' + b.id + '"' + sel + '>' + b.name + ' (' + (b.code || '-') + ')' + (b.satuan ? ' - ' + b.satuan : '') + '</option>';
            });
            return options;
        }

        function updateSatuanBadge(row) {
            var bahan = bahanBakuById(row.find('select[name="ingredients[bahan_baku_id][]"]').val());
            row.find('.satuan-badge').text(bahan && bahan.satuan ? bahan.satuan : '-');
        }

        function addBahanRow(selectedId) {
            var row = $('<div class="row bahan-baku-row mb-2"></div>');
            row.append(
                '<div class="col-6">' +
                    '<select name="ingredients[bahan_baku_id][]" class="form-control">' + bahanBakuSelectHtml(selectedId) + '</select>' +
                '</div>' +
                '<div class="col-4">' +
                    '<div class="input-group">' +
                        '<input type="number" step="0.001" min="0" name="ingredients[quantity][]" class="form-control" placeholder="Quantity">' +
                        '<div class="input-group-append"><span class="input-group-text satuan-badge">-</span></div>' +
                    '</div>' +
                '</div>' +
                '<div class="col-2">' +
                    '<button type="button" class="btn btn-sm btn-danger remove-bahan-baku"><i class="fas fa-times"></i></button>' +
                '</div>'
            );
            updateSatuanBadge(row);
            $('#bahan-baku-rows').append(row);
        }

        $(document).ready(function () {
            $('#add-bahan-baku').on('click', function () {
                // buang pesan "Belum ada bahan baku" saat mulai menambah
                $('#no-bahan-baku').remove();
                addBahanRow();
            });

            $(document).on('click', '.remove-bahan-baku', function () {
                $(this).closest('.bahan-baku-row').remove();
            });

            $(document).on('change', '.bahan-baku-row select', function () {
                updateSatuanBadge($(this).closest('.bahan-baku-row'));
            });
        });
    </script>
@endpush

@push('scripts')
    <script>
        // Pratinjau gambar menu: default tampil, ganti saat user memilih file baru
        $(document).on('change', 'input[name="image"]', function () {
            var file = this.files && this.files[0];
            if (!file) {
                return;
            }
            $('#menu-image-preview').attr('src', URL.createObjectURL(file));
            $('#menu-image-preview').next('small').text('Pratinjau gambar yang akan diunggah.');
        });
    </script>
@endpush
