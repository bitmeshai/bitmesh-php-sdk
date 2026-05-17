<?php

namespace BitmeshAI\Tests;

use BitmeshAI\BitmeshClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BitmeshClientValidationTest extends TestCase
{
    public function test_get_transcribe_recorded_rejects_empty_id(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transcription job id is required.');

        $client = new BitmeshClient('key', 'secret');
        $client->getTranscribeRecorded('  ');
    }

    public function test_tools_query_async_task_result_rejects_empty_task_id(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Task id is required.');

        $client = new BitmeshClient('key', 'secret');
        $client->toolsQueryAsyncTaskResult("\t");
    }

    public function test_get_tools_result_rejects_empty_path(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tools result path is required.');

        $client = new BitmeshClient('key', 'secret');
        $client->getToolsResult('');
    }

    public function test_tools_portrait_try_on_rejects_missing_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File does not exist or is not readable');

        $client = new BitmeshClient('key', 'secret');
        $client->toolsPortraitTryOnClothes([], ['person_image' => '/no/such/file.png']);
    }
}
