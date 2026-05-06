# Code Examples

Runnable examples for the Bitmesh PHP SDK. See [api-reference.md](api-reference.md) for full parameter and return details.

```php
<?php

require 'vendor/autoload.php';

use BitmeshAI\BitmeshClient;

$consumerKey = 'YOUR_CONSUMER_KEY';
$consumerSecret = 'YOUR_CONSUMER_SECRET';

$client = new BitmeshClient($consumerKey, $consumerSecret);
```

---

## Chat (simple)

One-shot chat: pass a single string. The SDK wraps it as a `"user"` message.

```php
$response = $client->chat('What are some fun things to do with AI?');

// Response shape depends on provider; typically has 'choices' and optionally 'usage'
echo $response['choices'][0]['message']['content'] ?? json_encode($response);
```

With an explicit model and options (e.g. `max_tokens`, `temperature`):

```php
$response = $client->chat(
    'Explain quantum computing in one sentence.',
    'meta-llama/Llama-3.2-3B-Instruct-Turbo',
    [
        'max_tokens' => 150,
        'temperature' => 0.7,
    ]
);
```

---

## Chat with previous chat history

Pass a full `messages` array to keep context (e.g. system prompt + prior user/assistant turns). Each item must have `role` and `content`.

```php
$messages = [
    [
        'role' => 'system',
        'content' => 'You are a helpful assistant that answers briefly.',
    ],
    [
        'role' => 'user',
        'content' => 'What is the capital of France?',
    ],
    [
        'role' => 'assistant',
        'content' => 'The capital of France is Paris.',
    ],
    [
        'role' => 'user',
        'content' => 'What is one famous landmark there?',
    ],
];

$response = $client->chat($messages);

$reply = $response['choices'][0]['message']['content'] ?? '';
echo $reply;
```

Building history from a previous response (append assistant reply, then next user message):

```php
// After a chat() call, you might have:
$previousMessages = [
    ['role' => 'user', 'content' => 'Hello'],
    ['role' => 'assistant', 'content' => 'Hi! How can I help?'],
];

$nextUserMessage = 'Tell me a short joke.';
$messages = array_merge($previousMessages, [
    ['role' => 'user', 'content' => $nextUserMessage],
]);

$response = $client->chat($messages);
$assistantReply = $response['choices'][0]['message']['content'] ?? '';
```

---

## Image

Generate images with a required `prompt`. Optionally pass `model` and options such as `width`, `height`, `steps`, `seed`, `n`.

```php
$response = $client->image('A sunset over the mountains');

// Response usually has 'data' with one or more items, each with 'url' (proxied)
$imageUrl = $response['data'][0]['url'] ?? null;
if ($imageUrl) {
    echo "Image URL: $imageUrl\n";
}
```

With model and options:

```php
$response = $client->image(
    'A red dragon in a fantasy landscape',
    'rundiffusion/juggernaut-lightning-flux',
    [
        'width' => 1024,
        'height' => 1024,
        'steps' => 1,
        'seed' => 42,
        'n' => 1,
    ]
);
```

---

## Video

Start a video generation job with a required `prompt`. The response includes a job `id` you can use with `videoStatus()` to poll for completion.

```php
$response = $client->video('Ocean waves at sunset');

$jobId = $response['id'] ?? null;
$status = $response['status'] ?? null;  // e.g. 'in_progress', 'queued'

echo "Job ID: $jobId, status: $status\n";
```

With model and options:

```php
$response = $client->video(
    'Test',
    'bytedance/seedance-1.0-lite',
    [
        'seed' => 13,
        'seconds' => '5',
        'width' => 1440,
        'height' => 1440,
    ]
);

$jobId = $response['id'];
// Use $jobId with videoStatus() to check progress and get the video URL
```

---

## Get video status

Use the job `id` returned from `video()` to fetch status and outputs (e.g. `video_url` when completed).

```php
$jobId = '019c65b5-b577-7963-84cd-0303456ecdf5';  // from video() response

$status = $client->videoStatus($jobId);

echo "Status: " . ($status['status'] ?? 'unknown') . "\n";

if (isset($status['outputs']['video_url'])) {
    echo "Video URL: " . $status['outputs']['video_url'] . "\n";
}
```

Polling until completed (example loop):

```php
$jobId = $response['id'];  // from video()

do {
    $status = $client->videoStatus($jobId);
    $state = $status['status'] ?? 'unknown';
    echo "Status: $state\n";

    if ($state === 'completed' || $state === 'failed') {
        break;
    }

    sleep(5);
} while (true);

if (($status['status'] ?? '') === 'completed' && isset($status['outputs']['video_url'])) {
    echo "Video ready: " . $status['outputs']['video_url'] . "\n";
}
```

---
 
## Transcribe (recorded audio)

### Transcribe from an audio URL

```php
$response = $client->transcribeRecordedFromUrl(
    'https://example.com/audio.mp3',
    [
        'language_code' => 'en',
        'speech_models' => ['universal-3-pro'],
    ]
);

$jobId = $response['id'] ?? null;
```

### Transcribe from an uploaded file

```php
$response = $client->transcribeRecordedFromFile(
    '/path/to/audio.mp3',
    [
        'speech_models' => ['universal-2'],
    ]
);

$jobId = $response['id'] ?? null;
```

### Poll transcription status

```php
$status = $client->transcribeRecordedStatus($jobId, ['test' => true]);

echo "Transcribe status: " . ($status['status'] ?? 'unknown') . "\n";
```

---

## Tools

### Background removal

```php
$response = $client->backgroundRemoval(
    '/path/to/input.jpg',
    'mask',
    ['test' => true]
);
```

### Query async task result

```php
$response = $client->queryAsyncTaskResult('task-abc');
```
