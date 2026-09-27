<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kolom users.role semula enum('admin','staff','user'), sehingga role baru
     * tidak bisa ditambahkan. diubah menjadi varchar supaya role bisa dibuat
     * dan diatur dari halaman "Roles".
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $extra = DB::table('users')
            ->whereNotNull('role')
            ->distinct()
            ->pluck('role')
            ->diff(['admin', 'staff', 'user'])
            ->values();

        // Ada role dinamis yang tidak muat di enum lama: biarkan varchar.
        if ($extra->isNotEmpty()) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff', 'user'])->default('user')->change();
        });
    }
};
