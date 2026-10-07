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
        Schema::create('member_carts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('problem_type');
            $table->text('description')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_carts');
    }
};
