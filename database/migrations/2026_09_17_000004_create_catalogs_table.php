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
        Schema::create('catalogs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('material_type')->nullable();
            $table->string('media_type')->nullable();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('statement_of_responsibility')->nullable();
            $table->string('author_main')->nullable();
            $table->string('author_corporate')->nullable();
            $table->string('author_additional_person')->nullable();
            $table->string('author_additional_corporate')->nullable();
            $table->string('edition')->nullable();
            $table->string('publish_city')->nullable();
            $table->string('publisher')->nullable();
            $table->string('publish_year')->nullable();
            $table->string('pages')->nullable();
            $table->string('dimension')->nullable();
            $table->string('isbn')->nullable();
            $table->string('issn')->nullable();
            $table->string('ismn')->nullable();
            $table->string('ddc_number')->nullable();
            $table->string('catalog_call_number')->nullable();
            $table->string('subject_topic')->nullable();
            $table->string('language')->nullable();
            $table->text('abstract')->nullable();
            $table->string('serial_edition')->nullable();
            $table->date('serial_publish_date')->nullable();
            $table->string('serial_accompanying_material')->nullable();
            $table->text('serial_notes')->nullable();
            $table->boolean('is_quarantine')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogs');
    }
};
