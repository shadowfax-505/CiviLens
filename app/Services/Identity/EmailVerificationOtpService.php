<?php

namespace App\Services\Identity;

use App\Mail\EmailVerificationOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmailVerificationOtpService
{
    public function send(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        DB::table('email_verification_otps')->updateOrInsert(['user_id' => $user->id], [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'last_sent_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        Mail::to($user->email)->queue(new EmailVerificationOtp($code));
    }

    public function verify(User $user, string $code): void
    {
        $otp = DB::table('email_verification_otps')->where('user_id', $user->id)->first();

        if ($otp === null || now()->greaterThan($otp->expires_at) || $otp->attempts >= 5 || ! Hash::check($code, $otp->code_hash)) {
            if ($otp !== null) {
                DB::table('email_verification_otps')->where('user_id', $user->id)->increment('attempts');
            }

            throw ValidationException::withMessages(['code' => 'The verification code is invalid or expired.']);
        }

        $user->markEmailAsVerified();
        DB::table('email_verification_otps')->where('user_id', $user->id)->delete();
    }
}
