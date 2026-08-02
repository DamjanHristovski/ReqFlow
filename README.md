# ReqFlow

ReqFlow is a collaborative requirements-documentation tool. Teams create
projects, write project specifications (goals, scope, functional and
non-functional requirements), track specification versions, discuss changes
in comments, and get AI-assisted help improving and extending their
specifications.

## Tech Stack

| Layer      | Choice                                            |
|------------|----------------------------------------------------|
| Backend    | Laravel 12 (PHP 8.2+)                              |
| Database   | PostgreSQL 16, running in Docker                   |
| Auth       | Laravel Breeze (Blade stack)                       |
| Frontend   | Blade templates, server-rendered, Tailwind CSS, Vite — no SPA framework |
| Queue      | Laravel database queue driver, background Jobs     |
| AI         | `openai-php/laravel`, called only from queued Jobs, never from controllers |
| VCS        | GitHub                                             |
| Deployment | Render                                             |

## Architecture Overview

_Grows as each phase lands. Currently reflects Phase 1._

- **Controllers → Form Requests → Models** for standard CRUD.
- **Controllers never call OpenAI directly.** The intended flow (built out in
  Phase 6) is `Controller → Job → Service (app/Services/OpenAIService.php) →
  OpenAI API`, so AI calls never block an HTTP request.
- **Authorization** via Laravel Policies/Gates (introduced in Phase 2 once
  Teams exist).
- Postgres runs only in Docker; PHP/artisan run natively on the host — see
  [Local Development](#local-development) below.

## Project Roadmap

- [x] **Phase 1** — Project init, Docker Postgres, Laravel Breeze auth (Blade)
- [ ] **Phase 2** — Teams, team membership, roles, authorization
- [ ] **Phase 3** — Projects & Specifications CRUD
- [ ] **Phase 4** — Specification version history
- [ ] **Phase 5** — Comments
- [ ] **Phase 6** — OpenAI integration, Jobs, database queue
- [ ] **Phase 7** — Deployment configuration (Render)

## Getting Started

### Prerequisites

- PHP 8.2+ with the `pdo_pgsql` and `pgsql` extensions enabled
- Composer
- Node.js + npm
- Docker Desktop (used for PostgreSQL only — the app itself runs on the host)

### Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Create .env — see Environment Configuration below
php artisan key:generate

# 3. Start PostgreSQL (only Postgres runs in Docker)
docker compose up -d

# 4. Run migrations
php artisan migrate

# 5. Install JS dependencies and build assets
npm install
npm run build   # or `npm run dev` for hot-reloading during development
```

### Environment Configuration

Create the `.env` file at the project root, and paste in the correct `.env`
fields — you can copy the format from `.env.example` or paste directly.

`docker-compose.yml` reads `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`
straight from this same `.env` file (Docker Compose auto-loads it), so
whatever you choose is what both the Postgres container and Laravel use —
there's no separate credential to maintain. If any of the three are left
blank, `docker compose up` refuses to start and tells you which one is
missing, rather than silently falling back to a value Laravel doesn't know
about. See the [Docker](#docker) section below for a caveat on changing these
after the container has already been created.

## Local Development

Run these in separate terminals (or your own process manager):

```bash
php artisan serve        # app at http://127.0.0.1:8000
npm run dev               # Vite, hot-reloads Blade/CSS/JS changes
php artisan queue:listen  # background Jobs (needed from Phase 6 onward)
```

> **Windows note:** the Composer `dev` script (`composer run dev`) bundles in
> `laravel/pail` for log tailing, which requires the `pcntl` extension. `pcntl`
> is not available on Windows PHP builds, so that combined script will fail
> on Windows — run the commands above individually instead.

### Tests

```bash
php artisan test
```

Tests run against an in-memory SQLite database (configured in `phpunit.xml`)
for speed and isolation — this is intentionally separate from the Postgres
database used for local development.

### Docker

`docker-compose.yml` runs PostgreSQL only:

```bash
docker compose up -d     # start Postgres
docker compose down      # stop Postgres
docker compose ps        # check status
```

> **Changing DB credentials after first run:** Postgres only applies
> `POSTGRES_DB`/`POSTGRES_USER`/`POSTGRES_PASSWORD` when it initializes a
> fresh data volume. If you change `DB_DATABASE`, `DB_USERNAME`, or
> `DB_PASSWORD` in `.env` after the container has already been created once,
> the running container will *not* pick up the new values automatically. Run
> `docker compose down -v` to wipe the volume and reinitialize (destroys
> local data), or update the credentials inside Postgres directly.

## Deployment

Deployment configuration for Render lands in Phase 7.
