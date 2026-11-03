<?php

namespace App\Services\Concerns;

/**
 * Number formatting, trend arrows and brand assets shared by the monthly report PDFs.
 */
trait FormatsReportFigures
{
    /**
     * @param  array<string, mixed>  $source
     * @return array{current: float|null, previous: float|null}
     */
    private function pair(array $source, string $key): array
    {
        return [
            'current' => $this->num($source[$key]['current'] ?? null),
            'previous' => $this->num($source[$key]['previous'] ?? null),
        ];
    }

    private function num(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<int, float|null>  $values
     */
    private function sumOrNull(array $values): ?float
    {
        $present = array_filter($values, fn ($value) => $value !== null);

        return $present === [] ? null : array_sum($present);
    }

    private function number(?float $value, int $decimals = 0): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($decimals === 0 && floor($value) !== $value) {
            $decimals = 1;
        }

        return number_format($value, $decimals);
    }

    private function trimmed(float $value, int $decimals = 1): string
    {
        return rtrim(rtrim(number_format($value, $decimals), '0'), '.');
    }

    /**
     * @return array{tone: string, arrow: string, text: string}
     */
    private function trend(float $delta, string $text): array
    {
        if (abs($delta) < 0.0001) {
            return ['tone' => 'flat', 'arrow' => '', 'text' => 'No change'];
        }

        return $delta > 0
            ? ['tone' => 'up', 'arrow' => '▲', 'text' => $text]
            : ['tone' => 'down', 'arrow' => '▼', 'text' => $text];
    }

    private function logoDataUri(): ?string
    {
        $path = resource_path('images/new-logo-white-text.svg');
        $svg = is_file($path) ? file_get_contents($path) : false;

        return $svg ? 'data:image/svg+xml;base64,'.base64_encode($svg) : null;
    }

    /**
     * @return array<int, array{family: string, weight: int, path: string}>
     */
    private function fontFaces(): array
    {
        $faces = [];
        foreach ([['Inter', 400], ['Inter', 500], ['Inter', 600], ['Inter', 700], ['Inter', 800], ['Montserrat', 800]] as [$family, $weight]) {
            $path = resource_path('fonts/'.strtolower($family)."-{$weight}.ttf");
            if (is_file($path)) {
                $faces[] = ['family' => $family, 'weight' => $weight, 'path' => $path];
            }
        }

        return $faces;
    }
}
