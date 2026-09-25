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
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'status')) {
                $table->tinyInteger('status')->default(1)->after('image');
            }
            if (! Schema::hasColumn('categories', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('status');
            }
            if (! Schema::hasColumn('categories', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
        });

        // Tambah FK terpisah agar aman jika kolom sudah ada sebelumnya
        Schema::table('categories', function (Blueprint $table) {
            try {
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            } catch (\Throwable $e) {
                // FK mungkin sudah ada, abaikan
            }
            try {
                $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            } catch (\Throwable $e) {
                // FK mungkin sudah ada, abaikan
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            try {
                $table->dropForeign(['created_by']);
            } catch (\Throwable $e) {
            }
            try {
                $table->dropForeign(['updated_by']);
            } catch (\Throwable $e) {
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'created_by')) {
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('categories', 'updated_by')) {
                $table->dropColumn('updated_by');
            }
            if (Schema::hasColumn('categories', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
