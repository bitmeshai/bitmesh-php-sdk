<?php

namespace BitmeshAI\Tests;

use BitmeshAI\BitmeshClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Multipart uploads are signed over the JSON of the non-file fields as the
 * server sees them: text values, trimmed, empty strings as null, keys sorted.
 * These tests need no credentials.
 */
class BitmeshClientMultipartSigningTest extends TestCase
{
    private function call(BitmeshClient $client, string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod($client, $method);

        return $reflection->invoke($client, ...$args);
    }

    /**
     * What the API computes from the fields it received (all strings).
     *
     * @param  array<string, mixed>  $received
     */
    private function serverSignature(array $received, string $key, string $oauthSignature): string
    {
        array_walk_recursive($received, function (&$value) {
            $value = trim((string) $value);
            $value = $value === '' ? null : $value;
        });
        ksort($received);

        return hash('sha256', json_encode($received, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).$key.$oauthSignature);
    }

    public function test_values_are_converted_to_the_strings_sent_over_the_wire(): void
    {
        $client = new BitmeshClient('key', 'secret');

        $normalized = $this->call($client, 'normalizeMultipartFields', [
            'restore_face' => true,
            'test' => false,
            'resolution' => 1024,
            'skip' => null,
            'speech_models' => ['universal-2'],
            'empty' => [],
            'return_form' => 'crop',
        ]);

        $this->assertSame([
            'restore_face' => '1',
            'test' => '0',
            'resolution' => '1024',
            'speech_models' => ['universal-2'],
            'return_form' => 'crop',
        ], $normalized);
    }

    public function test_signature_matches_what_the_server_computes(): void
    {
        $client = new BitmeshClient('key', 'secret');

        $fields = $this->call($client, 'normalizeMultipartFields', [
            'test' => true,
            'resolution' => 1024,
            'return_form' => ' crop ',
            'speech_models' => ['universal-2'],
        ]);

        $this->assertSame(
            $this->serverSignature($fields, 'key', 'oauth-sig'),
            $this->call($client, 'generateMultipartPayloadSignature', $fields, 'oauth-sig')
        );
    }

    public function test_no_fields_signs_an_empty_json_array(): void
    {
        $client = new BitmeshClient('key', 'secret');

        $this->assertSame(
            hash('sha256', '[]'.'key'.'oauth-sig'),
            $this->call($client, 'generateMultipartPayloadSignature', [], 'oauth-sig')
        );
    }
}
