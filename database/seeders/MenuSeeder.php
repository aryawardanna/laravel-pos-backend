<?php

namespace Database\Seeders;

use App\Models\BahanBaku;
use App\Models\Category;
use App\Models\Menu;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::get()->keyBy('name');
        $bahanBakus = BahanBaku::where('status', '!=', -1)->get()->keyBy('code');

        $menus = [
            [
                'name' => 'Kopi Cappuccino',
                'code' => 'CAPPU',
                'category' => 'Minuman',
                'price' => 25000,
                'description' => 'Espresso dengan susu steam berlapis foam.',
                'status' => 1,
                'ingredients' => [
                    ['bahan_baku_code' => 'KOPI', 'quantity' => 0.02],
                    ['bahan_baku_code' => 'SUSU', 'quantity' => 0.25],
                    ['bahan_baku_code' => 'GULA', 'quantity' => 0.03],
                ],
            ],
            [
                'name' => 'Kopi Latte',
                'code' => 'LATTE',
                'category' => 'Minuman',
                'price' => 27000,
                'description' => 'Espresso dengan susu steam ekstra.',
                'status' => 1,
                'ingredients' => [
                    ['bahan_baku_code' => 'KOPI', 'quantity' => 0.03],
                    ['bahan_baku_code' => 'SUSU', 'quantity' => 0.3],
                ],
            ],
            [
                'name' => 'Ice Cappuccino 12oz',
                'code' => 'ICECAP',
                'category' => 'Minuman',
                'price' => 29000,
                'description' => 'Cappuccino dingin dalam cup kertas 12oz.',
                'status' => 1,
                'ingredients' => [
                    ['bahan_baku_code' => 'CUP12', 'quantity' => 1],
                    ['bahan_baku_code' => 'KOPI', 'quantity' => 0.02],
                    ['bahan_baku_code' => 'SUSU', 'quantity' => 0.25],
                ],
            ],
        ];

        foreach ($menus as $item) {
            $existing = Menu::where('code', $item['code'])->first();
            if ($existing) {
                continue;
            }

            $menu = Menu::create([
                'name' => $item['name'],
                'code' => $item['code'],
                'category_id' => $categories[$item['category']]->id ?? null,
                'price' => $item['price'],
                'description' => $item['description'],
                'status' => $item['status'],
            ]);

            $ingredients = [];
            foreach ($item['ingredients'] as $ingredient) {
                $bahanBaku = $bahanBakus[$ingredient['bahan_baku_code']] ?? null;
                if ($bahanBaku) {
                    $ingredients[$bahanBaku->id] = ['quantity' => $ingredient['quantity']];
                }
            }

            $menu->bahanBakus()->sync($ingredients);
        }
    }
}