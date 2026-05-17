<?php

namespace BitmeshAI\Tests;

class BitmeshClientImageTest extends BitmeshClientTestCase
{
    public function test_image_calls_api(): void
    {
        $client = $this->createClient(120);

        $response = $client->image([
            'prompt' => 'Make this cat smile',
            'model' => 'wan-ai/wan2.6-image',
            'reference_images' => [
                'https://placecats.com/800/600',
            ],
        ]);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Expected non-empty response from /image.');
        $this->assertArrayHasKey('id', $response);
        $this->assertNotEmpty((string) $response['id'], 'Expected id in /image response.');
    }
}
