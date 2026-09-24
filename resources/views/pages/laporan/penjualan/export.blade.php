<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="UTF-8"></head>
<body>
<table>
<tr><td colspan="11"><b>LAPORAN PENJUALAN</b></td></tr>
<tr><td colspan="11">Periode: {{ $periode }}</td></tr>
<tr><td colspan="11">Dicetak: {{ $generatedAt }} | Total Transaksi: {{ $totalTransaksi }} | Total Porsi: {{ $totalQty }} | Omzet: {{ $totalOmzet }}</td></tr>
<tr><td colspan="11"></td></tr>
</table>
<table border="1">
<thead>
<tr>
<th>No</th><th>Tanggal</th><th>No. Transaksi</th><th>Menu</th><th>Kategori</th><th>Qty</th><th>Harga</th><th>Subtotal</th><th>Metode Bayar</th><th>Kasir</th><th>Status</th>
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
<td>{{ $row->quantity }}</td>
<td>{{ $row->unit_price }}</td>
<td>{{ $row->subtotal }}</td>
<td>{{ $row->sale->payment_method ?? '-' }}</td>
<td>{{ $row->sale && $row->sale->creator ? $row->sale->creator->name : '-' }}</td>
<td>{{ $row->sale && $row->sale->isCompleted() ? 'Selesai' : 'Dibatalkan' }}</td>
</tr>
@endforeach
</tbody>
<tfoot>
<tr>
<td colspan="5"><b>TOTAL</b></td>
<td><b>{{ $totalQty }}</b></td>
<td></td>
<td><b>{{ $totalOmzet }}</b></td>
<td colspan="3"></td>
</tr>
</tfoot>
</table>
</body>
</html>
