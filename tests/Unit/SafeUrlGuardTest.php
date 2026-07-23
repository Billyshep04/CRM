<?php

namespace Tests\Unit;

use App\Exceptions\UnsafeWebsiteUrl;
use App\Services\WebsiteAnalysis\SafeUrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafeUrlGuardTest extends TestCase
{
    #[DataProvider('unsafeUrls')]
    public function test_it_rejects_non_public_targets(string $url): void
    {
        $this->expectException(UnsafeWebsiteUrl::class);
        app(SafeUrlGuard::class)->assertSafe($url);
    }

    public static function unsafeUrls(): array
    {
        return [
            ['http://localhost/admin'],
            ['http://127.0.0.1'],
            ['http://10.0.0.1'],
            ['http://169.254.169.254/latest/meta-data'],
            ['file:///etc/passwd'],
            ['https://user:password@example.com'],
        ];
    }
}
