<?php

namespace BitmeshAI\Tests;

use BitmeshAI\BitmeshClient;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

abstract class BitmeshClientTestCase extends TestCase
{
    protected static function consumerKey(): string
    {
        return (string) (getenv('BITMESH_TEST_CONSUMER_KEY') ?: '');
    }

    protected static function consumerSecret(): string
    {
        return (string) (getenv('BITMESH_TEST_CONSUMER_SECRET') ?: '');
    }

    protected function createClient(int $timeoutSeconds = 120): BitmeshClient
    {
        $this->skipIfIntegrationCredentialsMissing();

        return new BitmeshClient(
            self::consumerKey(),
            self::consumerSecret(),
            $timeoutSeconds
        );
    }

    protected function skipIfIntegrationCredentialsMissing(): void
    {
        if (self::consumerKey() === '' || self::consumerSecret() === '') {
            $this->markTestSkipped(
                'Set BITMESH_TEST_CONSUMER_KEY and BITMESH_TEST_CONSUMER_SECRET to run integration tests.'
            );
        }
    }

    protected function fixturePath(string $filename): string
    {
        return __DIR__.'/Fixtures/'.$filename;
    }

    protected function toolsResultPathFromImageUrl(string $imageUrl): string
    {
        $path = parse_url($imageUrl, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            throw new InvalidArgumentException('Invalid tools-result image URL: '.$imageUrl);
        }

        $prefix = '/tools-result/';
        $pos = strpos($path, $prefix);
        if ($pos === false) {
            throw new InvalidArgumentException('URL does not contain /tools-result/ path: '.$imageUrl);
        }

        return ltrim(substr($path, $pos + strlen($prefix)), '/');
    }
}
