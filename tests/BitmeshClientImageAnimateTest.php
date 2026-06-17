<?php

namespace BitmeshAI\Tests;

class BitmeshClientImageAnimateTest extends BitmeshClientTestCase
{
    public function test_image_animate_create_and_poll_in_test_mode(): void
    {
        $imagePath = $this->fixturePath('person.png');
        $this->assertFileExists($imagePath);

        $client = $this->createClient(120);

        // test mode: exercises the full signed round-trip without DomoAI calls or charges.
        $create = $client->imageAnimate([
            'model' => 'animate-2.4-faster',
            'image' => ['bytes_base64_encoded' => base64_encode((string) file_get_contents($imagePath))],
            'seconds' => 5,
            'test' => true,
        ]);

        $this->assertIsArray($create);
        $taskId = (string) ($create['data']['task_id'] ?? '');
        $this->assertNotEmpty($taskId, 'Expected data.task_id from /tools/video/image-animate.');

        $status = $client->getImageAnimate($taskId, ['test' => 1]);

        $this->assertIsArray($status);
        $this->assertSame('SUCCESS', $status['data']['status'] ?? null);
        $this->assertNotEmpty($status['data']['output_videos'][0]['url'] ?? null, 'Expected an output video url.');
    }
}
