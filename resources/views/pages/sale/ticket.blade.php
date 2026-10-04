@php
    $storeName = config('pos.store_name');
    $isKitchen = $part === 'dapur';
    $ticketLabel = $isKitchen ? config('pos.kitchen_label', 'DAPUR') : config('pos.bar_label', 'BAR');
    $items = $isKitchen ? ($groups['makanan'] ?? collect()) : ($groups['minuman'] ?? collect());
    $totalQty = $items->sum('quantity');
    $otherPaper = $paperWidth === 80 ? 58 : 80;
    $otherPart = $isKitchen ? 'bar' : 'dapur';
    // tinggi cadangan bila JavaScript dimatikan (0 = tinggi mengikuti isi bon)
    $pageHeight = $paperHeight > 0 ? $paperHeight : 297;
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bon {{ $ticketLabel }} {{ $sale->code }}</title>
    <style>
        /* Bon dapur/bar printer thermal: lebar {{ $paperWidth }}mm (tanpa harga) */

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
            font-size: 13px;
            line-height: 1.35;
        }

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

        .ticket-title {
            margin: 4px 0;
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 3px;
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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 1px 0;
            vertical-align: top;
        }

        .item {
            margin: 4px 0;
        }

        .item-qty {
            width: 38px;
            font-size: 18px;
            font-weight: bold;
        }

        .item-name {
            font-size: 15px;
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
                width: {{ $paperWidth }}mm;
                margin: 0;
                padding: 0 1mm;
                box-shadow: none;
            }
        }
    </style>

    {{--
        Ukuran kertas printer thermal. Wajib ditulis sebagai satu/dua <length>
        (mis. "80mm 297mm"). Blok ini diperbarui JavaScript saat mencetak.
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
        <button type="button" class="btn" onclick="printReceipt()">Cetak Bon {{ $ticketLabel }}</button>
        <a href="{{ route('sale.ticket', ['id' => $sale->id, 'part' => $otherPart, 'paper' => $paperWidth]) }}"
            class="btn btn-secondary">Bon {{ $otherPart === 'dapur' ? 'Dapur' : 'Bar' }}</a>
        <a href="{{ route('sale.print', ['id' => $sale->id, 'paper' => $paperWidth]) }}"
            class="btn btn-secondary">Struk</a>
        <a href="{{ route('sale.show', $sale->id) }}" class="btn btn-secondary">Detail</a>
        <div class="hint">
            Bon {{ $ticketLabel }} berisi item <strong>{{ $isKitchen ? 'makanan' : 'minuman' }}</strong> saja
            (tanpa harga). Kertas {{ $paperWidth }}mm.
        </div>
    </div>

    <div class="receipt">
        <div class="center">
            <div class="store-name">{{ $storeName }}</div>
            <div class="ticket-title">BON {{ $ticketLabel }}</div>
        </div>

        <div class="line-double"></div>

        @if ($sale->isCancelled())
            <div class="center bold">*** DIBATALKAN ***</div>
            <div class="line"></div>
        @endif

        <table>
            <tr>
                <td>No. Struk</td>
                <td class="right">{{ $sale->code ?: '-' }}</td>
            </tr>
            <tr>
                <td>Waktu</td>
                <td class="right">{{ $sale->sale_date?->format('d/m/Y') ?? '-' }}
                    {{ $sale->created_at?->format('H:i') }}</td>
            </tr>
            <tr>
                <td>Kasir</td>
                <td class="right">{{ $sale->creator?->name ?? '-' }}</td>
            </tr>
            @if ($sale->description)
                <tr>
                    <td>Meja / Catatan</td>
                    <td class="right">{{ $sale->description }}</td>
                </tr>
            @endif
        </table>

        <div class="line"></div>

        @forelse ($items as $item)
            <table class="item">
                <tr>
                    <td class="item-qty">{{ FormatQty($item->quantity) }}x</td>
                    <td class="item-name">{{ $item->menu?->name ?? '-' }}</td>
                </tr>
            </table>
        @empty
            <div class="center muted">Tidak ada item {{ $isKitchen ? 'makanan' : 'minuman' }} pada transaksi ini.</div>
        @endforelse

        <div class="line"></div>

        <table>
            <tr>
                <td class="bold">Total Porsi</td>
                <td class="right bold">{{ FormatQty($totalQty) }}</td>
            </tr>
        </table>

        <div class="line"></div>
        <div class="center muted">Bon produksi — harap disiapkan sesuai pesanan.</div>
    </div>

    <script>
        var paperWidth = {{ $paperWidth }};      // mm
        var fixedHeight = {{ $paperHeight }};    // mm, 0 = tinggi mengikuti isi bon
        var autoPrint = {{ $autoPrint ? 'true' : 'false' }};
        var MM_PER_PX = 25.4 / 96;

        /*
         * Ukuran kertas ditulis sebagai satu/dua <length> (mis. "80mm 297mm").
         * Bila tinggi 0, tinggi dihitung dari isi bon supaya kertas roll tidak terbuang.
         */
        function applyPageSize() {
            var height = fixedHeight;

            if (!height) {
                var receipt = document.querySelector('.receipt');
                var heightPx = receipt ? receipt.getBoundingClientRect().height : 0;

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

        // Cetak otomatis saat bon dibuka (matikan lewat POS_THERMAL_AUTO_PRINT=false)
        window.addEventListener('load', function () {
            applyPageSize();

            if (autoPrint) {
                setTimeout(printReceipt, 400);
            }
        });

        window.addEventListener('pageshow', function () {
            applyPageSize();
        });
    </script>
</body>

</html>
