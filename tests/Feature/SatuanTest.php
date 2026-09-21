<?php

namespace Tests\Feature;

use App\Models\Satuan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SatuanTest extends TestCase
{
    use RefreshDatabase;

    public function test_satuan_data_returns_datatables_json(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        Satuan::create([
            'name' => 'Kilogram',
            'code' => 'Kg',
            'description' => 'Satuan berat 1000 gram',
            'status' => 1,
        ]);

        // halaman index dapat diakses
        $this->actingAs($user)
            ->get(route('satuan.index'))
            ->assertOk()
            ->assertSee('Satuans');

        // endpoint datatable mengembalikan JSON sesuai struktur
        $response = $this->actingAs($user)
            ->get(route('satuan.data'))
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
                        'description',
                        'status',
                        'created_by',
                        'updated_by',
                        'action',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_satuan_crud_flow(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('satuan.create'))
            ->assertOk();

        // create — created_by & updated_by diisi user yang login
        $this->actingAs($user)
            ->post(route('satuan.store'), [
                'name' => 'Liter',
                'code' => 'Ltr',
                'description' => 'Satuan volume cairan',
                'status' => '1',
            ])
            ->assertRedirect(route('satuan.index'));

        $this->assertDatabaseHas('satuans', [
            'name' => 'Liter',
            'code' => 'Ltr',
            'status' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $satuan = Satuan::where('name', 'Liter')->first();

        // edit page
        $this->actingAs($user)
            ->get(route('satuan.edit', $satuan->id))
            ->assertOk()
            ->assertSee('Liter');

        // update — updated_by terisi user yang login
        $this->actingAs($user)
            ->put(route('satuan.update', $satuan->id), [
                'name' => 'Milliliter',
                'code' => 'Ml',
                'description' => 'Satuan volume cairan 1/1000 liter',
                'status' => '0',
            ])
            ->assertRedirect(route('satuan.index'));

        $this->assertDatabaseHas('satuans', [
            'id' => $satuan->id,
            'name' => 'Milliliter',
            'status' => 0,
            'updated_by' => $user->id,
        ]);

        // delete — soft delete: status diubah menjadi -1, record tetap ada
        $this->actingAs($user)
            ->delete(route('satuan.destroy', $satuan->id))
            ->assertRedirect(route('satuan.index'));

        $this->assertDatabaseHas('satuans', [
            'id' => $satuan->id,
            'status' => -1,
            'updated_by' => $user->id,
        ]);

        // record soft-deleted tidak muncul di datatable
        $this->actingAs($user)
            ->get(route('satuan.data'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_sidebar_highlights_satuan_menu_on_subpages(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $satuan = Satuan::create(['name' => 'Pieces', 'code' => 'Pcs', 'status' => 1]);

        // Menu "Satuan" tetap ter-highlight di halaman index, create, dan edit
        $this->assertSidebarMenuActive(route('satuan.index'), $user);
        $this->assertSidebarMenuActive(route('satuan.create'), $user);
        $this->assertSidebarMenuActive(route('satuan.edit', $satuan->id), $user);
    }

    private function assertSidebarMenuActive(string $pageUrl, User $user): void
    {
        $html = $this->actingAs($user)->get($pageUrl)->assertOk()->getContent();

        // Ambil <li class="active"> pertama yang mengandung <a class="nav-link">
        preg_match('/<li class="active">\s*<a class="nav-link" href="([^"]*)"/', $html, $m);

        $this->assertNotNull($m[1] ?? null, 'Tidak ditemukan menu sidebar aktif di: ' . $pageUrl);
        $this->assertSame(route('satuan.index'), $m[1], 'Menu aktif di sidebar bukan "Satuan" untuk: ' . $pageUrl);
    }
}