<?php

namespace Tests\Feature;

use App\Mail\MemberActivationMail;
use App\Models\Admin;
use App\Models\Member;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberActivationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function createAdmin(string $role = 'admin'): Admin
    {
        return Admin::create([
            'username'  => 'admin_' . Str::random(8),
            'password'  => Hash::make('password123'),
            'name'      => 'Petugas Testing',
            'email'     => 'admin_' . Str::random(8) . '@buleleng.go.id',
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    protected function createMember(array $attributes = []): Member
    {
        $nik = '510801' . rand(1000000000, 9999999999);
        $defaults = [
            'member_no'            => Member::generateAtlasMemberNo(),
            'name'                 => 'Anggota Uji Coba',
            'gender'               => 'L',
            'place_of_birth'       => 'Singaraja',
            'date_of_birth'        => '1998-05-10',
            'job_category'         => 'UMUM',
            'institution_name'     => 'Umum',
            'phone_number'         => '081' . rand(10000000, 99999999),
            'email'                => 'member_' . Str::random(8) . '@example.com',
            'password'             => Hash::make('Secret123!'),
            'ktp_address'          => 'Jl. Ngurah Rai No. 1 Singaraja',
            'residential_address'  => 'Jl. Ngurah Rai No. 1 Singaraja',
            'status'               => 'Aktif Bebas Pustaka',
            'valid_until'          => now()->addYears(5)->format('Y-m-d'),
            'nik'                  => $nik,
        ];

        return Member::create(array_merge($defaults, $attributes));
    }

    public function test_offline_member_registration_dispatches_activation_email_and_does_not_reveal_password(): void
    {
        $admin = $this->createAdmin();

        $nik = '510801' . rand(1000000000, 9999999999);
        $email = strtolower('member_' . Str::random(8) . '@example.com');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/members', [
            'name'                => 'Ketut Test Anggota',
            'place_of_birth'      => 'Singaraja',
            'date_of_birth'       => '2000-01-15',
            'job_category'        => 'UMUM',
            'institution_name'    => 'DAPD Buleleng',
            'phone_number'        => '081234567890',
            'email'               => $email,
            'gender'              => 'L',
            'nik'                 => $nik,
            'ktp_address'         => 'Jl. Wrekudara No. 1 Singaraja',
            'residential_address' => 'Jl. Wrekudara No. 1 Singaraja',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success'           => true,
            'activation_sent'   => true,
            'activation_status' => 'MENUNGGU_AKTIVASI',
        ]);

        // Pastikan tidak ada password yang diungkapkan ke admin
        $this->assertArrayNotHasKey('generated_password', $response->json());

        // Verifikasi di database
        $member = Member::where('email', $email)->first();
        $this->assertNotNull($member);
        $this->assertEquals('MENUNGGU_AKTIVASI', $member->activation_status);
        $this->assertEquals('Aktif Bebas Pustaka', $member->status); // Hak pinjam buku fisik langsung aktif
        $this->assertNotNull($member->activation_token_hash);
        $this->assertNotNull($member->activation_sent_at);
        $this->assertNotNull($member->activation_expires_at);

        // Verifikasi pengiriman email aktivasi
        Mail::assertSent(MemberActivationMail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    public function test_unactivated_member_cannot_login_and_gets_403(): void
    {
        $rawToken = Str::random(64);
        $member = $this->createMember([
            'activation_status'     => 'MENUNGGU_AKTIVASI',
            'activation_token_hash' => Member::hashActivationToken($rawToken),
        ]);

        $response = $this->postJson('/api/auth/member/login', [
            'email'    => $member->email,
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success'           => false,
            'activation_status' => 'MENUNGGU_AKTIVASI',
        ]);
    }

    public function test_verify_activation_token_transitions_status(): void
    {
        $rawToken = Str::random(64);
        $member = $this->createMember([
            'activation_status'     => 'MENUNGGU_AKTIVASI',
            'activation_token_hash' => Member::hashActivationToken($rawToken),
            'activation_expires_at' => now()->addHours(24),
        ]);

        $response = $this->postJson('/api/auth/member/verify-activation-token', [
            'token' => $rawToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data'    => [
                'email' => $member->email,
                'name'  => $member->name,
            ],
        ]);

        $member->refresh();
        $this->assertEquals('MENUNGGU_PASSWORD', $member->activation_status);
        $this->assertNotNull($member->activation_verified_at);
    }

    public function test_expired_activation_token_returns_410(): void
    {
        $rawToken = Str::random(64);
        $this->createMember([
            'activation_status'     => 'MENUNGGU_AKTIVASI',
            'activation_token_hash' => Member::hashActivationToken($rawToken),
            'activation_expires_at' => now()->subHour(),
        ]);

        $response = $this->postJson('/api/auth/member/verify-activation-token', [
            'token' => $rawToken,
        ]);

        $response->assertStatus(410);
        $response->assertJson([
            'success'    => false,
            'is_expired' => true,
        ]);
    }

    public function test_complete_activation_sets_password_and_activates_account(): void
    {
        $rawToken = Str::random(64);
        $member = $this->createMember([
            'activation_status'     => 'MENUNGGU_PASSWORD',
            'activation_token_hash' => Member::hashActivationToken($rawToken),
            'activation_expires_at' => now()->addHours(20),
        ]);

        $response = $this->postJson('/api/auth/member/complete-activation', [
            'token'                 => $rawToken,
            'password'              => 'Singaraja123!',
            'password_confirmation' => 'Singaraja123!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $member->refresh();
        $this->assertEquals('AKTIF', $member->activation_status);
        $this->assertNull($member->activation_token_hash); // Token one-time use dihapus
        $this->assertTrue(Hash::check('Singaraja123!', $member->password));

        // Anggota kini dapat login menggunakan password barunya
        $loginRes = $this->postJson('/api/auth/member/login', [
            'email'    => $member->email,
            'password' => 'Singaraja123!',
        ]);

        $loginRes->assertStatus(200);
        $loginRes->assertJson([
            'success' => true,
        ]);
    }

    public function test_admin_resend_activation_email(): void
    {
        $admin = $this->createAdmin();
        $member = $this->createMember([
            'activation_status' => 'MENUNGGU_AKTIVASI',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/members/{$member->id}/resend-activation");

        $response->assertStatus(200);
        $response->assertJson([
            'success'         => true,
            'activation_sent' => true,
        ]);

        Mail::assertSent(MemberActivationMail::class);
    }

    public function test_admin_change_member_email_requires_identity_confirmation(): void
    {
        $admin = $this->createAdmin();
        $member = $this->createMember([
            'activation_status' => 'GAGAL_DIKIRIM',
        ]);

        $newEmail = strtolower('new_' . Str::random(8) . '@example.com');

        // Coba ubah email tanpa konfirmasi identitas fisik
        $failResponse = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/admin/members/{$member->id}/change-email", [
                'email'              => $newEmail,
                'identity_confirmed' => false,
            ]);

        $failResponse->assertStatus(422);

        // Ubah email dengan konfirmasi identitas fisik
        $successResponse = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/admin/members/{$member->id}/change-email", [
                'email'              => $newEmail,
                'identity_confirmed' => true,
                'notes'              => 'Salah ketik saat entri di loket',
            ]);

        $successResponse->assertStatus(200);
        $successResponse->assertJson([
            'success'           => true,
            'activation_status' => 'MENUNGGU_AKTIVASI',
        ]);

        $member->refresh();
        $this->assertEquals($newEmail, $member->email);
        $this->assertEquals('MENUNGGU_AKTIVASI', $member->activation_status);
        $this->assertNotNull($member->activation_token_hash);

        Mail::assertSent(MemberActivationMail::class, function ($mail) use ($newEmail) {
            return $mail->hasTo($newEmail);
        });
    }
}
