<?php

declare(strict_types=1);

namespace BitmeshExample;

use BitmeshAI\BitmeshClient;

final class Router
{
    private const ROUTES = [
        '/' => 'home',
        '/chat' => 'chat',
        '/chat-vision' => 'chatVision',
        '/image' => 'image',
        '/image-to-image' => 'imageToImage',
        '/video' => 'video',
        '/video-status' => 'videoStatus',
        '/transcribe-file' => 'transcribeFile',
        '/transcribe-status' => 'transcribeStatus',
        '/tool-bgremove' => 'toolBgremove',
        '/tool-tryon' => 'toolTryon',
        '/tool-tryon-query' => 'toolTryonQuery',
    ];

    public function match(string $path): ?string
    {
        $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?? '', '/');
        if ($path === '') {
            $path = '/';
        }

        return self::ROUTES[$path] ?? null;
    }

    public function dispatch(string $path): void
    {
        $action = $this->match($path);

        if ($action === null) {
            http_response_code(404);
            echo $this->renderLayout('Not found', '<p>Page not found. Try <a href="/chat">/chat</a>, <a href="/chat-vision">/chat-vision</a>, <a href="/image">/image</a>, <a href="/image-to-image">/image-to-image</a>, <a href="/video">/video</a>, <a href="/video-status">/video-status</a>, <a href="/transcribe-file">/transcribe-file</a>, <a href="/transcribe-status">/transcribe-status</a>, <a href="/tool-bgremove">/tool-bgremove</a>, <a href="/tool-tryon">/tool-tryon</a>.</p>');
            return;
        }

        $this->{$action}();
    }

    private function home(): void
    {
        echo $this->renderLayout('Home', '<h1>Bitmesh Demo</h1><p>Choose: <a href="/chat">Chat</a>, <a href="/chat-vision">Chat Vision</a>, <a href="/image">Image</a>, <a href="/image-to-image">Image to Image</a>, <a href="/video">Video</a>, <a href="/video-status">Video status</a>, <a href="/transcribe-file">Transcribe file</a>, <a href="/transcribe-status">Transcribe status</a>, <a href="/tool-bgremove">Background removal</a>, <a href="/tool-tryon">Try-on clothes</a>.</p>');
    }

    private function chat(): void
    {
        echo $this->renderLayout('Chat', $this->renderChat());
    }

    private function chatVision(): void
    {
        echo $this->renderLayout('Chat Vision', $this->renderChatVision());
    }

    private function image(): void
    {
        echo $this->renderLayout('Image', $this->renderImage());
    }

    private function imageToImage(): void
    {
        echo $this->renderLayout('Image to Image', $this->renderImageToImage());
    }

    private function video(): void
    {
        echo $this->renderLayout('Video', $this->renderVideo());
    }

    private function videoStatus(): void
    {
        echo $this->renderLayout('Video status', $this->renderVideoStatus());
    }

    private function transcribeFile(): void
    {
        echo $this->renderLayout('Transcribe file', $this->renderTranscribeFile());
    }

    private function transcribeStatus(): void
    {
        echo $this->renderLayout('Transcribe status', $this->renderTranscribeStatus());
    }

    private function toolBgremove(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handleToolBgremovePost();
            return;
        }
        echo $this->renderLayout('Background removal', $this->renderToolBgremove());
    }

    private function toolTryon(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handleToolTryonPost();
            return;
        }
        echo $this->renderLayout('Try-on clothes', $this->renderToolTryon());
    }

    private function toolTryonQuery(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'Method Not Allowed']);
            return;
        }
        $this->handleToolTryonQueryPost();
    }

    private function renderLayout(string $title, string $body): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitmesh Demo – {$title}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
        nav { margin-bottom: 1.5rem; }
        nav a { margin-right: 1rem; }
        h1 { font-size: 1.25rem; }
        pre { background: #f4f4f4; padding: 1rem; overflow-x: auto; font-size: 0.875rem; }
        .error { color: #c00; }
        code { background: #eee; padding: 0.2em 0.4em; font-size: 0.9em; }
    </style>
</head>
<body>
    <nav>
        <a href="/chat">Chat</a>
        <a href="/chat-vision">Chat Vision</a>
        <a href="/image">Image</a>
        <a href="/image-to-image">Image to Image</a>
        <a href="/video">Video</a>
        <a href="/transcribe-file">Transcribe File</a>
        <a href="/transcribe-status">Transcribe Status</a>
        <a href="/tool-bgremove">Background removal</a>
        <a href="/tool-tryon">Try-on clothes</a>
    </nav>
    {$body}
</body>
</html>
HTML;
    }

    private function renderChat(): string
    {
        $snippet = <<<'PHP'
<?php

require 'vendor/autoload.php';

use BitmeshAI\BitmeshClient;

$consumerKey = getenv('BITMESH_CONSUMER_KEY') ?: 'YOUR_CONSUMER_KEY';
$consumerSecret = getenv('BITMESH_CONSUMER_SECRET') ?: 'YOUR_CONSUMER_SECRET';

// Optional third argument: timeout in seconds (default 30). BitmeshClient always calls https://api.bitmesh.ai.
$client = new BitmeshClient($consumerKey, $consumerSecret, 120);

// POST /chat — pass the JSON body the API expects (model, messages, max_tokens, …).
$response = $client->chat([
    'model' => 'google/gemma-3n-e4b-it',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'What are some fun things to do with AI?'],
    ],
    'max_tokens' => 1000,
]);

// If your API key uses a fixed default model, omit 'model' so the server does not reject the request:
// $response = $client->chat([
//     'messages' => [['role' => 'user', 'content' => 'Hello']],
//     'max_tokens' => 256,
// ]);

// Response has 'choices' and optionally 'usage'
$content = $response['choices'][0]['message']['content'] ?? json_encode($response);
echo $content;
PHP;
        $html = "<h1>Chat</h1><p>Basic text-only PHP example using the Bitmesh SDK.</p>";
        $html .= "<h2>Example code</h2><pre>" . htmlspecialchars($snippet) . "</pre>";

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        if ($consumerKey !== '' && $consumerSecret !== '') {
            try {
                $client = new BitmeshClient($consumerKey, $consumerSecret, 120);
                $response = $client->chat([
                    'model' => 'google/gemma-3n-e4b-it',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                        ['role' => 'user', 'content' => 'Say "Hello from Bitmesh" in one short sentence.'],
                    ],
                    'max_tokens' => 1000,
                ]);
                $html .= "<h2>API response</h2><pre>" . htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "</pre>";
                $content = $response['choices'][0]['message']['content'] ?? null;
                if ($content !== null) {
                    $html .= "<p><strong>Assistant reply:</strong> " . htmlspecialchars($content) . "</p>";
                }
            } catch (\Throwable $e) {
                $html .= "<h2>API response</h2><p class=\"error\">Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            $html .= "<h2>API response</h2><p>Set <code>BITMESH_CONSUMER_KEY</code> and <code>BITMESH_CONSUMER_SECRET</code> in your environment to see a live response above.</p>";
        }

        return $html;
    }

    private function renderChatVision(): string
    {
        $snippet = <<<'PHP'
<?php

require 'vendor/autoload.php';

use BitmeshAI\BitmeshClient;

$consumerKey = getenv('BITMESH_CONSUMER_KEY') ?: 'YOUR_CONSUMER_KEY';
$consumerSecret = getenv('BITMESH_CONSUMER_SECRET') ?: 'YOUR_CONSUMER_SECRET';

$client = new BitmeshClient($consumerKey, $consumerSecret, 120);

// Vision-style messages: nested content with text + image_url blocks.
$response = $client->chat([
    'model' => 'google/gemma-3n-e4b-it',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'solve this riddle'],
                ['type' => 'image_url', 'image_url' => ['url' => 'https://cdn1.byjus.com/wp-content/uploads/2020/10/maths-puzzles-example-2-solution.png']],
            ],
        ],
    ],
    'max_tokens' => 1000,
]);

// Response has 'choices' and optionally 'usage'
$content = $response['choices'][0]['message']['content'] ?? json_encode($response);
echo $content;
PHP;
        $html = "<h1>Chat Vision</h1><p>Multimodal PHP example using structured message content.</p>";
        $html .= "<h2>Example code</h2><pre>" . htmlspecialchars($snippet) . "</pre>";

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        if ($consumerKey !== '' && $consumerSecret !== '') {
            try {
                $client = new BitmeshClient($consumerKey, $consumerSecret, 120);
                $response = $client->chat([
                    'model' => 'google/gemma-3n-e4b-it',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => 'solve this riddle'],
                                ['type' => 'image_url', 'image_url' => ['url' => 'https://cdn1.byjus.com/wp-content/uploads/2020/10/maths-puzzles-example-2-solution.png']],
                            ],
                        ],
                    ],
                    'max_tokens' => 1000,
                ]);
                $html .= "<h2>API response</h2><pre>" . htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "</pre>";
                $content = $response['choices'][0]['message']['content'] ?? null;
                if ($content !== null) {
                    $html .= "<p><strong>Assistant reply:</strong> " . htmlspecialchars($content) . "</p>";
                }
            } catch (\Throwable $e) {
                $html .= "<h2>API response</h2><p class=\"error\">Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            $html .= "<h2>API response</h2><p>Set <code>BITMESH_CONSUMER_KEY</code> and <code>BITMESH_CONSUMER_SECRET</code> in your environment to see a live response above.</p>";
        }

        return $html;
    }

    private function renderImage(): string
    {
        $snippet = <<<'PHP'
<?php

require 'vendor/autoload.php';

use BitmeshAI\BitmeshClient;

$consumerKey = getenv('BITMESH_CONSUMER_KEY') ?: 'YOUR_CONSUMER_KEY';
$consumerSecret = getenv('BITMESH_CONSUMER_SECRET') ?: 'YOUR_CONSUMER_SECRET';

$client = new BitmeshClient($consumerKey, $consumerSecret, 180);

// POST /image — pass prompt, model, and provider-specific options.
$response = $client->image([
    'prompt' => 'A serene mountain landscape at sunset',
    'model' => 'rundiffusion/juggernaut-lightning-flux',
    'width' => 1024,
    'height' => 1024,
]);

// Image URL(s) are often in data[].url (shape depends on provider)
$url = $response['data'][0]['url'] ?? null;
if ($url) {
    echo '<img src="' . htmlspecialchars($url) . '" alt="Generated" />';
}
PHP;
        $html = "<h1>Image</h1><p>Basic PHP example using the Bitmesh SDK with <code>rundiffusion/juggernaut-lightning-flux</code>.</p>";
        $html .= "<h2>Example code</h2><pre>" . htmlspecialchars($snippet) . "</pre>";

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        if ($consumerKey !== '' && $consumerSecret !== '') {
            try {
                $client = new BitmeshClient($consumerKey, $consumerSecret, 180);
                $response = $client->image([
                    'prompt' => 'A cute robot reading a book, digital art',
                    'model' => 'rundiffusion/juggernaut-lightning-flux',
                    'width' => 1024,
                    'height' => 1024,
                ]);
                $html .= "<h2>API response</h2><pre>" . htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "</pre>";
                $data = $response['data'] ?? [];
                if (is_array($data) && $data !== []) {
                    $html .= "<h2>Generated image</h2>";
                    foreach ($data as $item) {
                        $url = $item['url'] ?? null;
                        if ($url !== null && $url !== '') {
                            $html .= '<p><img src="' . htmlspecialchars($url) . '" alt="Generated" style="max-width:100%;height:auto;border:1px solid #ddd;" /></p>';
                        }
                    }
                }
            } catch (\Throwable $e) {
                $html .= "<h2>API response</h2><p class=\"error\">Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            $html .= "<h2>API response</h2><p>Set <code>BITMESH_CONSUMER_KEY</code> and <code>BITMESH_CONSUMER_SECRET</code> in your environment to see a live response above.</p>";
        }

        return $html;
    }

    private function renderImageToImage(): string
    {
        $snippet = <<<'PHP'
<?php

require 'vendor/autoload.php';

use BitmeshAI\BitmeshClient;

$consumerKey = getenv('BITMESH_CONSUMER_KEY') ?: 'YOUR_CONSUMER_KEY';
$consumerSecret = getenv('BITMESH_CONSUMER_SECRET') ?: 'YOUR_CONSUMER_SECRET';

$client = new BitmeshClient($consumerKey, $consumerSecret, 180);

$response = $client->image([
    'prompt' => 'Make this cat smile',
    'model' => 'wan-ai/wan2.6-image',
    'reference_images' => [
        'https://placecats.com/800/600',
    ],
]);

$url = $response['data'][0]['url'] ?? null;
if ($url) {
    echo '<img src="' . htmlspecialchars($url) . '" alt="Generated" />';
}
PHP;
        $html = "<h1>Image to Image</h1><p>Generate an image using model <code>wan-ai/wan2.6-image</code> with one <code>reference_images</code> URL.</p>";
        $html .= "<h2>Example code</h2><pre>" . htmlspecialchars($snippet) . "</pre>";

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        if ($consumerKey !== '' && $consumerSecret !== '') {
            try {
                $client = new BitmeshClient($consumerKey, $consumerSecret, 180);
                $response = $client->image([
                    'prompt' => 'Make this cat smile',
                    'model' => 'wan-ai/wan2.6-image',
                    'reference_images' => [
                        'https://placecats.com/800/600',
                    ],
                ]);
                $html .= "<h2>API response</h2><pre>" . htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "</pre>";
                $data = $response['data'] ?? [];
                if (is_array($data) && $data !== []) {
                    $html .= "<h2>Generated image</h2>";
                    foreach ($data as $item) {
                        $url = $item['url'] ?? null;
                        if ($url !== null && $url !== '') {
                            $html .= '<p><img src="' . htmlspecialchars($url) . '" alt="Generated" style="max-width:100%;height:auto;border:1px solid #ddd;" /></p>';
                        }
                    }
                }
            } catch (\Throwable $e) {
                $html .= "<h2>API response</h2><p class=\"error\">Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            $html .= "<h2>API response</h2><p>Set <code>BITMESH_CONSUMER_KEY</code> and <code>BITMESH_CONSUMER_SECRET</code> in your environment to see a live response above.</p>";
        }

        return $html;
    }

    private function renderVideo(): string
    {
        $snippet = <<<'PHP'
<?php

require 'vendor/autoload.php';

use BitmeshAI\BitmeshClient;

$consumerKey = getenv('BITMESH_CONSUMER_KEY') ?: 'YOUR_CONSUMER_KEY';
$consumerSecret = getenv('BITMESH_CONSUMER_SECRET') ?: 'YOUR_CONSUMER_SECRET';

$client = new BitmeshClient($consumerKey, $consumerSecret, 180);

// POST /video — payload keys depend on the provider (often prompt, model, frame_images, …).
$response = $client->video([
    'prompt' => 'A cat walking in the rain, cinematic',
    'model' => 'minimax/video-01-director',
    'frame_images' => [
        [
            'input_image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=1200&auto=format&fit=crop',
            'frame' => 0,
        ],
    ],
]);

$id = $response['id'] ?? null;
$status = $response['status'] ?? null;
// BitmeshClient does not include a video job poll helper; follow provider docs or dashboard for status.
PHP;
        $html = "<h1>Video</h1><p>Start a video generation request with model <code>bytedance/seedance-1.0-lite</code> and starter <code>frame_images</code>. The PHP SDK does not ship a separate poll helper for jobs; use the API or dashboard for follow-up status.</p>";
        $html .= "<h2>Example code</h2><pre>" . htmlspecialchars($snippet) . "</pre>";

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        if ($consumerKey !== '' && $consumerSecret !== '') {
            try {
                $client = new BitmeshClient($consumerKey, $consumerSecret, 180);
                $response = $client->video([
                    'prompt' => 'A cat walking in the rain, cinematic, 4k',
                    'model' => 'minimax/video-01-director',
                    'frame_images' => [
                        [
                            'input_image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?q=80&w=1200&auto=format&fit=crop',
                            'frame' => 0,
                        ],
                    ],
                ]);
                $html .= "<h2>API response</h2><pre>" . htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "</pre>";
                $id = $response['id'] ?? null;
                $status = $response['status'] ?? null;
                if ($id !== null && $id !== '') {
                    $statusEsc = htmlspecialchars($status ?? '');
                    $html .= "<p><strong>Status:</strong> " . $statusEsc . "</p>";
                    $html .= "<p>Job id: <code>" . htmlspecialchars((string) $id) . "</code>. Poll or finalize the job using the Bitmesh HTTP API or your account tools; see the SDK API reference.</p>";
                }
            } catch (\Throwable $e) {
                $html .= "<h2>API response</h2><p class=\"error\">Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            $html .= "<h2>API response</h2><p>Set <code>BITMESH_CONSUMER_KEY</code> and <code>BITMESH_CONSUMER_SECRET</code> in your environment to start a video and see a link to check progress.</p>";
        }

        return $html;
    }

    private function renderVideoStatus(): string
    {
        $snippet = <<<'PHP'
<?php
// Poll after $client->video([...]) returns a job id
$status = $client->getVideo((string) $id, [
    // optional query params if supported
]);
PHP;
        $html = "<h1>Video status</h1><p>Poll a video job with <code>GET /video/{id}</code> via <code>BitmeshClient::getVideo()</code>.</p>";
        $html .= "<h2>Example code</h2><pre>" . htmlspecialchars($snippet) . "</pre>";

        if (isset($_GET['id']) && trim((string) $_GET['id']) !== '') {
            $id = trim((string) $_GET['id']);
            $html .= "<p>You passed id <code>" . htmlspecialchars($id) . "</code> — use that value when calling the HTTP poll endpoint outside this demo client.</p>";
        } else {
            $html .= "<p>Start a job on the <a href=\"/video\">Video</a> page, then use the returned id with your own integration.</p>";
        }

        $html .= '<p><a href="/video">Back to Video</a></p>';

        return $html;
    }

    private function renderTranscribeFile(): string
    {
        ini_set('upload_max_filesize', '64M');
        ini_set('post_max_size', '64M');

        $snippet = <<<'PHP'
<?php
// Upload local audio: POST /transcribe-recorded (multipart). Optional extra form fields as second arg.
$response = $client->transcribeFile('/path/to/audio.mp3', [
    'speech_models' => ['universal-2'],
]);
$id = $response['id'] ?? null;
PHP;
        $html = "<h1>Transcribe file</h1><p>Upload an audio file and call <code>transcribeFile()</code>.</p>";
        $html .= "<h2>Example code</h2><pre>" . htmlspecialchars($snippet) . "</pre>";

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        $html .= '<h2>Try it</h2><form method="post" enctype="multipart/form-data"><p><label for="audio">Audio file</label><br><input id="audio" type="file" name="audio" accept="audio/*" required></p><p><button type="submit">Transcribe file</button></p></form>';

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return $html;
        }

        if ($consumerKey === '' || $consumerSecret === '') {
            $html .= "<p class=\"error\">Set <code>BITMESH_CONSUMER_KEY</code> and <code>BITMESH_CONSUMER_SECRET</code> in your environment.</p>";
            return $html;
        }

        if (!isset($_FILES['audio']) || !is_array($_FILES['audio'])) {
            $html .= "<p class=\"error\">Please choose an audio file.</p>";
            return $html;
        }

        $file = $_FILES['audio'];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                $html .= "<p class=\"error\">Please choose an audio file.</p>";
                return $html;
            }
            if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                $maxFile = (string) ini_get('upload_max_filesize');
                $maxPost = (string) ini_get('post_max_size');
                $html .= "<p class=\"error\">Upload failed: file is too large for current PHP limits (<code>upload_max_filesize=" . htmlspecialchars($maxFile) . "</code>, <code>post_max_size=" . htmlspecialchars($maxPost) . "</code>).</p>";
                return $html;
            }
            $html .= "<p class=\"error\">Upload failed (code " . $uploadError . ").</p>";
            return $html;
        }

        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            $html .= "<p class=\"error\">Upload failed: temporary file not found.</p>";
            return $html;
        }

        try {
            $client = new BitmeshClient($consumerKey, $consumerSecret, 600);
            $response = $client->transcribeFile((string) $file['tmp_name'], [
                'speech_models' => ['universal-2'],
            ]);
            $html .= "<h2>API response</h2><pre>" . htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "</pre>";
            $id = $response['id'] ?? null;
            if ($id !== null && $id !== '') {
                $html .= '<p><a href="/transcribe-status?id=' . htmlspecialchars(rawurlencode((string) $id)) . '">Check transcription status</a></p>';
            }
        } catch (\Throwable $e) {
            $html .= "<h2>API response</h2><p class=\"error\">Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }

        return $html;
    }

    private function renderTranscribeStatus(): string
    {
        $snippet = <<<'PHP'
<?php
// Poll: GET /transcribe-recorded/{id}
$response = $client->getTranscribeRecorded($id);
PHP;
        $html = "<h1>Transcribe status</h1><p>Check a transcription job using <code>getTranscribeRecorded()</code>.</p>";
        $html .= "<h2>Example code</h2><pre>" . htmlspecialchars($snippet) . "</pre>";

        $id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
        $html .= '<h2>Check status</h2><form method="get" action="/transcribe-status"><p><label for="id">Transcribe ID</label><br><input id="id" type="text" name="id" value="' . htmlspecialchars($id) . '" placeholder="Enter transcription id" required style="width:100%;max-width:480px;"></p><p><button type="submit">Get status</button></p></form>';
        if ($id === '') {
            return $html;
        }

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        if ($consumerKey === '' || $consumerSecret === '') {
            $html .= "<p class=\"error\">Set <code>BITMESH_CONSUMER_KEY</code> and <code>BITMESH_CONSUMER_SECRET</code> in your environment.</p>";
            return $html;
        }

        try {
            $client = new BitmeshClient($consumerKey, $consumerSecret, 120);
            $response = $client->getTranscribeRecorded($id);
            $html .= "<h2>API response</h2><pre>" . htmlspecialchars(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "</pre>";
            if (isset($response['status'])) {
                $html .= "<p><strong>Status:</strong> " . htmlspecialchars((string) $response['status']) . "</p>";
            }
            $html .= '<p><a href="/transcribe-file">Back to Transcribe File</a></p>';
        } catch (\Throwable $e) {
            $html .= "<h2>API response</h2><p class=\"error\">Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }

        return $html;
    }

    private function handleToolBgremovePost(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        if ($consumerKey === '' || $consumerSecret === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Set BITMESH_CONSUMER_KEY and BITMESH_CONSUMER_SECRET in your environment.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!isset($_FILES['image']) || !is_uploaded_file((string) ($_FILES['image']['tmp_name'] ?? ''))) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Please choose an image file.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $file = $_FILES['image'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Upload failed (code ' . (int) $file['error'] . ').'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $returnForm = isset($_POST['return_form']) ? trim((string) $_POST['return_form']) : '';
        $allowedReturn = ['', 'mask', 'whiteBK', 'crop'];
        if (!in_array($returnForm, $allowedReturn, true)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Invalid return_form.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $returnFormOpt = $returnForm === '' ? null : $returnForm;

        $test = isset($_POST['test']) && (string) $_POST['test'] !== '' && (string) $_POST['test'] !== '0';

        $tmp = (string) $file['tmp_name'];

        $fields = [];
        if ($returnFormOpt !== null) {
            $fields['return_form'] = $returnFormOpt;
        }
        if ($test) {
            $fields['test'] = '1';
        }

        try {
            $client = new BitmeshClient($consumerKey, $consumerSecret, 180);
            $json = $client->toolsGeneralBackgroundRemoval($fields, $tmp);
        } catch (\Throwable $e) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(200);

        if (is_array($json)) {
            $errCode = (int) ($json['error_code'] ?? -1);
            $data = $json['data'] ?? null;
            $imageUrl = is_array($data) ? ($data['image_url'] ?? null) : null;
            if ($errCode === 0 && is_string($imageUrl) && $imageUrl !== '') {
                echo json_encode(['ok' => true, 'image_url' => $imageUrl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            $msg = 'Unexpected response';
            if (isset($json['error'])) {
                $msg = is_string($json['error']) ? $json['error'] : json_encode($json['error'], JSON_UNESCAPED_UNICODE);
            } elseif (isset($json['message'])) {
                $msg = (string) $json['message'];
            }
            echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['ok' => false, 'message' => 'Invalid response'], JSON_UNESCAPED_UNICODE);
    }

    private function renderToolBgremove(): string
    {
        $snippet = <<<'PHP'
<?php

require 'vendor/autoload.php';

use BitmeshAI\BitmeshClient;

$consumerKey = getenv('BITMESH_CONSUMER_KEY') ?: 'YOUR_CONSUMER_KEY';
$consumerSecret = getenv('BITMESH_CONSUMER_SECRET') ?: 'YOUR_CONSUMER_SECRET';

$client = new BitmeshClient($consumerKey, $consumerSecret, 180);

// POST /tools/general/background-removal (multipart). Non-file options + image path.
$result = $client->toolsGeneralBackgroundRemoval(
    [
        'return_form' => 'mask', // omit or choose mask|whiteBK|crop per API docs
        // 'test' => '1',
    ],
    '/path/to/local/photo.jpg'
);

if ((int) ($result['error_code'] ?? -1) === 0) {
    $imageUrl = $result['data']['image_url'] ?? null;
}

// Optional: download the proxied asset (GET /tools-result/... — no OAuth).
// $path = parse_url((string) $imageUrl, PHP_URL_PATH);
// $prefix = '/tools-result/';
// $relative = ltrim(substr((string) $path, strpos((string) $path, $prefix) + strlen($prefix)), '/');
// $bytes = $client->getToolsResult($relative);
// file_put_contents('/tmp/mask.png', $bytes);
PHP;
        $html = "<h1>Background removal</h1><p>Uses <code>BitmeshClient::toolsGeneralBackgroundRemoval()</code> — <code>POST /tools/general/background-removal</code> on <code>https://api.bitmesh.ai</code> with OAuth 1.0 and multipart payload signing.</p>";
        $html .= "<h2>Example code</h2><pre>" . htmlspecialchars($snippet) . "</pre>";

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        $html .= '<style>
#bgremove-form label { display: block; margin: 0.75rem 0 0.25rem; }
#bgremove-form input[type=file], #bgremove-form select { max-width: 100%; }
#bgremove-form .row { margin-bottom: 0.5rem; }
#bgremove-loading { margin-top: 1rem; color: #555; }
#bgremove-error { color: #c00; margin-top: 0.75rem; }
#bgremove-result { margin-top: 1rem; }
</style>';

        if ($consumerKey === '' || $consumerSecret === '') {
            $html .= "<h2>Try it</h2><p>Set <code>BITMESH_CONSUMER_KEY</code> and <code>BITMESH_CONSUMER_SECRET</code> in your environment to use the form below.</p>";
        } else {
            $html .= "<h2>Try it</h2><p>Upload an image. Optional <code>return_form</code>; enable test mode for a no-charge demo response from the proxy.</p>";
        }

        $disabled = ($consumerKey === '' || $consumerSecret === '') ? ' disabled' : '';
        $html .= '<form id="bgremove-form" enctype="multipart/form-data" method="post" action="/tool-bgremove">';
        $html .= '<div class="row"><label for="bgremove-image">Image</label><input id="bgremove-image" type="file" name="image" accept="image/*" required' . $disabled . ' /></div>';
        $html .= '<div class="row"><label for="bgremove-return">return_form (optional)</label><select id="bgremove-return" name="return_form"' . $disabled . '>';
        $html .= '<option value="">Default (cutout)</option><option value="mask">mask</option><option value="whiteBK">whiteBK</option><option value="crop">crop</option>';
        $html .= '</select></div>';
        $html .= '<div class="row"><label><input type="checkbox" name="test" value="1"' . $disabled . ' /> Test mode (no charge)</label></div>';
        $html .= '<p><button type="submit"' . $disabled . '>Remove background</button></p>';
        $html .= '</form>';
        $html .= '<p id="bgremove-loading" hidden>Working…</p>';
        $html .= '<p id="bgremove-error" class="error" role="alert"></p>';
        $html .= '<div id="bgremove-result"></div>';

        $html .= <<<'HTML'
<script>
(function () {
  var form = document.getElementById('bgremove-form');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var loading = document.getElementById('bgremove-loading');
    var errEl = document.getElementById('bgremove-error');
    var result = document.getElementById('bgremove-result');
    errEl.textContent = '';
    result.innerHTML = '';
    loading.hidden = false;
    var fd = new FormData(form);
    fetch('/tool-bgremove', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (t) {
          var data = {};
          if (t) {
            try { data = JSON.parse(t); } catch (e) { data = { ok: false, message: t }; }
          }
          return { r: r, data: data };
        });
      })
      .then(function (x) {
        loading.hidden = true;
        if (x.data && x.data.ok && x.data.image_url) {
          var u = String(x.data.image_url);
          var esc = function (s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
          };
          result.innerHTML = '<p><img src="' + esc(u) + '" alt="Result" style="max-width:100%;height:auto;border:1px solid #ddd" /></p>' +
            '<p><a href="' + esc(u) + '" target="_blank" rel="noopener noreferrer">Open image URL</a></p>';
          return;
        }
        var msg = (x.data && x.data.message) ? String(x.data.message) : ('HTTP ' + x.r.status);
        errEl.textContent = msg;
      })
      .catch(function (err) {
        loading.hidden = true;
        errEl.textContent = err && err.message ? err.message : 'Request failed';
      });
  });
})();
</script>
HTML;

        return $html;
    }

    private function handleToolTryonPost(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        if ($consumerKey === '' || $consumerSecret === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Set BITMESH_CONSUMER_KEY and BITMESH_CONSUMER_SECRET in your environment.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $fileKeys = ['person_image', 'top_garment', 'bottom_garment'];
        $paths = [];
        foreach ($fileKeys as $key) {
            if (! isset($_FILES[$key]) || ! is_array($_FILES[$key])) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => 'Upload required for '.str_replace('_', ' ', $key).'.'], JSON_UNESCAPED_UNICODE);

                return;
            }
            $f = $_FILES[$key];
            if (! is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => 'Missing upload for '.$key.'.'], JSON_UNESCAPED_UNICODE);

                return;
            }
            if ((int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => 'Upload failed for '.$key.' (code '.(int) $f['error'].').'], JSON_UNESCAPED_UNICODE);

                return;
            }
            $paths[$key] = (string) $f['tmp_name'];
        }

        $taskType = isset($_POST['task_type']) ? trim((string) $_POST['task_type']) : 'async';
        if (! in_array($taskType, ['sync', 'async'], true)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'task_type must be sync or async.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $resolution = isset($_POST['resolution']) ? trim((string) $_POST['resolution']) : '';
        if ($resolution === '') {
            $resolution = '-1';
        }

        $restoreFace = isset($_POST['restore_face']) ? trim((string) $_POST['restore_face']) : 'true';
        if ($restoreFace !== 'true' && $restoreFace !== 'false') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'restore_face must be true or false.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $fields = [
            'task_type' => $taskType,
            'resolution' => $resolution,
            'restore_face' => $restoreFace,
        ];

        $test = isset($_POST['test']) && (string) $_POST['test'] !== '' && (string) $_POST['test'] !== '0';
        if ($test) {
            $fields['test'] = '1';
        }

        try {
            $client = new BitmeshClient($consumerKey, $consumerSecret, 300);
            $api = $client->toolsPortraitTryOnClothes($fields, [
                'person_image' => $paths['person_image'],
                'top_garment' => $paths['top_garment'],
                'bottom_garment' => $paths['bottom_garment'],
            ]);
        } catch (\Throwable $e) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);

            return;
        }

        http_response_code(200);

        echo json_encode(['ok' => true, 'api' => $api], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function handleToolTryonQueryPost(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        if ($consumerKey === '' || $consumerSecret === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Set BITMESH_CONSUMER_KEY and BITMESH_CONSUMER_SECRET in your environment.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $taskId = isset($_POST['task_id']) ? trim((string) $_POST['task_id']) : '';
        if ($taskId === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'task_id is required.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        try {
            $client = new BitmeshClient($consumerKey, $consumerSecret, 120);
            $api = $client->toolsQueryAsyncTaskResult($taskId);
        } catch (\Throwable $e) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);

            return;
        }

        http_response_code(200);
        echo json_encode(['ok' => true, 'api' => $api], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function renderToolTryon(): string
    {
        $snippet = <<<'PHP'
<?php

require 'vendor/autoload.php';

use BitmeshAI\BitmeshClient;

$consumerKey = getenv('BITMESH_CONSUMER_KEY') ?: 'YOUR_CONSUMER_KEY';
$consumerSecret = getenv('BITMESH_CONSUMER_SECRET') ?: 'YOUR_CONSUMER_SECRET';

$client = new BitmeshClient($consumerKey, $consumerSecret, 300);

// POST /tools/portrait/try-on-clothes (multipart). Non-file fields + person/top/bottom uploads.
$response = $client->toolsPortraitTryOnClothes(
    [
        'task_type' => 'async',   // async jobs return task_id; poll with toolsQueryAsyncTaskResult()
        'resolution' => '-1',
        'restore_face' => 'true',
        // 'test' => '1',
    ],
    [
        'person_image' => '/path/to/person.png',
        'top_garment' => '/path/to/top.png',
        'bottom_garment' => '/path/to/bottom.png',
    ]
);

// Optional: poll async task
// $poll = $client->toolsQueryAsyncTaskResult((string) $response['task_id']);
PHP;
        $html = '<h1>Try-on clothes</h1><p>Uses <code>BitmeshClient::toolsPortraitTryOnClothes()</code> (<code>POST /tools/portrait/try-on-clothes</code>) and optionally <code>toolsQueryAsyncTaskResult()</code> for async tasks.</p>';
        $html .= '<h2>Example code</h2><pre>'.htmlspecialchars($snippet).'</pre>';

        $consumerKey = (string) ($_ENV['BITMESH_CONSUMER_KEY'] ?? getenv('BITMESH_CONSUMER_KEY') ?: '');
        $consumerSecret = (string) ($_ENV['BITMESH_CONSUMER_SECRET'] ?? getenv('BITMESH_CONSUMER_SECRET') ?: '');

        $html .= '<style>
#tryon-form label { display: block; margin: 0.75rem 0 0.25rem; }
#tryon-form input[type=file], #tryon-form select { max-width: 100%; }
#tryon-form .row { margin-bottom: 0.5rem; }
#tryon-loading, #tryon-query-loading { margin-top: 1rem; color: #555; }
#tryon-error, #tryon-query-error { color: #c00; margin-top: 0.75rem; }
#tryon-result, #tryon-query-result { margin-top: 1rem; }
#tryon-poll-section { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #ddd; display: none; }
#tryon-poll-section.visible { display: block; }
</style>';

        if ($consumerKey === '' || $consumerSecret === '') {
            $html .= '<h2>Try it</h2><p>Set <code>BITMESH_CONSUMER_KEY</code> and <code>BITMESH_CONSUMER_SECRET</code> to use the form.</p>';
        } else {
            $html .= '<h2>Try it</h2><p>Upload three images. Default options match typical async try-on flows; use “Query async task” after you receive a <code>task_id</code>.</p>';
        }

        $disabled = ($consumerKey === '' || $consumerSecret === '') ? ' disabled' : '';
        $html .= '<form id="tryon-form" enctype="multipart/form-data" method="post" action="/tool-tryon">';
        $html .= '<div class="row"><label for="tryon-person">person_image</label><input id="tryon-person" type="file" name="person_image" accept="image/*" required'.$disabled.' /></div>';
        $html .= '<div class="row"><label for="tryon-top">top_garment</label><input id="tryon-top" type="file" name="top_garment" accept="image/*" required'.$disabled.' /></div>';
        $html .= '<div class="row"><label for="tryon-bottom">bottom_garment</label><input id="tryon-bottom" type="file" name="bottom_garment" accept="image/*" required'.$disabled.' /></div>';
        $html .= '<div class="row"><label for="tryon-task-type">task_type</label><select id="tryon-task-type" name="task_type"'.$disabled.'><option value="async" selected>async</option><option value="sync">sync</option></select></div>';
        $html .= '<div class="row"><label for="tryon-resolution">resolution</label><input id="tryon-resolution" type="text" name="resolution" value="-1" style="width:6rem;"'.$disabled.' /></div>';
        $html .= '<div class="row"><label for="tryon-restore">restore_face</label><select id="tryon-restore" name="restore_face"'.$disabled.'><option value="true" selected>true</option><option value="false">false</option></select></div>';
        $html .= '<div class="row"><label><input type="checkbox" name="test" value="1"'.$disabled.' /> Test mode (no charge)</label></div>';
        $html .= '<p><button type="submit"'.$disabled.'>Submit try-on</button></p>';
        $html .= '</form>';
        $html .= '<p id="tryon-loading" hidden>Submitting…</p>';
        $html .= '<p id="tryon-error" class="error" role="alert"></p>';
        $html .= '<div id="tryon-result"></div>';

        $pollClass = (($consumerKey !== '' && $consumerSecret !== '') ? ' class="visible"' : '');

        $html .= '<div id="tryon-poll-section"'.$pollClass.'><h2>Query async task</h2>';
        $html .= '<form id="tryon-query-form" method="post" action="/tool-tryon-query">';
        $html .= '<p><label for="tryon-task-id">task_id</label><br><input id="tryon-task-id" type="text" name="task_id" placeholder="Paste task id from submit response" style="width:100%;max-width:480px;" required'.$disabled.' /></p>';
        $html .= '<p><button type="submit"'.$disabled.'>Poll <code>toolsQueryAsyncTaskResult</code></button></p>';
        $html .= '</form>';
        $html .= '<p id="tryon-query-loading" hidden>Querying…</p>';
        $html .= '<p id="tryon-query-error" class="error" role="alert"></p>';
        $html .= '<div id="tryon-query-result"></div></div>';

        $html .= <<<'HTML'
<script>
(function () {
  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
  }

  function showJsonInto(elId, obj) {
    var el = document.getElementById(elId);
    if (!el) return;
    el.innerHTML = '<pre>' + escHtml(JSON.stringify(obj, null, 2)) + '</pre>';
  }

  var pollSection = document.getElementById('tryon-poll-section');

  var form = document.getElementById('tryon-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var loading = document.getElementById('tryon-loading');
      var errEl = document.getElementById('tryon-error');
      var result = document.getElementById('tryon-result');
      errEl.textContent = '';
      result.innerHTML = '';
      loading.hidden = false;
      var fd = new FormData(form);
      fetch('/tool-tryon', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) {
          return r.text().then(function (t) {
            var data = {};
            if (t) { try { data = JSON.parse(t); } catch (e) { data = { ok: false, message: t }; } }
            return { r: r, data: data };
          });
        })
        .then(function (x) {
          loading.hidden = true;
          if (x.data && x.data.ok && x.data.api) {
            showJsonInto('tryon-result', x.data.api);
            var tid = x.data.api.task_id ? String(x.data.api.task_id) : '';
            if (tid !== '' && pollSection) {
              pollSection.classList.add('visible');
              var input = document.getElementById('tryon-task-id');
              if (input) input.value = tid;
            }
            return;
          }
          var msg = (x.data && x.data.message) ? String(x.data.message) : ('HTTP ' + x.r.status);
          errEl.textContent = msg;
        })
        .catch(function (err) {
          loading.hidden = true;
          errEl.textContent = err && err.message ? err.message : 'Request failed';
        });
    });
  }

  var qform = document.getElementById('tryon-query-form');
  if (qform) {
    qform.addEventListener('submit', function (e) {
      e.preventDefault();
      var loading = document.getElementById('tryon-query-loading');
      var errEl = document.getElementById('tryon-query-error');
      var resultEl = document.getElementById('tryon-query-result');
      errEl.textContent = '';
      resultEl.innerHTML = '';
      loading.hidden = false;
      var fd = new FormData(qform);
      fetch('/tool-tryon-query', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) {
          return r.text().then(function (t) {
            var data = {};
            if (t) { try { data = JSON.parse(t); } catch (e) { data = { ok: false, message: t }; } }
            return { r: r, data: data };
          });
        })
        .then(function (x) {
          loading.hidden = true;
          if (x.data && x.data.ok && x.data.api) {
            showJsonInto('tryon-query-result', x.data.api);
            return;
          }
          var msg = (x.data && x.data.message) ? String(x.data.message) : ('HTTP ' + x.r.status);
          errEl.textContent = msg;
        })
        .catch(function (err) {
          loading.hidden = true;
          errEl.textContent = err && err.message ? err.message : 'Request failed';
        });
    });
  }
})();
</script>
HTML;

        return $html;
    }
}
