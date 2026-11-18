<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link reset password petugas yang dibuat oleh Super Admin.
     * Hanya hash token (SHA-256) yang disimpan; token asli hanya ada di link.
     */
    public function up(): void
    {
        Schema::create('admin_password_resets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('admin_id')->index();
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_resets');
    }
};
