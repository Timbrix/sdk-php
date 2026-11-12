<?php

declare(strict_types=1);

namespace Timbrix\Tests;

use GuzzleHttp\Client;
use InvalidArgumentException;
use Timbrix\TimbrixClient;

final class TimbrixClientTest extends TestCase
{
    public function test_rejects_empty_api_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TimbrixClient('');
    }

    public function test_normalizes_base_url(): void
    {
        $this->assertSame('https://api.timbrix.mx/', TimbrixClient::normalizeBaseUrl('https://api.timbrix.mx'));
        $this->assertSame('https://proxy.local/timbrix/', TimbrixClient::normalizeBaseUrl('https://proxy.local/timbrix'));
        $this->assertSame('https://x.mx/', TimbrixClient::normalizeBaseUrl('https://x.mx//'));
    }

    public function test_default_base_url_is_production(): void
    {
        $this->assertSame('https://api.timbrix.mx', TimbrixClient::DEFAULT_BASE_URL);
    }

    public function test_rejects_invalid_option_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TimbrixClient('key', ['timeout' => 0]);
    }

    public function test_rejects_unknown_option(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TimbrixClient('key', ['maxRetry' => 3]);
    }

    public function test_accepts_valid_options(): void
    {
        $client = new TimbrixClient('key', [
            'baseUrl' => 'https://proxy.local/timbrix',
            'timeout' => 10.5,
            'maxRetries' => 0,
            'httpClient' => new Client(),
            'sleeper' => static function (float $seconds): void {
            },
        ]);
        $this->assertInstanceOf(TimbrixClient::class, $client);
    }
}
