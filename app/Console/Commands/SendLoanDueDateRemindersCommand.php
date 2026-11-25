<?php

namespace App\Console\Commands;

use App\Mail\LoanDueDateNotificationMail;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendLoanDueDateRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:send-due-reminders {--dry-run : Tampilkan daftar tanpa mengirim email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim email pengingat tenggat waktu pengembalian buku (H-1 / hari ini) dan pemberitahuan keterlambatan tanpa denda kepada anggota';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $this->info("Memulai pemindaian tenggat pengembalian buku [Tanggal: {$today->toDateString()}]" . ($isDryRun ? ' (DRY RUN)' : ''));

        $remindersCount = 0;
        $overdueCount = 0;

        // Ambil transaksi yang masih aktif dipinjam (BORROWED atau OVERDUE)
        $activeLoans = Loan::with(['member', 'collection.catalog'])
            ->whereIn('status', [Loan::STATUS_BORROWED, Loan::STATUS_OVERDUE])
            ->whereNotNull('due_date')
            ->get();

        foreach ($activeLoans as $loan) {
            $member = $loan->member;
            if (! $member || empty($member->email)) {
                continue;
            }

            // Cek apakah sudah pernah dikirimkan reminder hari ini (anti-spam)
            if ($loan->due_reminder_sent_at && Carbon::parse($loan->due_reminder_sent_at)->isToday()) {
                continue;
            }

            $dueDate = Carbon::parse($loan->due_date)->startOfDay();

            // Kasus 1: Melewati batas waktu pengembalian (Overdue)
            if ($dueDate->lessThan($today)) {
                $this->line("- Terlambat: [{$loan->loan_number}] {$member->name} ({$member->email}) - Jatuh tempo: {$dueDate->toDateString()}");

                if (! $isDryRun) {
                    if ($loan->status !== Loan::STATUS_OVERDUE) {
                        $loan->update(['status' => Loan::STATUS_OVERDUE]);
                    }

                    try {
                        Mail::to($member->email)->send(new LoanDueDateNotificationMail($loan, LoanDueDateNotificationMail::TYPE_OVERDUE));
                        $loan->update(['due_reminder_sent_at' => now()]);
                        $overdueCount++;
                    } catch (\Throwable $e) {
                        Log::error("Gagal mengirim email keterlambatan ke {$member->email}: " . $e->getMessage());
                    }
                } else {
                    $overdueCount++;
                }

                continue;
            }

            // Kasus 2: Jatuh tempo hari ini atau besok (H-0 atau H-1)
            if ($dueDate->equalTo($today) || $dueDate->equalTo($tomorrow)) {
                $daysDiff = $dueDate->isToday() ? 'Hari ini' : 'Besok';
                $this->line("- Pengingat ({$daysDiff}): [{$loan->loan_number}] {$member->name} ({$member->email}) - Jatuh tempo: {$dueDate->toDateString()}");

                if (! $isDryRun) {
                    try {
                        Mail::to($member->email)->send(new LoanDueDateNotificationMail($loan, LoanDueDateNotificationMail::TYPE_REMINDER_SOON));
                        $loan->update(['due_reminder_sent_at' => now()]);
                        $remindersCount++;
                    } catch (\Throwable $e) {
                        Log::error("Gagal mengirim email pengingat tenggat ke {$member->email}: " . $e->getMessage());
                    }
                } else {
                    $remindersCount++;
                }
            }
        }

        $this->info("Pemindaian selesai. Pengingat terkirim: {$remindersCount}, Keterlambatan terkirim: {$overdueCount}.");

        return Command::SUCCESS;
    }
}
