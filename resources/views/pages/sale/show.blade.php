@extends('layouts.app')

@section('title', 'Penjualan Detail')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Detail Penjualan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Transaksi</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('sale.index') }}">Penjualan</a></div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header">
                                <h4>Informasi Transaksi</h4>
                                <div class="card-header-action">
                                    @if ($sale->isCompleted())
                                        <span class="badge badge-success">Selesai</span>
                                    @else
                                        <span class="badge badge-danger">Dibatalkan</span>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Nomor Transaksi</th>
                                        <td>{{ $sale->code ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal</th>
                                        <td>{{ $sale->sale_date?->format('d F Y') ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Metode Bayar</th>
                                        <td>{{ $sale->payment_method ? ucfirst($sale->payment_method) : '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Subtotal</th>
                                        <td>{{ FormatMoney($sale->subtotal) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Diskon</th>
                                        <td>{{ FormatMoney($sale->discount) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Pajak</th>
                                        <td>{{ FormatMoney($sale->tax) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Total</th>
                                        <td><strong>{{ FormatMoney($sale->total) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Dibayar</th>
                                        <td>{{ FormatMoney($sale->paid) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Kembalian</th>
                                        <td>{{ FormatMoney($sale->change_amount) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Catatan</th>
                                        <td>{{ $sale->description ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Kasir</th>
                                        <td>{{ $sale->creator?->name ?? '-' }}</td>
                                    </tr>
                                </table>

                                <a href="{{ route('sale.print', $sale->id) }}" target="_blank"
                                    class="btn btn-primary">Cetak Struk</a>
                                <a href="{{ route('sale.index') }}" class="btn btn-secondary">Kembali</a>

                                @if ($sale->isCompleted())
                                    <form action="{{ route('sale.destroy', $sale->id) }}" method="POST"
                                        class="d-inline delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger">Batalkan Transaksi</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header">
                                <h4>Item Menu Terjual</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Menu</th>
                                                <th>Qty (porsi)</th>
                                                <th>Harga</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($sale->items as $item)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>{{ $item->menu?->name ?? '-' }}</td>
                                                    <td><span class="badge badge-info">{{ FormatQty($item->quantity) }}</span></td>
                                                    <td>{{ FormatMoney($item->unit_price) }}</td>
                                                    <td>{{ FormatMoney($item->subtotal) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">Belum ada item.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="4" class="text-right">Total</th>
                                                <th>{{ FormatMoney($sale->total) }}</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Pemakaian Bahan Baku (FEFO per Batch)</h4>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-light">
                                    Penjualan menu otomatis mengurangi stok bahan baku sesuai resep.
                                    Batch dengan kedaluwarsa paling awal dipakai lebih dulu (FEFO).
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Bahan Baku</th>
                                                <th>Satuan</th>
                                                <th>Batch / Lot</th>
                                                <th>Kedaluwarsa</th>
                                                <th>Qty Terpakai</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($sale->usages->sortBy([['bahan_baku_id', 'asc'], ['id', 'asc']]) as $usage)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>{{ $usage->bahanBaku?->name ?? '-' }}</td>
                                                    <td>{{ $usage->bahanBaku?->satuan?->name ?? '-' }}</td>
                                                    <td>{{ $usage->batch_code ?: '-' }}</td>
                                                    <td>{{ $usage->batch?->expired_date?->format('d F Y') ?? '-' }}</td>
                                                    <td><span class="badge badge-warning">{{ FormatQty($usage->quantity) }}</span></td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">Tidak ada bahan baku terpakai.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
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
        $(document).on('submit', '.delete-form', function (e) {
            if (!confirm('Batalkan transaksi ini? Stok bahan baku akan dikembalikan ke batch semula.')) {
                e.preventDefault();
            }
        });
    </script>
@endpush

