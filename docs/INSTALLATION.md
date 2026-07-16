# Installation Guide

Two ways to get this running: the **guided web installer** (recommended —
it writes your `.env` and creates the first admin for you), or a **manual
setup** if you're scripting a deployment.

## Requirements

- PHP 8.3+
- Composer
- MySQL (or another database Laravel supports — the app defaults to and is tested against MySQL for real use; the automated test suite itself always runs against an in-memory SQLite database regardless of your configured connection)
- Node.js + npm (for building frontend assets)
- A way to serve the app locally: `php artisan serve`, [Laravel Herd](https://herd.laravel.com), Valet, or any standard PHP web server

## Option A: Guided web installer (recommended)

1. **Get the code and dependencies onto the server**, but stop short of configuring `.env` — the installer does that for you:
   ```bash
   git clone <this-repo> mlm
   cd mlm
   composer install
   npm install
   npm run build
   ```
2. **Serve the app** and visit it in a browser. With nothing configured yet, every request redirects to `/install` automatically (`EnsureAppIsInstalled` middleware) — you don't need to visit `/install` directly.
3. **Walk through the wizard** (`resources/views/livewire/pages/install/wizard.blade.php`), six steps:
   1. **Application & database** — app name, URL, and your database connection details. The wizard tests the connection before letting you continue.
   2. **Welcome / requirements check** — confirms the server meets requirements (writable `storage/`, writable `.env`, etc.).
   3. **Company details** — company name and support email (stored as the `company_name` / `support_email` system settings).
   4. **Compensation plan** — choose **unilevel**, **binary**, or **matrix** as the active plan, and set that plan's specific parameters right here (e.g. level percentages for unilevel/matrix, or the pairing percentage for binary, matrix width, etc.). This choice becomes the `active_plan_type` system setting.
      > This is a one-time decision in practice: switching it later changes how the *existing* commission/tree data gets interpreted going forward. Once installed, the admin Settings page locks this field for exactly that reason.
   5. **Admin account** — name, email, and password for the first (`super_admin`) user, who becomes the root of the genealogy tree.
   6. **Review & install** — confirms your choices, then runs the migrations, seeds the ranks (`RankSeeder`) and commission configuration for your chosen plan, creates the admin account, and marks the app installed (`installed_at` system setting).
4. You're redirected to `/admin/login` to sign in with the admin account you just created. (The wizard deliberately doesn't log you in automatically — see the comment in `wizard.blade.php`'s `install()` method for why: the session driver can change mid-install once the `sessions` table first exists.)

That's it — no separate `migrate`/`seed`/`key:generate` step needed; the wizard does all of it.

## Option B: Manual setup

If you're scripting this (CI, a deploy pipeline, etc.) and want to skip the
browser wizard:

```bash
composer install
cp .env.example .env
php artisan key:generate

# Edit .env: set DB_* to a real, reachable database, and APP_URL.

php artisan migrate

# Seed at minimum the ranks; commission configuration for whichever
# plan you're running should also be seeded or entered via the admin
# Settings page afterward.
php artisan db:seed --class=RankSeeder
php artisan db:seed --class=SystemSettingSeeder
php artisan db:seed --class=CommissionConfigurationSeeder

npm install
npm run build
```

Then create the first admin/root user and system settings yourself — the
cleanest reference for exactly what fields that needs is the wizard's own
`install()` method (`resources/views/livewire/pages/install/wizard.blade.php`).
At minimum you need: a `User` with `role = super_admin`, `depth = 0`, and
its `path` set to its own id (this is what makes it the tree's root); and
the `active_plan_type` / `installed_at` system settings set via
`SystemSetting::set()`.

There is also a `composer setup` script (`composer.json`) that automates
the `.env` copy, key generation, and `migrate --force` — useful as a
starting point for a scripted deploy, though it still expects you to
handle seeding and the first admin/tree-root yourself.

## Running it day to day

```bash
composer run dev
```

Starts four things together (via `concurrently`): the PHP dev server,
`queue:listen`, `pail` (log tailing), and the Vite dev server. If you're
using Laravel Herd or another always-on local environment, Herd handles
the PHP-serving part on its own — you don't need `php artisan serve`
running separately, but you may still want the queue/Vite pieces from
`composer run dev` (or Herd's own per-site queue/scheduler toggles, if
using Herd Pro).

## Scheduler

The daily personal volume commission feature
(`commission:personal-volume-daily`, registered in `routes/console.php`)
only fires if Laravel's scheduler is actually being invoked. In
production, that means a single system cron entry:

```
* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
```

Laravel Herd users: check the per-site **Scheduler** toggle instead of
setting up cron manually. Without one of these, the command exists and can
be run manually, but nothing will trigger it on its own.

## Running the test suite

```bash
composer test
# or
php artisan test
```

Runs against an isolated in-memory SQLite database (`phpunit.xml`),
migrated fresh for every run — it never touches your real configured
database, so it's always safe to run.

## Configuration reference

Everything editable after install lives in the `system_settings` table,
most conveniently reachable via the admin **Settings** page
(`/admin/settings`). The full current list, with defaults, is in
`database/seeders/SystemSettingSeeder.php`:

| Key | Default | Meaning |
|---|---|---|
| `active_plan_type` | `unilevel` | Which network plan is live. Locked after install. |
| `matrix_width` | `3` | Children per node under the matrix plan. |
| `binary_pair_percentage` | `10` | Binary pairing bonus %, if no per-plan `CommissionConfiguration` override exists. |
| `personal_volume_commission_enabled` | `false` | Master on/off switch for the daily personal volume payout. |
| `personal_volume_percentage` | `2` | Daily % of cumulative personal sales volume paid out. |
| `minimum_payout_threshold` | `50` | Minimum wallet withdrawal amount. |
| `withdrawal_fee_percentage` | `2` | Fee deducted from each withdrawal. |
| `company_name` / `support_email` | *(set during install)* | Display-only company info. |
