<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * purchase_items dipakai sebagai tabel batch/lot, jadi selain batch dari pembelian
     * diperlukan lot penyesuaian (stok awal, stock opname, koreksi stok manual)
     * yang tidak terikat ke pembelian.
     */
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_id')->nullable()->change();
            $table->string('source', 20)->default('purchase')->after('purchase_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('source');
            $table->unsignedBigInteger('purchase_id')->nullable(false)->change();
        });
    }
};
