<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Satuan;
use App\Models\StockOpname;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invariant inventory: total sisa batch harus sama dengan stok bahan baku,
 * termasuk untuk stok awal, stock opname, koreksi stok manual, dan pembelian.
 */
class BatchStockSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeBahanBaku(float $stock = 0, float $price = 20000): BahanBaku
    {
        $satuan = Satuan::create(['name' => 'Liter', 'code' => 'Ltr', 'status' => 1]);

        return BahanBaku::create([
            'name' => 'Susu Full Cream',
            'code' => 'SUSU',
            'satuan_id' => $satuan->id,
            'price' => $price,
            'stock' => $stock,
            'min_stock' => 5,
            'status' => 1,
        ]);
    }

    private function receivePurchase(User $user, BahanBaku $bahanBaku, string $quantity, string $unitPrice, string $expiredDate): void
    {
        $this->actingAs($user)->post(route('purchase.store'), [
            'supplier_id' => Supplier::create(['name' => 'PT Susu Nusantara', 'status' => 1])->id,
            'purchase_date' => '2026-10-30',
            'status' => '1',
            'items' => [
                'bahan_baku_id' => [$bahanBaku->id],
                'quantity' => [$quantity],
                'unit_price' => [$unitPrice],
                'expired_date' => [$expiredDate],
            ],
        ])->assertRedirect(route('purchase.index'));
    }

    private function assertBatchTotalEqualsStock(BahanBaku $bahanBaku): void
    {
        $totalRemaining = (float) PurchaseItem::where('bahan_baku_id', $bahanBaku->id)->sum('remaining_qty');

        $this->assertEqualsWithDelta(
            (float) $bahanBaku->fresh()->stock,
            $totalRemaining,
            0.0005,
            'Total sisa batch tidak sama dengan stok bahan baku: ' . $bahanBaku->code
        );
    }

    public function test_opening_stock_is_held_in_adjustment_batch(): void
    {
        $susu = $this->makeBahanBaku(30);

        SyncBahanBakuBatches($susu);

        $adjustment = PurchaseItem::where('bahan_baku_id', $susu->id)
            ->where('source', PurchaseItem::SOURCE_ADJUSTMENT)
            ->first();

        $this->assertNotNull($adjustment, 'Lot penyesuaian untuk stok awal tidak dibuat');
        $this->assertSame('ADJ-SUSU', $adjustment->batch_code);
        $this->assertNull($adjustment->purchase_id);
        $this->assertEquals(30, (float) $adjustment->remaining_qty);
        $this->assertBatchTotalEqualsStock($susu);

        // idempotent: tidak membuat lot penyesuaian kedua
        SyncBahanBakuBatches($susu);
        SyncBahanBakuBatches($susu);

        $this->assertSame(1, PurchaseItem::where('bahan_baku_id', $susu->id)
            ->where('source', PurchaseItem::SOURCE_ADJUSTMENT)
            ->count());
        $this->assertBatchTotalEqualsStock($susu);
    }

    public function test_purchase_receive_and_cancel_keep_batch_total_equal_to_stock(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku(0);

        $this->receivePurchase($user, $susu, '10', '20000', '2026-10-30');
        $this->receivePurchase($user, $susu, '15', '22000', '2026-11-15');

        $this->assertEquals(25, (float) $susu->refresh()->stock);
        $this->assertBatchTotalEqualsStock($susu);

        // batalkan pembelian terakhir
        $purchase = Purchase::orderBy('id', 'desc')->first();
        $this->actingAs($user)->delete(route('purchase.destroy', $purchase->id))
            ->assertSessionHas('success');

        $this->assertEquals(10, (float) $susu->refresh()->stock);
        $this->assertBatchTotalEqualsStock($susu);
    }

    public function test_opname_negative_difference_deducts_batches_fefo(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku(0);

        $this->receivePurchase($user, $susu, '10', '20000', '2026-10-30');
        $this->receivePurchase($user, $susu, '15', '22000', '2026-11-15');

        // opname: fisik 20 -> kurang 5, dipotong dari batch paling cepat kedaluwarsa
        $this->actingAs($user)->post(route('stock_opname.store'), [
            'opname_date' => '2026-11-20',
            'status' => '1',
            'items' => [
                'bahan_baku_id' => [$susu->id],
                'physical_stock' => ['20'],
            ],
        ])->assertSessionHas('success');

        $this->assertEquals(20, (float) $susu->refresh()->stock);

        $batches = PurchaseItem::where('bahan_baku_id', $susu->id)->orderBy('expired_date')->get();

        $this->assertEquals(5, (float) $batches[0]->remaining_qty);    // batch exp 30 Okt terpotong 5
        $this->assertEquals(15, (float) $batches[1]->remaining_qty);   // batch exp 15 Nov tetap
        $this->assertBatchTotalEqualsStock($susu);
    }

    public function test_opname_positive_difference_creates_adjustment_batch(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku(0);

        $this->receivePurchase($user, $susu, '10', '20000', '2026-10-30');

        // opname: fisik 13 -> lebih 3, masuk ke lot penyesuaian
        $this->actingAs($user)->post(route('stock_opname.store'), [
            'opname_date' => '2026-11-01',
            'status' => '1',
            'items' => [
                'bahan_baku_id' => [$susu->id],
                'physical_stock' => ['13'],
            ],
        ])->assertSessionHas('success');

        $this->assertEquals(13, (float) $susu->refresh()->stock);

        $adjustment = PurchaseItem::where('bahan_baku_id', $susu->id)
            ->where('source', PurchaseItem::SOURCE_ADJUSTMENT)
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertEquals(3, (float) $adjustment->remaining_qty);
        $this->assertBatchTotalEqualsStock($susu);

        // batalkan opname -> total batch ikut kembali sama dengan stok
        $opname = StockOpname::first();
        $this->actingAs($user)->delete(route('stock_opname.destroy', $opname->id))
            ->assertSessionHas('success');

                $this->assertEquals(10, (float) $susu->refresh()->stock);
        $this->assertBatchTotalEqualsStock($susu);
        // setelah pembatalan, tidak ada lot dengan sisa negatif
        $this->assertGreaterThanOrEqual(0, (float) PurchaseItem::where('bahan_baku_id', $susu->id)->min('remaining_qty'));
    }

        public function test_bahan_baku_master_update_does_not_touch_stock_or_batches(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku(0);

        $this->receivePurchase($user, $susu, '10', '20000', '2026-10-30');
        $this->assertEquals(10, (float) $susu->refresh()->stock);

        // update master (nama/harga) — kolom stock tidak dikirim, jadi stok & batch tak berubah
        $this->actingAs($user)->put(route('bahan_baku.update', $susu->id), [
            'name' => 'Susu Full Cream (Premium)',
            'code' => 'SUSU',
            'satuan_id' => $susu->satuan_id,
            'price' => '25000',
            'min_stock' => '5',
            'description' => 'premium',
            'status' => '1',
        ])->assertRedirect(route('bahan_baku.index'));

        $this->assertEquals(10, (float) $susu->refresh()->stock);
        $this->assertSame('Susu Full Cream (Premium)', $susu->name);
        $this->assertBatchTotalEqualsStock($susu);
        $this->assertEquals(10, (float) PurchaseItem::where('bahan_baku_id', $susu->id)
            ->where('source', PurchaseItem::SOURCE_PURCHASE)
            ->sum('remaining_qty'));
    }

    public function test_batch_page_lists_adjustment_batch_with_source_label(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku(30);

        SyncBahanBakuBatches($susu);

        $this->actingAs($user)->get(route('batch_bahan_baku.data'))
            ->assertOk()
            ->assertJsonPath('data.0.source', '<span class="badge badge-warning">Penyesuaian</span>')
            ->assertJsonPath('data.0.batch_code', 'ADJ-SUSU')
            ->assertJsonPath('data.0.quantity', '30')
            ->assertJsonPath('data.0.remaining_qty', '<span class="badge badge-info">30</span>');
    }
}
