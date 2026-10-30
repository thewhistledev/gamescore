# GameScore

A self-hosted game information and update platform designed specifically for ordinary PHP shared hosting.

## What it does

- Dark responsive UI with configurable branding and accent colour
- Game directory and individual game pages
- Verified external update ingestion via Steam News
- Configurable JSON API, RSS and Atom source adapters with field mapping
- Manual editorial updates
- Source provenance, verification status, timestamps and original URLs
- User registration, password hashing, sessions and CSRF protection
- Admin control room with **22 configuration menus and 10+ options in every menu**
- Public JSON API: `/api/games` and `/api/updates`
- Shared-host cron endpoint for automatic update imports
- GitHub-based website updater using a repository manifest
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
7. Configure additional APIs or feeds from **Admin → Sources**.

PHP 8.1+ is recommended. Required extensions are PDO MySQL, cURL, JSON and OpenSSL. RSS/Atom ingestion also uses SimpleXML when enabled by the host.

## External sources

The admin source manager accepts JSON APIs and RSS/Atom feeds. Each source can be attached to a game and can define:

- Endpoint URL
- HTTP method
- Custom request headers
- Enabled/disabled state
- Verified/unverified status
- Import interval
- JSON field mappings for item list, title, body, URL, ID and publication date

Example JSON mapping:

```json
{
  "items": "data",
  "title": "title",
  "body": "body",
  "url": "url",
  "id": "id",
  "date": "published_at"
}
```

Imported source markup is cleaned before display. This specifically prevents Steam's BBCode such as `[h1]`, `[url]` and `[img]` from being dumped into a game page like the website has suffered a minor formatting stroke.

## Automatic updates

Create a shared-host cron job that requests:

`https://your-domain.example/cron?token=YOUR_CRON_TOKEN`

The installer generates the token in `config/config.php`. A cron interval of 15-60 minutes is suitable for most sites.

## Website updater

The admin **Updater** menu checks the configured GitHub repository and branch for `update.json`. The updater downloads only the files listed in the manifest, writes them through temporary files, and can create backups under `storage/update-backups/` before replacement. `config/` and `storage/` are never accepted as update targets.

The updater is intentionally compatible with hosts that do not provide SSH, Composer or shell access.

## Payments

Payments are intentionally **OFF by default**. Stripe configuration remains explicit and can be enabled later. Do not put Stripe secret keys in JavaScript or public repository files.

## API

`GET /api/games`

`GET /api/updates`

Both return JSON and are deliberately simple enough to consume from another website, app or bot.

## Architecture

GameScore is intentionally conventional: a small PHP front controller, PDO/MySQL, server-rendered views and static CSS. This is less fashionable than requiring six services to display a game title, which is precisely why it works well on cheap shared hosting.

The data pipeline is split into Steam, configurable API/feed sources, manual editorial content and a GitHub-driven application updater. The public pages do not care which source supplied an update, so new adapters can be added without redesigning the site.
