# Render deployment

The production target is a **free Docker web service on Render**, region Ohio,
with Neon PostgreSQL for accounts, sessions and photo/GIF bytes. No Google Cloud
account, Composer packages, object-storage account, or persistent Render disk is
required. Media counts against the existing Neon storage quota; this setup is for
small demos. Move media to object storage if usage outgrows that quota.

`render.yaml` defines the service. On this account both Blueprint validation and
manual free service creation returned HTTP 402 (payment information required).
Add payment information at https://dashboard.render.com/billing to proceed.
No service was created during setup. The free plan is selected explicitly.

After that, run `python3 deploy/provision.py` using the existing Render CLI login
and prepared `.env.production`. It creates/reuses Camagru, configures secrets and
auto-deploy, and prints the first-deploy CLI command. The helper requires PyYAML
and uses the authenticated API for environment settings not exposed by the CLI.
It replaces the service env vars with the local configuration; keep that file
secure and maintain any additional settings there. It never prints credentials.

 GitHub Actions runs CI on PRs and main;
Render's `checksPass` auto-deploy setting deploys main only after checks pass.
Connect the GitHub repository through Render if GitHub checks aren't visible.
No Render API key is stored in GitHub. The actual service's env vars are configured
through Render's API/dashboard; `sync: false` fields are required for new Blueprints.

## Runtime configuration

Use `.env.production.example` as a reference. Keep secrets in Render environment
variables and the ignored mode-600 `.env.production` locally. The Neon pooled
host serves requests. The direct host runs versioned, transactionally locked
migrations at startup (`AUTO_MIGRATE=1`), since free services don't support
pre-deploy commands. Startup refuses an unfamiliar existing unversioned schema.
Do not drop existing tables to bypass this check. Keep migrations backward compatible.

Set `APP_URL` to the service's exact HTTPS URL and `COOKIE_SECURE=1`.
Keep `APP_KEY` stable: it encrypts queued mail. `MEDIA_DRIVER=pgsql` and
`SESSION_DRIVER=pgsql` keep data across Render restarts and redeployments.
For local development keep `.env` and `make up` (PostgreSQL + optional Mailpit).
For a local production smoke test use `make prod-up` with a prepared `.env.production`.

## Email

Render free blocks SMTP ports 25, 465 and 587. Use Brevo port 2525 with STARTTLS.
The SMTP key, exact SMTP login from Brevo's SMTP settings, and verified sender
are required. A Brevo account username may differ from its SMTP login. No
credentials are checked into Git. Mailpit remains the free local inbox.

Delivery uses the existing bounded outbox and retries on subsequent visits.
No worker, scheduler, or keepalive service is provisioned. Idle services sleep
and the first request can take longer. Photos persist in Neon despite Render's
ephemeral filesystem. See https://render.com/docs/free for free-service limits.

## Verification and rollback

CI builds the image and checks PHP syntax, configuration, migration safety,
repeatable migrations, PostgreSQL session lifecycle, and binary media persistence
using disposable services. Tests that reset schemas require CI_DISPOSABLE_DB=1.

After deployment, check `/health`, signup/verification, login, uploads and GIFs,
comments, deletion, and persistence across redeployment. Inspect Render logs for
SMTP failures. An AUTH check alone does not verify sender authorization/delivery.
Use the Render dashboard to roll back to a previous successful deploy; rollback
does not undo SQL migrations. Never change APP_KEY during routine releases.
