<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Satuan;
use App\Models\User;
use File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BahanBakuTest extends TestCase
{
    use RefreshDatabase;

    public function test_bahan_baku_data_returns_datatables_json(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $satuan = Satuan::create(['name' => 'Kilogram', 'code' => 'Kg', 'status' => 1]);

        BahanBaku::create([
            'name' => 'Gula Pasir',
            'code' => 'GULA',
            'satuan_id' => $satuan->id,
            'price' => 15000,
            'stock' => 250,
            'min_stock' => 25,
            'description' => 'Gula pasir putih',
            'image' => 'gula.jpg',
            'status' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // halaman index dapat diakses
        $this->actingAs($user)
            ->get(route('bahan_baku.index'))
            ->assertOk()
            ->assertSee('Bahan Bakus');

        // endpoint datatable mengembalikan JSON sesuai struktur
        $response = $this->actingAs($user)
            ->get(route('bahan_baku.data'))
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
                        'satuan_id',
                        'price',
                        'stock',
                        'min_stock',
                        'status',
                        'created_by',
                        'updated_by',
                        'action',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_bahan_baku_crud_flow_soft_delete(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $satuan = Satuan::create(['name' => 'Kilogram', 'code' => 'Kg', 'status' => 1]);

        $this->actingAs($user)
            ->get(route('bahan_baku.create'))
            ->assertOk();

        // create — created_by & updated_by diisi user yang login
        $this->actingAs($user)
            ->post(route('bahan_baku.store'), [
                'name' => 'Kopi Biji Arabica',
                'code' => 'KOPI',
                'satuan_id' => $satuan->id,
                'price' => '120000',
                'stock' => '80',
                'min_stock' => '10',
                'description' => 'Kopi biji arabica 100%',
                'image' => UploadedFile::fake()->image('kopi.jpg', 100, 100),
                'status' => '1',
            ])
            ->assertRedirect(route('bahan_baku.index'));

        $this->assertDatabaseHas('bahan_bakus', [
            'name' => 'Kopi Biji Arabica',
            'code' => 'KOPI',
            'satuan_id' => $satuan->id,
            'status' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $bahanBaku = BahanBaku::where('code', 'KOPI')->first();

        // gambar harus didisk terterimpan di public/images/bahan_baku
        $storeImagePath = public_path('images/bahan_baku/' . $bahanBaku->image);
        $this->assertNotNull($bahanBaku->image);
        $this->assertTrue(File::exists($storeImagePath), 'Gambar bahan baku tidak didisk terimpan: ' . $storeImagePath);

        // edit page menampilkan data lama
        $this->actingAs($user)
            ->get(route('bahan_baku.edit', $bahanBaku->id))
            ->assertOk()
            ->assertSee('Kopi Biji Arabica');

        // update — updated_by terisi user yang login
        $this->actingAs($user)
            ->put(route('bahan_baku.update', $bahanBaku->id), [
                'name' => 'Kopi Biji Robusta',
                'code' => 'KOPI-R',
                'satuan_id' => $satuan->id,
                'price' => '95000',
                'stock' => '60',
                'min_stock' => '10',
                'description' => 'Kopi biji robusta 100%',
                'image' => UploadedFile::fake()->image('kopi2.jpg', 100, 100),
                'status' => '0',
            ])
            ->assertRedirect(route('bahan_baku.index'));

        $this->assertDatabaseHas('bahan_bakus', [
            'id' => $bahanBaku->id,
            'name' => 'Kopi Biji Robusta',
            'status' => 0,
            'updated_by' => $user->id,
        ]);

        // update gambar: gambar lama terhapus, gambar baru didisk terimpan
        $bahanBaku = BahanBaku::find($bahanBaku->id);
        $this->assertTrue(!File::exists($storeImagePath), 'Gambar lama harus terhapus saat update: ' . $storeImagePath);
        $updateImagePath = public_path('images/bahan_baku/' . $bahanBaku->image);
        $this->assertTrue(File::exists($updateImagePath), 'Gambar baru tidak didisk terimpan: ' . $updateImagePath);

        // cleanup gambar test
        foreach ([$storeImagePath, $updateImagePath] as $imagePath) {
            if (File::exists($imagePath)) {
                File::delete($imagePath);
            }
        }

        // delete — soft delete: status diubah menjadi -1, record tetap ada
        $this->actingAs($user)
            ->delete(route('bahan_baku.destroy', $bahanBaku->id))
            ->assertRedirect(route('bahan_baku.index'));

        $this->assertDatabaseHas('bahan_bakus', [
            'id' => $bahanBaku->id,
            'status' => -1,
            'updated_by' => $user->id,
        ]);

        // record soft-deleted tidak muncul di datatable
        $this->actingAs($user)
            ->get(route('bahan_baku.data'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_sidebar_highlights_bahan_baku_menu_on_subpages(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $satuan = Satuan::create(['name' => 'Kilogram', 'code' => 'Kg', 'status' => 1]);
        $bahanBaku = BahanBaku::create([
            'name' => 'Gula Pasir',
            'code' => 'GULA',
            'satuan_id' => $satuan->id,
            'status' => 1,
        ]);

        // Menu "Bahan Baku" tetap ter-highlight di halaman index, create, dan edit
        $this->assertSidebarMenuActive(route('bahan_baku.index'), $user);
        $this->assertSidebarMenuActive(route('bahan_baku.create'), $user);
        $this->assertSidebarMenuActive(route('bahan_baku.edit', $bahanBaku->id), $user);
    }

    private function assertSidebarMenuActive(string $pageUrl, User $user): void
    {
        $html = $this->actingAs($user)->get($pageUrl)->assertOk()->getContent();

        // Ambil <li class="active"> pertama yang mengandung <a class="nav-link">
        preg_match('/<li class="active">\s*<a class="nav-link" href="([^"]*)"/', $html, $m);

        $this->assertNotNull($m[1] ?? null, 'Tidak ditemukan menu sidebar aktif di: ' . $pageUrl);
        $this->assertSame(route('bahan_baku.index'), $m[1], 'Menu aktif di sidebar bukan "Bahan Baku" untuk: ' . $pageUrl);
    }
}