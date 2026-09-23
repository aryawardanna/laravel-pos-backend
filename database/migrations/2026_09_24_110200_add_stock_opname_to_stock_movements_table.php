<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menghubungkan kartu stok dengan stock opname sehingga penyesuaian
     * (dan pembatalannya) tetap terlacak di kartu stok.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_opname_id')->nullable()->after('purchase_item_id');
            $table->unsignedBigInteger('stock_opname_item_id')->nullable()->after('stock_opname_id');

            $table->foreign('stock_opname_id')->references('id')->on('stock_opnames')->onDelete('set null');
            $table->foreign('stock_opname_item_id')->references('id')->on('stock_opname_items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['stock_opname_id']);
            $table->dropForeign(['stock_opname_item_id']);
            $table->dropColumn(['stock_opname_id', 'stock_opname_item_id']);
        });
    }
};
