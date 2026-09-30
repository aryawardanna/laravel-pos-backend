<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kolom ini dipakai dropdown header pojok kanan untuk menampilkan
     * "Logged in ... ago" berisi waktu login terakhir user. Diisi pada
     * setiap login yang benar-benar memakai password (lihat
     * AppServiceProvider::boot), bukan saat sesi dipulihkan dari cookie
     * Remember Me.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'last_login_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'last_login_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
