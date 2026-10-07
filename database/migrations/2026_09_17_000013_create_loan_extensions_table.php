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
        Schema::create('loan_extensions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->date('previous_due_date');
            $table->date('new_due_date');
            $table->date('extension_date');
            $table->foreignId('processed_by_user_id')->constrained('admins')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_extensions');
    }
};
