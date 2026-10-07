<?php

namespace Database\Seeders;

use App\Models\Locker;
use Illuminate\Database\Seeder;

class LockerSeeder extends Seeder
{
    public function run(): void
    {
        $lockers = [
            'LCK-01',
            'LCK-02',
            'LCK-03',
            'LCK-04',
            'LCK-05',
        ];

        foreach ($lockers as $code) {
            Locker::firstOrCreate(
                ['locker_code' => $code],
                ['status' => 'AVAILABLE']
            );
        }
    }
}
