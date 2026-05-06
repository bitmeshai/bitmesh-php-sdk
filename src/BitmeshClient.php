<?php

namespace BitmeshAI;

class BitmeshClient
{
    private string $consumerKey;
    private string $consumerSecret;
    private string $apiBaseUrl;
    private string $userAgent;

    /**
     * Create a new Bitmesh AI client.
     *
     * @param string $consumerKey    OAuth consumer key provided by Bitmesh
     * @param string $consumerSecret OAuth consumer secret provided by Bitmesh
     * @param string $apiBaseUrl     Base URL of the Bitmesh AI API (change this if you use a local/dev server)
     *                               Defaults to the production URL.
     * @param string $userAgent      Optional User-Agent header value
     */
    public function __construct(
        string $consumerKey,
        string $consumerSecret,
        string $apiBaseUrl = 'https://aiproxyapi-production.up.railway.app',
        string $userAgent = 'BitmeshPhpSdk/1.0'
    ) {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->userAgent = $userAgent;
    }

    /**
     * Call the `/chat` endpoint.
     *
     * Minimal usage:
     *
     * $client = new BitmeshClient($consumerKey, $consumerSecret);
     * $response = $client->chat('What are some fun things to do with AI?');
     *
     * @param string|array<int, array{role:string,content:string}> $messages
     *        - string: convenience form, will be wrapped as a single "user" message
     *        - array: full messages array as expected by the API (min 1 element; each: role, content)
     * @param string|null $model     Optional model name. Omit (null) when the API key has a fixed default model.
     * @param array<string,mixed> $options Optional request parameters. Supported keys:
     *        - max_tokens: int ≥ 1
     *        - temperature: float 0–2
     *        - repetition_penalty: float ≥ 0
     *        - frequency_penalty: float -2–2
     *        - presence_penalty: float -2–2
     *        - test: bool – if true, charges are not applied
     * @param array<string,mixed> $extraPayload Extra fields to merge into the request payload
     *
     * @return array<string,mixed>   Decoded JSON response as associative array
     *
     * @throws \RuntimeException     On HTTP / transport / decode errors
     */
    public function chat(
        string|array $messages,
        ?string $model = null,
        array $options = [],
        array $extraPayload = []
    ): array {
        $url = $this->apiBaseUrl . '/chat';

        // Normalize messages parameter
        if (is_string($messages)) {
            $messages = [
                ['role' => 'user', 'content' => $messages],
            ];
        }

        $payload = [
            'messages' => $messages,
        ];

        // Only include model when explicitly provided (required if key has no default; prohibited if key has fixed model)
        if ($model !== null) {
            $payload['model'] = $model;
        }

        $payload = array_merge($payload, $options, $extraPayload);

        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonBody === false) {
            throw new \RuntimeException('Failed to encode chat payload as JSON.');
        }

        // OAuth 1.0 params and Authorization header
        $method = 'POST';
        $oauthParams = $this->generateOAuthParams($method, $url);
        $authHeader = $this->buildAuthorizationHeader($oauthParams);

        // Payload signature header (matches sample script)
        $payloadSignature = hash('sha256', $jsonBody . $this->consumerKey . $oauthParams['oauth_signature']);

        // Execute HTTP request (extracted for easier testing)
        [$httpCode, $body] = $this->sendRequest($url, $authHeader, $payloadSignature, $jsonBody);

        $decoded = json_decode($body, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Failed to decode Bitmesh response JSON: ' . json_last_error_msg() . '. Raw body: ' . $body
            );
        }

        if ($httpCode !== 200) {
            $message = 'Bitmesh API returned HTTP ' . $httpCode;
            if (is_array($decoded) && isset($decoded['error'])) {
                $message .= ' - ' . json_encode($decoded['error']);
            }
            throw new \RuntimeException($message);
        }

        return is_array($decoded) ? $decoded : ['data' => $decoded];
    }

    /**
     * Call the `/image` endpoint.
     *
     * Generate images via the configured AI provider. Image URLs in the response
     * are rewritten to your proxy (e.g. https://<your-domain>/imgrslt/{id}).
     *
     * @param string $prompt          Required prompt describing the image to generate.
     * @param string|null $model      Optional model name. Omit (null) when the API key has a fixed default model.
     * @param array<string,mixed> $options Optional request parameters. Supported keys:
     *        - width: int ≥ 1
     *        - height: int ≥ 1
     *        - steps: int ≥ 1
     *        - seed: int
     *        - n: int ≥ 1 – number of images to generate
     * @param array<string,mixed> $extraPayload Extra fields to merge into the request payload
     *
     * @return array<string,mixed>    Decoded JSON response (e.g. data[].url)
     *
     * @throws \RuntimeException      On HTTP / transport / decode errors
     */
    public function image(
        string $prompt,
        ?string $model = null,
        array $options = [],
        array $extraPayload = []
    ): array {
        $url = $this->apiBaseUrl . '/image';

        $payload = [
            'prompt' => $prompt,
        ];

        if ($model !== null) {
            $payload['model'] = $model;
        }

        $payload = array_merge($payload, $options, $extraPayload);

        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonBody === false) {
            throw new \RuntimeException('Failed to encode image payload as JSON.');
        }

        $method = 'POST';
        $oauthParams = $this->generateOAuthParams($method, $url);
        $authHeader = $this->buildAuthorizationHeader($oauthParams);
        $payloadSignature = hash('sha256', $jsonBody . $this->consumerKey . $oauthParams['oauth_signature']);

        [$httpCode, $body] = $this->sendRequest($url, $authHeader, $payloadSignature, $jsonBody);

        $decoded = json_decode($body, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Failed to decode Bitmesh response JSON: ' . json_last_error_msg() . '. Raw body: ' . $body
            );
        }

        if ($httpCode !== 200) {
            $message = 'Bitmesh API returned HTTP ' . $httpCode;
            if (is_array($decoded) && isset($decoded['error'])) {
                $message .= ' - ' . json_encode($decoded['error']);
            }
            throw new \RuntimeException($message);
        }

        return is_array($decoded) ? $decoded : ['data' => $decoded];
    }

    /**
     * Call the `/video` endpoint.
     *
     * Generate videos via the underlying AI provider. Response may contain
     * `id` (video job ID, used with videoStatus()) and `outputs` / `data`.
     *
     * @param string $prompt          Required prompt, 1–32000 characters.
     * @param string|null $model      Optional model name. Omit (null) when the API key has a fixed default model.
     * @param array<string,mixed> $options Optional request parameters. Supported keys:
     *        - width: int ≥ 1
     *        - height: int ≥ 1
     *        - seconds: string – duration (per provider API)
     *        - fps: int ≥ 1
     *        - steps: int 10–50
     *        - seed: int
     *        - guidance_scale: float ≥ 0
     *        - output_format: string, one of MP4, WEBM
     *        - output_quality: int ≥ 1
     *        - negative_prompt: string
     *        - frame_images: array (items: input_image, frame)
     *        - reference_images: array of string
     * @param array<string,mixed> $extraPayload Extra fields to merge into the request payload
     *
     * @return array<string,mixed>    Decoded JSON response (e.g. id, outputs, data)
     *
     * @throws \RuntimeException      On HTTP / transport / decode errors
     */
    public function video(
        string $prompt,
        ?string $model = null,
        array $options = [],
        array $extraPayload = []
    ): array {
        $url = $this->apiBaseUrl . '/video';

        $payload = [
            'prompt' => $prompt,
        ];

        if ($model !== null) {
            $payload['model'] = $model;
        }

        $payload = array_merge($payload, $options, $extraPayload);

        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonBody === false) {
            throw new \RuntimeException('Failed to encode video payload as JSON.');
        }

        $method = 'POST';
        $oauthParams = $this->generateOAuthParams($method, $url);
        $authHeader = $this->buildAuthorizationHeader($oauthParams);
        $payloadSignature = hash('sha256', $jsonBody . $this->consumerKey . $oauthParams['oauth_signature']);

        [$httpCode, $body] = $this->sendRequest($url, $authHeader, $payloadSignature, $jsonBody);

        $decoded = json_decode($body, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Failed to decode Bitmesh response JSON: ' . json_last_error_msg() . '. Raw body: ' . $body
            );
        }

        if ($httpCode !== 200) {
            $message = 'Bitmesh API returned HTTP ' . $httpCode;
            if (is_array($decoded) && isset($decoded['error'])) {
                $message .= ' - ' . json_encode($decoded['error']);
            }
            throw new \RuntimeException($message);
        }

        return is_array($decoded) ? $decoded : ['data' => $decoded];
    }

    /**
     * Call the `GET /video/{id}` endpoint.
     *
     * Fetch video generation job details (status, outputs, video_url, cost).
     *
     * @param string $id Provider video job ID (from video() response).
     *
     * @return array<string,mixed>    Decoded JSON response (id, status, outputs, etc.)
     *
     * @throws \RuntimeException      On HTTP / transport / decode errors
     */
    public function videoStatus(string $id): array
    {
        $url = $this->apiBaseUrl . '/video/' . rawurlencode($id);

        $method = 'GET';
        $oauthParams = $this->generateOAuthParams($method, $url);
        $authHeader = $this->buildAuthorizationHeader($oauthParams);

        // Payload signature for GET: empty body, same formula as POST
        $payloadSignature = hash('sha256', '' . $this->consumerKey . $oauthParams['oauth_signature']);

        [$httpCode, $body] = $this->sendGetRequest($url, $authHeader, $payloadSignature);

        $decoded = json_decode($body, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Failed to decode Bitmesh response JSON: ' . json_last_error_msg() . '. Raw body: ' . $body
            );
        }

        if ($httpCode !== 200) {
            $message = 'Bitmesh API returned HTTP ' . $httpCode;
            if (is_array($decoded) && isset($decoded['error'])) {
                $message .= ' - ' . json_encode($decoded['error']);
            }
            throw new \RuntimeException($message);
        }

        return is_array($decoded) ? $decoded : ['data' => $decoded];
    }

    /**
     * Submit a prerecorded transcription job (URL mode).
     *
     * Calls POST /transcribe-recorded
     */
    public function transcribeRecordedFromUrl(
        string $audioUrl,
        array $options = [],
        array $extraPayload = []
    ): array {
        $url = $this->apiBaseUrl . '/transcribe-recorded';

        $payload = [
            'audio_url' => $audioUrl,
        ];

        $payload = array_merge($payload, $options, $extraPayload);

        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonBody === false) {
            throw new \RuntimeException('Failed to encode transcribe URL payload as JSON.');
        }

        $method = 'POST';
        $oauthParams = $this->generateOAuthParams($method, $url);
        $authHeader = $this->buildAuthorizationHeader($oauthParams);
        $payloadSignature = hash('sha256', $jsonBody . $this->consumerKey . $oauthParams['oauth_signature']);

        [$httpCode, $body] = $this->sendRequest($url, $authHeader, $payloadSignature, $jsonBody);
        return $this->parseJsonResponseOrThrow($httpCode, $body);
    }

    /**
     * Submit a prerecorded transcription job (upload mode).
     *
     * Calls POST /transcribe-recorded with multipart/form-data.
     */
    public function transcribeRecordedFromFile(
        string $audioFilePath,
        array $options = [],
        array $extraPayload = []
    ): array {
        $url = $this->apiBaseUrl . '/transcribe-recorded';

        // Ensure we never send audio_url in upload mode.
        unset($options['audio_url'], $extraPayload['audio_url']);

        $nonFileFields = array_merge($options, $extraPayload);
        $normalizedNonFileFields = $this->normalizeMultipartNonFileFields($nonFileFields);

        $method = 'POST';
        $oauthParams = $this->generateOAuthParams($method, $url);
        $authHeader = $this->buildAuthorizationHeader($oauthParams);

        $payloadSignatureJson = $this->canonicalizeMultipartNonFileFieldsForSignature($nonFileFields);
        $payloadSignature = hash('sha256', $payloadSignatureJson . $this->consumerKey . $oauthParams['oauth_signature']);

        $multipartPostFields = ['audio' => new \CURLFile($audioFilePath)];
        foreach ($normalizedNonFileFields as $key => $value) {
            $multipartPostFields[$key] = $value;
        }

        [$httpCode, $body] = $this->sendMultipartRequest($url, $authHeader, $payloadSignature, $multipartPostFields);
        return $this->parseJsonResponseOrThrow($httpCode, $body);
    }

    /**
     * Poll transcription job status/result.
     *
     * Calls GET /transcribe-recorded/{id}
     */
    public function transcribeRecordedStatus(string $id, array $queryParams = []): array
    {
        $url = $this->apiBaseUrl . '/transcribe-recorded/' . rawurlencode($id);

        if (!empty($queryParams)) {
            $normalizedQueryParams = [];
            foreach ($queryParams as $key => $value) {
                if (is_bool($value)) {
                    $normalizedQueryParams[$key] = $value ? '1' : '0';
                } else {
                    $normalizedQueryParams[$key] = $value;
                }
            }

            $queryString = http_build_query($normalizedQueryParams);
            if ($queryString !== '') {
                $url .= '?' . $queryString;
            }
        }

        $method = 'GET';
        $oauthParams = $this->generateOAuthParams($method, $url);
        $authHeader = $this->buildAuthorizationHeader($oauthParams);

        // Payload signature for GET uses empty body.
        $payloadSignature = hash('sha256', '' . $this->consumerKey . $oauthParams['oauth_signature']);

        [$httpCode, $body] = $this->sendGetRequest($url, $authHeader, $payloadSignature);
        return $this->parseJsonResponseOrThrow($httpCode, $body);
    }

    /**
     * Tool: remove background from an uploaded image.
     *
     * Calls POST /tools/general/background-removal
     */
    public function backgroundRemoval(
        string $imageFilePath,
        ?string $returnForm = null,
        array $options = [],
        array $extraPayload = []
    ): array {
        $url = $this->apiBaseUrl . '/tools/general/background-removal';

        $nonFileFields = [];
        if ($returnForm !== null) {
            $nonFileFields['return_form'] = $returnForm;
        }
        $nonFileFields = array_merge($nonFileFields, $options, $extraPayload);

        $normalizedNonFileFields = $this->normalizeMultipartNonFileFields($nonFileFields);

        $method = 'POST';
        $oauthParams = $this->generateOAuthParams($method, $url);
        $authHeader = $this->buildAuthorizationHeader($oauthParams);

        $payloadSignatureJson = $this->canonicalizeMultipartNonFileFieldsForSignature($nonFileFields);
        $payloadSignature = hash('sha256', $payloadSignatureJson . $this->consumerKey . $oauthParams['oauth_signature']);

        $multipartPostFields = ['image' => new \CURLFile($imageFilePath)];
        foreach ($normalizedNonFileFields as $key => $value) {
            $multipartPostFields[$key] = $value;
        }

        [$httpCode, $body] = $this->sendMultipartRequest($url, $authHeader, $payloadSignature, $multipartPostFields);
        return $this->parseJsonResponseOrThrow($httpCode, $body);
    }

    /**
     * Tool: query async task status/result.
     *
     * Calls POST /tools/query-async-task-result
     */
    public function queryAsyncTaskResult(string $taskId, array $extraPayload = []): array
    {
        $url = $this->apiBaseUrl . '/tools/query-async-task-result';

        $payload = [
            'task_id' => $taskId,
        ];
        $payload = array_merge($payload, $extraPayload);

        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonBody === false) {
            throw new \RuntimeException('Failed to encode query-async-task-result payload as JSON.');
        }

        $method = 'POST';
        $oauthParams = $this->generateOAuthParams($method, $url);
        $authHeader = $this->buildAuthorizationHeader($oauthParams);
        $payloadSignature = hash('sha256', $jsonBody . $this->consumerKey . $oauthParams['oauth_signature']);

        [$httpCode, $body] = $this->sendRequest($url, $authHeader, $payloadSignature, $jsonBody);
        return $this->parseJsonResponseOrThrow($httpCode, $body);
    }

    /**
     * Send HTTP request to Bitmesh AI.
     *
     * @param string $url
     * @param string $authHeader
     * @param string $payloadSignature
     * @param string $jsonBody
     *
     * @return array{0:int,1:string} [HTTP status code, response body]
     *
     * @throws \RuntimeException on transport errors
     */
    protected function sendRequest(
        string $url,
        string $authHeader,
        string $payloadSignature,
        string $jsonBody
    ): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $authHeader,
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: ' . $this->userAgent,
                'X-Payload-Signature: ' . $payloadSignature,
            ],
            CURLOPT_HEADER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Curl error while calling Bitmesh: ' . $error);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$httpCode, $body];
    }

    /**
     * Send HTTP GET request to Bitmesh AI (e.g. /video/{id}).
     *
     * @param string $url
     * @param string $authHeader
     * @param string $payloadSignature Payload signature (e.g. for GET, hash of empty body + key + oauth_signature).
     *
     * @return array{0:int,1:string} [HTTP status code, response body]
     *
     * @throws \RuntimeException on transport errors
     */
    protected function sendGetRequest(string $url, string $authHeader, string $payloadSignature): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $authHeader,
                'Accept: application/json',
                'User-Agent: ' . $this->userAgent,
                'X-Payload-Signature: ' . $payloadSignature,
            ],
            CURLOPT_HEADER => false,
            CURLOPT_HTTPGET => true,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Curl error while calling Bitmesh: ' . $error);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$httpCode, $body];
    }

    /**
     * Send HTTP multipart/form-data request to Bitmesh AI (e.g. /tools/... uploads).
     *
     * @param string $url
     * @param string $authHeader
     * @param string $payloadSignature Payload signature for non-file fields.
     * @param array<string,mixed> $multipartPostFields Multipart fields; file fields should use CURLFile.
     *
     * @return array{0:int,1:string} [HTTP status code, response body]
     *
     * @throws \RuntimeException on transport errors
     */
    protected function sendMultipartRequest(
        string $url,
        string $authHeader,
        string $payloadSignature,
        array $multipartPostFields
    ): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $authHeader,
                'Accept: application/json',
                'User-Agent: ' . $this->userAgent,
                'X-Payload-Signature: ' . $payloadSignature,
            ],
            CURLOPT_HEADER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $multipartPostFields,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Curl error while calling Bitmesh: ' . $error);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$httpCode, $body];
    }

    /**
     * Normalize non-file multipart values to match how PHP will parse them.
     *
     * Multipart form scalars are sent as strings; to keep signature validation consistent,
     * we normalize booleans and numbers to string equivalents before signing.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function normalizeMultipartNonFileValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $k => $v) {
                $normalized[$k] = $this->normalizeMultipartNonFileValue($v);
            }
            return $normalized;
        }

        // Strings (and other scalar-ish values) go out as strings.
        return (string) $value;
    }

    /**
     * Canonicalize non-file fields for multipart request signing.
     *
     * - drop nulls
     * - normalize scalars to string equivalents
     * - ksort keys
     * - json_encode with JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
     */
    private function canonicalizeMultipartNonFileFieldsForSignature(array $nonFileFields): string
    {
        $normalized = $this->normalizeMultipartNonFileFields($nonFileFields);
        ksort($normalized);

        $jsonString = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonString === false) {
            throw new \RuntimeException('Failed to encode multipart signature payload as JSON.');
        }

        return $jsonString;
    }

    /**
     * Normalize non-file fields into a form that will be parsed consistently from multipart.
     *
     * This returns an associative array with nulls dropped, and scalars normalized to strings.
     *
     * @param array<string,mixed> $nonFileFields
     * @return array<string,mixed>
     */
    private function normalizeMultipartNonFileFields(array $nonFileFields): array
    {
        $normalized = [];
        foreach ($nonFileFields as $key => $value) {
            if ($value === null) {
                continue;
            }
            $normalized[$key] = $this->normalizeMultipartNonFileValue($value);
        }

        return $normalized;
    }

    /**
     * Decode JSON response and throw consistent RuntimeException for non-200.
     *
     * @return array<string, mixed>
     */
    private function parseJsonResponseOrThrow(int $httpCode, string $body): array
    {
        $decoded = json_decode($body, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Failed to decode Bitmesh response JSON: ' . json_last_error_msg() . '. Raw body: ' . $body
            );
        }

        if ($httpCode !== 200) {
            $message = 'Bitmesh API returned HTTP ' . $httpCode;
            if (is_array($decoded) && isset($decoded['error'])) {
                $message .= ' - ' . json_encode($decoded['error']);
            }
            throw new \RuntimeException($message);
        }

        return is_array($decoded) ? $decoded : ['data' => $decoded];
    }

    /**
     * Generate OAuth 1.0 parameters and signature.
     */
    private function generateOAuthParams(string $method, string $url): array
    {
        $params = [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_nonce' => bin2hex(random_bytes(8)),
            'oauth_version' => '1.0',
        ];

        $params['oauth_signature'] = $this->generateSignature($method, $url, $params);

        return $params;
    }

    /**
     * Generate OAuth 1.0 signature using HMAC-SHA1.
     *
     * This mirrors the standalone script logic you provided.
     */
    private function generateSignature(string $method, string $url, array $params): string
    {
        $parsedUrl = parse_url($url);

        $scheme = $parsedUrl['scheme'] ?? 'http';
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? null;
        $path = isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : '';

        $normalizedUrl = $scheme . '://' . $host;

        if (
            ($scheme === 'http' && $port !== null && $port !== 80) ||
            ($scheme === 'https' && $port !== null && $port !== 443)
        ) {
            $normalizedUrl .= ':' . $port;
        }

        $normalizedUrl .= '/' . $path;

        $queryParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }

        $allParams = array_merge($params, $queryParams);
        unset($allParams['oauth_signature']);

        ksort($allParams);

        $normalizedParams = [];
        foreach ($allParams as $key => $value) {
            $normalizedParams[] = $this->urlEncode($key) . '=' . $this->urlEncode((string) $value);
        }
        $paramString = implode('&', $normalizedParams);

        $signatureBaseString =
            $this->urlEncode($method) . '&' .
            $this->urlEncode($normalizedUrl) . '&' .
            $this->urlEncode($paramString);

        $signingKey = $this->urlEncode($this->consumerSecret) . '&';

        return base64_encode(hash_hmac('sha1', $signatureBaseString, $signingKey, true));
    }

    /**
     * Build OAuth Authorization header.
     */
    private function buildAuthorizationHeader(array $oauthParams): string
    {
        $headerParts = [];

        foreach ($oauthParams as $key => $value) {
            if (strpos($key, 'oauth_') === 0) {
                $headerParts[] = $this->urlEncode($key) . '="' . $this->urlEncode((string) $value) . '"';
            }
        }

        return 'OAuth ' . implode(', ', $headerParts);
    }

    /**
     * RFC 3986-compliant URL encoding (for OAuth).
     */
    private function urlEncode(string $value): string
    {
        return str_replace(
            ['+', '%7E'],
            ['%20', '~'],
            rawurlencode($value)
        );
    }
}
