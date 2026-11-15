# Camagru

A photo-sharing app built with PHP 8.4, PostgreSQL, and plain JavaScript. Capture or upload photos, add frames, create GIFs, and share likes and comments.

See [ARCHITECTURE.md](ARCHITECTURE.md) for Mermaid diagrams tracing the UI through the code, database, media storage, and email delivery.

## Run

Requires Docker Compose and Make. Prepare `.env` before starting:

```sh
cp .env.example .env
chmod 600 .env
```

Replace both `GENERATE_ME` values in `.env`: use a separate random value for `DB_PASSWORD` and `APP_KEY`. Generate each with `openssl rand -hex 32` (run it twice). `APP_KEY` must contain exactly 64 lowercase hexadecimal characters. Review the other values for your environment.

Keep `APP_URL` equal to the browser URL, including its port, so CSRF origin checks work. Set `COOKIE_SECURE=1` when using HTTPS.

The app requires an existing `.env`; startup rejects missing configuration, placeholder secrets, and invalid key/URL/port settings. Configuration is never generated automatically.

Then start the app:

```sh
make up
```

- App: http://localhost:8080
- Email inbox: http://localhost:8025

Open the confirmation email in Mailpit after signing up. Change `APP_URL`, `APP_PORT`, and `MAILPIT_PORT` in `.env` if needed.

### SMTP configuration

The example `.env` enables Mailpit for development with `COMPOSE_PROFILES=dev`, `SMTP_HOST=mailpit`, `SMTP_PORT=1025`, and `SMTP_ENCRYPTION=none`. Leave `SMTP_USER` and `SMTP_PASSWORD` empty for Mailpit.

To use an external SMTP provider, update `.env`:

```dotenv
COMPOSE_PROFILES=
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_ENCRYPTION=starttls
SMTP_USER=your-smtp-username
SMTP_PASSWORD='your-smtp-password'
SMTP_FROM=no-reply@your-domain.com
MAIL_FROM="Camagru Accounts <no-reply@your-domain.com>"
```

Use the host, credentials, and authorized sender address supplied by your provider. `SMTP_FROM` is the envelope sender; `MAIL_FROM` is the visible From header. `SMTP_ENCRYPTION` accepts `none`, `starttls` (usually port 587), or `tls` (implicit TLS, usually port 465). TLS connections verify the server certificate. Set both credentials together, or leave both empty for a relay without authentication. Single quotes around the password preserve literal `$` and `#` characters in Compose's `.env` format.

Run `make up` after changing `.env`; this recreates the web container with the new settings. When switching an existing development stack to an external provider, also stop its inbox with `docker compose --profile dev stop mailpit`. Mailpit starts only when the `dev` profile is enabled and is not a dependency of the web service. Startup rejects missing or invalid SMTP settings. Existing installations should copy the new SMTP and profile settings from `.env.example`.

Run `php tests/smtp.php` to check SMTP configuration validation without contacting a provider.

```sh
make down   # Stop; keep accounts and images
make logs   # Follow service logs
```

Nginx and PHP-FPM share the `web` container. Container configuration lives in `docker/`. PostgreSQL initializes new database volumes from `src/Database/schema.sql`. Data persists in Docker volumes.

PHP lives in `src/`, templates in `src/Views/`, and static assets in `public/assets/`. JavaScript uses native modules; there is no package manager or build step.

Select several stickers to combine them on photos or GIFs; select None to clear them. The live preview and server apply stickers in picker order.
