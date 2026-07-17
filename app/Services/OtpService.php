<?php

namespace App\Services;

use App\Models\Otp;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class OtpService
{
    /**
     * Generate and send OTP to the given email.
     * 
     * @return array
     */
    public function generateAndSend(string $email, string $type = 'registration'): array
    {
        // Delete any existing OTP for this email and type
        Otp::where('email', $email)->where('type', $type)->delete();

        // Generate 6 digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(10);

        Otp::create([
            'email' => $email,
            'code' => $code,
            'type' => $type,
            'expires_at' => $expiresAt,
        ]);

        try {
            $fromEmail = env('RESEND_FROM_EMAIL', 'onboarding@resend.dev');
            $response = \Illuminate\Support\Facades\Http::withToken(env('RESEND_API_KEY', 're_EkgFNwNi_PKXidvg3QUFEvsZct7XmqfJk'))
                ->post('https://api.resend.com/emails', [
                    'from' => $fromEmail,
                    'to' => $email,
                    'subject' => '[' . $code . '] Kode Verifikasi JagoKasir',
                    'html' => view('emails.otp', [
                        'otp' => $code,
                        'type' => $type,
                        'expires' => 10,
                        'title' => $type === 'registration' ? 'Verifikasi Pendaftaran Akun' : ($type === 'reset_password' ? 'Atur Ulang Kata Sandi' : 'Verifikasi Keamanan'),
                        'description' => $type === 'registration' 
                            ? 'Terima kasih telah bergabung dengan JagoKasir. Silakan gunakan kode di bawah ini untuk memverifikasi pendaftaran Anda.' 
                            : ($type === 'reset_password' 
                                ? 'Kami menerima permintaan untuk mengatur ulang kata sandi Anda. Gunakan kode verifikasi di bawah ini untuk melanjutkan.' 
                                : 'Gunakan kode verifikasi di bawah ini untuk melanjutkan aksi keamanan Anda di JagoKasir.')
                    ])->render()
                ]);

            if ($response->successful()) {
                return ['success' => true];
            }
            
            \Illuminate\Support\Facades\Log::error('Resend API Fail: ' . $response->body());
            return ['success' => false, 'error' => $response->json('message') ?? $response->body()];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send OTP email via Resend: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify the OTP code.
     */
    public function verify(string $email, string $code, string $type, bool $deleteAfter = true): bool
    {
        $otp = Otp::where('email', $email)
            ->where('code', $code)
            ->where('type', $type)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($otp) {
            if ($deleteAfter) {
                $otp->delete(); // Delete after successful verification
            }
            return true;
        }

        return false;
    }
}
