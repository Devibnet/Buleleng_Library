<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail perubahan akun & hak akses petugas (super_admin, admin, operator).
     * Nama/peran pelaku dan target disimpan sebagai snapshot agar log tetap terbaca
     * walaupun akun terkait kemudian diubah atau dihapus.
     */
    public function up(): void
    {
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Pelaku
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('actor_username')->nullable();
            $table->string('actor_role', 30)->nullable();

            // Jenis aktivitas, mis. account_created, role_changed, account_deleted
            $table->string('action', 50)->index();

            // Akun yang terdampak
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->string('target_name')->nullable();
            $table->string('target_username')->nullable();
            $table->string('target_role', 30)->nullable();

            // Detail perubahan: { field: { from, to } }
            $table->json('changes')->nullable();
            $table->text('description')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
