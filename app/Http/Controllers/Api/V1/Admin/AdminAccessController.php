<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\AdminPasswordReset;
use App\Models\ImpersonationSession;
use App\Services\AdminActivityLogger;
use App\Support\StaffPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class AdminAccessController extends Controller
{
    public function __construct(private AdminActivityLogger $activityLogger)
    {
    }

    /** Nama: huruf, spasi, titik, koma, apostrof, strip (tanpa angka). */
    private const NAME_REGEX = "/^[\pL\s.,'-]+$/u";

    /** Email: local@domain(min 2 karakter)[.subdomain].tld(min 2 huruf). */
    private const EMAIL_REGEX = '/^[^\s@]+@[A-Za-z0-9-]{2,}(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/';

    /**
     * Dapatkan instance Admin yang sedang login.
     */
    protected function getAuthenticatedAdmin(Request $request): ?Admin
    {
        $user = Auth::guard('admin')->user() ?? Auth::guard('sanctum')->user() ?? $request->user();

        if ($user instanceof Admin) {
            return $user;
        }

        if ($user && isset($user->id)) {
            return Admin::find($user->id);
        }

        return null;
    }

    /**
     * Format payload data admin.
     */
    protected function formatAdmin(Admin $admin): array
    {
        $roleLabels = [
            'super_admin' => 'Super Admin',
            'admin'       => 'Pustakawan / Admin',
            'operator'    => 'Operator Sirkulasi',
        ];

        return [
            'id'                  => $admin->id,
            'name'                => $admin->name,
            'username'            => $admin->username,
            'email'               => $admin->email,
            'role'                => $admin->role ?? 'operator',
            'role_label'          => $roleLabels[$admin->role] ?? ucfirst($admin->role),
            'is_active'           => (bool) $admin->is_active,
            'created_at'          => $admin->created_at ? $admin->created_at->toIso8601String() : null,
            'created_at_formatted'=> $admin->created_at ? $admin->created_at->translatedFormat('d M Y, H:i') : '-',
            'updated_at'          => $admin->updated_at ? $admin->updated_at->toIso8601String() : null,
        ];
    }

    /**
     * Tambahkan waktu kedaluwarsa link reset password yang masih aktif (jika ada) ke tiap akun.
     */
    protected function withActiveResetLinks($admins)
    {
        $expiries = AdminPasswordReset::usable()
            ->whereIn('admin_id', $admins->pluck('id'))
            ->get(['admin_id', 'expires_at'])
            ->groupBy('admin_id')
            ->map(fn ($rows) => $rows->max('expires_at'));

        return $admins->map(function (array $admin) use ($expiries) {
            $expiresAt = $expiries->get($admin['id']);
            $admin['active_reset_expires_at'] = $expiresAt?->toIso8601String();
            return $admin;
        })->values();
    }

    /**
     * Pastikan pemohon memiliki peran Super Admin.
     */
    protected function authorizeSuperAdmin(Request $request): ?JsonResponse
    {
        $admin = $this->getAuthenticatedAdmin($request);

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
            ], 401);
        }

        if ($admin->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin yang berwenang menambah, mengubah, atau menghapus akun petugas.',
            ], 403);
        }

        return null;
    }

    /**
     * Pastikan pemohon adalah Super Admin atau Admin (pengelola akun).
     */
    protected function authorizeAccountManager(?Admin $admin): ?JsonResponse
    {
        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
            ], 401);
        }

        if (! in_array($admin->role, ['super_admin', 'admin'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak memiliki izin untuk mengelola akun petugas.',
            ], 403);
        }

        return null;
    }

    /**
     * GET /api/v1/admin/access/users
     * Daftar akun petugas, pencarian, filter role/status, statistik.
     */
    public function index(Request $request): JsonResponse
    {
        $currentAdmin = $this->getAuthenticatedAdmin($request);
        if ($currentAdmin && $currentAdmin->role === 'operator') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Operator Sirkulasi tidak memiliki izin untuk melihat daftar akun petugas.',
            ], 403);
        }
        $query = Admin::query();

        // 1. Pencarian (Nama, Username, Email)
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // 2. Filter Role
        if ($role = $request->query('role')) {
            if ($role !== 'all' && $role !== 'ALL') {
                $query->where('role', strtolower($role));
            }
        }

        // 3. Filter Status Aktif
        if ($request->has('status') && $request->query('status') !== 'all' && $request->query('status') !== 'ALL') {
            $status = $request->query('status');
            if ($status === 'active' || $status === '1') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive' || $status === '0') {
                $query->where('is_active', false);
            }
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
        $paginated = $query->orderBy('role', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($perPage);

        // 4. Hitung Statistik Realtime
        $totalUsers = Admin::count();
        $activeUsers = Admin::where('is_active', true)->count();
        $inactiveUsers = Admin::where('is_active', false)->count();
        $superAdminCount = Admin::where('role', 'super_admin')->count();
        $adminCount = Admin::where('role', 'admin')->count();
        $operatorCount = Admin::where('role', 'operator')->count();

        return response()->json([
            'success' => true,
            'message' => 'Daftar akun petugas berhasil dimuat.',
            'stats'   => [
                'total_users'       => $totalUsers,
                'active_users'      => $activeUsers,
                'inactive_users'    => $inactiveUsers,
                'super_admin_count' => $superAdminCount,
                'admin_count'       => $adminCount,
                'operator_count'    => $operatorCount,
            ],
            'data'    => $this->withActiveResetLinks(
                collect($paginated->items())->map(fn ($admin) => $this->formatAdmin($admin))->values()
            ),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/access/users
     * Buat akun staf baru.
     */
    public function store(Request $request): JsonResponse
    {
        $currentAdmin = $this->getAuthenticatedAdmin($request);
        if ($deny = $this->authorizeAccountManager($currentAdmin)) {
            return $deny;
        }

        // Admin hanya boleh menambahkan akun Operator.
        if ($currentAdmin->role === 'admin' && $request->input('role') !== 'operator') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Admin hanya dapat menambahkan akun Operator Sirkulasi.',
            ], 403);
        }
        $validated = $request->validate([
            'name'      => ['required', 'string', 'min:3', 'max:255', 'regex:' . self::NAME_REGEX],
            'username'  => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:admins,username'],
            'email'     => ['required', 'email', 'max:255', 'regex:' . self::EMAIL_REGEX, 'unique:admins,email'],
            'password'  => StaffPassword::rules(true),
            'role'      => ['required', Rule::in(['super_admin', 'admin', 'operator'])],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required'      => 'Nama lengkap petugas wajib diisi.',
            'name.min'           => 'Nama minimal 3 karakter.',
            'name.regex'         => 'Nama tidak boleh mengandung angka dan hanya boleh berisi huruf, spasi, titik, koma, apostrof, atau strip.',
            'username.required'  => 'Username wajib diisi.',
            'username.unique'    => 'Username sudah digunakan oleh petugas lain.',
            'username.regex'     => 'Username hanya boleh mengandung huruf, angka, titik, strip, atau garis bawah.',
            'email.required'     => 'Alamat email wajib diisi.',
            'email.email'        => 'Format alamat email tidak valid.',
            'email.regex'        => 'Format email tidak valid. Contoh: nama@domain.id',
            'email.unique'       => 'Alamat email sudah terdaftar.',
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal harus 6 karakter.',
            'password.confirmed' => 'Verifikasi password tidak cocok.',
            'password.regex'     => 'Password harus mengandung minimal 1 huruf besar, 1 angka, dan 1 karakter unik.',
            'role.required'      => 'Peran / hak akses wajib ditentukan.',
            'role.in'            => 'Peran yang dipilih tidak valid.',
        ]);

        $admin = Admin::create([
            'name'      => trim($validated['name']),
            'username'  => strtolower(trim($validated['username'])),
            'email'     => strtolower(trim($validated['email'])),
            'password'  => Hash::make($validated['password']),
            'role'      => $validated['role'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $created = AdminActivityLogger::snapshot($admin);
        $this->activityLogger->log(
            $request,
            $currentAdmin,
            'account_created',
            $admin,
            collect($created)->map(fn ($value) => ['from' => null, 'to' => $value])->all()
        );

        return response()->json([
            'success' => true,
            'message' => "Akun petugas '{$admin->name}' ({$admin->username}) berhasil dibuat.",
            'data'    => $this->formatAdmin($admin),
        ], 201);
    }

    /**
     * GET /api/v1/admin/access/users/{id}
     * Ambil detail akun petugas.
     */
    public function show(int $id): JsonResponse
    {
        $admin = Admin::find($id);

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akun petugas tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail akun petugas ditemukan.',
            'data'    => $this->formatAdmin($admin),
        ]);
    }

    /**
     * PUT /api/v1/admin/access/users/{id}
     * Perbarui data akun petugas.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $requester = $this->getAuthenticatedAdmin($request);
        if ($deny = $this->authorizeAccountManager($requester)) {
            return $deny;
        }
        $admin = Admin::find($id);

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akun petugas tidak ditemukan.',
            ], 404);
        }

        // Admin hanya boleh mengedit akunnya sendiri, tanpa mengubah peran.
        if ($requester->role === 'admin') {
            if ($requester->id !== $admin->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Admin hanya dapat mengedit akunnya sendiri.',
                ], 403);
            }

            if ($request->has('role') && $request->input('role') !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Admin tidak dapat mengubah perannya sendiri.',
                ], 403);
            }
        }

        $validated = $request->validate([
            'name'      => ['required', 'string', 'min:3', 'max:255', 'regex:' . self::NAME_REGEX],
            'username'  => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('admins', 'username')->ignore($admin->id)],
            'email'     => ['required', 'email', 'max:255', 'regex:' . self::EMAIL_REGEX, Rule::unique('admins', 'email')->ignore($admin->id)],
            'password'  => StaffPassword::rules(false),
            'role'      => ['required', Rule::in(['super_admin', 'admin', 'operator'])],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required'      => 'Nama lengkap petugas wajib diisi.',
            'name.min'           => 'Nama minimal 3 karakter.',
            'name.regex'         => 'Nama tidak boleh mengandung angka dan hanya boleh berisi huruf, spasi, titik, koma, apostrof, atau strip.',
            'username.required'  => 'Username wajib diisi.',
            'username.unique'    => 'Username sudah digunakan oleh petugas lain.',
            'username.regex'     => 'Username hanya boleh mengandung huruf, angka, titik, strip, atau garis bawah.',
            'email.required'     => 'Alamat email wajib diisi.',
            'email.email'        => 'Format alamat email tidak valid.',
            'email.regex'        => 'Format email tidak valid. Contoh: nama@domain.id',
            'email.unique'       => 'Alamat email sudah terdaftar.',
            'password.min'       => 'Password minimal harus 6 karakter.',
            'password.confirmed' => 'Verifikasi password tidak cocok.',
            'password.regex'     => 'Password harus mengandung minimal 1 huruf besar, 1 angka, dan 1 karakter unik.',
            'role.required'      => 'Peran / hak akses wajib ditentukan.',
            'role.in'            => 'Peran yang dipilih tidak valid.',
        ]);

        $currentAdmin = $this->getAuthenticatedAdmin($request);

        // Proteksi: Petugas tidak boleh mendowngrade akunnya sendiri jika dia satu-satunya Super Admin
        if ($currentAdmin && $currentAdmin->id === $admin->id) {
            if ($admin->role === 'super_admin' && $validated['role'] !== 'super_admin') {
                $otherSuperAdmins = Admin::where('role', 'super_admin')
                    ->where('id', '!=', $admin->id)
                    ->where('is_active', true)
                    ->count();

                if ($otherSuperAdmins === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak dapat menurunkan peran akun Anda sendiri karena Anda adalah satu-satunya Super Admin aktif.',
                    ], 422);
                }
            }

            if ($request->has('is_active') && ! $request->boolean('is_active')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
                ], 422);
            }
        }

        $updateData = [
            'name'      => trim($validated['name']),
            'username'  => strtolower(trim($validated['username'])),
            'email'     => strtolower(trim($validated['email'])),
            'role'      => $validated['role'],
        ];

        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $before = AdminActivityLogger::snapshot($admin);
        $admin->update($updateData);

        $changes = AdminActivityLogger::diff($before, AdminActivityLogger::snapshot($admin));
        if (! empty($validated['password'])) {
            $changes['password'] = ['from' => null, 'to' => null, 'changed' => true];
        }
        if ($action = AdminActivityLogger::resolveUpdateAction($changes)) {
            $this->activityLogger->log($request, $requester, $action, $admin, $changes);
        }

        return response()->json([
            'success' => true,
            'message' => "Data akun petugas '{$admin->name}' berhasil diperbarui.",
            'data'    => $this->formatAdmin($admin),
        ]);
    }

    /**
     * PATCH /api/v1/admin/access/users/{id}/toggle-status
     * Ubah status aktif/nonaktif akun petugas secara instan.
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->authorizeSuperAdmin($request)) {
            return $deny;
        }
        $admin = Admin::find($id);

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akun petugas tidak ditemukan.',
            ], 404);
        }

        $currentAdmin = $this->getAuthenticatedAdmin($request);

        // 1. Jangan biarkan admin menonaktifkan akunnya sendiri
        if ($currentAdmin && $currentAdmin->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ], 422);
        }

        // 2. Jangan biarkan menonaktifkan satu-satunya Super Admin aktif
        if ($admin->role === 'super_admin' && $admin->is_active) {
            $otherActiveSuperAdmins = Admin::where('role', 'super_admin')
                ->where('id', '!=', $admin->id)
                ->where('is_active', true)
                ->count();

            if ($otherActiveSuperAdmins === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menonaktifkan akun ini karena merupakan satu-satunya Super Admin aktif pada sistem.',
                ], 422);
            }
        }

        $admin->is_active = ! $admin->is_active;
        $admin->save();

        $this->activityLogger->log(
            $request,
            $currentAdmin,
            $admin->is_active ? 'account_activated' : 'account_deactivated',
            $admin,
            ['is_active' => ['from' => ! $admin->is_active, 'to' => (bool) $admin->is_active]]
        );

        $statusText = $admin->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'success' => true,
            'message' => "Akun petugas '{$admin->name}' berhasil {$statusText}.",
            'data'    => $this->formatAdmin($admin),
        ]);
    }

    /**
     * DELETE /api/v1/admin/access/users/{id}
     * Hapus akun petugas.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->authorizeSuperAdmin($request)) {
            return $deny;
        }
        $admin = Admin::find($id);

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Akun petugas tidak ditemukan.',
            ], 404);
        }

        $currentAdmin = $this->getAuthenticatedAdmin($request);

        if ($currentAdmin && $currentAdmin->id === $admin->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 422);
        }

        if ($admin->role === 'super_admin') {
            $superAdminsCount = Admin::where('role', 'super_admin')->count();
            if ($superAdminsCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sistem harus memiliki setidaknya satu Super Admin. Akun ini tidak dapat dihapus.',
                ], 422);
            }
        }

        $name = $admin->name;
        $deletedSnapshot = AdminActivityLogger::snapshot($admin);
        $admin->delete();

        $this->activityLogger->log(
            $request,
            $currentAdmin,
            'account_deleted',
            $admin,
            collect($deletedSnapshot)->map(fn ($value) => ['from' => $value, 'to' => null])->all(),
            null,
            $deletedSnapshot
        );

        return response()->json([
            'success' => true,
            'message' => "Akun petugas '{$name}' berhasil dihapus dari sistem.",
        ]);
    }

    /**
     * GET /api/v1/admin/access/permissions-matrix
     * Matriks Hak Akses per Modul dan Peran.
     */
    public function permissionsMatrix(): JsonResponse
    {
        $full = ['view' => true, 'create' => true, 'edit' => true, 'delete' => true];
        $none = ['view' => false, 'create' => false, 'edit' => false, 'delete' => false];

        // Operator hanya memiliki akses ke Layanan Sirkulasi.
        // Admin memiliki akses penuh kecuali pada pengelolaan akun petugas.
        $modules = [
            ['PENGELOLAAN KOLEKSI', 'Entri Katalog Buku', 'Membuat, mengubah, menghapus, dan mengimpor metadata bibliografis buku.', false],
            ['PENGELOLAAN KOLEKSI', 'Daftar Koleksi & Eksemplar', 'Manajemen nomor barcode fisik, cetak label barcode, dan kondisi fisik buku.', false],
            ['PENGELOLAAN KOLEKSI', 'Karantina Koleksi', 'Memindahkan buku rusak/hilang ke karantina, validasi, dan publikasi ke OPAC.', false],
            ['KEANGGOTAAN', 'Entri & Data Anggota', 'Pendaftaran anggota baru, cetak KTA, verifikasi KTP, dan ekspor data.', false],
            ['KEANGGOTAAN', 'Keranjang Bermasalah', 'Penanganan sanksi sirkulasi, pembekuan akses pinjam, tindak lanjut dan pemulihan status.', false],
            ['LAYANAN SIRKULASI', 'Reservasi', 'Verifikasi ketersediaan buku di rak fisik, konfirmasi siap ambil, dan pengambilan di loket.', true],
            ['LAYANAN SIRKULASI', 'Peminjaman', 'Transaksi peminjaman buku tatap muka menggunakan scanner barcode buku & KTA.', true],
            ['LAYANAN SIRKULASI', 'Pengembalian', 'Pencatatan pengembalian buku, inspeksi kondisi fisik, dan pencatatan riwayat pinjam.', true],
            ['LAYANAN SIRKULASI', 'Daftar Peminjaman', 'Monitoring pinjaman aktif, sinkronisasi, dan ekspor data pinjaman.', true],
            ['LAYANAN RUANG BACA', 'Peminjaman Loker & Buku Tamu', 'Pengelolaan kunci loker penitipan barang dan presensi kehadiran pemustaka.', false],
            ['DASHBOARD & LAPORAN', 'Dashboard, Laporan & Statistik', 'Ringkasan statistik perpustakaan dan laporan eksekutif.', false],
            ['SISTEM', 'Pengaturan Sistem', 'Konfigurasi umum aplikasi perpustakaan.', false],
        ];

        $matrix = array_map(fn ($m) => [
            'category'    => $m[0],
            'module'      => $m[1],
            'description' => $m[2],
            'permissions' => [
                'super_admin' => $full,
                'admin'       => $full,
                'operator'    => $m[3] ? $full : $none,
            ],
        ], $modules);

        $matrix[] = [
            'category'    => 'SISTEM & HAK AKSES',
            'module'      => 'Kelola Akun Petugas',
            'description' => 'Super Admin mengelola semua akun. Admin hanya dapat menambah akun Operator dan mengedit akunnya sendiri.',
            'permissions' => [
                'super_admin' => $full,
                'admin'       => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                'operator'    => $none,
            ],
            'notes' => [
                'admin' => 'Tambah: hanya Operator. Edit: hanya akun sendiri. Tidak dapat menonaktifkan atau menghapus akun.',
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Matriks hak akses sistem berhasil diambil.',
            'roles'   => [
                [
                    'key'         => 'super_admin',
                    'name'        => 'Super Admin',
                    'badge'       => 'Merah',
                    'description' => 'Akses penuh ke seluruh modul, termasuk menambah, mengedit, menonaktifkan, dan menghapus akun Admin maupun Operator.',
                ],
                [
                    'key'         => 'admin',
                    'name'        => 'Pustakawan / Admin',
                    'badge'       => 'Biru',
                    'description' => 'Akses ke seluruh modul. Pada kelola akun, hanya dapat menambah Operator dan mengedit akunnya sendiri.',
                ],
                [
                    'key'         => 'operator',
                    'name'        => 'Operator Sirkulasi',
                    'badge'       => 'Hijau',
                    'description' => 'Hanya layanan sirkulasi: reservasi, peminjaman, pengembalian, dan daftar peminjaman.',
                ],
            ],
            'matrix'  => $matrix,
            'data'    => $matrix,
        ]);
    }

    /**
     * GET /api/v1/admin/access/activity-logs
     * Audit trail perubahan akun & hak akses petugas.
     *
     * Query: search, action, target_role, actor_id, date_from, date_to (Y-m-d), page, per_page
     */
    public function activityLogs(Request $request): JsonResponse
    {
        $currentAdmin = $this->getAuthenticatedAdmin($request);
        if ($deny = $this->authorizeAccountManager($currentAdmin)) {
            return $deny;
        }

        $query = AdminActivityLog::query();

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('actor_name', 'like', "%{$search}%")
                  ->orWhere('actor_username', 'like', "%{$search}%")
                  ->orWhere('target_name', 'like', "%{$search}%")
                  ->orWhere('target_username', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $action = $request->query('action');
        if ($action && $action !== 'all' && array_key_exists($action, AdminActivityLog::ACTION_LABELS)) {
            $query->where('action', $action);
        }

        $targetRole = $request->query('target_role');
        if ($targetRole && in_array($targetRole, ['super_admin', 'admin', 'operator'], true)) {
            $query->where('target_role', $targetRole);
        }

        if ($actorId = (int) $request->query('actor_id')) {
            $query->where('actor_id', $actorId);
        }

        foreach (['date_from' => '>=', 'date_to' => '<='] as $param => $operator) {
            $value = $request->query($param);
            if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $query->whereDate('created_at', $operator, $value);
            }
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
        $paginated = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Log aktivitas berhasil diambil.',
            'data'    => collect($paginated->items())->map(fn (AdminActivityLog $log) => $this->formatLog($log))->values(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
            'actions' => collect(AdminActivityLog::ACTION_LABELS)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                ->values(),
        ]);
    }

    /**
     * Format satu entri log untuk frontend, termasuk rincian perubahan per field.
     */
    protected function formatLog(AdminActivityLog $log): array
    {
        $fieldLabels = [
            'name'          => 'Nama',
            'username'      => 'Username',
            'email'         => 'Email',
            'role'          => 'Peran',
            'is_active'     => 'Status Akun',
            'password'      => 'Password',
            'reset_link'    => 'Link Reset',
            'problem_type'  => 'Jenis Permasalahan',
            'item_title'    => 'Judul Buku',
            'item_barcode'  => 'Barcode Buku',
            'action_status' => 'Status Tindakan',
            'action_notes'  => 'Catatan Tindakan',
            'action'        => 'Tindakan',
            'notes'         => 'Catatan',
            'status'        => 'Status',
            'affected_count'=> 'Jumlah Terdampak',
            'title'         => 'Judul',
            'author_main'   => 'Pengarang Utama',
            'publisher'     => 'Penerbit',
            'isbn'          => 'ISBN',
            'barcode_no'    => 'Nomor Barcode',
            'call_number'   => 'Nomor Panggil',
            'shelf_location'=> 'Lokasi Rak',
            'loan_count'    => 'Jumlah Pinjaman',
            'returned_count'=> 'Jumlah Kembali',
            'barcodes'      => 'Daftar Barcode',
            'duration'      => 'Durasi Pinjam',
            'locker_number' => 'Nomor Loker',
            'phone_number'  => 'Nomor Telepon',
            'job_category'  => 'Kategori Pekerjaan',
            'institution_name' => 'Nama Instansi',
        ];

        $formatValue = function (string $field, $value) {
            if ($value === null) {
                return null;
            }
            return match ($field) {
                'role'      => AdminActivityLogger::roleLabel($value),
                'is_active' => $value ? 'Aktif' : 'Nonaktif',
                default     => (string) $value,
            };
        };

        // Urutkan sesuai urutan form (kolom JSON MySQL tidak menjaga urutan key)
        $order = array_flip(array_keys($fieldLabels));
        $rawChanges = $log->changes ?? [];
        $durationSeconds = isset($rawChanges['duration_seconds']) ? (int) $rawChanges['duration_seconds'] : null;

        uksort($rawChanges, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        $changes = [];
        foreach ($rawChanges as $field => $change) {
            // Hanya masukkan ke tabel perubahan jika field memiliki struktur diff: 'from' atau 'to'
            if (! is_array($change) || (! array_key_exists('from', $change) && ! array_key_exists('to', $change))) {
                continue;
            }

            $isPassword = $field === 'password';
            $changes[] = [
                'field'       => $field,
                'label'       => $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field)),
                'from'        => $isPassword ? null : $formatValue($field, $change['from'] ?? null),
                'to'          => $isPassword ? 'Diubah (disembunyikan)' : $formatValue($field, $change['to'] ?? null),
                'is_password' => $isPassword,
                // Nilai asli (mis. kode peran) untuk pewarnaan di UI
                'from_raw'    => $isPassword ? null : ($change['from'] ?? null),
                'to_raw'      => $isPassword ? null : ($change['to'] ?? null),
            ];
        }

        return [
            'id'               => $log->id,
            'action'           => $log->action,
            'action_label'     => AdminActivityLog::ACTION_LABELS[$log->action] ?? $log->action,
            'module'           => $log->module ?? 'Manajemen Petugas & Akses',
            'result'           => $log->result ?? 'success',
            'description'      => $log->description,
            'duration_seconds' => $durationSeconds,
            'actor'            => [
                'id'         => $log->actor_id,
                'name'       => $log->actor_name ?? 'Sistem',
                'username'   => $log->actor_username,
                'role'       => $log->actor_role,
                'role_label' => AdminActivityLogger::roleLabel($log->actor_role),
            ],
            'impersonator' => $log->impersonator_id ? [
                'id'         => $log->impersonator_id,
                'name'       => $log->impersonator_name,
                'role'       => 'super_admin',
                'role_label' => 'Super Admin',
            ] : null,
            'impersonated_session_id' => $log->impersonated_session_id,
            'impersonation_reason'    => $log->impersonation_reason,
            'target'       => [
                'id'         => $log->target_id,
                'name'       => $log->target_name,
                'username'   => $log->target_username,
                'role'       => $log->target_role,
                'role_label' => AdminActivityLogger::roleLabel($log->target_role),
            ],
            'changes'             => $changes,
            'ip_address'          => $log->ip_address,
            'user_agent'          => $log->user_agent,
            'created_at'          => $log->created_at?->toIso8601String(),
            // Ditampilkan dalam WITA (Buleleng, Bali)
            'created_at_formatted'=> $log->created_at?->copy()->timezone('Asia/Makassar')->locale('id')->translatedFormat('d M Y, H:i:s') . ' WITA',
            'created_at_human'    => $log->created_at?->copy()->locale('id')->diffForHumans(),
        ];
    }

    /**
     * POST /api/v1/admin/access/users/{id}/impersonate
     * Memulai sesi impersonasi akun Admin atau Operator oleh Super Admin.
     */
    public function impersonate(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->authorizeSuperAdmin($request)) {
            return $deny;
        }

        $superAdmin = $this->getAuthenticatedAdmin($request);

        // Validasi: tidak boleh nested impersonation
        if (AdminActivityLogger::resolveActiveImpersonation($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat melakukan impersonasi bertingkat. Akhiri sesi impersonasi saat ini terlebih dahulu.',
            ], 400);
        }

        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Alasan impersonasi wajib diisi.',
            'reason.min'      => 'Alasan impersonasi minimal 5 karakter.',
            'reason.max'      => 'Alasan impersonasi maksimal 500 karakter.',
        ]);

        $target = Admin::find($id);

        if (! $target) {
            return response()->json([
                'success' => false,
                'message' => 'Akun target petugas tidak ditemukan.',
            ], 404);
        }

        // Validasi: tidak boleh impersonate akun sendiri
        if ($target->id === $superAdmin->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat melakukan impersonasi terhadap akun Anda sendiri.',
            ], 422);
        }

        // Validasi: tidak boleh impersonate sesama Super Admin
        if ($target->role === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin tidak diperkenankan mengimpersonasi sesama akun Super Admin.',
            ], 422);
        }

        // Validasi: akun target harus aktif
        if (! $target->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun target saat ini berstatus nonaktif dan tidak dapat diimpersonasi.',
            ], 422);
        }

        $sessionId = ImpersonationSession::generateSessionId();
        $expiresAt = now()->addHours(2); // Batas waktu sesi impersonasi: 2 jam

        // Buat token Sanctum untuk akun target dengan nama khusus sesi impersonasi
        $tokenResult = $target->createToken("impersonation:{$superAdmin->id}:{$sessionId}", ['*'], $expiresAt);
        $plainToken = $tokenResult->plainTextToken;
        $tokenId = $tokenResult->accessToken->id;

        // Catat sesi impersonasi di database
        $session = ImpersonationSession::create([
            'session_id'           => $sessionId,
            'super_admin_id'       => $superAdmin->id,
            'super_admin_name'     => $superAdmin->name,
            'super_admin_username' => $superAdmin->username,
            'target_admin_id'      => $target->id,
            'target_name'          => $target->name,
            'target_username'      => $target->username,
            'target_role'          => $target->role,
            'reason'               => trim((string) $request->input('reason')),
            'token_id'             => $tokenId,
            'ip_address'           => $request->ip(),
            'user_agent'           => Str::limit((string) $request->userAgent(), 250, ''),
            'status'               => ImpersonationSession::STATUS_ACTIVE,
            'started_at'           => now(),
            'expires_at'           => $expiresAt,
        ]);

        // Catat ke Audit Log
        $this->activityLogger->log(
            $request,
            $superAdmin,
            'impersonate_start',
            $target,
            [
                'session_id' => $sessionId,
                'reason'     => $request->input('reason'),
                'expires_at' => $expiresAt->toIso8601String(),
            ],
            null,
            null,
            'Manajemen Petugas & Akses'
        );

        $roleLabel = AdminActivityLogger::roleLabel($target->role);

        return response()->json([
            'success' => true,
            'message' => "Sesi impersonasi berhasil dimulai sebagai {$roleLabel} – {$target->name}.",
            'data'    => [
                'token'         => $plainToken,
                'user'          => $this->formatAdmin($target),
                'impersonation' => [
                    'session_id'   => $sessionId,
                    'super_admin'  => [
                        'id'       => $superAdmin->id,
                        'name'     => $superAdmin->name,
                        'username' => $superAdmin->username,
                        'role'     => $superAdmin->role,
                    ],
                    'target'       => [
                        'id'         => $target->id,
                        'name'       => $target->name,
                        'username'   => $target->username,
                        'role'       => $target->role,
                        'role_label' => $roleLabel,
                    ],
                    'reason'       => $request->input('reason'),
                    'started_at'   => $session->started_at->toIso8601String(),
                    'expires_at'   => $session->expires_at->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * POST /api/v1/admin/access/stop-impersonation
     * Mengakhiri sesi impersonasi dan mencatat ke audit log.
     */
    public function stopImpersonation(Request $request): JsonResponse
    {
        $session = null;
        $sessionId = $request->input('session_id');

        if ($sessionId) {
            $session = ImpersonationSession::where('session_id', $sessionId)->active()->first();
        }

        if (! $session) {
            $session = AdminActivityLogger::resolveActiveImpersonation($request);
        }

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ditemukan sesi impersonasi aktif yang dapat diakhiri.',
            ], 404);
        }

        $session->update([
            'status'     => ImpersonationSession::STATUS_ENDED,
            'ended_at'   => now(),
            'end_reason' => $request->input('end_reason', 'manual'),
        ]);

        // Hapus token akses sesi impersonasi
        if ($session->token_id) {
            PersonalAccessToken::where('id', $session->token_id)->delete();
        }

        $superAdmin = Admin::find($session->super_admin_id);
        $targetAdmin = Admin::find($session->target_admin_id);

        $this->activityLogger->log(
            $request,
            $superAdmin,
            'impersonate_stop',
            $targetAdmin,
            [
                'session_id'       => $session->session_id,
                'reason'           => $session->reason,
                'duration_seconds' => $session->started_at ? now()->diffInSeconds($session->started_at) : 0,
            ],
            null,
            null,
            'Manajemen Petugas & Akses'
        );

        return response()->json([
            'success' => true,
            'message' => 'Sesi impersonasi telah diakhiri. Konteks dikembalikan ke akun Super Admin.',
        ], 200);
    }

    /**
     * GET /api/v1/admin/access/impersonation/current
     * Cek status sesi impersonasi token saat ini.
     */
    public function currentImpersonation(Request $request): JsonResponse
    {
        $session = AdminActivityLogger::resolveActiveImpersonation($request);

        if (! $session) {
            return response()->json([
                'success'          => true,
                'is_impersonating' => false,
                'data'             => null,
            ]);
        }

        return response()->json([
            'success'          => true,
            'is_impersonating' => true,
            'data'             => [
                'session_id'   => $session->session_id,
                'super_admin'  => [
                    'id'       => $session->super_admin_id,
                    'name'     => $session->super_admin_name,
                    'username' => $session->super_admin_username,
                ],
                'target'       => [
                    'id'         => $session->target_admin_id,
                    'name'       => $session->target_name,
                    'username'   => $session->target_username,
                    'role'       => $session->target_role,
                    'role_label' => AdminActivityLogger::roleLabel($session->target_role),
                ],
                'reason'       => $session->reason,
                'started_at'   => $session->started_at?->toIso8601String(),
                'expires_at'   => $session->expires_at?->toIso8601String(),
            ],
        ]);
    }
}
