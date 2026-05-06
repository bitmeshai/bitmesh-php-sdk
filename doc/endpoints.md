## AI Proxy API Reference

Basic reference for the public HTTP API exposed by this service.

- **Base URL**: `https://<your-domain>`
- **Content Type**:
  - Most endpoints use `application/json`
  - Tool upload endpoints use `multipart/form-data`
- **Authentication**: Protected endpoints require OAuthOneLegged + API key checks.
  - **JSON bodies** (`application/json`): `X-Payload-Signature = sha256(raw_request_body + consumer_key + oauth_signature)`
  - **Tool multipart uploads** (`tools/` + file fields): sign canonical JSON of non-file fields (`ksort`, `json_encode`) as `sha256(json_string + consumer_key + oauth_signature)`

---

## Health & Utility

### `GET /test`

- **Description**: Authenticated health-check endpoint.
- **Auth**: Required.
- **Response**: `200 OK`

```json
{
  "message": "you are the man"
}
```

---

## Chat

### `POST /chat`
### `POST /v1/chat`

- **Description**: Chat completion proxy.
- **Auth**: Required.
- **Body**
  - `model` (`string`) - required when API key has no fixed model, prohibited when key is fixed
  - `messages` (required `array`, min 1)
    - `messages.*.role`: `system | user | assistant | tool`
    - `messages.*.content`: mixed/string
  - Optional: `max_tokens`, `temperature`, `repetition_penalty`, `frequency_penalty`, `presence_penalty`, `test`
- **Responses**
  - `200` provider response
  - `422` validation error
  - `4xx/5xx` provider/internal error

---

## Image

### `POST /image`
### `POST /v1/image`

- **Description**: Image generation proxy.
- **Auth**: Required.
- **Body**
  - `model` (`string`) - same fixed-model rule as chat
  - `prompt` (required `string`)
  - Optional: `width`, `height`, `steps`, `seed`, `n`, `reference_images`
- **Responses**
  - `200` provider response (`data[].url` rewritten to `/imgrslt/{id}`)
  - `422` validation error
  - `4xx/5xx` provider/internal error

---

## Video

### `POST /video`
### `POST /v1/video`

- **Description**: Video generation proxy.
- **Auth**: Required.
- **Body**
  - `model` (`string`) - same fixed-model rule as chat
  - `prompt` (required `string`)
  - Optional: `width`, `height`, `seconds`, `fps`, `steps`, `seed`, `guidance_scale`, `output_format`, `output_quality`, `negative_prompt`, `frame_images`, `reference_images`
- **Responses**
  - `200` provider response
  - `422` validation error
  - `4xx/5xx` provider/internal error

### `GET /video/{id}`
### `GET /v1/video/{id}`

- **Description**: Get provider video job status/details.
- **Auth**: Required.
- **Path Params**
  - `id` (`string`) provider video job id
- **Responses**
  - `200` provider status payload
  - `4xx/5xx` error payload

---

## Transcribe

### `POST /transcribe-recorded`
### `POST /v1/transcribe-recorded`

- **Description**: Submit an AssemblyAI prerecorded transcription job.
- **Auth**: Required.
- **Content Type**: `application/json` (URL mode) or `multipart/form-data` (direct file upload)
- **Mode selection**: The controller branches on whether `audio_url` is provided.
  - If `audio_url` is present and non-empty - **URL mode** (Assembly pulls the media from that URL).
  - If `audio_url` is missing, `null`, or an empty string - **Upload mode** (the proxy uploads the `audio` file to AssemblyAI `/v2/upload` and uses the returned URL internally).
- **URL mode body** (JSON or multipart)
  - `audio_url` (required `url`)
  - `speech_models` (optional `array`, min 1)
    - `speech_models.*`: `universal-3-pro | universal-2`
  - `speech_model` (optional `string`)
    - backward-compatible alias; internally mapped to `speech_models: [speech_model]` if `speech_models` is missing
  - `language_code` (optional `string`)
  - `punctuate` (optional `boolean`)
  - `format_text` (optional `boolean`)
  - `dual_channel` (optional `boolean`)
  - `language_detection` (optional `boolean`)
  - `webhook_url` (optional `url`)
  - `test` (optional `boolean`)
- **Upload mode body** (multipart/form-data)
  - `audio` (required `file`) - mp3, wav, m4a, mp4, flac, ogg, or webm; max size controlled by `ASSEMBLY_AI_MAX_UPLOAD_SIZE_KB` (default `512000` KB = 500 MB)
  - All other optional fields from URL mode are accepted (`speech_models`, `speech_model`, `language_code`, `punctuate`, `format_text`, `dual_channel`, `language_detection`, `webhook_url`, `test`)
  - Do NOT send `audio_url` - its presence (even as empty string after trim is treated as URL mode)
  - Signing: multipart requests sign the canonical JSON of non-file fields (`ksort` + `json_encode` with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`) as `sha256(json_string + consumer_key + oauth_signature)` - same rule as tool uploads
- **Behavior**
  - Enforces global active transcription limit (default `5`)
  - Returns `429` when capacity is full
  - Upload step (upload mode) runs before concurrency acquisition; a failed upload does not consume a slot
  - Uploads to AssemblyAI are not separately billed; per-minute billing still settles on `completed`
- **Responses**
  - `200` accepted by provider (returns transcript `id`)
  - `413` upload exceeds PHP `upload_max_filesize` / `post_max_size` (upload mode only); body includes `php_upload_error`
  - `422` validation error, or invalid audio upload (partial, wrong mime, wrong size per Laravel rules)
  - `429` concurrent limit reached
  - `4xx/5xx` provider/internal error (including AssemblyAI upload failure)
- **Upload error response body** (status `413` or `422`, upload mode only)
  ```json
  {
    "error": "Invalid audio upload",
    "details": "<human-readable reason, e.g. exceeds upload_max_filesize>",
    "php_upload_error": 1
  }
  ```
  `php_upload_error` corresponds to PHP `UPLOAD_ERR_*` constants (1 = `INI_SIZE`, 2 = `FORM_SIZE`, 3 = `PARTIAL`, 4 = `NO_FILE`).
- **AssemblyAI upload failure body** (status echoed from provider)
  ```json
  { "error": "AssemblyAI upload failed", "details": "<provider error>" }
  ```
- **Infra note**: 500 MB uploads require `upload_max_filesize`, `post_max_size` (PHP) and `client_max_body_size` (nginx) to be tuned accordingly. For the Docker setup, `src/php.ini` is mounted into the container via `docker-compose.yml` as `/usr/local/etc/php/conf.d/zzz-custom.ini`.

### `GET /transcribe-recorded/{id}`
### `GET /v1/transcribe-recorded/{id}`

- **Description**: Poll transcription job status/result.
- **Auth**: Required.
- **Path Params**
  - `id` (`string`, route pattern `[A-Za-z0-9-]+`)
- **Query Params**
  - `test` (optional `boolean`)
- **Behavior**
  - On terminal states (`completed`/`error`), the service releases a concurrency slot
  - On completed jobs, pricing is settled per minute using configured rates
- **Responses**
  - `200` transcription status/result payload
  - `4xx/5xx` provider/internal error

---

## Tools

These routes do not use chat/image/video model selection. Pricing is configured in `config/ai_tools.php`.

### `POST /tools/general/background-removal`
### `POST /v1/tools/general/background-removal`

- **Description**: Remove background from an uploaded image.
- **Auth**: Required.
- **Headers**
  - `Content-Type: multipart/form-data`
  - `Authorization` (OAuth1)
  - `X-Payload-Signature` using canonical non-file-field signing
- **Body** (`multipart/form-data`)
  - `image` (required file/image)
  - `return_form` (optional `mask | whiteBK | crop`)
  - `test` (optional `boolean`)
- **Responses**
  - `200` normalized success/failure payload
  - `422` validation error
  - `402` insufficient balance
  - `503` tool disabled
  - `4xx/5xx` provider/internal error

### `POST /tools/portrait/try-on-clothes`
### `POST /v1/tools/portrait/try-on-clothes`

- **Description**: Virtual try-on clothing generation.
- **Auth**: Required.
- **Headers/Body**: multipart upload with OAuth signing as above.
- **Responses**: normalized tool response + standard validation/billing errors.

### `POST /tools/query-async-task-result`
### `POST /v1/tools/query-async-task-result`

- **Description**: Query async task status/result for tool jobs.
- **Auth**: Required.
- **Body**
  - `task_id` (required `string`)
- **Responses**
  - `200` task status/result
  - `422` validation error
  - `4xx/5xx` provider/internal error

### Public Tool Result Proxies

### `GET /tools-result/{date}/{id}`
### `GET /tools-result/{path}`

- **Description**: Public proxy routes for tool result assets.
- **Auth**: Not required.

---

## Public Image Proxy

### `GET /imgrslt/{id}`

- **Description**: Public image proxy for Together image URLs.
- **Auth**: Not required.
- **Responses**
  - `200` binary image
  - `404` not found
  - `500` fetch error

---

## Notes

- **Rate Limiting**: Multiple throttles are applied (IP, API key, endpoint-level where configured).
- **Billing**:
  - Chat/image/video use model/provider pricing tables
  - Tools use `config/ai_tools.php` `cost`/`sell_price`
  - Transcribe-recorded uses per-minute rates from `config/ai_providers.php` (`assembly_ai`)
- **Model Selection**:
  - Chat/image/video follow API-key fixed-model rules
  - Tools do not use `model`
  - Transcribe-recorded does not use `model`; it uses Assembly speech model parameters.

