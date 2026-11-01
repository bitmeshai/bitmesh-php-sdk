<?php

namespace BitmeshAI\Tests;

class BitmeshClientToolsBackgroundRemovalTest extends BitmeshClientTestCase
{
    public function test_tools_general_background_removal_and_download_result(): void
    {
        $imagePath = $this->fixturePath('stool.png');
        $this->assertFileExists($imagePath);

        $client = $this->createClient(120);

        $response = $client->toolsGeneralBackgroundRemoval(['return_form' => 'mask'], $imagePath);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('success', $response['status']);
        $this->assertNotEmpty($response['data']['image_url'] ?? null, 'image_url should be present and non-empty');

        // The link is plain and unsigned: fetch it exactly as returned.
        $imageBytes = @file_get_contents((string) $response['data']['image_url']);
        $this->assertNotFalse($imageBytes, 'Could not download '.$response['data']['image_url']);
        // PNG by default; a mask can come back as JPEG.
        $this->assertMatchesRegularExpression('/^(\x89PNG|\xFF\xD8\xFF)/', (string) $imageBytes, 'Expected a PNG or JPEG result.');
    }
}
