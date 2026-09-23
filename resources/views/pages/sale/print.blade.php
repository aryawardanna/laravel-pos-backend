@php
    $storeName = config('pos.store_name');
    $storeAddress = config('pos.store_address');
    $storePhone = config('pos.store_phone');
    $otherPaper = $paperWidth === 80 ? 58 : 80;
    $totalQty = $sale->items->sum('quantity');
    // tinggi cadangan bila JavaScript dimatikan (0 = tinggi mengikuti isi struk)
    $pageHeight = $paperHeight > 0 ? $paperHeight : 297;
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $sale->code }}</title>
    <style>
        /* Struk printer thermal: lebar {{ $paperWidth }}mm
           (58mm ≈ 32 karakter/baris, 80mm ≈ 48 karakter/baris) */

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            background: #eef0f3;
            color: #000;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.35;
        }

        /* Toolbar bantuan di layar (tidak ikut tercetak) */
        .toolbar {
            width: {{ $paperWidth }}mm;
            margin: 12px auto 0;
            padding: 10px;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
            font-family: 'Segoe UI', Tahoma, sans-serif;
            text-align: center;
        }

        .toolbar .btn {
            display: inline-block;
            margin: 2px 3px;
            padding: 6px 12px;
            border: 0;
            border-radius: 4px;
            background: #6777ef;
            color: #fff;
            font-size: 12px;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar .btn-secondary {
            background: #98a6ad;
        }

        .toolbar .hint {
            margin-top: 8px;
            color: #6c757d;
            font-size: 11px;
            line-height: 1.4;
        }

        .receipt {
            width: {{ $paperWidth }}mm;
            margin: 12px auto 24px;
            padding: 4mm 3mm 6mm;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .store-name {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: .5px;
        }

        .muted {
            font-size: 11px;
        }

        .line {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        .line-double {
            border-top: 3px double #000;
            margin: 6px 0;
        }

        .void {
            margin: 6px 0;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
            text-align: center;
            border: 1px solid #000;
            padding: 2px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 1px 0;
            vertical-align: top;
        }

        .item-name {
            font-weight: bold;
        }

        .qty-line {
            font-size: 11px;
        }

        .totals .label {
            width: 55%;
        }

        .grand td {
            padding-top: 3px;
            font-size: 13px;
            font-weight: bold;
        }

        @media print {
            html,
            body {
                background: #fff;
            }

            .no-print {
                display: none !important;
            }

            .receipt {
                /* tetap {{ $paperWidth }}mm walau dialog print masih memilih A4,
                   supaya struk tidak melebar mengikuti lebar kertas */
                width: {{ $paperWidth }}mm;
                margin: 0;
                padding: 0 1mm;
                box-shadow: none;
            }
        }
    </style>

    {{--
        Ukuran kertas printer thermal. Wajib ditulis sebagai satu/dua <length>
        (mis. "80mm 297mm") — kata kunci "auto" hanya boleh berdiri sendiri,
        jadi "80mm auto" dianggap tidak valid oleh browser dan berakibat
        ukuran kertas jatuh ke default printer (A4).
        Blok ini diperbarui oleh JavaScript saat mencetak: bila tinggi 0,
        tinggi dihitung dari isi struk sehingga kertas roll tidak terbuang.
    --}}
    <style id="page-size">
        @page {
            size: {{ $paperWidth }}mm {{ $pageHeight }}mm;
            margin: 0;
        }
    </style>
</head>

<body>
    {{-- Toolbar bantuan di layar: tidak ikut tercetak --}}
    <div class="toolbar no-print">
        <button type="button" class="btn" onclick="printReceipt()">Cetak Struk</button>
        <a href="{{ route('sale.print', $sale->id) }}?paper={{ $otherPaper }}" class="btn btn-secondary">
            Kertas {{ $otherPaper }}mm
        </a>
        <a href="{{ route('sale.show', $sale->id) }}" class="btn btn-secondary">Detail</a>
        <a href="{{ route('sale.create') }}" class="btn btn-secondary">Transaksi Baru</a>
        <div class="hint">
            Ukuran struk: <strong>{{ $paperWidth }}mm</strong>
            ({{ $paperHeight > 0 ? $paperHeight . 'mm' : 'tinggi mengikuti isi struk' }}).
            Di dialog print pilih <em>Paper size</em> {{ $paperWidth }}mm / Roll Paper,
            <em>Margins</em> = None, dan matikan "Headers and footers".
        </div>
        <div class="hint">
            Masih muncul A4? Ukuran kertas diambil dari daftar driver printer.
            Pilih <em>Paper size</em> {{ $paperWidth }} x {{ $pageHeight }} mm di dialog print
            atau atur ukuran kertas default printer di sistem operasi. Untuk mencetak
            tanpa dialog sama sekali, jalankan browser dengan opsi
            <code>--kiosk-printing</code>.
        </div>
    </div>

    <div class="receipt">
        <div class="center">
            <div class="store-name">{{ $storeName }}</div>
            @if ($storeAddress)
                <div class="muted">{{ $storeAddress }}</div>
            @endif
            @if ($storePhone)
                <div class="muted">Telp {{ $storePhone }}</div>
            @endif
        </div>

        <div class="line-double"></div>

        @if ($sale->isCancelled())
            <div class="void">DIBATALKAN</div>
        @endif

        <table>
            <tr>
                <td>No. Struk</td>
                <td class="right">{{ $sale->code ?: '-' }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td class="right">{{ $sale->sale_date?->format('d/m/Y') ?? '-' }}
                    {{ $sale->created_at?->format('H:i') }}</td>
            </tr>
            <tr>
                <td>Kasir</td>
                <td class="right">{{ $sale->creator?->name ?? '-' }}</td>
            </tr>
            <tr>
                <td>Pembayaran</td>
                <td class="right">{{ strtoupper($sale->payment_method ?: 'cash') }}</td>
            </tr>
        </table>

        <div class="line"></div>

        @forelse ($sale->items as $item)
            <table>
                <tr>
                    <td class="item-name">{{ $item->menu?->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="qty-line">{{ FormatQty($item->quantity) }} x {{ FormatMoney($item->unit_price) }}</td>
                    <td class="right">{{ FormatMoney($item->subtotal) }}</td>
                </tr>
            </table>
        @empty
            <div class="center muted">Tidak ada item.</div>
        @endforelse

        <div class="line"></div>

        <table class="totals">
            <tr>
                <td class="label">Jumlah Item</td>
                <td class="right">{{ $sale->items->count() }} menu ({{ FormatQty($totalQty) }} porsi)</td>
            </tr>
            <tr>
                <td class="label">Subtotal</td>
                <td class="right">{{ FormatMoney($sale->subtotal) }}</td>
            </tr>
            @if ((float) $sale->discount > 0)
                <tr>
                    <td class="label">Diskon</td>
                    <td class="right">-{{ FormatMoney($sale->discount) }}</td>
                </tr>
            @endif
            @if ((float) $sale->tax > 0)
                <tr>
                    <td class="label">Pajak</td>
                    <td class="right">{{ FormatMoney($sale->tax) }}</td>
                </tr>
            @endif
            <tr class="grand">
                <td class="label">TOTAL</td>
                <td class="right">{{ FormatMoney($sale->total) }}</td>
            </tr>
            <tr>
                <td class="label">Bayar ({{ strtoupper($sale->payment_method ?: 'cash') }})</td>
                <td class="right">{{ FormatMoney($sale->paid) }}</td>
            </tr>
            <tr>
                <td class="label">Kembali</td>
                <td class="right">{{ FormatMoney($sale->change_amount) }}</td>
            </tr>
        </table>

        @if ($sale->description)
            <div class="line"></div>
            <div class="muted">Catatan: {{ $sale->description }}</div>
        @endif

        <div class="line"></div>
        <div class="center">{{ config('pos.receipt_footer') }}</div>
        <div class="center muted">Struk ini adalah bukti pembayaran yang sah.</div>
    </div>

    <script>
        var paperWidth = {{ $paperWidth }};      // mm
        var fixedHeight = {{ $paperHeight }};    // mm, 0 = tinggi mengikuti isi struk
        var autoPrint = {{ $autoPrint ? 'true' : 'false' }};
        var MM_PER_PX = 25.4 / 96;

        /*
         * Ukuran kertas ditulis sebagai satu/dua <length> (mis. "80mm 297mm").
         * Kata kunci "auto" tidak boleh digabung dengan satuan: aturan seperti
         * "size: 80mm auto" jadi tidak valid dan browser memakai ukuran default
         * printer (A4). Bila tinggi 0, tinggi dihitung dari isi struk supaya
         * kertas roll tidak terbuang.
         */
        function applyPageSize() {
            var height = fixedHeight;

            if (!height) {
                var receipt = document.querySelector('.receipt');
                var heightPx = receipt ? receipt.getBoundingClientRect().height : 0;

                // tambahan kecil agar baris terakhir tidak terpotong
                height = Math.max(40, Math.ceil((heightPx + 14) * MM_PER_PX));
            }

            var sizeStyle = document.getElementById('page-size');

            if (sizeStyle) {
                sizeStyle.textContent =
                    '@page { size: ' + paperWidth + 'mm ' + height + 'mm; margin: 0; }';
            }
        }

        function printReceipt() {
            applyPageSize();
            window.print();
        }

        // Cetak otomatis saat struk dibuka (matikan lewat POS_THERMAL_AUTO_PRINT=false)
        window.addEventListener('load', function () {
            applyPageSize();

            if (autoPrint) {
                setTimeout(printReceipt, 400);
            }
        });

        // Tombol "Kertas 58mm/80mm" di toolbar memperbarui tinggi otomatis saat
        // halaman dibuka ulang.
        window.addEventListener('pageshow', function () {
            applyPageSize();
        });
    </script>
</body>

</html>
