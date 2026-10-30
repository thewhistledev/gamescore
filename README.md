# GameScore

A self-hosted game information and update platform designed specifically for ordinary PHP shared hosting.

## What it does

- Stunning responsive dark UI with configurable accent colour
- Game directory and individual game pages
- Verified external update ingestion via the Steam News API
- Manual editorial updates from the admin area
- Provenance on every imported update: source, URL, timestamp and verification flag
- User registration, secure password hashing, sessions and CSRF protection
- Admin role and content management
- JSON API endpoints: `/api/games` and `/api/updates`
- Shared-host cron endpoint for automatic update imports
- Optional Stripe Checkout infrastructure, disabled by default
- Four-step browser installation wizard
- MySQL/MariaDB database
- No Node.js runtime, queue worker, Docker, Redis or shell access required

## Install

1. Upload the repository contents to the document root of your PHP hosting account.
2. Point the domain at that directory.
3. Make `config/` and `storage/` writable by PHP during installation.
4. Open the site. The installer appears automatically.
5. Enter the site identity, database credentials and first admin account.
6. Add games from `/admin` and give games a Steam App ID if you want automatic Steam News imports.

PHP 8.1+ is recommended. Required extensions are PDO MySQL, cURL, JSON and OpenSSL.

## Automatic updates

Create a shared-host cron job that requests:

`https://your-domain.example/cron?token=YOUR_CRON_TOKEN`

The installer generates the token in `config/config.php`. A cron interval of 15-60 minutes is suitable for most sites.

## Payments

Payments are intentionally **OFF by default**. The installer writes an explicit disabled Stripe configuration. To enable Checkout, configure `enabled`, `secret`, `price_id` and `webhook_secret` in `config/config.php`, then expose `/checkout` to the relevant UI. Stripe webhook signature verification is included.

Do not put Stripe secret keys in JavaScript or public repository files.

## API

`GET /api/games`

`GET /api/updates`

Both return JSON and are deliberately simple enough to consume from another website, app or bot.

## Architecture

GameScore is intentionally conventional: a small PHP front controller, PDO/MySQL, server-rendered views and static CSS. This is less fashionable than requiring six services to display a game title, which is precisely why it works well on cheap shared hosting.

The source adapter boundary currently starts with Steam News. More adapters can be added to `app/bootstrap.php` or split into `app/sources/` later without changing the public pages or API.
