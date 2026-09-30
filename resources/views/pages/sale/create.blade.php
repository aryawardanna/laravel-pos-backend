@extends('layouts.app')

@section('title', 'Transaksi')

@push('style')
{{-- Layar kasir dirancang seperti aplikasi mobile (app bar "TRANSAKSI",
     chip kategori, daftar menu, dan keranjang sebagai bottom sheet).
     Footer template disembunyikan supaya layar kasir terasa penuh. --}}
<script>
    document.documentElement.classList.add('kasir-mode');
</script>
<style>
/* =====================================================================
   Kasir / Transaksi — tampilan ala aplikasi mobile
   ===================================================================== */

html.kasir-mode .main-footer {
    display: none;
}

.kasir-page {
    --ks-green: #12a06a;
    --ks-green-dark: #0d7d52;
    --ks-green-soft: #d8f4e6;
    --ks-orange: #f5a623;
    --ks-ink: #12202e;
    --ks-muted: #8d9aa8;
    --ks-line: #e8edf2;
    --ks-bg: #f7f9fb;
    padding-bottom: 24px;
}
.kasir-page .section > *:first-child {
    margin-top: 0;
}

/* --- Bingkai layar kasir -------------------------------------------
   Layar kecil : tampil seperti aplikasi ponsel (satu kolom, keranjang
                 sebagai bottom sheet).
   Layar besar : lebar penuh halaman, dua kolom — katalog menu di kiri
                 dan panel pesanan yang selalu terlihat di kanan.
   ------------------------------------------------------------------ */
.kasir-frame {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 560px;
    height: calc(100vh - 104px);
    height: calc(100dvh - 104px);
    min-height: 0;
    margin: 0 auto;
    background: #fff;
    border: 1px solid #e6ebf1;
    border-radius: 26px;
    box-shadow: 0 18px 45px rgba(18, 32, 46, .16);
    overflow: hidden;
}

/* --- Isi layar: katalog menu + keranjang --------------------------- */
.kasir-body {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.kasir-catalog {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

/* --- App bar ------------------------------------------------------- */
.kasir-appbar {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    padding: 8px 6px;
    background: #fff;
    border-bottom: 1px solid var(--ks-line);
}
.kasir-appbar-title {
    flex: 1 1 auto;
    margin: 0;
    text-align: center;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: .08em;
    color: #111c26;
}
.kasir-icon-btn {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #33475b;
    font-size: 16px;
    line-height: 1;
    cursor: pointer;
}
.kasir-icon-btn:hover,
.kasir-icon-btn:focus {
    background: #f1f5f9;
    color: var(--ks-green-dark);
    outline: none;
}

/* --- Baris judul: nama kategori + jumlah pesanan + favorit --------- */
.kasir-head {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 16px 6px;
}
.kasir-head-title {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 17px;
    font-weight: 700;
    color: #111c26;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kasir-pill-order {
    flex: 0 0 auto;
    padding: 9px 18px;
    border: 0;
    border-radius: 999px;
    background: var(--ks-orange);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    box-shadow: 0 6px 14px rgba(245, 166, 35, .35);
    transition: transform .15s ease;
    cursor: pointer;
}
.kasir-pill-order:active {
    transform: scale(.96);
}
.kasir-pill-order.is-empty {
    background: #c9d2db;
    box-shadow: none;
}
.kasir-pill-order.bump {
    animation: kasirBump .35s ease;
}
@keyframes kasirBump {
    0% { transform: scale(1); }
    40% { transform: scale(1.14); }
    100% { transform: scale(1); }
}
.kasir-star {
    flex: 0 0 auto;
    width: 40px;
    height: 40px;
    border: 0;
    border-radius: 50%;
    background: var(--ks-green);
    color: #fff;
    font-size: 15px;
    cursor: pointer;
    transition: box-shadow .15s ease;
}
.kasir-star.is-active {
    background: var(--ks-green-dark);
    box-shadow: 0 0 0 3px var(--ks-green-soft);
}
/* --- Toolbar ikon -------------------------------------------------- */
.kasir-tools {
    flex: 0 0 auto;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 2px;
    row-gap: 4px;
    /* padding kiri disamakan secara optis dengan judul & daftar (16px):
       tombol ikon 38px dengan ikon di tengah, jadi 8px + 11px ≈ 16px. */
    padding: 8px 12px 4px 8px;
}
.kasir-tool {
    flex: 0 0 auto;
    height: 38px;
    min-width: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 8px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: #40525f;
    font-size: 17px;
    line-height: 1;
    cursor: pointer;
}
.kasir-tool:hover,
.kasir-tool:focus {
    background: #f1f5f9;
    color: var(--ks-green-dark);
    outline: none;
}
.kasir-tool--tax {
    height: 28px;
    padding: 0 9px;
    border: 1.5px solid #40525f;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .08em;
}
.kasir-tool--tax:hover {
    border-color: var(--ks-green-dark);
}
.kasir-tool--ppob {
    font-family: Georgia, 'Times New Roman', serif;
    font-style: italic;
    font-weight: 700;
    font-size: 15px;
    color: var(--ks-green);
}

/* --- Kotak pencarian ----------------------------------------------- */
.kasir-search {
    flex: 0 0 auto;
    padding: 0 16px 8px;
}
.kasir-search .form-control {
    height: 40px;
    border: 1px solid var(--ks-line);
    border-radius: 10px;
    background: var(--ks-bg);
    font-size: 13px;
}
.kasir-search .form-control:focus {
    background: #fff;
    border-color: var(--ks-green);
    box-shadow: none;
}

/* --- Chip kategori ------------------------------------------------- */
.kasir-chips {
    flex: 0 0 auto;
    display: flex;
    gap: 8px;
    padding: 2px 16px 12px;
    overflow-x: auto;
    scrollbar-width: none;
}
.kasir-chips::-webkit-scrollbar {
    display: none;
}
.kasir-chip {
    flex: 0 0 auto;
    padding: 8px 18px;
    border: 1px solid #e1e7ee;
    border-radius: 12px;
    background: #f2f4f7;
    color: #5b6b7a;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
}
.kasir-chip.is-active {
    background: var(--ks-green-soft);
    border-color: var(--ks-green);
    color: var(--ks-green-dark);
}

/* --- Daftar menu --------------------------------------------------- */
.kasir-list {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: 0 16px 96px;
}
.kasir-item {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    padding: 10px 6px;
    margin: 0 -6px;
    border-bottom: 1px solid #f1f4f8;
    border-radius: 12px;
    cursor: pointer;
}
.kasir-item:last-child {
    border-bottom: 0;
}
.kasir-item.is-out {
    opacity: .5;
    cursor: not-allowed;
}
.kasir-item.is-highlight {
    background: var(--ks-green-soft);
}
.kasir-thumb {
    flex: 0 0 auto;
    width: 58px;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 12px;
    background: #eef1f5;
}
.kasir-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.kasir-thumb span {
    font-size: 14px;
    font-weight: 700;
    color: #9fb0c0;
}
.kasir-info {
    flex: 1 1 auto;
    min-width: 0;
}
.kasir-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--ks-ink);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kasir-meta {
    font-size: 12.5px;
    color: var(--ks-muted);
}
.kasir-meta-out {
    color: #d9534f;
    font-weight: 600;
}
.kasir-sep {
    margin: 0 3px;
}
.kasir-warn {
    margin-left: 4px;
    color: #f5a623;
}
.kasir-fav {
    flex: 0 0 auto;
    width: 28px;
    height: 28px;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #dbe3ea;
    font-size: 13px;
    cursor: pointer;
}
.kasir-fav:hover {
    background: #f1f5f9;
    color: #f5a623;
}
.kasir-fav.is-active {
    color: #f5a623;
}

/* --- Pengatur jumlah (angka di kanan baris menu) ------------------- */
.qty-step {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 4px;
}
.qty-btn {
    display: none;
    width: 26px;
    height: 26px;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid var(--ks-line);
    border-radius: 8px;
    background: #fff;
    color: #5b6b7a;
    font-size: 10px;
    cursor: pointer;
}
.qty-step.has-qty .qty-btn {
    display: inline-flex;
}
.qty-value {
    min-width: 40px;
    height: 34px;
    padding: 0 6px;
    border: 1px solid #e1e7ee;
    border-radius: 9px;
    background: #fff;
    color: var(--ks-ink);
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}
.qty-step.has-qty .qty-value {
    border-color: var(--ks-green);
    color: var(--ks-green-dark);
}
/* --- Keranjang (bottom sheet) -------------------------------------- */
.kasir-sheet {
    position: absolute;
    inset: 0;
    z-index: 20;
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease;
}
.kasir-sheet.is-open {
    opacity: 1;
    pointer-events: auto;
}
.kasir-sheet-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(12, 22, 33, .45);
}
.kasir-sheet-panel {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    min-height: 0;
    max-height: 94%;
    background: #fff;
    border-radius: 22px 22px 0 0;
    box-shadow: 0 -12px 30px rgba(18, 32, 46, .2);
    transform: translateY(100%);
    transition: transform .28s ease;
}
.kasir-sheet.is-open .kasir-sheet-panel {
    transform: translateY(0);
}
.kasir-sheet-head {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 14px 16px 10px;
    border-bottom: 1px solid var(--ks-line);
}
.kasir-sheet-title {
    font-size: 16px;
    font-weight: 700;
    color: #111c26;
}
.kasir-sheet-sub {
    font-size: 12px;
    color: var(--ks-muted);
}
.kasir-sheet-body {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: 6px 16px 12px;
}
.kasir-sheet-foot {
    flex: 0 0 auto;
    padding: 12px 16px 16px;
    border-top: 1px solid var(--ks-line);
    background: #fff;
}
.kasir-cart-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 0;
    border-bottom: 1px dashed #eef2f6;
}
.kasir-cart-row .kasir-thumb {
    width: 44px;
    height: 44px;
    border-radius: 10px;
}
.kasir-cart-right {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 8px;
}
.kasir-cart-right strong {
    font-size: 13px;
    color: #111c26;
    white-space: nowrap;
}
.kasir-cart-del {
    width: 26px;
    height: 26px;
    border: 0;
    border-radius: 8px;
    background: #fdeaea;
    color: #d9534f;
    font-size: 11px;
    cursor: pointer;
}
.kasir-cart-del:hover {
    background: #d9534f;
    color: #fff;
}
.kasir-cart-empty {
    padding: 18px 4px;
    text-align: center;
    font-size: 13px;
    color: var(--ks-muted);
}

/* --- Form pembayaran di dalam keranjang ---------------------------- */
.kasir-fields {
    padding-top: 8px;
}
.kasir-field {
    margin-bottom: 10px;
}
.kasir-field > label {
    display: block;
    margin-bottom: 4px;
    font-size: 12px;
    font-weight: 600;
    color: #5b6b7a;
}
.kasir-field .form-control {
    height: 40px;
    border-color: var(--ks-line);
    border-radius: 10px;
    font-size: 13px;
}
.kasir-field .form-control:focus {
    border-color: var(--ks-green);
    box-shadow: none;
}
.kasir-methods {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.kasir-method {
    flex: 1 1 0;
    min-width: 64px;
    padding: 8px 6px;
    border: 1px solid var(--ks-line);
    border-radius: 10px;
    background: var(--ks-bg);
    color: #5b6b7a;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.kasir-method.is-active {
    background: var(--ks-green-soft);
    border-color: var(--ks-green);
    color: var(--ks-green-dark);
}
.kasir-quick {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 6px;
}
.kasir-quick-btn {
    padding: 5px 10px;
    border: 1px solid var(--ks-line);
    border-radius: 8px;
    background: var(--ks-bg);
    color: #5b6b7a;
    font-size: 12px;
    cursor: pointer;
}
.kasir-quick-btn:hover {
    border-color: var(--ks-green);
    color: var(--ks-green-dark);
}
.kasir-check {
    margin: 4px 0 0;
    font-size: 13px;
    color: #5b6b7a;
}
.kasir-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    color: #5b6b7a;
}
.kasir-total--grand {
    margin: 2px 0;
    font-size: 15px;
}
.kasir-total--grand strong {
    font-size: 19px;
    color: var(--ks-green-dark);
}
.kasir-save {
    width: 100%;
    height: 48px;
    margin-top: 10px;
    border: 0;
    border-radius: 14px;
    background: var(--ks-green);
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
}
.kasir-save:hover {
    background: var(--ks-green-dark);
}
.kasir-save:disabled {
    background: #c9d2db;
    cursor: not-allowed;
}
/* --- Tombol scan mengapung ---------------------------------------- */
.kasir-fab {
    position: absolute;
    left: 16px;
    bottom: 18px;
    z-index: 15;
    width: 56px;
    height: 56px;
    border: 4px solid #fff;
    border-radius: 50%;
    background: #5f6b76;
    color: #fff;
    font-size: 18px;
    box-shadow: 0 8px 18px rgba(18, 32, 46, .28);
    cursor: pointer;
}
.kasir-fab:hover {
    background: var(--ks-green);
}

/* --- Keadaan kosong ------------------------------------------------ */
.kasir-empty {
    padding: 36px 12px;
    text-align: center;
    color: var(--ks-muted);
}
.kasir-empty i {
    display: block;
    margin-bottom: 8px;
    font-size: 26px;
    color: #c6d0da;
}
.kasir-empty p {
    margin: 0;
    font-size: 13px;
}

/* --- Layar ponsel sempit: layar kasir memakai seluruh lebar ponsel -- */
@media (max-width: 575.98px) {
    .kasir-page {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    .kasir-frame {
        height: calc(100vh - 104px);
        height: calc(100dvh - 104px);
        min-height: 0;
        border: 0;
        border-radius: 0;
        box-shadow: none;
    }
    .kasir-head-title {
        font-size: 16px;
    }
    .kasir-pill-order {
        padding: 8px 14px;
        font-size: 12px;
    }
}

/* --- Versi web / layar lebar (>= 992px) ----------------------------
   Layar kasir memakai seluruh lebar konten tanpa pembatas max-width,
   sehingga tidak ada sisa jarak kiri-kanan. Padding kiri dibiarkan
   mengikuti template (280px saat sidebar tampil, 90px saat sidebar-mini,
   30px di layar sempit) supaya bingkai tidak pernah menutupi sidebar. */
@media (min-width: 992px) {
    .kasir-page {
        padding-bottom: 0;
    }
    .kasir-frame {
        max-width: none;
        margin-left: 0;
        margin-right: 0;
        height: calc(100vh - 110px);
        height: calc(100dvh - 110px);
    }
}

/* Tablet landscape / desktop kecil: katalog menu memakai dua kolom. */
@media (min-width: 992px) and (max-width: 1199.98px) {
    .kasir-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        grid-auto-rows: max-content;
        align-content: start;
        column-gap: 28px;
    }
    .kasir-list > .kasir-empty {
        grid-column: 1 / -1;
    }
    .kasir-list > .kasir-item:last-child {
        border-bottom: 1px solid #f1f4f8;
    }
}

/* =====================================================================
   Versi web (layar >= 1200px)
   Panel pesanan berubah dari bottom sheet menjadi kolom tetap di kanan,
   jadi kasir bisa memilih menu dan mengisi pembayaran tanpa membuka apa
   pun; tombol scan mengapung digantikan ikon pada toolbar.
   Daftar katalog memakai grid otomatis agar lebar layar terpakai penuh.
   ===================================================================== */
@media (min-width: 1200px) {
    .kasir-frame {
        border-radius: 22px;
    }
    .kasir-body {
        flex-direction: row;
    }
    .kasir-catalog {
        flex: 1 1 auto;
        min-width: 0;
    }
    .kasir-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        grid-auto-rows: max-content;
        align-content: start;
        column-gap: 28px;
        padding-bottom: 20px;
    }
    .kasir-list > .kasir-empty {
        grid-column: 1 / -1;
    }
    .kasir-list > .kasir-item:last-child {
        border-bottom: 1px solid #f1f4f8;
    }
    .kasir-sheet {
        position: static;
        inset: auto;
        display: flex;
        flex: 0 0 392px;
        min-height: 0;
        opacity: 1;
        pointer-events: auto;
        transition: none;
        border-left: 1px solid var(--ks-line);
    }
    .kasir-sheet-backdrop {
        display: none;
    }
    .kasir-sheet-panel {
        position: static;
        flex: 1 1 auto;
        min-height: 0;
        max-height: none;
        border-radius: 0;
        box-shadow: none;
        transform: none;
        transition: none;
    }
    .kasir-sheet-close,
    .kasir-fab {
        display: none;
    }
}
</style>
@endpush

@section('main')
    <div class="main-content kasir-page">
        <section class="section">
            <form action="{{ route('sale.store') }}" method="POST" id="sale-form" autocomplete="off">
                @csrf
                <div class="kasir-frame">

                    {{-- App bar: kembali, judul, menu lain --}}
                    <div class="kasir-appbar">
                        <a href="{{ route('sale.index') }}" class="kasir-icon-btn" title="Kembali ke daftar transaksi">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <h1 class="kasir-appbar-title">TRANSAKSI</h1>
                        <div class="dropdown">
                            <button type="button" class="kasir-icon-btn" data-toggle="dropdown" title="Menu lainnya">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a href="{{ route('sale.index') }}" class="dropdown-item has-icon"><i class="fas fa-receipt"></i> Daftar Transaksi</a>
                                <a href="{{ route('sale.create') }}" class="dropdown-item has-icon"><i class="fas fa-sync-alt"></i> Transaksi Baru</a>
                                <button type="button" class="dropdown-item has-icon" id="btn-fullscreen"><i class="fas fa-expand"  style="margin-top: 5px;"></i> Layar Penuh</button>
                            </div>
                        </div>
                    </div>

                    {{-- Isi layar: katalog menu (kiri) + keranjang/pesanan (kanan) --}}
                    <div class="kasir-body">
                        <div class="kasir-catalog">

                            {{-- Nama kategori aktif + ringkasan pesanan + favorit --}}
                            <div class="kasir-head">
                                <div class="kasir-head-title" id="kasir-head-title">Semua Menu</div>
                                <button type="button" class="kasir-pill-order is-empty" id="btn-open-cart" title="Lihat keranjang &amp; bayar">
                                    <span id="order-count">0</span> Keranjang
                                </button>
                                <button type="button" class="kasir-star" id="btn-favorite" title="Tampilkan menu favorit">
                                    <i class="fas fa-star"></i>
                                </button>
                            </div>

                            {{-- Toolbar ikon --}}
                            <div class="kasir-tools">
                                <button type="button" class="kasir-tool" id="btn-search" title="Cari menu"><i class="fas fa-search"></i></button>
                                <button type="button" class="kasir-tool" id="btn-new-order" title="Pesanan baru"><i class="fas fa-plus"></i></button>
                                <button type="button" class="kasir-tool" id="btn-barcode" title="Input / scan kode menu"><i class="fas fa-barcode"></i></button>
                                <button type="button" class="kasir-tool" id="btn-scan" title="Scan kamera"><i class="fas fa-expand"></i></button>
                                <div class="dropdown">
                                    <button type="button" class="kasir-tool" data-toggle="dropdown" title="Pengaturan kasir"><i class="fas fa-cog"></i></button>
                                    <div class="dropdown-menu">
                                        <button type="button" class="dropdown-item has-icon" id="btn-toggle-tax"><i class="far fa-check-square"></i> Pajak: <span id="tax-state-label">Tampil</span></button>
                                        <button type="button" class="dropdown-item has-icon" id="btn-toggle-print"><i class="far fa-check-square"></i> Cetak struk: <span id="print-state-label">Otomatis</span></button>
                                        <button type="button" class="dropdown-item has-icon text-danger" id="btn-reset-cart"><i class="fas fa-trash-alt"></i> Kosongkan keranjang</button>
                                    </div>
                                </div>
                                <button type="button" class="kasir-tool kasir-tool--tax" id="btn-tax" title="Atur pajak">TAX</button>
                                <button type="button" class="kasir-tool kasir-tool--ppob" id="btn-ppob" title="PPOB">PPOB</button>
                            </div>

                            {{-- Pencarian (disembunyikan, dibuka lewat ikon kaca pembesar) --}}
                            <div class="kasir-search d-none" id="kasir-search-box">
                                <input type="text" id="menu-search" class="form-control" placeholder="Cari nama / kode menu...">
                            </div>

                            {{-- Chip kategori --}}
                            <div class="kasir-chips" id="kasir-chips">
                                <button type="button" class="kasir-chip is-active" data-category="all" data-label="Semua Menu">Semua</button>
                                @foreach ($categories as $category)
                                    <button type="button" class="kasir-chip" data-category="{{ $category->id }}" data-label="{{ $category->name }}">{{ $category->name }}</button>
                                @endforeach
                            </div>
                            {{-- Daftar menu: ketuk baris untuk menambah 1 porsi --}}
                            <div class="kasir-list" id="kasir-list">
                                @forelse ($menus as $menu)
                                    @php
                                        $info = $stockInfo[$menu->id] ?? ['max_qty' => null, 'expired_batches' => 0];
                                        $maxQty = $info['max_qty'];
                                        $expiredBatches = $info['expired_batches'];
                                        $isOut = $maxQty !== null && $maxQty <= 0;
                                    @endphp
                                    <div class="kasir-item{{ $isOut ? ' is-out' : '' }}"
                                        data-id="{{ $menu->id }}"
                                        data-label="{{ $menu->name }}"
                                        data-name="{{ strtolower($menu->name) }}"
                                        data-code="{{ strtolower($menu->code ?? '') }}"
                                        data-category="{{ $menu->category_id }}"
                                        data-price="{{ (float) $menu->price }}"
                                        data-image="{{ MenuImageUrl($menu) }}"
                                        data-max="{{ $maxQty === null ? '' : (int) $maxQty }}"
                                        data-out="{{ $isOut ? 1 : 0 }}">
                                        <div class="kasir-thumb">
                                            @if ($menu->image)
                                                <img src="{{ MenuImageUrl($menu) }}" alt="{{ $menu->name }}">
                                            @else
                                                <span>{{ mb_strtoupper(mb_substr($menu->name, 0, 2)) }}</span>
                                            @endif
                                        </div>
                                        <div class="kasir-info">
                                            <div class="kasir-name">{{ $menu->name }}</div>
                                            <div class="kasir-meta">
                                                @if ($menu->bahanBakus->isEmpty())
                                                    Tanpa resep
                                                @elseif ($isOut)
                                                    <span class="kasir-meta-out">Stok habis</span>
                                                @else
                                                    Sisa {{ (int) $maxQty }}
                                                @endif
                                                <span class="kasir-sep">&middot;</span>
                                                Rp {{ FormatMoney($menu->price, 0) }}
                                                @if ($expiredBatches > 0)
                                                    <i class="fas fa-exclamation-triangle kasir-warn" title="{{ $expiredBatches }} batch kedaluwarsa tidak ikut dipakai"></i>
                                                @endif
                                            </div>
                                        </div>
                                        <button type="button" class="kasir-fav" title="Tandai favorit"><i class="fas fa-star"></i></button>
                                        <div class="qty-step">
                                            <button type="button" class="qty-btn qty-minus" title="Kurangi 1 porsi"><i class="fas fa-minus"></i></button>
                                            <button type="button" class="qty-value" title="Tambah 1 porsi">0</button>
                                            <button type="button" class="qty-btn qty-plus" title="Tambah 1 porsi"><i class="fas fa-plus"></i></button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="kasir-empty">
                                        <i class="fas fa-utensils"></i>
                                        <p>Belum ada menu aktif yang bisa dijual.</p>
                                    </div>
                                @endforelse
                                <div class="kasir-empty d-none" id="kasir-empty">
                                    <i class="fas fa-search"></i>
                                    <p>Menu tidak ditemukan</p>
                                </div>
                            </div>
                        </div>

                        {{-- Keranjang + pembayaran: bottom sheet di mobile,
                             panel tetap di kolom kanan pada versi web --}}
                        <div class="kasir-sheet" id="cart-sheet">
                            <div class="kasir-sheet-backdrop" data-close-cart></div>
                            <div class="kasir-sheet-panel">
                                <div class="kasir-sheet-head">
                                    <div>
                                        <div class="kasir-sheet-title">Pesanan</div>
                                        <div class="kasir-sheet-sub"><span id="sheet-count">0</span> porsi &middot; <span id="sheet-items">0</span> menu</div>
                                    </div>
                                    <button type="button" class="kasir-icon-btn kasir-sheet-close" data-close-cart title="Tutup">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>

                                <div class="kasir-sheet-body">
                                    <div id="cart-body">
                                        <div class="kasir-cart-empty">Belum ada item. Pilih menu untuk menambah.</div>
                                    </div>

                                    <div class="kasir-fields">
                                        <div class="kasir-field">
                                            <label>Tanggal Transaksi</label>
                                            <input type="date" name="sale_date" class="form-control" value="{{ old('sale_date', $saleDate) }}">
                                        </div>
                                        <div class="kasir-field">
                                            <label>Metode Bayar</label>
                                            <div class="kasir-methods" id="pay-methods">
                                                @foreach (['cash' => 'Cash', 'qris' => 'QRIS', 'transfer' => 'Transfer', 'debit' => 'Debit'] as $value => $label)
                                                    <button type="button" class="kasir-method{{ old('payment_method', 'cash') === $value ? ' is-active' : '' }}" data-value="{{ $value }}">{{ $label }}</button>
                                                @endforeach
                                            </div>
                                            <input type="hidden" name="payment_method" id="payment_method" value="{{ old('payment_method', 'cash') }}">
                                        </div>
                                        <div class="kasir-field">
                                            <label>Bayar (Rp)</label>
                                            <input type="number" name="paid" id="paid" class="form-control" min="0" step="any" value="{{ old('paid', 0) }}" required>
                                            <div class="kasir-quick">
                                                <button type="button" class="kasir-quick-btn" data-exact="1">Uang Pas</button>
                                                <button type="button" class="kasir-quick-btn" data-pay="50000">50.000</button>
                                                <button type="button" class="kasir-quick-btn" data-pay="100000">100.000</button>
                                                <button type="button" class="kasir-quick-btn" data-pay="200000">200.000</button>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="kasir-field">
                                                    <label>Diskon (Rp)</label>
                                                    <input type="number" name="discount" id="discount" class="form-control" min="0" step="any" value="{{ old('discount', 0) }}">
                                                </div>
                                            </div>
                                            <div class="col-6" id="tax-wrap">
                                                <div class="kasir-field">
                                                    <label>Pajak (Rp)</label>
                                                    <input type="number" name="tax" id="tax" class="form-control" min="0" step="any" value="{{ old('tax', 0) }}">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="kasir-field">
                                            <label>Catatan</label>
                                            <input type="text" name="description" class="form-control" placeholder="Opsional" value="{{ old('description') }}">
                                        </div>
                                        <div class="form-check kasir-check">
                                            <input type="checkbox" name="print_receipt" value="1" id="print_receipt" class="form-check-input" checked>
                                            <label class="form-check-label" for="print_receipt">Cetak struk setelah simpan</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="kasir-sheet-foot">
                                    <div class="kasir-total"><span>Subtotal</span><span id="label-subtotal">Rp 0</span></div>
                                    <div class="kasir-total kasir-total--grand"><span>Total</span><strong id="label-total">Rp 0</strong></div>
                                    <div class="kasir-total"><span>Kembali</span><span id="label-change">Rp 0</span></div>
                                    <button type="submit" class="kasir-save" id="btn-save" disabled>Simpan Transaksi</button>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- Tombol scan mengapung --}}
                    <button type="button" class="kasir-fab" id="btn-fab-scan" title="Scan kamera">
                        <i class="fas fa-camera"></i>
                    </button>


                </div>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
<script>
$(function () {
    /* =================================================================
       Kasir / Transaksi
       -----------------------------------------------------------------
       Keranjang disimpan di objek `cart` (client side); harga, nama, dan
       gambar diambil dari data-attribute baris menu sehingga kasir tidak
       perlu request tambahan. Server tetap menghitung ulang total saat
       transaksi disimpan (SaleController::store).
       ================================================================= */

    const cart = {};
    const FAV_KEY = 'kasir.favorite.menus';
    let favorites = readFavorites();
    let activeCategory = 'all';
    let favoriteOnly = false;

    function readFavorites() {
        try {
            const raw = window.localStorage.getItem(FAV_KEY);
            const list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list.map(String) : [];
        } catch (e) {
            return [];
        }
    }

    function saveFavorites() {
        try {
            window.localStorage.setItem(FAV_KEY, JSON.stringify(favorites));
        } catch (e) {
            /* localStorage bisa diblokir browser; favorit cukup untuk sesi ini */
        }
    }

    function fmt(value) {
        return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
    }

    function esc(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function notify(message, title) {
        if (window.iziToast) {
            iziToast.warning({ title: title || 'Perhatian', message: message, position: 'topRight' });
        } else {
            alert(message);
        }
    }

    function info(message, title) {
        if (window.iziToast) {
            iziToast.info({ title: title || 'Info', message: message, position: 'topRight' });
        }
    }

    /* --- Keranjang -------------------------------------------------- */
    function subtotalValue() {
        return Object.values(cart).reduce(function (total, item) {
            return total + item.price * item.qty;
        }, 0);
    }

    function totalValue() {
        const discount = Number($('#discount').val() || 0);
        const tax = Number($('#tax').val() || 0);

        return Math.max(0, subtotalValue() - discount + tax);
    }

    function updateTotals() {
        const paid = Number($('#paid').val() || 0);
        $('#label-subtotal').text(fmt(subtotalValue()));
        $('#label-total').text(fmt(totalValue()));
        $('#label-change').text(fmt(paid - totalValue()));
    }

    function renderCart() {
        let porsi = 0;
        let jumlahMenu = 0;
        let html = '';

        Object.values(cart).forEach(function (item) {
            if (item.qty <= 0) {
                return;
            }

            porsi += item.qty;
            jumlahMenu += 1;

            html += '<div class="kasir-cart-row">'
                + '<div class="kasir-thumb"><img src="' + esc(item.image) + '" alt=""></div>'
                + '<div class="kasir-info">'
                + '<div class="kasir-name">' + esc(item.name) + '</div>'
                + '<div class="kasir-meta">' + fmt(item.price) + ' &times; ' + item.qty + '</div>'
                + '</div>'
                + '<div class="kasir-cart-right">'
                + '<strong>' + fmt(item.price * item.qty) + '</strong>'
                + '<button type="button" class="kasir-cart-del" data-id="' + esc(item.id) + '" title="Hapus item"><i class="fas fa-times"></i></button>'
                + '</div>'
                + '<input type="hidden" name="menu_id[]" value="' + esc(item.id) + '">'
                + '<input type="hidden" name="quantity[]" value="' + item.qty + '">'
                + '</div>';
        });

        if (! jumlahMenu) {
            html = '<div class="kasir-cart-empty">Belum ada item. Pilih menu untuk menambah.</div>';
        }

        $('#cart-body').html(html);
        $('#order-count').text(porsi);
        $('#sheet-count').text(porsi);
        $('#sheet-items').text(jumlahMenu);
        $('#btn-open-cart').toggleClass('is-empty', porsi === 0);
        $('#btn-save').prop('disabled', porsi === 0);

        // Angka di kanan tiap baris menu = jumlah porsi di keranjang.
        $('.kasir-item').each(function () {
            const $item = $(this);
            const item = cart[String($item.data('id'))];
            const qty = item ? item.qty : 0;

            $item.find('.qty-value').text(qty);
            $item.find('.qty-step').toggleClass('has-qty', qty > 0);
        });

        updateTotals();
    }

    function bumpOrderPill() {
        const $pill = $('#btn-open-cart').removeClass('bump');

        window.setTimeout(function () { $pill.addClass('bump'); }, 10);
        window.setTimeout(function () { $pill.removeClass('bump'); }, 400);
    }

    /* --- Gulir panel pesanan ke baris yang baru ditambah (versi web) -- */
    function revealCartRow(id) {
        if (! window.matchMedia('(min-width: 1200px)').matches) {
            return;
        }

        const $row = $('#cart-body .kasir-cart-del[data-id="' + id + '"]').closest('.kasir-cart-row');

        if ($row.length && $row[0].scrollIntoView) {
            $row[0].scrollIntoView({ block: 'nearest' });
        }
    }

    /* --- Ubah jumlah porsi ----------------------------------------- */
    function changeQty($item, delta) {
        const id = String($item.data('id'));
        const label = $item.data('label');
        const max = $item.data('max');
        const isOut = Number($item.data('out')) === 1;
        const current = cart[id] ? cart[id].qty : 0;
        const next = current + delta;

        if (delta > 0 && isOut) {
            notify('Stok bahan untuk "' + label + '" tidak mencukupi.');
            return;
        }

        if (delta > 0 && max !== '' && max !== null && max !== undefined && next > Number(max)) {
            notify('Sisa porsi "' + label + '" tinggal ' + max + '. Tidak bisa ditambah lagi.');
            return;
        }

        if (next <= 0) {
            delete cart[id];
        } else {
            cart[id] = {
                id: id,
                name: label,
                price: Number($item.data('price') || 0),
                image: $item.data('image'),
                qty: next
            };
        }

        if (delta > 0) {
            $item.addClass('is-highlight');
            window.setTimeout(function () { $item.removeClass('is-highlight'); }, 260);
            bumpOrderPill();
        }

        renderCart();

        if (delta > 0) {
            revealCartRow(id);
        }
    }

    /* --- Filter kategori, pencarian, dan favorit -------------------- */
    function applyFilter() {
        const keyword = String($('#menu-search').val() || '').trim().toLowerCase();
        const adaMenu = $('.kasir-item').length > 0;
        let visible = 0;

        $('.kasir-item').each(function () {
            const $item = $(this);
            const id = String($item.data('id'));
            const category = String($item.data('category'));
            const haystack = String($item.data('name')) + ' ' + String($item.data('code'));

            let ok = activeCategory === 'all' || category === activeCategory;

            if (ok && keyword !== '') {
                ok = haystack.indexOf(keyword) !== -1;
            }
            if (ok && favoriteOnly) {
                ok = favorites.indexOf(id) !== -1;
            }

            $item.toggle(ok);

            if (ok) {
                visible += 1;
            }
        });

        $('#kasir-empty').toggleClass('d-none', ! adaMenu || visible > 0);
    }

    function headTitle() {
        const label = $('#kasir-chips .kasir-chip.is-active').data('label');

        return favoriteOnly ? 'Menu Favorit' : (label || 'Semua Menu');
    }

    function paintFavorites() {
        $('.kasir-fav').each(function () {
            const $star = $(this);
            const on = favorites.indexOf(String($star.data('id'))) !== -1;

            $star.toggleClass('is-active', on);
            $star.attr('title', on ? 'Hapus dari favorit' : 'Tandai favorit');
        });

        $('#btn-favorite').toggleClass('is-active', favoriteOnly);
    }
    /* --- Aksi pada daftar menu -------------------------------------- */
    // Ketuk baris menu = tambah 1 porsi (angka di kanan ikut naik).
    $(document).on('click', '.kasir-item', function (e) {
        if ($(e.target).closest('.kasir-fav, .qty-btn, .qty-value').length) {
            return;
        }

        changeQty($(this), 1);
    });
    $(document).on('click', '.qty-value, .qty-plus', function () {
        changeQty($(this).closest('.kasir-item'), 1);
    });
    $(document).on('click', '.qty-minus', function () {
        changeQty($(this).closest('.kasir-item'), -1);
    });

    /* --- Favorit ---------------------------------------------------- */
    $(document).on('click', '.kasir-fav', function (e) {
        e.stopPropagation();

        const id = String($(this).data('id'));
        const index = favorites.indexOf(id);

        if (index === -1) {
            favorites.push(id);
        } else {
            favorites.splice(index, 1);
        }

        saveFavorites();
        paintFavorites();
        applyFilter();
    });

    $('#btn-favorite').on('click', function () {
        favoriteOnly = ! favoriteOnly;
        $('#kasir-head-title').text(headTitle());
        paintFavorites();
        applyFilter();
        info(favoriteOnly ? 'Hanya menampilkan menu favorit.' : 'Menampilkan semua menu.');
    });

    /* --- Chip kategori --------------------------------------------- */
    $('#kasir-chips').on('click', '.kasir-chip', function () {
        const $chip = $(this);

        $('#kasir-chips .kasir-chip').removeClass('is-active');
        $chip.addClass('is-active');
        activeCategory = String($chip.data('category'));
        $('#kasir-head-title').text(headTitle());
        applyFilter();
    });

    /* --- Pencarian nama / kode menu --------------------------------- */
    $('#btn-search').on('click', function () {
        const $box = $('#kasir-search-box');
        $box.toggleClass('d-none');

        if ($box.hasClass('d-none')) {
            $('#menu-search').val('');
            applyFilter();
        } else {
            $('#menu-search').trigger('focus');
        }
    });

    $('#menu-search').on('input', applyFilter).on('keydown', function (e) {
        if (e.key !== 'Enter' && e.keyCode !== 13) {
            return;
        }

        e.preventDefault();

        // Alur barcode scanner: satu hasil saja -> langsung masuk keranjang.
        const $visible = $('.kasir-item:visible');
        if ($visible.length === 1) {
            changeQty($visible.first(), 1);
            $(this).val('');
            applyFilter();
        }
    });

    $('#btn-barcode').on('click', function () {
        $('#kasir-search-box').removeClass('d-none');
        $('#menu-search').trigger('focus');
        info('Scan barcode / ketik kode menu lalu tekan Enter. Satu hasil langsung masuk keranjang.');
    });
    /* --- Keranjang (bottom sheet) ----------------------------------- */
    function openCart() {
        $('#cart-sheet').addClass('is-open');
    }

    function closeCart() {
        $('#cart-sheet').removeClass('is-open');
    }

    $('#btn-open-cart').on('click', function () {
        openCart();

        // Di layar web panel pesanan sudah tampil, jadi pil "N Pesanan"
        // dipakai untuk langsung lompat ke kolom pembayaran.
        if (window.matchMedia('(min-width: 1200px)').matches) {
            $('#paid').trigger('focus').select();
        }
    });
    $('[data-close-cart]').on('click', closeCart);
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            closeCart();
        }
    });

    $(document).on('click', '.kasir-cart-del', function () {
        delete cart[String($(this).data('id'))];
        renderCart();
    });

    /* --- Pembayaran ------------------------------------------------- */
    $(document).on('input change', '#discount, #tax, #paid', updateTotals);

    $('#pay-methods').on('click', '.kasir-method', function () {
        const $btn = $(this);

        $('#pay-methods .kasir-method').removeClass('is-active');
        $btn.addClass('is-active');
        $('#payment_method').val(String($btn.data('value')));
    });

    $('.kasir-quick-btn').on('click', function () {
        const $btn = $(this);

        $('#paid').val($btn.data('exact') ? totalValue() : Number($btn.data('pay')));
        updateTotals();
    });
    /* --- Toolbar ---------------------------------------------------- */
    function resetCart() {
        if (! Object.keys(cart).length) {
            info('Keranjang masih kosong.');
            return;
        }

        if (! window.confirm('Kosongkan keranjang dan mulai pesanan baru?')) {
            return;
        }

        Object.keys(cart).forEach(function (id) { delete cart[id]; });
        renderCart();
    }

    $('#btn-new-order, #btn-reset-cart').on('click', resetCart);

    $('#btn-tax').on('click', function () {
        openCart();
        window.setTimeout(function () { $('#tax').trigger('focus').select(); }, 260);
    });

    $('#btn-scan, #btn-fab-scan').on('click', function () {
        info('Scanner kamera belum terhubung. Pakai kolom pencarian untuk input kode menu.');
    });

    $('#btn-ppob').on('click', function () {
        info('Modul PPOB belum tersedia.');
    });

    $('#btn-toggle-tax').on('click', function () {
        const hidden = $('#tax-wrap').toggleClass('d-none').hasClass('d-none');

        $('#tax-state-label').text(hidden ? 'Sembunyi' : 'Tampil');
        $(this).find('i').attr('class', hidden ? 'far fa-square' : 'far fa-check-square');
    });

    $('#btn-toggle-print').on('click', function () {
        const checked = ! $('#print_receipt').prop('checked');

        $('#print_receipt').prop('checked', checked);
        $('#print-state-label').text(checked ? 'Otomatis' : 'Tidak');
        $(this).find('i').attr('class', checked ? 'far fa-check-square' : 'far fa-square');
    });

    $('#btn-fullscreen').on('click', function () {
        if (! document.fullscreenElement && document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        } else if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    });
    /* --- Simpan transaksi ------------------------------------------- */
    $('#sale-form').on('submit', function (e) {
        if (! Object.keys(cart).length) {
            e.preventDefault();
            openCart();
            notify('Keranjang masih kosong. Ketuk menu untuk menambah pesanan.');
        }
    });

    /* --- Pulihkan keranjang setelah validasi gagal ------------------ */
    @php
        // Isi keranjang hidup di browser, jadi jumlah porsi dikirim ulang
        // lewat old input supaya tidak hilang saat halaman kembali.
        $oldCartRows = [];
        foreach ((array) old('menu_id', []) as $index => $menuId) {
            $qty = (float) old('quantity.' . $index, 0);
            if ($menuId !== null && $menuId !== '' && $qty > 0) {
                $oldCartRows[] = ['id' => (string) $menuId, 'qty' => $qty];
            }
        }
    @endphp

    @json($oldCartRows).forEach(function (row) {
        const $item = $('.kasir-item[data-id="' + row.id + '"]');

        if (! $item.length) {
            return;
        }

        cart[row.id] = {
            id: row.id,
            name: $item.data('label'),
            price: Number($item.data('price') || 0),
            image: $item.data('image'),
            qty: row.qty
        };
    });

    /* --- Render awal ------------------------------------------------ */
    paintFavorites();
    applyFilter();
    renderCart();

    @if ($errors->any() || session('error'))
        // Halaman kembali karena transaksi gagal: keranjang langsung dibuka.
        openCart();
    @endif
});
</script>
@endpush


