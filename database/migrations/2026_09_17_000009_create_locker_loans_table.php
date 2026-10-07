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
        Schema::create('locker_loans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('loan_number')->unique();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('locker_id')->constrained('lockers')->cascadeOnDelete();
            $table->string('deposit_type');
            $table->dateTime('borrow_date');
            $table->dateTime('return_date')->nullable();
            $table->text('violation_note')->nullable();
            $table->enum('status', ['ACTIVE', 'RETURNED', 'OVERDUE', 'VIOLATION'])->default('ACTIVE');
            $table->foreignId('processed_by_user_id')->constrained('admins')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locker_loans');
    }
};
