@extends('layouts.app')
@section('title', 'Kasir Baru')
@push('style')
    <style>
        .menu-thumb {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
    </style>
@endpush
@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Kasir / Transaksi Baru</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('sale.index') }}">Penjualan</a></div>
                    <div class="breadcrumb-item">Baru</div>
                </div>
            </div>
            <div class="section-body">
                <form action="{{ route('sale.store') }}" method="POST" id="sale-form">
                    @csrf
                    <div class="row">
                        <div class="col-md-7">
                            <div class="card">
                                <div class="card-header"><h4>Pilih Menu</h4></div>
                                <div class="card-body">
                                    <input type="text" id="menu-search" class="form-control mb-3" placeholder="Cari menu...">
                                    <div class="row" id="menu-grid">
                                        @foreach ($menus as $menu)
                                            @php
                                                $info = $stockInfo[$menu->id] ?? ['max_qty' => null, 'expired_batches' => 0, 'recipes' => []];
                                                $maxQty = $info['max_qty'];
                                                $expiredBatches = $info['expired_batches'];
                                            @endphp
                                            <div class="col-md-6 menu-card" data-name="{{ strtolower($menu->name) }}">
                                                <div class="card card-primary">
                                                    <img src="{{ MenuImageUrl($menu) }}" class="card-img-top menu-thumb"
                                                        alt="{{ $menu->name }}">
                                                    <div class="card-body p-3">
                                                        <strong>{{ $menu->name }}</strong><br>
                                                        <small class="text-muted">{{ $menu->category ? $menu->category->name : '-' }}</small><br>
                                                        <span class="badge badge-success">Rp {{ number_format($menu->price, 0, ',', '.') }}</span>
                                                        @if ($menu->bahanBakus->isEmpty())
                                                            <span class="badge badge-secondary">Tanpa resep</span>
                                                        @elseif ($maxQty !== null && $maxQty <= 0)
                                                            <span class="badge badge-danger">Stok habis</span>
                                                        @elseif ($maxQty !== null)
                                                            <span class="badge badge-info">Sisa {{ (int) $maxQty }} porsi</span>
                                                        @endif
                                                        @if ($expiredBatches > 0)
                                                            <span class="badge badge-warning">{{ $expiredBatches }} batch kedaluwarsa</span>
                                                        @endif
                                                        <div class="mt-1"><small class="text-muted">@foreach ($menu->bahanBakus as $bb){{ $bb->name }}: {{ $bb->pivot->quantity }} {{ $bb->satuan ? $bb->satuan->name : '' }}{{ !$loop->last ? ',' : '' }}@endforeach</small></div>
                                                        <button type="button" class="btn btn-sm btn-primary mt-2 btn-add-menu" data-id="{{ $menu->id }}" data-name="{{ $menu->name }}" data-price="{{ $menu->price }}" data-image="{{ MenuImageUrl($menu) }}" data-max="{{ $maxQty === null ? '' : (int) $maxQty }}" {{ ($maxQty !== null && $maxQty <= 0) ? 'disabled' : '' }}>+ Tambah</button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-body" id="cart-box">
                                    <div class="form-group">
                                        <label>Tanggal</label>
                                        <input type="date" name="sale_date" class="form-control" value="{{ old('sale_date', date('Y-m-d')) }}">
                                    </div>
                                    <table class="table table-sm" id="cart-table">
                                        <thead><tr><th>Menu</th><th width="90">Qty</th><th>Subtotal</th><th></th></tr></thead>
                                        <tbody id="cart-body"><tr id="cart-empty"><td colspan="4" class="text-center text-muted">Belum ada item</td></tr></tbody>
                                    </table>
                                    <div class="form-group"><label>Diskon (Rp)</label><input type="number" name="discount" id="discount" class="form-control" value="0" min="0"></div>
                                    <div class="form-group"><label>Pajak (Rp)</label><input type="number" name="tax" id="tax" class="form-control" value="0" min="0"></div>
                                    <div class="form-group"><label>Metode Bayar</label>
                                        <select name="payment_method" class="form-control"><option value="cash">Cash</option><option value="qris">QRIS</option><option value="transfer">Transfer</option><option value="debit">Debit</option></select>
                                    </div>
                                    <div class="form-group"><label>Bayar (Rp) <span class="text-danger">*</span></label><input type="number" name="paid" id="paid" class="form-control" value="0" min="0" required></div>
                                    <div class="form-group"><label>Catatan</label><input type="text" name="description" class="form-control" placeholder="Opsional"></div>
                                    <h5>Subtotal: <span id="label-subtotal">Rp 0</span></h5>
                                    <h4>Total: <span id="label-total">Rp 0</span></h4>
                                    <h5>Kembali: <span id="label-change">Rp 0</span></h5>
                                    <div class="form-check mt-2 mb-2">
                                        <input type="checkbox" name="print_receipt" value="1" id="print_receipt"
                                            class="form-check-input" checked>
                                        <label class="form-check-label" for="print_receipt">
                                            Cetak struk (printer thermal) setelah simpan
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-block mt-3">Simpan Transaksi</button>
                                    <a href="{{ route('sale.index') }}" class="btn btn-secondary btn-block">Batal</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    let cart = {};
    function fmt(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }
    function recalc() {
        let subtotal = 0, html = '', count = 0;
        Object.values(cart).forEach(function (it) {
            let st = it.price * it.qty; subtotal += st; count++;
            html += '<tr><td>'
                + '<img src="' + it.image + '" width="32" height="32"'
                + ' style="object-fit:cover;border-radius:4px;margin-right:6px;">'
                + it.name + '<br><small class="text-muted">Rp ' + Number(it.price).toLocaleString('id-ID') + '</small>'
                + '<input type="hidden" name="menu_id[]" value="' + it.id + '">'
                + '<input type="hidden" name="quantity[]" value="' + it.qty + '"></td>'
                + '<td><input type="number" class="form-control form-control-sm cart-qty" data-id="' + it.id + '" value="' + it.qty + '" min="1"></td>'
                + '<td>' + fmt(st) + '</td>'
                + '<td><button type="button" class="btn btn-sm btn-danger cart-del" data-id="' + it.id + '">x</button></td></tr>';
        });
        if (!count) html = '<tr id="cart-empty"><td colspan="4" class="text-center text-muted">Belum ada item</td></tr>';
        $('#cart-body').html(html);
        let disc = Number($('#discount').val() || 0), tax = Number($('#tax').val() || 0), paid = Number($('#paid').val() || 0);
        let total = Math.max(0, subtotal - disc + tax);
        $('#label-subtotal').text(fmt(subtotal)); $('#label-total').text(fmt(total)); $('#label-change').text(fmt(paid - total));
    }
    $(document).on('click', '.btn-add-menu', function () {
        let id = $(this).data('id'), max = $(this).data('max');
        if (!cart[id]) cart[id] = { id: id, name: $(this).data('name'), price: Number($(this).data('price')), image: $(this).data('image'), qty: 0 };
        if (max !== '' && cart[id].qty + 1 > Number(max)) { alert('Stok bahan tidak cukup untuk menambah porsi ini.'); return; }
        cart[id].qty++; recalc();
    });
    $(document).on('change', '.cart-qty', function () { let id = $(this).data('id'); cart[id].qty = Math.max(1, Number($(this).val() || 1)); recalc(); });
    $(document).on('click', '.cart-del', function () { delete cart[$(this).data('id')]; recalc(); });
    $(document).on('input', '#discount,#tax,#paid', recalc);
    $('#menu-search').on('input', function () { let q = $(this).val().toLowerCase(); $('.menu-card').each(function () { $(this).toggle($(this).data('name').indexOf(q) !== -1); }); });
    $('#sale-form').on('submit', function (e) { if (!Object.keys(cart).length) { e.preventDefault(); alert('Keranjang masih kosong.'); } });
    recalc();
});
</script>
@endpush
