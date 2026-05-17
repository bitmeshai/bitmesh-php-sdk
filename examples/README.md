# Bitmesh.ai API SDK – PHP demo

Minimal PHP project that demos the **`BitmeshAI\BitmeshClient`** shipped in this repo’s parent SDK. Composer autoload maps **`BitmeshAI\\`** to **`../src/`** so this app uses **`../src/BitmeshClient.php`** instead of pulling **bitmeshai/bitmesh-php-sdk** from Packagist.

The client always calls **`https://api.bitmesh.ai`**. You pass **payload arrays** to `chat()`, `image()`, and `video()` exactly as the HTTP API expects.

## Setup

From the **`examples/`** directory:

```bash
composer install
```

If you clone only the **`examples`** folder elsewhere, install the SDK with Composer instead (see the root package **bitmeshai/bitmesh-php-sdk**) or copy `src/BitmeshClient.php` and adjust **`autoload.psr-4`** in **`composer.json`**.

Create `.env` (or copy `example.env`) and set:

- `BITMESH_CONSUMER_KEY`
- `BITMESH_CONSUMER_SECRET`

## Run

Point your web server document root at `public/`, or use PHP built-in server:

```bash
php -S localhost:8080 -t public
```

Then open (each page shows copy-paste PHP and a live call when credentials are set):

- http://localhost:8080/ — index
- http://localhost:8080/chat — `POST /chat`
- http://localhost:8080/chat-vision — multimodal `chat`
- http://localhost:8080/image — `POST /image`
- http://localhost:8080/image-to-image — `image` with `reference_images`
- http://localhost:8080/video — `POST /video`
- http://localhost:8080/video-status — job polling example (`getVideo()`)
- http://localhost:8080/transcribe-file — `transcribeFile()`
- http://localhost:8080/transcribe-status — `getTranscribeRecorded()`
- http://localhost:8080/tool-bgremove — `toolsGeneralBackgroundRemoval()`
- http://localhost:8080/tool-tryon — `toolsPortraitTryOnClothes()` and `toolsQueryAsyncTaskResult()`

## Extra sample script

- `public/file.php` — CLI transcript submit + poll (loads project `vendor/` and `.env`).

## Structure

- `public/index.php` — entry point, dispatches via router
- `src/Router.php` — routes and embedded example snippets

Full method list and signing rules: see the SDK’s `doc/api-reference.md` and `doc/code-examples.md`.
