@extends('layouts.app')

@section('title', 'Stock Opname Detail')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Detail Stock Opname</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventory</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('stock_opname.index') }}">Stock Opname</a></div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Informasi Opname</h4>
                                <div class="card-header-action">
                                    @if ($opname->isFinal())
                                        <span class="badge badge-success">Selesai</span>
                                    @elseif ($opname->isCancelled())
                                        <span class="badge badge-danger">Dibatalkan</span>
                                    @else
                                        <span class="badge badge-warning">Draft</span>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th width="30%">Nomor Opname</th>
                                        <td>{{ $opname->code ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal Opname</th>
                                        <td>{{ $opname->opname_date?->format('d F Y') ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Jumlah Item</th>
                                        <td>{{ $opname->items->count() }} item</td>
                                    </tr>
                                    <tr>
                                        <th>Ringkasan Selisih</th>
                                        <td>
                                            <span class="badge badge-success">{{ $opname->items->filter->isIncrease()->count() }} lebih</span>
                                            <span class="badge badge-danger">{{ $opname->items->filter->isDecrease()->count() }} kurang</span>
                                            <span class="badge badge-secondary">{{ $opname->items->filter->isMatch()->count() }} sesuai</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Catatan</th>
                                        <td>{{ $opname->description ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat Oleh</th>
                                        <td>{{ $opname->creator?->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Diubah Oleh</th>
                                        <td>{{ $opname->updater?->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat Pada</th>
                                        <td>{{ $opname->created_at?->format('d F Y H:i') ?? '-' }}</td>
                                    </tr>
                                </table>

                                <div class="mt-3">
                                    <a href="{{ route('stock_opname.index') }}" class="btn btn-secondary">Kembali</a>

                                    @if ($opname->isDraft())
                                        <a href="{{ route('stock_opname.edit', $opname->id) }}" class="btn btn-info">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>

                                        <form action="{{ route('stock_opname.finalize', $opname->id) }}" method="POST"
                                            class="d-inline finalize-form">
                                            @csrf
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check"></i> Selesaikan Opname
                                            </button>
                                        </form>
                                    @endif

                                    @if (!$opname->isCancelled())
                                        <form action="{{ route('stock_opname.destroy', $opname->id) }}" method="POST"
                                            class="d-inline delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger confirm-delete">
                                                <i class="fas fa-times"></i> Batalkan
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Hasil Hitung Fisik</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Bahan Baku</th>
                                                <th>Satuan</th>
                                                <th>Stok Sistem</th>
                                                <th>Stok Fisik</th>
                                                <th>Selisih</th>
                                                <th>Nilai Selisih</th>
                                                <th>Catatan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($opname->items as $item)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>{{ $item->bahanBaku?->name ?? '-' }}</td>
                                                    <td>{{ $item->bahanBaku?->satuan?->name ?? '-' }}</td>
                                                    <td>{{ FormatQty($item->system_stock) }}</td>
                                                    <td>{{ FormatQty($item->physical_stock) }}</td>
                                                    <td>
                                                        @if ($item->isIncrease())
                                                            <span class="badge badge-success">+{{ FormatQty($item->difference) }}</span>
                                                        @elseif ($item->isDecrease())
                                                            <span class="badge badge-danger">{{ FormatQty($item->difference) }}</span>
                                                        @else
                                                            <span class="badge badge-secondary">0 (sesuai)</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($item->isMatch())
                                                            {{ FormatMoney(0) }}
                                                        @else
                                                            {{ FormatMoney((float) $item->difference * (float) $item->unit_price) }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $item->description ?: '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted">Belum ada item.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                @if ($opname->isDraft())
                                    <div class="alert alert-warning mt-3 mb-0">
                                        Opname ini masih <strong>draft</strong> — stok bahan baku belum disesuaikan.
                                        Klik <strong>Selesaikan Opname</strong> untuk menerapkan selisih ke stok.
                                    </div>
                                @elseif ($opname->isCancelled())
                                    <div class="alert alert-danger mt-3 mb-0">
                                        Opname ini <strong>dibatalkan</strong> — penyesuaian stoknya sudah dikembalikan.
                                    </div>
                                @else
                                    <div class="alert alert-success mt-3 mb-0">
                                        Opname <strong>selesai</strong> — selisih sudah diterapkan ke stok bahan baku
                                        dan tercatat di <a href="{{ route('kartu_stok.index') }}">kartu stok</a>.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).on('submit', '.finalize-form', function (e) {
            if (!confirm('Selesaikan stock opname ini? Stok bahan baku akan disesuaikan dengan hasil hitung fisik.')) {
                e.preventDefault();
            }
        });

        $(document).on('submit', '.delete-form', function (e) {
            if (!confirm('Batalkan stock opname ini? Penyesuaian stok akan dikembalikan.')) {
                e.preventDefault();
            }
        });
    </script>
@endpush