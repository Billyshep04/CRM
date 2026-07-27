<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerFormSubmissionValidator
{
    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function validate(array $schema, array $answers): array
    {
        $rules = [];
        $allowedKeys = [];

        foreach ($schema as $question) {
            $key = trim((string) ($question['key'] ?? ''));
            if ($key === '' || in_array($key, $allowedKeys, true)) {
                continue;
            }

            $allowedKeys[] = $key;
            $type = (string) ($question['type'] ?? 'text');
            $required = (bool) ($question['required'] ?? false);
            $fieldRules = [$required ? ($type === 'checkbox' ? 'present' : 'required') : 'nullable'];

            $fieldRules[] = match ($type) {
                'number' => 'numeric',
                'date' => 'date_format:Y-m-d',
                'checkbox' => 'boolean',
                default => 'string',
            };

            if (in_array($type, ['text', 'textarea'], true)) {
                $fieldRules[] = 'max:10000';
            }

            if ($type === 'select') {
                $options = array_values(array_filter(array_map(
                    static fn ($option): string => trim((string) $option),
                    $question['options'] ?? []
                )));
                $fieldRules[] = Rule::in($options);
            }

            $rules["answers.{$key}"] = $fieldRules;
        }

        $unexpectedKeys = array_diff(array_keys($answers), $allowedKeys);
        if ($unexpectedKeys !== []) {
            throw ValidationException::withMessages([
                'answers' => ['The submission contains fields that are not part of this form.'],
            ]);
        }

        $validated = Validator::make(['answers' => $answers], [
            'answers' => ['required', 'array'],
            ...$rules,
        ])->validate();

        $normalized = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $validated['answers'])) {
                $normalized[$key] = $validated['answers'][$key];
            }
        }

        return $normalized;
    }
}
