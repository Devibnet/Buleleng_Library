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
        Schema::create('collections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('catalog_id')->constrained('catalogs')->cascadeOnDelete();
            $table->string('item_number')->nullable();
            $table->string('barcode_no')->unique();
            $table->string('rfid_no')->nullable();
            $table->string('exemplar_call_number')->nullable();
            $table->date('procurement_date')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_name')->nullable();
            $table->string('currency')->default('IDR')->nullable();
            $table->decimal('price', 12, 2)->default(0)->nullable();
            $table->string('library_location_code')->nullable();
            $table->string('room_location_code')->nullable();
            $table->string('access_type')->nullable();
            $table->string('category')->nullable();
            $table->string('availability_status')->default('AVAILABLE');
            $table->boolean('is_quarantine')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
