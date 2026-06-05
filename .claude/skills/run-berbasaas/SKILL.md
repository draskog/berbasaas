---
name: run-berbasaas
description: Run the Berbasaas Laravel harvest management application with Sail and Vite
---

# Berbasaas - Run Skill

Berbasaas is a Laravel 13 application for managing harvest data and payslips for blueberry farms. It uses Livewire 4, Volt single-file components, and Flux UI. The app is containerized with Laravel Sail and uses Vite for frontend bundling.

The application is driven via a Node.js REPL driver that uses Playwright for browser automation, allowing programmatic control for testing and interaction.

## Prerequisites

**System packages** (already installed in standard Laravel Sail images):
- Docker & Docker Compose
- PHP 8.5 (via Sail container)
- Node.js 24+ (via Sail container)

**Setup commands:**
```bash
# Start Sail services (MySQL, Redis, Memcached, Mailpit, Selenium)
vendor/bin/sail up -d

# Install dependencies
vendor/bin/sail composer install
vendor/bin/sail npm install

# Download Playwright browsers
vendor/bin/sail npx playwright install
```

## Build

```bash
# Run database migrations
vendor/bin/sail artisan migrate

# Build frontend assets (for production)
vendor/bin/sail npm run build

# Or start Vite dev server (watches for changes)
vendor/bin/sail npm run dev &
```

## Run (Agent Path)

The driver provides a REPL interface for programmatic app control:

```bash
# Start REPL driver
node .claude/skills/run-berbasaas/driver.mjs

# In the REPL, try these commands:
launch
visit http://berbasaas.test
screenshot home.png
click 'button:has-text("Prijava")'
fill 'input[name="email"]' test@example.com
wait 2000
eval 'document.title'
quit
```

**Available driver commands:**
- `launch` — Launch headless Chromium browser
- `visit <url>` — Navigate to URL (localhost aliases convert to berbasaas.test)
- `click <selector>` — Click element by CSS selector
- `fill <selector> <text>` — Fill input with text
- `screenshot [filename]` — Save screenshot to `.claude/skills/run-berbasaas/screenshots/`
- `wait <ms>` — Pause in milliseconds
- `eval <code>` — Execute JavaScript in page context
- `quit` — Close browser and exit

**Screenshots** are saved to `.claude/skills/run-berbasaas/screenshots/`.

## Run (Human Path - Development)

```bash
# Terminal 1: Start Sail services
vendor/bin/sail up -d

# Terminal 2: Start Vite dev server
vendor/bin/sail npm run dev

# Terminal 3: View logs (optional)
vendor/bin/sail logs -f laravel.test

# Then open your browser:
vendor/bin/sail open
# Or navigate manually to: http://berbasaas.test
```

The app will be accessible at `http://berbasaas.test` and hot-module reloading works during development.

## Direct Invocation (Testing Internal Code)

For testing PHP logic without the full browser:

```bash
# Run tests
vendor/bin/sail artisan test

# Run specific test
vendor/bin/sail artisan test --filter=testName

# Tinker REPL for PHP exploration
vendor/bin/sail artisan tinker
> \App\Models\Harvester::count();
```

## Test

```bash
# Run all tests with compact output
vendor/bin/sail artisan test --compact

# Run tests with coverage
vendor/bin/sail artisan test --coverage

# Watch tests during development
vendor/bin/sail artisan test --watch
```

The project uses **Pest v4** for testing. Test files are in `tests/Feature/` and `tests/Unit/`.

## Gotchas

### 1. **Sail Services Need Time to Boot**
   - On first `sail up -d`, MySQL and other services take 10-30 seconds to be ready
   - If you get "Connection refused" immediately, wait and retry
   - `sail artisan migrate` will fail until MySQL is healthy

### 2. **Vite Dev Server Must Be Running for Asset Loading**
   - The app loads Vite resources from `http://localhost:5173` in dev mode
   - If Vite stops, pages will fail to load CSS/JS
   - Keep `npm run dev` running in a separate terminal
   - Production mode (`npm run build`) bundles assets into `public/build/`

### 3. **Host Header Requirement**
   - The app is configured to use `http://berbasaas.test`
   - Your `/etc/hosts` or Docker must map `berbasaas.test` to `127.0.0.1`
   - Or access via `localhost` and let Sail's reverse proxy handle routing
   - The driver automatically converts `localhost` → `berbasaas.test` for page navigation

### 4. **Database Persistence**
   - Database changes persist in the `sail-mysql` Docker volume
   - Running `sail down` removes containers but keeps the volume
   - To reset the database: `sail artisan migrate:refresh`
   - To purge everything: `docker volume rm $(docker volume ls -q | grep sail)` then `sail up -d`

### 5. **Playwright Browser Installation is Large**
   - `playwright install` downloads ~200MB of browser binaries
   - This happens once per Sail environment
   - If installation hangs, ensure you have internet connectivity and ~500MB free disk

### 6. **Livewire Component State**
   - Livewire components maintain server-side state
   - Page reloads reset component state
   - Forms can be validated in real-time via `wire:` directives
   - The driver's `wait` command helps with Livewire reactivity timing

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `Connection refused` on `sail artisan migrate` | MySQL not ready | Wait 15 seconds, retry |
| `Vite manifest not found` | Vite dev server not running | `vendor/bin/sail npm run dev` in another terminal |
| `Cannot find berbasaas.test` | DNS/hosts file not configured | Use localhost or add `127.0.0.1 berbasaas.test` to `/etc/hosts` |
| Playwright `headless_shell not found` | Browsers not installed | Run `vendor/bin/sail npx playwright install` |
| `CORS` errors in browser console | Vite dev server port changed | Check `APP_URL` and Vite config match |
| Tests fail with "No such table" | Migrations not run for test DB | `sail artisan migrate --env=testing` |
| Port 80 or 5173 already in use | Another service using ports | `lsof -i :80` or `:5173`, then stop that process |

## Summary

The Berbasaas app is a full-stack Laravel Livewire application that can be run via:
- **Sail** (Docker Compose) for isolated, reproducible environments
- **Vite** dev server for hot reloading during development
- **Playwright driver** for headless testing and automation
- **Pest** for comprehensive test coverage

All paths above use `vendor/bin/sail` to ensure commands run in the correct container context with all dependencies available.
