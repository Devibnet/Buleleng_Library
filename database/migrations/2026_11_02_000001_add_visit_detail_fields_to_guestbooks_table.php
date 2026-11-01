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
        Schema::table('guestbooks', function (Blueprint $table) {
            $table->string('job', 50)->nullable()->after('address');
            $table->string('education', 10)->nullable()->after('job');
            $table->string('visit_purpose')->nullable()->after('education');
            $table->string('institution_phone', 25)->nullable()->after('institution_email');
            $table->index('visit_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guestbooks', function (Blueprint $table) {
            $table->dropIndex(['visit_date']);
            $table->dropColumn(['job', 'education', 'visit_purpose', 'institution_phone']);
        });
    }
};
