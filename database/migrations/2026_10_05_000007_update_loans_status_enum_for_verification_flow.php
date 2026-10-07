<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('PENDING_CHECK', 'READY_FOR_PICKUP', 'UNAVAILABLE', 'RESERVED', 'BORROWED', 'RETURNED', 'OVERDUE', 'CANCELLED', 'EXPIRED', 'LOST') NOT NULL DEFAULT 'PENDING_CHECK'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('RESERVED', 'BORROWED', 'RETURNED', 'OVERDUE', 'CANCELLED', 'EXPIRED', 'LOST') NOT NULL DEFAULT 'BORROWED'");
    }
};
