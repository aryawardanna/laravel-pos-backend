<?php

namespace Database\Seeders;

use App\Models\BahanBaku;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Contoh stock opname bahan baku.
 *
 * Selisih didefinisikan relatif terhadap stok yang ada saat seeder dijalankan
 * (physical = stok sistem + selisih), sehingga data demo selalu konsisten.
 * Opname berstatus selesai langsung menyesuaikan stok dan mencatat
 * penyesuaiannya ke kartu stok (tipe adjustment).
 */
class StockOpnameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bahanBakus = BahanBaku::where('status', '!=', -1)->get()->keyBy('code');
        $userId = User::where('email', 'admin@example.com')->value('id') ?? User::value('id');

        $opnames = [
            [
                'code' => 'SO-20260924-0001',
                'opname_date' => '2026-09-24',
                'status' => StockOpname::STATUS_FINAL,
                'description' => 'Hitung fisik rutin bahan baku',
                'items' => [
                    ['code' => 'SUSU', 'difference' => -2, 'description' => 'Susu tumpah saat produksi'],
                    ['code' => 'GULA', 'difference' => 3, 'description' => 'Kelebihan timbangan'],
                    ['code' => 'CUP12', 'difference' => 0, 'description' => null],
                ],
            ],
            [
                'code' => 'SO-20260925-0001',
                'opname_date' => '2026-09-25',
                'status' => StockOpname::STATUS_DRAFT,
                'description' => 'Draft opname kopi (belum diselesaikan)',
                'items' => [
                    ['code' => 'KOPI', 'difference' => -1, 'description' => 'Menunggu verifikasi ulang'],
                ],
            ],
        ];

        foreach ($opnames as $data) {
            if (StockOpname::where('code', $data['code'])->exists()) {
                continue;
            }

            $opname = StockOpname::create([
                'code' => $data['code'],
                'opname_date' => $data['opname_date'],
                'status' => $data['status'],
                'description' => $data['description'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($data['items'] as $row) {
                $bahanBaku = $bahanBakus->get($row['code']);

                if (!$bahanBaku) {
                    continue;
                }

                $systemStock = (float) $bahanBaku->stock;
                $difference = (float) $row['difference'];

                $item = StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'bahan_baku_id' => $bahanBaku->id,
                    'system_stock' => $systemStock,
                    'physical_stock' => $systemStock + $difference,
                    'difference' => $difference,
                    'unit_price' => $bahanBaku->price,
                    'description' => $row['description'],
                ]);

                // draft dan item yang sesuai tidak mengubah stok
                if ($data['status'] !== StockOpname::STATUS_FINAL || $difference === 0.0) {
                    continue;
                }

                $balance = max(0, $systemStock + $difference);

                $bahanBaku->update([
                    'stock' => $balance,
                    'updated_by' => $userId,
                ]);

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
                    'description' => 'Stock opname ' . $opname->code,
                    'created_by' => $userId,
                ]);

                // total sisa batch harus sama dengan stok bahan baku
                SyncBahanBakuBatches($bahanBaku);
            }
        }
    }
}
