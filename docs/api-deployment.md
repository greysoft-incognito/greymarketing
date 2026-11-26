# Deploying the API

The API (`api/`) runs on a Linux server with PHP-FPM behind Nginx, PostgreSQL, a queue worker and the Laravel scheduler. Code is shipped by the **Deploy API** GitHub Actions workflow over SSH.

## How a deploy works

The workflow (`.github/workflows/deploy-api.yml`):

1. Checks out the commit, installs Composer dependencies with `--no-dev --optimize-autoloader` on PHP 8.5.
2. Packs `api/` (without tests, `.env` files or logs) and copies it to `<DEPLOY_PATH>/releases/<timestamp>-<sha>` over SSH.
3. Runs `api/scripts/deploy.sh` on the server, which:
   - links `shared/.env` and `shared/storage` into the release,
   - runs `php artisan migrate --force`,
   - runs `php artisan optimize` (config, routes, events, views) and `storage:link`,
   - points `current` at the new release in one atomic switch,
   - optionally reloads PHP-FPM, then runs `php artisan queue:restart`,
   - keeps the newest releases (5 by default) and removes the rest.

If any step fails before the switch, the live release keeps serving and the uploaded release is removed.

It runs:

- **Manually** from Actions → Deploy API → Run workflow, with an optional branch, tag or commit.
- **Automatically** after the API Tests workflow passes on a push to `main`, once the `API_AUTO_DEPLOY` variable is `true`.

Deploys never overlap: a second run waits for the first.

### Server layout

```
/var/www/greymarketing-api          # DEPLOY_PATH
├── current -> releases/20261126101500-a1b2c3d
├── releases/
│   └── 20261126101500-a1b2c3d/
└── shared/
    ├── .env
    └── storage/
```

The web root is `<DEPLOY_PATH>/current/public`.

## GitHub settings

Add these under **Settings → Secrets and variables → Actions** (repository secrets, or secrets on a `production` environment, which the job uses and which can require approval before a deploy).

### Secrets

| Name | Required | Value |
|---|---|---|
| `DEPLOY_HOST` | yes | Server hostname or IP |
| `DEPLOY_USER` | yes | SSH user that owns `DEPLOY_PATH`, e.g. `deploy` |
| `DEPLOY_PATH` | yes | Absolute base path, e.g. `/var/www/greymarketing-api` |
| `DEPLOY_SSH_KEY` | yes | Private key (the whole file) whose public key is in the user's `~/.ssh/authorized_keys` |
| `DEPLOY_KNOWN_HOSTS` | recommended | Output of `ssh-keyscan -H <host>`. Without it the host key is trusted on first use for every run |
| `DEPLOY_PORT` | no | SSH port. Default `22` |

### Variables

| Name | Default | Value |
|---|---|---|
| `API_AUTO_DEPLOY` | unset | `true` to deploy after every green API Tests run on `main` |
| `DEPLOY_PHP` | `php` | PHP binary on the server, e.g. `/usr/bin/php8.5` |
| `DEPLOY_KEEP_RELEASES` | `5` | Releases kept on the server |
| `DEPLOY_RELOAD_COMMAND` | unset | Run after the switch to clear OPcache, e.g. `sudo systemctl reload php8.5-fpm` |

Create a dedicated key pair for deploys:

```bash
ssh-keygen -t ed25519 -C "greymarketing-deploy" -f greymarketing-deploy -N ""
# Append greymarketing-deploy.pub to /home/deploy/.ssh/authorized_keys on the server
# Paste greymarketing-deploy (the private key) into DEPLOY_SSH_KEY
ssh-keyscan -H your-server.example.com   # paste into DEPLOY_KNOWN_HOSTS
```

If you use `DEPLOY_RELOAD_COMMAND` with `sudo`, allow only that command without a password, e.g. in `/etc/sudoers.d/deploy`:

```
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.5-fpm
```

## Server requirements

- Ubuntu 24.04 or similar
- PHP 8.5 (CLI and FPM) with `bcmath, curl, dom, exif, fileinfo, gd, iconv, intl, mbstring, pcntl, pdo_pgsql, pgsql, zip`
- PostgreSQL 16
- Nginx
- Supervisor (queue worker)
- Cron (scheduler)
- Redis is optional; the default cache and queue use the database

Composer and Node are not needed on the server: dependencies are installed by the workflow.

## First-time setup

Run these once on the server as the deploy user.

### 1. Database

```bash
sudo -u postgres createuser --pwprompt greymarketing
sudo -u postgres createdb --owner=greymarketing greymarketing
```

### 2. Directories and environment file

```bash
mkdir -p /var/www/greymarketing-api/{releases,shared/storage}
nano /var/www/greymarketing-api/shared/.env
chmod 640 /var/www/greymarketing-api/shared/.env
```

Fill `.env` from the [environment reference](#environment) below. Generate `APP_KEY` anywhere PHP is available:

```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

The PHP-FPM user (usually `www-data`) must be able to read the release and write `shared/storage`. The simplest setup is to run PHP-FPM's pool as the deploy user, or add `www-data` to the deploy user's group and `chmod -R g+w shared/storage`.

### 3. First deploy

Run **Deploy API** manually from the Actions tab. It runs the migrations and creates `current`.

### 4. Seed the database (once)

```bash
cd /var/www/greymarketing-api/current
php artisan db:seed --force                                  # roles, admin user, plans
php artisan db:seed --class=ConfigurationSeeder --force      # runtime settings
```

Run these only on the first install. `ConfigurationSeeder` overwrites every setting's value, so running it again resets anything changed from the admin settings since. The deploy workflow never seeds.

The seeder creates `admin@example.com` with the password `password`. Sign in and change the email and password straight away.

Plan prices from `PlanSeeder` are placeholders. Set real prices in the admin console under Plans.

### 5. Nginx

```nginx
server {
    listen 80;
    server_name api.example.com;
    root /var/www/greymarketing-api/current/public;

    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

`$realpath_root` makes PHP-FPM resolve the `current` symlink on every request, so new releases are picked up without a restart. Add HTTPS with Certbot (`certbot --nginx -d api.example.com`). `client_max_body_size` must cover contact import uploads.

### 6. Queue worker

Campaign launches, message sending, contact imports and exports run on the queue. `/etc/supervisor/conf.d/greymarketing-worker.conf`:

```ini
[program:greymarketing-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php8.5 /var/www/greymarketing-api/current/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=deploy
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=1860
redirect_stderr=true
stdout_logfile=/var/www/greymarketing-api/shared/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

Each deploy runs `queue:restart`, so workers finish their current job and Supervisor starts them again on the new release.

### 7. Scheduler

The scheduler dispatches due campaigns every minute, expires subscriptions hourly and prunes expired tokens daily. Add to the deploy user's crontab (`crontab -e`):

```cron
* * * * * cd /var/www/greymarketing-api/current && /usr/bin/php8.5 artisan schedule:run >> /dev/null 2>&1
```

## Environment

Start from `api/.env.example`. Production values:

### App

| Key | Value |
|---|---|
| `APP_NAME` | `GreyMarketing` |
| `APP_ENV` | `production` (also forces HTTPS URLs) |
| `APP_KEY` | Generated key, see above. Never change it after launch: stored provider credentials are encrypted with it |
| `APP_DEBUG` | `false` |
| `APP_URL` | Public API URL, e.g. `https://api.example.com`. Webhook, tracking and unsubscribe links are built from it |
| `LOG_STACK` | `daily` |
| `LOG_LEVEL` | `warning` |
| `QUERY_LOGGER_ENABLED` | `false` |

### Database

| Key | Value |
|---|---|
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `5432` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | From step 1 |

### Queue, cache and sessions

| Key | Value |
|---|---|
| `QUEUE_CONNECTION` | `database` (or `redis`) |
| `DB_QUEUE_RETRY_AFTER` | `1860`. Must be longer than the longest job (campaign launch and imports allow 30 minutes), or a running job is retried by another worker |
| `REDIS_QUEUE_RETRY_AFTER` | `1860` when the queue uses Redis |
| `CACHE_STORE` | `database` (or `redis`) |
| `SESSION_DRIVER` | `database` |
| `MESSAGING_RATE_PER_SECOND` | Messages per second per connection that sets no rate of its own. Default `10` |

### Mail

The API sends its own account emails (verification codes, notices) through Laravel mail. Set `MAIL_MAILER` (`smtp`, `postmark`, `ses`, …), its credentials, `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`. Customers' campaign emails go through their own connected providers, not this mailer.

### Payments (Flutterwave)

| Key | Value |
|---|---|
| `FLUTTERWAVE_PUBLIC_KEY` | Flutterwave dashboard → Settings → API keys |
| `FLUTTERWAVE_SECRET_KEY` | Same page |
| `FLUTTERWAVE_WEBHOOK_HASH` | Any long random string; enter the same value as the webhook secret hash in Flutterwave |

These can also be set from the admin settings (`flutterwave_public_key`, `flutterwave_secret_key`, `flutterwave_webhook_hash`), which take priority over `.env`.

Set these runtime settings from the admin settings after seeding:

| Setting | Value |
|---|---|
| `app_currency` | Billing currency, e.g. `NGN` |
| `payment_verify_url` | Web app billing page, e.g. `https://app.example.com/billing`. Used when a checkout does not send its own redirect |
| `unsubscribe_page_url` | Web app page that confirms unsubscribes. It receives the signed action URL as `?action=`. Left empty, unsubscribe links return a JSON response |

### Web app access

The web app authenticates with Sanctum bearer tokens, and CORS allows any origin by default (`config/cors.php`). If the app is served from the same parent domain and you use cookie sessions, set `SANCTUM_STATEFUL_DOMAINS=app.example.com` and `SESSION_DOMAIN=.example.com`.

Build the web app with `API_URL` set to the public API URL.

## Webhooks

### Flutterwave

In the Flutterwave dashboard → Settings → Webhooks:

- **URL:** `https://api.example.com/api/webhooks/flutterwave`
- **Secret hash:** the value of `FLUTTERWAVE_WEBHOOK_HASH`
- Enable **charge.completed** events

Requests without a matching `verif-hash` header are rejected with 401.

### Messaging providers

Each customer's provider connection has its own webhook URLs, shown on the Providers page of the web app:

- **Delivery reports:** `https://api.example.com/api/webhooks/status/{connection}`
- **Inbound SMS (STOP replies):** `https://api.example.com/api/webhooks/inbound/{connection}`

Delivery report URLs are shown for Africa's Talking, Mailgun, Termii and Twilio connections; inbound URLs for Africa's Talking and Twilio. Customers paste them into their provider's dashboard (for Mailgun, together with the connection's webhook signing key, which also turns on Mailgun open and click events). Where a provider takes a callback URL per message, the API sends the delivery report URL with each message, so nothing needs to be set there.

These URLs come from `APP_URL`, so set it before customers connect providers.

## Routine operations

```bash
cd /var/www/greymarketing-api/current

php artisan migrate:status          # migrations applied
php artisan queue:failed            # failed jobs
php artisan queue:retry all         # retry them
php artisan schedule:list           # scheduled commands
tail -f storage/logs/laravel-*.log  # application log
```

### Rolling back

Point `current` at the previous release and restart the workers:

```bash
cd /var/www/greymarketing-api
ls -1t releases                                   # newest first
ln -sfn "$PWD/releases/<previous>" current.next && mv -Tf current.next current
php current/artisan queue:restart
```

Migrations are not rolled back automatically. If the bad release ran a migration the old code cannot work with, run `php artisan migrate:rollback --step=1` from the new release before switching back.

### Maintenance mode

```bash
php artisan down --retry=60
php artisan up
```
