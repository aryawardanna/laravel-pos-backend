<?php

namespace Database\Seeders;

use App\Models\BahanBaku;
use App\Models\Satuan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BahanBakuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $satuans = [];
        foreach (Satuan::where('status', '!=', -1)->get() as $satuan) {
            $satuans[$satuan->code] = $satuan->id;
        }

        $bahanBakus = [
            [
                'name' => 'Gula Pasir',
                'code' => 'GULA',
                'satuan_id' => $satuans['Kg'] ?? null,
                'price' => 15000,
                'stock' => 250,
                'min_stock' => 25,
                'description' => 'Gula pasir putih untuk pembuatan minuman dan dessert',
                'status' => 1,
            ],
            [
                'name' => 'Kopi Biji Arabica',
                'code' => 'KOPI',
                'satuan_id' => $satuans['Kg'] ?? null,
                'price' => 120000,
                'stock' => 80,
                'min_stock' => 10,
                'description' => 'Kopi biji arabica 100% untuk espresso',
                'status' => 1,
            ],
            [
                'name' => 'Susu UHT',
                'code' => 'SUSU',
                'satuan_id' => $satuans['Ltr'] ?? null,
                'price' => 18000,
                'stock' => 30,
                'min_stock' => 10,
                'description' => 'Susu UHT untuk cappuccino dan latte',
                'status' => 1,
            ],
            [
                'name' => 'Cup Kertas 12oz',
                'code' => 'CUP12',
                'satuan_id' => $satuans['Pcs'] ?? null,
                'price' => 900,
                'stock' => 5000,
                'min_stock' => 500,
                'description' => 'Cup kertas 12oz untuk cold drink',
                'status' => 1,
            ],
        ];

        foreach ($bahanBakus as $bahanBaku) {
            $existing = BahanBaku::where('code', $bahanBaku['code'])->first();
            if ($existing) {
                continue;
            }

            BahanBaku::create($bahanBaku);
        }
    }
}