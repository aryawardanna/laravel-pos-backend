<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Satuan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_data_returns_datatables_json(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Minuman']);
        $satuan = Satuan::create(['name' => 'Kilogram', 'code' => 'Kg', 'status' => 1]);
        $bahan1 = BahanBaku::create(['name' => 'Kopi', 'code' => 'KOPI', 'satuan_id' => $satuan->id, 'status' => 1]);
        $bahan2 = BahanBaku::create(['name' => 'Susu', 'code' => 'SUSU', 'satuan_id' => $satuan->id, 'status' => 1]);

        $menu = Menu::create([
            'name' => 'Kopi Cappuccino',
            'code' => 'CAPPU',
            'category_id' => $category->id,
            'price' => 25000,
            'status' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $menu->bahanBakus()->sync([
            $bahan1->id => ['quantity' => 0.02],
            $bahan2->id => ['quantity' => 0.25],
        ]);

        // halaman index dapat diakses
        $this->actingAs($user)
            ->get(route('menu.index'))
            ->assertOk()
            ->assertSee('Menus');

        // endpoint datatable mengembalikan JSON sesuai struktur
        $response = $this->actingAs($user)
            ->get(route('menu.data'))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'DT_RowIndex',
                        'name',
                        'code',
                        'image',
                        'category_id',
                        'price',
                        'bahan_baku_count',
                        'status',
                        'created_by',
                        'updated_by',
                        'action',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');

        // JSON harus berisi jumlah bahan baku
        $data = json_decode($response->getContent(), true)['data'][0];
        $this->assertStringContainsString('2', $data['bahan_baku_count']);
    }

    public function test_menu_crud_with_bahan_baku_sync(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Minuman']);
        $satuan = Satuan::create(['name' => 'Kilogram', 'code' => 'Kg', 'status' => 1]);
        $bahan1 = BahanBaku::create(['name' => 'Kopi', 'code' => 'KOPI', 'satuan_id' => $satuan->id, 'status' => 1]);
        $bahan2 = BahanBaku::create(['name' => 'Susu', 'code' => 'SUSU', 'satuan_id' => $satuan->id, 'status' => 1]);
        $bahan3 = BahanBaku::create(['name' => 'Cup 12oz', 'code' => 'CUP12', 'satuan_id' => $satuan->id, 'status' => 1]);

        $this->actingAs($user)
            ->get(route('menu.create'))
            ->assertOk();

        // create menu dengan 2 bahan baku
        $this->actingAs($user)
            ->post(route('menu.store'), [
                'name' => 'Kopi Latte',
                'code' => 'LATTE',
                'category_id' => $category->id,
                'price' => '27000',
                'description' => 'Espresso dengan susu steam',
                'status' => '1',
                'ingredients' => [
                    'bahan_baku_id' => [$bahan1->id, $bahan2->id],
                    'quantity' => ['0.03', '0.3'],
                ],
            ])
            ->assertRedirect(route('menu.index'));

        $menu = Menu::where('code', 'LATTE')->first();
        $this->assertNotNull($menu);
        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'name' => 'Kopi Latte',
            'status' => 1,
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('menu_bahan_bakus', [
            'menu_id' => $menu->id,
            'bahan_baku_id' => $bahan1->id,
            'quantity' => 0.03,
        ]);
        $this->assertDatabaseHas('menu_bahan_bakus', [
            'menu_id' => $menu->id,
            'bahan_baku_id' => $bahan2->id,
            'quantity' => 0.3,
        ]);

        // __APPEND_MORE__
    }

    public function test_menu_update_syncs_bahan_baku_and_soft_delete(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Minuman']);
        $satuan = Satuan::create(['name' => 'Kilogram', 'code' => 'Kg', 'status' => 1]);
        $bahan1 = BahanBaku::create(['name' => 'Kopi', 'code' => 'KOPI', 'satuan_id' => $satuan->id, 'status' => 1]);
        $bahan2 = BahanBaku::create(['name' => 'Susu', 'code' => 'SUSU', 'satuan_id' => $satuan->id, 'status' => 1]);
        $bahan3 = BahanBaku::create(['name' => 'Cup 12oz', 'code' => 'CUP12', 'satuan_id' => $satuan->id, 'status' => 1]);

        $menu = Menu::create([
            'name' => 'Kopi Cappuccino',
            'code' => 'CAPPU',
            'category_id' => $category->id,
            'price' => 25000,
            'status' => 1,
        ]);
        $menu->bahanBakus()->sync([
            $bahan1->id => ['quantity' => 0.02],
            $bahan2->id => ['quantity' => 0.25],
        ]);

        // edit page menampilkan data lama
        $this->actingAs($user)
            ->get(route('menu.edit', $menu->id))
            ->assertOk()
            ->assertSee('Kopi Cappuccino');

        // update: ganti resep jadi 2 bahan baku lain (bahan2 quantity berubah, bahan1 dibuang)
        $this->actingAs($user)
            ->put(route('menu.update', $menu->id), [
                'name' => 'Ice Cappuccino 12oz',
                'code' => 'ICECAP',
                'category_id' => $category->id,
                'price' => '29000',
                'status' => '1',
                'ingredients' => [
                    'bahan_baku_id' => [$bahan2->id, $bahan3->id],
                    'quantity' => ['0.3', '1'],
                ],
            ])
            ->assertRedirect(route('menu.index'));

        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'name' => 'Ice Cappuccino 12oz',
            'updated_by' => $user->id,
        ]);
        // pivot: bahan1 dibuang, bahan2 & bahan3 ada dengan quantity baru
        $this->assertDatabaseMissing('menu_bahan_bakus', [
            'menu_id' => $menu->id,
            'bahan_baku_id' => $bahan1->id,
        ]);
        $this->assertDatabaseHas('menu_bahan_bakus', [
            'menu_id' => $menu->id,
            'bahan_baku_id' => $bahan2->id,
            'quantity' => 0.3,
        ]);
        $this->assertDatabaseHas('menu_bahan_bakus', [
            'menu_id' => $menu->id,
            'bahan_baku_id' => $bahan3->id,
            'quantity' => 1,
        ]);

        // soft delete: status -1, tidak muncul di datatable
        $this->actingAs($user)
            ->delete(route('menu.destroy', $menu->id))
            ->assertRedirect(route('menu.index'));

        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'status' => -1,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('menu.data'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_sidebar_highlights_menu_on_subpages(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $menu = Menu::create(['name' => 'Kopi', 'code' => 'KOPI', 'status' => 1]);

        // Menu "Produk / Menu" tetap ter-highlight di halaman index, create, dan edit
        $this->assertSidebarMenuActive(route('menu.index'), $user);
        $this->assertSidebarMenuActive(route('menu.create'), $user);
        $this->assertSidebarMenuActive(route('menu.edit', $menu->id), $user);
    }

    private function assertSidebarMenuActive(string $pageUrl, User $user): void
    {
        $html = $this->actingAs($user)->get($pageUrl)->assertOk()->getContent();

        preg_match('/<li class="active">\s*<a class="nav-link" href="([^"]*)"/', $html, $m);

        $this->assertNotNull($m[1] ?? null, 'Tidak ditemukan menu sidebar aktif di: ' . $pageUrl);
        $this->assertSame(route('menu.index'), $m[1], 'Menu aktif di sidebar bukan "Produk / Menu" untuk: ' . $pageUrl);
    }
}