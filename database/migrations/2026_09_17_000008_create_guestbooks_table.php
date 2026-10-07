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
        Schema::create('guestbooks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('visit_type', ['INDIVIDUAL', 'GROUP'])->default('INDIVIDUAL');
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('visitor_name');
            $table->string('phone_number')->nullable();
            $table->string('institution_name')->nullable();
            $table->text('institution_address')->nullable();
            $table->string('institution_email')->nullable();
            $table->text('address')->nullable();
            $table->integer('total_personnel')->default(1);
            $table->integer('gender_male_count')->default(0);
            $table->integer('gender_female_count')->default(0);
            $table->json('job_breakdown')->nullable();
            $table->json('education_breakdown')->nullable();
            $table->dateTime('visit_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guestbooks');
    }
};
