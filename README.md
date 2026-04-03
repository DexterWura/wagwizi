# PostAI — Social Media Management Platform

A multivendor social media management system built on **Laravel (PHP 8.2+)**. The platform enables users to connect social media accounts, compose posts, schedule publishing, view analytics, and manage billing — all from a single workspace.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Folder Structure](#folder-structure)
3. [Roles & Access](#roles--access)
4. [Getting Started](#getting-started)
5. [Environment Configuration](#environment-configuration)
6. [Database](#database)
7. [Project Rules](#project-rules)
8. [Coding Standards](#coding-standards)
9. [Service Layer Contract](#service-layer-contract)
10. [Frontend Conventions](#frontend-conventions)
11. [API Design](#api-design)
12. [Security](#security)
13. [Performance & Scalability](#performance--scalability)
14. [File Uploads](#file-uploads)
15. [Queue & Scheduled Tasks](#queue--scheduled-tasks)
16. [Rules for AI Agents](#rules-for-ai-agents)

---

## Architecture Overview

```
Browser ──► index.php (front controller)
                │
                ▼
        .htaccess rewrites
                │
                ▼
    app/logic/bootstrap.php  ──►  Laravel Application
                │
        ┌───────┴────────┐
        ▼                ▼
   routes/web.php    routes/api.php
        │                │
        ▼                ▼
   Controllers ──► Service Layer ──► Models ──► Database
        │
        ▼
   Blade Views (app/frontend/)
```

The application follows **strict layered architecture**:

- **Controllers** handle HTTP concerns only: request validation, calling services, returning responses.
- **Services** contain all business logic. They are the single source of truth for operations.
- **Models** represent database entities and their relationships. Scopes live on models.
- **Utils** hold stateless helper/utility functions (file handling, formatting, etc.).
- **Frontend** is Blade PHP templates organized into pages, partials, and sections.

---

## Folder Structure

```
project-root/
│
├── index.php                     # Front controller — ALL requests enter here
├── .htaccess                     # Apache rewrite rules, security headers
├── .gitignore                    # Git exclusions
├── README.md                     # This file
│
├── secrets/                      # Sensitive configuration (NEVER commit .env)
│   ├── .env                      # Environment variables
│   └── .gitignore                # Ensures .env is never committed
│
├── assets/                       # Public static assets (served directly by Apache)
│   ├── css/                      # Stylesheets
│   │   ├── style.css             # Application styles
│   │   └── landing.css           # Landing page styles
│   ├── js/                       # JavaScript
│   │   ├── app.js                # Application JS
│   │   ├── landing.js            # Landing page JS
│   │   └── ...
│   ├── images/                   # Platform images (logo, icons, etc.)
│   │   └── logo.svg
│   └── uploads/                  # User-uploaded files (gitignored)
│       ├── images/               # Uploaded images
│       ├── videos/               # Uploaded videos
│       ├── documents/            # Uploaded documents
│       └── avatars/              # User profile pictures
│
└── app/                          # Application code
    ├── frontend/                 # Blade PHP templates
    │   ├── pages/                # Full page templates (dashboard, login, etc.)
    │   ├── partials/             # Reusable template fragments (sidebar, topbar)
    │   └── sections/             # Page section components
    │
    └── logic/                    # Backend application code
        ├── composer.json         # PHP dependency manifest
        ├── bootstrap.php         # Laravel application bootstrap
        │
        ├── config/               # Laravel configuration files
        │   ├── app.php
        │   ├── database.php
        │   ├── cache.php
        │   ├── queue.php
        │   ├── session.php
        │   └── view.php
        │
        ├── routes/               # Route definitions
        │   ├── web.php           # Web routes (Blade views, session auth)
        │   └── api.php           # API routes (Sanctum token auth, /api/v1/*)
        │
        ├── controllers/          # HTTP Controllers
        │   ├── Controller.php    # Abstract base controller
        │   └── Auth/
        │       └── AuthController.php
        │
        ├── models/               # Eloquent models
        │   ├── User.php
        │   ├── SocialAccount.php
        │   ├── Post.php
        │   └── Subscription.php
        │
        ├── services/             # Business logic (service layer)
        │   ├── Auth/
        │   │   └── AuthService.php
        │   └── Post/
        │       └── PostPublishingService.php
        │
        ├── utils/                # Stateless utilities
        │   └── FileUploadUtil.php
        │
        ├── providers/            # Laravel service providers
        │   ├── AppServiceProvider.php
        │   └── RouteServiceProvider.php
        │
        ├── Http/                 # HTTP Kernel & middleware
        │   └── Kernel.php
        │
        ├── Console/              # Console Kernel & artisan commands
        │   └── Kernel.php
        │
        ├── database/
        │   └── migrations/       # Database migration files
        │
        ├── storage/              # Laravel storage (logs, cache, compiled views)
        │   └── .gitignore
        │
        ├── tests/                # PHPUnit test files
        │
        └── vendor/               # Composer dependencies (gitignored)
```

---

## Roles & Access

| Role | Description | Access |
|------|-------------|--------|
| `super_admin` | Platform owner. One per installation. | Full system access, user management, billing config, feature flags. |
| `support` | Customer support staff. | Read access to user data, can assist with account issues. Cannot modify billing or system settings. |
| `user` | Standard customer. | Manages their own social accounts, posts, scheduling, analytics, and subscription. |

---

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer 2.x
- MySQL 8.0+ or PostgreSQL 14+
- Redis (recommended for production cache/queue/session)
- Apache with `mod_rewrite` enabled

### Installation

```bash
# 1. Clone the repository
git clone <repo-url> postai
cd postai

# 2. Install PHP dependencies
cd app/logic
composer install
cd ../..

# 3. Configure environment
cp secrets/.env.example secrets/.env   # (or edit secrets/.env directly)
# Edit secrets/.env with your database credentials and API keys

# 4. Generate application key
cd app/logic
php artisan key:generate --env-file=../../secrets/.env
cd ../..

# 5. Run migrations
cd app/logic
php artisan migrate
cd ../..

# 6. Set file permissions (Linux/Mac)
chmod -R 775 app/logic/storage
chmod -R 775 assets/uploads

# 7. Point Apache document root to the project root directory
# Ensure mod_rewrite is enabled: a2enmod rewrite
```

---

## Environment Configuration

All environment variables live in `secrets/.env`. This file is **never committed to version control**.

Key sections:
- **Database** — `DB_*` variables for MySQL/PostgreSQL connection
- **Cache/Queue/Session** — defaults to `file`/`database` for local dev; use `redis` in production
- **Mail** — SMTP, SendGrid, or Mailjet configuration
- **Payment Gateways** — Stripe, Razorpay, Mollie, Paynow, Authorize.net, BTCPay
- **Social OAuth** — Client ID/Secret for each platform (Facebook, Twitter, LinkedIn, Instagram, TikTok, YouTube)
- **SMS/Messaging** — Twilio, Vonage, MessageBird
- **AI/LLM** — OpenAI, Anthropic API keys for the content assistant

---

## Database

### Connection

Default is MySQL. Configure via `DB_*` variables in `secrets/.env`. PostgreSQL is also supported.

### Migrations

All migration files live in `app/logic/database/migrations/`. Run them with:

```bash
cd app/logic && php artisan migrate
```

### Naming Convention

- Migration files: `YYYY_MM_DD_HHMMSS_description.php`
- Tables: plural snake_case (`users`, `social_accounts`, `posts`)
- Columns: snake_case (`scheduled_at`, `platform_user_id`)
- Foreign keys: `<singular_table>_id` (e.g., `user_id`)
- Indexes on columns used in WHERE, JOIN, and ORDER BY clauses

### NULL Policy

**0 is not a substitute for NULL.** If a value is absent or not applicable, it must be `NULL`, not `0`, `""`, or any other sentinel value. Nullable columns must be declared as `->nullable()` in migrations.

### Billing currency

- Table **`billing_currency_settings`** stores base currency, default display currency, Paynow checkout currency (single locked code), and exchange rates. It is populated by migration `2026_04_03_000001_create_billing_currency_settings_table` (which also imports from legacy `site_settings.payment_gateways` JSON when present).
- Paynow initiate requests may include a `currency` field (see `config/services.php` and `PAYNOW_SEND_CURRENCY_FIELD` in `secrets/.env`) so hosted checkout aligns with the configured checkout currency.

---

## Project Rules

These rules are **non-negotiable**. Every contributor and AI agent must follow them.

This project is written with four pillars in mind. Every decision — every file, every query, every line — must serve these:

| Pillar | Meaning |
|--------|---------|
| **Security** | Assume hostile input. Validate everything server-side. Never expose backend logic, secrets, or internal structure to the client. |
| **Scalability** | Code must work at 10 million users the same way it works at 10. No shortcuts that collapse under load. |
| **Efficiency** | No wasted queries, no redundant processing, no unnecessary network calls. Respect the user's time and the server's resources. |
| **Maintainability** | Clean, readable, well-structured code that a new developer (or AI agent) can understand and extend without archaeology. |

### 1. Designed for Scale

This platform is built to serve **millions of users**. Every line of code must assume high concurrency and large datasets.

- All database queries must use indexes. No full table scans.
- Use pagination for all list endpoints. Never return unbounded result sets.
- Use database transactions for multi-step operations that must be atomic.
- Use queue jobs for anything that takes longer than 200ms (API calls, email, publishing).
- Cache aggressively. User settings, plan details, and social account metadata should be cached.
- Avoid N+1 queries. Use eager loading (`with()`) for relationships.
- Use database-level constraints (unique indexes, foreign keys) — not just application validation.

### 2. Strict Layered Architecture

- **Controllers** ONLY handle HTTP: validate input, call a service, return a response. No business logic.
- **Services** contain ALL business logic. A controller never queries the database directly.
- **Models** define relationships, scopes, casts, and attribute logic. No HTTP awareness.
- **Utils** are stateless helpers. They don't depend on request context or database state.

If you're unsure where code goes: if it touches the database or contains a business rule, it's a **service**. If it validates a request or formats a response, it's a **controller**. If it's a reusable function with no side effects, it's a **util**.

### 3. OOP & Clean Code

- All code is object-oriented. No loose functions in global scope.
- Follow encapsulation: private/protected by default, public only when necessary.
- Favor composition over deep inheritance chains.
- Classes should have a single responsibility.
- Method names must clearly describe what they do.
- Only add comments when they provide real value. Never add comments that narrate the obvious.
- Never remove existing comments unless they are TODOs that have been fully resolved.

### 4. Code Reusability

- If a piece of logic is used in **multiple places**, extract it into a shared method or service.
- If it's used **only once**, keep it inline. Don't over-abstract.
- Shared utilities go in `app/logic/utils/`.
- Shared business logic goes in `app/logic/services/`.

### 5. Backend-Only Data Access

- **The frontend never directly accesses the database.** All data comes through controllers → services → models.
- Blade templates receive data from controllers. They never instantiate models or run queries.
- API endpoints follow the same discipline: controllers delegate to services.

### 6. No Backend Logic Exposed to Frontend

- **Never expose business logic, validation rules, pricing calculations, access control decisions, or internal system behavior in frontend code (Blade, JS, HTML).**
- The frontend is a **display and interaction layer only**. It renders data it receives and sends user actions back to the server. It does not decide what is allowed — the backend decides.
- No API keys, tokens, internal URLs, database column names, table names, or server paths may appear in frontend code.
- Error messages returned to the client must be user-friendly. Never expose stack traces, SQL errors, file paths, or class names to the browser.
- All authorization checks happen server-side. A hidden button or a disabled input is **not** security — the server must enforce every permission regardless of what the client sends.
- All form validation must be duplicated server-side. Client-side validation is a UX convenience, not a trust boundary.
- Route names and URL patterns should not reveal internal architecture (e.g., avoid `/api/v1/eloquent-model-name`).

### 7. CSS in CSS Files Only

- **All styling must be written in `.css` files** inside `assets/css/`. No inline `style` attributes on HTML elements. No `<style>` blocks in Blade templates.
- If a page or component needs unique styles, add them to the appropriate CSS file or create a new one in `assets/css/` and link it.
- This keeps styles cacheable, maintainable, and out of the HTML.

### 8. Respect the Folder Structure

- Controllers go in `app/logic/controllers/`.
- Models go in `app/logic/models/`.
- Services go in `app/logic/services/` with domain subfolders (e.g., `Auth/`, `Post/`, `Billing/`).
- Utils go in `app/logic/utils/`.
- Migrations go in `app/logic/database/migrations/`.
- Blade views go in `app/frontend/` under `pages/`, `partials/`, or `sections/`.
- Static assets go in `assets/` under `css/`, `js/`, or `images/`.
- User uploads go in `assets/uploads/` under their respective type folders.
- Environment secrets go in `secrets/`.
- **Never place files outside their designated directory.**

---

## Coding Standards

### PHP

- PHP 8.2+ with strict types where appropriate.
- PSR-4 autoloading (configured in `composer.json`).
- PSR-12 coding style.
- Type hints on all method parameters and return types.
- Use enums for fixed value sets (roles, statuses, platforms).
- Use Laravel's built-in validation (Form Requests for complex validation).
- Use Eloquent relationships — not raw joins — unless there's a measurable performance reason.

### Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| Classes | PascalCase | `PostPublishingService` |
| Methods | camelCase | `publishDuePosts()` |
| Variables | camelCase | `$socialAccount` |
| DB tables | plural snake_case | `social_accounts` |
| DB columns | snake_case | `scheduled_at` |
| Routes (web) | kebab-case | `/media-library` |
| Routes (API) | snake_case | `/api/v1/social_accounts` |
| Config keys | snake_case | `queue.default` |
| Env vars | SCREAMING_SNAKE | `DB_DATABASE` |

---

## Service Layer Contract

Every service class must follow these rules:

1. **Injectable** — Services are instantiated via Laravel's service container. Use constructor injection for dependencies.
2. **No HTTP awareness** — Services never access `Request`, `Session`, or return HTTP responses. They receive plain data and return plain data or throw exceptions.
3. **Transactional** — Multi-step database operations must be wrapped in `DB::transaction()`.
4. **Testable** — Services can be unit tested without an HTTP layer. Mock dependencies via constructor injection.
5. **Domain-organized** — Group services by domain: `Auth/`, `Post/`, `Billing/`, `SocialAccount/`, `Analytics/`, etc.

```php
// CORRECT: Service receives data, returns result
class PostPublishingService
{
    public function schedulePost(int $userId, array $data): Post
    {
        // validate business rules, create post, return model
    }
}

// WRONG: Service depends on Request object
class PostPublishingService
{
    public function schedulePost(Request $request): JsonResponse  // NO
    {
        // This belongs in a controller
    }
}
```

---

## Frontend Conventions

- **Blade PHP** is the template engine. All frontend files use `.blade.php` extension (transition from `.html` will happen as pages are converted).
- **Pages** (`app/frontend/pages/`) are full page templates.
- **Partials** (`app/frontend/partials/`) are reusable fragments included via `@include`.
- **Sections** (`app/frontend/sections/`) are larger page sections used with `@yield` and `@section`.
- Static assets are referenced with paths relative to the project root: `/assets/css/style.css`.
- Use Laravel's `asset()` helper in Blade: `{{ asset('assets/css/style.css') }}`.
- The frontend supports dark/light themes via `data-theme` attribute on `<html>`.
- All UI must be responsive and work on mobile, tablet, and desktop.

---

## API Design

- All API routes are prefixed with `/api/v1/`.
- Authentication via Laravel Sanctum (token-based for API, session-based for SPA).
- Responses use consistent JSON structure:

```json
{
    "success": true,
    "data": { ... },
    "message": "Operation completed"
}
```

```json
{
    "success": false,
    "errors": { "field": ["Error message"] },
    "message": "Validation failed"
}
```

- Use proper HTTP status codes: 200, 201, 204, 400, 401, 403, 404, 422, 429, 500.
- Rate limiting is enforced on all API endpoints (60 requests/minute per user by default).
- Paginated endpoints return `meta` with `current_page`, `last_page`, `per_page`, `total`.

---

## Security

Security is not a feature — it is a constraint on every feature.

### Secrets & Configuration

- `.env` is stored in `secrets/` and gitignored. Never commit secrets.
- `.htaccess` blocks direct access to `secrets/`, `app/logic/`, and all dotfiles.
- Never hardcode API keys, passwords, tokens, or internal URLs anywhere in the codebase. Use `env()`.

### Input & Output

- **All user input is validated server-side.** Never trust the client. Client-side validation is UX, not security.
- Sanitize and escape all output. Use Blade's `{{ }}` (escaped) by default. Only use `{!! !!}` when you explicitly control the content.
- Error responses to the client must be generic and user-friendly. Never expose stack traces, SQL errors, file paths, class names, or internal structure.

### Authentication & Authorization

- API authentication via Sanctum. Tokens are scoped and revocable.
- All authorization happens server-side. Every controller action must verify the user has permission — regardless of what the frontend shows or hides.
- Use `bcrypt` (Laravel's default) for password hashing. Never store plaintext passwords.
- Rate-limit authentication endpoints to prevent brute force.

### Data Protection

- Social OAuth tokens are stored encrypted in the database (`encrypted` cast).
- Use Laravel's CSRF protection for all web forms.
- Enable HTTPS in production. Set `SESSION_SECURE_COOKIE=true`.
- Database column names, table names, and internal IDs should never leak to frontend JS or HTML source.

### File Uploads

- File uploads are validated by MIME type and size. No PHP/executable files allowed in `assets/uploads/`.
- `.htaccess` denies execution of PHP inside the `assets/` directory.
- Uploaded files are named with UUIDs — never use user-supplied filenames.

### Frontend Boundary

- The browser is an **untrusted environment**. Any logic that determines access, pricing, limits, or permissions must run on the server.
- The frontend must not contain business rules, internal API routing logic, or anything that would help an attacker understand the backend.

---

## Performance & Scalability

### Database

- Index every column used in `WHERE`, `JOIN`, or `ORDER BY`.
- Use composite indexes for multi-column queries (e.g., `['status', 'scheduled_at']` on posts).
- Use `select()` to limit columns when you don't need full models.
- Use chunking (`chunk()`, `chunkById()`) for processing large datasets.
- Avoid `COUNT(*)` on large tables — use approximate counts or cache.

### Caching

- Use Redis for cache, session, and queue in production.
- Cache expensive queries with appropriate TTL.
- Use cache tags for easy invalidation of related data.
- Cache user plans/subscriptions — they don't change often.

### Queue

- All external API calls (social media publishing, email, SMS) must go through the queue.
- Use job batching for bulk operations (e.g., publishing to 5 platforms at once).
- Failed jobs are stored in `failed_jobs` table for debugging and retry.
- Monitor queue health in production.

### Horizontal Scaling

- Sessions and cache in Redis (shared across instances).
- File uploads should move to S3/cloud storage for multi-server deployments.
- Queue workers can scale independently.
- Database read replicas for analytics-heavy queries.

---

## File Uploads

- Upload directory: `assets/uploads/`
- Subdirectories by type: `images/`, `videos/`, `documents/`, `avatars/`
- Files are named with UUIDs to prevent collisions and path guessing.
- Maximum upload size is configurable via `UPLOAD_MAX_SIZE_MB` env variable.
- Validate MIME types server-side. Allowed types are enforced per upload context.
- In production, consider migrating to cloud storage (S3) via Laravel's filesystem abstraction.

---

## Queue & Scheduled Tasks

### Queue Workers

```bash
cd app/logic && php artisan queue:work --tries=3 --backoff=30
```

### Scheduler

Add to system crontab:

```
* * * * * cd /path/to/project/app/logic && php artisan schedule:run >> /dev/null 2>&1
```

### Current Scheduled Tasks

| Task | Frequency | Description |
|------|-----------|-------------|
| `posts:publish-due` | Every minute | Publishes posts whose `scheduled_at` has passed |

---

## Rules for AI Agents

If you are an AI agent (Cursor, Copilot, Codex, or similar) working on this codebase, you **must** follow these rules in addition to all project rules above:

1. **Read this README first.** Understand the folder structure, layered architecture, and coding standards before making any changes.

2. **Respect the folder structure.** Place files exactly where they belong. Controllers in `controllers/`, models in `models/`, services in `services/`, etc.

3. **Never put business logic in controllers.** Controllers validate input, call a service, and return a response. That's it.

4. **Never access the database from views.** Blade templates receive data from controllers. They never run queries.

5. **Use the service layer.** If you need to perform an operation involving business rules or database mutations, write a service or extend an existing one.

6. **Think at scale.** Before writing a query, ask: "Will this work with 10 million rows?" Add indexes. Use pagination. Avoid N+1.

7. **NULL means absent.** Don't use `0`, empty strings, or sentinel values where `NULL` is the correct semantic. If something doesn't exist, it's `NULL`.

8. **Don't over-comment.** Don't add comments that restate what the code does. Only comment non-obvious decisions, constraints, or trade-offs.

9. **Don't remove existing comments** unless they are TODOs that you have fully resolved.

10. **OOP always.** No loose functions. Every piece of logic belongs to a class.

11. **Type everything.** Use PHP type hints on all parameters and return types. Use Eloquent casts for model attributes.

12. **Test your assumptions.** If you're unsure about the project structure, read the codebase first. Don't guess.

13. **Don't modify vendor.** The `vendor/` directory is managed by Composer. Never edit files in it.

14. **Secrets stay in secrets.** Never hardcode API keys, passwords, or tokens. Use `env()` and the `secrets/.env` file.

15. **Queue heavy work.** External API calls, email sending, file processing — all must be dispatched as queue jobs in production-ready code.

16. **All CSS in CSS files.** Never use inline `style` attributes or `<style>` blocks in templates. All styling goes in `.css` files inside `assets/css/`.

17. **Never expose backend logic in frontend.** No business rules, pricing logic, permission checks, internal IDs, database column names, API keys, or server paths in Blade templates, JS, or HTML. The frontend displays and collects — the backend decides.

18. **Never expose internal errors to the client.** Error messages must be user-friendly. Stack traces, SQL errors, file paths, and class names must never reach the browser.

19. **Server-side is the trust boundary.** Every permission check, every validation, every limit enforcement must happen on the server. Frontend restrictions (hidden buttons, disabled fields, JS checks) are UX — not security.

20. **Four pillars on every change.** Before submitting any code, verify it satisfies: **Security** (no leaks, no trust of client), **Scalability** (works at 10M users), **Efficiency** (no wasted work), **Maintainability** (clean, readable, well-placed).

---

*Last updated: April 2026*
