<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\AdminPasswordReset;
use App\Services\AdminActivityLogger;
use App\Support\StaffPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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
            'name'      => 'Nama',
            'username'  => 'Username',
            'email'     => 'Email',
            'role'      => 'Peran',
            'is_active' => 'Status Akun',
            'password'  => 'Password',
            'reset_link' => 'Link Reset',
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
        uksort($rawChanges, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        $changes = [];
        foreach ($rawChanges as $field => $change) {
            $isPassword = $field === 'password';
            $changes[] = [
                'field'       => $field,
                'label'       => $fieldLabels[$field] ?? ucfirst($field),
                'from'        => $isPassword ? null : $formatValue($field, $change['from'] ?? null),
                'to'          => $isPassword ? 'Diubah (disembunyikan)' : $formatValue($field, $change['to'] ?? null),
                'is_password' => $isPassword,
                // Nilai asli (mis. kode peran) untuk pewarnaan di UI
                'from_raw'    => $isPassword ? null : ($change['from'] ?? null),
                'to_raw'      => $isPassword ? null : ($change['to'] ?? null),
            ];
        }

        return [
            'id'           => $log->id,
            'action'       => $log->action,
            'action_label' => AdminActivityLog::ACTION_LABELS[$log->action] ?? $log->action,
            'description'  => $log->description,
            'actor'        => [
                'id'         => $log->actor_id,
                'name'       => $log->actor_name ?? 'Sistem',
                'username'   => $log->actor_username,
                'role'       => $log->actor_role,
                'role_label' => AdminActivityLogger::roleLabel($log->actor_role),
            ],
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
}
