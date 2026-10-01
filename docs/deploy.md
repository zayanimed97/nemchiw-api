# Deploying to Hostinger (api.lunara-tn.com)

Shared hosting, SSH on port 65002. The app lives **outside** `public_html`; only
`public/` is exposed, through a symlink.

## One-time setup

1. hPanel → Advanced → PHP Configuration: PHP **8.3 or newer** for the site.
   Extensions: pdo_mysql, mbstring, openssl, gd, fileinfo, bcmath, intl.
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
   ```
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
