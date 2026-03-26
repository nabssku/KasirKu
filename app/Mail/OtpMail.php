<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $type;
    public $title;
    public $description;
    public $expires;

    /**
     * Create a new message instance.
     */
    public function __construct($otp, $type = 'registration', $expires = 10)
    {
        $this->otp = $otp;
        $this->type = $type;
        $this->expires = $expires;

        if ($type === 'registration') {
            $this->title = 'Verifikasi Pendaftaran Akun';
            $this->description = 'Terima kasih telah bergabung dengan JagoKasir. Silakan gunakan kode di bawah ini untuk memverifikasi pendaftaran Anda.';
        } elseif ($type === 'reset_password') {
            $this->title = 'Atur Ulang Kata Sandi';
            $this->description = 'Kami menerima permintaan untuk mengatur ulang kata sandi Anda. Gunakan kode verifikasi di bawah ini untuk melanjutkan.';
        } else {
            $this->title = 'Verifikasi Keamanan';
            $this->description = 'Gunakan kode verifikasi di bawah ini untuk melanjutkan aksi keamanan Anda di JagoKasir.';
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . $this->otp . '] Kode Verifikasi JagoKasir',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
