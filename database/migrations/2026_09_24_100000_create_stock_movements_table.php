<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kartu stok: ledger setiap pergerakan stok bahan baku.
     * Sumber pergerakan saat ini adalah pembelian (masuk saat diterima,
     * keluar saat dibatalkan atau dikoreksi).
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bahan_baku_id');
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->unsignedBigInteger('purchase_item_id')->nullable();
            $table->string('batch_code')->nullable();
            $table->date('movement_date');
            $table->string('type', 20)->default('in');              // in | out | adjustment
            $table->decimal('quantity_in', 14, 3)->default(0);
            $table->decimal('quantity_out', 14, 3)->default(0);
            $table->decimal('balance', 14, 3)->default(0);          // saldo stok setelah pergerakan
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->string('reference')->nullable();                // nomor pembelian / dokumen
            $table->string('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('bahan_baku_id')->references('id')->on('bahan_bakus')->onDelete('cascade');
            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('set null');
            // baris pembelian bisa diganti saat edit, kartu stok tetap tersimpan
            $table->foreign('purchase_item_id')->references('id')->on('purchase_items')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['bahan_baku_id', 'movement_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
