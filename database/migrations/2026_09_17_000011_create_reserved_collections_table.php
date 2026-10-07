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
        Schema::create('reserved_collections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('reservation_date');
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->enum('status', ['PENDING', 'APPROVED', 'CANCELLED', 'COMPLETED', 'EXPIRED'])->default('PENDING');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reserved_collections');
    }
};
