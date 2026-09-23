@extends('layouts.app')

@section('title', 'Pembelian Detail')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Detail Pembelian</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ url('/home') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Transaksi</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('purchase.index') }}">Pembelian</a></div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Informasi Pembelian</h4>
                                <div class="card-header-action">
                                    @if ($purchase->isReceived())
                                        <span class="badge badge-success">Diterima</span>
                                    @elseif ($purchase->isCancelled())
                                        <span class="badge badge-danger">Dibatalkan</span>
                                    @else
                                        <span class="badge badge-warning">Draft</span>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th width="30%">Nomor Pembelian</th>
                                        <td>{{ $purchase->code ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal Pembelian</th>
                                        <td>{{ $purchase->purchase_date?->format('d F Y') ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Supplier</th>
                                        <td>{{ $purchase->supplier?->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Total</th>
                                        <td>{{ FormatMoney($purchase->total) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Jumlah Batch</th>
                                        <td>{{ $purchase->items->count() }} batch</td>
                                    </tr>
                                    <tr>
                                        <th>Catatan</th>
                                        <td>{{ $purchase->description ?: '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat Oleh</th>
                                        <td>{{ $purchase->creator?->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Diubah Oleh</th>
                                        <td>{{ $purchase->updater?->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat Pada</th>
                                        <td>{{ $purchase->created_at?->format('d F Y H:i') ?? '-' }}</td>
                                    </tr>
                                </table>

                                <div class="mt-3">
                                    <a href="{{ route('purchase.index') }}" class="btn btn-secondary">Kembali</a>

                                    @if (!$purchase->isCancelled())
                                        <a href="{{ route('purchase.edit', $purchase->id) }}" class="btn btn-info">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    @endif

                                    @if ($purchase->isDraft())
                                        <form action="{{ route('purchase.receive', $purchase->id) }}" method="POST"
                                            class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check"></i> Terima Pembelian
                                            </button>
                                        </form>
                                    @endif

                                    @if (!$purchase->isCancelled())
                                        <form action="{{ route('purchase.destroy', $purchase->id) }}" method="POST"
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
                                <h4>Batch / Lot Bahan Baku</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Batch</th>
                                                <th>Bahan Baku</th>
                                                <th>Satuan</th>
                                                <th>Quantity</th>
                                                <th>Sisa Batch</th>
                                                <th>Harga Beli</th>
                                                <th>Subtotal</th>
                                                <th>Kedaluwarsa</th>
                                                <th>Catatan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($purchase->items as $item)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>{{ $item->batch_code ?: '-' }}</td>
                                                    <td>{{ $item->bahanBaku?->name ?? '-' }}</td>
                                                    <td>{{ $item->bahanBaku?->satuan?->name ?? '-' }}</td>
                                                    <td>{{ FormatQty($item->quantity) }}</td>
                                                    <td>
                                                        @if ($purchase->isReceived())
                                                            <span class="badge badge-info">
                                                                {{ FormatQty($item->remaining_qty) }}
                                                            </span>
                                                        @else
                                                            <span class="badge badge-secondary">belum masuk stok</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ FormatMoney($item->unit_price) }}</td>
                                                    <td>{{ FormatMoney($item->subtotal) }}</td>
                                                    <td>{{ $item->expired_date?->format('d F Y') ?? '-' }}</td>
                                                    <td>{{ $item->description ?: '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="10" class="text-center text-muted">Belum ada batch.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="7" class="text-right">Total</th>
                                                <th>{{ FormatMoney($purchase->total) }}</th>
                                                <th colspan="2"></th>
                                            </tr>
                                        </tfoot>
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
            if (!confirm('Batalkan pembelian ini? Stok bahan baku akan dikembalikan.')) {
                e.preventDefault();
            }
        });
    </script>
@endpush
