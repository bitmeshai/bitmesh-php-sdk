<?php

namespace BitmeshAI;

use RuntimeException;

class BitmeshClient
{
    private const BASE_URL = 'https://api.bitmesh.ai';

    private string $key;

    private string $secret;

    private string $baseUrl;

    private int $timeoutSeconds;

    public function __construct(string $key, string $secret, int $timeoutSeconds = 30)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->baseUrl = rtrim(self::BASE_URL, '/');
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function chat(array $payload): array
    {
        return $this->sendSignedJsonRequest('POST', '/chat', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function image(array $payload): array
    {
        return $this->sendSignedJsonRequest('POST', '/image', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function video(array $payload): array
    {
        return $this->sendSignedJsonRequest('POST', '/video', $payload);
    }

    /**
     * Poll video generation job status / result (GET /video/{id}).
     *
     * @param  array<string, scalar|null>  $query  Optional query string params (e.g. test flags if supported)
     * @return array<string, mixed>
     */
    public function getVideo(string $id, array $query = []): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new RuntimeException('Video job id is required.');
        }

        return $this->sendSignedGetRequest('video/'.rawurlencode($id), $query);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function transcribeFile(string $audioFilePath, array $fields = []): array
    {
        return $this->sendSignedMultipartRequest('POST', '/transcribe-recorded', $audioFilePath, $fields);
    }

    /**
     * Poll transcription job status/result (GET /transcribe-recorded/{id}).
     *
     * @param  array<string, scalar|null>  $query  Optional query string params (e.g. test)
     * @return array<string, mixed>
     */
    public function getTranscribeRecorded(string $id, array $query = []): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new RuntimeException('Transcription job id is required.');
        }

        return $this->sendSignedGetRequest('transcribe-recorded/'.rawurlencode($id), $query);
    }

    /**
     * AiLabTools universal background removal (POST /tools/general/background-removal).
     *
     * @param  array<string, mixed>  $fields  Non-file fields (e.g. return_form, test)
     * @return array<string, mixed>
     */
    public function toolsGeneralBackgroundRemoval(array $fields, string $imagePath): array
    {
        return $this->sendSignedToolMultipartRequest('/tools/general/background-removal', $fields, [
            'image' => $imagePath,
        ]);
    }

    /**
     * AiLabTools virtual try-on clothes (POST /tools/portrait/try-on-clothes).
     *
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $files  Field name => local file path
     * @return array<string, mixed>
     */
    public function toolsPortraitTryOnClothes(array $fields, array $files): array
    {
        return $this->sendSignedToolMultipartRequest('/tools/portrait/try-on-clothes', $fields, $files);
    }

    /**
     * Query async tool task status/result (POST /tools/query-async-task-result).
     *
     * @return array<string, mixed>
     */
    public function toolsQueryAsyncTaskResult(string $taskId): array
    {
        $taskId = trim($taskId);
        if ($taskId === '') {
            throw new RuntimeException('Task id is required.');
        }

        return $this->sendSignedJsonRequest('POST', '/tools/query-async-task-result', [
            'task_id' => $taskId,
        ]);
    }

    /**
     * DomoAI image-to-video (POST /tools/video/image-animate).
     *
     * Async: returns a task_id immediately. Poll getImageAnimate($taskId) until the
     * status is terminal, or pass a "callback_url" in $payload to be notified.
     *
     * Provide the image inside the payload as DomoAI expects, e.g.:
     *   'image' => ['bytes_base64_encoded' => base64_encode((string) file_get_contents($path))]
     *   // or 'image' => ['domoai_uri' => 'domoai://...']
     *
     * Typical payload keys: model (required), image (required), seconds (required, 1-10),
     * prompt, aspect_ratio, callback_url, test.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function imageAnimate(array $payload): array
    {
        return $this->sendSignedJsonRequest('POST', '/tools/video/image-animate', $payload);
    }

    /**
     * Poll DomoAI image-to-video task status/result (GET /tools/video/image-animate/{task_id}).
     *
     * @param  array<string, scalar|null>  $query  Optional query params (e.g. ['test' => 1])
     * @return array<string, mixed>
     */
    public function getImageAnimate(string $taskId, array $query = []): array
    {
        $taskId = trim($taskId);
        if ($taskId === '') {
            throw new RuntimeException('Image-animate task id is required.');
        }

        return $this->sendSignedGetRequest('tools/video/image-animate/'.rawurlencode($taskId), $query);
    }

    /**
     * Fetch a proxied tool result asset (GET /tools-result/{path}). No OAuth required.
     */
    public function getToolsResult(string $path): string
    {
        $path = ltrim($path, '/');
        if ($path === '') {
            throw new RuntimeException('Tools result path is required.');
        }

        return $this->sendUnsignedGetRequest('tools-result/'.$path);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendSignedJsonRequest(string $method, string $path, array $payload = []): array
    {
        $url = $this->buildUrl($path);
        $jsonBody = $this->encodeJson($payload);
        $oauthParams = $this->generateOAuthParams($method, $url);
        $oauthSignature = (string) ($oauthParams['oauth_signature'] ?? '');

        $headers = [
            'Authorization: '.$this->buildAuthorizationHeader($oauthParams),
            'X-Payload-Signature: '.$this->generatePayloadSignature($jsonBody, $oauthSignature),
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $curlHandle = curl_init($url);
        if ($curlHandle === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $responseBody = curl_exec($curlHandle);
        $curlError = curl_error($curlHandle);
        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        if ($responseBody === false) {
            throw new RuntimeException('Bitmesh request failed: '.$curlError);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf(
                'Bitmesh API request failed with status %d: %s',
                $statusCode,
                $responseBody
            ));
        }

        return $this->decodeJsonResponse($responseBody);
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    private function sendSignedGetRequest(string $path, array $query = []): array
    {
        $path = ltrim($path, '/');
        $queryString = $query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $url = $this->buildUrl($path.$queryString);

        $oauthParams = $this->generateOAuthParams('GET', $url);
        $oauthSignature = (string) ($oauthParams['oauth_signature'] ?? '');

        $headers = [
            'Authorization: '.$this->buildAuthorizationHeader($oauthParams),
            'X-Payload-Signature: '.$this->generatePayloadSignature('', $oauthSignature),
            'Accept: application/json',
        ];

        $curlHandle = curl_init($url);
        if ($curlHandle === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $responseBody = curl_exec($curlHandle);
        $curlError = curl_error($curlHandle);
        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        if ($responseBody === false) {
            throw new RuntimeException('Bitmesh request failed: '.$curlError);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf(
                'Bitmesh API request failed with status %d: %s',
                $statusCode,
                $responseBody
            ));
        }

        return $this->decodeJsonResponse($responseBody);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $files
     * @return array<string, mixed>
     */
    private function sendSignedToolMultipartRequest(string $path, array $fields, array $files): array
    {
        foreach ($files as $fieldName => $filePath) {
            if (! is_string($filePath) || $filePath === '' || ! is_file($filePath) || ! is_readable($filePath)) {
                throw new RuntimeException(sprintf(
                    'File does not exist or is not readable for field "%s": %s',
                    (string) $fieldName,
                    (string) $filePath
                ));
            }
        }

        $url = $this->buildUrl($path);
        $oauthParams = $this->generateOAuthParams('POST', $url);
        $oauthSignature = (string) ($oauthParams['oauth_signature'] ?? '');

        $headers = [
            'Authorization: '.$this->buildAuthorizationHeader($oauthParams),
            'X-Payload-Signature: '.$this->generateMultipartPayloadSignature($fields, $oauthSignature),
            'Accept: application/json',
        ];

        $postFields = $this->flattenMultipartFields($fields);
        foreach ($files as $fieldName => $filePath) {
            $postFields[(string) $fieldName] = new \CURLFile($filePath);
        }

        $curlHandle = curl_init($url);
        if ($curlHandle === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $responseBody = curl_exec($curlHandle);
        $curlError = curl_error($curlHandle);
        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        if ($responseBody === false) {
            throw new RuntimeException('Bitmesh request failed: '.$curlError);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf(
                'Bitmesh API request failed with status %d: %s',
                $statusCode,
                $responseBody
            ));
        }

        return $this->decodeJsonResponse($responseBody);
    }

    private function sendUnsignedGetRequest(string $path): string
    {
        $url = $this->buildUrl($path);

        $curlHandle = curl_init($url);
        if ($curlHandle === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $responseBody = curl_exec($curlHandle);
        $curlError = curl_error($curlHandle);
        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        if ($responseBody === false) {
            throw new RuntimeException('Bitmesh request failed: '.$curlError);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf(
                'Bitmesh API request failed with status %d: %s',
                $statusCode,
                is_string($responseBody) ? $responseBody : ''
            ));
        }

        return $responseBody;
    }

    private function buildUrl(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function generateOAuthParams(string $method, string $url): array
    {
        $params = [
            'oauth_consumer_key' => $this->key,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_version' => '1.0',
        ];

        $params['oauth_signature'] = $this->generateSignature($method, $url, $params);

        return $params;
    }

    /**
     * @param  array<string, string>  $oauthParams
     */
    private function buildAuthorizationHeader(array $oauthParams): string
    {
        $headerParts = [];
        foreach ($oauthParams as $key => $value) {
            if (strpos($key, 'oauth_') === 0) {
                $headerParts[] = rawurlencode($key).'="'.rawurlencode((string) $value).'"';
            }
        }

        return 'OAuth '.implode(', ', $headerParts);
    }

    private function generatePayloadSignature(string $requestBody, string $oauthSignature): string
    {
        return hash('sha256', $requestBody.$this->key.$oauthSignature);
    }

    /**
     * @param  array<string, mixed>  $nonFileFields
     */
    private function generateMultipartPayloadSignature(array $nonFileFields, string $oauthSignature): string
    {
        ksort($nonFileFields);
        $json = json_encode($nonFileFields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            throw new RuntimeException('Failed to encode multipart fields for payload signature.');
        }

        return hash('sha256', $json.$this->key.$oauthSignature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeJson(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            throw new RuntimeException('Failed to encode request payload to JSON.');
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function sendSignedMultipartRequest(string $method, string $path, string $audioFilePath, array $fields = []): array
    {
        if (! is_file($audioFilePath) || ! is_readable($audioFilePath)) {
            throw new RuntimeException('Audio file does not exist or is not readable: '.$audioFilePath);
        }

        $url = $this->buildUrl($path);
        $oauthParams = $this->generateOAuthParams($method, $url);
        $oauthSignature = (string) ($oauthParams['oauth_signature'] ?? '');

        $headers = [
            'Authorization: '.$this->buildAuthorizationHeader($oauthParams),
            'X-Payload-Signature: '.$this->generateMultipartPayloadSignature($fields, $oauthSignature),
            'Accept: application/json',
        ];

        $postFields = $this->flattenMultipartFields($fields);
        $postFields['audio'] = new \CURLFile($audioFilePath);

        $curlHandle = curl_init($url);
        if ($curlHandle === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $responseBody = curl_exec($curlHandle);
        $curlError = curl_error($curlHandle);
        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        if ($responseBody === false) {
            throw new RuntimeException('Bitmesh request failed: '.$curlError);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf(
                'Bitmesh API request failed with status %d: %s',
                $statusCode,
                $responseBody
            ));
        }

        return $this->decodeJsonResponse($responseBody);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(string $responseBody): array
    {
        $decoded = json_decode($responseBody, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Bitmesh API returned a non-JSON or invalid JSON response.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function flattenMultipartFields(array $fields, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($fields as $key => $value) {
            $fieldName = $prefix === '' ? (string) $key : $prefix.'['.$key.']';
            if (is_array($value)) {
                $flattened += $this->flattenMultipartFields($value, $fieldName);

                continue;
            }

            $flattened[$fieldName] = $value;
        }

        return $flattened;
    }

    /**
     * @param  array<string, string>  $params
     */
    private function generateSignature(string $method, string $url, array $params): string
    {
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || ! isset($parsedUrl['host'])) {
            throw new RuntimeException('Invalid URL for OAuth signature generation.');
        }

        $scheme = $parsedUrl['scheme'] ?? 'http';
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'] ?? null;
        $path = isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : '';

        $normalizedUrl = $scheme.'://'.$host;
        if (($scheme === 'http' && $port !== null && $port !== 80) || ($scheme === 'https' && $port !== null && $port !== 443)) {
            $normalizedUrl .= ':'.$port;
        }
        $normalizedUrl .= '/'.$path;

        $queryParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }

        $allParams = array_merge($params, $queryParams);
        unset($allParams['oauth_signature']);
        ksort($allParams);

        $normalizedParams = [];
        foreach ($allParams as $key => $value) {
            $normalizedParams[] = rawurlencode((string) $key).'='.rawurlencode((string) $value);
        }

        $signatureBaseString = rawurlencode(strtoupper($method))
            .'&'.rawurlencode($normalizedUrl)
            .'&'.rawurlencode(implode('&', $normalizedParams));

        $signingKey = rawurlencode($this->secret).'&';

        return base64_encode(hash_hmac('sha1', $signatureBaseString, $signingKey, true));
    }
}
