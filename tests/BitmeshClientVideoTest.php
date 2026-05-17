<?php

namespace BitmeshAI\Tests;

/**
 * @group expensive
 */
class BitmeshClientVideoTest extends BitmeshClientTestCase
{
    public function test_video_calls_api(): void
    {
        $client = $this->createClient(180);

        $response = $client->video([
            'prompt' => 'Make this car run fast',
            'model' => 'bytedance/seedance-1.0-lite',
            'frame_images' => [
                [
                    'input_image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=1470&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
                    'frame' => 0,
                ],
            ],
        ]);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Expected non-empty response from /video.');
        $this->assertArrayHasKey('id', $response);
        $this->assertNotEmpty((string) $response['id'], 'Expected id in /video response.');
    }
}
