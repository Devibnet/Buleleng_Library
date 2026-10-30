<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index untuk pencarian katalog saat import Excel (deduplikasi berdasarkan ISBN / judul)
     * dan pencarian OPAC, agar tidak memindai seluruh tabel untuk setiap baris.
     */
    public function up(): void
    {
        Schema::table('catalogs', function (Blueprint $table) {
            $table->index('title', 'catalogs_title_index');
            $table->index('isbn', 'catalogs_isbn_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalogs', function (Blueprint $table) {
            $table->dropIndex('catalogs_title_index');
            $table->dropIndex('catalogs_isbn_index');
        });
    }
};
