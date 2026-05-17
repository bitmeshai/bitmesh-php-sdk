<?php

namespace BitmeshAI\Tests;

class BitmeshClientTranscribeTest extends BitmeshClientTestCase
{
    public function test_transcribe_file_calls_api(): void
    {
        $audioPath = $this->fixturePath('test_audio.mp3');
        $this->assertFileExists($audioPath, 'Expected transcribe fixture: '.$audioPath);

        $client = $this->createClient(600);

        $response = $client->transcribeFile($audioPath, [
            'speech_models' => ['universal-2'],
        ]);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response, 'Expected non-empty response from /transcribe-recorded.');
        $this->assertArrayHasKey('id', $response);
        $this->assertNotEmpty((string) $response['id'], 'Expected transcript id in transcribe response.');
    }

    public function test_get_transcribe_recorded_calls_api(): void
    {
        $audioPath = $this->fixturePath('test_audio.mp3');
        $this->assertFileExists($audioPath);

        $client = $this->createClient(600);

        $accepted = $client->transcribeFile($audioPath, [
            'speech_models' => ['universal-2'],
        ]);
        $this->assertArrayHasKey('id', $accepted);
        $transcriptId = (string) $accepted['id'];
        $this->assertNotEmpty($transcriptId);

        $statusPayload = $client->getTranscribeRecorded($transcriptId);

        $this->assertIsArray($statusPayload);
        $this->assertNotEmpty($statusPayload, 'Expected non-empty response from GET transcribe-recorded/{id}.');
        $this->assertArrayHasKey('status', $statusPayload);
        $this->assertNotEmpty((string) $statusPayload['status'], 'Expected transcript status in poll response.');
    }
}
