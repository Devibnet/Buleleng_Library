<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admins = [
            [
                'username'  => 'superadmin',
                'name'      => 'Super Admin',
                'email'     => 'superadmin@buleleng.go.id',
                'role'      => 'super_admin',
                'is_active' => true,
                'password'  => Hash::make('password123'),
            ],
            [
                'username'  => 'admin',
                'name'      => 'Admin Utama',
                'email'     => 'admin@buleleng.go.id',
                'role'      => 'admin',
                'is_active' => true,
                'password'  => Hash::make('password123'),
            ],
            [
                'username'  => 'operator',
                'name'      => 'Operator Sirkulasi',
                'email'     => 'operator@buleleng.go.id',
                'role'      => 'operator',
                'is_active' => true,
                'password'  => Hash::make('password123'),
            ],
        ];

        foreach ($admins as $adminData) {
            Admin::updateOrCreate(
                ['username' => $adminData['username']],
                $adminData
            );
        }
    }
}
