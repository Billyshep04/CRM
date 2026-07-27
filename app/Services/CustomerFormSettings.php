<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class CustomerFormSettings
{
    private const FILE_PATH = 'app/private/customer_form_settings.json';

    public function __construct(private readonly FormTemplateNormalizer $normalizer)
    {
    }

    public function adminPayload(): array
    {
        $stored = $this->read();

        return ['types' => $stored['types'] ?? $this->defaultTypes()];
    }

    /** @param array<int, array<string, mixed>> $types */
    public function update(array $types): array
    {
        Storage::disk('local')->put(self::FILE_PATH, json_encode([
            'types' => $this->normalizer->normalize($types),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this->adminPayload();
    }

    public function findType(string $slug): ?array
    {
        foreach ($this->adminPayload()['types'] as $type) {
            if (($type['slug'] ?? '') === $slug) return $type;
        }

        return null;
    }

    private function read(): array
    {
        if (!Storage::disk('local')->exists(self::FILE_PATH)) return [];
        $decoded = json_decode(Storage::disk('local')->get(self::FILE_PATH), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<int, array<string, mixed>> */
    private function defaultTypes(): array
    {
        return $this->normalizer->normalize([
            [
                'slug' => 'client-onboarding',
                'label' => 'Client onboarding',
                'questions' => [
                    ['key' => 'business_overview', 'label' => 'Tell us about your business', 'type' => 'textarea', 'required' => true],
                    ['key' => 'primary_contact', 'label' => 'Primary contact name', 'type' => 'text', 'required' => true],
                    ['key' => 'business_goals', 'label' => 'What are your main goals?', 'type' => 'textarea', 'required' => true],
                    ['key' => 'target_audience', 'label' => 'Who is your target audience?', 'type' => 'textarea', 'required' => false],
                    ['key' => 'target_date', 'label' => 'Preferred completion date', 'type' => 'date', 'required' => false],
                ],
            ],
            [
                'slug' => 'website-content',
                'label' => 'Website content questionnaire',
                'questions' => [
                    ['key' => 'services', 'label' => 'Services to feature', 'type' => 'textarea', 'required' => true],
                    ['key' => 'tone', 'label' => 'Preferred tone of voice', 'type' => 'select', 'required' => true, 'options' => ['Professional', 'Friendly', 'Bold', 'Luxury']],
                    ['key' => 'competitors', 'label' => 'Competitor or inspiration websites', 'type' => 'textarea', 'required' => false],
                    ['key' => 'content_approved', 'label' => 'I confirm these details are ready to use', 'type' => 'checkbox', 'required' => true],
                ],
            ],
        ]);
    }
}
