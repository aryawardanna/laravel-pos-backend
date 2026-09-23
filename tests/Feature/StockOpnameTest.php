<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Satuan;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeBahanBaku(string $name, string $code, float $stock, float $price = 0, string $satuanName = 'Liter'): BahanBaku
    {
        $satuan = Satuan::firstOrCreate(
            ['code' => $satuanName === 'Liter' ? 'Ltr' : 'Kg'],
            ['name' => $satuanName, 'status' => 1]
        );

        return BahanBaku::create([
            'name' => $name,
            'code' => $code,
            'satuan_id' => $satuan->id,
            'price' => $price,
            'stock' => $stock,
            'min_stock' => 5,
            'status' => 1,
        ]);
    }

    /**
     * Payload form opname.
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'opname_date' => '2026-09-24',
            'description' => 'Hitung fisik rutin',
            'status' => '1',
        ], $overrides);
    }

    public function test_stock_opname_index_and_datatable_json_with_filters(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku('Susu', 'SUSU', 10, 20000);

        $this->actingAs($user)->post(route('stock_opname.store'), $this->payload([
            'items' => [
                'bahan_baku_id' => [$susu->id],
                'physical_stock' => ['8'],
            ],
        ]))->assertRedirect(route('stock_opname.index'));

        $code = StockOpname::first()->code;
        $this->assertStringStartsWith('SO-20260924-', $code);

        // halaman index dapat diakses
        $this->actingAs($user)->get(route('stock_opname.index'))
            ->assertOk()
            ->assertSee('Stock Opname Bahan Baku');

        $this->actingAs($user)->get(route('stock_opname.data'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'DT_RowIndex',
                        'code',
                        'opname_date',
                        'item_count',
                        'difference_summary',
                        'status',
                        'created_by',
                        'action',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');

        // filter status
        $this->actingAs($user)->get(route('stock_opname.data', ['status' => StockOpname::STATUS_DRAFT]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // filter nomor opname
        $this->actingAs($user)->get(route('stock_opname.data', ['code' => $code]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // filter rentang tanggal
        $this->actingAs($user)->get(route('stock_opname.data', ['date_from' => '2026-10-01']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)->get(route('stock_opname.data', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_final_opname_applies_adjustment_and_records_stock_card(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku('Susu', 'SUSU', 75, 20000);
        $gula = $this->makeBahanBaku('Gula', 'GULA', 250, 15000, 'Kilogram');

        $this->actingAs($user)->post(route('stock_opname.store'), $this->payload([
            'items' => [
                'bahan_baku_id' => [$susu->id, $gula->id],
                'physical_stock' => ['73', '253'],
                'description' => ['Susu tumpah', null],
            ],
        ]))->assertRedirect(route('stock_opname.index'))->assertSessionHas('success');

        $opname = StockOpname::with('items')->first();

        $this->assertSame(StockOpname::STATUS_FINAL, (int) $opname->status);
        $this->assertSame(2, $opname->items->count());

        // snapshot stok sistem & selisih tersimpan
        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $opname->id,
            'bahan_baku_id' => $susu->id,
            'system_stock' => 75,
            'physical_stock' => 73,
            'difference' => -2,
        ]);

        // stok master disesuaikan dengan hasil hitung fisik
        $this->assertEquals(73, (float) $susu->refresh()->stock);
        $this->assertEquals(253, (float) $gula->refresh()->stock);

        // kartu stok mencatat dua penyesuaian dengan arah & saldo yang benar
        $movements = StockMovement::orderBy('id')->get();
        $this->assertCount(2, $movements);

        $this->assertSame(StockMovement::TYPE_ADJUSTMENT, $movements[0]->type);
        $this->assertEquals(0, (float) $movements[0]->quantity_in);
        $this->assertEquals(2, (float) $movements[0]->quantity_out);
        $this->assertEquals(73, (float) $movements[0]->balance);
        $this->assertSame($opname->code, $movements[0]->reference);
        $this->assertSame($opname->id, $movements[0]->stock_opname_id);

        $this->assertEquals(3, (float) $movements[1]->quantity_in);
        $this->assertEquals(0, (float) $movements[1]->quantity_out);
        $this->assertEquals(253, (float) $movements[1]->balance);
    }

    public function test_draft_opname_does_not_change_stock_until_finalized(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku('Susu', 'SUSU', 10, 20000);

        $this->actingAs($user)->post(route('stock_opname.store'), $this->payload([
            'status' => '0',
            'items' => [
                'bahan_baku_id' => [$susu->id],
                'physical_stock' => ['7'],
            ],
        ]))->assertRedirect(route('stock_opname.index'));

        $opname = StockOpname::first();

        // draft belum memengaruhi stok maupun kartu stok
        $this->assertSame(StockOpname::STATUS_DRAFT, (int) $opname->status);
        $this->assertEquals(10, (float) $susu->refresh()->stock);
        $this->assertSame(0, StockMovement::count());

        // selesaikan opname draft
        $this->actingAs($user)->post(route('stock_opname.finalize', $opname->id))
            ->assertRedirect(route('stock_opname.index'))
            ->assertSessionHas('success');

        $this->assertEquals(7, (float) $susu->refresh()->stock);
        $this->assertSame(1, StockMovement::count());

        // tidak dapat diselesaikan dua kali
        $this->actingAs($user)->post(route('stock_opname.finalize', $opname->id))
            ->assertRedirect(route('stock_opname.index'))
            ->assertSessionHas('error');

        $this->assertEquals(7, (float) $susu->refresh()->stock);
    }

    public function test_system_stock_snapshot_is_taken_from_master(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku('Susu', 'SUSU', 40, 20000);

        $this->actingAs($user)->post(route('stock_opname.store'), $this->payload([
            'status' => '0',
            'items' => [
                'bahan_baku_id' => [$susu->id],
                'physical_stock' => ['35'],
            ],
        ]));

        $item = StockOpnameItem::first();

        // stok sistem diambil dari master & selisih dihitung di sisi server
        $this->assertEquals(40, (float) $item->system_stock);
        $this->assertEquals(35, (float) $item->physical_stock);
        $this->assertEquals(-5, (float) $item->difference);
        $this->assertEquals(20000, (float) $item->unit_price);
    }

    public function test_update_is_only_allowed_for_draft(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku('Susu', 'SUSU', 10, 20000);

        $this->actingAs($user)->post(route('stock_opname.store'), $this->payload([
            'status' => '0',
            'items' => [
                'bahan_baku_id' => [$susu->id],
                'physical_stock' => ['8'],
            ],
        ]));

        $opname = StockOpname::first();

        // draft boleh diubah
        $this->actingAs($user)->get(route('stock_opname.edit', $opname->id))->assertOk();

        $this->actingAs($user)->put(route('stock_opname.update', $opname->id), $this->payload([
            'opname_date' => '2026-09-25',
            'status' => '0',
            'items' => [
                'bahan_baku_id' => [$susu->id],
                'physical_stock' => ['9'],
            ],
        ]))->assertRedirect(route('stock_opname.index'))->assertSessionHas('success');

        $this->assertEquals(9, (float) $opname->fresh()->items()->first()->physical_stock);
        $this->assertEquals(10, (float) $susu->refresh()->stock);

        // setelah diselesaikan, opname tidak dapat diubah lagi
        $this->actingAs($user)->post(route('stock_opname.finalize', $opname->id));
        $this->assertEquals(9, (float) $susu->refresh()->stock);

        $this->actingAs($user)->get(route('stock_opname.edit', $opname->id))
            ->assertRedirect(route('stock_opname.index'))
            ->assertSessionHas('error');

        $this->actingAs($user)->put(route('stock_opname.update', $opname->id), $this->payload([
            'opname_date' => '2026-09-26',
            'status' => '0',
            'items' => [
                'bahan_baku_id' => [$susu->id],
                'physical_stock' => ['1'],
            ],
        ]))->assertRedirect(route('stock_opname.index'))->assertSessionHas('error');

        $this->assertEquals(9, (float) $susu->refresh()->stock);
    }

    public function test_cancel_final_opname_reverts_adjustment(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku('Susu', 'SUSU', 10, 20000);

        $this->actingAs($user)->post(route('stock_opname.store'), $this->payload([
            'items' => [
                'bahan_baku_id' => [$susu->id],
                'physical_stock' => ['6'],
            ],
        ]));

        $opname = StockOpname::first();
        $this->assertEquals(6, (float) $susu->refresh()->stock);

        $this->actingAs($user)->delete(route('stock_opname.destroy', $opname->id))
            ->assertRedirect(route('stock_opname.index'))
            ->assertSessionHas('success');

        // stok dikembalikan & status menjadi dibatalkan
        $this->assertEquals(10, (float) $susu->refresh()->stock);
        $this->assertDatabaseHas('stock_opnames', [
            'id' => $opname->id,
            'status' => StockOpname::STATUS_CANCELLED,
        ]);

        // pembatalan tercatat sebagai penyesuaian balik di kartu stok
        $movements = StockMovement::orderBy('id')->get();
        $this->assertCount(2, $movements);
        $this->assertSame(StockMovement::TYPE_ADJUSTMENT, $movements[1]->type);
        $this->assertEquals(4, (float) $movements[1]->quantity_in);
        $this->assertEquals(10, (float) $movements[1]->balance);

        // pembatalan kedua ditolak
        $this->actingAs($user)->delete(route('stock_opname.destroy', $opname->id))
            ->assertRedirect(route('stock_opname.index'))
            ->assertSessionHas('error');
    }

    public function test_cancel_final_opname_is_blocked_when_later_movement_exists(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku('Susu', 'SUSU', 10, 20000);

        $this->actingAs($user)->post(route('stock_opname.store'), $this->payload([
            'items' => [
                'bahan_baku_id' => [$susu->id],
                'physical_stock' => ['9'],
            ],
        ]));

        $opname = StockOpname::first();
        $this->assertEquals(9, (float) $susu->refresh()->stock);

        // simulasikan pergerakan stok setelah opname (mis. pembelian diterima)
        StockMovement::create([
            'bahan_baku_id' => $susu->id,
            'movement_date' => '2026-09-26',
            'type' => StockMovement::TYPE_IN,
            'quantity_in' => 5,
            'quantity_out' => 0,
            'balance' => 14,
            'unit_price' => 20000,
            'reference' => 'PB-20260926-0001',
            'description' => 'Pembelian setelah opname',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->delete(route('stock_opname.destroy', $opname->id))
            ->assertRedirect(route('stock_opname.index'))
            ->assertSessionHas('error');

        // status & stok tidak berubah
        $this->assertDatabaseHas('stock_opnames', [
            'id' => $opname->id,
            'status' => StockOpname::STATUS_FINAL,
        ]);
        $this->assertEquals(9, (float) $susu->refresh()->stock);
    }

    public function test_sidebar_highlights_stock_opname_menu(): void
    {
        $user = $this->makeUser();
        $susu = $this->makeBahanBaku('Susu', 'SUSU', 10, 20000);

        $this->actingAs($user)->post(route('stock_opname.store'), $this->payload([
            'status' => '0',
            'items' => [
                'bahan_baku_id' => [$susu->id],
                'physical_stock' => ['8'],
            ],
        ]));

        $opname = StockOpname::first();

        $pages = [
            route('stock_opname.index'),
            route('stock_opname.create'),
            route('stock_opname.edit', $opname->id),
            route('stock_opname.show', $opname->id),
        ];

        foreach ($pages as $pageUrl) {
            $html = $this->actingAs($user)->get($pageUrl)->assertOk()->getContent();

            preg_match('/<li class="active">\s*<a class="nav-link" href="([^"]*)"/', $html, $m);

            $this->assertNotNull($m[1] ?? null, 'Tidak ditemukan menu sidebar aktif di: ' . $pageUrl);
            $this->assertSame(
                route('stock_opname.index'),
                $m[1],
                'Menu aktif di sidebar bukan "Stock Opname" untuk: ' . $pageUrl
            );
        }
    }
}
