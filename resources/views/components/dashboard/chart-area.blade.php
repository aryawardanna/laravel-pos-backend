{{--
    Area grafik dashboard.

    Menghasilkan <canvas> bila datanya ada, atau pesan singkat bila kosong,
    sehingga tiap kartu grafik tidak perlu menulis kondisi @if/@else sendiri.

    Parameter:
      $id        id canvas (harus sama dengan id di JavaScript)
      $adaData   bool, apakah ada data untuk digambar
      $pesan     teks yang tampil saat data kosong
--}}
<div class="dash-chart">
    @if ($adaData)
        <canvas id="{{ $id }}"></canvas>
    @else
        <div class="dash-empty">{{ $pesan }}</div>
    @endif
</div>
