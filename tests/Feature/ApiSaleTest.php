<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Category;
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

class ApiSaleTest extends TestCase
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

    private function makeMenu(float $price, array $recipes, string $name = 'Kopi Susu', ?Category $category = null): Menu
    {
        $menu = Menu::create([
            'name' => $name,
            'code' => 'KS',
            'category_id' => $category?->id,
            'price' => $price,
            'status' => 1,
        ]);

        foreach ($recipes as [$bahanBaku, $quantity]) {
            $menu->bahanBakus()->attach($bahanBaku->id, ['quantity' => $quantity]);
        }

        return $menu;
    }

    private function receivePurchase(User $user, BahanBaku $bahanBaku, string $quantity, string $expiredDate): void
    {
        $this->actingAs($user, 'sanctum')->post(route('purchase.store'), [
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

    private function apiPayload(Menu $menu, string $quantity, array $overrides = []): array
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

    /** @test */
    public function test_unauthenticated_access_is_rejected(): void
    {
        $menu = $this->makeMenu(25000, [[$this->makeBahanBaku(10), 0.25]]);

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/sales', $this->apiPayload($menu, '1'))
            ->assertUnauthorized();
        $this->withHeaders(['Accept' => 'application/json'])
            ->get('/api/sales')
            ->assertUnauthorized();
        $this->withHeaders(['Accept' => 'application/json'])
            ->get('/api/sales/1')
            ->assertUnauthorized();
        $this->withHeaders(['Accept' => 'application/json'])
            ->delete('/api/sales/1')
            ->assertUnauthorized();
        $this->withHeaders(['Accept' => 'application/json'])
            ->get('/api/sales/menu-info/1')
            ->assertUnauthorized();
    }

    /** @test */
    public function test_store_sale_api_success(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(10);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/sales', $this->apiPayload($menu, '4'));

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id', 'code', 'sale_date', 'subtotal', 'discount', 'tax',
                    'total', 'paid', 'change_amount', 'payment_method', 'status',
                    'status_label', 'item_count', 'created_by', 'created_at',
                    'items' => [
                        '*' => [
                            'id', 'menu_id', 'menu_name', 'quantity',
                            'unit_price', 'subtotal', 'description',
                        ],
                    ],
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertStringStartsWith('TRX-'.Carbon::today()->format('Ymd').'-', $response->json('data.code'));
        $this->assertSame(Sale::STATUS_COMPLETED, (int) $response->json('data.status'));
        $this->assertSame(100000, (int) $response->json('data.subtotal'));
        $this->assertSame(100000, (int) $response->json('data.total'));

        $this->assertEqualsWithDelta(9, (float) $bahanBaku->refresh()->stock, 0.0005);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $response->json('data.id')]);
    }

    /** @test */
    public function test_store_sale_api_validation_error(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/sales', []);

        $response->assertUnprocessable()
            ->assertJsonStructure(['success', 'message', 'errors']);

        $this->assertFalse($response->json('success'));
        $this->assertArrayHasKey('menu_id', $response->json('errors'));
    }

    /** @test */
    public function test_store_sale_api_insufficient_stock(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(0);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/sales', $this->apiPayload($menu, '100'));

        $response->assertStatus(400)
            ->assertJsonStructure(['success', 'message']);

        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function test_store_sale_api_paid_less_than_total(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(100);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/sales', $this->apiPayload($menu, '1', ['paid' => '10000']));

        $response->assertStatus(400)
            ->assertJsonStructure(['success', 'message']);

        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('kurang dari total', $response->json('message'));
    }

    /** @test */
    public function test_index_sales_api(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(100);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $this->actingAs($user, 'sanctum')
            ->post('/api/sales', $this->apiPayload($menu, '2'))
            ->assertCreated();

        $response = $this->actingAs($user, 'sanctum')->get('/api/sales');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'code', 'total', 'status', 'status_label', 'item_count'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertSame(1, $response->json('meta.total'));
    }

    /** @test */
    public function test_show_sale_api(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(10);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $createResp = $this->actingAs($user, 'sanctum')
            ->post('/api/sales', $this->apiPayload($menu, '4'))
            ->assertCreated();

        $saleId = $createResp->json('data.id');

        $response = $this->actingAs($user, 'sanctum')->get("/api/sales/{$saleId}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'code', 'total', 'items' => [
                        '*' => ['menu_id', 'menu_name', 'quantity', 'unit_price', 'subtotal'],
                    ],
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertSame($saleId, $response->json('data.id'));
        $this->assertCount(1, $response->json('data.items'));
    }

    /** @test */
    public function test_show_sale_api_not_found(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'sanctum')
            ->get('/api/sales/999999')
            ->assertNotFound()
            ->assertJsonStructure(['success', 'message']);
    }

    /** @test */
    public function test_destroy_sale_api_returns_stock(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(10);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $createResp = $this->actingAs($user, 'sanctum')
            ->post('/api/sales', $this->apiPayload($menu, '4'))
            ->assertCreated();

        $saleId = $createResp->json('data.id');

        $this->assertEqualsWithDelta(9, (float) $bahanBaku->fresh()->stock, 0.0005);

        $response = $this->actingAs($user, 'sanctum')->delete("/api/sales/{$saleId}");

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);

        $this->assertTrue($response->json('success'));

        $this->assertEqualsWithDelta(10, (float) $bahanBaku->fresh()->stock, 0.0005);

        $sale = Sale::find($saleId);
        $this->assertSame(Sale::STATUS_CANCELLED, (int) $sale->status);
    }

    /** @test */
    public function test_destroy_sale_api_already_cancelled(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(10);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $createResp = $this->actingAs($user, 'sanctum')
            ->post('/api/sales', $this->apiPayload($menu, '2'))
            ->assertCreated();

        $saleId = $createResp->json('data.id');

        $this->actingAs($user, 'sanctum')->delete("/api/sales/{$saleId}")->assertOk();

        $response = $this->actingAs($user, 'sanctum')->delete("/api/sales/{$saleId}");
        $response->assertStatus(400)
            ->assertJsonStructure(['success', 'message']);
    }

    /** @test */
    public function test_menu_info_api(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(10);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $response = $this->actingAs($user, 'sanctum')
            ->get("/api/sales/menu-info/{$menu->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'name', 'price', 'has_recipe', 'max_qty',
                    'expired_batches', 'recipes' => [
                        '*' => [
                            'bahan_baku_id', 'bahan_baku_name', 'satuan',
                            'need_per_portion', 'stock', 'available',
                            'expired_batches', 'max_portion',
                        ],
                    ],
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertSame($menu->id, $response->json('data.id'));
        $this->assertSame(40, $response->json('data.max_qty'));
    }

    /** @test */
    public function test_menu_info_api_not_found(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'sanctum')
            ->get('/api/sales/menu-info/999999')
            ->assertNotFound()
            ->assertJsonStructure(['success', 'message']);
    }

    /** @test */
    public function test_store_sale_api_multiple_menus_and_items(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(100);
        $menu1 = $this->makeMenu(30000, [[$bahanBaku, 0.2]], 'Nasi Goreng');
        $menu2 = $this->makeMenu(25000, [[$bahanBaku, 0.1]], 'Kopi Susu');

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/sales', [
                'sale_date' => Carbon::today()->toDateString(),
                'menu_id' => [$menu1->id, $menu2->id],
                'quantity' => ['2', '3'],
                'discount' => '10000',
                'tax' => '5000',
                'paid' => '200000',
                'payment_method' => 'cash',
            ])
            ->assertCreated();

        $data = $response->json('data');

        $this->assertSame(135000, (int) $data['subtotal']);
        $this->assertSame(130000, (int) $data['total']);
        $this->assertSame(70000, (int) $data['change_amount']);
        $this->assertCount(2, $data['items']);
    }

    /** @test */
    public function test_store_sale_api_creates_stock_movements_and_usages(): void
    {
        $user = $this->makeUser();
        $bahanBaku = $this->makeBahanBaku(0);
        $menu = $this->makeMenu(25000, [[$bahanBaku, 0.25]]);

        $this->receivePurchase($user, $bahanBaku, '5', Carbon::today()->addDays(30)->toDateString());

        $this->actingAs($user, 'sanctum')
            ->post('/api/sales', $this->apiPayload($menu, '4'))
            ->assertCreated();

        $this->assertDatabaseHas('sale_item_usages', [
            'bahan_baku_id' => $bahanBaku->id,
            'quantity' => 1.0,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'bahan_baku_id' => $bahanBaku->id,
            'type' => StockMovement::TYPE_OUT,
            'quantity_out' => 1.0,
        ]);
    }
}