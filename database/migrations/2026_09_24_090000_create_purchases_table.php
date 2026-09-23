<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();                 // nomor pembelian, mis. PB-20260924-0001
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->date('purchase_date');                      // tanggal pembelian / penerimaan
            $table->decimal('total', 14, 2)->default(0);
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(0);           // 0 = draft, 1 = diterima, -1 = dibatalkan
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'purchase_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
