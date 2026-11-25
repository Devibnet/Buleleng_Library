<?php

namespace App\Mail;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationReadyForPickupMail extends Mailable
{
    use Queueable, SerializesModels;

    public Loan $loan;

    /**
     * Create a new message instance.
     */
    public function __construct(Loan $loan)
    {
        $this->loan = $loan;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $code = $this->loan->reservation_code ?? 'RSV';

        return new Envelope(
            subject: "Buku Reservasi Siap Diambil di Loket ({$code}) - Perpustakaan Daerah Buleleng",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation_ready_for_pickup',
        );
    }
}
