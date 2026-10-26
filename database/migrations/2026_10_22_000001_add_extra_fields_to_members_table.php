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
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'gender')) {
                $table->string('gender', 10)->default('L')->nullable()->after('name');
            }
            if (!Schema::hasColumn('members', 'nik')) {
                $table->string('nik', 30)->nullable()->after('member_no');
            }
            if (!Schema::hasColumn('members', 'status')) {
                $table->string('status', 50)->default('Aktif Bebas Pustaka')->nullable()->after('photo_path');
            }
            if (!Schema::hasColumn('members', 'max_loans')) {
                $table->integer('max_loans')->default(3)->nullable()->after('institution_name');
            }
            if (!Schema::hasColumn('members', 'valid_until')) {
                $table->date('valid_until')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['gender', 'nik', 'status', 'max_loans', 'valid_until']);
        });
    }
};
