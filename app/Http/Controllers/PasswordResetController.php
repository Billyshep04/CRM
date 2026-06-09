<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetMailable;
use App\Models\User;
use App\Services\AdminMailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class PasswordResetController extends Controller
{
    public function request(Request $request, AdminMailSettings $mailSettings)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user && $user->hasRole('customer') && !$this->recentTokenExists($email)) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            $resetUrl = $this->resetUrl($email, $token);

            if ($mailSettings->smtp2goEnabled()) {
                $this->sendViaSmtp2go($mailSettings, $user, $resetUrl);
            } else {
                Mail::to($user->email)->send(new PasswordResetMailable($user, $resetUrl));
            }
        }

        return response()->json([
            'message' => 'If a customer portal account exists for that email, a password reset link has been sent.',
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $user = User::query()->where('email', $email)->first();
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (
            !$user
            || !$user->hasRole('customer')
            || !$record
            || $this->tokenExpired($record->created_at)
            || !Hash::check((string) $validated['token'], (string) $record->token)
        ) {
            return response()->json([
                'message' => 'This password reset link is invalid or has expired.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make((string) $validated['password']),
        ])->save();

        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'message' => 'Your password has been reset. You can now sign in.',
        ]);
    }

    private function resetUrl(string $email, string $token): string
    {
        return url('/?' . http_build_query([
            'reset_token' => $token,
            'email' => $email,
        ]));
    }

    private function sendViaSmtp2go(AdminMailSettings $mailSettings, User $user, string $resetUrl): void
    {
        $apiKey = $mailSettings->smtp2goApiKey();
        if ($apiKey === null || $apiKey === '') {
            throw new RuntimeException('SMTP2GO is enabled but no API key is configured.');
        }

        $fromAddress = trim((string) config('mail.from.address'));
        if ($fromAddress === '') {
            throw new RuntimeException('MAIL_FROM_ADDRESS is missing.');
        }

        $toAddress = trim((string) $user->email);
        if ($toAddress === '') {
            throw new RuntimeException('Customer email is missing.');
        }

        $fromName = trim((string) config('mail.from.name'));
        $sender = $fromName !== '' ? "{$fromName} <{$fromAddress}>" : $fromAddress;
        $subject = 'Reset your WebStamp CRM password';

        $payload = [
            'api_key' => $apiKey,
            'sender' => $sender,
            'to' => [$toAddress],
            'subject' => $subject,
            'html_body' => View::make('emails.password-reset', [
                'user' => $user,
                'resetUrl' => $resetUrl,
            ])->render(),
            'text_body' => "Reset your WebStamp CRM password\n{$resetUrl}\n\nThis link expires in "
                . config('auth.passwords.users.expire', 60)
                . ' minutes.',
        ];

        $response = Http::acceptJson()
            ->timeout(20)
            ->post('https://api.smtp2go.com/v3/email/send', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                sprintf('SMTP2GO request failed (%d): %s', $response->status(), $response->body())
            );
        }

        $failed = (int) data_get($response->json(), 'data.failed', 0);
        $succeeded = (int) data_get($response->json(), 'data.succeeded', 0);

        if ($failed > 0 || $succeeded < 1) {
            $failureMessage = data_get($response->json(), 'data.failures.0.error')
                ?: data_get($response->json(), 'data.failures.0.message')
                ?: 'Unknown SMTP2GO failure.';

            throw new RuntimeException((string) $failureMessage);
        }
    }

    private function recentTokenExists(string $email): bool
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || !$record->created_at) {
            return false;
        }

        return Carbon::parse($record->created_at)
            ->gt(now()->subSeconds((int) config('auth.passwords.users.throttle', 60)));
    }

    private function tokenExpired(mixed $createdAt): bool
    {
        if (!$createdAt) {
            return true;
        }

        return Carbon::parse($createdAt)
            ->lt(now()->subMinutes((int) config('auth.passwords.users.expire', 60)));
    }
}
