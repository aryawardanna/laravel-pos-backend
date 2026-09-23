@extends('layouts.app')

@section('title', 'Stock Opname Create')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h2 class="section-title m-0">Stock Opname Bahan Baku</h2>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventory</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('stock_opname.index') }}">Stock Opname</a></div>
                </div>
            </div>

            <div class="section-body">
                <div class="card">
                    <form action="{{ route('stock_opname.store') }}" method="POST">
                        @csrf

                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Tanggal Opname <span class="text-danger">*</span></label>
                                        <input type="date" name="opname_date"
                                            class="form-control @error('opname_date') is-invalid @enderror"
                                            value="{{ old('opname_date', date('Y-m-d')) }}">
                                        @error('opname_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <div class="selectgroup w-100">
                                            <label class="selectgroup-item">
                                                <input type="radio" name="status" value="0" class="selectgroup-input"
                                                    {{ old('status', '0') == '0' ? 'checked' : '' }}>
                                                <span class="selectgroup-button">Draft</span>
                                            </label>
                                            <label class="selectgroup-item">
                                                <input type="radio" name="status" value="1" class="selectgroup-input"
                                                    {{ old('status') == '1' ? 'checked' : '' }}>
                                                <span class="selectgroup-button">Selesai</span>
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">
                                            Draft belum mengubah stok. Pilih Selesai bila selisih langsung diterapkan.
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Catatan</label>
                                        <textarea name="description"
                                            class="form-control @error('description') is-invalid @enderror"
                                            placeholder="Catatan opname (opsional)">{{ old('description') }}</textarea>
                                        @error('description')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Hasil Hitung Fisik <span class="text-danger">*</span></label>
                                <small class="form-text text-muted mb-2">
                                    Pilih bahan baku lalu masukkan jumlah hasil hitung fisik. Selisih dihitung
                                    otomatis terhadap stok sistem.
                                </small>

                                @error('items')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('items.bahan_baku_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror

                                <div id="item-rows"></div>

                                <button type="button" class="btn btn-sm btn-success mt-2" id="add-item">
                                    <i class="fas fa-plus"></i> Tambah Bahan Baku
                                </button>
                                <button type="button" class="btn btn-sm btn-info mt-2" id="add-all-item">
                                    <i class="fas fa-list"></i> Muat Semua Bahan Baku
                                </button>

                                <div class="text-right mt-3">
                                    <h5 class="m-0">
                                        Item: <span id="summary-item">0</span> |
                                        <span class="text-success">Lebih: <span id="summary-increase">0</span></span> |
                                        <span class="text-danger">Kurang: <span id="summary-decrease">0</span></span> |
                                        <span class="text-muted">Sesuai: <span id="summary-match">0</span></span>
                                    </h5>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('stock_opname.index') }}" class="btn btn-secondary">Batal</a>
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
        'stock' => (float) $b->stock,
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

        function formatQty(value) {
            return parseFloat(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 3 });
        }

        function bahanBakuSelectHtml(selectedId) {
            var options = '<option value="">-- Pilih Bahan Baku --</option>';
            bahanBakuOptions.forEach(function (b) {
                var sel = (b.id == selectedId) ? ' selected' : '';
                options += '<option value="' + b.id + '"' + sel + '>' + b.name + ' (' + (b.code || '-') + ')' +
                    (b.satuan ? ' - ' + b.satuan : '') + '</option>';
            });
            return options;
        }

        function updateSummary() {
            var increase = 0;
            var decrease = 0;
            var match = 0;
            var total = 0;

            $('.opname-item').each(function () {
                var bahan = bahanBakuById($(this).find('select[name="items[bahan_baku_id][]"]').val());

                if (!bahan) {
                    return;
                }

                total++;

                var difference = parseFloat($(this).find('input[name="items[physical_stock][]"]').val() || 0) - bahan.stock;

                if (difference > 0) {
                    increase++;
                } else if (difference < 0) {
                    decrease++;
                } else {
                    match++;
                }
            });

            $('#summary-item').text(total);
            $('#summary-increase').text(increase);
            $('#summary-decrease').text(decrease);
            $('#summary-match').text(match);
        }

        function updateRow(row) {
            var bahan = bahanBakuById(row.find('select[name="items[bahan_baku_id][]"]').val());
            var systemStock = bahan ? bahan.stock : 0;
            var physicalStock = parseFloat(row.find('input[name="items[physical_stock][]"]').val() || 0);
            var difference = physicalStock - systemStock;

            row.find('.satuan-badge').text(bahan && bahan.satuan ? bahan.satuan : '-');
            row.find('.system-stock').text(formatQty(systemStock));
            row.find('.difference')
                .text((difference > 0 ? '+' : '') + formatQty(difference))
                .removeClass('text-success text-danger text-muted')
                .addClass(difference > 0 ? 'text-success' : (difference < 0 ? 'text-danger' : 'text-muted'));

            updateSummary();
        }

        function addItemRow(selectedId) {
            var row = $('<div class="opname-item border rounded p-2 mb-2"></div>');
            row.append(
                '<div class="row">' +
                    '<div class="col-md-4">' +
                        '<div class="form-group mb-1"><label class="mb-1">Bahan Baku</label>' +
                        '<select name="items[bahan_baku_id][]" class="form-control">' + bahanBakuSelectHtml(selectedId) + '</select></div>' +
                    '</div>' +
                    '<div class="col-md-2">' +
                        '<div class="form-group mb-1"><label class="mb-1">Stok Sistem</label>' +
                        '<div class="form-control-plaintext pt-0 system-stock">0</div></div>' +
                    '</div>' +
                    '<div class="col-md-2">' +
                        '<div class="form-group mb-1"><label class="mb-1">Jumlah Fisik</label>' +
                        '<div class="input-group">' +
                            '<input type="number" step="0.001" min="0" name="items[physical_stock][]" class="form-control" placeholder="0" value="0">' +
                            '<div class="input-group-append"><span class="input-group-text satuan-badge">-</span></div>' +
                        '</div></div>' +
                    '</div>' +
                    '<div class="col-md-2">' +
                        '<div class="form-group mb-1"><label class="mb-1">Selisih</label>' +
                        '<div class="form-control-plaintext pt-0 font-weight-bold difference text-muted">0</div></div>' +
                    '</div>' +
                    '<div class="col-md-2 text-right">' +
                        '<label class="mb-1 d-block">&nbsp;</label>' +
                        '<button type="button" class="btn btn-sm btn-danger remove-item"><i class="fas fa-times"></i></button>' +
                    '</div>' +
                '</div>' +
                '<div class="row">' +
                    '<div class="col-12">' +
                        '<input type="text" name="items[description][]" class="form-control form-control-sm" placeholder="Catatan (opsional)">' +
                    '</div>' +
                '</div>'
            );
            $('#item-rows').append(row);
            updateRow(row);
        }

        function addAllBahanBaku() {
            var selected = [];

            $('.opname-item select[name="items[bahan_baku_id][]"]').each(function () {
                if ($(this).val()) {
                    selected.push(String($(this).val()));
                }
            });

            bahanBakuOptions.forEach(function (b) {
                if (selected.indexOf(String(b.id)) === -1) {
                    addItemRow(b.id);
                }
            });
        }

        $(document).ready(function () {
            $('#add-item').on('click', function () {
                addItemRow();
            });

            $('#add-all-item').on('click', function () {
                addAllBahanBaku();
            });

            $(document).on('click', '.remove-item', function () {
                $(this).closest('.opname-item').remove();
                updateSummary();
            });

            $(document).on('change', '.opname-item select[name="items[bahan_baku_id][]"]', function () {
                updateRow($(this).closest('.opname-item'));
            });

            $(document).on('input', '.opname-item input[name="items[physical_stock][]"]', function () {
                updateRow($(this).closest('.opname-item'));
            });

            // form create: mulai dengan satu baris kosong
            if ($('#item-rows .opname-item').length === 0) {
                addItemRow();
            }
        });
    </script>
@endpush
