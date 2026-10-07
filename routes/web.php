<?php

use App\Http\Controllers\Admin\CirculationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\MemberRegisterController;
use App\Http\Controllers\Member\LoanReservationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Google OAuth for Member Guard
Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// Member Registration Completion
Route::get('/member/register/complete', [MemberRegisterController::class, 'showRegistrationForm'])->name('member.register.complete');
Route::post('/member/register/complete', [MemberRegisterController::class, 'completeRegistration'])->name('member.register.complete.post');

// Member Protected Area
Route::middleware('auth:member')->group(function () {
    Route::get('/member/dashboard', function () {
        return view('member.dashboard', [
            'member' => Auth::guard('member')->user(),
        ]);
    })->name('member.dashboard');

    // Member Online Reservation Routes
    Route::get('/member/reservation', [LoanReservationController::class, 'index'])->name('member.reservation');
    Route::post('/member/reservation', [LoanReservationController::class, 'store'])->name('member.reservation.store');

    Route::post('/member/logout', function () {
        Auth::guard('member')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/')->with('info', 'Anda telah berhasil keluar.');
    })->name('member.logout');
});

// Route Simulasi Testing Google OAuth (Khusus environment local)
if (app()->environment('local')) {
    Route::get('/test-google-flow', function () {
        Auth::guard('member')->logout();

        session([
            'google_user' => [
                'google_id'  => 'google_dummy_' . time(),
                'name'       => 'Angga Prabaswara',
                'email'      => 'angga.' . time() . '@example.com',
                'photo_path' => 'https://ui-avatars.com/api/?name=Angga+Prabaswara&background=2563eb&color=fff',
            ],
        ]);

        return redirect()->route('member.register.complete');
    })->name('test.google.flow');

    // Quick Auto Login Member Test Route
    Route::get('/test-login-member', function () {
        $member = \App\Models\Member::firstOrCreate(
            ['email' => 'angga.member@buleleng.go.id'],
            [
                'member_no'           => \App\Models\Member::generateMemberNo(),
                'name'                => 'Angga Prabaswara',
                'password'            => \Illuminate\Support\Facades\Hash::make('password123'),
                'ktp_address'         => 'Jl. Ngurah Rai No. 10 Singaraja',
                'residential_address' => 'Jl. Ngurah Rai No. 10 Singaraja',
                'place_of_birth'      => 'Singaraja',
                'date_of_birth'       => '1995-08-17',
                'job_category'        => 'MAHASISWA',
                'institution_name'    => 'Undiksha',
                'phone_number'        => '081234567890',
            ]
        );
        Auth::guard('member')->login($member);
        return redirect()->route('member.reservation')->with('success', "Login otomatis sebagai Member: {$member->name}");
    })->name('test.login.member');

    // Quick Auto Login Admin Operator Test Route
    Route::get('/test-login-admin/{role?}', function ($role = 'operator') {
        $admin = \App\Models\Admin::where('role', $role)->first();
        if (! $admin) {
            return "Akun admin role '{$role}' belum ada di database. Silakan jalankan 'php artisan db:seed'.";
        }
        Auth::guard('admin')->login($admin);
        return redirect()->route('admin.circulation.loans.index')->with('success', "Login otomatis sebagai Admin ({$admin->name} - Role: {$admin->role})");
    })->name('test.login.admin');
}

// Route Admin Login (Target Redirect / Fallback)
Route::get('/admin/login', function () {
    return 'Halaman Login Admin';
})->name('admin.login');

// Admin Protected Area with RBAC Groups
Route::prefix('admin')->name('admin.')->group(function () {

    // Group 'super_admin': Kelola data akun staf admin
    Route::middleware(['auth:admin', 'admin.role:super_admin'])->prefix('super-admin')->name('super_admin.')->group(function () {
        Route::get('/staff', function () {
            return 'Halaman Kelola Data Akun Staf Admin';
        })->name('staff.index');
    });

    // Group 'admin': Kelola master data (katalog, koleksi, member, lokasi)
    Route::middleware(['auth:admin', 'admin.role:admin,super_admin'])->prefix('master')->name('master.')->group(function () {
        Route::get('/catalogs', function () {
            return 'Halaman Kelola Master Data Katalog';
        })->name('catalogs.index');

        Route::get('/collections', function () {
            return 'Halaman Kelola Master Data Koleksi';
        })->name('collections.index');

        Route::get('/members', function () {
            return 'Halaman Kelola Master Data Member';
        })->name('members.index');

        Route::get('/locations', function () {
            return 'Halaman Kelola Master Data Lokasi';
        })->name('locations.index');
    });

    // Group 'operator': Transaksi sirkulasi (peminjaman, pengembalian, denda)
    Route::middleware(['auth:admin', 'admin.role:operator,admin,super_admin'])->prefix('circulation')->name('circulation.')->group(function () {
        Route::get('/loans', [CirculationController::class, 'index'])->name('loans.index');
        Route::post('/confirm-reservation', [CirculationController::class, 'confirmReservation'])->name('confirm_reservation');
        Route::post('/direct-loan', [CirculationController::class, 'storeDirect'])->name('direct_loan');
        Route::post('/returns/{loan}', [CirculationController::class, 'returnLoan'])->name('return_loan');
    });

    // Group 'reports': Modul Laporan Eksekutif (Report Engine)
    Route::middleware(['auth:admin', 'admin.role:operator,admin,super_admin'])->prefix('reports')->name('reports.')->group(function () {
        Route::get('/circulation', [ReportController::class, 'circulation'])->name('circulation');
        Route::get('/members', [ReportController::class, 'members'])->name('members');
        Route::get('/catalogs', [ReportController::class, 'catalogs'])->name('catalogs');
        Route::get('/onsite-readings', [ReportController::class, 'onsiteReadings'])->name('onsite_readings');
        Route::get('/guestbooks', [ReportController::class, 'guestbooks'])->name('guestbooks');
    });

    // Group 'settings': Modul Pengaturan Umum Sistem (System Settings)
    Route::middleware(['auth:admin', 'admin.role:super_admin,admin'])->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SystemSettingController::class, 'index'])->name('index');
        Route::put('/', [SystemSettingController::class, 'update'])->name('update');
        Route::post('/', [SystemSettingController::class, 'update'])->name('update.post');
    });
});
