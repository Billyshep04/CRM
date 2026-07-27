<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProposalFormSettings
{
    private const FILE_PATH = 'app/private/proposal_form_settings.json';

    public function __construct(private readonly FormTemplateNormalizer $normalizer)
    {
    }

    public function adminPayload(): array
    {
        $stored = $this->read();

        return [
            'types' => $stored['types'] ?? $this->defaultTypes(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $types
     */
    public function update(array $types): array
    {
        $normalized = $this->normalizer->normalize($types);

        Storage::disk('local')->put(self::FILE_PATH, json_encode([
            'types' => $normalized,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this->adminPayload();
    }

    public function findType(string $slug): ?array
    {
        foreach ($this->adminPayload()['types'] as $type) {
            if (($type['slug'] ?? '') === $slug) {
                return $type;
            }
        }

        return null;
    }

    private function read(): array
    {
        if (!Storage::disk('local')->exists(self::FILE_PATH)) {
            return [];
        }

        $decoded = json_decode(Storage::disk('local')->get(self::FILE_PATH), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function defaultTypes(): array
    {
        $questions = [
            ['key' => 'number_of_pages', 'label' => 'Number of pages', 'type' => 'number', 'required' => true],
            ['key' => 'copywriting_required', 'label' => 'Does the client need copywriting?', 'type' => 'checkbox', 'required' => false],
            ['key' => 'photography_required', 'label' => 'Does the client need images supplied (Photography)?', 'type' => 'checkbox', 'required' => false],
            ['key' => 'booking_system_required', 'label' => 'Booking system required?', 'type' => 'checkbox', 'required' => false],
            ['key' => 'ecommerce_required', 'label' => 'Ecommerce required?', 'type' => 'checkbox', 'required' => false],
            ['key' => 'blog_required', 'label' => 'Blog/news section?', 'type' => 'checkbox', 'required' => false],
            ['key' => 'portal_required', 'label' => 'Login/customer portal?', 'type' => 'checkbox', 'required' => false],
            ['key' => 'hosting_required', 'label' => 'Hosting required?', 'type' => 'checkbox', 'required' => false],
            ['key' => 'domain_required', 'label' => 'Domain required?', 'type' => 'checkbox', 'required' => false],
            ['key' => 'seo_setup_required', 'label' => 'SEO setup required?', 'type' => 'checkbox', 'required' => false],
            ['key' => 'target_launch_date', 'label' => 'Target launch date', 'type' => 'date', 'required' => false],
            ['key' => 'special_requirements', 'label' => 'Notes / special requirements', 'type' => 'textarea', 'required' => false],
        ];

        return array_map(
            static fn (string $label, int $index): array => [
                'slug' => Str::slug($label),
                'label' => $label,
                'sort_order' => $index,
                'questions' => array_map(
                    static fn (array $question, int $questionIndex): array => [...$question, 'sort_order' => $questionIndex, 'options' => []],
                    $questions,
                    array_keys($questions)
                ),
            ],
            [
                'Website build',
                'Website redesign',
                'Website amendments',
                'SEO package',
                'Hosting / maintenance',
                'Branding / logo',
            ],
            range(0, 5)
        );
    }
}
