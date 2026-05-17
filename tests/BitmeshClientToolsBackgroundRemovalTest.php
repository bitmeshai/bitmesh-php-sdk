<?php

namespace BitmeshAI\Tests;

class BitmeshClientToolsBackgroundRemovalTest extends BitmeshClientTestCase
{
    public function test_tools_general_background_removal_and_get_tools_result(): void
    {
        $imagePath = $this->fixturePath('stool.png');
        $this->assertFileExists($imagePath);

        $client = $this->createClient(120);

        $response = $client->toolsGeneralBackgroundRemoval(['return_form' => 'mask'], $imagePath);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
        $this->assertNotEmpty($response['data']['image_url'] ?? null, 'image_url should be present and non-empty');

        $resultPath = $this->toolsResultPathFromImageUrl((string) $response['data']['image_url']);
        $imageBytes = $client->getToolsResult($resultPath);
        $this->assertNotEmpty($imageBytes, 'Expected non-empty proxied tools-result image body.');
    }
}
