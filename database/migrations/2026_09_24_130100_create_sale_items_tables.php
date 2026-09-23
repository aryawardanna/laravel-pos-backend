<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Baris item penjualan: satu menu per baris.
     * Stok bahan baku yang terpakai dicatat di kartu stok + sale_item_usages.
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('menu_id');
            $table->decimal('quantity', 14, 3)->default(0);   // jumlah porsi terjual
            $table->decimal('unit_price', 14, 2)->default(0); // harga menu saat terjual (snapshot)
            $table->decimal('subtotal', 14, 2)->default(0);   // quantity * unit_price
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
            $table->foreign('menu_id')->references('id')->on('menus')->onDelete('restrict');

            $table->index('sale_id');
        });

        // Jejak pemakaian bahan baku per item penjualan per batch (FEFO):
        // dari batch mana saja stok diambil untuk tiap bahan baku.
        Schema::create('sale_item_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('sale_item_id');
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('bahan_baku_id');
            $table->unsignedBigInteger('purchase_item_id')->nullable(); // batch yang dipakai
            $table->string('batch_code')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);   // jumlah bahan terpakai dari batch ini
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->onDelete('cascade');
            $table->foreign('menu_id')->references('id')->on('menus')->onDelete('restrict');
            $table->foreign('bahan_baku_id')->references('id')->on('bahan_bakus')->onDelete('restrict');
            $table->foreign('purchase_item_id')->references('id')->on('purchase_items')->onDelete('set null');

            $table->index(['sale_id', 'bahan_baku_id']);
        });

        // Hubungkan kartu stok ke penjualan agar pemakaian tercatat di kartu stok.
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->nullable()->after('stock_opname_item_id');
            $table->unsignedBigInteger('sale_item_id')->nullable()->after('sale_id');

            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropForeign(['sale_item_id']);
            $table->dropColumn(['sale_id', 'sale_item_id']);
        });

        Schema::dropIfExists('sale_item_usages');
        Schema::dropIfExists('sale_items');
    }
};
