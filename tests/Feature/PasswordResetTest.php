<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMailable;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminMailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_request_and_complete_password_reset(): void
    {
        Mail::fake();
        $this->bindMailSettings(false);

        $user = $this->createUserWithRole('customer', [
            'email' => 'customer@example.test',
            'password' => Hash::make('OldPassword123'),
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'customer@example.test',
        ])->assertOk()
            ->assertJsonPath('message', 'If a customer portal account exists for that email, a password reset link has been sent.');

        Mail::assertSent(PasswordResetMailable::class, function (PasswordResetMailable $mail) use (&$resetUrl): bool {
            $resetUrl = $mail->resetUrl;

            return str_contains($resetUrl, 'reset_token=')
                && str_contains($resetUrl, 'email=customer%40example.test');
        });

        parse_str((string) parse_url($resetUrl, PHP_URL_QUERY), $params);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'customer@example.test',
            'token' => $params['reset_token'],
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertOk()
            ->assertJsonPath('message', 'Your password has been reset. You can now sign in.');

        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'customer@example.test',
        ]);
    }

    public function test_staff_password_reset_request_does_not_send_customer_reset_email(): void
    {
        Mail::fake();
        $this->bindMailSettings(false);

        $this->createUserWithRole('staff', [
            'email' => 'staff@example.test',
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'staff@example.test',
        ])->assertOk();

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'staff@example.test',
        ]);
    }

    public function test_customer_password_reset_uses_smtp2go_when_enabled(): void
    {
        Mail::fake();
        Http::fake([
            'https://api.smtp2go.com/v3/email/send' => Http::response([
                'data' => [
                    'succeeded' => 1,
                    'failed' => 0,
                ],
            ]),
        ]);
        $this->bindMailSettings(true);

        $this->createUserWithRole('customer', [
            'email' => 'customer@example.test',
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'customer@example.test',
        ])->assertOk();

        Mail::assertNothingSent();
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.smtp2go.com/v3/email/send'
                && $request['api_key'] === 'smtp2go-test-key'
                && $request['to'] === ['customer@example.test']
                && $request['subject'] === 'Reset your WebStamp CRM password'
                && str_contains((string) $request['html_body'], 'reset_token=');
        });
    }

    private function createUserWithRole(string $roleSlug, array $attributes = []): User
    {
        $role = Role::query()->create([
            'name' => ucfirst($roleSlug),
            'slug' => $roleSlug,
        ]);

        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);

        return $user;
    }

    private function bindMailSettings(bool $smtp2goEnabled): void
    {
        $this->app->instance(AdminMailSettings::class, new class($smtp2goEnabled) extends AdminMailSettings {
            public function __construct(private readonly bool $smtp2goEnabled)
            {
            }

            public function smtp2goEnabled(): bool
            {
                return $this->smtp2goEnabled;
            }

            public function smtp2goApiKey(): ?string
            {
                return 'smtp2go-test-key';
            }
        });
    }
}
