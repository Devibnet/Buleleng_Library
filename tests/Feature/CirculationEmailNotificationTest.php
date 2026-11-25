<?php

namespace Tests\Feature;

use App\Mail\LoanDueDateNotificationMail;
use App\Mail\ReservationReadyForPickupMail;
use App\Models\Admin;
use App\Models\Catalog;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Member;
use App\Services\CirculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CirculationEmailNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected CirculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->service = app(CirculationService::class);
    }

    protected function createAdmin(string $role = 'admin'): Admin
    {
        return Admin::create([
            'username'  => 'admin_' . Str::random(8),
            'password'  => Hash::make('Secret123!'),
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
            'name'                 => 'Pemustaka Uji',
            'gender'               => 'L',
            'place_of_birth'       => 'Singaraja',
            'date_of_birth'        => '1995-08-17',
            'job_category'         => 'UMUM',
            'institution_name'     => 'Umum',
            'phone_number'         => '081' . rand(10000000, 99999999),
            'email'                => 'pemustaka_' . Str::random(8) . '@example.com',
            'password'             => Hash::make('Singaraja123!'),
            'ktp_address'          => 'Jl. Wrekudara No. 1 Singaraja',
            'residential_address'  => 'Jl. Wrekudara No. 1 Singaraja',
            'status'               => 'Aktif Bebas Pustaka',
            'activation_status'    => 'AKTIF',
            'valid_until'          => now()->addYears(3)->format('Y-m-d'),
            'nik'                  => $nik,
        ];

        return Member::create(array_merge($defaults, $attributes));
    }

    protected function createCatalogAndCollection(): array
    {
        $catalog = Catalog::create([
            'title'            => 'Babad Buleleng dan Sejarah Ki Barak Panji Sakti',
            'author'           => 'Tim Sejarah Buleleng',
            'publisher'        => 'DAPD Kab. Buleleng Press',
            'publish_year'     => 2024,
            'isbn'             => '978-602-' . rand(10000, 99999),
            'classification'   => '959.8',
            'call_number'      => '959.8 TIM b',
            'total_copies'     => 5,
            'available_copies' => 5,
        ]);

        $collection = Collection::create([
            'catalog_id'          => $catalog->id,
            'barcode_no'          => 'COL-' . Str::upper(Str::random(8)),
            'rfid_tag'            => 'RFID-' . Str::upper(Str::random(10)),
            'location'            => 'Rak Sejarah Lokal Lantai 2',
            'availability_status' => 'AVAILABLE',
            'physical_condition'  => 'Baik',
        ]);

        return [$catalog, $collection];
    }

    public function test_confirm_ready_dispatches_reservation_ready_email(): void
    {
        $admin = $this->createAdmin();
        $member = $this->createMember();
        [$catalog, $collection] = $this->createCatalogAndCollection();

        // Buat loan pending check (reservasi online)
        $loan = Loan::create([
            'loan_number'        => Loan::generateLoanNumber(),
            'reservation_code'   => Loan::generateReservationCode(),
            'member_id'          => $member->id,
            'collection_id'      => $collection->id,
            'status'             => Loan::STATUS_PENDING_CHECK,
            'pickup_deadline'    => null,
            'created_by_user_id' => $admin->id,
        ]);

        $updatedLoan = $this->service->confirmReady($loan->id, $admin->id);

        $this->assertEquals(Loan::STATUS_READY_FOR_PICKUP, $updatedLoan->status);
        $this->assertNotNull($updatedLoan->pickup_deadline);

        Mail::assertSent(ReservationReadyForPickupMail::class, function ($mail) use ($member, $loan) {
            return $mail->hasTo($member->email) &&
                   $mail->loan->id === $loan->id &&
                   $mail->loan->reservation_code === $loan->reservation_code;
        });
    }

    public function test_direct_borrow_dispatches_loan_due_date_email(): void
    {
        $admin = $this->createAdmin();
        $member = $this->createMember();
        [$catalog, $collection] = $this->createCatalogAndCollection();

        $loan = $this->service->processDirectBorrow(
            $member->member_no,
            $collection->barcode_no,
            $admin->id,
            7
        );

        $this->assertEquals(Loan::STATUS_BORROWED, $loan->status);
        $this->assertEquals(now()->addDays(7)->toDateString(), $loan->due_date->toDateString());

        Mail::assertSent(LoanDueDateNotificationMail::class, function ($mail) use ($member, $loan) {
            return $mail->hasTo($member->email) &&
                   $mail->loan->id === $loan->id &&
                   $mail->notificationType === LoanDueDateNotificationMail::TYPE_NEW_LOAN;
        });
    }

    public function test_confirm_pickup_dispatches_loan_due_date_email(): void
    {
        $admin = $this->createAdmin();
        $member = $this->createMember();
        [$catalog, $collection] = $this->createCatalogAndCollection();

        $loan = Loan::create([
            'loan_number'        => Loan::generateLoanNumber(),
            'reservation_code'   => Loan::generateReservationCode(),
            'member_id'          => $member->id,
            'collection_id'      => $collection->id,
            'status'             => Loan::STATUS_READY_FOR_PICKUP,
            'pickup_deadline'    => now()->addDays(3),
            'created_by_user_id' => $admin->id,
        ]);

        $borrowedLoan = $this->service->confirmPickup($loan->reservation_code, $admin->id, 14);

        $this->assertEquals(Loan::STATUS_BORROWED, $borrowedLoan->status);
        $this->assertEquals(now()->addDays(14)->toDateString(), $borrowedLoan->due_date->toDateString());

        Mail::assertSent(LoanDueDateNotificationMail::class, function ($mail) use ($member, $borrowedLoan) {
            return $mail->hasTo($member->email) &&
                   $mail->loan->id === $borrowedLoan->id &&
                   $mail->notificationType === LoanDueDateNotificationMail::TYPE_NEW_LOAN;
        });
    }

    public function test_renewal_dispatches_renewal_due_date_email(): void
    {
        $admin = $this->createAdmin();
        $member = $this->createMember();
        [$catalog, $collection] = $this->createCatalogAndCollection();

        $originalDueDate = now()->addDays(5)->toDateString();

        $loan = Loan::create([
            'loan_number'        => Loan::generateLoanNumber(),
            'member_id'          => $member->id,
            'collection_id'      => $collection->id,
            'status'             => Loan::STATUS_BORROWED,
            'borrow_date'        => now()->subDays(2),
            'loan_date'          => now()->subDays(2)->toDateString(),
            'due_date'           => $originalDueDate,
            'extension_count'    => 0,
            'created_by_user_id' => $admin->id,
        ]);

        $renewedLoan = $this->service->processRenewal($collection->barcode_no, 7);

        $this->assertEquals(1, $renewedLoan->extension_count);
        $this->assertEquals(Carbon::parse($originalDueDate)->addDays(7)->toDateString(), $renewedLoan->due_date->toDateString());

        Mail::assertSent(LoanDueDateNotificationMail::class, function ($mail) use ($member, $renewedLoan) {
            return $mail->hasTo($member->email) &&
                   $mail->loan->id === $renewedLoan->id &&
                   $mail->notificationType === LoanDueDateNotificationMail::TYPE_RENEWAL;
        });
    }

    public function test_console_command_sends_due_soon_and_overdue_reminders(): void
    {
        $admin = $this->createAdmin();
        $memberSoon = $this->createMember(['email' => 'soon_' . Str::random(6) . '@example.com']);
        $memberOverdue = $this->createMember(['email' => 'overdue_' . Str::random(6) . '@example.com']);

        [$catalog1, $col1] = $this->createCatalogAndCollection();
        [$catalog2, $col2] = $this->createCatalogAndCollection();

        // Pinjaman 1: Jatuh tempo besok (H-1)
        $loanSoon = Loan::create([
            'loan_number'        => Loan::generateLoanNumber(),
            'member_id'          => $memberSoon->id,
            'collection_id'      => $col1->id,
            'status'             => Loan::STATUS_BORROWED,
            'borrow_date'        => now()->subDays(6),
            'loan_date'          => now()->subDays(6)->toDateString(),
            'due_date'           => now()->addDay()->toDateString(),
            'created_by_user_id' => $admin->id,
        ]);

        // Pinjaman 2: Sudah terlambat 2 hari
        $loanOverdue = Loan::create([
            'loan_number'        => Loan::generateLoanNumber(),
            'member_id'          => $memberOverdue->id,
            'collection_id'      => $col2->id,
            'status'             => Loan::STATUS_BORROWED,
            'borrow_date'        => now()->subDays(10),
            'loan_date'          => now()->subDays(10)->toDateString(),
            'due_date'           => now()->subDays(2)->toDateString(),
            'created_by_user_id' => $admin->id,
        ]);

        // Jalankan perintah console
        $this->artisan('loans:send-due-reminders')
            ->assertExitCode(0);

        // Verifikasi email terkirim
        Mail::assertSent(LoanDueDateNotificationMail::class, function ($mail) use ($memberSoon, $loanSoon) {
            return $mail->hasTo($memberSoon->email) &&
                   $mail->loan->id === $loanSoon->id &&
                   $mail->notificationType === LoanDueDateNotificationMail::TYPE_REMINDER_SOON;
        });

        Mail::assertSent(LoanDueDateNotificationMail::class, function ($mail) use ($memberOverdue, $loanOverdue) {
            return $mail->hasTo($memberOverdue->email) &&
                   $mail->loan->id === $loanOverdue->id &&
                   $mail->notificationType === LoanDueDateNotificationMail::TYPE_OVERDUE;
        });

        $loanSoon->refresh();
        $loanOverdue->refresh();

        $this->assertNotNull($loanSoon->due_reminder_sent_at);
        $this->assertNotNull($loanOverdue->due_reminder_sent_at);
        $this->assertEquals(Loan::STATUS_OVERDUE, $loanOverdue->status);
    }
}
