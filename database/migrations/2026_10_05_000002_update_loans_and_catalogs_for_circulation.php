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
        // 1. Update tabel catalogs untuk kolom total_copies & available_copies jika belum ada
        Schema::table('catalogs', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogs', 'total_copies')) {
                $table->integer('total_copies')->default(0)->after('is_quarantine');
            }
            if (! Schema::hasColumn('catalogs', 'available_copies')) {
                $table->integer('available_copies')->default(0)->after('total_copies');
            }
        });

        // Sync data awal total_copies & available_copies dari tabel collections yang sudah ada
        $catalogs = DB::table('catalogs')->get();
        foreach ($catalogs as $cat) {
            $total = DB::table('collections')->where('catalog_id', $cat->id)->count();
            $available = DB::table('collections')
                ->where('catalog_id', $cat->id)
                ->where('availability_status', 'AVAILABLE')
                ->where('is_quarantine', false)
                ->count();

            DB::table('catalogs')->where('id', $cat->id)->update([
                'total_copies' => $total,
                'available_copies' => $available,
            ]);
        }

        // 2. Update tabel loans
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'reservation_code')) {
                $table->string('reservation_code')->nullable()->unique()->after('loan_number');
            }
            if (! Schema::hasColumn('loans', 'pickup_deadline')) {
                $table->dateTime('pickup_deadline')->nullable()->after('reservation_code');
            }
            if (! Schema::hasColumn('loans', 'borrow_date')) {
                $table->dateTime('borrow_date')->nullable()->after('pickup_deadline');
            }

            $table->date('loan_date')->nullable()->change();
            $table->date('due_date')->nullable()->change();
            $table->foreignId('created_by_user_id')->nullable()->change();
        });

        // Update ENUM status kolom pada loans agar mendukung status baru: RESERVED, BORROWED, RETURNED, OVERDUE, CANCELLED, EXPIRED, LOST
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('RESERVED', 'BORROWED', 'RETURNED', 'OVERDUE', 'CANCELLED', 'EXPIRED', 'LOST') NOT NULL DEFAULT 'BORROWED'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'reservation_code')) {
                $table->dropColumn('reservation_code');
            }
            if (Schema::hasColumn('loans', 'pickup_deadline')) {
                $table->dropColumn('pickup_deadline');
            }
            if (Schema::hasColumn('loans', 'borrow_date')) {
                $table->dropColumn('borrow_date');
            }
        });

        Schema::table('catalogs', function (Blueprint $table) {
            if (Schema::hasColumn('catalogs', 'total_copies')) {
                $table->dropColumn('total_copies');
            }
            if (Schema::hasColumn('catalogs', 'available_copies')) {
                $table->dropColumn('available_copies');
            }
        });
    }
};
