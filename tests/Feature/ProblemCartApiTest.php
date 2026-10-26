<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Member;
use App\Models\MemberCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProblemCartApiTest extends TestCase
{
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Cari atau buat admin untuk otentikasi API
        $this->admin = Admin::first() ?? Admin::create([
            'username'  => 'admin_test',
            'email'     => 'admin_test@buleleng.go.id',
            'password'  => bcrypt('password123'),
            'full_name' => 'Admin Penguji Sirkulasi',
            'role'      => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin, ['*']);
    }

    /**
     * Test fetching problem cart list with stats.
     */
    public function test_can_fetch_problem_carts_list()
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/admin/problem-carts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'stats' => [
                    'total_cases',
                    'unique_members',
                    'umum_count',
                    'pelajar_count',
                    'mahasiswa_count',
                    'status_breakdown',
                ],
            ]);
    }

    /**
     * Test creating a problem case for a member.
     */
    public function test_can_create_problem_case()
    {
        $member = Member::first() ?? Member::create([
            'member_no'    => 'AGT-TEST-0001',
            'name'         => 'Wayan Tester',
            'email'        => 'wayan@test.com',
            'job_category' => 'PELAJAR',
            'status'       => 'Aktif',
        ]);

        $payload = [
            'member_id'          => $member->id,
            'problem_type'       => 'KOLEKSI_RUSAK',
            'item_title'         => 'Buku Uji Kasus Sirkulasi',
            'item_barcode'       => 'EK-TEST-999',
            'fine_amount'        => 50000,
            'paid_amount'        => 10000,
            'action_status'      => 'DALAM_MEDIASI',
            'action_notes'       => 'Cicilan perbaikan buku rusak',
            'description'        => 'Halaman robek di bagian bab 3.',
            'late_duration_text' => 'Rusak Ringan',
        ];

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/admin/problem-carts', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.problem_type', 'KOLEKSI_RUSAK')
            ->assertJsonPath('data.remaining_debt', 40000);

        $caseId = $response->json('data.id');

        // Test followup action (e.g. paying second installment)
        $followupResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/v1/admin/problem-carts/{$caseId}/followup", [
                'action'        => 'Pembayaran Cicilan 2',
                'officer'       => 'Petugas Kasir',
                'action_status' => 'DALAM_MEDIASI',
                'payment_added' => 20000,
                'notes'         => 'Pembayaran cicilan ganti rugi sebesar Rp 20.000',
            ]);

        $followupResponse->assertStatus(200)
            ->assertJsonPath('data.paid_amount', 30000)
            ->assertJsonPath('data.remaining_debt', 20000);

        // Test resolving the case
        $resolveResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/v1/admin/problem-carts/{$caseId}/resolve", [
                'officer' => 'Ketua Layanan Sirkulasi',
                'notes'   => 'Kasus diselesaikan setelah penggantian disetujui.',
            ]);

        $resolveResponse->assertStatus(200)
            ->assertJsonPath('data.is_resolved', true);

        // Test member problem history
        $historyResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/v1/admin/members/{$member->id}/problem-history");

        $historyResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'summary' => [
                    'active_cases_count',
                    'resolved_cases_count',
                    'total_fines',
                    'total_paid',
                ],
            ]);
    }

    /**
     * Test sync circulation and bulk actions.
     */
    public function test_can_sync_circulation_and_bulk_action()
    {
        // 1. Sync Circulation
        $syncResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/admin/problem-carts/sync-circulation');

        $syncResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // 2. Bulk Action (e.g. resolve cases or set status)
        $caseIds = MemberCart::pluck('id')->take(2)->toArray();

        if (!empty($caseIds)) {
            $bulkResponse = $this->withHeaders(['Accept' => 'application/json'])
                ->postJson('/api/v1/admin/problem-carts/bulk-action', [
                    'action' => 'FREEZE',
                    'ids'    => $caseIds,
                ]);

            $bulkResponse->assertStatus(200)
                ->assertJsonPath('success', true);
        }
    }
}

