<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Penjualan - {{ $periode }}</title>
<style>
* { font-family: Arial, Helvetica, sans-serif; }
body { margin: 24px; color: #222; }
.header { text-align: center; margin-bottom: 12px; }
.header h2 { margin: 0 0 4px; }
.header p { margin: 2px 0; font-size: 12px; color: #555; }
.summary { margin: 12px 0; font-size: 12px; }
.summary span { display: inline-block; margin-right: 18px; }
table { width: 100%; border-collapse: collapse; font-size: 11px; }
th, td { border: 1px solid #999; padding: 5px 6px; }
th { background: #f0f0f0; }
td.num, th.num { text-align: right; }
tfoot td { font-weight: bold; background: #fafafa; }
.toolbar { margin-bottom: 12px; }
.toolbar button { padding: 8px 14px; margin-right: 8px; cursor: pointer; }
@media print {
.toolbar { display: none; }
body { margin: 0; }
@page { size: A4 landscape; margin: 10mm; }
}
</style>
</head>
<body>
<div class="toolbar">
<button onclick="window.print()">Cetak / Simpan PDF</button>
<button onclick="window.close()">Tutup</button>
</div>
<div class="header">
<h2>LAPORAN PENJUALAN</h2>
<p>Periode: {{ $periode }} | Dicetak: {{ $generatedAt }}</p>
</div>
<div class="summary">
<span>Total Transaksi: <b>{{ $totalTransaksi }}</b></span>
<span>Selesai: <b>{{ $transaksiSelesai }}</b></span>
<span>Total Porsi: <b>{{ $totalQty }}</b></span>
<span>Omzet: <b>Rp {{ number_format($totalOmzet, 0, ',', '.') }}</b></span>
</div>
<table>
<thead>
<tr>
<th>No</th><th>Tanggal</th><th>No. Transaksi</th><th>Menu</th><th>Kategori</th>
<th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th>
<th>Bayar</th><th>Kasir</th><th>Status</th>
</tr>
</thead>
<tbody>
@forelse ($rows as $i => $row)
<tr>
<td>{{ $i + 1 }}</td>
<td>{{ $row->sale && $row->sale->sale_date ? $row->sale->sale_date->format('d/m/Y') : '-' }}</td>
<td>{{ $row->sale->code ?? '-' }}</td>
<td>{{ $row->menu->name ?? '-' }}</td>
<td>{{ $row->menu && $row->menu->category ? $row->menu->category->name : '-' }}</td>
<td class="num">{{ $row->quantity }}</td>
<td class="num">{{ number_format($row->unit_price, 0, ',', '.') }}</td>
<td class="num">{{ number_format($row->subtotal, 0, ',', '.') }}</td>
<td>{{ $row->sale->payment_method ?? '-' }}</td>
<td>{{ $row->sale && $row->sale->creator ? $row->sale->creator->name : '-' }}</td>
<td>{{ $row->sale && $row->sale->isCompleted() ? 'Selesai' : 'Dibatalkan' }}</td>
</tr>
@empty
<tr><td colspan="11" style="text-align:center">Tidak ada data.</td></tr>
@endforelse
</tbody>
<tfoot>
<tr>
<td colspan="5">TOTAL</td>
<td class="num">{{ $totalQty }}</td>
<td></td>
<td class="num">{{ number_format($totalOmzet, 0, ',', '.') }}</td>
<td colspan="3"></td>
</tr>
</tfoot>
</table>
<script>window.onload = function () { window.print(); };</script>
</body>
</html>
