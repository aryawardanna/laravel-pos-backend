<?php

namespace Database\Seeders;

use App\Models\Satuan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SatuanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $satuans = [
            ['name' => 'Pieces', 'code' => 'Pcs', 'description' => 'Satuan per buah / item', 'status' => 1],
            ['name' => 'Kilogram', 'code' => 'Kg', 'description' => 'Satuan berat 1000 gram', 'status' => 1],
            ['name' => 'Liter', 'code' => 'Ltr', 'description' => 'Satuan volume cairan', 'status' => 1],
            ['name' => 'Box', 'code' => 'Box', 'description' => 'Satuan kemasan', 'status' => 1],
            ['name' => 'Pack', 'code' => 'Pack', 'description' => 'Satuan kemasan / bungkus', 'status' => 1],
        ];

        foreach ($satuans as $satuan) {
            Satuan::create($satuan);
        }
    }
}