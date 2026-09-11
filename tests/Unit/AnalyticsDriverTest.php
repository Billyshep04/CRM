<?php

namespace Tests\Unit;

use App\Exceptions\AnalyticsMisconfiguredException;
use App\Services\Analytics\AnalyticsDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalyticsDriverTest extends TestCase
{
    #[DataProvider('validValues')]
    public function test_it_normalises_recognised_values(string $configured, string $expected): void
    {
        config(['analytics.driver' => $configured]);

        $this->assertSame($expected, AnalyticsDriver::current());
        $this->assertSame($expected, AnalyticsDriver::currentOrUnknown());
    }

    public static function validValues(): array
    {
        return [
            'plain google' => ['google', 'google'],
            'plain mock' => ['mock', 'mock'],
            'uppercase' => ['GOOGLE', 'google'],
            'whitespace' => ['  mock  ', 'mock'],
            'mixed case with padding' => [' Google ', 'google'],
        ];
    }

    public function test_google_is_live_and_mock_is_not(): void
    {
        config(['analytics.driver' => 'google']);
        $this->assertTrue(AnalyticsDriver::isLive());

        config(['analytics.driver' => 'mock']);
        $this->assertFalse(AnalyticsDriver::isLive());
    }

    public function test_an_unrecognised_value_throws_instead_of_silently_becoming_mock(): void
    {
        config(['analytics.driver' => 'goggle']);

        $this->expectException(AnalyticsMisconfiguredException::class);
        AnalyticsDriver::current();
    }

    public function test_an_unrecognised_value_is_reported_as_misconfigured_without_throwing(): void
    {
        config(['analytics.driver' => 'goggle']);

        $this->assertSame('misconfigured', AnalyticsDriver::currentOrUnknown());
    }

    public function test_a_blank_value_is_also_rejected(): void
    {
        config(['analytics.driver' => '']);

        $this->expectException(AnalyticsMisconfiguredException::class);
        AnalyticsDriver::current();
    }
}
