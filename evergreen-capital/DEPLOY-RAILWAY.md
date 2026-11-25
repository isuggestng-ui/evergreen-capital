# Evergreen Capital — Deploy to Railway (GitHub → Railway)

This build is pre-configured for Railway. You do **not** edit any PHP or create
`config.php` by hand — the app configures itself from Railway's environment
variables and creates its own database tables + admin account on first boot.

Everything here has been syntax-checked and boot-tested end to end (homepage,
login, register, admin login, static assets, 404s, and a deliberately broken
database connection all behave correctly — no 500s).

---

## 1. Push to GitHub

From the project folder (the one containing `index.php`):

```bash
git init
git add .
git commit -m "Evergreen Capital — Railway-ready"
git branch -M main
git remote add origin https://github.com/<your-username>/<your-repo>.git
git push -u origin main
```

> `.gitignore` already excludes secrets (`config.php`), the local SQLite file,
> and user uploads, so nothing sensitive is committed.

---

## 2. Create the Railway project

1. Go to **railway.com → New Project → Deploy from GitHub repo**.
2. Pick the repo you just pushed. Railway detects PHP via `nixpacks.toml` and
   starts building immediately.

---

## 3. Add a database (do this before the first successful boot)

In the project canvas: **New → Database → Add MySQL** (recommended) *or* **Add
PostgreSQL**. Either works — the app auto-detects which one you attached.

Railway automatically injects the connection variables (`MYSQLHOST`,
`MYSQLPASSWORD`, … or `PGHOST`, `PGPASSWORD`, …). **You don't copy these
anywhere** — `config.railway.php` reads them at runtime.

> If your database service and app are separate services, make sure Railway's
> **variable references** link them (Railway does this automatically when both
> are in the same project). If the app can't see the DB variables, add a
> reference variable `DATABASE_URL = ${{ MySQL.MYSQL_URL }}` on the app service.

---

## 4. (Optional) Set your own variables

On the **app service → Variables**, you may add any of these. All are optional —
sensible defaults are used if you skip them:

| Variable          | Purpose                                   | Default            |
|-------------------|-------------------------------------------|--------------------|
| `ADMIN_EMAIL`     | First admin login email                   | `admin@evergreencapital.com` |
| `ADMIN_PASSWORD`  | First admin login password                | `Evergreen#2026`     |
| `APP_KEY`         | Long random string for app secrets        | auto-generated     |
| `APP_DEBUG`       | `1` to show errors while troubleshooting  | `0`                |
| `SITE_NAME`       | Site name (first install only)            | `Evergreen Capital`         |
| `DEFAULT_LANG`    | `en` or `ru` (first install only)         | `en`               |

---

## 5. Deploy & open

Railway builds and deploys automatically on every push. When the deploy is
green, click **Generate Domain** (Settings → Networking) and open the URL.

- Public site loads at `/` (Russian by default; RU/EN toggle in the header).
- **Admin panel:** `/admin/login.php`
  - Email: `admin@evergreencapital.com` (or your `ADMIN_EMAIL`)
  - Password: `Evergreen#2026` (or your `ADMIN_PASSWORD`)
  - **Change both immediately** after first login.

On the very first request the app silently creates all tables, seeds the
settings, and creates the admin — this takes a second or two. If you refresh
too fast and see a brief "Starting up…" page, just refresh again.

---

## 6. IMPORTANT: persist uploaded files with a Volume

Railway's container filesystem is **ephemeral** — it resets on every redeploy.
Your MySQL/Postgres data is safe (it lives in the database plugin), but files
uploaded through the app (avatars, KYC documents, deposit proofs) live in
`/uploads` and would be lost on redeploy.

To keep them, add a **Volume**:

1. App service → **Settings → Volumes → New Volume**.
2. Mount path: `/app/uploads`
3. Redeploy.

If you don't add a Volume, the app still runs fine — uploads just won't survive
a redeploy.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| "Starting up…" page won't go away | A database plugin isn't attached, or its variables aren't linked to the app service. Add MySQL/Postgres and confirm the variables appear on the app service. |
| Build fails | Check the build logs. This project has no Composer deps, so failures are almost always the DB plugin missing. |
| Want to see the real error | Set `APP_DEBUG=1` temporarily, redeploy, reload. **Set it back to `0`** afterward. |
| Can't reach admin | The URL is `/admin/login.php`, not `/login.php`. |
| Cyrillic looks wrong | Not applicable here — tables are created programmatically as utf8mb4/UTF-8. |

---

## What was added for Railway (nothing else was changed)

- `config.railway.php` — builds DB/app config from environment variables.
- `inc/install_seed.php` — creates tables + seeds settings/admin on first boot.
- `inc/bootstrap.php` — modified to load env config and run the first-boot
  installer, and to show a clean "Starting up" page instead of a raw 500 if the
  database is briefly unreachable.
- `nixpacks.toml`, `railway.json`, `Procfile` — tell Railway how to build and
  start PHP, bound to the injected `$PORT`.
- `router.php` — router for PHP's built-in server (serves static files, routes
  PHP, clean 404s).
- `.gitignore`, `.env.example` — repo hygiene + variable documentation.

Your original cPanel/MySQL and Neon/Postgres paths still work exactly as before —
if a hand-written `config.php` exists, it takes priority and none of the Railway
auto-config runs.
