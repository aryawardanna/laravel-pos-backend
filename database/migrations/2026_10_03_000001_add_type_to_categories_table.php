<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menandai jenis kategori: makanan / minuman / lainnya.
     *
     * Dipakai untuk memisahkan cetak struk & bon dapur/bar. Default "lainnya"
     * supaya kategori lama yang belum ditandai tidak salah dianggap makanan.
     * Kolom dicek dulu agar migrasi aman di database yang sudah punya kolomnya.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'type')) {
                $table->string('type', 20)->default('lainnya')->after('image');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
