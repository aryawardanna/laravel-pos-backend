<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Penjualan</title>
<style>
body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color: #222; font-size: 10px; }
.header { text-align: center; margin-bottom: 10px; }
.header h2 { margin: 0 0 4px; font-size: 16px; }
.header p { margin: 2px 0; font-size: 10px; color: #555; }
.summary { margin: 8px 0; font-size: 10px; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #999; padding: 4px 5px; }
th { background: #eee; }
.num { text-align: right; }
tfoot td { font-weight: bold; }
</style>
</head>
<body>
<div class="header">
<h2>LAPORAN PENJUALAN</h2>
<p>Periode: {{ $periode }} | Dicetak: {{ $generatedAt }}</p>
<p>Total Transaksi: {{ $totalTransaksi }} | Selesai: {{ $transaksiSelesai }} | Total Porsi: {{ $totalQty }} | Omzet: Rp {{ number_format($totalOmzet, 0, ',', '.') }}</p>
</div>
<table>
<thead>
<tr>
<th>No</th><th>Tanggal</th><th>No. Transaksi</th><th>Menu</th><th>Kategori</th>
<th>Qty</th><th>Harga</th><th>Subtotal</th><th>Bayar</th><th>Kasir</th><th>Status</th>
</tr>
</thead>
<tbody>
@foreach ($rows as $i => $row)
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
@endforeach
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
</body>
</html>
