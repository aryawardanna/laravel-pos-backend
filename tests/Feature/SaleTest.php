<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Menu;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItemUsage;
use App\Models\Satuan;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Penjualan / kasir: jual menu otomatis mengurangi stok bahan baku sesuai resep
 * (menu_bahan_bakus) memakai FEFO per batch, jejaknya di kartu stok + sale_item_usages,
 * dan pembatalan mengembalikan stok ke batch semula.
 */
class SaleTest extends TestCase
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
            'min_stock' => 1,
            'status' => 1,
        ]);
    }

    /**
     * Menu dengan resep: tiap baris resep = [bahan baku, qty per porsi].
     */
    private function makeMenu(float $price, array $recipes, string $name = 'Kopi Susu'): Menu
    {
        $menu = Menu::create([
            'name' => $name,
            'code' => 'KS',
            'price' => $price,
            'status' => 1,
        ]);

        foreach ($recipes as [$bahanBaku, $quantity]) {
            $menu->bahanBakus()->attach($bahanBaku->id, ['quantity' => $quantity]);
        }

        return $menu;
    }

    /**
     * Terima pembelian = membuat satu batch/lot baru.
     */
    private function receivePurchase(User $user, BahanBaku $bahanBaku, string $quantity, string $expiredDate): void
    {
        $this->actingAs($user)->post(route('purchase.store'), [
            'supplier_id' => Supplier::create(['name' => 'PT Susu Nusantara', 'status' => 1])->id,
            'purchase_date' => Carbon::today()->toDateString(),
            'status' => '1',
            'items' => [
                'bahan_baku_id' => [$bahanBaku->id],
                'quantity' => [$quantity],
                'unit_price' => ['20000'],
                'expired_date' => [$expiredDate],
            ],
        ])->assertRedirect(route('purchase.index'));
    }

    /**
     * Payload transaksi kasir.
     */
    private function payload(Menu $menu, string $quantity, array $overrides = []): array
    {
        return array_merge([
            'sale_date' => Carbon::today()->toDateString(),
            'menu_id' => [$menu->id],
            'quantity' => [$quantity],
            'discount' => '0',
            'tax' => '0',
            'paid' => '500000',
            'payment_method' => 'cash',
        ], $overrides);
    }

    private function assertBatchTotalEqualsStock(BahanBaku $bahanBaku): void
    {
        $this->assertEqualsWithDelta(
            (float) $bahanBaku->fresh()->stock,
            (float) PurchaseItem::where('bahan_baku_id', $bahanBaku->id)->sum('remaining_qty'),
            0.0005,
            'Total sisa batch tidak sama dengan stok bahan baku'
        );
    }

    public function test_kasir_page_shows_menu_with_max_portion(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(10);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $this->actingAs($user)->get(route('sale.create'))
            ->assertOk()
            ->assertSee('Kasir / Transaksi Baru')
            ->assertSee('Kopi Susu')
            ->assertSee('Sisa 40 porsi');

        // API info menu: 10 liter / 0.25 per porsi = 40 porsi
        $this->actingAs($user)->get(route('sale.menu-info', $menu->id))
            ->assertOk()
            ->assertJsonPath('max_qty', 40)
            ->assertJsonPath('recipes.0.need_per_portion', 0.25)
            ->assertJsonPath('recipes.0.available', 10);
    }

    public function test_store_sale_reduces_stock_fefo_across_batches(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(0);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $this->receivePurchase($user, $bahanBaku, '0.6', Carbon::today()->addDays(7)->toDateString());
        $this->receivePurchase($user, $bahanBaku, '5', Carbon::today()->addDays(30)->toDateString());

        $this->assertEqualsWithDelta(5.6, (float) $bahanBaku->refresh()->stock, 0.0005);

        // 4 porsi butuh 1 liter: 0.6 dari batch kedaluwarsa terdekat, 0.4 dari batch berikutnya
        $this->actingAs($user)->post(route('sale.store'), $this->payload($menu, '4'))
            ->assertRedirect(route('sale.show', Sale::first()->id))
            ->assertSessionHas('success');

        $sale = Sale::with('items')->first();
        $this->assertSame(Sale::STATUS_COMPLETED, (int) $sale->status);
        $this->assertStringStartsWith('TRX-' . Carbon::today()->format('Ymd') . '-', $sale->code);
        $this->assertEquals(100000, (float) $sale->subtotal);
        $this->assertEquals(100000, (float) $sale->total);
        $this->assertEquals(400000, (float) $sale->change_amount);
        $this->assertSame($user->id, (int) $sale->created_by);

        $this->assertSame(1, $sale->items->count());
        $this->assertEqualsWithDelta(4, (float) $sale->items->first()->quantity, 0.0005);

        // FEFO: batch kedaluwarsa paling awal habis lebih dulu
        $batches = PurchaseItem::orderBy('id')->get();

        $this->assertEqualsWithDelta(0, (float) $batches[0]->remaining_qty, 0.0005);
        $this->assertEqualsWithDelta(4.6, (float) $batches[1]->remaining_qty, 0.0005);
        $this->assertEqualsWithDelta(4.6, (float) $bahanBaku->refresh()->stock, 0.0005);
        $this->assertBatchTotalEqualsStock($bahanBaku);

        // jejak pemakaian per batch
        $usages = SaleItemUsage::orderBy('id')->get();
        $this->assertSame(2, $usages->count());
        $this->assertSame($batches[0]->batch_code, $usages[0]->batch_code);
        $this->assertEqualsWithDelta(0.6, (float) $usages[0]->quantity, 0.0005);
        $this->assertSame($batches[1]->batch_code, $usages[1]->batch_code);
        $this->assertEqualsWithDelta(0.4, (float) $usages[1]->quantity, 0.0005);

        // kartu stok: dua baris keluar dengan saldo bertingkat
        $movements = StockMovement::where('type', StockMovement::TYPE_OUT)->orderBy('id')->get();
        $this->assertSame(2, $movements->count());
        $this->assertSame($sale->id, (int) $movements[0]->sale_id);
        $this->assertEqualsWithDelta(0.6, (float) $movements[0]->quantity_out, 0.0005);
        $this->assertEqualsWithDelta(5.0, (float) $movements[0]->balance, 0.0005);
        $this->assertEqualsWithDelta(0.4, (float) $movements[1]->quantity_out, 0.0005);
        $this->assertEqualsWithDelta(4.6, (float) $movements[1]->balance, 0.0005);
    }

    public function test_store_sale_is_rejected_when_stock_is_not_enough(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(0);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $this->receivePurchase($user, $bahanBaku, '1', Carbon::today()->addDays(30)->toDateString());

        // 5 porsi butuh 1.25 liter, stok hanya 1 liter
        $this->actingAs($user)->post(route('sale.store'), $this->payload($menu, '5'))
            ->assertRedirect()
            ->assertSessionHas('error');

        // transaksi dibatalkan (rollback): tidak ada penjualan, stok & batch tidak berubah
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleItemUsage::count());
        $this->assertSame(0, StockMovement::where('type', StockMovement::TYPE_OUT)->count());
        $this->assertEqualsWithDelta(1, (float) $bahanBaku->refresh()->stock, 0.0005);
        $this->assertEqualsWithDelta(1, (float) PurchaseItem::first()->remaining_qty, 0.0005);
        $this->assertBatchTotalEqualsStock($bahanBaku);
    }

    public function test_store_sale_does_not_use_expired_batch(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(0);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        // batch A sudah kedaluwarsa kemarin, batch B masih layak
        $this->receivePurchase($user, $bahanBaku, '5', Carbon::today()->subDay()->toDateString());
        $this->receivePurchase($user, $bahanBaku, '1', Carbon::today()->addDays(30)->toDateString());

        $this->actingAs($user)->post(route('sale.store'), $this->payload($menu, '4'))
            ->assertSessionHas('success');

        $batches = PurchaseItem::orderBy('id')->get();

        // batch kedaluwarsa tidak tersentuh, pemakaian hanya dari batch layak
        $this->assertEqualsWithDelta(5, (float) $batches[0]->remaining_qty, 0.0005);
        $this->assertEqualsWithDelta(0, (float) $batches[1]->remaining_qty, 0.0005);
        $this->assertEqualsWithDelta(5, (float) $bahanBaku->refresh()->stock, 0.0005);

        $usages = SaleItemUsage::get();
        $this->assertSame(1, $usages->count());
        $this->assertSame($batches[1]->batch_code, $usages->first()->batch_code);
        $this->assertBatchTotalEqualsStock($bahanBaku);

        // batch layak sudah habis -> transaksi berikutnya ditolak walau stok masih ada
        $this->actingAs($user)->post(route('sale.store'), $this->payload($menu, '4'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Sale::count());
        $this->assertEqualsWithDelta(5, (float) $bahanBaku->refresh()->stock, 0.0005);
    }

    public function test_cancel_sale_returns_stock_to_the_original_batches(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(0);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $this->receivePurchase($user, $bahanBaku, '0.6', Carbon::today()->addDays(7)->toDateString());
        $this->receivePurchase($user, $bahanBaku, '5', Carbon::today()->addDays(30)->toDateString());

        $this->actingAs($user)->post(route('sale.store'), $this->payload($menu, '4'));
        $sale = Sale::first();

        $this->assertEqualsWithDelta(4.6, (float) $bahanBaku->refresh()->stock, 0.0005);

        $this->actingAs($user)->delete(route('sale.destroy', $sale->id))
            ->assertRedirect(route('sale.index'))
            ->assertSessionHas('success');

        // stok kembali persis ke batch semula (0.6 + 5)
        $batches = PurchaseItem::orderBy('id')->get();
        $this->assertEqualsWithDelta(0.6, (float) $batches[0]->remaining_qty, 0.0005);
        $this->assertEqualsWithDelta(5, (float) $batches[1]->remaining_qty, 0.0005);
        $this->assertEqualsWithDelta(5.6, (float) $bahanBaku->refresh()->stock, 0.0005);
        $this->assertBatchTotalEqualsStock($bahanBaku);

        // record tetap ada dengan status dibatalkan + kartu stok masuk dari penjualan ini
        $this->assertSame(Sale::STATUS_CANCELLED, (int) $sale->refresh()->status);

        $movements = StockMovement::where('type', StockMovement::TYPE_IN)
            ->where('sale_id', $sale->id)
            ->orderBy('id')
            ->get();

        $this->assertSame(2, $movements->count());
        $this->assertEqualsWithDelta(0.6, (float) $movements[0]->quantity_in, 0.0005);
        $this->assertEqualsWithDelta(0.4, (float) $movements[1]->quantity_in, 0.0005);

        // pembatalan kedua ditolak
        $this->actingAs($user)->delete(route('sale.destroy', $sale->id))
            ->assertRedirect(route('sale.index'))
            ->assertSessionHas('error');

        $this->assertEqualsWithDelta(5.6, (float) $bahanBaku->refresh()->stock, 0.0005);
    }

    public function test_sale_index_show_and_datatable_json_with_filters(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(0);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $this->receivePurchase($user, $bahanBaku, '4', Carbon::today()->addDays(30)->toDateString());
        $this->actingAs($user)->post(route('sale.store'), $this->payload($menu, '2'));

        $sale = Sale::first();
        $batchCode = PurchaseItem::first()->batch_code;

        $this->actingAs($user)->get(route('sale.index'))
            ->assertOk()
            ->assertSee('Penjualan / Kasir');

        // halaman detail menampilkan menu + batch yang terpakai (jejak FEFO)
        $this->actingAs($user)->get(route('sale.show', $sale->id))
            ->assertOk()
            ->assertSee($sale->code)
            ->assertSee('Kopi Susu')
            ->assertSee('Pemakaian Bahan Baku (FEFO per Batch)')
            ->assertSee($batchCode)
            ->assertSee(route('sale.print', $sale->id));

        $datatable = $this->actingAs($user)->get(route('sale.data'))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'DT_RowIndex',
                        'code',
                        'sale_date',
                        'item_count',
                        'total',
                        'payment_method',
                        'status',
                        'created_by',
                        'action',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');

        // kolom aksi menyediakan tautan cetak struk thermal
        $action = json_decode($datatable->getContent(), true)['data'][0]['action'];
        $this->assertStringContainsString(route('sale.print', $sale->id), $action);

        $this->actingAs($user)->get(route('sale.data', ['code' => $sale->code]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)->get(route('sale.data', ['status' => Sale::STATUS_CANCELLED]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)->get(route('sale.data', ['date_from' => Carbon::today()->addDay()->toDateString()]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_kasir_page_shows_menu_image_and_default_when_empty(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(5);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        // menu belum punya gambar -> gambar default tampil di kartu menu
        $this->actingAs($user)->get(route('sale.create'))
            ->assertOk()
            ->assertSee('images/default-menu.svg');

        file_put_contents(public_path('images/menu/uji-menu.jpg'), 'dummy');
        $menu->update(['image' => 'uji-menu.jpg']);

        try {
            // gambar menu tersedia -> dipakai di kartu menu & thumbnail keranjang
            $response = $this->actingAs($user)->get(route('sale.create'))
                ->assertOk()
                ->assertSee('images/menu/uji-menu.jpg');

            $this->assertStringContainsString(
                'data-image="' . asset('images/menu/uji-menu.jpg') . '"',
                $response->getContent()
            );
        } finally {
            @unlink(public_path('images/menu/uji-menu.jpg'));
        }
    }

    public function test_store_sale_can_redirect_straight_to_thermal_receipt(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(0);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $this->receivePurchase($user, $bahanBaku, '4', Carbon::today()->addDays(30)->toDateString());

        // opsi "cetak struk" dicentang di halaman kasir
        $this->actingAs($user)->post(route('sale.store'), $this->payload($menu, '2', ['print_receipt' => '1']))
            ->assertRedirect(route('sale.print', Sale::first()->id))
            ->assertSessionHas('success');
    }

    public function test_print_receipt_page_is_ready_for_thermal_printer(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(0);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $this->receivePurchase($user, $bahanBaku, '4', Carbon::today()->addDays(30)->toDateString());

        $this->actingAs($user)->post(route('sale.store'), $this->payload($menu, '2', [
            'discount' => '5000',
            'tax' => '2000',
            'payment_method' => 'qris',
            'description' => 'Meja 4',
        ]));

        $sale = Sale::first();

        file_put_contents('/tmp/receipt.html', $this->actingAs($user)->get(route('sale.print', $sale->id))->getContent());

        // struk default 80mm siap dicetak ke printer thermal.
        // @page wajib berisi dua <length> ("80mm 297mm"): "80mm auto" tidak valid
        // dan membuat browser memakai ukuran default printer (A4).
        $this->actingAs($user)->get(route('sale.print', $sale->id))
            ->assertOk()
            ->assertSee('size: 80mm 297mm')
            ->assertSee('var fixedHeight = 297')
            ->assertSee('width: 80mm')     // struk tidak melebar mengikuti kertas A4
            ->assertSee(config('pos.store_name'))
            ->assertSee($sale->code)
            ->assertSee('Kopi Susu')
            ->assertSee('2 x 25.000,00')
            ->assertSee('50.000,00')    // subtotal
            ->assertSee('-5.000,00')    // diskon
            ->assertSee('47.000,00')    // total: 50.000 - 5.000 + 2.000
            ->assertSee('QRIS')
            ->assertSee('Meja 4')
            ->assertSee($user->name)
            ->assertSee(config('pos.receipt_footer'));

        // kertas 58mm untuk printer thermal kecil
        $this->actingAs($user)->get(route('sale.print', ['id' => $sale->id, 'paper' => 58]))
            ->assertOk()
            ->assertSee('size: 58mm 297mm')
            ->assertSee('var paperWidth = 58');

        // tinggi kertas bisa dikunci, mis. roll 80 x 150 mm.
        $this->actingAs($user)->get(route('sale.print', ['id' => $sale->id, 'height' => 150]))
            ->assertOk()
            ->assertSee('size: 80mm 150mm')
            ->assertSee('var fixedHeight = 150');

        // height=0 -> tinggi dihitung dari isi struk saat mencetak
        $this->actingAs($user)->get(route('sale.print', ['id' => $sale->id, 'height' => 0]))
            ->assertOk()
            ->assertSee('var fixedHeight = 0')
            ->assertSee('applyPageSize')
            ->assertSee('pageshow');

        // transaksi dibatalkan -> struk ikut ditandai DIBATALKAN
        $this->actingAs($user)->delete(route('sale.destroy', $sale->id));

        $this->actingAs($user)->get(route('sale.print', $sale->id))
            ->assertOk()
            ->assertSee('DIBATALKAN');
    }

    public function test_store_sale_materializes_stock_without_batch_as_adjustment_lot(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(1);   // stok awal tanpa batch/lot
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $this->assertSame(0, PurchaseItem::count());

        $this->actingAs($user)->post(route('sale.store'), $this->payload($menu, '2'))
            ->assertSessionHas('success');

        $batch = PurchaseItem::first();
        $this->assertSame(PurchaseItem::SOURCE_ADJUSTMENT, $batch->source);
        $this->assertSame('ADJ-SUSU', $batch->batch_code);
        $this->assertEqualsWithDelta(0.5, (float) $batch->remaining_qty, 0.0005);
        $this->assertEqualsWithDelta(0.5, (float) $bahanBaku->refresh()->stock, 0.0005);
        $this->assertBatchTotalEqualsStock($bahanBaku);
    }
}
