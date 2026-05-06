<?php

namespace BitmeshAI\Tests;

use BitmeshAI\BitmeshClient;

class BitmeshClientToolsBackgroundRemovalTest extends BitmeshClientTestCase
{
    public function testBackgroundRemovalBuildsMultipartFieldsAndSignature()
    {
        $consumerKey = $this->getConsumerKey();
        $consumerSecret = $this->getConsumerSecret();

        $imagePath = tempnam(sys_get_temp_dir(), 'img_');
        file_put_contents($imagePath, 'dummy-image');

        $client = new class($consumerKey, $consumerSecret, 'https://api.bitmesh.ai') extends BitmeshClient {
            public array $captured = [];

            protected function sendMultipartRequest(
                string $url,
                string $authHeader,
                string $payloadSignature,
                array $multipartPostFields
            ): array {
                $this->captured = [
                    'url' => $url,
                    'authHeader' => $authHeader,
                    'payloadSignature' => $payloadSignature,
                    'multipartPostFields' => $multipartPostFields,
                ];

                return [
                    200,
                    json_encode(['ok' => true, 'tool' => 'background-removal'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ];
            }
        };

        $response = $client->backgroundRemoval($imagePath, 'mask', ['test' => true], []);

        $this->assertIsArray($response);
        $this->assertSame(true, $response['ok']);
        $this->assertSame('https://api.bitmesh.ai/tools/general/background-removal', $client->captured['url']);

        $this->assertArrayHasKey('image', $client->captured['multipartPostFields']);
        $this->assertInstanceOf(\CURLFile::class, $client->captured['multipartPostFields']['image']);
        $this->assertSame('mask', $client->captured['multipartPostFields']['return_form']);

        preg_match('/oauth_signature="([^"]+)"/', $client->captured['authHeader'], $matches);
        $oauthSignature = rawurldecode($matches[1]);

        $nonFileFields = [
            'return_form' => 'mask',
            'test' => true,
        ];

        $normalize = function ($value) use (&$normalize) {
            if ($value === null) {
                return null;
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
            if (is_array($value)) {
                $out = [];
                foreach ($value as $k => $v) {
                    $out[$k] = $normalize($v);
                }
                return $out;
            }
            return (string) $value;
        };

        $normalized = [];
        foreach ($nonFileFields as $key => $value) {
            if ($value === null) {
                continue;
            }
            $normalized[$key] = $normalize($value);
        }
        ksort($normalized);

        $canonicalJson = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $expectedPayloadSignature = hash(
            'sha256',
            $canonicalJson . $consumerKey . $oauthSignature
        );

        $this->assertSame($expectedPayloadSignature, $client->captured['payloadSignature']);
    }
}

