<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Satuan;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTest extends TestCase
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

    private function makeSupplier(): Supplier
    {
        return Supplier::create(['name' => 'PT Susu Nusantara', 'status' => 1]);
    }

    /**
     * Payload form pembelian (satu baris batch).
     */
    private function payload(BahanBaku $bahanBaku, array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => $this->makeSupplier()->id,
            'purchase_date' => '2026-10-30',
            'description' => 'Pembelian batch susu',
            'status' => '1',
            'items' => [
                'bahan_baku_id' => [$bahanBaku->id],
                'quantity' => ['10'],
                'unit_price' => ['20000'],
                'expired_date' => ['2026-10-30'],
                'description' => ['batch pertama'],
            ],
        ], $overrides);
    }

    public function test_purchase_index_and_datatable_json_with_filters(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku();

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku))
            ->assertRedirect(route('purchase.index'));

        $code = Purchase::first()->code;
        $this->assertStringStartsWith('PB-20261030-', $code);

        // halaman index dapat diakses
        $this->actingAs($user)->get(route('purchase.index'))
            ->assertOk()
            ->assertSee('Pembelian Bahan Baku');

        // endpoint datatable mengembalikan JSON sesuai struktur
        $this->actingAs($user)->get(route('purchase.data'))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'DT_RowIndex',
                        'code',
                        'purchase_date',
                        'supplier_id',
                        'total',
                        'item_count',
                        'status',
                        'created_by',
                        'action',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');

        // filter status
        $this->actingAs($user)->get(route('purchase.data', ['status' => Purchase::STATUS_DRAFT]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // filter rentang tanggal
        $this->actingAs($user)->get(route('purchase.data', ['date_from' => '2026-11-01']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)->get(route('purchase.data', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // filter nomor pembelian
        $this->actingAs($user)->get(route('purchase.data', ['code' => $code]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // pencarian supplier melalui kotak pencarian DataTables
        $this->actingAs($user)->get(route('purchase.data', ['search' => ['value' => 'Susu Nusantara']]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_received_purchase_creates_separate_batches_and_increases_stock(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku();

        // dua batch dari bahan baku yang sama, harga & kedaluwarsa berbeda
        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku, [
            'items' => [
                'bahan_baku_id' => [$bahanBaku->id, $bahanBaku->id],
                'quantity' => ['10', '15'],
                'unit_price' => ['20000', '22000'],
                'expired_date' => ['2026-10-30', '2026-11-15'],
                'description' => ['batch 1', 'batch 2'],
            ],
        ]))->assertRedirect(route('purchase.index'));

        $purchase = Purchase::with('items')->first();
        $code = $purchase->code;

        $this->assertSame(Purchase::STATUS_RECEIVED, (int) $purchase->status);
        $this->assertSame('2026-10-30', $purchase->purchase_date->format('Y-m-d'));
        $this->assertSame(2, $purchase->items->count());
        $this->assertEquals(530000, (float) $purchase->total);

        // batch tersimpan terpisah: batch_code, quantity, harga, kedaluwarsa masing-masing
        $this->assertDatabaseHas('purchase_items', [
            'batch_code' => $code . '-1',
            'quantity' => 10,
            'remaining_qty' => 10,
            'unit_price' => 20000,
            'subtotal' => 200000,
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'batch_code' => $code . '-2',
            'quantity' => 15,
            'remaining_qty' => 15,
            'unit_price' => 22000,
            'subtotal' => 330000,
        ]);

        $items = PurchaseItem::orderBy('id')->get();
        $this->assertSame('2026-10-30', $items[0]->expired_date->format('Y-m-d'));
        $this->assertSame('2026-11-15', $items[1]->expired_date->format('Y-m-d'));

        // stok bahan baku bertambah 25 dan harga beli mengikuti pembelian terakhir
        $bahanBaku->refresh();
        $this->assertEquals(25, (float) $bahanBaku->stock);
        $this->assertEquals(22000, (float) $bahanBaku->price);
    }

    public function test_draft_purchase_does_not_change_stock_and_receive_action_does(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(5, 18000);

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku, ['status' => '0']))
            ->assertRedirect(route('purchase.index'));

        $purchase = Purchase::with('items')->first();

        $this->assertSame(Purchase::STATUS_DRAFT, (int) $purchase->status);
        $this->assertEquals(0, (float) $purchase->items->first()->remaining_qty);

        // draft belum memengaruhi stok & harga bahan baku
        $bahanBaku->refresh();
        $this->assertEquals(5, (float) $bahanBaku->stock);
        $this->assertEquals(18000, (float) $bahanBaku->price);

        // aksi terima pembelian draft
        $this->actingAs($user)->post(route('purchase.receive', $purchase->id))
            ->assertRedirect(route('purchase.index'))
            ->assertSessionHas('success');

        $bahanBaku->refresh();
        $this->assertEquals(15, (float) $bahanBaku->stock);
        $this->assertEquals(20000, (float) $bahanBaku->price);

        $purchase = Purchase::with('items')->find($purchase->id);
        $this->assertSame(Purchase::STATUS_RECEIVED, (int) $purchase->status);
        $this->assertEquals(10, (float) $purchase->items->first()->remaining_qty);

        // pembelian yang sudah diterima tidak dapat diterima ulang
        $this->actingAs($user)->post(route('purchase.receive', $purchase->id))
            ->assertRedirect(route('purchase.index'))
            ->assertSessionHas('error');
    }

    public function test_cancel_purchase_reverts_stock_and_keeps_record(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(2);

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku));
        $purchase = Purchase::first();

        $this->assertEquals(12, (float) $bahanBaku->refresh()->stock);

        $this->actingAs($user)->delete(route('purchase.destroy', $purchase->id))
            ->assertRedirect(route('purchase.index'))
            ->assertSessionHas('success');

        // stok dikembalikan, record tetap ada dengan status -1 (dibatalkan)
        $this->assertEquals(2, (float) $bahanBaku->refresh()->stock);
        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => Purchase::STATUS_CANCELLED,
        ]);
        $this->assertEquals(0, (float) PurchaseItem::where('purchase_id', $purchase->id)->first()->remaining_qty);

        // pembatalan kedua ditolak
        $this->actingAs($user)->delete(route('purchase.destroy', $purchase->id))
            ->assertRedirect(route('purchase.index'))
            ->assertSessionHas('error');

        $this->assertEquals(2, (float) $bahanBaku->refresh()->stock);
    }

    public function test_cancel_is_blocked_when_batch_already_used(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku();

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku));
        $purchase = Purchase::with('items')->first();

        // simulasikan batch sebagian sudah terpakai (mis. dipakai produksi)
        $purchase->items->first()->update(['remaining_qty' => 4]);

        $this->actingAs($user)->delete(route('purchase.destroy', $purchase->id))
            ->assertRedirect(route('purchase.index'))
            ->assertSessionHas('error');

        // status & stok tidak berubah
        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => Purchase::STATUS_RECEIVED,
        ]);
        $this->assertEquals(10, (float) $bahanBaku->refresh()->stock);
    }

    public function test_update_received_purchase_rolls_back_and_reapplies_stock(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku();
        $supplier = $this->makeSupplier();

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku, [
            'supplier_id' => $supplier->id,
        ]));
        $purchase = Purchase::with('items')->first();

        $this->assertEquals(10, (float) $bahanBaku->refresh()->stock);

        // update: quantity 10 -> 4, harga 20000 -> 25000
        $this->actingAs($user)->put(route('purchase.update', $purchase->id), [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-11-01',
            'description' => 'Revisi pembelian',
            'status' => '1',
            'items' => [
                'bahan_baku_id' => [$bahanBaku->id],
                'quantity' => ['4'],
                'unit_price' => ['25000'],
                'expired_date' => ['2026-11-20'],
                'description' => ['batch revisi'],
            ],
        ])->assertRedirect(route('purchase.index'))->assertSessionHas('success');

        $bahanBaku->refresh();
        $this->assertEquals(4, (float) $bahanBaku->stock);
        $this->assertEquals(25000, (float) $bahanBaku->price);

        $purchase = Purchase::with('items')->find($purchase->id);
        $this->assertSame('2026-11-01', $purchase->purchase_date->format('Y-m-d'));
        $this->assertEquals(100000, (float) $purchase->total);
        $this->assertSame(1, $purchase->items->count());
        $this->assertEquals(4, (float) $purchase->items->first()->quantity);
        $this->assertEquals(4, (float) $purchase->items->first()->remaining_qty);

        // batch yang sudah terpakai tidak dapat diubah
        $purchase->items->first()->update(['remaining_qty' => 1]);

        $this->actingAs($user)->put(route('purchase.update', $purchase->id), [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-11-02',
            'status' => '1',
            'items' => [
                'bahan_baku_id' => [$bahanBaku->id],
                'quantity' => ['9'],
                'unit_price' => ['26000'],
            ],
        ])->assertRedirect(route('purchase.index'))->assertSessionHas('error');

        $this->assertEquals(4, (float) $bahanBaku->refresh()->stock);
    }

    public function test_batch_inventory_lists_each_batch_separately(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku();
        $supplier = $this->makeSupplier();

        // tiga pembelian untuk bahan baku yang sama -> tiga batch terpisah
        $batches = [
            ['2026-10-30', '10', '20000', '2026-10-30'],
            ['2026-11-15', '15', '22000', '2026-11-15'],
            ['2026-12-20', '20', '21000', '2026-12-20'],
        ];

        foreach ($batches as $batch) {
            $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku, [
                'supplier_id' => $supplier->id,
                'purchase_date' => $batch[0],
                'items' => [
                    'bahan_baku_id' => [$bahanBaku->id],
                    'quantity' => [$batch[1]],
                    'unit_price' => [$batch[2]],
                    'expired_date' => [$batch[3]],
                ],
            ]))->assertRedirect(route('purchase.index'));
        }

        $this->assertSame(3, PurchaseItem::count());
        $this->assertEquals(45, (float) $bahanBaku->refresh()->stock);

        // halaman batch/lot dapat diakses
        $this->actingAs($user)->get(route('batch_bahan_baku.index'))
            ->assertOk()
            ->assertSee('Batch / Lot Stok Bahan Baku');

        // setiap batch muncul sebagai baris tersendiri
        $this->actingAs($user)->get(route('batch_bahan_baku.data'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'DT_RowIndex',
                        'batch_code',
                        'bahan_baku_id',
                        'satuan',
                        'quantity',
                        'remaining_qty',
                        'unit_price',
                        'remaining_value',
                        'received_date',
                        'expired_date',
                        'supplier',
                        'purchase',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');

        // format angka memakai format Indonesia (10 tidak tampil sebagai "10.000")
        $this->actingAs($user)->get(route('batch_bahan_baku.data'))
            ->assertOk()
            ->assertJsonPath('data.0.quantity', '10')
            ->assertJsonPath('data.0.unit_price', '20.000,00')
            ->assertJsonPath('data.0.remaining_value', '200.000,00');

        // filter bahan baku + hanya batch yang masih ada stok
        $this->actingAs($user)->get(route('batch_bahan_baku.data', [
            'bahan_baku_id' => $bahanBaku->id,
            'only_available' => '1',
        ]))->assertOk()->assertJsonCount(3, 'data');

        // filter rentang kedaluwarsa
        $this->actingAs($user)->get(route('batch_bahan_baku.data', ['expired_from' => '2026-12-01']))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // pembelian yang dibatalkan tidak lagi muncul sebagai batch
        $purchase = Purchase::orderBy('id')->first();
        $this->actingAs($user)->delete(route('purchase.destroy', $purchase->id))
            ->assertSessionHas('success');

        $this->actingAs($user)->get(route('batch_bahan_baku.data'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_edit_form_renders_quantity_without_thousand_separator(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku();

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku, [
            'items' => [
                'bahan_baku_id' => [$bahanBaku->id],
                'quantity' => ['3'],
                'unit_price' => ['30'],
                'expired_date' => ['2026-10-30'],
            ],
        ]))->assertRedirect(route('purchase.index'));

        $purchase = Purchase::first();
        $html = $this->actingAs($user)->get(route('purchase.edit', $purchase->id))
            ->assertOk()
            ->getContent();

        // nilai decimal mentah ("3.000") tidak boleh dirender ke input number:
        // pada locale Indonesia angka tersebut terbaca sebagai 3000
        $this->assertStringNotContainsString('value="3.000"', $html);
        $this->assertMatchesRegularExpression(
            '/name="items\[quantity\]\[\]"\s+class="form-control"\s+value="3"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/name="items\[unit_price\]\[\]"\s+class="form-control"\s+value="30"/',
            $html
        );

        // subtotal baris memakai format Indonesia (3 x 30 = 90)
        $this->assertStringContainsString('90,00', $html);
    }

    public function test_sidebar_highlights_purchase_and_batch_menus(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku();

        $this->actingAs($user)->post(route('purchase.store'), $this->payload($bahanBaku));
        $purchase = Purchase::first();

        $this->assertSidebarMenu(route('purchase.index'), $user, route('purchase.index'));
        $this->assertSidebarMenu(route('purchase.create'), $user, route('purchase.index'));
        $this->assertSidebarMenu(route('purchase.edit', $purchase->id), $user, route('purchase.index'));
        $this->assertSidebarMenu(route('batch_bahan_baku.index'), $user, route('batch_bahan_baku.index'));
    }

    private function assertSidebarMenu(string $pageUrl, User $user, string $expectedHref): void
    {
        $html = $this->actingAs($user)->get($pageUrl)->assertOk()->getContent();

        // Ambil <li class="active"> pertama yang mengandung <a class="nav-link">
        preg_match('/<li class="active">\s*<a class="nav-link" href="([^"]*)"/', $html, $m);

        $this->assertNotNull($m[1] ?? null, 'Tidak ditemukan menu sidebar aktif di: ' . $pageUrl);
        $this->assertSame($expectedHref, $m[1], 'Menu aktif di sidebar tidak sesuai untuk: ' . $pageUrl);
    }
}
