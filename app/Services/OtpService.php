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
     */
    public function generateAndSend(string $email, string $type = 'registration'): bool
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
            Mail::to($email)->send(new OtpMail($code, $type, 10));
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send OTP email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify the OTP code.
     */
    public function verify(string $email, string $code, string $type): bool
    {
        $otp = Otp::where('email', $email)
            ->where('code', $code)
            ->where('type', $type)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($otp) {
            $otp->delete(); // Delete after successful verification
            return true;
        }

        return false;
    }
}
