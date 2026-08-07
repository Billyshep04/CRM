<?php

namespace Tests\Feature;

use App\Mail\CustomerFormCompletedAdminMailable;
use App\Mail\CustomerFormRequestedMailable;
use App\Models\Customer;
use App\Models\CustomerFormRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerFormSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerFormWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_customer_form_templates(): void
    {
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $this->actingAs($admin)->getJson('/api/admin/customer-forms')
            ->assertOk()
            ->assertJsonPath('data.types.0.slug', 'client-onboarding');
        $this->actingAs($admin)->getJson('/api/admin/proposal-forms')
            ->assertOk()
            ->assertJsonStructure(['data' => ['types']]);

        $payload = [
            'types' => [[
                'slug' => 'handover',
                'label' => 'Project handover',
                'questions' => [[
                    'key' => '',
                    'label' => 'Approval confirmed',
                    'type' => 'checkbox',
                    'required' => true,
                    'options' => [],
                ]],
            ]],
        ];

        $this->actingAs($admin)->putJson('/api/admin/customer-forms', $payload)
            ->assertOk()
            ->assertJsonPath('data.types.0.label', 'Project handover')
            ->assertJsonPath('data.types.0.questions.0.key', 'approval_confirmed');

        $this->actingAs($staff)->getJson('/api/admin/customer-forms')->assertForbidden();
        $this->assertSame(
            'Project handover',
            $this->app->make(CustomerFormSettings::class)->findType('handover')['label']
        );
    }

    public function test_long_question_labels_generate_stable_keys_within_the_validation_limit(): void
    {
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->userWithRole('admin');
        $longLabel = str_repeat('Tell us about your preferred website navigation and customer journey ', 3);

        $response = $this->actingAs($admin)->putJson('/api/admin/customer-forms', [
            'types' => [[
                'slug' => 'long-question-form',
                'label' => 'Long question form',
                'questions' => [[
                    'key' => '',
                    'label' => $longLabel,
                    'type' => 'textarea',
                    'required' => false,
                    'options' => [],
                ]],
            ]],
        ])->assertOk();

        $key = (string) $response->json('data.types.0.questions.0.key');
        $this->assertLessThanOrEqual(100, mb_strlen($key));
        $this->assertSame($key, $this->app->make(CustomerFormSettings::class)->findType('long-question-form')['questions'][0]['key']);
    }

    public function test_admin_can_send_form_and_customer_can_submit_it_once(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->seed(RolePermissionSeeder::class);

        $admin = $this->userWithRole('admin');
        $portalUser = $this->userWithRole('customer', 'client@example.com');
        $customer = Customer::query()->create([
            'name' => 'Example Client',
            'email' => $portalUser->email,
            'billing_address' => '1 Example Street',
            'user_id' => $portalUser->id,
            'created_by_user_id' => $admin->id,
        ]);

        $this->configureTemplate();

        $sendResponse = $this->actingAs($admin)->postJson("/api/customers/{$customer->id}/forms", [
            'template_slug' => 'project-brief',
        ])->assertCreated()
            ->assertJsonPath('data.template_name', 'Project brief')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('notification_sent', true);

        $formId = (int) $sendResponse->json('data.id');
        Mail::assertSent(CustomerFormRequestedMailable::class, fn ($mail): bool => $mail->hasTo($customer->email));

        $this->app->make(CustomerFormSettings::class)->update([[
            'slug' => 'project-brief',
            'label' => 'Changed template',
            'questions' => [[
                'key' => 'replacement_field',
                'label' => 'Replacement field',
                'type' => 'text',
                'required' => false,
            ]],
        ]]);

        $this->actingAs($portalUser)->getJson('/api/portal/forms')
            ->assertOk()
            ->assertJsonPath('data.0.id', $formId)
            ->assertJsonPath('data.0.template_name', 'Project brief')
            ->assertJsonPath('data.0.form_schema.0.key', 'company_goals');

        $answers = [
            'company_goals' => 'Launch a new customer portal',
            'service_level' => 'Growth',
            'approved' => true,
        ];

        $this->actingAs($portalUser)->postJson("/api/portal/forms/{$formId}/submit", [
            'answers' => $answers,
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.answers.company_goals', $answers['company_goals']);

        $this->assertDatabaseHas('customer_form_requests', [
            'id' => $formId,
            'customer_id' => $customer->id,
            'status' => CustomerFormRequest::STATUS_COMPLETED,
        ]);
        $this->assertNotNull(CustomerFormRequest::query()->findOrFail($formId)->completed_at);
        Mail::assertSent(CustomerFormCompletedAdminMailable::class, fn ($mail): bool => $mail->hasTo('info@web-stamp.co.uk'));

        $this->actingAs($portalUser)->postJson("/api/portal/forms/{$formId}/submit", [
            'answers' => $answers,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('form');

        $this->actingAs($admin)->getJson("/api/customers/{$customer->id}/forms")
            ->assertOk()
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.answers.service_level', 'Growth');
    }

    public function test_customer_forms_are_isolated_and_submission_fields_are_validated(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->seed(RolePermissionSeeder::class);

        $admin = $this->userWithRole('admin');
        $owner = $this->userWithRole('customer', 'owner@example.com');
        $otherCustomer = $this->userWithRole('customer', 'other@example.com');
        $staff = $this->userWithRole('staff');
        $customer = Customer::query()->create([
            'name' => 'Owner',
            'email' => $owner->email,
            'billing_address' => '1 Owner Street',
            'user_id' => $owner->id,
            'created_by_user_id' => $admin->id,
        ]);

        $this->configureTemplate();
        $formId = (int) $this->actingAs($admin)->postJson("/api/customers/{$customer->id}/forms", [
            'template_slug' => 'project-brief',
        ])->assertCreated()->json('data.id');

        $this->actingAs($otherCustomer)->getJson("/api/portal/forms/{$formId}")->assertNotFound();
        $this->actingAs($otherCustomer)->postJson("/api/portal/forms/{$formId}/submit", [
            'answers' => [],
        ])->assertNotFound();
        $this->actingAs($staff)->getJson("/api/customers/{$customer->id}/forms")->assertForbidden();

        $this->actingAs($owner)->postJson("/api/portal/forms/{$formId}/submit", [
            'answers' => [
                'company_goals' => 'A goal',
                'service_level' => 'Invalid option',
                'approved' => false,
                'unexpected' => 'not allowed',
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('answers');

        $this->actingAs($owner)->postJson("/api/portal/forms/{$formId}/submit", [
            'answers' => [
                'company_goals' => 'A goal',
                'service_level' => 'Invalid option',
                'approved' => false,
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('answers.service_level');
    }

    public function test_admin_can_delete_a_sent_customer_form(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->seed(RolePermissionSeeder::class);

        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');
        $portalUser = $this->userWithRole('customer', 'delete-form@example.com');
        $customer = Customer::query()->create([
            'name' => 'Delete Form Client',
            'email' => $portalUser->email,
            'billing_address' => '3 Example Street',
            'user_id' => $portalUser->id,
            'created_by_user_id' => $admin->id,
        ]);
        $otherCustomer = Customer::query()->create([
            'name' => 'Other Client',
            'email' => 'other-client@example.com',
            'billing_address' => '4 Example Street',
            'created_by_user_id' => $admin->id,
        ]);

        $this->configureTemplate();
        $formId = (int) $this->actingAs($admin)->postJson("/api/customers/{$customer->id}/forms", [
            'template_slug' => 'project-brief',
        ])->assertCreated()->json('data.id');

        $this->actingAs($staff)
            ->deleteJson("/api/customers/{$customer->id}/forms/{$formId}")
            ->assertForbidden();
        $this->actingAs($admin)
            ->deleteJson("/api/customers/{$otherCustomer->id}/forms/{$formId}")
            ->assertNotFound();
        $this->actingAs($admin)
            ->deleteJson("/api/customers/{$customer->id}/forms/{$formId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('customer_form_requests', ['id' => $formId]);
        $this->actingAs($portalUser)->getJson('/api/portal/forms')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function configureTemplate(): void
    {
        $this->app->make(CustomerFormSettings::class)->update([[
            'slug' => 'project-brief',
            'label' => 'Project brief',
            'questions' => [
                [
                    'key' => 'company_goals',
                    'label' => 'Company goals',
                    'type' => 'textarea',
                    'required' => true,
                ],
                [
                    'key' => 'service_level',
                    'label' => 'Service level',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['Starter', 'Growth'],
                ],
                [
                    'key' => 'approved',
                    'label' => 'Details approved',
                    'type' => 'checkbox',
                    'required' => true,
                ],
            ],
        ]]);
    }

    private function userWithRole(string $role, ?string $email = null): User
    {
        $user = User::factory()->create($email ? ['email' => $email] : []);
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
