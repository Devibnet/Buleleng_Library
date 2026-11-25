<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\ImpersonationSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImpersonateUserWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function createAdmin(string $role = 'admin', array $attributes = []): Admin
    {
        return Admin::create(array_merge([
            'username'  => 'user_' . Str::random(8),
            'password'  => Hash::make('Secret123!'),
            'name'      => 'Staf ' . ucfirst($role),
            'email'     => 'staff_' . Str::random(8) . '@buleleng.go.id',
            'role'      => $role,
            'is_active' => true,
        ], $attributes));
    }

    public function test_super_admin_can_impersonate_operator_with_valid_reason(): void
    {
        $superAdmin = $this->createAdmin('super_admin', ['name' => 'Superadmin Angga']);
        $operator = $this->createAdmin('operator', ['name' => 'Operator Made']);

        $reason = 'Pemeriksaan kendala transaksi peminjaman di loket sirkulasi';

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/access/users/{$operator->id}/impersonate", [
                'reason' => $reason,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.user.id', $operator->id);
        $response->assertJsonPath('data.user.role', 'operator');
        $response->assertJsonPath('data.impersonation.super_admin.id', $superAdmin->id);
        $response->assertJsonPath('data.impersonation.target.id', $operator->id);
        $response->assertJsonPath('data.impersonation.reason', $reason);
        $this->assertNotEmpty($response->json('data.token'));

        $sessionId = $response->json('data.impersonation.session_id');

        // Pastikan record sesi di database dibuat
        $session = ImpersonationSession::where('session_id', $sessionId)->first();
        $this->assertNotNull($session);
        $this->assertEquals(ImpersonationSession::STATUS_ACTIVE, $session->status);
        $this->assertEquals($superAdmin->id, $session->super_admin_id);
        $this->assertEquals($operator->id, $session->target_admin_id);
        $this->assertEquals($reason, $session->reason);

        // Pastikan tercatat di Audit Log
        $log = AdminActivityLog::where('action', 'impersonate_start')
            ->where('impersonated_session_id', $sessionId)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($superAdmin->id, $log->actor_id);
        $this->assertEquals($operator->id, $log->target_id);
        $this->assertEquals($reason, $log->impersonation_reason);
    }

    public function test_impersonate_requires_reason_with_min_5_characters(): void
    {
        $superAdmin = $this->createAdmin('super_admin');
        $operator = $this->createAdmin('operator');

        // Tanpa alasan
        $res1 = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/access/users/{$operator->id}/impersonate", []);
        $res1->assertStatus(422);
        $res1->assertJsonValidationErrors(['reason']);

        // Alasan terlalu pendek (< 5 karakter)
        $res2 = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/access/users/{$operator->id}/impersonate", [
                'reason' => 'tes',
            ]);
        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['reason']);
    }

    public function test_cannot_impersonate_self(): void
    {
        $superAdmin = $this->createAdmin('super_admin');

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/access/users/{$superAdmin->id}/impersonate", [
                'reason' => 'Mencoba impersonasi akun sendiri',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_cannot_impersonate_another_super_admin(): void
    {
        $superAdmin1 = $this->createAdmin('super_admin');
        $superAdmin2 = $this->createAdmin('super_admin');

        $response = $this->actingAs($superAdmin1, 'sanctum')
            ->postJson("/api/v1/admin/access/users/{$superAdmin2->id}/impersonate", [
                'reason' => 'Mencoba impersonasi sesama super admin',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_cannot_impersonate_inactive_user(): void
    {
        $superAdmin = $this->createAdmin('super_admin');
        $operator = $this->createAdmin('operator', ['is_active' => false]);

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/access/users/{$operator->id}/impersonate", [
                'reason' => 'Mencoba impersonasi akun nonaktif',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_non_super_admin_cannot_impersonate(): void
    {
        $admin = $this->createAdmin('admin');
        $operator = $this->createAdmin('operator');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/access/users/{$operator->id}/impersonate", [
                'reason' => 'Admin mencoba impersonasi',
            ]);

        $response->assertStatus(403);
    }

    public function test_can_stop_impersonation_and_revokes_token(): void
    {
        $superAdmin = $this->createAdmin('super_admin');
        $operator = $this->createAdmin('operator');

        $impersonateRes = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/access/users/{$operator->id}/impersonate", [
                'reason' => 'Pengujian verifikasi hak akses operator',
            ]);

        $impersonateRes->assertStatus(200);
        $sessionId = $impersonateRes->json('data.impersonation.session_id');
        $token = $impersonateRes->json('data.token');

        // Panggil stop impersonation menggunakan token sesi impersonasi
        $stopRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/access/stop-impersonation', [
                'session_id' => $sessionId,
            ]);

        $stopRes->assertStatus(200);
        $stopRes->assertJsonPath('success', true);

        // Verifikasi sesi di database diakhiri
        $session = ImpersonationSession::where('session_id', $sessionId)->first();
        $this->assertEquals(ImpersonationSession::STATUS_ENDED, $session->status);
        $this->assertNotNull($session->ended_at);

        // Verifikasi log impersonate_stop dicatat
        $stopLog = AdminActivityLog::where('action', 'impersonate_stop')
            ->where('impersonated_session_id', $sessionId)
            ->first();
        $this->assertNotNull($stopLog);
        $this->assertEquals($superAdmin->id, $stopLog->actor_id);
        $this->assertEquals($operator->id, $stopLog->target_id);
    }

    public function test_activity_during_impersonation_attributes_super_admin_in_audit_log(): void
    {
        $superAdmin = $this->createAdmin('super_admin');
        $operator = $this->createAdmin('operator');

        $impersonateRes = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/access/users/{$operator->id}/impersonate", [
                'reason' => 'Perbaikan data petugas oleh super admin',
            ]);

        $token = $impersonateRes->json('data.token');
        $sessionId = $impersonateRes->json('data.impersonation.session_id');

        // Melalui token impersonasi, ubah data diri operator
        $updateRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/admin/access/users/{$operator->id}", [
                'name'     => 'Operator Made Updated',
                'username' => $operator->username,
                'email'    => $operator->email,
                'role'     => $operator->role,
            ]);

        $updateRes->assertStatus(200);

        // Periksa audit log untuk update akun tersebut
        $updateLog = AdminActivityLog::where('target_id', $operator->id)
            ->where('action', 'account_updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($updateLog);
        // Pelaku sebenarnya harus Superadmin
        $this->assertEquals($superAdmin->id, $updateLog->actor_id);
        $this->assertEquals($superAdmin->id, $updateLog->impersonator_id);
        $this->assertEquals($sessionId, $updateLog->impersonated_session_id);
        $this->assertEquals('Perbaikan data petugas oleh super admin', $updateLog->impersonation_reason);
    }

    public function test_problem_cart_activity_during_impersonation_attributes_super_admin(): void
    {
        $superAdmin = $this->createAdmin('super_admin');
        $targetAdmin = $this->createAdmin('admin', ['name' => 'Admin Utama']);

        $impersonateRes = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/v1/admin/access/users/{$targetAdmin->id}/impersonate", [
                'reason' => 'Uji pencatatan keranjang bermasalah',
            ]);

        $token = $impersonateRes->json('data.token');
        $sessionId = $impersonateRes->json('data.impersonation.session_id');

        $member = \App\Models\Member::first() ?? \App\Models\Member::create([
            'member_no'    => 'AGT-IMP-' . \Illuminate\Support\Str::random(5),
            'name'         => 'Pemustaka Tes',
            'email'        => 'pemustaka_' . \Illuminate\Support\Str::random(5) . '@test.com',
            'job_category' => 'PELAJAR',
            'status'       => 'Aktif',
        ]);

        // Masukkan kasus ke Keranjang Bermasalah menggunakan token impersonasi
        $createCaseRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/problem-carts', [
                'member_id'    => $member->id,
                'problem_type' => 'BUKU_HILANG',
                'item_title'   => 'Laskar Pelangi',
                'item_barcode' => 'LP-001',
                'description'  => 'Hilang saat dibawa ke luar perpustakaan',
            ]);

        $createCaseRes->assertStatus(201);

        // Pastikan tercatat di AdminActivityLog dengan modul Keranjang Bermasalah
        $log = AdminActivityLog::where('action', 'problem_cart_created')
            ->where('impersonated_session_id', $sessionId)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Keranjang Bermasalah', $log->module);
        $this->assertEquals($superAdmin->id, $log->actor_id);
        $this->assertEquals($superAdmin->id, $log->impersonator_id);
        $this->assertEquals($sessionId, $log->impersonated_session_id);
        $this->assertEquals('Uji pencatatan keranjang bermasalah', $log->impersonation_reason);

        // Verifikasi endpoint activity-logs memformat log tanpa baris kosong
        $logsRes = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/access/activity-logs');

        $logsRes->assertStatus(200);
        $items = collect($logsRes->json('data'));

        // Cek log impersonate_stop tidak memiliki row kosong '-' pada 'Sebelum'/'Sesudah'
        $stopLogItem = $items->firstWhere('action', 'impersonate_stop');
        if ($stopLogItem) {
            $this->assertEmpty($stopLogItem['changes'], 'Log impersonate_stop tidak boleh memiliki baris Sebelum/Sesudah kosong');
        }
    }
}
