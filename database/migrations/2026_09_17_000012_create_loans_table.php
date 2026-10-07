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
        Schema::create('loans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('loan_number')->unique();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->date('loan_date');
            $table->date('due_date');
            $table->date('return_date')->nullable();
            $table->integer('late_days')->default(0);
            $table->integer('extension_count')->default(0);
            $table->enum('status', ['BORROWED', 'RETURNED', 'OVERDUE', 'LOST'])->default('BORROWED');
            $table->decimal('fine_amount', 12, 2)->default(0);
            $table->foreignId('created_by_user_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('returned_by_user_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
