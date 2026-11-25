<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanDueDateNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const TYPE_NEW_LOAN     = 'NEW_LOAN';
    public const TYPE_RENEWAL      = 'RENEWAL';
    public const TYPE_REMINDER_SOON = 'REMINDER_SOON';
    public const TYPE_OVERDUE      = 'OVERDUE';

    public Loan $loan;
    public string $notificationType;

    /**
     * Create a new message instance.
     */
    public function __construct(Loan $loan, string $notificationType = self::TYPE_NEW_LOAN)
    {
        $this->loan = $loan;
        $this->notificationType = $notificationType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $bookTitle = $this->loan->collection?->catalog?->title ?? 'Buku Perpustakaan';
        $shortTitle = mb_strimwidth($bookTitle, 0, 32, '...');

        $subject = match ($this->notificationType) {
            self::TYPE_RENEWAL      => "Perpanjangan Masa Pinjam: {$shortTitle} - ATLAS Buleleng",
            self::TYPE_REMINDER_SOON => "Pengingat Tenggat Pengembalian Buku: {$shortTitle} - ATLAS Buleleng",
            self::TYPE_OVERDUE      => "Pemberitahuan: Batas Tenggat Pengembalian Terlewati - ATLAS Buleleng",
            default                 => "Informasi Peminjaman & Tenggat Buku: {$shortTitle} - ATLAS Buleleng",
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.loan_due_date_notification',
        );
    }
}
