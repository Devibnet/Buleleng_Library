<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE guestbooks MODIFY COLUMN visit_type ENUM('MEMBER', 'NON_MEMBER', 'GROUP', 'INDIVIDUAL') NOT NULL DEFAULT 'NON_MEMBER'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE guestbooks MODIFY COLUMN visit_type ENUM('INDIVIDUAL', 'GROUP') NOT NULL DEFAULT 'INDIVIDUAL'");
    }
};
