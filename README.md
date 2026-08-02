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

_Grows as each phase lands. Currently reflects Phase 3._

- **Controllers → Form Requests → Models** for standard CRUD.
- **Controllers never call OpenAI directly.** The intended flow (built out in
  Phase 6) is `Controller → Job → Service (app/Services/OpenAIService.php) →
  OpenAI API`, so AI calls never block an HTTP request.
- **Authorization** via Laravel Policies (`app/Policies/*Policy.php`),
  registered by Laravel's naming-convention auto-discovery — no manual
  registration needed.
- **Teams** (`app/Models/Team.php`) are the top-level ownership boundary.
  Membership and role (`owner` / `member`) live in a `team_members` pivot
  table (`app/Models/TeamMember.php`), not directly on `User` — a user can
  belong to many teams with a different role in each. A team can never end up
  without an owner: the last remaining owner can't be demoted or removed
  (enforced in `TeamMemberController`).
- **Projects belong to a Team; Specifications belong to a Project** — a
  strict two-level hierarchy (`Team → Project → Specification`), each
  scoping authorization to its parent. Only the team **owner** can
  create/edit/delete Projects (structural, deliberately restricted); any team
  **member** can create/edit/delete Specifications within them (day-to-day
  work, deliberately open). This asymmetry is intentional, not an oversight.
- **`specifications.current_version`** exists now (default `1`) but isn't
  incremented yet — Phase 4 adds the `specification_versions` table and the
  logic that bumps it on edit.
- Postgres runs only in Docker; PHP/artisan run natively on the host — see
  [Local Development](#local-development) below.

## Project Roadmap

- [x] **Phase 1** — Project init, Docker Postgres, Laravel Breeze auth (Blade)
- [x] **Phase 2** — Teams, team membership, roles, authorization
- [x] **Phase 3** — Projects & Specifications CRUD
- [ ] **Phase 4** — Specification version history
- [ ] **Phase 5** — Comments
- [ ] **Phase 6** — OpenAI integration, Jobs, database queue
- [ ] **Phase 7** — Deployment configuration (Render)

## Routes

_Grows as each phase lands. Currently reflects Phase 3. All routes below
require authentication (redirect to `/login` if signed out) unless noted
otherwise._

### General

| Method | URI | Name | What it shows |
|---|---|---|---|
| GET | `/` | — | No page of its own — redirects to `/dashboard` if signed in, `/login` otherwise. |
| GET | `/dashboard` | `dashboard` | Landing page after login; links to Teams. |

### Authentication *(guest-only unless noted)*

| Method | URI | Name | What it shows |
|---|---|---|---|
| GET | `/register` | `register` | Registration form (name, email, password). |
| POST | `/register` | — | Creates the account and logs the user in. |
| GET | `/login` | `login` | Login form. |
| POST | `/login` | — | Authenticates and starts the session. |
| POST | `/logout` | `logout` | *(authenticated)* Ends the session. |
| GET | `/forgot-password` | `password.request` | Form to request a password reset email. |
| POST | `/forgot-password` | `password.email` | Sends the password reset email. |
| GET | `/reset-password/{token}` | `password.reset` | Form to set a new password, from the emailed link. |
| POST | `/reset-password` | `password.store` | Saves the new password. |
| GET | `/verify-email` | `verification.notice` | *(authenticated)* "Please verify your email" notice. |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` | *(authenticated)* Verifies the address, from the emailed link. |
| POST | `/email/verification-notification` | `verification.send` | *(authenticated)* Resends the verification email. |
| GET | `/confirm-password` | `password.confirm` | *(authenticated)* Re-enter your password before a sensitive action. |
| POST | `/confirm-password` | — | *(authenticated)* Confirms the password. |
| PUT | `/password` | `password.update` | *(authenticated)* Changes the password, from the Profile page. |

### Profile

| Method | URI | Name | What it shows |
|---|---|---|---|
| GET | `/profile` | `profile.edit` | Update name/email, change password, delete account. |
| PATCH | `/profile` | `profile.update` | Saves name/email changes. |
| DELETE | `/profile` | `profile.destroy` | Deletes the account (requires password confirmation). |

### Teams

| Method | URI | Name | What it shows |
|---|---|---|---|
| GET | `/teams` | `teams.index` | Every team the user belongs to, with their role. |
| GET | `/teams/create` | `teams.create` | Form to create a new team. |
| POST | `/teams` | `teams.store` | Creates the team; creator becomes `owner`. |
| GET | `/teams/{team}` | `teams.show` | Team detail: member list with roles, add-member form and role/remove controls (owner only), leave-team button. Requires membership — 403 otherwise. |
| GET | `/teams/{team}/edit` | `teams.edit` | Rename or delete the team. Owner only. |
| PUT/PATCH | `/teams/{team}` | `teams.update` | Saves the new team name. Owner only. |
| DELETE | `/teams/{team}` | `teams.destroy` | Soft-deletes the team. Owner only. |
| POST | `/teams/{team}/members` | `teams.members.store` | Adds an existing user as a member, by email. Owner only. |
| PATCH | `/teams/{team}/members/{member}` | `teams.members.update` | Changes a member's role (`owner`/`member`). Owner only; blocked if it would leave the team without an owner. |
| DELETE | `/teams/{team}/members/{member}` | `teams.members.destroy` | Removes a member (owner only), or lets a member remove themselves (leave team). Blocked if it would remove the last owner. |

### Projects

| Method | URI | Name | What it shows |
|---|---|---|---|
| GET | `/teams/{team}/projects` | `teams.projects.index` | Every project belonging to the team. Requires team membership. |
| GET | `/teams/{team}/projects/create` | `teams.projects.create` | Form to create a project in this team. Owner only. |
| POST | `/teams/{team}/projects` | `teams.projects.store` | Creates the project. Owner only. |
| GET | `/projects/{project}` | `projects.show` | Project detail: description, status, and its specifications list. Requires team membership. |
| GET | `/projects/{project}/edit` | `projects.edit` | Rename, change status, or delete the project. Owner only. |
| PUT/PATCH | `/projects/{project}` | `projects.update` | Saves changes. Owner only. |
| DELETE | `/projects/{project}` | `projects.destroy` | Soft-deletes the project (and, on cascade, its specifications). Owner only. |

### Specifications

| Method | URI | Name | What it shows |
|---|---|---|---|
| GET | `/projects/{project}/specifications/create` | `projects.specifications.create` | Form to create a specification (title, description, goals, scope, functional/non-functional requirements). Any team member. |
| POST | `/projects/{project}/specifications` | `projects.specifications.store` | Creates the specification at version 1. Any team member. |
| GET | `/specifications/{specification}` | `specifications.show` | Full specification content and current version number. Requires team membership. |
| GET | `/specifications/{specification}/edit` | `specifications.edit` | Edit form, same fields as create. Any team member. |
| PUT/PATCH | `/specifications/{specification}` | `specifications.update` | Saves changes. Any team member. |
| DELETE | `/specifications/{specification}` | `specifications.destroy` | Soft-deletes the specification. Any team member. |

## Field Reference

### Project Form

| Field | Required? | What it's for |
|---|---|---|
| Name | Required | The project's identifying name within the team. |
| Description | Optional | Free-text summary of what the project is / why it exists. |
| Status | Required | Lifecycle stage — one of Planning, In Progress, Completed, On Hold. |

### Specification Form

| Field | Required? | What it's for |
|---|---|---|
| Title | Required | The specification's identifying name within the project. |
| Description | Optional | Short free-text summary of what this specification covers. |
| Goals | Optional | The outcome this specification is trying to achieve — *why* it exists. |
| Scope | Optional | The boundary of what's included and explicitly excluded — *what's in, what's out* (e.g. "covers email/password login; OAuth is a separate spec"). |
| Functional Requirements | Optional | The concrete features/behaviors the software must have. |
| Non-Functional Requirements | Optional | Quality attributes the software must meet (performance, security, availability, etc.), as opposed to specific features. |

All content fields beyond Title/Name are optional by design — specs and
projects often start as rough drafts and get filled in incrementally
(especially once AI-assisted drafting lands in Phase 6). Tighten any of these
to `required` in `Store*Request`/`Update*Request` if you'd rather enforce
completeness up front.

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

### IDE Helper

`barryvdh/laravel-ide-helper` is a dev dependency so editors (PhpStorm,
Intelephense) can resolve magically-proxied methods (`auth()->check()`,
facades, model properties) instead of flagging them as undefined. Its
generated files are gitignored and not part of the app — regenerate them
after pulling changes that add models, facades, or routes:

```bash
php artisan ide-helper:generate
php artisan ide-helper:meta
php artisan ide-helper:models --nowrite
```

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
