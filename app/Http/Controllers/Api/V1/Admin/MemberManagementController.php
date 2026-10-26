<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MemberManagementController extends Controller
{
    /**
     * Get paginated member list with search, filter, and statistics summary
     */
    public function index(Request $request): JsonResponse
    {
        $query = Member::query();

        // Search Keyword
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('member_no', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('institution_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('place_of_birth', 'like', "%{$search}%");
            });
        }

        // Filter Job Category
        if ($request->filled('job_category') && $request->job_category !== 'all') {
            $cat = strtoupper($request->job_category);
            if (in_array($cat, ['UMUM', 'PELAJAR', 'MAHASISWA'])) {
                $query->where('job_category', $cat);
            }
        }

        // Filter Status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSorts = ['id', 'member_no', 'name', 'created_at', 'date_of_birth'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = (int) $request->get('per_page', 5);
        if ($perPage < 1) $perPage = 5;
        if ($perPage > 100) $perPage = 100;

        $members = $query->paginate($perPage);

        // Overall Global Statistics for Header Cards
        $totalMembers = Member::count();
        $umumCount = Member::where('job_category', 'UMUM')->count();
        $pelajarCount = Member::where('job_category', 'PELAJAR')->count();
        $mahasiswaCount = Member::where('job_category', 'MAHASISWA')->count();

        $calcPercent = function ($count, $total) {
            if ($total <= 0) return '0.0%';
            return number_format(($count / $total) * 100, 1) . '%';
        };

        return response()->json([
            'success' => true,
            'message' => 'Data anggota berhasil diambil.',
            'data'    => MemberResource::collection($members)->response()->getData(true),
            'stats'   => [
                'total_members'    => $totalMembers,
                'umum_count'       => $umumCount,
                'umum_percent'     => $calcPercent($umumCount, $totalMembers),
                'pelajar_count'    => $pelajarCount,
                'pelajar_percent'  => $calcPercent($pelajarCount, $totalMembers),
                'mahasiswa_count'  => $mahasiswaCount,
                'mahasiswa_percent'=> $calcPercent($mahasiswaCount, $totalMembers),
            ],
            'next_member_no' => Member::generateAtlasMemberNo(),
        ]);
    }

    /**
     * Preview or auto-generate next member number
     */
    public function nextNumber(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'member_no' => Member::generateAtlasMemberNo(),
        ]);
    }

    /**
     * Store new member directly from admin page
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'                => ['required', 'string', 'max:255'],
            'member_no'           => ['nullable', 'string', 'max:50', 'unique:members,member_no'],
            'place_of_birth'      => ['required', 'string', 'max:255'],
            'date_of_birth'       => ['required', 'date'],
            'job_category'        => ['required', 'string'],
            'institution_name'    => ['required', 'string', 'max:255'],
            'phone_number'        => ['required', 'string', 'max:30'],
            'ktp_address'         => ['required', 'string'],
            'residential_address' => ['required', 'string'],
            'gender'              => ['required', 'string', 'in:L,P'],
            'nik'                 => ['nullable', 'string', 'max:30'],
            'email'               => ['nullable', 'email', 'max:255', 'unique:members,email'],
            'photo'               => ['nullable'],
            'status'              => ['nullable', 'string', 'max:50'],
        ], [
            'name.required'                => 'Nama lengkap wajib diisi sesuai identitas resmi.',
            'gender.required'              => 'Jenis kelamin wajib dipilih (Laki-Laki atau Perempuan).',
            'gender.in'                    => 'Pilihan jenis kelamin harus L (Laki-Laki) atau P (Perempuan).',
            'place_of_birth.required'      => 'Tempat lahir wajib diisi.',
            'date_of_birth.required'       => 'Tanggal lahir wajib diisi.',
            'job_category.required'        => 'Pekerjaan / status kategori wajib dipilih.',
            'institution_name.required'    => 'Nama instansi atau sekolah wajib diisi.',
            'phone_number.required'        => 'Nomor HP yang terdaftar WhatsApp wajib diisi.',
            'ktp_address.required'         => 'Alamat sesuai KTP wajib diisi.',
            'residential_address.required' => 'Alamat tempat tinggal / domisili saat ini wajib diisi.',
            'email.unique'                 => 'Email ini telah digunakan oleh anggota lain.',
            'member_no.unique'             => 'Nomor anggota ini sudah terdaftar.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Normalise Job Category
        $rawCategory = strtoupper(trim($validated['job_category']));
        if (str_contains($rawCategory, 'PELAJAR')) {
            $jobCategory = 'PELAJAR';
        } elseif (str_contains($rawCategory, 'MAHASISWA')) {
            $jobCategory = 'MAHASISWA';
        } else {
            $jobCategory = 'UMUM';
        }
        // Maksimal peminjaman buku baku 3 buku untuk semua anggota
        $maxLoans = 3;

        // Determine or generate member_no
        $memberNo = !empty($validated['member_no']) 
            ? trim($validated['member_no']) 
            : Member::generateAtlasMemberNo();

        // Handle Photo: file upload or base64 data url from camera
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            if ($file->isValid()) {
                $filename = 'mbr_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                Storage::disk('public')->put('photos/members/' . $filename, file_get_contents($file->getPathname()));
                $photoPath = '/storage/photos/members/' . $filename;
            }
        } elseif (!empty($request->photo_base64) && is_string($request->photo_base64)) {
            // Camera snapshot base64 data URI
            $base64 = $request->photo_base64;
            if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
                $base64Data = substr($base64, strpos($base64, ',') + 1);
                $decoded = base64_decode($base64Data);
                if ($decoded !== false) {
                    $ext = strtolower($type[1]) === 'png' ? 'png' : 'jpg';
                    $filename = 'cam_' . time() . '_' . Str::random(8) . '.' . $ext;
                    Storage::disk('public')->put('photos/members/' . $filename, $decoded);
                    $photoPath = '/storage/photos/members/' . $filename;
                }
            }
        }

        // Email: Tidak auto-generate. Jika kosong simpan null
        $email = !empty($validated['email']) ? trim($validated['email']) : null;

        // Valid until date (5 years from creation)
        $validUntil = now()->addYears(5)->format('Y-m-d');

        $member = Member::create([
            'member_no'           => $memberNo,
            'nik'                 => $validated['nik'] ?? null,
            'name'                => $validated['name'],
            'gender'              => $validated['gender'],
            'place_of_birth'      => $validated['place_of_birth'],
            'date_of_birth'       => $validated['date_of_birth'],
            'job_category'        => $jobCategory,
            'institution_name'    => $validated['institution_name'],
            'max_loans'           => $maxLoans,
            'phone_number'        => $validated['phone_number'],
            'email'               => $email,
            'password'            => Hash::make('atlas123456'),
            'ktp_address'         => $validated['ktp_address'],
            'residential_address' => $validated['residential_address'],
            'photo_path'          => $photoPath,
            'status'              => $validated['status'] ?? 'Aktif Bebas Pustaka',
            'valid_until'         => $validUntil,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Anggota {$member->name} ({$member->member_no}) berhasil didaftarkan!",
            'data'    => new MemberResource($member),
        ], 201);
    }

    /**
     * Show single member detail
     */
    public function show(int $id): JsonResponse
    {
        $member = Member::with(['loans', 'guestbooks', 'onsiteReadings'])->find($id);
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Data anggota tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new MemberResource($member),
        ]);
    }

    /**
     * Update existing member
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $member = Member::find($id);
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Data anggota tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'                => ['required', 'string', 'max:255'],
            'gender'              => ['required', 'string', 'in:L,P'],
            'place_of_birth'      => ['required', 'string', 'max:255'],
            'date_of_birth'       => ['required', 'date'],
            'job_category'        => ['required', 'string'],
            'institution_name'    => ['required', 'string', 'max:255'],
            'phone_number'        => ['required', 'string', 'max:30'],
            'ktp_address'         => ['required', 'string'],
            'residential_address' => ['required', 'string'],
            'nik'                 => ['nullable', 'string', 'max:30'],
            'email'               => ['nullable', 'email', 'max:255', 'unique:members,email,' . $id],
            'status'              => ['nullable', 'string', 'max:50'],
        ], [
            'name.required'                => 'Nama lengkap wajib diisi sesuai identitas resmi.',
            'gender.required'              => 'Jenis kelamin wajib dipilih (Laki-Laki atau Perempuan).',
            'gender.in'                    => 'Pilihan jenis kelamin harus L (Laki-Laki) atau P (Perempuan).',
            'place_of_birth.required'      => 'Tempat lahir wajib diisi.',
            'date_of_birth.required'       => 'Tanggal lahir wajib diisi.',
            'job_category.required'        => 'Pekerjaan / status kategori wajib dipilih.',
            'institution_name.required'    => 'Nama instansi atau sekolah wajib diisi.',
            'phone_number.required'        => 'Nomor HP yang terdaftar WhatsApp wajib diisi.',
            'ktp_address.required'         => 'Alamat sesuai KTP wajib diisi.',
            'residential_address.required' => 'Alamat tempat tinggal / domisili saat ini wajib diisi.',
            'email.unique'                 => 'Email ini telah digunakan oleh anggota lain.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $rawCategory = strtoupper(trim($validated['job_category']));
        if (str_contains($rawCategory, 'PELAJAR')) {
            $jobCategory = 'PELAJAR';
        } elseif (str_contains($rawCategory, 'MAHASISWA')) {
            $jobCategory = 'MAHASISWA';
        } else {
            $jobCategory = 'UMUM';
        }
        // Maksimal peminjaman buku baku 3 buku untuk semua anggota
        $maxLoans = 3;

        // Handle Photo update if provided
        $photoPath = $member->photo_path;
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            if ($file->isValid()) {
                $filename = 'mbr_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
                Storage::disk('public')->put('photos/members/' . $filename, file_get_contents($file->getPathname()));
                $photoPath = '/storage/photos/members/' . $filename;
            }
        } elseif (!empty($request->photo_base64) && is_string($request->photo_base64)) {
            $base64 = $request->photo_base64;
            if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
                $base64Data = substr($base64, strpos($base64, ',') + 1);
                $decoded = base64_decode($base64Data);
                if ($decoded !== false) {
                    $ext = strtolower($type[1]) === 'png' ? 'png' : 'jpg';
                    $filename = 'cam_' . time() . '_' . Str::random(8) . '.' . $ext;
                    Storage::disk('public')->put('photos/members/' . $filename, $decoded);
                    $photoPath = '/storage/photos/members/' . $filename;
                }
            }
        }

        $memberData = [
            'name'                => $validated['name'],
            'gender'              => $validated['gender'],
            'nik'                 => $validated['nik'] ?? $member->nik,
            'place_of_birth'      => $validated['place_of_birth'],
            'date_of_birth'       => $validated['date_of_birth'],
            'job_category'        => $jobCategory,
            'institution_name'    => $validated['institution_name'],
            'max_loans'           => $maxLoans,
            'phone_number'        => $validated['phone_number'],
            'ktp_address'         => $validated['ktp_address'],
            'residential_address' => $validated['residential_address'],
            'photo_path'          => $photoPath,
            'status'              => $validated['status'] ?? $member->status,
        ];

        if ($request->has('email')) {
            $memberData['email'] = !empty($validated['email']) ? trim($validated['email']) : null;
        }

        $member->update($memberData);

        return response()->json([
            'success' => true,
            'message' => "Data anggota {$member->name} berhasil diperbarui.",
            'data'    => new MemberResource($member),
        ]);
    }

    /**
     * Delete single member with validation & confirmation support
     */
    public function destroy(int $id): JsonResponse
    {
        $member = Member::find($id);
        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Anggota tidak ditemukan.',
            ], 404);
        }

        // Check if member has active loans
        $activeLoansCount = $member->loans()
            ->whereIn('loan_status', ['BORROWED', 'PENDING_RETURN', 'WAITING_PAYMENT', 'EXTENDED'])
            ->count();

        if ($activeLoansCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Tidak dapat menghapus anggota {$member->name}. Anggota masih memiliki {$activeLoansCount} peminjaman aktif yang belum dikembalikan.",
            ], 422);
        }

        $name = $member->name;
        $memberNo = $member->member_no;
        $member->delete();

        return response()->json([
            'success' => true,
            'message' => "Anggota {$name} ({$memberNo}) berhasil dihapus.",
        ]);
    }

    /**
     * Bulk Delete selected members
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih setidaknya satu anggota untuk dihapus.',
            ], 422);
        }

        $members = Member::whereIn('id', $ids)->get();
        $deletedCount = 0;
        $skipped = [];

        foreach ($members as $m) {
            $hasActiveLoans = $m->loans()
                ->whereIn('loan_status', ['BORROWED', 'PENDING_RETURN', 'WAITING_PAYMENT', 'EXTENDED'])
                ->exists();

            if ($hasActiveLoans) {
                $skipped[] = "{$m->name} ({$m->member_no}) - memiliki pinjaman aktif";
                continue;
            }

            $m->delete();
            $deletedCount++;
        }

        $message = "Berhasil menghapus {$deletedCount} anggota.";
        if (count($skipped) > 0) {
            $message .= " " . count($skipped) . " anggota dilewati karena memiliki peminjaman aktif.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'deleted_count' => $deletedCount,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Export members list to CSV / Excel
     */
    public function export(Request $request)
    {
        $members = Member::orderBy('id', 'desc')->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="data_anggota_dapd_buleleng_' . date('Ymd_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($members) {
            $handle = fopen('php://output', 'w');
            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'No. Anggota',
                'NIK / NISN',
                'Nama Lengkap',
                'Jenis Kelamin',
                'Tempat Lahir',
                'Tanggal Lahir',
                'Pekerjaan / Kategori',
                'Instansi / Sekolah',
                'Nomor WhatsApp',
                'Email',
                'Alamat Sesuai KTP',
                'Alamat Domisili',
                'Status Keanggotaan',
                'Masa Berlaku Sampai',
                'Tanggal Terdaftar',
            ]);

            foreach ($members as $m) {
                $validUntil = $m->valid_until ? $m->valid_until->format('d-m-Y') : date('d-m-Y', strtotime('+5 years', strtotime($m->created_at)));
                fputcsv($handle, [
                    $m->member_no,
                    $m->nik ?: '-',
                    $m->name,
                    $m->gender === 'P' ? 'Perempuan' : 'Laki-laki',
                    $m->place_of_birth ?: '-',
                    $m->date_of_birth ? $m->date_of_birth->format('d-m-Y') : '-',
                    $m->job_category,
                    $m->institution_name ?: '-',
                    $m->phone_number ?: '-',
                    $m->email ?: '-',
                    $m->ktp_address ?: '-',
                    $m->residential_address ?: '-',
                    $m->status ?: 'Aktif Bebas Pustaka',
                    $validUntil,
                    $m->created_at ? $m->created_at->format('d-m-Y H:i') : '-',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
