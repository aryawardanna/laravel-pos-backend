<?php

use App\Models\BahanBaku;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menyamakan total sisa batch dengan stok bahan baku untuk data yang sudah ada
     * (stok awal sebelum fitur batch, penyesuaian stock opname, dan koreksi stok manual).
     */
    public function up(): void
    {
        foreach (BahanBaku::all() as $bahanBaku) {
            SyncBahanBakuBatches($bahanBaku);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
