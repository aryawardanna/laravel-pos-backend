@extends('layouts.app')

@section('title', 'Pembelian Edit')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h2 class="section-title m-0">Pembelian Bahan Baku</h2>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Transaksi</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('purchase.index') }}">Pembelian</a></div>
                </div>
            </div>

            <div class="section-body">
                <div class="card">
                    <form action="{{ route('purchase.update', $purchase->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Nomor Pembelian</label>
                                        <input type="text" class="form-control" value="{{ $purchase->code }}" readonly>
                                        <small class="form-text text-muted">
                                            Nomor pembelian tidak berubah walau tanggal diubah.
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Supplier</label>
                                        <select name="supplier_id"
                                            class="form-control @error('supplier_id') is-invalid @enderror">
                                            <option value="">-- Pilih Supplier --</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}"
                                                    {{ old('supplier_id', $purchase->supplier_id) == $supplier->id ? 'selected' : '' }}>
                                                    {{ $supplier->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('supplier_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Tanggal Pembelian <span class="text-danger">*</span></label>
                                        <input type="date" name="purchase_date"
                                            class="form-control @error('purchase_date') is-invalid @enderror"
                                            value="{{ old('purchase_date', $purchase->purchase_date?->format('Y-m-d')) }}">
                                        @error('purchase_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <div class="selectgroup w-100">
                                            <label class="selectgroup-item">
                                                <input type="radio" name="status" value="1" class="selectgroup-input"
                                                    {{ old('status', (string) $purchase->status) == '1' ? 'checked' : '' }}>
                                                <span class="selectgroup-button">Diterima</span>
                                            </label>
                                            <label class="selectgroup-item">
                                                <input type="radio" name="status" value="0" class="selectgroup-input"
                                                    {{ old('status', (string) $purchase->status) == '0' ? 'checked' : '' }}>
                                                <span class="selectgroup-button">Draft</span>
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">Pilih Draft bila stok belum ingin ditambahkan.</small>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Catatan</label>
                                        <textarea name="description"
                                            class="form-control @error('description') is-invalid @enderror"
                                            placeholder="Catatan pembelian (opsional)">{{ old('description', $purchase->description) }}</textarea>
                                        @error('description')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Bahan Baku (Batch / Lot) <span class="text-danger">*</span></label>
                                <small class="form-text text-muted mb-2">
                                    Setiap baris menjadi satu batch tersendiri. Menyimpan perubahan akan mengganti
                                    seluruh batch pembelian ini.
                                </small>

                                @if ($purchase->isReceived())
                                    <div class="alert alert-info">
                                        Pembelian ini sudah diterima sehingga stoknya sudah masuk. Perubahan hanya
                                        diperbolehkan selama stok batch belum terpakai — stok lama ditarik kembali
                                        lalu nilai baru diterapkan.
                                    </div>
                                @endif

                                @error('items')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('items.bahan_baku_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror

                                <div id="item-rows">
                                    @foreach ($purchase->items as $item)
                                        <div class="purchase-item border rounded p-2 mb-2">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group mb-1">
                                                        <label class="mb-1">Bahan Baku</label>
                                                        <select name="items[bahan_baku_id][]" class="form-control">
                                                            <option value="">-- Pilih Bahan Baku --</option>
                                                            @foreach ($bahanBakus as $bahanBaku)
                                                                <option value="{{ $bahanBaku->id }}"
                                                                    {{ $item->bahan_baku_id == $bahanBaku->id ? 'selected' : '' }}>
                                                                    {{ $bahanBaku->name }} ({{ $bahanBaku->code ?: '-' }}){{ $bahanBaku->satuan ? ' - ' . $bahanBaku->satuan->name : '' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group mb-1">
                                                        <label class="mb-1">Quantity</label>
                                                        <div class="input-group">
                                                            <input type="number" step="0.001" min="0"
                                                                name="items[quantity][]" class="form-control"
                                                                value="{{ FormatQtyInput($item->quantity) }}">
                                                            <div class="input-group-append">
                                                                <span class="input-group-text satuan-badge">{{ $item->bahanBaku?->satuan?->name ?? '-' }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group mb-1">
                                                        <label class="mb-1">Harga Beli / Satuan</label>
                                                        <input type="number" step="0.01" min="0"
                                                            name="items[unit_price][]" class="form-control"
                                                            value="{{ FormatMoneyInput($item->unit_price) }}">
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group mb-1">
                                                        <label class="mb-1">Kedaluwarsa</label>
                                                        <input type="date" name="items[expired_date][]" class="form-control"
                                                            value="{{ $item->expired_date?->format('Y-m-d') }}">
                                                    </div>
                                                </div>
                                                <div class="col-md-1">
                                                    <div class="form-group mb-1">
                                                        <label class="mb-1">Subtotal</label>
                                                        <div class="pt-2 font-weight-bold row-subtotal">
                                                            {{ FormatMoney($item->subtotal) }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 text-right">
                                                    <label class="mb-1 d-block">&nbsp;</label>
                                                    <button type="button" class="btn btn-sm btn-danger remove-item">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <input type="text" name="items[description][]"
                                                        class="form-control form-control-sm"
                                                        placeholder="Catatan batch (opsional)"
                                                        value="{{ $item->description }}">
                                                    <small class="form-text text-muted">
                                                        Batch {{ $item->batch_code }} — terpakai
                                                        {{ FormatQty($item->usedQty()) }} dari
                                                        {{ FormatQty($item->quantity) }}
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <button type="button" class="btn btn-sm btn-success mt-2" id="add-item">
                                    <i class="fas fa-plus"></i> Tambah Bahan Baku
                                </button>

                                <div class="text-right mt-3">
                                    <h5 class="m-0">Total: <span id="grand-total">0</span></h5>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('purchase.index') }}" class="btn btn-secondary">Batal</a>
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

        function bahanBakuSelectHtml(selectedId) {
            var options = '<option value="">-- Pilih Bahan Baku --</option>';
            bahanBakuOptions.forEach(function (b) {
                var sel = (b.id == selectedId) ? ' selected' : '';
                options += '<option value="' + b.id + '"' + sel + '>' + b.name + ' (' + (b.code || '-') + ')' +
                    (b.satuan ? ' - ' + b.satuan : '') + ' | stok: ' + b.stock + '</option>';
            });
            return options;
        }

        function formatNumber(value) {
            return parseFloat(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
        }

        function updateGrandTotal() {
            var total = 0;
            $('.purchase-item').each(function () {
                var qty = parseFloat($(this).find('input[name="items[quantity][]"]').val() || 0);
                var price = parseFloat($(this).find('input[name="items[unit_price][]"]').val() || 0);
                total += qty * price;
            });
            $('#grand-total').text(formatNumber(total));
        }

        function updateRow(row) {
            var bahan = bahanBakuById(row.find('select[name="items[bahan_baku_id][]"]').val());
            var qty = parseFloat(row.find('input[name="items[quantity][]"]').val() || 0);
            var price = parseFloat(row.find('input[name="items[unit_price][]"]').val() || 0);

            row.find('.satuan-badge').text(bahan && bahan.satuan ? bahan.satuan : '-');
            row.find('.row-subtotal').text(formatNumber(qty * price));
        }

        function addItemRow() {
            var row = $('<div class="purchase-item border rounded p-2 mb-2"></div>');
            row.append(
                '<div class="row">' +
                    '<div class="col-md-4">' +
                        '<div class="form-group mb-1"><label class="mb-1">Bahan Baku</label>' +
                        '<select name="items[bahan_baku_id][]" class="form-control">' + bahanBakuSelectHtml() + '</select></div>' +
                    '</div>' +
                    '<div class="col-md-2">' +
                        '<div class="form-group mb-1"><label class="mb-1">Quantity</label>' +
                        '<div class="input-group">' +
                            '<input type="number" step="0.001" min="0" name="items[quantity][]" class="form-control" placeholder="0">' +
                            '<div class="input-group-append"><span class="input-group-text satuan-badge">-</span></div>' +
                        '</div></div>' +
                    '</div>' +
                    '<div class="col-md-2">' +
                        '<div class="form-group mb-1"><label class="mb-1">Harga Beli / Satuan</label>' +
                        '<input type="number" step="0.01" min="0" name="items[unit_price][]" class="form-control" placeholder="0"></div>' +
                    '</div>' +
                    '<div class="col-md-2">' +
                        '<div class="form-group mb-1"><label class="mb-1">Kedaluwarsa</label>' +
                        '<input type="date" name="items[expired_date][]" class="form-control"></div>' +
                    '</div>' +
                    '<div class="col-md-1">' +
                        '<div class="form-group mb-1"><label class="mb-1">Subtotal</label>' +
                        '<div class="pt-2 font-weight-bold row-subtotal">0</div></div>' +
                    '</div>' +
                    '<div class="col-md-1 text-right">' +
                        '<label class="mb-1 d-block">&nbsp;</label>' +
                        '<button type="button" class="btn btn-sm btn-danger remove-item"><i class="fas fa-times"></i></button>' +
                    '</div>' +
                '</div>' +
                '<div class="row">' +
                    '<div class="col-12">' +
                        '<input type="text" name="items[description][]" class="form-control form-control-sm" placeholder="Catatan batch (opsional)">' +
                    '</div>' +
                '</div>'
            );
            $('#item-rows').append(row);
            updateRow(row);
            updateGrandTotal();
        }

        $(document).ready(function () {
            $('#add-item').on('click', function () {
                addItemRow();
            });

            $(document).on('click', '.remove-item', function () {
                $(this).closest('.purchase-item').remove();
                updateGrandTotal();
            });

            $(document).on('change', '.purchase-item select[name="items[bahan_baku_id][]"]', function () {
                updateRow($(this).closest('.purchase-item'));
            });

            $(document).on('input', '.purchase-item input[name="items[quantity][]"], .purchase-item input[name="items[unit_price][]"]', function () {
                updateRow($(this).closest('.purchase-item'));
            });

            // baris batch yang sudah ada (mode edit): hitung ulang subtotal
            $('.purchase-item').each(function () {
                updateRow($(this));
            });

            updateGrandTotal();
        });
    </script>
@endpush
