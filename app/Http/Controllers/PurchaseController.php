<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $suppliers = Supplier::where('status', '!=', -1)->orderBy('name')->get();

        return view('pages.purchase.index', compact('suppliers'));
    }

    /**
     * DataTables server-side.
     *
     * Pencarian: nomor pembelian (kolom code), supplier (nama supplier), dan tanggal.
     * Filter: status, supplier, serta rentang tanggal pembelian.
     */
    public function data(Request $request)
    {
        $query = Purchase::with(['supplier', 'creator', 'items'])->orderBy('id', 'desc');

        if ($request->filled('code')) {
            $query->where('code', 'like', '%' . $request->input('code') . '%');
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('purchase_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('purchase_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('code', function (Purchase $purchase) {
                return $purchase->code ? e($purchase->code) : '-';
            })

            ->editColumn('purchase_date', function (Purchase $purchase) {
                return $purchase->purchase_date
                    ? $purchase->purchase_date->format('d F Y')
                    : '-';
            })

            ->editColumn('supplier_id', function (Purchase $purchase) {
                return $purchase->supplier ? e($purchase->supplier->name) : '-';
            })

            ->editColumn('total', function (Purchase $purchase) {
                return e(FormatMoney($purchase->total));
            })

            // jumlah batch bahan baku pada pembelian ini
            ->addColumn('item_count', function (Purchase $purchase) {
                $total = $purchase->items->count();

                return $total > 0
                    ? '<span class="badge badge-info">' . $total . ' Batch</span>'
                    : '<span class="badge badge-secondary">0</span>';
            })

            ->editColumn('status', function (Purchase $purchase) {
                if ($purchase->isReceived()) {
                    return '<span class="badge badge-success">Diterima</span>';
                }

                if ($purchase->isCancelled()) {
                    return '<span class="badge badge-danger">Dibatalkan</span>';
                }

                return '<span class="badge badge-warning">Draft</span>';
            })

            ->editColumn('created_by', function (Purchase $purchase) {
                return $purchase->creator ? e($purchase->creator->name) : '-';
            })

            // pencarian supplier berdasarkan nama
            // (kolom supplier_id ditandai non-searchable di sisi DataTables)
            ->filter(function ($query) use ($request) {
                $keyword = $request->input('search.value');

                if (filled($keyword)) {
                    $query->orWhereHas('supplier', function ($supplierQuery) use ($keyword) {
                        $supplierQuery->where('name', 'like', '%' . $keyword . '%');
                    });
                }
            }, true)

            ->addColumn('action', function (Purchase $purchase) {
                $html = '<a href="' . route('purchase.show', $purchase->id) . '"
                            class="btn btn-sm btn-primary btn-icon">
                            <i class="fas fa-eye"></i> Detail
                        </a>';

                if (!$purchase->isCancelled()) {
                    $html .= ' <a href="' . route('purchase.edit', $purchase->id) . '"
                                class="btn btn-sm btn-info btn-icon">
                                <i class="fas fa-edit"></i> Edit
                            </a>';
                }

                if ($purchase->isDraft()) {
                    $html .= ' <form action="' . route('purchase.receive', $purchase->id) . '"
                            method="POST"
                            class="d-inline ml-1 receive-form">
                            ' . csrf_field() . '
                            <button type="submit"
                                    class="btn btn-sm btn-success btn-icon confirm-receive">
                                <i class="fas fa-check"></i> Terima
                            </button>
                        </form>';
                }

                if (!$purchase->isCancelled()) {
                    $html .= ' <form action="' . route('purchase.destroy', $purchase->id) . '"
                            method="POST"
                            class="d-inline ml-1 delete-form">
                            ' . csrf_field() . '
                            ' . method_field('DELETE') . '
                            <button type="submit"
                                    class="btn btn-sm btn-danger btn-icon confirm-delete">
                                <i class="fas fa-times"></i> Batalkan
                            </button>
                        </form>';
                }

                return $html;
            })

            // Izinkan HTML hanya untuk kolom badge, status, dan action
            ->rawColumns(['item_count', 'status', 'action'])
            ->toJson();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $suppliers = Supplier::where('status', '!=', -1)->orderBy('name')->get();
        $bahanBakus = BahanBaku::with('satuan')->where('status', '!=', -1)->orderBy('name')->get();

        return view('pages.purchase.create', compact('suppliers', 'bahanBakus'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $rows = $this->extractItems($request);

        if (empty($rows)) {
            return redirect()->back()
                ->with('error', 'Minimal satu bahan baku dengan quantity lebih dari 0 harus diisi.')
                ->withInput();
        }

        $status = (int) $request->input('status', Purchase::STATUS_DRAFT);

        DB::transaction(function () use ($request, $rows, $status) {
            $purchase = Purchase::create([
                'code' => $this->generateCode($request->input('purchase_date')),
                'supplier_id' => $request->input('supplier_id'),
                'purchase_date' => $request->input('purchase_date'),
                'total' => collect($rows)->sum('subtotal'),
                'description' => $request->input('description'),
                'status' => $status,
                'created_by' => Auth::user()->id,
                'updated_by' => Auth::user()->id,
            ]);

            $this->saveItems($purchase, $rows);

            if ($status === Purchase::STATUS_RECEIVED) {
                $this->applyStock($purchase);
            }
        });

        return redirect()->route('purchase.index')->with('success', 'Pembelian berhasil disimpan');
    }

    /**
     * Aturan validasi header pembelian dan baris batch.
     */
    private function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer'],
            'purchase_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'items' => ['required', 'array', 'min:1'],
            'items.bahan_baku_id' => ['required', 'array', 'min:1'],
            'items.bahan_baku_id.*' => ['required', 'integer'],
            'items.quantity.*' => ['required', 'numeric', 'min:0.001'],
            'items.unit_price.*' => ['required', 'numeric', 'min:0'],
            'items.expired_date.*' => ['nullable', 'date'],
            'items.description.*' => ['nullable', 'string'],
        ];
    }

    /**
     * Pesan validasi.
     */
    private function messages(): array
    {
        return [
            'purchase_date.required' => 'Tanggal pembelian wajib diisi.',
            'purchase_date.date' => 'Tanggal pembelian tidak valid.',
            'items.required' => 'Minimal satu bahan baku harus diisi.',
            'items.bahan_baku_id.required' => 'Minimal satu bahan baku harus dipilih.',
            'items.bahan_baku_id.*.required' => 'Bahan baku pada setiap baris wajib dipilih.',
            'items.quantity.*.required' => 'Quantity pada setiap baris wajib diisi.',
            'items.quantity.*.min' => 'Quantity minimal 0.001.',
            'items.unit_price.*.required' => 'Harga beli pada setiap baris wajib diisi.',
        ];
    }

    /**
     * Konversi input baris batch (items[bahan_baku_id][] + items[quantity][] +
     * items[unit_price][] + items[expired_date][] + items[description][])
     * menjadi array baris yang siap disimpan.
     */
    private function extractItems(Request $request): array
    {
        $items = $request->input('items') ?? [];

        $ids = $items['bahan_baku_id'] ?? [];
        $quantities = $items['quantity'] ?? [];
        $prices = $items['unit_price'] ?? [];
        $expiredDates = $items['expired_date'] ?? [];
        $descriptions = $items['description'] ?? [];

        $rows = [];

        foreach ($ids as $index => $id) {
            if (empty($id)) {
                continue;
            }

            $quantity = (float) ($quantities[$index] ?? 0);
            $unitPrice = (float) (StoreMoney($prices[$index] ?? 0) ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $rows[] = [
                'bahan_baku_id' => (int) $id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $quantity * $unitPrice,
                'expired_date' => !empty($expiredDates[$index]) ? $expiredDates[$index] : null,
                'description' => $descriptions[$index] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Simpan baris pembelian. Setiap baris mendapat nomor batch sendiri
     * sehingga menjadi satu lot tersendiri di inventory.
     */
    private function saveItems(Purchase $purchase, array $rows): void
    {
        foreach (array_values($rows) as $index => $row) {
            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'bahan_baku_id' => $row['bahan_baku_id'],
                'batch_code' => $purchase->code . '-' . ($index + 1),
                'quantity' => $row['quantity'],
                'remaining_qty' => $purchase->isReceived() ? $row['quantity'] : 0,
                'unit_price' => $row['unit_price'],
                'subtotal' => $row['subtotal'],
                'expired_date' => $row['expired_date'],
                'description' => $row['description'],
            ]);
        }
    }

    /**
     * Nomor pembelian: PB-YYYYMMDD-0001 (urut per hari, tidak pernah dipakai ulang).
     */
    private function generateCode(?string $purchaseDate): string
    {
        $date = Carbon::parse($purchaseDate)->format('Ymd');
        $prefix = 'PB-' . $date . '-';

        $lastCode = Purchase::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->value('code');

        $sequence = $lastCode ? ((int) substr($lastCode, -4)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Terapkan pembelian ke stok: setiap batch masuk ke stok bahan baku,
     * harga beli terakhir diperbarui, dan pergerakan dicatat ke kartu stok.
     */
    private function applyStock(Purchase $purchase): void
    {
        foreach ($purchase->items as $item) {
            $item->update(['remaining_qty' => $item->quantity]);

            $bahanBaku = BahanBaku::find($item->bahan_baku_id);

            if (!$bahanBaku) {
                continue;
            }

            $balance = (float) $bahanBaku->stock + (float) $item->quantity;

            $bahanBaku->update([
                'stock' => $balance,
                'price' => $item->unit_price,
                'updated_by' => Auth::user()->id,
            ]);

            $this->recordMovement(
                $bahanBaku,
                $item,
                $purchase,
                StockMovement::TYPE_IN,
                (float) $item->quantity,
                0,
                $balance,
                'Pembelian diterima (batch ' . $item->batch_code . ')'
            );
        }
    }

    /**
     * Kembalikan stok ke kondisi sebelum pembelian diterima.
     * Hanya dipanggil setelah dipastikan seluruh batch belum terpakai.
     */
    private function revertStock(Purchase $purchase, string $reason): void
    {
        foreach ($purchase->items as $item) {
            $bahanBaku = BahanBaku::find($item->bahan_baku_id);

            if ($bahanBaku) {
                $balance = max(0, (float) $bahanBaku->stock - (float) $item->quantity);

                $bahanBaku->update([
                    'stock' => $balance,
                    'updated_by' => Auth::user()->id,
                ]);

                $this->recordMovement(
                    $bahanBaku,
                    $item,
                    $purchase,
                    StockMovement::TYPE_OUT,
                    0,
                    (float) $item->quantity,
                    $balance,
                    $reason . ' (batch ' . $item->batch_code . ')'
                );
            }

            $item->update(['remaining_qty' => 0]);
        }
    }

    /**
     * Catat pergerakan stok ke kartu stok.
     */
    private function recordMovement(
        BahanBaku $bahanBaku,
        PurchaseItem $item,
        Purchase $purchase,
        string $type,
        float $quantityIn,
        float $quantityOut,
        float $balance,
        string $description
    ): void {
        StockMovement::create([
            'bahan_baku_id' => $bahanBaku->id,
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $item->id,
            'batch_code' => $item->batch_code,
            'movement_date' => $purchase->purchase_date,
            'type' => $type,
            'quantity_in' => $quantityIn,
            'quantity_out' => $quantityOut,
            'balance' => $balance,
            'unit_price' => $item->unit_price,
            'reference' => $purchase->code,
            'description' => $description,
            'created_by' => Auth::user()->id,
        ]);
    }

    /**
     * Pembelian yang sudah diterima aman diubah/dibatalkan hanya jika seluruh
     * batch belum terpakai (remaining_qty masih sama dengan quantity).
     */
    private function stockIsUntouched(Purchase $purchase): bool
    {
        return $purchase->items->every(fn (PurchaseItem $item) => $item->isUntouched());
    }

    /**
     * Display the specified resource (detail pembelian beserta batch-nya).
     */
    public function show(string $id)
    {
        $purchase = Purchase::with([
            'supplier',
            'creator',
            'updater',
            'items.bahanBaku.satuan',
        ])->findOrFail($id);

        return view('pages.purchase.show', compact('purchase'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $purchase = Purchase::with(['items.bahanBaku.satuan'])->findOrFail($id);

        if ($purchase->isCancelled()) {
            return redirect()->route('purchase.index')
                ->with('error', 'Pembelian yang sudah dibatalkan tidak dapat diubah.');
        }

        $suppliers = Supplier::where('status', '!=', -1)->orderBy('name')->get();
        $bahanBakus = BahanBaku::with('satuan')->where('status', '!=', -1)->orderBy('name')->get();

        return view('pages.purchase.edit', compact('purchase', 'suppliers', 'bahanBakus'));
    }

    /**
     * Update the specified resource in storage.
     *
     * Aturan aman terhadap stok: pembelian yang sudah diterima hanya dapat diubah
     * bila seluruh batch belum terpakai. Stok lama ditarik kembali lalu diterapkan ulang.
     */
    public function update(Request $request, string $id)
    {
        $purchase = Purchase::with('items')->findOrFail($id);

        if ($purchase->isCancelled()) {
            return redirect()->route('purchase.index')
                ->with('error', 'Pembelian yang sudah dibatalkan tidak dapat diubah.');
        }

        if ($purchase->isReceived() && !$this->stockIsUntouched($purchase)) {
            return redirect()->route('purchase.index')
                ->with('error', 'Pembelian tidak dapat diubah karena sebagian stok batch sudah terpakai.');
        }

        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $rows = $this->extractItems($request);

        if (empty($rows)) {
            return redirect()->back()
                ->with('error', 'Minimal satu bahan baku dengan quantity lebih dari 0 harus diisi.')
                ->withInput();
        }

        $status = (int) $request->input('status', Purchase::STATUS_DRAFT);

        DB::transaction(function () use ($purchase, $request, $rows, $status) {
            // tarik stok lama, lalu ganti seluruh baris batch dengan yang baru
            if ($purchase->isReceived()) {
                $this->revertStock($purchase, 'Koreksi pembelian');
            }

            $purchase->items()->delete();

            $purchase->update([
                'supplier_id' => $request->input('supplier_id'),
                'purchase_date' => $request->input('purchase_date'),
                'total' => collect($rows)->sum('subtotal'),
                'description' => $request->input('description'),
                'status' => $status,
                'updated_by' => Auth::user()->id,
            ]);

            $this->saveItems($purchase, $rows);

            // relasi items masih ter-cache: muat ulang sebelum diterapkan ke stok
            $purchase->load('items');

            if ($status === Purchase::STATUS_RECEIVED) {
                $this->applyStock($purchase);
            }
        });

        return redirect()->route('purchase.index')->with('success', 'Pembelian berhasil diperbarui');
    }

    /**
     * Terima pembelian draft: stok bahan baku dan batch bertambah.
     */
    public function receive(string $id)
    {
        $purchase = Purchase::with('items')->findOrFail($id);

        if (!$purchase->isDraft()) {
            return redirect()->route('purchase.index')
                ->with('error', 'Hanya pembelian berstatus draft yang dapat diterima.');
        }

        DB::transaction(function () use ($purchase) {
            $purchase->update([
                'status' => Purchase::STATUS_RECEIVED,
                'updated_by' => Auth::user()->id,
            ]);

            $this->applyStock($purchase);
        });

        return redirect()->route('purchase.index')->with('success', 'Pembelian diterima, stok bahan baku bertambah');
    }

    /**
     * Batalkan pembelian (soft delete via status -1) dengan aturan aman terhadap stok.
     *
     * Bila pembelian sudah diterima, stok dikembalikan hanya jika seluruh batch
     * belum pernah terpakai; jika tidak, pembatalan ditolak agar stok tidak minus.
     */
    public function destroy(string $id)
    {
        $purchase = Purchase::with('items')->findOrFail($id);

        if ($purchase->isCancelled()) {
            return redirect()->route('purchase.index')
                ->with('error', 'Pembelian ini sudah dibatalkan.');
        }

        if ($purchase->isReceived() && !$this->stockIsUntouched($purchase)) {
            return redirect()->route('purchase.index')
                ->with('error', 'Pembelian tidak dapat dibatalkan karena sebagian stok batch sudah terpakai.');
        }

        DB::transaction(function () use ($purchase) {
            if ($purchase->isReceived()) {
                $this->revertStock($purchase, 'Pembatalan pembelian');
            }

            $purchase->update([
                'status' => Purchase::STATUS_CANCELLED,
                'updated_by' => Auth::user()->id,
            ]);
        });

        return redirect()->route('purchase.index')->with('success', 'Pembelian berhasil dibatalkan');
    }
}
