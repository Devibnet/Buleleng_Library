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
            $table->string('activation_status', 30)->default('AKTIF')->after('password_changed_at');
            $table->string('activation_token_hash', 64)->nullable()->index()->after('activation_status');
            $table->timestamp('activation_sent_at')->nullable()->after('activation_token_hash');
            $table->timestamp('activation_expires_at')->nullable()->after('activation_sent_at');
            $table->timestamp('activation_verified_at')->nullable()->after('activation_expires_at');
            $table->text('activation_failed_reason')->nullable()->after('activation_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['activation_token_hash']);
            $table->dropColumn([
                'activation_status',
                'activation_token_hash',
                'activation_sent_at',
                'activation_expires_at',
                'activation_verified_at',
                'activation_failed_reason',
            ]);
        });
    }
};
