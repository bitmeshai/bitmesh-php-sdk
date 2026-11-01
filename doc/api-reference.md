## Bitmesh PHP SDK – API Reference

Reference for the `BitmeshAI\BitmeshClient` class. All requests use the fixed host **`https://api.bitmesh.ai`**.

- **Namespace**: `BitmeshAI`
- **Class**: `BitmeshClient`
- **Requirements**: PHP 8+, ext-curl, ext-json.
- **Authentication (signed requests)**: OAuth 1.0 “one-legged” (`Authorization: OAuth ...`, HMAC-SHA1). JSON and GET bodies use **`X-Payload-Signature`**: SHA-256 of `body + consumerKey + oauthSignature` (empty string body for GET). Multipart requests sign a canonical JSON encoding of **non-file** fields only (see implementation), same header name.
- **Unsigned**: `getToolsResult()` calls `GET .../tools-result/...` without OAuth (public asset fetch).

On failure the client throws **`RuntimeException`** (HTTP status and body text, transport errors, missing files, invalid JSON, or validation errors such as empty id/path).

---

## Constructor

### `new BitmeshClient(string $key, string $secret, int $timeoutSeconds = 30)`

| Parameter | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | OAuth consumer key |
| `$secret` | `string` | OAuth consumer secret |
| `$timeoutSeconds` | `int` | cURL timeout in seconds (default `30`) |

---

## `chat(array $payload): array`

- **HTTP**: `POST /chat` with `Content-Type: application/json`
- **Description**: Chat completions. Pass the **exact JSON body** the API expects (e.g. `model`, `messages`, `max_tokens`, `temperature`, …). If your API key is bound to a fixed model, omit `model` in the payload (the server may reject a conflicting `model` field).
- **Returns**: Decoded JSON object (`array<string, mixed>`).

---

## `image(array $payload): array`

- **HTTP**: `POST /image` (`application/json`)
- **Description**: Image generation. Payload fields depend on the provider (e.g. `prompt`, `model`, `reference_images`, dimensions, …).

---

## `video(array $payload): array`

- **HTTP**: `POST /video` (`application/json`)
- **Description**: Video generation. Typical keys include `prompt`, `model`, `frame_images`, etc. The response shape is provider-specific (often includes `id` for a job to poll).

---

## `getVideo(string $id, array $query = []): array`

- **HTTP**: `GET /video/{id}` (id is URL-encoded; optional `$query` merged into the query string)
- **Description**: Poll video generation job status or retrieve result metadata. Empty or whitespace-only `$id` throws **`RuntimeException`**.

---

## `transcribeFile(string $audioFilePath, array $fields = []): array`

- **HTTP**: `POST /transcribe-recorded` (`multipart/form-data`)
- **Description**: Upload a local audio file. The file is sent as the **`audio`** part. `$fields` are non-file form fields (e.g. `speech_models` as nested arrays are flattened for multipart and included in the payload signature). The file must exist and be readable.
- **Returns**: JSON response (e.g. often includes `id` for the transcript job).

---

## `getTranscribeRecorded(string $id, array $query = []): array`

- **HTTP**: `GET /transcribe-recorded/{id}` (id is URL-encoded; optional `$query` merged into the query string)
- **Description**: Poll transcription job status or fetch result. Empty or whitespace-only `$id` throws **`RuntimeException`**.

---

## `toolsGeneralBackgroundRemoval(array $fields, string $imagePath): array`

- **HTTP**: `POST /tools/general/background-removal` (multipart)
- **Description**: Background removal. **`image`** is taken from `$imagePath` (JPEG, PNG, BMP or WebP, up to 3 MB, under 2000×2000 px). Other tool options go in `$fields` (e.g. `return_form` => `mask` | `whiteBK` | `crop`). File must exist and be readable.
- **Returns**: `data.image_url`, a plain link to the result on the provider's storage (deleted after about 24 hours).

---

## `toolsPortraitTryOnClothes(array $fields, array $files): array`

- **HTTP**: `POST /tools/portrait/try-on-clothes` (multipart)
- **Description**: Virtual try-on. `$files` maps **field name → absolute path** (`person_image` and `top_garment` required, `bottom_garment` optional; up to 5 MB each). `$fields` may include `resolution` (`-1`, `1024`, `1280`), `restore_face` and `test`. Every path must be a readable file.
- **Returns**: `task_id` to poll with `toolsQueryAsyncTaskResult()`. Charged when the task is accepted.

---

## `toolsQueryAsyncTaskResult(string $taskId): array`

- **HTTP**: `POST /tools/query-async-task-result` (`application/json` body `{"task_id":"..."}`)
- **Description**: Poll an async tools task. `status` is `queued`, `processing`, `success` or `fail`; on `success` the picture is at `output.image_url`. An unknown task id answers `400`. Poll every 10 seconds or slower: this endpoint shares the per-IP media rate limit. Empty `$taskId` throws **`RuntimeException`**.

---

## `imageAnimate(array $payload): array`

- **HTTP**: `POST /tools/video/image-animate` (`application/json`)
- **Description**: DomoAI image-to-video. **Async**: returns a `task_id` immediately; the cost is settled later (no charge at submit time). Poll with `getImageAnimate()` or pass a `callback_url` in the payload to be notified.
- **Payload keys**: `model` (required, `animate-2.4-faster` | `animate-2.4-advanced`), `image` (required object with `bytes_base64_encoded`; JPEG, PNG, WebP or AVIF up to 10 MB), `seconds` (required integer, `1`–`10`), optional `prompt` (≤2000), `aspect_ratio` (`16:9` | `9:16` | `1:1` | `4:3` | `3:4`), `callback_url`, `test`.
- **Image**: base64-encode locally, e.g. `'image' => ['bytes_base64_encoded' => base64_encode((string) file_get_contents($path))]`.
- **Returns**: Decoded JSON, typically `{"code":0,"data":{"task_id":"..."}}`.

---

## `getImageAnimate(string $taskId, array $query = []): array`

- **HTTP**: `GET /tools/video/image-animate/{task_id}` (id is URL-encoded; optional `$query` merged into the query string, e.g. `['test' => 1]`)
- **Description**: Poll an image-to-video task. The response `data.status` cycles `PENDING` → `QUEUING` → `PROCESSING` → `SUCCESS` (terminal also `FAILED` / `CANCELED`); on `SUCCESS`, `data.output_videos[].url` holds the video URL (signed, valid for about 8 hours) and `data.credits` the credits consumed. Empty or whitespace-only `$taskId` throws **`RuntimeException`**.

---

## `getToolsResult(string $path): string`

- **HTTP**: `GET /tools-result/{path}` (path segments after `tools-result/`)
- **Description**: Download raw bytes for a result served by the API under `/tools-result/`. Current tools return plain links to the provider's storage instead; fetch those directly. **No** OAuth or payload signature. Pass the path relative to `tools-result/` (leading slashes are normalized). Empty path throws **`RuntimeException`**. Returns the response body as a **`string`** (binary-safe).

---

## Notes

- **Success criteria**: HTTP status must be 2xx; JSON endpoints must decode to a JSON **object** (`array`), not a bare string/number/list at the top level.
- **Rate limits**: The API may throttle; the client does not retry automatically.

For runnable snippets, see [code-examples.md](code-examples.md).
