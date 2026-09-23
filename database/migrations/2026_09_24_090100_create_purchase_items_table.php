<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Setiap baris purchase_items adalah satu BATCH / LOT bahan baku.
     * Jadi satu bahan baku bisa punya banyak batch dari pembelian yang berbeda
     * (harga beli, tanggal penerimaan, dan tanggal kedaluwarsa masing-masing).
     */
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('bahan_baku_id');
            $table->string('batch_code')->nullable();            // nomor batch/lot, mis. PB-20260924-0001-1
            $table->decimal('quantity', 14, 3)->default(0);       // jumlah diterima (dalam satuan bahan baku)
            $table->decimal('remaining_qty', 14, 3)->default(0);  // sisa stok batch ini
            $table->decimal('unit_price', 14, 2)->default(0);     // harga beli per satuan
            $table->decimal('subtotal', 14, 2)->default(0);       // quantity * unit_price
            $table->date('expired_date')->nullable();             // tanggal kedaluwarsa batch
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('cascade');
            $table->foreign('bahan_baku_id')->references('id')->on('bahan_bakus')->onDelete('cascade');

            $table->index(['bahan_baku_id', 'expired_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
