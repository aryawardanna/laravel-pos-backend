<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Satuan;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KartuStokTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeBahanBaku(float $stock = 0, float $price = 0): BahanBaku
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

    /**
     * Payload form pembelian (satu baris batch).
     */
    private function payload(BahanBaku $bahanBaku, array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => Supplier::create(['name' => 'PT Susu Nusantara', 'status' => 1])->id,
            'purchase_date' => '2026-10-30',
            'status' => '1',
            'items' => [
                'bahan_baku_id' => [$bahanBaku->id],
                'quantity' => ['10'],
                'unit_price' => ['20000'],
                'expired_date' => ['2026-10-30'],
            ],
        ], $overrides);
    }

    public function test_received_purchase_records_stock_card_entries_per_batch(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(5);

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku, [
            'items' => [
                'bahan_baku_id' => [$bahanBaku->id, $bahanBaku->id],
                'quantity' => ['10', '15'],
                'unit_price' => ['20000', '22000'],
                'expired_date' => ['2026-10-30', '2026-11-15'],
            ],
        ]))->assertRedirect(route('purchase.index'));

        $purchase = Purchase::first();
        $movements = StockMovement::orderBy('id')->get();

        // satu baris kartu stok untuk setiap batch
        $this->assertCount(2, $movements);
        $this->assertSame(StockMovement::TYPE_IN, $movements[0]->type);
        $this->assertSame(StockMovement::TYPE_IN, $movements[1]->type);

        // saldo = stok setelah pergerakan (5 + 10 = 15, lalu 15 + 15 = 30)
        $this->assertEquals(15, (float) $movements[0]->balance);
        $this->assertEquals(30, (float) $movements[1]->balance);

        $this->assertEquals(10, (float) $movements[0]->quantity_in);
        $this->assertEquals(0, (float) $movements[0]->quantity_out);
        $this->assertEquals(22000, (float) $movements[1]->unit_price);

        // referensi & batch tercatat
        $this->assertSame($purchase->code, $movements[0]->reference);
        $this->assertSame($purchase->code . '-1', $movements[0]->batch_code);
        $this->assertSame($purchase->code . '-2', $movements[1]->batch_code);
        $this->assertSame($purchase->id, $movements[0]->purchase_id);
        $this->assertSame('2026-10-30', $movements[0]->movement_date->format('Y-m-d'));
        $this->assertSame($user->id, $movements[0]->created_by);
    }

    public function test_draft_purchase_records_no_entry_until_received(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku();

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku, ['status' => '0']))
            ->assertRedirect(route('purchase.index'));

        // draft belum memengaruhi stok, jadi kartu stok masih kosong
        $this->assertSame(0, StockMovement::count());

        $purchase = Purchase::first();

        $this->actingAs($user)->post(route('purchase.receive', $purchase->id))
            ->assertRedirect(route('purchase.index'));

        $movement = StockMovement::first();
        $this->assertNotNull($movement);
        $this->assertSame(StockMovement::TYPE_IN, $movement->type);
        $this->assertEquals(10, (float) $movement->balance);
        $this->assertSame($purchase->code, $movement->reference);
    }

    public function test_cancel_records_stock_out_entry(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(2);

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku));
        $purchase = Purchase::first();

        $this->actingAs($user)->delete(route('purchase.destroy', $purchase->id))
            ->assertRedirect(route('purchase.index'));

        $movements = StockMovement::orderBy('id')->get();

        $this->assertCount(2, $movements);
        $this->assertSame(StockMovement::TYPE_IN, $movements[0]->type);
        $this->assertEquals(12, (float) $movements[0]->balance);

        $this->assertSame(StockMovement::TYPE_OUT, $movements[1]->type);
        $this->assertEquals(10, (float) $movements[1]->quantity_out);
        $this->assertEquals(2, (float) $movements[1]->balance);
        $this->assertStringContainsString('Pembatalan', $movements[1]->description);
        $this->assertSame($purchase->code, $movements[1]->reference);
    }

    public function test_edit_received_purchase_records_reversal_and_new_entry(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku();

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku));
        $purchase = Purchase::first();
        $oldBatchCode = PurchaseItem::first()->batch_code;

        $this->assertEquals(10, (float) StockMovement::first()->balance);

        $this->actingAs($user)->put(route('purchase.update', $purchase->id), [
            'purchase_date' => '2026-11-01',
            'status' => '1',
            'items' => [
                'bahan_baku_id' => [$bahanBaku->id],
                'quantity' => ['4'],
                'unit_price' => ['25000'],
                'expired_date' => ['2026-11-20'],
            ],
        ])->assertRedirect(route('purchase.index'));

        $movements = StockMovement::orderBy('id')->get();

        // masuk 10, keluar 10 (koreksi), lalu masuk 4
        $this->assertCount(3, $movements);
        $this->assertSame(StockMovement::TYPE_IN, $movements[0]->type);
        $this->assertSame(StockMovement::TYPE_OUT, $movements[1]->type);
        $this->assertSame(StockMovement::TYPE_IN, $movements[2]->type);

        $this->assertEquals(10, (float) $movements[0]->balance);
        $this->assertEquals(0, (float) $movements[1]->balance);
        $this->assertEquals(4, (float) $movements[2]->balance);
        $this->assertStringContainsString('Koreksi', $movements[1]->description);

        // riwayat batch lama tetap tercatat walau baris pembelian sudah diganti
        $this->assertSame($oldBatchCode, $movements[1]->batch_code);
        $this->assertSame($purchase->code, $movements[1]->reference);
    }

    public function test_kartu_stok_datatable_json_and_filters(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku();

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku));
        $purchase = Purchase::first();
        $this->actingAs($user)->delete(route('purchase.destroy', $purchase->id));

        // halaman kartu stok dapat diakses
        $this->actingAs($user)->get(route('kartu_stok.index'))
            ->assertOk()
            ->assertSee('Kartu Stok Bahan Baku');

        $this->actingAs($user)->get(route('kartu_stok.data'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'DT_RowIndex',
                        'movement_date',
                        'bahan_baku_id',
                        'satuan',
                        'batch_code',
                        'reference_link',
                        'type',
                        'quantity_in',
                        'quantity_out',
                        'balance',
                        'unit_price',
                        'description',
                        'created_by',
                    ],
                ],
            ])
            ->assertJsonCount(2, 'data');

        // format angka memakai format Indonesia (10 tidak tampil sebagai "10.000")
        $this->actingAs($user)->get(route('kartu_stok.data'))
            ->assertOk()
            ->assertJsonPath('data.0.quantity_in', '10')
            ->assertJsonPath('data.0.quantity_out', '-')
            ->assertJsonPath('data.0.balance', '10')
            ->assertJsonPath('data.0.unit_price', '20.000,00')
            ->assertJsonPath('data.1.quantity_out', '10')
            ->assertJsonPath('data.1.balance', '0');

        // filter jenis pergerakan
        $this->actingAs($user)->get(route('kartu_stok.data', ['type' => StockMovement::TYPE_OUT]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // filter bahan baku
        $this->actingAs($user)->get(route('kartu_stok.data', ['bahan_baku_id' => $bahanBaku->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // filter nomor pembelian (referensi)
        $this->actingAs($user)->get(route('kartu_stok.data', ['reference' => $purchase->code]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // filter rentang tanggal
        $this->actingAs($user)->get(route('kartu_stok.data', ['date_from' => '2026-11-01']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)->get(route('kartu_stok.data', ['date_to' => '2026-10-31']))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_sidebar_highlights_kartu_stok_menu(): void
    {
        $user = $this->makeUser();

        $html = $this->actingAs($user)->get(route('kartu_stok.index'))->assertOk()->getContent();

        preg_match('/<li class="active">\s*<a class="nav-link" href="([^"]*)"/', $html, $m);

        $this->assertNotNull($m[1] ?? null, 'Tidak ditemukan menu sidebar aktif di halaman kartu stok');
        $this->assertSame(route('kartu_stok.index'), $m[1]);
    }
}
