<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

/**
 * Stock opname (hitung fisik) stok bahan baku.
 *
 * Draft belum memengaruhi stok. Saat opname diselesaikan, selisih antara hasil
 * hitung fisik dan stok sistem diterapkan ke stok bahan baku dan dicatat di
 * kartu stok sebagai penyesuaian (adjustment).
 */
class StockOpnameController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('pages.stock_opname.index');
    }

    /**
     * DataTables server-side (pencarian nomor opname, filter status & rentang tanggal).
     */
    public function data(Request $request)
    {
        $query = StockOpname::with(['creator', 'items'])->orderBy('id', 'desc');

        if ($request->filled('code')) {
            $query->where('code', 'like', '%' . $request->input('code') . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('opname_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('opname_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->editColumn('code', function (StockOpname $opname) {
                return $opname->code ? e($opname->code) : '-';
            })

            ->editColumn('opname_date', function (StockOpname $opname) {
                return $opname->opname_date
                    ? $opname->opname_date->format('d F Y')
                    : '-';
            })

            ->addColumn('item_count', function (StockOpname $opname) {
                $total = $opname->items->count();

                return $total > 0
                    ? '<span class="badge badge-info">' . $total . ' Item</span>'
                    : '<span class="badge badge-secondary">0</span>';
            })

            // ringkasan selisih: berapa item lebih / kurang / sesuai
            ->addColumn('difference_summary', function (StockOpname $opname) {
                $increase = $opname->items->filter(fn (StockOpnameItem $item) => $item->isIncrease())->count();
                $decrease = $opname->items->filter(fn (StockOpnameItem $item) => $item->isDecrease())->count();
                $match = $opname->items->filter(fn (StockOpnameItem $item) => $item->isMatch())->count();

                return '<span class="badge badge-success">+' . $increase . ' lebih</span>
                        <span class="badge badge-danger">-' . $decrease . ' kurang</span>
                        <span class="badge badge-secondary">' . $match . ' sesuai</span>';
            })

            ->editColumn('status', function (StockOpname $opname) {
                if ($opname->isFinal()) {
                    return '<span class="badge badge-success">Selesai</span>';
                }

                if ($opname->isCancelled()) {
                    return '<span class="badge badge-danger">Dibatalkan</span>';
                }

                return '<span class="badge badge-warning">Draft</span>';
            })

            ->editColumn('created_by', function (StockOpname $opname) {
                return $opname->creator ? e($opname->creator->name) : '-';
            })

            ->addColumn('action', function (StockOpname $opname) {
                $html = '<a href="' . route('stock_opname.show', $opname->id) . '"
                            class="btn btn-sm btn-primary btn-icon">
                            <i class="fas fa-eye"></i> Detail
                        </a>';

                if ($opname->isDraft()) {
                    $html .= ' <a href="' . route('stock_opname.edit', $opname->id) . '"
                                class="btn btn-sm btn-info btn-icon">
                                <i class="fas fa-edit"></i> Edit
                            </a>';

                    $html .= ' <form action="' . route('stock_opname.finalize', $opname->id) . '"
                            method="POST"
                            class="d-inline ml-1 finalize-form">
                            ' . csrf_field() . '
                            <button type="submit"
                                    class="btn btn-sm btn-success btn-icon confirm-finalize">
                                <i class="fas fa-check"></i> Selesaikan
                            </button>
                        </form>';
                }

                if (!$opname->isCancelled()) {
                    $html .= ' <form action="' . route('stock_opname.destroy', $opname->id) . '"
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

            ->rawColumns(['item_count', 'difference_summary', 'status', 'action'])
            ->toJson();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $bahanBakus = BahanBaku::with('satuan')->where('status', '!=', -1)->orderBy('name')->get();

        return view('pages.stock_opname.create', compact('bahanBakus'));
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
                ->with('error', 'Minimal satu bahan baku harus diisi.')
                ->withInput();
        }

        $status = (int) $request->input('status', StockOpname::STATUS_DRAFT);

        DB::transaction(function () use ($request, $rows, $status) {
            $opname = StockOpname::create([
                'code' => $this->generateCode($request->input('opname_date')),
                'opname_date' => $request->input('opname_date'),
                'status' => $status,
                'description' => $request->input('description'),
                'created_by' => Auth::user()->id,
                'updated_by' => Auth::user()->id,
            ]);

            $this->saveItems($opname, $rows);

            $opname->load('items');

            if ($status === StockOpname::STATUS_FINAL) {
                $this->applyAdjustment($opname);
            }
        });

        return redirect()->route('stock_opname.index')->with('success', 'Stock opname berhasil disimpan');
    }

    /**
     * Aturan validasi header & baris opname.
     */
    private function rules(): array
    {
        return [
            'opname_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'items' => ['required', 'array', 'min:1'],
            'items.bahan_baku_id' => ['required', 'array', 'min:1'],
            'items.bahan_baku_id.*' => ['required', 'integer'],
            'items.physical_stock.*' => ['required', 'numeric', 'min:0'],
            'items.description.*' => ['nullable', 'string'],
        ];
    }

    /**
     * Pesan validasi.
     */
    private function messages(): array
    {
        return [
            'opname_date.required' => 'Tanggal opname wajib diisi.',
            'opname_date.date' => 'Tanggal opname tidak valid.',
            'items.required' => 'Minimal satu bahan baku harus diisi.',
            'items.bahan_baku_id.required' => 'Minimal satu bahan baku harus dipilih.',
            'items.bahan_baku_id.*.required' => 'Bahan baku pada setiap baris wajib dipilih.',
            'items.physical_stock.*.required' => 'Jumlah fisik pada setiap baris wajib diisi.',
            'items.physical_stock.*.numeric' => 'Jumlah fisik harus berupa angka.',
            'items.physical_stock.*.min' => 'Jumlah fisik tidak boleh negatif.',
        ];
    }

    /**
     * Konversi input baris (items[bahan_baku_id][] + items[physical_stock][] + items[description][])
     * menjadi baris opname. Stok sistem diambil dari master bahan baku (bukan dari form)
     * sehingga selisih selalu dihitung di sisi server.
     */
    private function extractItems(Request $request): array
    {
        $items = $request->input('items') ?? [];

        $ids = $items['bahan_baku_id'] ?? [];
        $physicals = $items['physical_stock'] ?? [];
        $descriptions = $items['description'] ?? [];

        $rows = [];
        $used = [];

        foreach ($ids as $index => $id) {
            if (empty($id) || isset($used[$id])) {
                continue;   // satu bahan baku hanya boleh muncul sekali
            }

            $bahanBaku = BahanBaku::find($id);

            if (!$bahanBaku) {
                continue;
            }

            $used[$id] = true;

            $systemStock = (float) $bahanBaku->stock;
            $physicalStock = (float) ($physicals[$index] ?? 0);

            $rows[] = [
                'bahan_baku_id' => $bahanBaku->id,
                'system_stock' => $systemStock,
                'physical_stock' => $physicalStock,
                'difference' => $physicalStock - $systemStock,
                'unit_price' => (float) $bahanBaku->price,
                'description' => $descriptions[$index] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Simpan baris hasil hitung fisik.
     */
    private function saveItems(StockOpname $opname, array $rows): void
    {
        foreach ($rows as $row) {
            StockOpnameItem::create([
                'stock_opname_id' => $opname->id,
                'bahan_baku_id' => $row['bahan_baku_id'],
                'system_stock' => $row['system_stock'],
                'physical_stock' => $row['physical_stock'],
                'difference' => $row['difference'],
                'unit_price' => $row['unit_price'],
                'description' => $row['description'],
            ]);
        }
    }

    /**
     * Nomor opname: SO-YYYYMMDD-0001 (urut per hari, tidak dipakai ulang).
     */
    private function generateCode(?string $opnameDate): string
    {
        $date = Carbon::parse($opnameDate)->format('Ymd');
        $prefix = 'SO-' . $date . '-';

        $lastCode = StockOpname::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->value('code');

        $sequence = $lastCode ? ((int) substr($lastCode, -4)) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Terapkan selisih opname ke stok bahan baku & catat di kartu stok.
     */
    private function applyAdjustment(StockOpname $opname): void
    {
        foreach ($opname->items as $item) {
            $difference = (float) $item->difference;

            if ($difference === 0.0) {
                continue;   // stok sudah sesuai, tidak perlu penyesuaian
            }

            $bahanBaku = BahanBaku::find($item->bahan_baku_id);

            if (!$bahanBaku) {
                continue;
            }

            $balance = max(0, (float) $bahanBaku->stock + $difference);

            $bahanBaku->update([
                'stock' => $balance,
                'updated_by' => Auth::user()->id,
            ]);

            $this->recordMovement(
                $opname,
                $item,
                $bahanBaku,
                $difference,
                $balance,
                'Stock opname ' . $opname->code
            );

            // jaga agar total sisa batch selalu sama dengan stok bahan baku
            SyncBahanBakuBatches($bahanBaku);
        }
    }

    /**
     * Kembalikan stok ke kondisi sebelum opname diselesaikan.
     */
    private function revertAdjustment(StockOpname $opname): void
    {
        foreach ($opname->items as $item) {
            $difference = (float) $item->difference;

            if ($difference === 0.0) {
                continue;
            }

            $bahanBaku = BahanBaku::find($item->bahan_baku_id);

            if (!$bahanBaku) {
                continue;
            }

            $balance = max(0, (float) $bahanBaku->stock - $difference);

            $bahanBaku->update([
                'stock' => $balance,
                'updated_by' => Auth::user()->id,
            ]);

            $this->recordMovement(
                $opname,
                $item,
                $bahanBaku,
                -$difference,
                $balance,
                'Pembatalan stock opname ' . $opname->code
            );

            // kembalikan juga sisa batch agar tetap sama dengan stok
            SyncBahanBakuBatches($bahanBaku);
        }
    }

    /**
     * Catat penyesuaian stok ke kartu stok.
     * $difference positif = stok bertambah, negatif = stok berkurang.
     */
    private function recordMovement(
        StockOpname $opname,
        StockOpnameItem $item,
        BahanBaku $bahanBaku,
        float $difference,
        float $balance,
        string $description
    ): void {
        StockMovement::create([
            'bahan_baku_id' => $bahanBaku->id,
            'stock_opname_id' => $opname->id,
            'stock_opname_item_id' => $item->id,
            'movement_date' => $opname->opname_date,
            'type' => StockMovement::TYPE_ADJUSTMENT,
            'quantity_in' => $difference > 0 ? $difference : 0,
            'quantity_out' => $difference < 0 ? abs($difference) : 0,
            'balance' => $balance,
            'unit_price' => $item->unit_price,
            'reference' => $opname->code,
            'description' => $description,
            'created_by' => Auth::user()->id,
        ]);
    }

    /**
     * Opname yang sudah selesai aman dibatalkan hanya bila belum ada
     * pergerakan stok lain setelahnya pada bahan baku yang disesuaikan.
     */
    private function hasLaterMovements(StockOpname $opname): bool
    {
        foreach ($opname->items as $item) {
            if ((float) $item->difference === 0.0) {
                continue;
            }

            $lastMovementId = StockMovement::where('stock_opname_id', $opname->id)
                ->where('stock_opname_item_id', $item->id)
                ->max('id');

            if (!$lastMovementId) {
                continue;
            }

            $hasLater = StockMovement::where('bahan_baku_id', $item->bahan_baku_id)
                ->where('id', '>', $lastMovementId)
                ->exists();

            if ($hasLater) {
                return true;
            }
        }

        return false;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $opname = StockOpname::with([
            'creator',
            'updater',
            'items.bahanBaku.satuan',
        ])->findOrFail($id);

        return view('pages.stock_opname.show', compact('opname'));
    }

    /**
     * Show the form for editing the specified resource (hanya draft).
     */
    public function edit(string $id)
    {
        $opname = StockOpname::with(['items.bahanBaku.satuan'])->findOrFail($id);

        if (!$opname->isDraft()) {
            return redirect()->route('stock_opname.index')
                ->with('error', 'Hanya stock opname berstatus draft yang dapat diubah.');
        }

        $bahanBakus = BahanBaku::with('satuan')->where('status', '!=', -1)->orderBy('name')->get();

        return view('pages.stock_opname.edit', compact('opname', 'bahanBakus'));
    }

    /**
     * Update the specified resource in storage (hanya draft).
     */
    public function update(Request $request, string $id)
    {
        $opname = StockOpname::with('items')->findOrFail($id);

        if (!$opname->isDraft()) {
            return redirect()->route('stock_opname.index')
                ->with('error', 'Hanya stock opname berstatus draft yang dapat diubah.');
        }

        $validator = Validator::make($request->all(), $this->rules(), $this->messages());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $rows = $this->extractItems($request);

        if (empty($rows)) {
            return redirect()->back()
                ->with('error', 'Minimal satu bahan baku harus diisi.')
                ->withInput();
        }

        $status = (int) $request->input('status', StockOpname::STATUS_DRAFT);

        DB::transaction(function () use ($opname, $request, $rows, $status) {
            // draft belum menyentuh stok, jadi baris lama cukup diganti
            $opname->items()->delete();

            $opname->update([
                'opname_date' => $request->input('opname_date'),
                'description' => $request->input('description'),
                'status' => $status,
                'updated_by' => Auth::user()->id,
            ]);

            $this->saveItems($opname, $rows);

            $opname->load('items');

            if ($status === StockOpname::STATUS_FINAL) {
                $this->applyAdjustment($opname);
            }
        });

        return redirect()->route('stock_opname.index')->with('success', 'Stock opname berhasil diperbarui');
    }

    /**
     * Selesaikan stock opname draft: selisih langsung diterapkan ke stok.
     */
    public function finalize(string $id)
    {
        $opname = StockOpname::with('items')->findOrFail($id);

        if (!$opname->isDraft()) {
            return redirect()->route('stock_opname.index')
                ->with('error', 'Hanya stock opname berstatus draft yang dapat diselesaikan.');
        }

        DB::transaction(function () use ($opname) {
            $opname->update([
                'status' => StockOpname::STATUS_FINAL,
                'updated_by' => Auth::user()->id,
            ]);

            $this->applyAdjustment($opname);
        });

        return redirect()->route('stock_opname.index')
            ->with('success', 'Stock opname selesai, stok bahan baku sudah disesuaikan');
    }

    /**
     * Batalkan stock opname (soft delete via status -1).
     *
     * Draft: hanya ditandai dibatalkan. Selesai: penyesuaian stok dikembalikan
     * bila belum ada pergerakan stok lain setelahnya pada bahan baku terkait.
     */
    public function destroy(string $id)
    {
        $opname = StockOpname::with('items')->findOrFail($id);

        if ($opname->isCancelled()) {
            return redirect()->route('stock_opname.index')
                ->with('error', 'Stock opname ini sudah dibatalkan.');
        }

        if ($opname->isFinal() && $this->hasLaterMovements($opname)) {
            return redirect()->route('stock_opname.index')
                ->with('error', 'Stock opname tidak dapat dibatalkan karena sudah ada pergerakan stok setelahnya.');
        }

        DB::transaction(function () use ($opname) {
            if ($opname->isFinal()) {
                $this->revertAdjustment($opname);
            }

            $opname->update([
                'status' => StockOpname::STATUS_CANCELLED,
                'updated_by' => Auth::user()->id,
            ]);
        });

        return redirect()->route('stock_opname.index')->with('success', 'Stock opname berhasil dibatalkan');
    }
}
