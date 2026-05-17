<?php

namespace BitmeshAI\Tests;

class BitmeshClientToolsTryOnClothesTest extends BitmeshClientTestCase
{
    public function test_tools_portrait_try_on_clothes_calls_api(): void
    {
        $personPath = $this->fixturePath('person.png');
        $topGarmentPath = $this->fixturePath('top_garment.png');
        $bottomGarmentPath = $this->fixturePath('bottom_garment.png');
        $this->assertFileExists($personPath);
        $this->assertFileExists($topGarmentPath);
        $this->assertFileExists($bottomGarmentPath);

        $client = $this->createClient(120);

        $response = $client->toolsPortraitTryOnClothes([
            'task_type' => 'async',
            'resolution' => '-1',
            'restore_face' => 'true',
        ], [
            'person_image' => $personPath,
            'top_garment' => $topGarmentPath,
            'bottom_garment' => $bottomGarmentPath,
        ]);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('task_id', $response);
        $this->assertNotEmpty((string) $response['task_id'], 'Expected task_id in try-on response.');
    }
}
