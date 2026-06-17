# Code Examples

Examples for `BitmeshAI\BitmeshClient`. The client always calls **`https://api.bitmesh.ai`**. Each method mirrors the HTTP API: you pass **payload arrays** (or file paths) that match the server contract. See [api-reference.md](api-reference.md) for behavior and errors.

```php
<?php

require 'vendor/autoload.php';

use BitmeshAI\BitmeshClient;

$consumerKey = 'YOUR_CONSUMER_KEY';
$consumerSecret = 'YOUR_CONSUMER_SECRET';

// Optional third argument: timeout in seconds (default 30)
$client = new BitmeshClient($consumerKey, $consumerSecret, 120);
```

---

## Chat

Send a JSON body as documented for `POST /chat`:

```php
$response = $client->chat([
    'model' => 'openai/gpt-4o-mini',
    'messages' => [
        ['role' => 'user', 'content' => 'Reply with exactly: ok'],
    ],
    'max_tokens' => 32,
    'temperature' => 0,
]);

echo $response['choices'][0]['message']['content'] ?? json_encode($response);
```

If your key uses a **fixed default model**, omit `model` so the server does not reject the request:

```php
$response = $client->chat([
    'messages' => [
        ['role' => 'user', 'content' => 'Hello'],
    ],
]);
```

Vision-style content is expressed in the payload as the API expects (nested `content` arrays with `image_url`, etc.):

```php
$response = $client->chat([
    'model' => 'google/gemma-3n-e4b-it',
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'What is in this image?'],
                [
                    'type' => 'image_url',
                    'image_url' => ['url' => 'https://placecats.com/600/400'],
                ],
            ],
        ],
    ],
    'max_tokens' => 256,
]);
```

---

## Image

```php
$response = $client->image([
    'prompt' => 'A red bicycle by a canal',
    'model' => 'wan-ai/wan2.6-image',
    'reference_images' => [
        'https://placecats.com/800/600',
    ],
]);

$jobOrData = $response; // shape depends on provider; often includes `id`
```

---

## Video

```php
$response = $client->video([
    'prompt' => 'Short cinematic scene',
    'model' => 'bytedance/seedance-1.0-lite',
    'frame_images' => [
        [
            'input_image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=1200',
            'frame' => 0,
        ],
    ],
]);

$id = $response['id'] ?? null;
```

Poll job status (`GET /video/{id}`) using the returned `id`:

```php
$videoId = (string) ($response['id'] ?? '');
if ($videoId !== '') {
    $status = $client->getVideo($videoId, [
        // optional query params if supported (e.g. test)
    ]);
    echo $status['status'] ?? json_encode($status);
}
```

---

## Image-to-video (DomoAI: create + poll)

Submit a still image and animate it (`POST /tools/video/image-animate`). The image goes in the JSON payload as base64:

```php
$create = $client->imageAnimate([
    'model' => 'animate-2.4-faster',          // or animate-2.4-advanced
    'image' => [
        'bytes_base64_encoded' => base64_encode((string) file_get_contents('/path/to/photo.jpg')),
        // or: 'domoai_uri' => 'domoai://...'
    ],
    'seconds' => 5,                            // 1..10
    'prompt' => 'gentle motion, cinematic',    // optional
    'aspect_ratio' => '9:16',                  // optional: 16:9|9:16|1:1|4:3|3:4
    // 'callback_url' => 'https://you.example/webhook', // optional: receive results via webhook
    // 'test' => true,                         // optional: no DomoAI call, no charge
]);

$taskId = (string) ($create['data']['task_id'] ?? '');
```

Poll until terminal (`GET /tools/video/image-animate/{task_id}`):

```php
do {
    sleep(4);
    $status = $client->getImageAnimate($taskId);   // pass ['test' => 1] to poll in test mode
    $state = $status['data']['status'] ?? 'UNKNOWN';
} while (! in_array($state, ['SUCCESS', 'FAILED', 'CANCELED'], true));

if ($state === 'SUCCESS') {
    $videoUrl = $status['data']['output_videos'][0]['url'] ?? null; // time-limited; download promptly
}
```

Alternatively, pass `callback_url` on create and the proxy POSTs each DomoAI status update to it.

---

## Transcription (upload + poll)

Submit a local file (`POST /transcribe-recorded`):

```php
$response = $client->transcribeFile('/path/to/recording.mp3', [
    'speech_models' => ['universal-2'],
]);

$transcriptId = $response['id'] ?? null;
```

Poll status or result (`GET /transcribe-recorded/{id}`):

```php
$status = $client->getTranscribeRecorded($transcriptId, [
    // optional query params, e.g. test flags if supported
]);

echo $status['status'] ?? 'unknown';
```

---

## Tools – background removal + download result

```php
$result = $client->toolsGeneralBackgroundRemoval(
    ['return_form' => 'mask'],
    '/path/to/input.png'
);

// Typical shape includes data.image_url (absolute URL with /tools-result/...)
$imageUrl = $result['data']['image_url'] ?? null;

$path = parse_url((string) $imageUrl, PHP_URL_PATH);
$prefix = '/tools-result/';
$relative = ltrim(substr((string) $path, strpos((string) $path, $prefix) + strlen($prefix)), '/');

$bytes = $client->getToolsResult($relative);
file_put_contents('/tmp/mask.png', $bytes);
```

---

## Tools – try-on (async) + query task

```php
$tryOn = $client->toolsPortraitTryOnClothes(
    [
        'task_type' => 'async',
        'resolution' => '-1',
        'restore_face' => 'true',
    ],
    [
        'person_image' => '/path/to/person.png',
        'top_garment' => '/path/to/top.png',
        'bottom_garment' => '/path/to/bottom.png',
    ]
);

$taskId = (string) ($tryOn['task_id'] ?? '');

$query = $client->toolsQueryAsyncTaskResult($taskId);
echo $query['status'] ?? json_encode($query);
```

Use the `task_id` returned from a successful async try-on submission when polling.

---

## Error handling

```php
use RuntimeException;

try {
    $out = $client->chat(['messages' => [['role' => 'user', 'content' => 'Hi']]]);
} catch (RuntimeException $e) {
    // HTTP errors include status and body in the message
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
}
```
