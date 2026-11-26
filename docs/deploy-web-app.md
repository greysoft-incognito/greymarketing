# Deploy the web app on Cloudflare

The Quasar app is a static single-page app served by Cloudflare Workers static assets. `wrangler.jsonc` at the repository root holds the config: it uploads `app/dist/spa` and serves `index.html` for any path that is not a file, so history-mode routes like `/campaigns/12` load on refresh.

No Worker script runs; every request is served from the uploaded assets.

## Connect the repository (Workers Builds)

1. In the Cloudflare dashboard, open **Workers & Pages**, choose **Create**, then **Import a repository**, and pick `greysoft-incognito/greymarketing`.
2. Enter these build settings:

| Setting | Value |
|---|---|
| Project name | `greymarketing` (must match `name` in `wrangler.jsonc`) |
| Production branch | `main` |
| Root directory | `/` |
| Build command | `pnpm app:build` |
| Deploy command | `npx wrangler deploy` |
| Non-production branch deploy command | `npx wrangler versions upload` |

3. Under **Build variables and secrets**, add:

| Variable | Value |
|---|---|
| `API_URL` | Public URL of the API without the trailing `/api`, for example `https://api.example.com` |
| `NODE_VERSION` | `22` |

`API_URL` is read by `app/quasar.config.ts` at build time and compiled into the bundle, so changing it needs a new build (**Deployments** > **Retry build**, or push a commit).

4. Optional: under **Settings** > **Build** > **Build watch paths**, include `app/*`, `wrangler.jsonc`, `package.json`, `pnpm-lock.yaml` and `pnpm-workspace.yaml` so API-only commits do not rebuild the app.

Cloudflare installs dependencies with pnpm because `pnpm-lock.yaml` is at the root, and uses the Wrangler version pinned in the root `package.json`.

## Custom domain

Open the Worker, go to **Settings** > **Domains & Routes** > **Add** > **Custom domain**, and enter the app hostname (for example `app.example.com`). The zone must be on the same Cloudflare account.

Point the API at that origin wherever it builds links back to the app (email verification, password reset, unsubscribe pages).

## Deploy from a terminal

```bash
pnpm install
pnpm exec wrangler login
API_URL=https://api.example.com pnpm app:deploy
```

`pnpm exec wrangler deploy --dry-run` after `pnpm app:build` checks the config without uploading.
