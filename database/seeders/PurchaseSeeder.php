<?php

namespace Database\Seeders;

use App\Models\BahanBaku;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Contoh pembelian bahan baku dengan konsep batch/lot.
 *
 * Setiap baris pembelian menghasilkan satu batch tersendiri, sehingga satu bahan baku
 * dapat memiliki beberapa batch dengan harga beli dan tanggal kedaluwarsa berbeda.
 */
class PurchaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bahanBakus = BahanBaku::where('status', '!=', -1)->get()->keyBy('code');
        $suppliers = Supplier::where('status', '!=', -1)->get()->keyBy('name');
        $userId = User::where('email', 'admin@example.com')->value('id') ?? User::value('id');

        $defaultSupplierId = optional($suppliers->first())->id;

        $purchases = [
            [
                'code' => 'PB-20261030-0001',
                'supplier' => 'PT Sumber Jaya Makmur',
                'purchase_date' => '2026-10-30',
                'status' => Purchase::STATUS_RECEIVED,
                'description' => 'Pembelian susu batch 1',
                'items' => [
                    ['code' => 'SUSU', 'quantity' => 10, 'unit_price' => 20000, 'expired_date' => '2026-10-30'],
                ],
            ],
            [
                'code' => 'PB-20261115-0001',
                'supplier' => 'PT Sumber Jaya Makmur',
                'purchase_date' => '2026-11-15',
                'status' => Purchase::STATUS_RECEIVED,
                'description' => 'Pembelian susu batch 2',
                'items' => [
                    ['code' => 'SUSU', 'quantity' => 15, 'unit_price' => 22000, 'expired_date' => '2026-11-15'],
                ],
            ],
            [
                'code' => 'PB-20261220-0001',
                'supplier' => 'UD Segar Sejahtera',
                'purchase_date' => '2026-12-20',
                'status' => Purchase::STATUS_RECEIVED,
                'description' => 'Pembelian susu batch 3',
                'items' => [
                    ['code' => 'SUSU', 'quantity' => 20, 'unit_price' => 21000, 'expired_date' => '2026-12-20'],
                ],
            ],
            [
                'code' => 'PB-20261221-0001',
                'supplier' => 'CV Berkah Abadi',
                'purchase_date' => '2026-12-21',
                'status' => Purchase::STATUS_RECEIVED,
                'description' => 'Pembelian gula pasir dan cup kertas',
                'items' => [
                    ['code' => 'GULA', 'quantity' => 25, 'unit_price' => 15500, 'expired_date' => '2027-06-30'],
                    ['code' => 'CUP12', 'quantity' => 1000, 'unit_price' => 950, 'expired_date' => null],
                ],
            ],
            [
                'code' => 'PB-20261222-0001',
                'supplier' => 'PT Sumber Jaya Makmur',
                'purchase_date' => '2026-12-22',
                'status' => Purchase::STATUS_DRAFT,
                'description' => 'Draft pembelian kopi (belum diterima)',
                'items' => [
                    ['code' => 'KOPI', 'quantity' => 5, 'unit_price' => 125000, 'expired_date' => '2027-03-31'],
                ],
            ],
        ];

        foreach ($purchases as $data) {
            if (Purchase::where('code', $data['code'])->exists()) {
                continue;
            }

            $purchase = Purchase::create([
                'code' => $data['code'],
                'supplier_id' => optional($suppliers->get($data['supplier']))->id ?? $defaultSupplierId,
                'purchase_date' => $data['purchase_date'],
                'total' => collect($data['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_price']),
                'description' => $data['description'],
                'status' => $data['status'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach (array_values($data['items']) as $index => $item) {
                $bahanBaku = $bahanBakus->get($item['code']);

                if (!$bahanBaku) {
                    continue;
                }

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'bahan_baku_id' => $bahanBaku->id,
                    'batch_code' => $purchase->code . '-' . ($index + 1),
                    'quantity' => $item['quantity'],
                    'remaining_qty' => $data['status'] === Purchase::STATUS_RECEIVED ? $item['quantity'] : 0,
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                    'expired_date' => $item['expired_date'],
                ]);

                // hanya pembelian yang sudah diterima yang menambah stok bahan baku
                if ($data['status'] === Purchase::STATUS_RECEIVED) {
                    $bahanBaku->update([
                        'stock' => (float) $bahanBaku->stock + $item['quantity'],
                        'price' => $item['unit_price'],
                        'updated_by' => $userId,
                    ]);
                }
            }
        }

        $this->syncStockCards();
    }

    /**
     * Isi kartu stok untuk pembelian yang sudah diterima namun belum tercatat
     * (mis. data pembelian yang dibuat sebelum fitur kartu stok ada).
     *
     * Saldo awal dihitung dari stok saat ini dikurangi seluruh pembelian yang sudah
     * diterima, sehingga saldo akhir tetap sama dengan stok master bahan baku.
     */
    private function syncStockCards(): void
    {
        foreach (BahanBaku::where('status', '!=', -1)->get() as $bahanBaku) {
            if (StockMovement::where('bahan_baku_id', $bahanBaku->id)->exists()) {
                continue;   // sudah tercatat, jangan dobel
            }

            $items = PurchaseItem::with('purchase')
                ->where('bahan_baku_id', $bahanBaku->id)
                ->whereHas('purchase', function ($query) {
                    $query->where('status', Purchase::STATUS_RECEIVED);
                })
                ->get();

            if ($items->isEmpty()) {
                continue;
            }

            $items = $items->sortBy(function ($item) {
                return $item->purchase->purchase_date->format('Y-m-d')
                    . '-' . str_pad((string) $item->id, 10, '0', STR_PAD_LEFT);
            })->values();

            // saldo sebelum pembelian pertama (stok awal master bahan baku)
            $balance = (float) $bahanBaku->stock - $items->sum(fn ($item) => (float) $item->quantity);

            foreach ($items as $item) {
                $balance += (float) $item->quantity;

                StockMovement::create([
                    'bahan_baku_id' => $bahanBaku->id,
                    'purchase_id' => $item->purchase_id,
                    'purchase_item_id' => $item->id,
                    'batch_code' => $item->batch_code,
                    'movement_date' => $item->purchase->purchase_date,
                    'type' => StockMovement::TYPE_IN,
                    'quantity_in' => $item->quantity,
                    'quantity_out' => 0,
                    'balance' => $balance,
                    'unit_price' => $item->unit_price,
                    'reference' => $item->purchase->code,
                    'description' => 'Pembelian diterima (batch ' . $item->batch_code . ')',
                    'created_by' => $item->purchase->created_by,
                ]);
            }
        }
    }
}
