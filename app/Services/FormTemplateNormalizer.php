<?php

namespace App\Services;

use Illuminate\Support\Str;

class FormTemplateNormalizer
{
    /**
     * @param  array<int, array<string, mixed>>  $types
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $types): array
    {
        $normalized = [];

        foreach ($types as $index => $type) {
            $label = trim((string) ($type['label'] ?? ''));
            if ($label === '') continue;

            $slug = trim((string) ($type['slug'] ?? '')) ?: Str::slug($label);
            $questions = [];

            foreach (($type['questions'] ?? []) as $questionIndex => $question) {
                $questionLabel = trim((string) ($question['label'] ?? ''));
                if ($questionLabel === '') continue;

                $questionKey = trim((string) ($question['key'] ?? '')) ?: Str::slug($questionLabel, '_');
                $questionKey = $this->limitQuestionKey($questionKey);
                $fieldType = (string) ($question['type'] ?? 'text');
                if (!in_array($fieldType, ['text', 'textarea', 'number', 'date', 'select', 'checkbox'], true)) {
                    $fieldType = 'text';
                }

                $questions[] = [
                    'key' => $questionKey,
                    'label' => $questionLabel,
                    'type' => $fieldType,
                    'required' => (bool) ($question['required'] ?? false),
                    'options' => array_values(array_filter(array_map(
                        static fn ($option): string => trim((string) $option),
                        $question['options'] ?? []
                    ))),
                    'sort_order' => (int) ($question['sort_order'] ?? $questionIndex),
                ];
            }

            usort($questions, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
            $normalized[] = [
                'slug' => $slug,
                'label' => $label,
                'sort_order' => (int) ($type['sort_order'] ?? $index),
                'questions' => $questions,
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        return $normalized;
    }

    private function limitQuestionKey(string $key): string
    {
        if (Str::length($key) <= 100) {
            return $key;
        }

        return Str::substr($key, 0, 91).'_'.substr(sha1($key), 0, 8);
    }
}
