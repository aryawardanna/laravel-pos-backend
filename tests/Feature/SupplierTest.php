<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_data_returns_datatables_json(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        Supplier::create([
            'name' => 'PT Sumber Jaya',
            'phone' => '021-5551234',
            'email' => 'cs@sumberjaya.co.id',
            'address' => 'Jl. Raya Kebayoran No. 12, Jakarta Selatan',
            'description' => 'Supplier bahan baku makanan',
            'status' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // halaman index dapat diakses
        $this->actingAs($user)
            ->get(route('supplier.index'))
            ->assertOk()
            ->assertSee('Suppliers');

        // endpoint datatable mengembalikan JSON sesuai struktur
        $response = $this->actingAs($user)
            ->get(route('supplier.data'))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'DT_RowIndex',
                        'name',
                        'phone',
                        'email',
                        'address',
                        'status',
                        'created_by',
                        'updated_by',
                        'action',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_supplier_crud_flow_sets_created_by_and_updated_by(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('supplier.create'))
            ->assertOk();

        // create — created_by & updated_by diisi oleh user yang login
        $this->actingAs($user)
            ->post(route('supplier.store'), [
                'name' => 'PT Mitra Niaga',
                'phone' => '021-6889900',
                'email' => 'admin@mitraniaga.co.id',
                'address' => 'Jl. Gatot Subroto No. 100, Jakarta Pusat',
                'description' => 'Supplier bahan baku umum',
                'status' => '1',
            ])
            ->assertRedirect(route('supplier.index'));

        $this->assertDatabaseHas('suppliers', [
            'name' => 'PT Mitra Niaga',
            'email' => 'admin@mitraniaga.co.id',
            'status' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $supplier = Supplier::where('name', 'PT Mitra Niaga')->first();

        // edit page menampilkan data lama
        $this->actingAs($user)
            ->get(route('supplier.edit', $supplier->id))
            ->assertOk()
            ->assertSee('PT Mitra Niaga');

        // update — updated_by terisi user yang login
        $this->actingAs($user)
            ->put(route('supplier.update', $supplier->id), [
                'name' => 'PT Mitra Niaga Nusantara',
                'phone' => '021-6889901',
                'email' => 'admin@mitraniaga.co.id',
                'address' => 'Jl. Gatot Subroto No. 100, Jakarta Pusat',
                'description' => 'Supplier bahan baku umum & kemasan',
                'status' => '0',
            ])
            ->assertRedirect(route('supplier.index'));

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'PT Mitra Niaga Nusantara',
            'status' => 0,
            'updated_by' => $user->id,
        ]);

        // delete
        $this->actingAs($user)
            ->delete(route('supplier.destroy', $supplier->id))
            ->assertRedirect(route('supplier.index'));

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }
}