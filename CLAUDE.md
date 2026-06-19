# CLAUDE.md — AI Behavior & Codebase Guidelines

This file governs how AI assistants (Claude and others) must behave when working on this codebase.
Read and follow every section before writing or suggesting any code.

---

## Project Overview

**Laravel 13 HR Agent Management & Audit Logging System**

| Item | Value |
|---|---|
| Framework | Laravel 13 |
| Auth | Custom session-based (HrAgent model, NOT Laravel default User auth) |
| Frontend | Blade templates + Tailwind CSS v4 |
| Build Tool | Vite 8 |
| Database (Local) | SQLite |
| Database (Production) | MySQL (configured via `.env` on server) |
| Deployment Method | **Manual FileZilla FTP/SFTP — NO CI/CD** |

---

## 1. Build & Development Commands (Local Only)

These commands run **only on your local machine**. Never instruct production to run them.

```bash
# Start full development environment (server + queue + logs + Vite watcher)
composer dev

# Build frontend assets for production
npm run build

# Run database migrations (local only)
php artisan migrate

# Clear all Laravel caches
php artisan optimize:clear

# Full fresh setup (first time only)
composer setup
```

**CSS compilation** — Tailwind CSS is processed by Vite. After any CSS change:
```bash
npm run build   # for production build
# OR
npm run dev     # for local watch mode
```

The compiled CSS output goes to `public/css/`. **Only upload `public/css/` to production**, not source files.

---

## 2. Code Style & Architecture Guidelines

### 2.1 Strict MVC Architecture

This project follows **standard Laravel MVC**. AI must not deviate from this structure:

```
Controllers  →  app/Http/Controllers/
Models       →  app/Models/
Middleware   →  app/Http/Middleware/
Helpers      →  app/Helpers/
Views        →  resources/views/          (Blade templates only)
Components   →  resources/views/components/
Routes       →  routes/web.php            (web routes only, no API routes)
```

**Forbidden patterns:**
- Do not introduce Service classes, Repository pattern, or DTO layers unless explicitly requested
- Do not create new directories under `app/` without explicit user approval
- Do not use Laravel API routes (`routes/api.php`) — this is a web-only application
- Do not use Laravel Livewire, Inertia.js, or any JS framework

### 2.2 Authentication System

This project uses a **custom authentication system**, NOT Laravel's built-in auth:

- **Model:** `HrAgent` (not `User`) — stores agent profiles
- **Auth Check:** `AgentAuthMiddleware` via `auth.agent` middleware alias
- **Session Key:** Check `AgentAuthMiddleware.php` for the exact session key name
- **Route Protection:** Always use `->middleware('auth.agent')` on protected routes

**Forbidden:**
- Do not use `Auth::user()`, `auth()->user()`, or Laravel's `Auth` facade
- Do not add `auth` middleware (Laravel's default) — use `auth.agent` instead
- Do not use Laravel Breeze, Jetstream, Fortify, or Sanctum

### 2.3 Audit Logging (Required for all state-changing actions)

Every controller action that creates, updates, or deletes data **must** call `logSystem()`:

```php
use App\Helpers\LogHelper;

// Inside any controller method that changes state:
LogHelper::logSystem(
    userType: 'agent',
    userId: session('agent_id'),   // use the correct session key
    module: 'Profile',             // module name (PascalCase noun)
    action: 'UPDATE',              // action verb (SCREAMING_SNAKE_CASE)
    description: 'Agent updated personal information'
);
```

- **Module names:** `Auth`, `Dashboard`, `Profile`, `Log` (match existing usage)
- **Action names:** `LOGIN`, `LOGOUT`, `UPDATE`, `CREATE`, `DELETE`, `VIEW`
- Do not write raw `DB::insert()` or `AgentLog::create()` for logging — always use `LogHelper::logSystem()`

### 2.4 Blade Component Usage

Reuse existing components. Do not create raw HTML form inputs when a component exists:

| Need | Use Component |
|---|---|
| Text input | `<x-form.input>` |
| Number input | `<x-form.number>` |
| Textarea | `<x-form.textarea>` |
| Select/dropdown | `<x-form.select>` |
| Date picker | `<x-form.date>` |
| Month-year picker | `<x-form.month-year>` |
| Year picker | `<x-form.year>` |
| Form section wrapper | `<x-form.section>` |
| Button | `<x-btn>` |
| Card container | `<x-card>` |
| Modal | `<x-modal>` |
| Confirm dialog | `<x-confirm-modal>` |
| Data table | `<x-table>` |
| Pagination | `<x-pagination>` |
| Status indicator | `<x-status-badge>` |
| Log action badge | `<x-log-action-badge>` |

All views must extend `layouts.app`: `@extends('layouts.app')`

### 2.5 Naming Conventions

| Type | Convention | Example |
|---|---|---|
| Controller methods | camelCase | `showLogin`, `updateBank` |
| Route names | dot.notation | `profile.update`, `logs.index` |
| Blade view files | kebab-case | `show.blade.php` |
| Model properties | snake_case | `agent_code`, `pass_decode` |
| CSS classes | Tailwind utility only | Never write custom CSS except in `app.css` |
| DB table names | snake_case, plural | `ag_logs`, `hr_agents` |

### 2.6 Database

- **Migration naming:** `YYYY_MM_DD_HHMMSS_description.php` — use exact timestamp format
- **Table prefix:** Project tables use no global prefix, but audit log table is `ag_logs` (not `agent_logs`)
- Always use Eloquent ORM. Do not write raw SQL queries unless absolutely required for performance
- Foreign key columns follow convention: `{model}_id` (e.g., `user_id`, `agent_id`)

---

## 3. Deployment Constraints & Anti-Patterns

> **CRITICAL — READ BEFORE SUGGESTING ANY CHANGE**
>
> This project is deployed manually via **FileZilla FTP/SFTP**. There is no CI/CD pipeline.
> Every change you suggest must be safe to upload as individual files without breaking the live system.

### 3.1 FileZilla-Safe Rules

**Files safe to upload individually:**
- `app/Http/Controllers/*.php`
- `app/Models/*.php`
- `app/Helpers/*.php`
- `resources/views/**/*.blade.php`
- `routes/web.php`
- `public/css/` (compiled output only)
- `database/migrations/*.php` (upload first, then run `php artisan migrate` on server via SSH or admin panel)

**Files that require extreme caution:**
- `config/*.php` — changes here affect the entire application
- `app/Http/Middleware/*.php` — auth middleware change locks out all users if wrong
- `bootstrap/app.php` — application bootstrap, one error = total downtime

### 3.2 Absolute Prohibitions for Production

**NEVER suggest or do any of the following for production:**

1. **`composer require <package>`** — Adding packages requires uploading the entire `vendor/` folder (hundreds of MB). Instead, use only existing packages already in `composer.json`.

2. **`npm install` or `npm ci` on production** — Node.js and npm are not available on most shared hosting. Build assets locally (`npm run build`) and upload only the compiled `public/css/` directory.

3. **Restructuring directories** — Never move, rename, or delete existing directories (`app/`, `public/`, `resources/`, `routes/`, `storage/`, `bootstrap/`). FileZilla cannot detect deletions on the server.

4. **Changing `.env` content via code** — Never hardcode production credentials. Never suggest editing `.env` on the server through any automated means. Production `.env` is managed manually on the server and must never be overwritten by uploads.

5. **Uploading `vendor/`** — Never suggest uploading `vendor/` directory. It's over 100MB and must be managed via SSH + `composer install` directly on server if ever needed.

6. **Uploading `node_modules/`** — Never upload this directory. It has no place on the production server.

7. **Uploading `.env`** — The local `.env` must never be uploaded. The production server has its own `.env` with real database credentials, app keys, and production URLs.

8. **Uploading `storage/`** — The `storage/` directory contains runtime data (logs, sessions, cache). Overwriting it will wipe active sessions and logs.

### 3.3 Config Separation (Local vs Production)

| Config | Local (`.env`) | Production (`.env` on server) |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` |
| `DB_CONNECTION` | `sqlite` | `mysql` |
| `APP_URL` | `http://localhost/agent/public` | `https://yourdomain.com` |
| `LOG_LEVEL` | `debug` | `error` |

When writing code, never hardcode values that differ between environments. Always use `config('app.name')`, `env('APP_URL')`, or the appropriate config helper.

### 3.4 Migration Safety

Migrations on production must be run **manually** via SSH or a database admin panel (phpMyAdmin). Before writing a migration:

- **Additive only:** Add columns or tables. Never drop columns or tables in migrations used on an active production system without explicit user approval.
- **Nullable new columns:** Any new column added to an existing table must be `nullable()` or have a `default()` value, so existing rows are not broken.
- **Test locally first:** Always verify `php artisan migrate` runs clean locally before uploading.

---

## 4. Strict Don'ts (AI Behavior Rules)

These rules prevent the AI from inventing changes outside the project's scope:

1. **Do not install new Laravel packages** without explicit user request listing the exact package name
2. **Do not refactor working code** while fixing a bug — fix only the reported issue
3. **Do not add unnecessary abstraction** (interfaces, contracts, repositories, service providers) unless the user specifically asks
4. **Do not create API endpoints** — this application has no API consumers
5. **Do not introduce JavaScript frameworks** (Vue, React, Alpine.js) — Blade + Tailwind only
6. **Do not rename existing route names** — route names are referenced throughout blade files; renaming breaks the app
7. **Do not change the `ag_logs` table structure** without running a new migration — never alter the Model to map to a different column name without a matching migration
8. **Do not add new middleware** without registering it in `bootstrap/app.php`
9. **Do not generate seeders for production data** — seeders are for local development only
10. **Do not change `vite.config.js`** without also updating any affected blade `@vite()` directives

---

## 5. When Adding a New Feature (Checklist)

Before writing any new feature code, verify:

- [ ] Does a Blade component already exist for the UI element needed? Use it.
- [ ] Is the route protected with `->middleware('auth.agent')`?
- [ ] Does every state-changing action call `LogHelper::logSystem()`?
- [ ] Are new DB columns nullable or have defaults (migration safety)?
- [ ] Does the compiled CSS (`npm run build`) need to be re-uploaded?
- [ ] Is any new config value in `.env.example` and NOT hardcoded?
- [ ] Does the change require uploading only individual PHP/Blade files (FileZilla safe)?
