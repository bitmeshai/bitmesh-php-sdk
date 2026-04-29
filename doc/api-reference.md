## Bitmesh PHP SDK – API Reference

Reference for the `BitmeshAI\BitmeshClient` class and its methods. The client talks to the Bitmesh HTTP API; for the raw HTTP contract see [endpoints.md](endpoints.md).

- **Namespace**: `BitmeshAI`
- **Class**: `BitmeshClient`
- **Authentication**: All methods use OAuth 1.0 (OAuthOneLegged) with the consumer key/secret. The client sends the required headers and payload signature.

---

## Client constructor

### `new BitmeshClient(string $consumerKey, string $consumerSecret, string $apiBaseUrl = '...', string $userAgent = '...')`

- **Description**: Create a new Bitmesh AI client. Use this instance to call `chat()`, `image()`, `video()`, `videoStatus()`, transcription, and tools methods.
- **Parameters**
  - **`$consumerKey`** (required): `string` – OAuth consumer key provided by Bitmesh.
  - **`$consumerSecret`** (required): `string` – OAuth consumer secret provided by Bitmesh.
  - **`$apiBaseUrl`** (optional): `string` – Base URL of the Bitmesh AI API. Default: `https://aiproxyapi-production.up.railway.app`. No trailing slash (trimmed automatically).
  - **`$userAgent`** (optional): `string` – User-Agent header value. Default: `BitmeshPhpSdk/1.0`.

---

## Chat completions

### `chat(string|array $messages, ?string $model = null, array $options = [], array $extraPayload = [])`

- **Description**: Proxy chat completion. Calls `POST /chat` (or `POST /v1/chat`) and forwards to the configured AI provider.
- **HTTP**: `POST /chat`
- **Parameters**
  - **`$messages`** (required): `string|array`
    - **`string`**: Convenience form; wrapped as a single `"user"` message.
    - **`array`**: Full messages array as expected by the API. Minimum 1 element. Each item:
      - **`role`** (required): `string`, one of: `system`, `user`, `assistant`, `tool`
      - **`content`** (required): `string`
  - **`$model`** (optional): `string|null`
    - Pass a model name when the API key has no default model.
    - Pass `null` when the API key has a fixed default model (do not send `model` in that case).
  - **`$options`** (optional): `array<string, mixed>` – Optional request parameters. Supported keys:
    - **`max_tokens`**: `integer` ≥ 1
    - **`temperature`**: `number` between 0 and 2
    - **`repetition_penalty`**: `number` ≥ 0
    - **`frequency_penalty`**: `number` between -2 and 2
    - **`presence_penalty`**: `number` between -2 and 2
    - **`test`**: `boolean` – if `true`, charges are not applied (`live` flag is false).
  - **`$extraPayload`** (optional): `array<string, mixed>` – Additional fields merged into the request body.
- **Returns**: `array<string, mixed>` – Decoded JSON response (e.g. `id`, `usage`, `choices`).
- **Throws**: `\RuntimeException` on HTTP errors, transport errors, or JSON decode errors (message includes status and body `error` when present).

---

## Image generation

### `image(string $prompt, ?string $model = null, array $options = [], array $extraPayload = [])`

- **Description**: Generate images via the configured AI provider. Calls `POST /image`. Image URLs in the response are rewritten to your proxy (e.g. `https://<your-domain>/imgrslt/{id}`).
- **HTTP**: `POST /image`
- **Parameters**
  - **`$prompt`** (required): `string` – Prompt describing the image to generate.
  - **`$model`** (optional): `string|null` – Same semantics as for `chat()` (omit when API key has a fixed model).
  - **`$options`** (optional): `array<string, mixed>`. Supported keys:
    - **`width`**: `integer` ≥ 1
    - **`height`**: `integer` ≥ 1
    - **`steps`**: `integer` ≥ 1
    - **`seed`**: `integer`
    - **`n`**: `integer` ≥ 1 – number of images to generate
  - **`$extraPayload`** (optional): `array<string, mixed>` – Extra fields merged into the request body.
- **Returns**: `array<string, mixed>` – Decoded JSON response (e.g. `data` array with `url` per image).
- **Throws**: `\RuntimeException` on non-200 responses, transport errors, or JSON decode errors.

---

## Video generation

### `video(string $prompt, ?string $model = null, array $options = [], array $extraPayload = [])`

- **Description**: Generate videos via the underlying AI provider. Calls `POST /video`. Response may contain `id` (video job ID for `videoStatus()`) and `outputs` / `data`.
- **HTTP**: `POST /video`
- **Parameters**
  - **`$prompt`** (required): `string` – Prompt for the video, length 1–32000 characters.
  - **`$model`** (optional): `string|null` – Same semantics as for `chat()` (omit when API key has a fixed model).
  - **`$options`** (optional): `array<string, mixed>`. Supported keys:
    - **`width`**: `integer` ≥ 1
    - **`height`**: `integer` ≥ 1
    - **`seconds`**: `string` – duration (per provider API)
    - **`fps`**: `integer` ≥ 1
    - **`steps`**: `integer` between 10 and 50
    - **`seed`**: `integer`
    - **`guidance_scale`**: `number` ≥ 0
    - **`output_format`**: `string`, one of `MP4`, `WEBM`
    - **`output_quality`**: `integer` ≥ 1
    - **`negative_prompt`**: `string`
    - **`frame_images`**: `array` – Items:
      - **`input_image`** (required with `frame_images`): `string`
      - **`frame`** (required with `frame_images`): `string` – frame index or `"last"`
    - **`reference_images`**: `array` of `string`
  - **`$extraPayload`** (optional): `array<string, mixed>` – Extra fields merged into the request body.
- **Returns**: `array<string, mixed>` – Decoded JSON response (e.g. `object`, `id`, `status`, `created_at`, `seconds`, `size`, or provider-specific `outputs` / `data`).
- **Throws**: `\RuntimeException` on non-200 responses, transport errors, or JSON decode errors.

---

## Video status & retrieval

### `videoStatus(string $id)`

- **Description**: Fetch video generation job details from the provider (status, outputs, video_url, cost). Calls `GET /video/{id}`.
- **HTTP**: `GET /video/{id}`
- **Parameters**
  - **`$id`** (required): `string` – Provider video job ID (e.g. `id` from a prior `video()` response). The value is URL-encoded in the path.
- **Returns**: `array<string, mixed>` – Decoded JSON response, e.g.:
  - **`id`**: job ID
  - **`status`**: e.g. `queued`, `running`, `in_progress`, `completed`
  - **`outputs`**: may contain:
    - **`video_url`**: video URL (rewritten to your proxy when applicable)
    - **`cost`**: numeric cost fields (per provider)
- **Throws**: `\RuntimeException` on non-200 responses, transport errors, or JSON decode errors.

---

## Transcribe (recorded audio)

### `transcribeRecordedFromUrl(string $audioUrl, array $options = [], array $extraPayload = [])`

- **Description**: Submit a prerecorded transcription job using AssemblyAI URL mode.
- **HTTP**: `POST /transcribe-recorded`
- **Parameters**
  - `$audioUrl` (required): `string` – Public URL to the audio file.
  - `$options` (optional): `array<string,mixed>` – Optional transcription fields (common ones from `doc/endpoints.md`):
    - `speech_models`: `array` (`universal-3-pro | universal-2`)
    - `speech_model`: `string` (alias mapped to `speech_models` internally)
    - `language_code`: `string`
    - `punctuate`: `boolean`
    - `format_text`: `boolean`
    - `dual_channel`: `boolean`
    - `language_detection`: `boolean`
    - `webhook_url`: `string` (URL)
    - `test`: `boolean`
  - `$extraPayload` (optional): Extra fields merged into the request body.
- **Returns**: `array<string, mixed>` – Provider response (typically includes `id`).
- **Throws**: `\RuntimeException` on non-200 responses, transport errors, or JSON decode errors.

---

### `transcribeRecordedFromFile(string $audioFilePath, array $options = [], array $extraPayload = [])`

- **Description**: Submit a prerecorded transcription job using multipart upload mode.
- **HTTP**: `POST /transcribe-recorded` with `multipart/form-data`
- **Parameters**
  - `$audioFilePath` (required): `string` – Path to an audio file (mp3/wav/m4a/mp4/flac/ogg/webm).
  - `$options` (optional): Same optional fields as URL mode (see `transcribeRecordedFromUrl()`), excluding `audio_url` (the SDK will not send `audio_url` in this method).
  - `$extraPayload` (optional): Extra fields merged into multipart non-file fields.
- **Returns**: `array<string, mixed>`
- **Throws**: `\RuntimeException` on non-200 responses, transport errors, or JSON decode errors.

---

### `transcribeRecordedStatus(string $id, array $queryParams = [])`

- **Description**: Poll transcription job status/result.
- **HTTP**: `GET /transcribe-recorded/{id}`
- **Parameters**
  - `$id` (required): `string` – Job id from a prior submission.
  - `$queryParams` (optional): Query params (common):
    - `test`: `boolean`
- **Returns**: `array<string, mixed>` – Provider status payload.
- **Throws**: `\RuntimeException` on non-200 responses, transport errors, or JSON decode errors.

---

## Tools

### `backgroundRemoval(string $imageFilePath, ?string $returnForm = null, array $options = [], array $extraPayload = [])`

- **Description**: Tool endpoint that removes the background from an uploaded image.
- **HTTP**: `POST /tools/general/background-removal` with `multipart/form-data`
- **Parameters**
  - `$imageFilePath` (required): `string` – Path to an input image file.
  - `$returnForm` (optional): `string|null` – One of `mask | whiteBK | crop`.
  - `$options` (optional): `array<string,mixed>` – Optional fields (commonly `test`).
  - `$extraPayload` (optional): Extra multipart non-file fields merged in.
- **Returns**: `array<string, mixed>` – Normalized tool response.
- **Throws**: `\RuntimeException` on non-200 responses, transport errors, or JSON decode errors.

---

### `queryAsyncTaskResult(string $taskId, array $extraPayload = [])`

- **Description**: Query async tool task status/result.
- **HTTP**: `POST /tools/query-async-task-result`
- **Parameters**
  - `$taskId` (required): `string` – `task_id` to query.
  - `$extraPayload` (optional): Extra fields merged into JSON body.
- **Returns**: `array<string, mixed>` – Task status/result payload.
- **Throws**: `\RuntimeException` on non-200 responses, transport errors, or JSON decode errors.

---

## Notes

- **Rate limiting**: The API enforces throttling (per-IP, per-API-key, per-endpoint). The SDK does not retry; non-200 responses throw.
- **Model selection**: If an API key is bound to a specific model, pass `null` for `$model` in `chat()`, `image()`, and `video()` so the client does not send a `model` field.
- **Unlisted endpoints**: The SDK does not implement `GET /test` (health) or `GET /imgrslt/{id}` (public image proxy). Use HTTP directly or extend the client if needed.
