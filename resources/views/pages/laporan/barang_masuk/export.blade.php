<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="UTF-8"></head>
<body>
<table>
<tr><td colspan="13"><b>LAPORAN BARANG MASUK</b></td></tr>
<tr><td colspan="13">Periode: {{ $periode }}</td></tr>
<tr><td colspan="13">Dicetak: {{ $generatedAt }} | Total Pembelian: {{ $totalPembelian }} | Diterima: {{ $pembelianDiterima }} | Baris Item: {{ $totalItem }} | Nilai: {{ $totalNilai }}</td></tr>
<tr><td colspan="13"></td></tr>
</table>
<table border="1">
<thead>
<tr>
<th>No</th><th>Tanggal</th><th>No. Pembelian</th><th>Supplier</th><th>Bahan Baku</th><th>Satuan</th><th>Batch</th><th>Qty</th><th>Harga</th><th>Subtotal</th><th>Expired</th><th>Penerima</th><th>Status</th>
</tr>
</thead>
<tbody>
@foreach ($rows as $i => $row)
<tr>
<td>{{ $i + 1 }}</td>
<td>{{ $row->purchase && $row->purchase->purchase_date ? $row->purchase->purchase_date->format('d/m/Y') : '-' }}</td>
<td>{{ $row->purchase->code ?? '-' }}</td>
<td>{{ $row->purchase && $row->purchase->supplier ? $row->purchase->supplier->name : '-' }}</td>
<td>{{ $row->bahanBaku->name ?? '-' }}</td>
<td>{{ $row->bahanBaku && $row->bahanBaku->satuan ? $row->bahanBaku->satuan->name : '-' }}</td>
<td>{{ $row->batch_code ?? '-' }}</td>
<td>{{ $row->quantity }}</td>
<td>{{ $row->unit_price }}</td>
<td>{{ $row->subtotal }}</td>
<td>{{ $row->expired_date ? $row->expired_date->format('d/m/Y') : '-' }}</td>
<td>{{ $row->purchase && $row->purchase->creator ? $row->purchase->creator->name : '-' }}</td>
<td>{{ $row->purchase && $row->purchase->isReceived() ? 'Diterima' : ($row->purchase && $row->purchase->isCancelled() ? 'Dibatalkan' : 'Draft') }}</td>
</tr>
@endforeach
</tbody>
<tfoot>
<tr>
<td colspan="9"><b>TOTAL NILAI (DITERIMA)</b></td>
<td><b>{{ $totalNilai }}</b></td>
<td colspan="3"></td>
</tr>
</tfoot>
</table>
</body>
</html>
