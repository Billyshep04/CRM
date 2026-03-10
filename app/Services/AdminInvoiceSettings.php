<?php

namespace App\Services;

class AdminInvoiceSettings
{
    private const FILE_PATH = 'app/private/admin_invoice_settings.json';
    private const DEFAULT_ACCOUNT_NAME = 'Billy Sheppard';
    private const DEFAULT_SORT_CODE = '04-00-03';
    private const DEFAULT_ACCOUNT_NUMBER = '05574495';

    /**
     * @return array<string, string>
     */
    public function adminPayload(): array
    {
        $raw = $this->readRaw();

        return [
            'account_name' => $this->resolveString($raw['account_name'] ?? null, self::DEFAULT_ACCOUNT_NAME),
            'sort_code' => $this->resolveString($raw['sort_code'] ?? null, self::DEFAULT_SORT_CODE),
            'account_number' => $this->resolveString($raw['account_number'] ?? null, self::DEFAULT_ACCOUNT_NUMBER),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function paymentDetails(): array
    {
        return $this->adminPayload();
    }

    /**
     * @return array<string, string>
     */
    public function updatePaymentDetails(string $accountName, string $sortCode, string $accountNumber): array
    {
        $settings = $this->readRaw();
        $settings['account_name'] = trim($accountName);
        $settings['sort_code'] = trim($sortCode);
        $settings['account_number'] = trim($accountNumber);

        $this->writeRaw($settings);

        return $this->adminPayload();
    }

    /**
     * @return array<string, mixed>
     */
    private function readRaw(): array
    {
        $path = $this->path();
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function writeRaw(array $settings): void
    {
        $path = $this->path();
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
            LOCK_EX
        );
    }

    private function path(): string
    {
        return storage_path(self::FILE_PATH);
    }

    private function resolveString(mixed $value, string $fallback): string
    {
        if (!is_string($value)) {
            return $fallback;
        }

        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : $fallback;
    }
}
