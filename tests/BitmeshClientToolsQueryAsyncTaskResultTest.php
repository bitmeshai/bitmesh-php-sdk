<?php

namespace BitmeshAI\Tests;

use BitmeshAI\BitmeshClient;

class BitmeshClientToolsQueryAsyncTaskResultTest extends BitmeshClientTestCase
{
    public function testQueryAsyncTaskResultBuildsPayloadAndSignature()
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
                        'task_id' => 'task-abc',
                        'status' => 'completed',
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ];
            }
        };

        $response = $client->queryAsyncTaskResult('task-abc', [
            'foo' => 'bar',
        ]);

        $this->assertIsArray($response);
        $this->assertSame('completed', $response['status']);
        $this->assertSame('https://api.bitmesh.ai/tools/query-async-task-result', $client->captured['url']);

        $decodedBody = json_decode($client->captured['jsonBody'], true);
        $this->assertSame('task-abc', $decodedBody['task_id']);
        $this->assertSame('bar', $decodedBody['foo']);

        preg_match('/oauth_signature="([^"]+)"/', $client->captured['authHeader'], $matches);
        $oauthSignature = rawurldecode($matches[1]);

        $expectedPayloadSignature = hash(
            'sha256',
            $client->captured['jsonBody'] . $consumerKey . $oauthSignature
        );

        $this->assertSame($expectedPayloadSignature, $client->captured['payloadSignature']);
    }
}

