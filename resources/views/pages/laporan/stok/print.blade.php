<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Stok Bahan Baku</title>
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
<h2>LAPORAN STOK BAHAN BAKU</h2>
<p>Dicetak: {{ $generatedAt }}</p>
</div>
<div class="summary">
<span>Total Bahan: <b>{{ $totalBahan }}</b></span>
<span>Habis: <b>{{ $stokHabis }}</b></span>
<span>Menipis: <b>{{ $stokMenipis }}</b></span>
<span>Nilai: <b>Rp {{ number_format($totalNilai, 0, ',', '.') }}</b></span>
</div>
<table>
<thead>
<tr>
<th>No</th><th>Bahan Baku</th><th>Kode</th><th>Satuan</th>
<th class="num">Stok</th><th class="num">Min. Stok</th><th class="num">Harga</th><th class="num">Nilai</th>
<th>Batch Aktif</th><th>Kondisi</th><th>Status</th>
</tr>
</thead>
<tbody>
@forelse ($rows as $i => $row)
@php $b = $batch[$row->id] ?? ['aktif' => 0, 'expired' => 0, 'segera' => 0]; @endphp
<tr>
<td>{{ $i + 1 }}</td>
<td>{{ $row->name }}</td>
<td>{{ $row->code ?? '-' }}</td>
<td>{{ $row->satuan->name ?? '-' }}</td>
<td class="num">{{ $row->stock }}</td>
<td class="num">{{ $row->min_stock }}</td>
<td class="num">{{ number_format($row->price, 0, ',', '.') }}</td>
<td class="num">{{ number_format((float) $row->stock * (float) $row->price, 0, ',', '.') }}</td>
<td>{{ $b['aktif'] }} (ED: {{ $b['segera'] }}, kedaluwarsa: {{ $b['expired'] }})</td>
<td>{{ (float) $row->stock <= 0 ? 'Habis' : ((float) $row->stock <= (float) $row->min_stock ? 'Menipis' : 'Aman') }}</td>
<td>{{ (int) $row->status === 1 ? 'Aktif' : 'Nonaktif' }}</td>
</tr>
@empty
<tr><td colspan="11" style="text-align:center">Tidak ada data.</td></tr>
@endforelse
</tbody>
<tfoot>
<tr>
<td colspan="7">TOTAL NILAI PERSEDIAAN</td>
<td class="num">{{ number_format($totalNilai, 0, ',', '.') }}</td>
<td colspan="3"></td>
</tr>
</tfoot>
</table>
<script>window.onload = function () { window.print(); };</script>
</body>
</html>
