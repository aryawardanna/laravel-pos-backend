<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Barang Masuk - {{ $periode }}</title>
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
<h2>LAPORAN BARANG MASUK</h2>
<p>Periode: {{ $periode }} | Dicetak: {{ $generatedAt }}</p>
</div>
<div class="summary">
<span>Total Pembelian: <b>{{ $totalPembelian }}</b></span>
<span>Diterima: <b>{{ $pembelianDiterima }}</b></span>
<span>Baris Item: <b>{{ $totalItem }}</b></span>
<span>Nilai: <b>Rp {{ number_format($totalNilai, 0, ',', '.') }}</b></span>
</div>
<table>
<thead>
<tr>
<th>No</th><th>Tanggal</th><th>No. Pembelian</th><th>Supplier</th><th>Bahan Baku</th><th>Satuan</th><th>Batch</th>
<th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th><th>Expired</th><th>Penerima</th><th>Status</th>
</tr>
</thead>
<tbody>
@forelse ($rows as $i => $row)
<tr>
<td>{{ $i + 1 }}</td>
<td>{{ $row->purchase && $row->purchase->purchase_date ? $row->purchase->purchase_date->format('d/m/Y') : '-' }}</td>
<td>{{ $row->purchase->code ?? '-' }}</td>
<td>{{ $row->purchase && $row->purchase->supplier ? $row->purchase->supplier->name : '-' }}</td>
<td>{{ $row->bahanBaku->name ?? '-' }}</td>
<td>{{ $row->bahanBaku && $row->bahanBaku->satuan ? $row->bahanBaku->satuan->name : '-' }}</td>
<td>{{ $row->batch_code ?? '-' }}</td>
<td class="num">{{ $row->quantity }}</td>
<td class="num">{{ number_format($row->unit_price, 0, ',', '.') }}</td>
<td class="num">{{ number_format($row->subtotal, 0, ',', '.') }}</td>
<td>{{ $row->expired_date ? $row->expired_date->format('d/m/Y') : '-' }}</td>
<td>{{ $row->purchase && $row->purchase->creator ? $row->purchase->creator->name : '-' }}</td>
<td>{{ $row->purchase && $row->purchase->isReceived() ? 'Diterima' : ($row->purchase && $row->purchase->isCancelled() ? 'Dibatalkan' : 'Draft') }}</td>
</tr>
@empty
<tr><td colspan="13" style="text-align:center">Tidak ada data.</td></tr>
@endforelse
</tbody>
<tfoot>
<tr>
<td colspan="9">TOTAL NILAI (DITERIMA)</td>
<td class="num">{{ number_format($totalNilai, 0, ',', '.') }}</td>
<td colspan="3"></td>
</tr>
</tfoot>
</table>
<script>window.onload = function () { window.print(); };</script>
</body>
</html>
