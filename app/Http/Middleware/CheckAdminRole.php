<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $admin = Auth::guard('admin')->user() ?? Auth::guard('sanctum')->user() ?? $request->user();

        $isApiRequest = $request->is('api/*') || $request->expectsJson();

        // 1. Cek Autentikasi Admin
        if (! $admin || ! ($admin instanceof \App\Models\Admin)) {
            if ($isApiRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
                ], 401);
            }

            return redirect('/admin/login')->with('error', 'Silakan login terlebih dahulu.');
        }

        // 2. Cek status is_active: jika false, batalkan akses, logout, dan tampilkan pesan "Akun Anda tidak aktif"
        if (! $admin->is_active) {
            if (Auth::guard('admin')->check()) {
                Auth::guard('admin')->logout();
            }

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($isApiRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda tidak aktif.',
                ], 403);
            }

            return redirect('/admin/login')->with('error', 'Akun Anda tidak aktif.');
        }

        // 3. Cek apakah nilai kolom role sesuai dengan parameter role yang dipasang di route
        if (! empty($roles)) {
            $allowedRoles = [];
            foreach ($roles as $role) {
                $allowedRoles = array_merge($allowedRoles, explode(',', $role));
            }

            if (! in_array($admin->role, $allowedRoles)) {
                if ($isApiRequest) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki hak akses ke resource ini.',
                    ], 403);
                }

                abort(403, 'Anda tidak memiliki hak akses ke resource ini.');
            }
        }

        return $next($request);
    }
}
