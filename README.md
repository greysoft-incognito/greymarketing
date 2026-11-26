# GreyMarketing

Send marketing email and SMS through your own provider accounts (Africa's Talking, SendPulse, Termii, Twilio, Mailgun, SMTP).

## Layout

| Path | What |
|---|---|
| `api/` | Laravel API, built on [toneflix/laravel-op](https://github.com/toneflix/laravel-op) |
| `app/` | Quasar web app: Vue 3, TypeScript, alova.js, Tailwind CSS and shadcn-vue |

## Setup

JavaScript dependencies use a pnpm workspace (`api`, `app`):

```bash
corepack enable
pnpm install
```

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

## App

```bash
cp app/.env.example app/.env   # API_URL points at the API, default http://localhost:8000
pnpm app:dev                   # http://localhost:9000
pnpm app:typecheck
pnpm app:build                 # outputs app/dist/spa
```

The app deploys to Cloudflare Workers static assets with `wrangler.jsonc`. Dashboard settings are in [docs/deploy-web-app.md](docs/deploy-web-app.md).

UI components live in `app/src/components/ui` in the shadcn-vue layout (`components.json` is set up), themed with the Greysoft colours in `app/src/css/app.css`.
