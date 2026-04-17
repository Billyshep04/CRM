<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\Role;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class JobPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_upload_list_and_download_job_photos(): void
    {
        Storage::fake('private');

        $staffUser = User::factory()->create();
        $this->assignRole($staffUser, 'staff');
        Sanctum::actingAs($staffUser);

        $customer = Customer::query()->create([
            'name' => 'Tommy May',
            'email' => 'tommy@example.test',
            'billing_address' => '1 Billing Street',
        ]);

        $job = Job::query()->create([
            'customer_id' => $customer->id,
            'description' => 'Fence repair',
            'cost' => 100.00,
            'status' => 'draft',
        ]);

        $uploadResponse = $this->post(
            "/api/jobs/{$job->id}/photos",
            [
                'photos' => [
                    UploadedFile::fake()->image('work-1.jpg'),
                    UploadedFile::fake()->image('work-2.png'),
                ],
            ],
            ['Accept' => 'application/json']
        );

        $uploadResponse->assertOk();
        $this->assertDatabaseCount('files', 2);

        $photo = StoredFile::query()->first();
        $this->assertNotNull($photo);
        Storage::disk('private')->assertExists($photo->path);

        $listResponse = $this->getJson("/api/jobs/{$job->id}/photos");
        $listResponse->assertOk()->assertJsonCount(2, 'data');

        $downloadResponse = $this->get("/api/jobs/{$job->id}/photos/{$photo->id}/download");
        $downloadResponse->assertOk();
    }

    public function test_staff_can_download_all_job_photos_as_zip(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZIP extension is not available.');
        }

        Storage::fake('private');

        $staffUser = User::factory()->create();
        $this->assignRole($staffUser, 'staff');
        Sanctum::actingAs($staffUser);

        $customer = Customer::query()->create([
            'name' => 'Tommy May',
            'email' => 'tommy@example.test',
            'billing_address' => '1 Billing Street',
        ]);

        $job = Job::query()->create([
            'customer_id' => $customer->id,
            'description' => 'Roof inspection',
            'cost' => 50.00,
            'status' => 'draft',
        ]);

        $this->post(
            "/api/jobs/{$job->id}/photos",
            [
                'photos' => [
                    UploadedFile::fake()->image('roof-1.jpg'),
                    UploadedFile::fake()->image('roof-2.jpg'),
                ],
            ],
            ['Accept' => 'application/json']
        )->assertOk();

        $downloadAllResponse = $this->get("/api/jobs/{$job->id}/photos/download-all");
        $downloadAllResponse->assertOk();
        $downloadAllResponse->assertHeader('content-type', 'application/zip');
    }

    private function assignRole(User $user, string $roleSlug): void
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst($roleSlug)]
        );

        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}

