<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'PT Sumber Jaya Makmur',
                'phone' => '021-5551234',
                'email' => 'cs@sumberjaya.co.id',
                'address' => 'Jl. Raya Kebayoran No. 12, Jakarta Selatan',
                'description' => 'Supplier bahan baku makanan dan minuman',
                'status' => 1,
            ],
            [
                'name' => 'CV Berkah Abadi',
                'phone' => '031-7778889',
                'email' => 'sales@berkahabadi.co.id',
                'address' => 'Jl. Ahmad Yani No. 45, Surabaya',
                'description' => 'Supplier peralatan dapur dan kemasan',
                'status' => 1,
            ],
            [
                'name' => 'UD Segar Sejahtera',
                'phone' => '022-4445566',
                'email' => 'info@segarsejahtera.co.id',
                'address' => 'Jl. Dago No. 8, Bandung',
                'description' => 'Supplier sayur dan buah segar',
                'status' => 1,
            ],
            [
                'name' => 'PT Mitra Niaga Nusantara',
                'phone' => '021-6889900',
                'email' => 'admin@mitraniaga.co.id',
                'address' => 'Jl. Gatot Subroto No. 100, Jakarta Pusat',
                'description' => 'Supplier bahan baku umum',
                'status' => 0,
            ],
        ];

        foreach ($suppliers as $supplier) {
            $existing = Supplier::where('name', $supplier['name'])->first();
            if ($existing) {
                continue;
            }

            Supplier::create($supplier);
        }
    }
}