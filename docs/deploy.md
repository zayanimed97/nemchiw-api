# Deploying to Hostinger (api.lunara-tn.com)

Shared hosting, SSH on port 65002. The app lives **outside** `public_html`; only
`public/` is exposed, through a symlink.

## One-time setup

1. hPanel → Advanced → PHP Configuration: PHP **8.3 or newer** for the site.
   Extensions: pdo_mysql, mbstring, openssl, gd, exif, fileinfo, bcmath, intl.
   PHP options: `memory_limit` ≥ 256M (photo decoding), `upload_max_filesize` ≥ 6M,
   `post_max_size` ≥ 8M, `max_execution_time` ≥ 30.
2. hPanel → Databases → MySQL: create a database and a user with a long random password.
3. hPanel → Advanced → SSH Access: enable it and add your public key.
4. On the server:
   ```bash
   php -v                       # must say 8.3+; if not, use the full path hPanel shows
   cd ~ && git clone <private repo SSH URL> nemchiw-api
   cd nemchiw-api
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && php artisan key:generate
   ```
   The repo is private: create a read-only **deploy key** on the server
   (`ssh-keygen -t ed25519 -f ~/.ssh/nemchiw_deploy`), add the `.pub` on GitHub under
   Settings → Deploy keys, and clone with the SSH URL.
5. Edit `.env`:
   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.lunara-tn.com
   LOG_CHANNEL=daily
   LOG_LEVEL=warning
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=…
   DB_USERNAME=…
   DB_PASSWORD=…
   OTP_CHANNEL=whatsapp
   WHATSAPP_TOKEN=…             # Meta System User token (never commit it)
   WHATSAPP_PHONE_NUMBER_ID=…   # from WhatsApp Manager → Phone numbers
   WHATSAPP_TEMPLATE=nemchiw_code
   WHATSAPP_LANG_EN=en          # the language code the English template was approved with
   GOOGLE_CLIENT_IDS=…          # the app's Google *web* client id (what Google puts in `aud`)
   APPLE_CLIENT_IDS=tn.nemchiw.app
   FACEBOOK_APP_ID=…
   FACEBOOK_APP_SECRET=…        # Facebook app → Settings → Basic (never commit it)
   DIDIT_API_KEY=…              # sandbox key first, then the live one
   DIDIT_WORKFLOW_ID=…          # the Biometric Authentication workflow
   DIDIT_WEBHOOK_SECRET=…
   ```
   In the Didit console, set the webhook URL to `https://api.lunara-tn.com/api/v1/webhooks/didit`
   (session status updates). Account deletion erases sessions at Didit through the queue, so the
   cron below must be running.
   Then `chmod 600 .env`.
6. Point the subdomain at `public/`. Find the subdomain's folder in hPanel
   (for example `~/domains/lunara-tn.com/public_html/api`), then:
   ```bash
   rm -rf ~/domains/lunara-tn.com/public_html/api
   ln -s ~/nemchiw-api/public ~/domains/lunara-tn.com/public_html/api
   ```
7. hPanel → Security → SSL: make sure the subdomain has a certificate, and turn on "Force HTTPS".
8. `php artisan migrate --force && php artisan spots:import && php artisan optimize`
9. hPanel → Advanced → Cron Jobs, every minute:
   `cd ~/nemchiw-api && php artisan schedule:run >> /dev/null 2>&1`
   This runs the queue worker, OTP pruning, token pruning and the 03:30 database backup
   (kept in `storage/app/private/backups`, newest 14). Hostinger's own backups are the
   off-server copy.
10. Check which client IP the rate limiters see. Set `EXPOSE_CLIENT_IP=true` in `.env`,
    run `php artisan optimize`, then from your phone on mobile data and from your laptop:
    ```bash
    curl -s https://api.lunara-tn.com/api/v1/_client-ip
    curl -s -H 'X-Forwarded-For: 203.0.113.9' https://api.lunara-tn.com/api/v1/_client-ip
    ```
    - Both must show **your** public IP (compare with any "what is my IP" site).
    - If the second shows `203.0.113.9`, the server trusts a forgeable header: turn off
      LiteSpeed's "Use Client IP in Header" (or ask Hostinger support) before going live.
    - If both show a Hostinger/CDN address instead of yours, the CDN is in front: configure
      `trustProxies` for the CDN's published ranges only. Never trust `*`.
    Then set `EXPOSE_CLIENT_IP=false` and `php artisan optimize` again.

## Every deploy

```bash
cd ~/nemchiw-api
git pull --ff-only
composer install --no-dev --optimize-autoloader
composer audit
php artisan migrate --force
php artisan optimize
```

## Rollback

`git checkout <previous commit>`, `composer install --no-dev -o`, `php artisan optimize`.
Roll a migration back only if it has not stored data you need.

## Checks after each deploy

```bash
curl -s https://api.lunara-tn.com/up                                  # 200
curl -s https://api.lunara-tn.com/api/v1/spots | head -c 200          # {"data":[…
curl -s https://api.lunara-tn.com/api/v1/nope                         # {"code":"not_found",…}
curl -sI https://api.lunara-tn.com/.env                               # 403 or 404, never 200
curl -sI https://api.lunara-tn.com/api/v1/spots | grep -i -E 'strict-transport|nosniff'
```

## Admin panel

The Filament panel lives at `/admin` (module `modules/Admin`). After deploying a release that
touches it:

```bash
php artisan migrate --force && php artisan filament:assets && php artisan optimize
```

- The panel needs a session that persists: `.env` must have `SESSION_DRIVER=file` (the
  API itself is stateless, so `array` used to be enough) and, in production,
  `SESSION_SECURE_COOKIE=true`. With `array`, every login fails with "page expired" (419).
- Create the first admin with `php artisan admin:create you@example.com --name="…"` (it asks
  for the password, 12 characters minimum). There is no sign-up page.
- At first login, scan the QR code with an authenticator app and store the recovery codes.
  Two-factor is required.
- Place photos are WebP: check GD has it with
  `php -r 'var_dump(gd_info()["WebP Support"] ?? false);'` (must print `true`).
- `public/media/places` must be writable by PHP. Its `.htaccess` serves the photos with a
  one-year immutable cache header.

## Slice 3.2: trips and organisers

```bash
php artisan migrate --force && php artisan filament:assets && php artisan optimize
```

- New tables: `organisers`, `organiser_applications`, `organiser_documents`, `trips`,
  `trip_requests`, `trip_request_members`, `trip_reports`; `users.medical_info` (encrypted).
- `ORGANISER_DOCUMENTS_DISK`: set it only if the documents should not go on the `local` disk.
  They live under `storage/app/private/organiser-documents`, never under `public/`. Decide on
  purpose whether backups include that folder (INPDP: identity documents).
- The scheduler now carries the trip lifecycle. Confirm hPanel → Advanced → Cron Jobs runs
  `php artisan schedule:run` every minute, and that `php artisan schedule:list` shows
  `trips:tick` (hourly: lapse, finish, counters) and `organisers:purge-documents` (hourly:
  deletes documents 30 days after a refusal or revocation).
- Admins see organiser documents at `/admin/documents/{id}` only; each view is written to
  `admin_actions`.

## Slice 3.3: chat and push

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force && php artisan optimize
```

- New tables: `conversations`, `messages`, `conversation_reads`, `push_tokens`; `trips.reminded_at`.
- Ably: create an app, Settings → Protocol Adapter Settings → turn on **Pusher protocol support**,
  accept the `public:`/`private:`/`presence:` namespaces, and turn **Require identification**
  off on them (error 40160 otherwise). Put the key in `.env` as above.
- Chat photos live in `storage/app/private/chat/<conversation id>/`, never under `public/`.
  Decide on purpose whether backups include that folder (INPDP).
- Pushes and realtime run after the response (`defer()`), so LiteSpeed must finish the request
  first: check that a chat message answers fast even with Ably's key wrong.
- The day-before reminder rides on the hourly `trips:tick`: the cron must be running.
- Check: `curl -s -H 'Accept: application/json' https://api.lunara-tn.com/api/v1/me/conversations` → 401.
