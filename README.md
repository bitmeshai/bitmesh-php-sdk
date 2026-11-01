## Bitmesh PHP SDK

PHP SDK for calling the Bitmesh AI API (chat, image, video, image-to-video, transcription, and tools) with built-in authentication. Browse [all available models](https://bitmesh.ai/models) on Bitmesh.ai.

### Installation

Install via Composer. The package is not on Packagist yet, so add the GitHub repository first:

```bash
composer config repositories.bitmesh vcs https://github.com/bitmeshai/bitmesh-php-sdk
composer require bitmeshai/bitmesh-php-sdk:^2.1
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

Or keep the credentials in a local `phpunit.xml` (git-ignored; PHPUnit reads it in place of `phpunit.xml.dist`): copy `phpunit.xml.dist` and add

```xml
<php>
    <env name="BITMESH_TEST_CONSUMER_KEY" value="your-oauth-consumer-key"/>
    <env name="BITMESH_TEST_CONSUMER_SECRET" value="your-oauth-consumer-secret"/>
</php>
```

Never commit credentials to `phpunit.xml.dist`.

Video tests cost more and are left out by default. Run them on their own:

```bash
./vendor/bin/phpunit --group expensive
```

Integration tests use fixture files under `tests/Fixtures/` (images and a sample MP3 for transcription).
