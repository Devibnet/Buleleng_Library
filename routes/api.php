<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CirculationController;
use App\Http\Controllers\Api\GuestbookController;
use App\Http\Controllers\Api\LockerController;
use App\Http\Controllers\Api\MemberLoanReservationController;
use App\Http\Controllers\Api\OnsiteReadingController;
use App\Http\Controllers\Api\V1\Admin\BookManagementController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RESTful API Routes - System Backend Perpustakaan Buleleng
|--------------------------------------------------------------------------
*/

// Public Catalog Endpoints (Bisa diakses tanpa login di root /books)
Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{id}', [BookController::class, 'show']);
Route::post('/books/{id}/reserve', [ReservationController::class, 'reserve']);

// Public Guestbook Endpoints (Bisa diakses tanpa login di root /api/guestbooks)
Route::prefix('guestbooks')->group(function () {
    Route::post('/member', [GuestbookController::class, 'storeMember']);
    Route::post('/non-member', [GuestbookController::class, 'storeNonMember']);
    Route::post('/group', [GuestbookController::class, 'storeGroup']);
});
Route::get('/admin/guestbooks/reports', [GuestbookController::class, 'reports'])->middleware(['auth:admin', 'admin.role:operator,admin,super_admin']);

// Public Onsite Reading Endpoints (Bisa diakses tanpa login di root /api/onsite-readings)
Route::prefix('onsite-readings')->group(function () {
    Route::post('/member', [OnsiteReadingController::class, 'storeMember']);
    Route::post('/guest', [OnsiteReadingController::class, 'storeGuest']);
});
Route::get('/admin/onsite-readings/reports', [OnsiteReadingController::class, 'reports'])->middleware(['auth:admin', 'admin.role:operator,admin,super_admin']);

// Admin System Settings Endpoints (Root alias)
Route::middleware(['auth:admin', 'admin.role:super_admin,admin'])->prefix('admin/settings')->group(function () {
    Route::get('/', [SystemSettingController::class, 'index']);
    Route::put('/', [SystemSettingController::class, 'update']);
    Route::post('/', [SystemSettingController::class, 'update']);
});

// Admin Circulation Verification Endpoints (Root alias)
Route::middleware(['auth:admin', 'admin.role:operator,admin,super_admin'])->prefix('admin/circulation/reservations')->group(function () {
    Route::get('/pending', [CirculationController::class, 'pendingReservations']);
    Route::post('/{id}/confirm-ready', [CirculationController::class, 'confirmReady']);
    Route::post('/{id}/mark-unavailable', [CirculationController::class, 'markUnavailable']);
});

Route::prefix('v1')->group(function () {

    // 1. Public Catalog Endpoints (v1)
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);

    // 2. Public Guestbook Endpoints (v1)
    Route::prefix('guestbooks')->group(function () {
        Route::post('/member', [GuestbookController::class, 'storeMember']);
        Route::post('/non-member', [GuestbookController::class, 'storeNonMember']);
        Route::post('/group', [GuestbookController::class, 'storeGroup']);
    });

    // 2b. Public Onsite Reading Endpoints (v1)
    Route::prefix('onsite-readings')->group(function () {
        Route::post('/member', [OnsiteReadingController::class, 'storeMember']);
        Route::post('/guest', [OnsiteReadingController::class, 'storeGuest']);
    });

    // 3. Protected Member Endpoints
    Route::middleware('auth:member')->group(function () {
        Route::post('/books/{id}/reserve', [ReservationController::class, 'reserve']);
    });

    // 4. Auth Endpoints
    Route::post('/auth/member/login', [AuthController::class, 'loginMember']);
    Route::post('/auth/admin/login', [AuthController::class, 'loginAdmin']);

    // 5. Member Protected Area (Guard: member)
    Route::middleware('auth:member')->prefix('member')->group(function () {
        Route::get('/reservations', [MemberLoanReservationController::class, 'index']);
        Route::post('/reservations', [MemberLoanReservationController::class, 'store']);
    });

    // 5b. Profile Management Endpoints (Guard: auth:sanctum)
    Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('/photo', [ProfileController::class, 'updatePhoto']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
    });

    // 6. Admin Protected Area with RBAC Guard & Role Middleware
    Route::prefix('admin')->group(function () {

        // Modul Manajemen Buku (CRUD & Import Excel)
        Route::middleware(['auth:sanctum', 'admin.role:operator,admin,super_admin'])->group(function () {
            Route::get('/books', [BookManagementController::class, 'index']);
            Route::post('/books/import', [BookManagementController::class, 'importExcel']);
            Route::get('/books/{id}', [BookManagementController::class, 'show']);
            Route::post('/books', [BookManagementController::class, 'store']);
            Route::put('/books/{id}', [BookManagementController::class, 'updateCatalog']);
            Route::delete('/books/{id}', [BookManagementController::class, 'destroyCatalog']);
            Route::put('/collections/{collection_id}', [BookManagementController::class, 'updateCollection']);
            Route::delete('/collections/{collection_id}', [BookManagementController::class, 'destroyCollection']);
        });

        // Group 'super_admin': Kelola data akun staf admin
        Route::middleware(['auth:admin', 'admin.role:super_admin'])->prefix('super-admin')->group(function () {
            Route::get('/staff', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Halaman Kelola Data Akun Staf Admin (API)',
                    'data'    => [],
                ]);
            });
        });

        // Group 'admin': Kelola master data (katalog, koleksi, member, lokasi)
        Route::middleware(['auth:admin', 'admin.role:admin,super_admin'])->prefix('master')->group(function () {
            Route::get('/catalogs', function () {
                $catalogs = \App\Models\Catalog::all();
                return response()->json([
                    'success' => true,
                    'message' => 'Data master katalog berhasil diambil.',
                    'data'    => \App\Http\Resources\CatalogResource::collection($catalogs),
                ]);
            });

            Route::get('/collections', function () {
                $collections = \App\Models\Collection::with('catalog')->all();
                return response()->json([
                    'success' => true,
                    'message' => 'Data master koleksi berhasil diambil.',
                    'data'    => \App\Http\Resources\CollectionResource::collection($collections),
                ]);
            });

            Route::get('/members', function () {
                $members = \App\Models\Member::all();
                return response()->json([
                    'success' => true,
                    'message' => 'Data master member berhasil diambil.',
                    'data'    => \App\Http\Resources\MemberResource::collection($members),
                ]);
            });
        });

        // Group 'operator': Transaksi sirkulasi (peminjaman, pengembalian, perpanjangan, denda)
        Route::middleware(['auth:admin', 'admin.role:operator,admin,super_admin'])->prefix('circulation')->group(function () {
            Route::get('/loans', [CirculationController::class, 'index']);
            Route::get('/reservations/search', [CirculationController::class, 'searchReservation']);
            Route::get('/reservations/pending', [CirculationController::class, 'pendingReservations']);
            Route::post('/reservations/{id}/confirm-ready', [CirculationController::class, 'confirmReady']);
            Route::post('/reservations/{id}/mark-unavailable', [CirculationController::class, 'markUnavailable']);
            Route::post('/confirm-reservation', [CirculationController::class, 'confirmReservation']);
            Route::post('/direct-loan', [CirculationController::class, 'storeDirect']);
            Route::post('/returns', [CirculationController::class, 'returnLoan']);
            Route::post('/returns/{loan}', [CirculationController::class, 'returnLoan']);
            Route::post('/renewals', [CirculationController::class, 'renewLoan']);
        });

        // Group 'lockers': Modul Peminjaman & Pengembalian Kunci Loker
        Route::middleware(['auth:admin', 'admin.role:operator,admin,super_admin'])->prefix('lockers')->group(function () {
            Route::post('/borrow', [LockerController::class, 'borrow']);
            Route::post('/return', [LockerController::class, 'returnLocker']);
            Route::get('/loans', [LockerController::class, 'loansHistory']);
        });

        // Group 'guestbooks': Rekap & Laporan Buku Tamu
        Route::middleware(['auth:admin', 'admin.role:operator,admin,super_admin'])->prefix('guestbooks')->group(function () {
            Route::get('/reports', [GuestbookController::class, 'reports']);
        });

        // Group 'onsite-readings': Rekap & Laporan Baca di Tempat
        Route::middleware(['auth:admin', 'admin.role:operator,admin,super_admin'])->prefix('onsite-readings')->group(function () {
            Route::get('/reports', [OnsiteReadingController::class, 'reports']);
        });

        // Group 'reports': Modul Laporan Eksekutif (Report Engine)
        Route::middleware(['auth:admin', 'admin.role:operator,admin,super_admin'])->prefix('reports')->group(function () {
            Route::get('/circulation', [ReportController::class, 'circulation']);
            Route::get('/members', [ReportController::class, 'members']);
            Route::get('/catalogs', [ReportController::class, 'catalogs']);
            Route::get('/onsite-readings', [ReportController::class, 'onsiteReadings']);
            Route::get('/guestbooks', [ReportController::class, 'guestbooks']);
        });

        // Group 'settings': Modul Pengaturan Umum Sistem (System Settings)
        Route::middleware(['auth:admin', 'admin.role:super_admin,admin'])->prefix('settings')->group(function () {
            Route::get('/', [SystemSettingController::class, 'index']);
            Route::put('/', [SystemSettingController::class, 'update']);
            Route::post('/', [SystemSettingController::class, 'update']);
        });
    });
});
