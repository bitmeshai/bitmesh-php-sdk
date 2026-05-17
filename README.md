## Bitmesh PHP SDK

PHP SDK for calling the Bitmesh AI API (chat, image, video, transcription, and tools) with built-in authentication. Browse [all available models](https://bitmesh.ai/models) on Bitmesh.ai.

### Installation

Install via Composer:

```bash
composer require bitmeshai/bitmesh-php-sdk
```

### Development and tests

From a clone of this repository:

```bash
composer install
./vendor/bin/phpunit
```

By default, PHPUnit **skips** HTTP integration cases when credentials are unset, runs **local validation** tests, and **excludes** the `@group expensive` suite (video generation). To run integration tests against `https://api.bitmesh.ai`, set:

- `BITMESH_TEST_CONSUMER_KEY` — OAuth consumer key
- `BITMESH_TEST_CONSUMER_SECRET` — OAuth consumer secret

Then run:

```bash
export BITMESH_TEST_CONSUMER_KEY="your-oauth-consumer-key"
export BITMESH_TEST_CONSUMER_SECRET="your-oauth-consumer-secret"
./vendor/bin/phpunit
```

To also run video tests (higher cost), include the `expensive` group:

```bash
./vendor/bin/phpunit --no-exclude-group expensive
```

Integration tests use fixture files under `tests/Fixtures/` (images and a sample MP3 for transcription).
