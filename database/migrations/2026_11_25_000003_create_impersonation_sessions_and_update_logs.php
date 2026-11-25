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
        // 1. Buat tabel impersonation_sessions
        Schema::create('impersonation_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('session_id', 64)->unique()->index();

            // Identitas Superadmin (Pelaku sebenarnya)
            $table->foreignId('super_admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('super_admin_name');
            $table->string('super_admin_username');

            // Identitas Akun Target (Yang diwakili: Admin atau Operator)
            $table->foreignId('target_admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('target_name');
            $table->string('target_username');
            $table->string('target_role', 30);

            // Alasan impersonasi (Wajib diisi)
            $table->text('reason');

            // ID Personal Access Token Sanctum yang diterbitkan untuk sesi ini
            $table->unsignedBigInteger('token_id')->nullable()->index();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->enum('status', ['ACTIVE', 'ENDED', 'EXPIRED'])->default('ACTIVE')->index();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 50)->nullable(); // manual, logout, timeout

            $table->timestamps();
        });

        // 2. Perluas tabel admin_activity_logs untuk mencatat metadata impersonasi
        Schema::table('admin_activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_activity_logs', 'impersonator_id')) {
                $table->unsignedBigInteger('impersonator_id')->nullable()->index()->after('actor_role');
            }
            if (! Schema::hasColumn('admin_activity_logs', 'impersonator_name')) {
                $table->string('impersonator_name')->nullable()->after('impersonator_id');
            }
            if (! Schema::hasColumn('admin_activity_logs', 'impersonated_session_id')) {
                $table->string('impersonated_session_id', 64)->nullable()->index()->after('impersonator_name');
            }
            if (! Schema::hasColumn('admin_activity_logs', 'impersonation_reason')) {
                $table->text('impersonation_reason')->nullable()->after('impersonated_session_id');
            }
            if (! Schema::hasColumn('admin_activity_logs', 'module')) {
                $table->string('module', 50)->nullable()->index()->after('action');
            }
            if (! Schema::hasColumn('admin_activity_logs', 'result')) {
                $table->string('result', 20)->default('success')->after('user_agent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impersonation_sessions');

        Schema::table('admin_activity_logs', function (Blueprint $table) {
            $cols = ['impersonator_id', 'impersonator_name', 'impersonated_session_id', 'impersonation_reason', 'module', 'result'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('admin_activity_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
