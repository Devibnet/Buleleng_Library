<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CirculationController;
use App\Http\Controllers\Api\GuestbookController;
use App\Http\Controllers\Api\LockerController;
use App\Http\Controllers\Api\OnsiteReadingController;
use App\Http\Controllers\Api\V1\Admin\AdminProfileController;
use App\Http\Controllers\Api\V1\Admin\BookImportController;
use App\Http\Controllers\Api\V1\Admin\BookManagementController;
use App\Http\Controllers\Api\V1\Admin\BorrowingController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\LoanListController;
use App\Http\Controllers\Api\V1\Admin\LockerDeskController;
use App\Http\Controllers\Api\V1\Admin\MemberManagementController;
use App\Http\Controllers\Api\V1\Admin\ProblemCartController;
use App\Http\Controllers\Api\V1\Admin\QuarantineController;
use App\Http\Controllers\Api\V1\Admin\ReservationDeskController;
use App\Http\Controllers\Api\V1\Admin\ReturnController;
use App\Http\Controllers\Api\V1\MemberSelfServiceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Auth\MemberRegisterController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RESTful API Routes - System Backend Perpustakaan Buleleng
|--------------------------------------------------------------------------
*/

// Public Catalog Endpoints (Bisa diakses tanpa login di root /books)
Route::get('/opac/stats', [BookController::class, 'stats']);
Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{id}', [BookController::class, 'show']);
Route::post('/books/{id}/reserve', [ReservationController::class, 'reserve']);

// Auth Endpoints (Bisa diakses di root /api/auth/login dan /api/auth/member/login)
Route::post('/auth/login', [AuthController::class, 'loginMember']);
Route::post('/auth/member/login', [AuthController::class, 'loginMember']);
Route::post('/auth/admin/login', [AuthController::class, 'loginAdmin']);
Route::post('/auth/register', [MemberRegisterController::class, 'registerApi']);
Route::post('/auth/member/register', [MemberRegisterController::class, 'registerApi']);
Route::post('/member/register', [MemberRegisterController::class, 'registerApi']);

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

// Admin Dashboard Endpoints (Root alias)
Route::middleware(['auth:sanctum', 'admin.role:operator,admin,super_admin'])->prefix('admin/dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::post('/scan', [DashboardController::class, 'scan']);
    Route::post('/sync', [DashboardController::class, 'sync']);
});

// Admin Profile Endpoints (Root alias)
Route::middleware(['auth:sanctum', 'admin.role:operator,admin,super_admin'])->prefix('admin/profile')->group(function () {
    Route::get('/', [AdminProfileController::class, 'show']);
    Route::put('/', [AdminProfileController::class, 'update']);
    Route::put('/password', [AdminProfileController::class, 'updatePassword']);
});

Route::prefix('v1')->group(function () {

    // 1. Public Catalog Endpoints (v1)
    Route::get('/opac/stats', [BookController::class, 'stats']);
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
    Route::post('/auth/member/register', [MemberRegisterController::class, 'registerApi']);
    Route::post('/auth/admin/login', [AuthController::class, 'loginAdmin']);

    // 5. Member Protected Area - Layanan Mandiri: Reservasi Saya & Pinjaman Saya (Guard: token Sanctum)
    Route::middleware('auth:sanctum')->prefix('member')->group(function () {
        Route::get('/reservations', [MemberSelfServiceController::class, 'reservations']);
        Route::post('/reservations', [MemberSelfServiceController::class, 'reserve']);
        Route::post('/reservations/{id}/cancel', [MemberSelfServiceController::class, 'cancel'])->whereNumber('id');
        Route::get('/loans', [MemberSelfServiceController::class, 'loans']);
        Route::get('/favorites', [MemberSelfServiceController::class, 'favorites']);
        Route::post('/favorites/{catalogId}/toggle', [MemberSelfServiceController::class, 'toggleFavorite'])->whereNumber('catalogId');
    });

    // 5b. Profile Management Endpoints (Guard: auth:sanctum)
    Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::match(['put', 'post'], '/', [ProfileController::class, 'update']);
        Route::post('/photo', [ProfileController::class, 'updatePhoto']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
        Route::get('/problem-history', [\App\Http\Controllers\Api\V1\Admin\ProblemCartController::class, 'myProblemHistory']);
    });

    // 6. Admin Protected Area with RBAC Guard & Role Middleware
    Route::prefix('admin')->group(function () {

        // Modul Manajemen Buku & Dashboard Admin (CRUD, Rekap Database & Import Excel)
        Route::middleware(['auth:sanctum', 'admin.role:operator,admin,super_admin'])->group(function () {
            // Dashboard Admin Otomasi & Statistik
            Route::get('/dashboard', [DashboardController::class, 'index']);
            Route::post('/dashboard/scan', [DashboardController::class, 'scan']);
            Route::post('/dashboard/sync', [DashboardController::class, 'sync']);

            Route::get('/books', [BookManagementController::class, 'index']);
            Route::get('/collections', [BookManagementController::class, 'listCollections']);
            Route::post('/books/import', [BookManagementController::class, 'importExcel']);
            Route::post('/books/import/prepare', [BookImportController::class, 'prepare']);
            Route::post('/books/import/{token}/process', [BookImportController::class, 'process']);
            Route::delete('/books/import/{token}', [BookImportController::class, 'cancel']);
            Route::get('/books/{id}', [BookManagementController::class, 'show']);
            Route::post('/books', [BookManagementController::class, 'store']);
            Route::put('/books/{id}', [BookManagementController::class, 'updateCatalog']);
            Route::delete('/books/{id}', [BookManagementController::class, 'destroyCatalog']);
            Route::put('/collections/{collection_id}', [BookManagementController::class, 'updateCollection']);
            Route::delete('/collections/{collection_id}', [BookManagementController::class, 'destroyCollection']);

            // Modul Karantina Koleksi & Sinkronisasi OPAC
            Route::get('/quarantine', [QuarantineController::class, 'index']);
            Route::post('/quarantine/move', [QuarantineController::class, 'moveToQuarantine']);
            Route::post('/quarantine/bulk-restore', [QuarantineController::class, 'bulkRestore']);
            Route::post('/quarantine/bulk-delete', [QuarantineController::class, 'bulkDestroy']);
            Route::post('/quarantine/{id}/restore', [QuarantineController::class, 'restore']);
            Route::post('/quarantine/{id}/validate', [QuarantineController::class, 'validateCatalog']);

            // Modul Manajemen Anggota Admin (CRUD, Search, Filter, Statistik, KTA, Bulk Delete, Export)
            Route::get('/members/export', [MemberManagementController::class, 'export']);
            Route::get('/members/next-number', [MemberManagementController::class, 'nextNumber']);
            Route::post('/members/bulk-delete', [MemberManagementController::class, 'bulkDestroy']);
            Route::get('/members', [MemberManagementController::class, 'index']);
            Route::post('/members', [MemberManagementController::class, 'store']);
            Route::get('/members/{id}', [MemberManagementController::class, 'show']);
            Route::put('/members/{id}', [MemberManagementController::class, 'update']);
            Route::delete('/members/{id}', [MemberManagementController::class, 'destroy']);

            // Modul Keranjang Anggota Bermasalah (Problem Carts)
            Route::get('/problem-carts', [ProblemCartController::class, 'index']);
            Route::post('/problem-carts', [ProblemCartController::class, 'store']);
            Route::post('/problem-carts/sync-circulation', [ProblemCartController::class, 'syncCirculation']);
            Route::post('/problem-carts/bulk-action', [ProblemCartController::class, 'bulkAction']);
            Route::get('/problem-carts/{id}', [ProblemCartController::class, 'show']);
            Route::put('/problem-carts/{id}', [ProblemCartController::class, 'update']);
            Route::post('/problem-carts/{id}/followup', [ProblemCartController::class, 'followup']);
            Route::post('/problem-carts/{id}/resolve', [ProblemCartController::class, 'resolve']);
            Route::delete('/problem-carts/{id}', [ProblemCartController::class, 'destroy']);
            Route::get('/members/{id}/problem-history', [ProblemCartController::class, 'memberHistory']);

            // Modul Reservasi (Verifikasi Rak & Pengambilan di Loket)
            Route::get('/circulation/reservation-desk', [ReservationDeskController::class, 'index']);
            Route::get('/circulation/reservation-desk/lookup', [ReservationDeskController::class, 'lookup']);
            Route::post('/circulation/reservation-desk/{id}/confirm-ready', [ReservationDeskController::class, 'confirmReady'])->whereNumber('id');
            Route::post('/circulation/reservation-desk/{id}/mark-unavailable', [ReservationDeskController::class, 'markUnavailable'])->whereNumber('id');
            Route::post('/circulation/reservation-desk/{id}/pickup', [ReservationDeskController::class, 'pickup'])->whereNumber('id');

            // Modul Peminjaman Buku (Loket Sirkulasi)
            Route::get('/circulation/borrow/member', [BorrowingController::class, 'member']);
            Route::get('/circulation/borrow/collection', [BorrowingController::class, 'collection']);
            Route::get('/circulation/borrow/collections/search', [BorrowingController::class, 'searchCollections']);
            Route::post('/circulation/borrow', [BorrowingController::class, 'store']);

            // Modul Pengembalian Buku (Loket Sirkulasi)
            Route::get('/circulation/return/lookup', [ReturnController::class, 'lookup']);
            Route::post('/circulation/return', [ReturnController::class, 'store']);

            // Modul Daftar Pinjaman Koleksi (Monitoring Sirkulasi Aktif)
            Route::get('/circulation/active-loans', [LoanListController::class, 'index']);
            Route::get('/circulation/active-loans/export', [LoanListController::class, 'export']);
            Route::post('/circulation/active-loans/sync', [LoanListController::class, 'sync']);

            // Modul Peminjaman Loker Penitipan (Loket Ruang Baca)
            Route::get('/lockers/desk/member', [LockerDeskController::class, 'member']);
            Route::get('/lockers/desk/lockers', [LockerDeskController::class, 'lockers']);
            Route::get('/lockers/desk/locker', [LockerDeskController::class, 'locker']);
            Route::post('/lockers/desk/borrow', [LockerDeskController::class, 'borrow']);

            // Admin Profile Management (v1)
            Route::prefix('profile')->group(function () {
                Route::get('/', [AdminProfileController::class, 'show']);
                Route::put('/', [AdminProfileController::class, 'update']);
                Route::put('/password', [AdminProfileController::class, 'updatePassword']);
            });
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
                $collections = \App\Models\Collection::with('catalog')->get();
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
        Route::middleware(['auth:sanctum', 'admin.role:operator,admin,super_admin'])->prefix('reports')->group(function () {
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

// Endpoint publik penyaji berkas storage dengan CORS header untuk ekspor Canvas/KTA
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    if (!file_exists($filePath)) {
        abort(404);
    }
    return response()->file($filePath, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
    ]);
})->where('path', '.*');

