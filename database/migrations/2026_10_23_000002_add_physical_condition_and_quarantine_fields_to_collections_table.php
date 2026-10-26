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
        Schema::table('collections', function (Blueprint $table) {
            if (!Schema::hasColumn('collections', 'physical_condition')) {
                $table->string('physical_condition', 50)->default('Baik')->after('availability_status');
            }
            if (!Schema::hasColumn('collections', 'quarantine_reason')) {
                $table->string('quarantine_reason', 255)->nullable()->after('is_quarantine');
            }
            if (!Schema::hasColumn('collections', 'quarantine_notes')) {
                $table->text('quarantine_notes')->nullable()->after('quarantine_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('collections', 'physical_condition')) {
                $columnsToDrop[] = 'physical_condition';
            }
            if (Schema::hasColumn('collections', 'quarantine_reason')) {
                $columnsToDrop[] = 'quarantine_reason';
            }
            if (Schema::hasColumn('collections', 'quarantine_notes')) {
                $columnsToDrop[] = 'quarantine_notes';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
