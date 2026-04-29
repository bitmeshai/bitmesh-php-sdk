<?php

namespace BitmeshAI\Tests;

use BitmeshAI\BitmeshClient;

class BitmeshClientTranscribeTest extends BitmeshClientTestCase
{
    public function testTranscribeRecordedFromUrlBuildsPayloadAndSignature()
    {
        $consumerKey = $this->getConsumerKey();
        $consumerSecret = $this->getConsumerSecret();

        $client = new class($consumerKey, $consumerSecret, 'https://api.bitmesh.ai') extends BitmeshClient {
            public array $captured = [];

            protected function sendRequest(
                string $url,
                string $authHeader,
                string $payloadSignature,
                string $jsonBody
            ): array {
                $this->captured = [
                    'url' => $url,
                    'authHeader' => $authHeader,
                    'payloadSignature' => $payloadSignature,
                    'jsonBody' => $jsonBody,
                ];

                return [
                    200,
                    json_encode([
                        'id' => 'transcript-123',
                        'status' => 'accepted',
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ];
            }
        };

        $audioUrl = 'https://example.com/audio.mp3';
        $options = [
            'speech_models' => ['universal-3-pro'],
            'language_code' => 'en',
        ];

        $response = $client->transcribeRecordedFromUrl($audioUrl, $options);

        $this->assertIsArray($response);
        $this->assertSame('transcript-123', $response['id']);
        $this->assertSame('https://api.bitmesh.ai/transcribe-recorded', $client->captured['url']);

        $decodedBody = json_decode($client->captured['jsonBody'], true);
        $this->assertSame($audioUrl, $decodedBody['audio_url']);
        $this->assertSame(['universal-3-pro'], $decodedBody['speech_models']);
        $this->assertSame('en', $decodedBody['language_code']);

        $this->assertStringStartsWith('OAuth ', $client->captured['authHeader']);
        $this->assertMatchesRegularExpression('/oauth_signature="([^"]+)"/', $client->captured['authHeader']);
        preg_match('/oauth_signature="([^"]+)"/', $client->captured['authHeader'], $matches);
        $oauthSignature = rawurldecode($matches[1]);

        $expectedPayloadSignature = hash(
            'sha256',
            $client->captured['jsonBody'] . $consumerKey . $oauthSignature
        );

        $this->assertSame($expectedPayloadSignature, $client->captured['payloadSignature']);
    }

    public function testTranscribeRecordedFromFileBuildsMultipartFieldsAndSignature()
    {
        $consumerKey = $this->getConsumerKey();
        $consumerSecret = $this->getConsumerSecret();

        $audioPath = tempnam(sys_get_temp_dir(), 'audio_');
        file_put_contents($audioPath, 'dummy-audio');

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
                    json_encode(['id' => 'transcript-uploaded-1'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ];
            }
        };

        $options = [
            // Must be ignored in upload mode
            'audio_url' => 'https://example.com/SHOULD_NOT_BE_SENT.mp3',
            'speech_models' => ['universal-2', 'universal-3-pro'],
            'test' => true,
        ];
        $extraPayload = [
            'language_code' => 'en',
            'punctuate' => false,
        ];

        $response = $client->transcribeRecordedFromFile($audioPath, $options, $extraPayload);

        $this->assertIsArray($response);
        $this->assertSame('transcript-uploaded-1', $response['id']);
        $this->assertSame('https://api.bitmesh.ai/transcribe-recorded', $client->captured['url']);

        $this->assertArrayHasKey('audio', $client->captured['multipartPostFields']);
        $this->assertInstanceOf(\CURLFile::class, $client->captured['multipartPostFields']['audio']);
        $this->assertArrayNotHasKey('audio_url', $client->captured['multipartPostFields']);

        preg_match('/oauth_signature="([^"]+)"/', $client->captured['authHeader'], $matches);
        $oauthSignature = rawurldecode($matches[1]);

        $nonFileFields = array_merge($options, $extraPayload);
        unset($nonFileFields['audio_url']);

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

    public function testTranscribeRecordedStatusBuildsGetRequestAndSignature()
    {
        $consumerKey = $this->getConsumerKey();
        $consumerSecret = $this->getConsumerSecret();

        $client = new class($consumerKey, $consumerSecret, 'https://api.bitmesh.ai') extends BitmeshClient {
            public array $captured = [];

            protected function sendGetRequest(string $url, string $authHeader, string $payloadSignature): array
            {
                $this->captured = [
                    'url' => $url,
                    'authHeader' => $authHeader,
                    'payloadSignature' => $payloadSignature,
                ];

                return [
                    200,
                    json_encode(['id' => 'job-1', 'status' => 'completed'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ];
            }
        };

        $response = $client->transcribeRecordedStatus('job-1', ['test' => true]);

        $this->assertIsArray($response);
        $this->assertSame('job-1', $response['id']);
        $this->assertSame('https://api.bitmesh.ai/transcribe-recorded/job-1?test=1', $client->captured['url']);

        preg_match('/oauth_signature="([^"]+)"/', $client->captured['authHeader'], $matches);
        $oauthSignature = rawurldecode($matches[1]);

        $expectedPayloadSignature = hash(
            'sha256',
            '' . $consumerKey . $oauthSignature
        );

        $this->assertSame($expectedPayloadSignature, $client->captured['payloadSignature']);
    }
}

