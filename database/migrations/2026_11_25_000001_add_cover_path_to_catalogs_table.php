<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sampul monograf: path relatif pada disk "public" (mis. covers/catalog-12-abc.webp).
     */
    public function up(): void
    {
        Schema::table('catalogs', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogs', 'cover_path')) {
                $table->string('cover_path')->nullable()->after('abstract');
            }
        });
    }

    public function down(): void
    {
        Schema::table('catalogs', function (Blueprint $table) {
            if (Schema::hasColumn('catalogs', 'cover_path')) {
                $table->dropColumn('cover_path');
            }
        });
    }
};
