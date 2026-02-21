<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class AdminMailSettings
{
    private const FILE_PATH = 'app/private/admin_mail_settings.json';

    /**
     * @return array<string, mixed>
     */
    public function adminPayload(): array
    {
        $raw = $this->readRaw();
        $apiKey = $this->decryptApiKey($raw['smtp2go_api_key'] ?? null);

        return [
            'smtp2go_enabled' => (bool) ($raw['smtp2go_enabled'] ?? false),
            'smtp2go_api_key_set' => $apiKey !== null && $apiKey !== '',
            'smtp2go_api_key_masked' => $this->maskApiKey($apiKey),
        ];
    }

    public function smtp2goEnabled(): bool
    {
        return (bool) ($this->readRaw()['smtp2go_enabled'] ?? false);
    }

    public function hasSmtp2goApiKey(): bool
    {
        return $this->smtp2goApiKey() !== null;
    }

    public function smtp2goApiKey(): ?string
    {
        return $this->decryptApiKey($this->readRaw()['smtp2go_api_key'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateSmtp2go(bool $enabled, ?string $apiKey = null): array
    {
        $settings = $this->readRaw();
        $settings['smtp2go_enabled'] = $enabled;

        if ($apiKey !== null && $apiKey !== '') {
            $settings['smtp2go_api_key'] = Crypt::encryptString($apiKey);
        }

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

    private function decryptApiKey(mixed $encrypted): ?string
    {
        if (!is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $value = Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }

        return $value !== '' ? $value : null;
    }

    private function maskApiKey(?string $apiKey): ?string
    {
        if ($apiKey === null || $apiKey === '') {
            return null;
        }

        $length = strlen($apiKey);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($apiKey, 0, 4) . str_repeat('*', max($length - 8, 4)) . substr($apiKey, -4);
    }
}
