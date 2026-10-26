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
        Schema::table('member_carts', function (Blueprint $table) {
            if (!Schema::hasColumn('member_carts', 'loan_id')) {
                $table->foreignId('loan_id')->nullable()->after('member_id')->constrained('loans')->nullOnDelete();
            }
            if (!Schema::hasColumn('member_carts', 'collection_id')) {
                $table->foreignId('collection_id')->nullable()->after('loan_id')->constrained('collections')->nullOnDelete();
            }
            if (!Schema::hasColumn('member_carts', 'item_title')) {
                $table->string('item_title')->nullable()->after('problem_type');
            }
            if (!Schema::hasColumn('member_carts', 'item_call_number')) {
                $table->string('item_call_number')->nullable()->after('item_title');
            }
            if (!Schema::hasColumn('member_carts', 'item_barcode')) {
                $table->string('item_barcode')->nullable()->after('item_call_number');
            }
            if (!Schema::hasColumn('member_carts', 'fine_amount')) {
                $table->decimal('fine_amount', 12, 2)->default(0)->after('description');
            }
            if (!Schema::hasColumn('member_carts', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('fine_amount');
            }
            if (!Schema::hasColumn('member_carts', 'handled_by')) {
                $table->string('handled_by')->nullable()->after('paid_amount');
            }
            if (!Schema::hasColumn('member_carts', 'action_status')) {
                $table->string('action_status')->default('AKSES_DIBEKUKAN')->after('handled_by');
            }
            if (!Schema::hasColumn('member_carts', 'action_notes')) {
                $table->text('action_notes')->nullable()->after('action_status');
            }
            if (!Schema::hasColumn('member_carts', 'reported_date')) {
                $table->date('reported_date')->nullable()->after('action_notes');
            }
            if (!Schema::hasColumn('member_carts', 'late_duration_text')) {
                $table->string('late_duration_text')->nullable()->after('reported_date');
            }
            if (!Schema::hasColumn('member_carts', 'followup_history')) {
                $table->json('followup_history')->nullable()->after('late_duration_text');
            }
            if (!Schema::hasColumn('member_carts', 'resolution_notes')) {
                $table->text('resolution_notes')->nullable()->after('is_resolved');
            }
            if (!Schema::hasColumn('member_carts', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('resolution_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep columns safe on rollback
    }
};
