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
            if (!Schema::hasColumn('members', 'ktp_photo_path')) {
                $table->string('ktp_photo_path', 255)->nullable()->after('photo_path');
            }
            if (!Schema::hasColumn('members', 'ktp_verified_at')) {
                $table->timestamp('ktp_verified_at')->nullable()->after('valid_until');
            }
            if (!Schema::hasColumn('members', 'ktp_verified_by')) {
                $table->unsignedBigInteger('ktp_verified_by')->nullable()->after('ktp_verified_at');
            }
            if (!Schema::hasColumn('members', 'verification_rejection_reason')) {
                $table->text('verification_rejection_reason')->nullable()->after('ktp_verified_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'ktp_photo_path',
                'ktp_verified_at',
                'ktp_verified_by',
                'verification_rejection_reason',
            ]);
        });
    }
};

