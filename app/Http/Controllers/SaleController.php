<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\Menu;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItemUsage;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

/**
 * Transaksi penjualan / kasir.
 *
 * Menjual MENU: setiap porsi mengurangi stok bahan baku sesuai resep
 * (menu_bahan_bakus) memakai FEFO per batch. Pemakaian tercatat di
 * kartu stok (type = out) dan jejak batch-nya di sale_item_usages agar
 * pembatalan bisa mengembalikan stok ke batch semula secara eksak.
 */
class SaleController extends Controller
{
    public function index()
    {
        return view('pages.sale.index');
    }

    public function data(Request $request)
    {
        $query = Sale::with(['creator', 'items'])->orderBy('id', 'desc');

        if ($request->filled('code')) {
            $query->where('code', 'like', '%' . $request->input('code') . '%');
        }
        if ($request->filled('date_from')) {
            $query->whereDate('sale_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sale_date', '<=', $request->input('date_to'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('code', fn (Sale $s) => $s->code ? e($s->code) : '-')
            ->editColumn('sale_date', fn (Sale $s) => $s->sale_date ? $s->sale_date->format('d F Y') : '-')
            ->addColumn('item_count', function (Sale $s) {
                $qty = $s->items->sum('quantity');
                return $s->items->count() > 0
                    ? '<span class="badge badge-info">' . $s->items->count() . ' Menu (' . FormatQty($qty) . ' porsi)</span>'
                    : '<span class="badge badge-secondary">0</span>';
            })
            ->editColumn('total', fn (Sale $s) => e(FormatMoney($s->total)))
            ->editColumn('payment_method', fn (Sale $s) => $s->payment_method ? e(ucfirst($s->payment_method)) : '-')
            ->editColumn('status', function (Sale $s) {
                return $s->isCompleted()
                    ? '<span class="badge badge-success">Selesai</span>'
                    : '<span class="badge badge-danger">Dibatalkan</span>';
            })
            ->editColumn('created_by', fn (Sale $s) => $s->creator ? e($s->creator->name) : '-')
            ->addColumn('action', function (Sale $s) {
                $a = '<a href="' . route('sale.show', $s->id) . '" class="btn btn-sm btn-info">Detail</a> ';
                $a .= '<a href="' . route('sale.print', $s->id) . '" target="_blank" class="btn btn-sm btn-secondary">Cetak</a> ';
                if ($s->isCompleted()) {
                    $a .= '<form action="' . route('sale.destroy', $s->id) . '" method="POST" class="delete-form d-inline">'
                        . csrf_field() . method_field('DELETE')
                        . '<button type="submit" class="btn btn-sm btn-danger">Batalkan</button></form>';
                }
                return $a;
            })
            ->rawColumns(['item_count', 'status', 'action'])
            ->toJson();
    }

    /**
     * Halaman kasir: pilih menu + jumlah, bayar, stok bahan otomatis berkurang.
     */
    public function create()
    {
        $menus = Menu::with(['category', 'bahanBakus.satuan'])
            ->where('status', 1)->orderBy('name')->get();

        // estimasi porsi dihitung di server supaya sama dengan perhitungan saat menyimpan
        $saleDate = Carbon::today()->toDateString();
        $stockInfo = [];

        foreach ($menus as $menu) {
            $stockInfo[$menu->id] = $this->menuAvailability($menu, $saleDate);
        }

        return view('pages.sale.create', compact('menus', 'stockInfo', 'saleDate'));
    }

    /**
     * API kecil untuk kasir: info resep + estimasi porsi yang bisa dijual.
     */
    public function menuInfo(string $id)
    {
        $menu = Menu::with(['bahanBakus.satuan'])->findOrFail($id);
        $info = $this->menuAvailability($menu, Carbon::today()->toDateString());

        return response()->json([
            'id' => $menu->id,
            'name' => $menu->name,
            'price' => (float) $menu->price,
            'price_formatted' => FormatMoney($menu->price),
            'has_recipe' => $info['recipes'] !== [],
            'max_qty' => $info['max_qty'],
            'expired_batches' => $info['expired_batches'],
            'recipes' => $info['recipes'],
        ]);
    }

    /**
     * Simpan transaksi: validasi stok, kurangi FEFO per batch,
     * catat kartu stok + jejak pemakaian batch.
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
                ->with('error', 'Minimal satu menu dengan jumlah lebih dari 0 harus diisi.')
                ->withInput();
        }

        $menus = Menu::with('bahanBakus')->whereIn('id', collect($rows)->pluck('menu_id'))->get()->keyBy('id');

        $subtotal = collect($rows)->sum('subtotal');
        $discount = (float) (StoreMoney($request->input('discount')) ?? 0);
        $tax = (float) (StoreMoney($request->input('tax')) ?? 0);
        $total = max(0, $subtotal - $discount + $tax);
        $paid = (float) (StoreMoney($request->input('paid')) ?? 0);

        if ($paid < $total) {
            return redirect()->back()->with('error', 'Uang dibayar kurang dari total belanja.')->withInput();
        }

        $saleDate = $request->filled('sale_date')
            ? Carbon::parse($request->input('sale_date'))->toDateString()
            : Carbon::today()->toDateString();

        try {
            $sale = DB::transaction(function () use ($request, $rows, $menus, $subtotal, $discount, $tax, $total, $paid, $saleDate) {
                $needs = $this->calculateNeeds($rows, $menus);
                $this->assertStockSufficient($needs, $saleDate);

                $sale = Sale::create([
                    'code' => $this->generateCode(),
                    'sale_date' => $saleDate,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'total' => $total,
                    'paid' => $paid,
                    'change_amount' => $paid - $total,
                    'payment_method' => $request->input('payment_method', 'cash'),
                    'status' => Sale::STATUS_COMPLETED,
                    'description' => $request->input('description'),
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                ]);

                foreach ($rows as $row) {
                    $sale->items()->create([
                        'menu_id' => $row['menu_id'],
                        'quantity' => $row['quantity'],
                        'unit_price' => $row['unit_price'],
                        'subtotal' => $row['subtotal'],
                    ]);
                }

                $sale->load('items');
                $this->applyStock($sale, $menus);

                return $sale;
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        // kasir bisa langsung diarahkan ke struk thermal setelah transaksi tersimpan
        if ($request->boolean('print_receipt')) {
            return redirect()->route('sale.print', $sale->id)
                ->with('success', 'Transaksi ' . $sale->code . ' berhasil, stok bahan baku sudah dikurangi');
        }

        return redirect()->route('sale.show', $sale->id)->with('success', 'Transaksi berhasil, stok bahan baku sudah dikurangi');
    }

    /**
     * Detail transaksi (tanpa struk).
     */
    public function show(string $id)
    {
        $sale = Sale::with(['items.menu', 'usages.bahanBaku.satuan', 'usages.batch', 'creator'])->findOrFail($id);

        return view('pages.sale.show', compact('sale'));
    }

    /**
     * Struk / invoice siap cetak untuk printer thermal.
     *
     * Halaman ini berdiri sendiri (tanpa layout admin) dengan lebar kertas
     * sesuai printer thermal: 80mm (default) atau 58mm lewat ?paper=58.
     * Browser mencetak ke printer thermal memakai dialog print (driver printer),
     * jadi tidak perlu aplikasi tambahan.
     */
    public function printReceipt(Request $request, string $id)
    {
        $sale = Sale::with(['items.menu', 'usages.bahanBaku.satuan', 'creator'])->findOrFail($id);

        $paperWidth = $this->paperWidth($request);
        $paperHeight = $this->paperHeight($request);

        return view('pages.sale.print', [
            'sale' => $sale,
            'paperWidth' => $paperWidth,
            'paperHeight' => $paperHeight,
            'charsPerLine' => config('pos.chars_per_line.' . $paperWidth, 48),
            'autoPrint' => config('pos.thermal_auto_print', true),
        ]);
    }

    /**
     * Lebar kertas printer thermal (mm). Hanya 58 dan 80 yang didukung.
     */
    protected function paperWidth(Request $request): int
    {
        $width = (int) $request->input('paper', config('pos.thermal_paper_width', 80));

        return in_array($width, [58, 80], true) ? $width : 80;
    }

    /**
     * Tinggi kertas printer thermal (mm). 0 = tinggi mengikuti isi struk,
     * sehingga kertas roll tidak terbuang dan ukuran A4 tidak terpakai.
     */
    protected function paperHeight(Request $request): int
    {
        $height = (int) $request->input('height', config('pos.thermal_paper_height', 0));

        return ($height > 0 && $height <= 1000) ? $height : 0;
    }

    /**
     * Batalkan transaksi: kembalikan stok ke batch semula secara eksak
     * (berdasar jejak sale_item_usages), catat kartu stok masuk.
     */
    public function destroy(string $id)
    {
        $sale = Sale::with(['items', 'usages'])->findOrFail($id);

        if ($sale->isCancelled()) {
            return redirect()->route('sale.index')->with('error', 'Transaksi ini sudah dibatalkan.');
        }

        DB::transaction(function () use ($sale) {
            $this->revertStock($sale);
            $sale->update(['status' => Sale::STATUS_CANCELLED, 'updated_by' => Auth::user()->id]);
        });

        return redirect()->route('sale.index')->with('success', 'Transaksi ' . $sale->code . ' dibatalkan, stok bahan baku dikembalikan ke batch semula');
    }

    protected function rules(): array
    {
        return [
            'sale_date' => ['nullable', 'date'],
            'menu_id' => ['required', 'array', 'min:1'],
            'menu_id.*' => ['required', 'integer', 'exists:menus,id'],
            'quantity' => ['required', 'array', 'min:1'],
            'quantity.*' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable'],
            'tax' => ['nullable'],
            'paid' => ['required'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
            'print_receipt' => ['nullable', 'boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'menu_id.required' => 'Minimal satu menu harus dipilih.',
            'quantity.required' => 'Jumlah setiap menu harus diisi.',
            'paid.required' => 'Nominal pembayaran harus diisi.',
        ];
    }

    protected function extractItems(Request $request): array
    {
        $menuIds = $request->input('menu_id', []);
        $quantities = $request->input('quantity', []);
        $menus = Menu::whereIn('id', $menuIds)->where('status', 1)->get()->keyBy('id');
        $rows = [];
        foreach ($menuIds as $index => $menuId) {
            $qty = (float) ($quantities[$index] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $menu = $menus->get($menuId);
            if (!$menu) {
                continue;
            }
            $price = (float) $menu->price;
            $rows[] = ['menu_id' => $menu->id, 'quantity' => $qty, 'unit_price' => $price, 'subtotal' => $qty * $price];
        }
        return $rows;
    }

    protected function calculateNeeds(array $rows, $menus): array
    {
        $needs = [];
        foreach ($rows as $row) {
            $menu = $menus->get($row['menu_id']);
            if (!$menu) {
                continue;
            }
            foreach ($menu->bahanBakus as $bahan) {
                $need = (float) $bahan->pivot->quantity * (float) $row['quantity'];
                if ($need <= 0) {
                    continue;
                }
                if (!isset($needs[$bahan->id])) {
                    $needs[$bahan->id] = ['need' => 0, 'name' => $bahan->name];
                }
                $needs[$bahan->id]['need'] += $need;
            }
        }
        return $needs;
    }

    protected function assertStockSufficient(array $needs, string $saleDate): void
    {
        if (empty($needs)) {
            return;
        }

        $stocks = BahanBaku::whereIn('id', array_keys($needs))->lockForUpdate()->get()->keyBy('id');

        foreach ($needs as $bahanId => $need) {
            $bahan = $stocks->get($bahanId);

            if (!$bahan) {
                throw new \RuntimeException('Bahan baku pada resep menu tidak ditemukan. Transaksi dibatalkan.');
            }

            $available = $this->availability($bahan, $saleDate);

            if ($available['available'] < $need['need'] - 1e-9) {
                $note = $available['expired_batches'] > 0
                    ? ' (' . $available['expired_batches'] . ' batch kedaluwarsa tidak dihitung)'
                    : '';

                throw new \RuntimeException(
                    'Stok "' . $need['name'] . '" tidak cukup: butuh ' . FormatQty($need['need'])
                    . ', layak dipakai ' . FormatQty($available['available'])
                    . ' dari stok ' . FormatQty($bahan->stock) . $note
                    . '. Kurangi jumlah menu atau tambah stok dulu.'
                );
            }
        }
    }

    /**
     * Ketersediaan tiap bahan baku pada resep menu + estimasi porsi yang bisa dijual.
     *
     * @return array{recipes: array<int, array<string, mixed>>, max_qty: int|null, expired_batches: int}
     */
    protected function menuAvailability(Menu $menu, string $saleDate): array
    {
        $recipes = [];
        $limits = [];
        $expiredBatches = 0;

        foreach ($menu->bahanBakus as $bahan) {
            $available = $this->availability($bahan, $saleDate);
            $need = (float) $bahan->pivot->quantity;
            $expiredBatches += $available['expired_batches'];

            $recipes[] = [
                'bahan_baku_id' => $bahan->id,
                'bahan_baku_name' => $bahan->name,
                'satuan' => $bahan->satuan ? $bahan->satuan->name : '-',
                'need_per_portion' => $need,
                'stock' => (float) $bahan->stock,
                'available' => $available['available'],
                'expired_batches' => $available['expired_batches'],
                'max_portion' => $need > 0 ? (int) floor($available['available'] / $need) : null,
            ];

            if ($need > 0) {
                $limits[] = (int) floor($available['available'] / $need);
            }
        }

        return [
            'recipes' => $recipes,
            'max_qty' => $limits === [] ? null : max(0, min($limits)),
            'expired_batches' => $expiredBatches,
        ];
    }

    /**
     * Stok bahan baku yang benar-benar bisa dipakai transaksi:
     * sisa batch yang belum kedaluwarsa + selisih stok yang belum tercermin di batch
     * (selisih ini akan dibuatkan lot penyesuaian saat transaksi berjalan).
     *
     * @return array{available: float, expired_batches: int}
     */
    protected function availability(BahanBaku $bahan, string $saleDate): array
    {
        $batchTotal = 0.0;
        $eligible = 0.0;
        $expiredBatches = 0;

        foreach (PurchaseItem::where('bahan_baku_id', $bahan->id)->get() as $batch) {
            $remaining = (float) $batch->remaining_qty;
            $batchTotal += $remaining;

            if ($remaining <= 0) {
                continue;
            }

            if ($this->isExpired($batch, $saleDate)) {
                $expiredBatches++;

                continue;
            }

            $eligible += $remaining;
        }

        $notInBatch = max(0, (float) $bahan->stock - $batchTotal);

        return ['available' => $eligible + $notInBatch, 'expired_batches' => $expiredBatches];
    }

    /**
     * Batch sudah kedaluwarsa pada tanggal transaksi (batch kedaluwarsa tidak dijual).
     */
    protected function isExpired(PurchaseItem $batch, string $saleDate): bool
    {
        return $batch->expired_date && $batch->expired_date->lt(Carbon::parse($saleDate)->startOfDay());
    }

    /**
     * Batch yang boleh dipakai untuk penjualan, urut FEFO
     * (kedaluwarsa paling awal lebih dulu, batch tanpa kedaluwarsa paling akhir).
     */
    protected function eligibleBatches($bahanBakuId, string $saleDate)
    {
        return PurchaseItem::where('bahan_baku_id', $bahanBakuId)
            ->where('remaining_qty', '>', 0)
            ->where(function ($query) use ($saleDate) {
                $query->whereNull('expired_date')
                    ->orWhereDate('expired_date', '>=', $saleDate);
            })
            ->orderByRaw('CASE WHEN expired_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expired_date', 'asc')
            ->orderBy('id', 'asc');
    }

    protected function applyStock(Sale $sale, $menus): void
    {
        $userId = Auth::user()->id;
        $saleDate = $sale->sale_date instanceof Carbon
            ? $sale->sale_date->toDateString()
            : (string) $sale->sale_date;

        $affected = [];
        $demands = [];

        foreach ($sale->items as $saleItem) {
            $menu = $menus->get($saleItem->menu_id);
            if (!$menu) {
                continue;
            }
            foreach ($menu->bahanBakus as $bahan) {
                $need = (float) $bahan->pivot->quantity * (float) $saleItem->quantity;
                if ($need > 0) {
                    $demands[$bahan->id][] = [
                        'sale_item_id' => $saleItem->id,
                        'menu_id' => $menu->id,
                        'menu_name' => $menu->name,
                        'need' => $need,
                    ];
                }
            }
        }

        foreach ($demands as $bahanId => $lines) {
            $bahan = BahanBaku::lockForUpdate()->find($bahanId);

            if (!$bahan) {
                continue;
            }

            // pastikan seluruh stok master sudah tercermin pada batch/lot
            // (stok awal / opname / koreksi manual dibuatkan lot penyesuaian)
            SyncBahanBakuBatches($bahan);

            foreach ($lines as $line) {
                $remaining = $line['need'];

                $batches = $this->eligibleBatches($bahanId, $saleDate)
                    ->lockForUpdate()
                    ->get();

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $take = min((float) $batch->remaining_qty, $remaining);

                    if ($take <= 0) {
                        continue;
                    }

                    $batch->decrement('remaining_qty', $take);

                    SaleItemUsage::create([
                        'sale_id' => $sale->id,
                        'sale_item_id' => $line['sale_item_id'],
                        'menu_id' => $line['menu_id'],
                        'bahan_baku_id' => $bahanId,
                        'purchase_item_id' => $batch->id,
                        'batch_code' => $batch->batch_code,
                        'quantity' => $take,
                    ]);

                    $bahan->decrement('stock', $take);
                    $bahan->refresh();

                    StockMovement::create([
                        'bahan_baku_id' => $bahanId,
                        'sale_id' => $sale->id,
                        'sale_item_id' => $line['sale_item_id'],
                        'purchase_item_id' => $batch->id,
                        'batch_code' => $batch->batch_code,
                        'movement_date' => $saleDate,
                        'type' => StockMovement::TYPE_OUT,
                        'quantity_in' => 0,
                        'quantity_out' => $take,
                        'balance' => $bahan->stock,
                        'unit_price' => $batch->unit_price,
                        'reference' => $sale->code,
                        'description' => 'Penjualan ' . $sale->code . ' - ' . $line['menu_name'],
                        'created_by' => $userId,
                    ]);

                    $remaining -= $take;
                }

                if ($remaining > 1e-9) {
                    throw new \RuntimeException(
                        'Stok batch "' . $bahan->name . '" tidak mencukupi'
                        . ' (batch kedaluwarsa tidak dipakai). Transaksi dibatalkan.'
                    );
                }
            }

            $affected[] = $bahanId;
        }

        foreach (array_unique($affected) as $bahanId) {
            SyncBahanBakuBatches($bahanId);
        }
    }

    protected function revertStock(Sale $sale): void
    {
        $userId = Auth::user()->id;
        $affected = [];
        $usages = SaleItemUsage::where('sale_id', $sale->id)->orderBy('id')->get();
        foreach ($usages as $usage) {
            $batch = PurchaseItem::lockForUpdate()->find($usage->purchase_item_id);
            if ($batch) {
                $batch->increment('remaining_qty', $usage->quantity);
            }
            $bahan = BahanBaku::lockForUpdate()->find($usage->bahan_baku_id);
            if ($bahan) {
                $bahan->increment('stock', $usage->quantity);
                $bahan->refresh();
                StockMovement::create([
                    'bahan_baku_id' => $bahan->id,
                    'sale_id' => $sale->id,
                    'sale_item_id' => $usage->sale_item_id,
                    'purchase_item_id' => $usage->purchase_item_id,
                    'batch_code' => $usage->batch_code,
                    'movement_date' => Carbon::today()->toDateString(),
                    'type' => StockMovement::TYPE_IN,
                    'quantity_in' => $usage->quantity,
                    'quantity_out' => 0,
                    'balance' => $bahan->stock,
                    'unit_price' => $batch ? $batch->unit_price : 0,
                    'reference' => $sale->code,
                    'description' => 'Pembatalan ' . $sale->code,
                    'created_by' => $userId,
                ]);
            }
            $affected[] = $usage->bahan_baku_id;
        }
        foreach (array_unique($affected) as $bahanId) {
            SyncBahanBakuBatches($bahanId);
        }
    }

    protected function generateCode(): string
    {
        $date = Carbon::today();
        $prefix = 'TRX-' . $date->format('Ymd') . '-';
        $last = Sale::where('code', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last->code, $m)) {
            $next = ((int) $m[1]) + 1;
        }
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
