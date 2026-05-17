<?php

namespace BitmeshAI\Tests;

class BitmeshClientChatTest extends BitmeshClientTestCase
{
    public function test_chat_calls_api(): void
    {
        $client = $this->createClient(120);

        $payload = [
            'model' => 'openai/gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Reply with exactly: sdk-ok',
                ],
            ],
            'max_tokens' => 16,
            'temperature' => 0,
        ];

        try {
            $response = $client->chat($payload);
        } catch (\RuntimeException $exception) {
            if (strpos($exception->getMessage(), 'This API key uses a fixed model') === false) {
                throw $exception;
            }

            unset($payload['model']);
            $response = $client->chat($payload);
        }

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Expected non-empty response from /chat.');
        $this->assertArrayHasKey('id', $response);
        $this->assertNotEmpty((string) $response['id'], 'Expected id in /chat response.');
    }

    public function test_chat_with_image_url_calls_api(): void
    {
        $client = $this->createClient(120);

        $payload = [
            'model' => 'google/gemma-3n-e4b-it',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a riddle solver.'],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'Describe the image in one short phrase.'],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => 'https://placecats.com/600/400'],
                        ],
                    ],
                ],
            ],
            'max_tokens' => 64,
        ];

        $response = $client->chat($payload);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertNotEmpty((string) $response['id']);
    }
}
