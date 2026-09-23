<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Penjualan menu kasir. Setiap penjualan mengurangi stok bahan baku
     * sesuai resep menu (menu_bahan_bakus) memakai FEFO per batch.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();                  // nomor penjualan, mis. TRX-20260924-0001
            $table->date('sale_date');                            // tanggal penjualan
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid', 14, 2)->default(0);           // uang dibayar
            $table->decimal('change_amount', 14, 2)->default(0);  // kembalian
            $table->string('payment_method', 30)->default('cash');
            $table->tinyInteger('status')->default(1);            // 1 = selesai, -1 = dibatalkan
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'sale_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
