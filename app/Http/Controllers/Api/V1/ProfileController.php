<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Profile Management",
 *     description="API Pengelolaan Profil Member Perpustakaan"
 * )
 */
class ProfileController extends Controller
{
    /**
     * Get authenticated member model instance.
     */
    protected function getAuthenticatedMember(Request $request): ?Member
    {
        /** @var Member|null $member */
        $member = $request->user();

        if (! $member) {
            $member = Auth::guard('sanctum')->user() ?? Auth::guard('member')->user();
        }

        return $member;
    }

    /**
     * Format member model attributes for API response.
     */
    protected function formatMemberData(Member $member): array
    {
        $photoUrl = null;
        if ($member->photo_path) {
            $photoUrl = asset('storage/' . $member->photo_path);
        } elseif ($member->avatar) {
            $photoUrl = $member->avatar;
        }

        return [
            'id'                  => $member->id,
            'member_no'           => $member->member_no,
            'name'                => $member->name,
            'residential_address' => $member->residential_address,
            'ktp_address'         => $member->ktp_address,
            'place_of_birth'      => $member->place_of_birth,
            'date_of_birth'       => $member->date_of_birth ? $member->date_of_birth->format('Y-m-d') : null,
            'job_category'        => $member->job_category,
            'institution_name'    => $member->institution_name,
            'phone_number'        => $member->phone_number,
            'email'               => $member->email,
            'photo_path'          => $member->photo_path,
            'photo_url'           => $photoUrl,
            'google_id'           => $member->google_id,
            'avatar'              => $member->avatar,
            'created_at'          => $member->created_at ? $member->created_at->toIso8601String() : null,
            'updated_at'          => $member->updated_at ? $member->updated_at->toIso8601String() : null,
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/profile",
     *     summary="Mengambil data profil member yang sedang login",
     *     description="Mengembalikan detail informasi akun member berdasarkan token autentikasi Sanctum.",
     *     operationId="getMemberProfile",
     *     tags={"Profile Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil profil member",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data profil member berhasil diambil."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="member_no", type="string", example="MEM-202609-0001"),
     *                 @OA\Property(property="name", type="string", example="Budi Santoso"),
     *                 @OA\Property(property="residential_address", type="string", example="Jl. Ngurah Rai No. 10, Singaraja"),
     *                 @OA\Property(property="ktp_address", type="string", example="Jl. Ngurah Rai No. 10, Singaraja"),
     *                 @OA\Property(property="place_of_birth", type="string", example="Buleleng"),
     *                 @OA\Property(property="date_of_birth", type="string", format="date", example="1998-05-15"),
     *                 @OA\Property(property="job_category", type="string", example="MAHASISWA"),
     *                 @OA\Property(property="institution_name", type="string", example="Universitas Pendidikan Ganesha"),
     *                 @OA\Property(property="phone_number", type="string", example="081234567890"),
     *                 @OA\Property(property="email", type="string", example="budi@example.com"),
     *                 @OA\Property(property="photo_path", type="string", nullable=true, example="profile-photos/abc123.jpg"),
     *                 @OA\Property(property="photo_url", type="string", nullable=true, example="http://localhost:8000/storage/profile-photos/abc123.jpg"),
     *                 @OA\Property(property="google_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="avatar", type="string", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", example="2026-09-17T00:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", example="2026-10-07T08:00:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated / Token tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $member = $this->getAuthenticatedMember($request);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data profil member berhasil diambil.',
            'data'    => $this->formatMemberData($member),
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/profile",
     *     summary="Memperbarui data teks profil member",
     *     description="Memperbarui informasi biodata member yang sedang login (name, address, place & date of birth, job, institution, phone, email).",
     *     operationId="updateMemberProfile",
     *     tags={"Profile Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email"},
     *             @OA\Property(property="name", type="string", example="Budi Santoso Updated"),
     *             @OA\Property(property="residential_address", type="string", example="Jl. Veteran No. 45, Singaraja"),
     *             @OA\Property(property="ktp_address", type="string", example="Jl. Veteran No. 45, Singaraja"),
     *             @OA\Property(property="place_of_birth", type="string", example="Singaraja"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1998-05-15"),
     *             @OA\Property(property="job_category", type="string", enum={"UMUM", "PELAJAR", "MAHASISWA"}, example="MAHASISWA"),
     *             @OA\Property(property="institution_name", type="string", example="Universitas Udayana"),
     *             @OA\Property(property="phone_number", type="string", example="081987654321"),
     *             @OA\Property(property="email", type="string", format="email", example="budi.updated@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profil berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data profil member berhasil diperbarui."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The email has already been taken."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $member = $this->getAuthenticatedMember($request);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'residential_address' => ['nullable', 'string'],
            'ktp_address'         => ['nullable', 'string'],
            'place_of_birth'      => ['nullable', 'string', 'max:255'],
            'date_of_birth'       => ['nullable', 'date'],
            'job_category'        => ['nullable', Rule::in(['UMUM', 'PELAJAR', 'MAHASISWA'])],
            'institution_name'    => ['nullable', 'string', 'max:255'],
            'phone_number'        => ['nullable', 'string', 'max:30'],
            'email'               => ['required', 'email', 'max:255', Rule::unique('members', 'email')->ignore($member->id)],
            'photo'               => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        if ($request->hasFile('photo')) {
            if ($member->photo_path && Storage::disk('public')->exists($member->photo_path)) {
                Storage::disk('public')->delete($member->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('profile-photos', 'public');
            unset($validated['photo']);
        }

        $member->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data profil member berhasil diperbarui.',
            'data'    => $this->formatMemberData($member->fresh()),
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/profile/photo",
     *     summary="Mengunggah / memperbarui foto profil member",
     *     description="Mengunggah berkas gambar foto profil baru, menghapus foto lama jika ada, dan memperbarui kolom photo_path.",
     *     operationId="updateMemberProfilePhoto",
     *     tags={"Profile Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"photo"},
     *                 @OA\Property(
     *                     property="photo",
     *                     description="Berkas foto profil (jpeg, png, jpg, webp, maks 2MB)",
     *                     type="string",
     *                     format="binary"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Foto profil berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Foto profil berhasil diperbarui."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi berkas foto gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The photo field is required."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $member = $this->getAuthenticatedMember($request);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $fileKey = $request->hasFile('photo') ? 'photo' : ($request->hasFile('image') ? 'image' : 'photo');

        $request->validate([
            $fileKey => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        $file = $request->file($fileKey);

        if ($member->photo_path && Storage::disk('public')->exists($member->photo_path)) {
            Storage::disk('public')->delete($member->photo_path);
        }

        $newPhotoPath = $file->store('profile-photos', 'public');

        $member->update([
            'photo_path' => $newPhotoPath,
        ]);

        $updatedMember = $member->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui.',
            'data'    => $this->formatMemberData($updatedMember),
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/profile/password",
     *     summary="Mengubah password akun member",
     *     description="Mengubah password member yang sedang login dengan memverifikasi current_password dan konfirmasi password baru (password_confirmation).",
     *     operationId="updateMemberPassword",
     *     tags={"Profile Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "password", "password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="secret123"),
     *             @OA\Property(property="password", type="string", format="password", example="newsecret123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newsecret123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password akun berhasil diperbarui.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal / Password lama salah",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Password saat ini tidak cocok.")
     *         )
     *     )
     * )
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $member = $this->getAuthenticatedMember($request);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $member->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini tidak cocok.',
                'errors'  => [
                    'current_password' => ['Password saat ini tidak cocok.'],
                ],
            ], 422);
        }

        $member->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password akun berhasil diperbarui.',
        ], 200);
    }
}
