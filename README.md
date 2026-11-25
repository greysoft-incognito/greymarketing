# GreyMarketing

Send marketing email and SMS through your own provider accounts (Africa's Talking, SendPulse, Termii, Twilio, Mailgun, SMTP).

## Layout

| Path | What |
|---|---|
| `api/` | Laravel API, built on [toneflix/laravel-op](https://github.com/toneflix/laravel-op) |
| `app/` | Quasar web app (alova.js, shadcn-vue), not started yet |

## API

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Tests run against PostgreSQL in CI and SQLite locally by default:

```bash
cd api
php artisan test
```
