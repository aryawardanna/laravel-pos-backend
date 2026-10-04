<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::factory(4)->create();

        // Pastikan kategori utama ada agar contoh menu & pemisahan cetak
        // (makanan -> bon dapur, minuman -> bon bar) bisa langsung dipakai.
        foreach ([
            ['name' => 'Makanan', 'type' => Category::TYPE_MAKANAN],
            ['name' => 'Minuman', 'type' => Category::TYPE_MINUMAN],
        ] as $row) {
            Category::firstOrCreate(
                ['name' => $row['name']],
                ['type' => $row['type'], 'status' => 1]
            );
        }
    }
}
