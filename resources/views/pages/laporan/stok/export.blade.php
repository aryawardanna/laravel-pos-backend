<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="UTF-8"></head>
<body>
<table>
<tr><td colspan="11"><b>LAPORAN STOK BAHAN BAKU</b></td></tr>
<tr><td colspan="11">Dicetak: {{ $generatedAt }} | Total Bahan: {{ $totalBahan }} | Habis: {{ $stokHabis }} | Menipis: {{ $stokMenipis }} | Nilai: {{ $totalNilai }}</td></tr>
<tr><td colspan="11"></td></tr>
</table>
<table border="1">
<thead>
<tr>
<th>No</th><th>Bahan Baku</th><th>Kode</th><th>Satuan</th><th>Stok</th><th>Min. Stok</th><th>Harga</th><th>Nilai (Stok x Harga)</th><th>Batch Aktif</th><th>Kondisi</th><th>Status</th>
</tr>
</thead>
<tbody>
@foreach ($rows as $i => $row)
@php $b = $batch[$row->id] ?? ['aktif' => 0, 'expired' => 0, 'segera' => 0]; @endphp
<tr>
<td>{{ $i + 1 }}</td>
<td>{{ $row->name }}</td>
<td>{{ $row->code ?? '-' }}</td>
<td>{{ $row->satuan->name ?? '-' }}</td>
<td>{{ $row->stock }}</td>
<td>{{ $row->min_stock }}</td>
<td>{{ $row->price }}</td>
<td>{{ (float) $row->stock * (float) $row->price }}</td>
<td>{{ $b['aktif'] }} (ED<=30hr: {{ $b['segera'] }}, kedaluwarsa: {{ $b['expired'] }})</td>
<td>{{ (float) $row->stock <= 0 ? 'Habis' : ((float) $row->stock <= (float) $row->min_stock ? 'Menipis' : 'Aman') }}</td>
<td>{{ (int) $row->status === 1 ? 'Aktif' : 'Nonaktif' }}</td>
</tr>
@endforeach
</tbody>
<tfoot>
<tr>
<td colspan="7"><b>TOTAL NILAI PERSEDIAAN</b></td>
<td><b>{{ $totalNilai }}</b></td>
<td colspan="3"></td>
</tr>
</tfoot>
</table>
</body>
</html>
