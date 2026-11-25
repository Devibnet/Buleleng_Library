<?php

namespace App\Mail;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Member $member;
    public string $activationUrl;
    public int $expiresHours;

    /**
     * Create a new message instance.
     */
    public function __construct(Member $member, string $token, int $expiresHours = 24)
    {
        $this->member = $member;
        $this->expiresHours = $expiresHours;

        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
        $this->activationUrl = rtrim($frontendUrl, '/') . '/aktivasi-akun?token=' . $token;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aktivasi Akun Digital Anggota - Perpustakaan Daerah Kabupaten Buleleng (ATLAS)',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.member_activation',
        );
    }
}
