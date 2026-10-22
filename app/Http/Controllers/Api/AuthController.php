<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Admin;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/auth/member/login
     */
    public function loginMember(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $member = Member::where('email', $request->email)->first();

        if (! $member || ! Hash::check($request->password, $member->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial email atau password member salah.',
            ], 401);
        }

        Auth::guard('member')->login($member);

        $token = $member->createToken('member-auth-token')->plainTextToken;

        $intendedBookId = session('intended_book_id');
        session()->forget('intended_book_id');

        return response()->json([
            'success'          => true,
            'message'          => 'Login member berhasil.',
            'intended_book_id' => $intendedBookId,
            'token'            => $token,
            'data'             => [
                'token'  => $token,
                'member' => new MemberResource($member),
            ],
        ], 200);
    }

    /**
     * POST /api/auth/admin/login
     */
    public function loginAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial username/email atau password admin salah.',
            ], 401);
        }

        if (! $admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak aktif.',
            ], 403);
        }

        Auth::guard('admin')->login($admin);

        $token = $admin->createToken('admin-auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login admin berhasil.',
            'token'   => $token,
            'data'    => [
                'token' => $token,
                'admin' => [
                    'id'        => $admin->id,
                    'username'  => $admin->username,
                    'name'      => $admin->name,
                    'email'     => $admin->email,
                    'role'      => $admin->role,
                    'is_active' => (bool) $admin->is_active,
                ],
            ],
        ], 200);
    }
}
