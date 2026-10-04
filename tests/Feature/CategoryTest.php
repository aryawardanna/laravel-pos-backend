<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kategori menu: penanda jenis (makanan/minuman/lainnya) dipakai untuk
 * memisahkan cetak struk & bon dapur/bar.
 */
class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_type_is_saved_and_shown_in_datatable(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->post(route('category.store'), [
            'name' => 'Minuman',
            'description' => 'Kategori minuman',
            'type' => 'minuman',
            'status' => '1',
        ])->assertRedirect(route('category.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Minuman',
            'type' => 'minuman',
        ]);

        $content = $this->actingAs($user)->get(route('category.data'))->assertOk()->getContent();

        $this->assertStringContainsString('Minuman', $content);
        $this->assertStringContainsString('badge-info', $content);   // warna badge jenis minuman
    }

    public function test_category_type_defaults_to_lainnya_when_omitted(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->post(route('category.store'), [
            'name' => 'Lain-lain',
            'status' => '1',
        ])->assertRedirect(route('category.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Lain-lain',
            'type' => 'lainnya',
        ]);
    }

    public function test_category_type_can_be_updated(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $category = Category::create([
            'name' => 'Kopi',
            'type' => Category::TYPE_MINUMAN,
            'status' => 1,
        ]);

        $this->actingAs($user)->put(route('category.update', $category->id), [
            'name' => 'Kopi',
            'type' => 'makanan',
            'status' => '1',
        ])->assertRedirect(route('category.index'));

        $this->assertSame('makanan', $category->refresh()->type);
    }

    public function test_category_type_is_rejected_when_invalid(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->post(route('category.store'), [
            'name' => 'Salah',
            'type' => 'cemilan',
            'status' => '1',
        ])->assertSessionHasErrors('type');

        $this->assertDatabaseMissing('categories', ['name' => 'Salah']);
    }
}
