<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Collection;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminAccessController extends Controller
{
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
            'data'    => collect($paginated->items())->map(fn ($admin) => $this->formatAdmin($admin))->values(),
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
        if ($deny = $this->authorizeSuperAdmin($request)) {
            return $deny;
        }
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'username'  => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:admins,username'],
            'email'     => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password'  => ['required', 'string', 'min:6'],
            'role'      => ['required', Rule::in(['super_admin', 'admin', 'operator'])],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required'      => 'Nama lengkap petugas wajib diisi.',
            'username.required'  => 'Username wajib diisi.',
            'username.unique'    => 'Username sudah digunakan oleh petugas lain.',
            'username.regex'     => 'Username hanya boleh mengandung huruf, angka, titik, strip, atau garis bawah.',
            'email.required'     => 'Alamat email wajib diisi.',
            'email.email'        => 'Format alamat email tidak valid.',
            'email.unique'       => 'Alamat email sudah terdaftar.',
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal harus 6 karakter.',
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

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'username'  => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('admins', 'username')->ignore($admin->id)],
            'email'     => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
            'password'  => ['nullable', 'string', 'min:6'],
            'role'      => ['required', Rule::in(['super_admin', 'admin', 'operator'])],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required'      => 'Nama lengkap petugas wajib diisi.',
            'username.required'  => 'Username wajib diisi.',
            'username.unique'    => 'Username sudah digunakan oleh petugas lain.',
            'username.regex'     => 'Username hanya boleh mengandung huruf, angka, titik, strip, atau garis bawah.',
            'email.required'     => 'Alamat email wajib diisi.',
            'email.email'        => 'Format alamat email tidak valid.',
            'email.unique'       => 'Alamat email sudah terdaftar.',
            'password.min'       => 'Password minimal harus 6 karakter.',
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

        $admin->update($updateData);

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
        $admin->delete();

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
        $matrix = [
            [
                'category'    => 'PENGELOLAAN KOLEKSI',
                'module'      => 'Entri Katalog Buku',
                'description' => 'Membuat, mengubah, dan mengunggah metadata bibliografis buku.',
                'permissions' => [
                    'super_admin' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'admin'       => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                    'operator'    => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                ],
            ],
            [
                'category'    => 'PENGELOLAAN KOLEKSI',
                'module'      => 'Daftar Koleksi & Eksemplar',
                'description' => 'Manajemen nomor barcode fisik, cetak label barcode, dan kondisi fisik buku.',
                'permissions' => [
                    'super_admin' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'admin'       => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                    'operator'    => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false],
                ],
            ],
            [
                'category'    => 'PENGELOLAAN KOLEKSI',
                'module'      => 'Karantina Koleksi',
                'description' => 'Memindahkan buku rusak/hilang ke karantina, validasi, dan publikasi ke OPAC.',
                'permissions' => [
                    'super_admin' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'admin'       => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                    'operator'    => ['view' => true, 'create' => true, 'edit' => false, 'delete' => false],
                ],
            ],
            [
                'category'    => 'KEANGGOTAAN',
                'module'      => 'Entri & Data Anggota',
                'description' => 'Pendaftaran anggota baru, cetak KTA, verifikasi identitas, dan ekspor data.',
                'permissions' => [
                    'super_admin' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'admin'       => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                    'operator'    => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                ],
            ],
            [
                'category'    => 'KEANGGOTAAN',
                'module'      => 'Keranjang Bermasalah',
                'description' => 'Penanganan sanksi sirkulasi, pembekuan akses pinjam, tindak lanjut dan pemulihan status.',
                'permissions' => [
                    'super_admin' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'admin'       => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                    'operator'    => ['view' => true, 'create' => true, 'edit' => false, 'delete' => false],
                ],
            ],
            [
                'category'    => 'LAYANAN SIRKULASI',
                'module'      => 'Verifikasi & Meja Reservasi',
                'description' => 'Verifikasi ketersediaan buku di rak fisik, konfirmasi siap ambil, dan pengambilan di loket.',
                'permissions' => [
                    'super_admin' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'admin'       => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'operator'    => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                ],
            ],
            [
                'category'    => 'LAYANAN SIRKULASI',
                'module'      => 'Peminjaman Langsung Loket',
                'description' => 'Transaksi peminjaman buku tatap muka menggunakan scanner barcode buku & KTA.',
                'permissions' => [
                    'super_admin' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'admin'       => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'operator'    => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                ],
            ],
            [
                'category'    => 'LAYANAN SIRKULASI',
                'module'      => 'Pengembalian Koleksi',
                'description' => 'Pencatatan pengembalian buku, inspeksi kondisi fisik, dan pencatatan riwayat pinjam.',
                'permissions' => [
                    'super_admin' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'admin'       => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'operator'    => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                ],
            ],
            [
                'category'    => 'RUANG BACA & FASILITAS',
                'module'      => 'Peminjaman Loker & Buku Tamu',
                'description' => 'Pengelolaan kunci loker penitipan barang dan presensi kehadiran pemustaka di ruang baca.',
                'permissions' => [
                    'super_admin' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'admin'       => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'operator'    => ['view' => true, 'create' => true, 'edit' => true, 'delete' => false],
                ],
            ],
            [
                'category'    => 'SISTEM & HAK AKSES',
                'module'      => 'Kelola Akses & Akun Petugas',
                'description' => 'Pengelolaan akun petugas, penetapan peran (role), dan monitoring log aktivitas staf.',
                'permissions' => [
                    'super_admin' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                    'admin'       => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                    'operator'    => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                ],
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
                    'description' => 'Akses penuh ke seluruh modul, konfigurasi keamanan, dan manajemen pengguna.',
                ],
                [
                    'key'         => 'admin',
                    'name'        => 'Pustakawan / Admin',
                    'badge'       => 'Biru',
                    'description' => 'Pengolahan katalog, manajemen eksemplar, keanggotaan, dan rekap sirkulasi.',
                ],
                [
                    'key'         => 'operator',
                    'name'        => 'Operator Sirkulasi',
                    'badge'       => 'Hijau',
                    'description' => 'Pelayanan operasional loket harian sirkulasi, loker, dan buku tamu presensi.',
                ],
            ],
            'matrix'  => $matrix,
            'data'    => $matrix,
        ]);
    }

    /**
     * GET /api/v1/admin/access/activity-logs
     * Riwayat aktivitas / audit trail terbaru.
     */
    public function activityLogs(Request $request): JsonResponse
    {
        $currentAdmin = $this->getAuthenticatedAdmin($request);
        if ($currentAdmin && $currentAdmin->role === 'operator') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Operator Sirkulasi tidak memiliki izin untuk melihat log audit keamanan.',
            ], 403);
        }
        // Kumpulkan 15 aktivitas terbaru dari transaksi sistem untuk audit trail
        $logs = [];

        // 1. Akun admin dibuat/diupdate
        $admins = Admin::latest('updated_at')->take(5)->get();
        foreach ($admins as $a) {
            $logs[] = [
                'id'        => 'admin_' . $a->id,
                'timestamp' => $a->updated_at ? $a->updated_at->toIso8601String() : now()->toIso8601String(),
                'date'      => $a->updated_at ? $a->updated_at->translatedFormat('d M Y, H:i') : '-',
                'actor'     => $a->name,
                'role'      => $a->role,
                'action'    => 'Pembaruan Akun Petugas',
                'details'   => "Akun {$a->username} ({$a->email}) berstatus " . ($a->is_active ? 'Aktif' : 'Nonaktif'),
                'type'      => 'security',
            ];
        }

        // 2. Transaksi sirkulasi terbaru
        $recentLoans = Loan::with(['member', 'collection.catalog', 'createdByAdmin'])
            ->latest('id')
            ->take(8)
            ->get();

        foreach ($recentLoans as $l) {
            $officer = $l->createdByAdmin?->name ?: 'Petugas Sirkulasi';
            $logs[] = [
                'id'        => 'loan_' . $l->id,
                'timestamp' => $l->created_at ? $l->created_at->toIso8601String() : now()->toIso8601String(),
                'date'      => $l->created_at ? $l->created_at->translatedFormat('d M Y, H:i') : '-',
                'actor'     => $officer,
                'role'      => 'operator',
                'action'    => "Transaksi Sirkulasi ({$l->status})",
                'details'   => "Peminjaman {$l->loan_number} untuk anggota {$l->member?->name} ({$l->collection?->catalog?->title})",
                'type'      => 'circulation',
            ];
        }

        // Urutkan berdasarkan waktu terbaru
        usort($logs, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return response()->json([
            'success' => true,
            'message' => 'Riwayat aktivitas sistem berhasil diambil.',
            'data'    => array_slice($logs, 0, 15),
        ]);
    }
}
