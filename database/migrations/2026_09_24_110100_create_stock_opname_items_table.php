<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Baris hasil hitung fisik per bahan baku:
     * system_stock = stok sistem saat opname, physical_stock = hasil hitung,
     * difference = physical_stock - system_stock (bisa positif / negatif).
     */
    public function up(): void
    {
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_opname_id');
            $table->unsignedBigInteger('bahan_baku_id');
            $table->decimal('system_stock', 14, 3)->default(0);
            $table->decimal('physical_stock', 14, 3)->default(0);
            $table->decimal('difference', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('stock_opname_id')->references('id')->on('stock_opnames')->onDelete('cascade');
            $table->foreign('bahan_baku_id')->references('id')->on('bahan_bakus')->onDelete('cascade');

            $table->unique(['stock_opname_id', 'bahan_baku_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
