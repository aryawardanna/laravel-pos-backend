<?php

use App\Models\BahanBaku;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kode bahan baku dijadikan nomor urut unik 5 digit (00001, 00002, ...) supaya
     * kode lama yang diisi manual (mis. "GULA") tidak bercampur dengan kode otomatis,
     * lalu kolom code diberi unique index agar tidak mungkin ada kode kembar.
     */
    public function up(): void
    {
        $sequence = 1;

        // Urut berdasarkan id: bahan baku paling lama mendapat nomor terkecil (00001).
        foreach (BahanBaku::orderBy('id')->get() as $bahanBaku) {
            $bahanBaku->update(['code' => CodeNumber($sequence, 5)]);
            $sequence++;
        }

        Schema::table('bahan_bakus', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bahan_bakus', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
    }
};
