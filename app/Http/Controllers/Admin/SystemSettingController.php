<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Pengaturan Sistem",
 *     description="API Modul Pengaturan Umum Sistem Perpustakaan (System Settings)"
 * )
 */
class SystemSettingController extends Controller
{
    protected SystemSettingService $settingService;

    public function __construct(SystemSettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * @OA\Get(
     *     path="/admin/settings",
     *     summary="Daftar Pengaturan Umum Sistem dikelompokkan per Tab Group",
     *     description="Menampilkan seluruh konfigurasi sistem perpustakaan berdasarkan role admin.",
     *     operationId="getSystemSettings",
     *     tags={"Pengaturan Sistem"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Daftar pengaturan berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar pengaturan umum berhasil diambil."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Akses ditolak (Operator)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Akses ditolak. Role Operator tidak memiliki izin untuk mengelola Pengaturan Umum.")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin || $admin->role === 'operator') {
            return $this->errorResponse($request, 'Akses ditolak. Role Operator tidak memiliki izin untuk mengelola Pengaturan Umum.', 403);
        }

        try {
            $settingsGrouped = $this->settingService->getSettingsGrouped($admin->role);

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Daftar pengaturan umum berhasil diambil.',
                    'data'    => $settingsGrouped,
                ], 200);
            }

            return view('admin.settings.index', [
                'settingsGrouped' => $settingsGrouped,
                'role'            => $admin->role,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($request, $e->getMessage(), 403);
        }
    }

    /**
     * @OA\Put(
     *     path="/admin/settings",
     *     summary="Update nilai Pengaturan Umum & Flush Cache",
     *     description="Menyimpan perubahan nilai konfigurasi berdasarkan key dan otomatis membesihkan cache system_settings.",
     *     operationId="updateSystemSettings",
     *     tags={"Pengaturan Sistem"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="settings",
     *                 type="object",
     *                 example={
     *                     "loan_duration_days": "7",
     *                     "max_borrow_qty": "3",
     *                     "fine_per_day": "1000",
     *                     "terms_and_conditions": "Syarat & ketentuan terbaru..."
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pengaturan berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pengaturan umum berhasil diperbarui dan cache telah dibersihkan."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Akses ditolak (Operator / Restricted Group for Admin)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Akses ditolak. Role Operator tidak memiliki izin untuk mengelola Pengaturan Umum.")
     *         )
     *     )
     * )
     */
    public function update(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin || $admin->role === 'operator') {
            return $this->errorResponse($request, 'Akses ditolak. Role Operator tidak memiliki izin untuk mengelola Pengaturan Umum.', 403);
        }

        try {
            $input = $request->input('settings', $request->all());
            $updatedSettings = $this->settingService->updateSettings($input, $admin->role);

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pengaturan umum berhasil diperbarui dan cache telah dibersihkan.',
                    'data'    => $updatedSettings,
                ], 200);
            }

            return redirect()->back()->with('success', 'Pengaturan umum berhasil diperbarui!');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse($request, $e->getMessage(), 403);
        } catch (\Throwable $e) {
            return $this->errorResponse($request, 'Gagal memperbarui pengaturan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper response untuk error
     */
    protected function errorResponse(Request $request, string $message, int $code = 400)
    {
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $code);
        }

        abort($code, $message);
    }
}
