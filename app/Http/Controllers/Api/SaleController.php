<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\Menu;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemUsage;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with(['creator', 'items.menu'])->orderBy('id', 'desc');

        if ($request->filled('code')) {
            $query->where('code', 'like', '%'.$request->input('code').'%');
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

        $perPage = min((int) $request->input('per_page', 20), 100);
        $sales = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sales->getCollection()->transform(fn (Sale $s) => $this->saleSummary($s)),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ], 200);
    }

    public function show(string $id)
    {
        $sale = Sale::with(['items.menu', 'creator', 'updater'])->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->saleDetail($sale),
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules(), $this->messages());
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rows = $this->extractItems($request);
        if (empty($rows)) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal satu menu dengan jumlah lebih dari 0 harus diisi.',
            ], 400);
        }

        $menus = Menu::with('bahanBakus')
            ->whereIn('id', collect($rows)->pluck('menu_id'))
            ->get()
            ->keyBy('id');

        $subtotal = collect($rows)->sum('subtotal');
        $discount = (float) ($request->input('discount') ?? 0);
        $tax = (float) ($request->input('tax') ?? 0);
        $total = max(0, $subtotal - $discount + $tax);
        $paid = (float) ($request->input('paid') ?? 0);

        if ($paid < $total) {
            return response()->json([
                'success' => false,
                'message' => 'Uang dibayar kurang dari total belanja.',
            ], 400);
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
                    $menu = $menus->get($row['menu_id']);
                    if (!$menu) {
                        continue;
                    }

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'menu_id' => $menu->id,
                        'quantity' => $row['quantity'],
                        'unit_price' => $row['unit_price'],
                        'subtotal' => $row['subtotal'],
                        'description' => null,
                    ]);
                }

                $this->applyStock($sale, $menus);

                return $sale->fresh(['items.menu', 'creator']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Transaksi penjualan berhasil disimpan.',
                'data' => $this->saleDetail($sale),
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Terjadi kesalahan saat menyimpan transaksi.',
            ], 400);
        }
    }

    public function destroy(string $id)
    {
        $sale = Sale::with(['items', 'usages'])->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan.',
            ], 404);
        }

        if ($sale->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi ini sudah dibatalkan.',
            ], 400);
        }

        DB::transaction(function () use ($sale) {
            $this->revertStock($sale);
            $sale->update([
                'status' => Sale::STATUS_CANCELLED,
                'updated_by' => Auth::user()->id,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Transaksi '.$sale->code.' dibatalkan, stok bahan baku dikembalikan ke batch semula.',
            'data' => $this->saleSummary($sale->fresh(['items'])),
        ], 200);
    }

    public function menuInfo(string $id)
    {
        $menu = Menu::with(['bahanBakus.satuan'])->find($id);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu tidak ditemukan.',
            ], 404);
        }

        $info = $this->menuAvailability($menu, Carbon::today()->toDateString());

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'price' => (float) $menu->price,
                'has_recipe' => $info['recipes'] !== [],
                'max_qty' => $info['max_qty'],
                'expired_batches' => $info['expired_batches'],
                'recipes' => $info['recipes'],
            ],
        ], 200);
    }

    protected function rules(): array
    {
        return [
            'sale_date' => ['nullable', 'date'],
            'menu_id' => ['required', 'array', 'min:1'],
            'menu_id.*' => ['required', 'integer', 'exists:menus,id'],
            'quantity' => ['required', 'array', 'min:1'],
            'quantity.*' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric'],
            'tax' => ['nullable', 'numeric'],
            'paid' => ['required', 'numeric'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string'],
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
            $rows[] = [
                'menu_id' => $menu->id,
                'quantity' => $qty,
                'unit_price' => $price,
                'subtotal' => $qty * $price,
            ];
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
                    ? ' ('.$available['expired_batches'].' batch kedaluwarsa tidak dihitung)'
                    : '';

                throw new \RuntimeException(
                    'Stok "'.$need['name'].'" tidak cukup: butuh '.number_format($need['need'], 2)
                    .', layak dipakai '.number_format($available['available'], 2)
                    .' dari stok '.number_format($bahan->stock, 2).$note
                    .'. Kurangi jumlah menu atau tambah stok dulu.'
                );
            }
        }
    }

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

    protected function isExpired(PurchaseItem $batch, string $saleDate): bool
    {
        return $batch->expired_date && $batch->expired_date->lt(Carbon::parse($saleDate)->startOfDay());
    }

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

            $this->syncBahanBakuBatches($bahan);

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
                        'description' => 'Penjualan '.$sale->code.' - '.$line['menu_name'],
                        'created_by' => $userId,
                    ]);

                    $remaining -= $take;
                }

                if ($remaining > 1e-9) {
                    throw new \RuntimeException(
                        'Stok batch "'.$bahan->name.'" tidak mencukupi'
                        .' (batch kedaluwarsa tidak dipakai). Transaksi dibatalkan.'
                    );
                }
            }

            $affected[] = $bahanId;
        }

        foreach (array_unique($affected) as $bahanId) {
            $this->syncBahanBakuBatches(BahanBaku::find($bahanId));
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
                    'description' => 'Pembatalan '.$sale->code,
                    'created_by' => $userId,
                ]);
            }
            $affected[] = $usage->bahan_baku_id;
        }

        foreach (array_unique($affected) as $bahanId) {
            $this->syncBahanBakuBatches(BahanBaku::find($bahanId));
        }
    }

    protected function generateCode(): string
    {
        $date = Carbon::today();
        $prefix = 'TRX-'.$date->format('Ymd').'-';
        $last = Sale::where('code', 'like', $prefix.'%')->orderBy('id', 'desc')->first();
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last->code, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

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

    protected function syncBahanBakuBatches(?BahanBaku $bahan): void
    {
        if (!$bahan) {
            return;
        }

        if (function_exists('SyncBahanBakuBatches')) {
            SyncBahanBakuBatches($bahan);
            return;
        }

        $batchTotal = PurchaseItem::where('bahan_baku_id', $bahan->id)->sum('remaining_qty');
        $diff = (float) $bahan->stock - (float) $batchTotal;

        if ($diff > 1e-9) {
            PurchaseItem::create([
                'bahan_baku_id' => $bahan->id,
                'batch_code' => 'ADJ-'.Carbon::today()->format('YmdHis'),
                'unit_price' => 0,
                'quantity' => $diff,
                'remaining_qty' => $diff,
                'expired_date' => null,
                'supplier_id' => null,
                'created_by' => Auth::user()->id,
                'updated_by' => Auth::user()->id,
            ]);
        }
    }

    protected function saleSummary(Sale $s): array
    {
        return [
            'id' => $s->id,
            'code' => $s->code,
            'sale_date' => $s->sale_date ? $s->sale_date->format('Y-m-d') : null,
            'subtotal' => (float) $s->subtotal,
            'discount' => (float) $s->discount,
            'tax' => (float) $s->tax,
            'total' => (float) $s->total,
            'paid' => (float) $s->paid,
            'change_amount' => (float) $s->change_amount,
            'payment_method' => $s->payment_method,
            'status' => (int) $s->status,
            'status_label' => $s->isCompleted() ? 'Selesai' : 'Dibatalkan',
            'item_count' => $s->items ? $s->items->count() : 0,
            'created_by' => $s->creator ? $s->creator->name : null,
            'created_at' => $s->created_at ? $s->created_at->toISOString() : null,
        ];
    }

    protected function saleDetail(Sale $s): array
    {
        $summary = $this->saleSummary($s);
        $summary['description'] = $s->description;
        $summary['updated_by'] = $s->updater ? $s->updater->name : null;
        $summary['updated_at'] = $s->updated_at ? $s->updated_at->toISOString() : null;
        $summary['items'] = $s->items ? $s->items->map(fn ($item) => [
            'id' => $item->id,
            'menu_id' => $item->menu_id,
            'menu_name' => $item->menu ? $item->menu->name : null,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'subtotal' => (float) $item->subtotal,
            'description' => $item->description,
        ])->toArray() : [];

        return $summary;
    }
}