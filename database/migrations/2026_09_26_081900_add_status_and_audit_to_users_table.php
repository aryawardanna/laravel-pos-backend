<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Catatan: kolom status / created_by / updated_by dipakai di model,
     * controller, dan form, tapi belum pernah punya migrasi di repo ini
     * (di sebagian database kolomnya dibuat manual). Karena itu setiap
     * kolom dicek dulu, agar migrasi ini aman dijalankan di database
     * yang sudah punya kolom tersebut maupun di database baru.
     */
    public function up(): void
    {
        $added = [];

        Schema::table('users', function (Blueprint $table) use (&$added) {
            if (! Schema::hasColumn('users', 'status')) {
                // 1 = active, 0 = nonactive, -1 = deleted
                $table->integer('status')->default(1)->after('role');
                $added[] = 'status';
            }

            if (! Schema::hasColumn('users', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('status');
                $added[] = 'created_by';
            }

            if (! Schema::hasColumn('users', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                $added[] = 'updated_by';
            }
        });

        // Foreign key hanya ditambahkan untuk kolom yang baru dibuat di migrasi
        // ini, supaya database yang sudah punya kolomnya tidak ikut berubah.
        foreach (['created_by', 'updated_by'] as $column) {
            if (! in_array($column, $added, true)) {
                continue;
            }

            Schema::table('users', function (Blueprint $table) use ($column) {
                $table->foreign($column)->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['updated_by', 'created_by'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                continue;
            }

            try {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropForeign([$column]);
                });
            } catch (\Throwable $e) {
                // Driver tidak mendukung drop FK, abaikan
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['status', 'created_by', 'updated_by'],
                fn (string $column) => Schema::hasColumn('users', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
