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
| Deployment | None — dockerized for local demo only (faculty project, see Phase 7) |

## Architecture Overview

_Grows as each phase lands. Currently reflects Phase 7._

- **Controllers → Form Requests → Models** for standard CRUD.
- **Controllers never call OpenAI directly.** The flow is
  `Controller → Job → Service (app/Services/OpenAIService.php) → OpenAI API`,
  so AI calls never block an HTTP request. Controller creates an `ai_requests`
  row (`status=pending`) and dispatches a Job onto the database queue; the Job
  (run by `php artisan queue:work`) calls `OpenAIService`, then updates that
  same row with the response and `status=completed` — or `status=failed` +
  `error_message` if it exhausts its retries.
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
- **Specification version history** (`specification_versions` table) stores
  an immutable, insert-only JSON snapshot of a specification's content per
  version, plus who changed it. Creating a specification writes version 1;
  editing one only writes a new version (and bumps
  `specifications.current_version`) if the versioned content actually
  changed — a no-op save doesn't spam the history. This logic lives in
  `app/Services/SpecificationVersionService.php`, called explicitly from
  `SpecificationController` (not a model observer), so the same service is
  reusable from the Phase 6 AI job (`AiRequestController::apply()`) without
  depending on the `Auth` facade inside a model event.
- **Restoring an old version rewinds the `current_version` pointer** to that
  version's number — it does **not** create a new version row. Nothing is
  ever deleted either: every version stays in the table regardless of
  whether it's "current." One consequence: once you've rewound past a
  version number and then make a genuinely new edit, that edit can't reuse
  the version number you rewound past (it already belongs to different
  content, and version numbers are unique per specification) — so numbering
  jumps to one past the highest version ever created for that specification,
  not `current_version + 1`.
- **Editing to content that exactly matches an existing version prompts
  instead of silently duplicating it.** Saving a specification checks the
  new content against every prior version (not just the current one); if it
  matches, the edit isn't applied — the user is shown a modal ("This matches
  Version X — restore instead?") and chooses between restoring to that
  version or saving as a new version anyway (`force_new_version` bypasses
  the check on the resubmit).
- **Comments belong to a Specification** and are visible inline on its show
  page. Any team member can post one (same rule as Specifications); deleting
  one is **author-only** — unlike Projects/Specifications, being the team
  **owner** grants no extra power here, matching the brief's literal "delete
  own comments" wording. Comments use a hard delete (no soft-delete recovery
  story) and are entirely separate from the versioning system — they're not
  part of a specification's content snapshot.
- **Comments are threaded, to unlimited depth**, using `comments.parent_id`
  (a self-referencing FK). `Comment::buildTree()` fetches every comment for a
  specification in a single flat query, then assembles the whole reply tree
  in memory — no N+1 regardless of nesting depth. Top-level comments sort
  newest-first; replies within a thread sort chronologically. Rendering is a
  self-recursive Blade component (`resources/views/components/comment.blade.php`).
  Replying is allowed at any depth. Deleting a comment cascades to its entire
  reply subtree (a direct consequence of `parent_id`'s `cascadeOnDelete()`) —
  the confirmation modal warns how many replies will go with it.
- **"View replies (N)" only appears on top-level comments** and, once
  clicked, reveals the *entire* subtree at once — nested replies don't get
  their own individual collapse toggle. `N` counts all descendants
  (children, grandchildren, ...), not just direct replies.
- **"Improve Text" is per-field, not whole-specification.** Each of the 5
  long-text fields (Description, Goals, Scope, Functional/Non-Functional
  Requirements — deliberately excluding Title, a short label rather than
  prose) gets its own "Improve" button and its own `ai_requests` row
  (`field` column tracks which one). "Generate Next Steps" is the one
  whole-specification AI action, analyzing all fields together.
- **AI suggestions are reviewed, not auto-applied.** A completed
  `improve_text` request shows the suggestion with an "Apply" button that
  overwrites that field and runs through the normal
  `SpecificationVersionService` flow — so applying a suggestion creates a
  new version exactly like a manual edit would (the "matches an existing
  version" prompt is skipped here; deliberately, since applying an AI
  suggestion is already a considered, one-click action).
- **Jobs retry 3 times with backoff `[10, 30, 60]` seconds** before Laravel
  moves them to `failed_jobs` and calls each job's `failed()` method, which
  marks the corresponding `ai_requests` row `status=failed` with the
  exception message in `error_message`. Verified for real (not mocked): ran
  a job with no `OPENAI_API_KEY` configured through all 3 attempts and
  confirmed it landed in `failed_jobs` with the `ai_requests` row correctly
  updated.
- No auto-refresh/polling on pending AI requests yet — the user checks back
  by reloading the page. Deliberately deferred; see the note on optimizing
  full-page-reload interactions after all phases are complete.
- Postgres runs only in Docker; PHP/artisan run natively on the host — see
  [Local Development](#local-development) below.
- **No cloud hosting.** This is a faculty project, not a production service —
  Phase 7 dockerizes the *entire* stack (app + queue worker + Postgres) for
  local presentation purposes only, rather than preparing a Render/cloud
  deployment. See [Running the Full Stack in Docker](#running-the-full-stack-in-docker)
  below.
- **The Docker demo stack is opt-in via a Compose profile** (`--profile demo`),
  so it doesn't change the behavior of the `docker compose up` command
  already used throughout local development (Postgres only, unaffected).
- **`app` and `queue-worker` are two separate containers sharing one
  Postgres-backed queue** — the same architecture as a real deployment, just
  running on one machine. Verified for real: dispatched an AI job through the
  `app` container over HTTP, confirmed the separate `queue-worker` container
  picked it up, retried it, and marked it failed (no API key configured) —
  proving the containers actually communicate through the shared database,
  not just that each one boots.
- **Migrations run via a dedicated one-shot `migrate` service**, not baked
  into the app container's startup — `app` and `queue-worker` both wait for
  it to exit successfully (`depends_on: condition: service_completed_successfully`)
  before starting, so migrations only ever run once per `docker compose
  --profile demo up`, never racing across multiple containers.

## Project Roadmap

- [x] **Phase 1** — Project init, Docker Postgres, Laravel Breeze auth (Blade)
- [x] **Phase 2** — Teams, team membership, roles, authorization
- [x] **Phase 3** — Projects & Specifications CRUD
- [x] **Phase 4** — Specification version history
- [x] **Phase 5** — Comments
- [x] **Phase 6** — OpenAI integration, Jobs, database queue
- [x] **Phase 7** — Dockerized for local demo (no cloud hosting — faculty project)

## Routes

_Grows as each phase lands. Currently reflects Phase 6. All routes below
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
| GET | `/specifications/{specification}` | `specifications.show` | Full specification content, current version number, and its comments (with an add-comment form). Requires team membership. |
| GET | `/specifications/{specification}/edit` | `specifications.edit` | Edit form, same fields as create. Any team member. |
| PUT/PATCH | `/specifications/{specification}` | `specifications.update` | Saves changes. Any team member. If the submitted content exactly matches an existing version, nothing is saved yet — the response re-renders the edit page with a confirmation modal instead (see Specification Versions below). |
| DELETE | `/specifications/{specification}` | `specifications.destroy` | Soft-deletes the specification. Any team member. |

### Specification Versions

| Method | URI | Name | What it shows |
|---|---|---|---|
| GET | `/specifications/{specification}/versions` | `specifications.versions.index` | Every version, newest first, with who changed it and when; a compare form; a restore button per non-current version. Requires team membership. |
| GET | `/specifications/{specification}/versions/{version}` | `specifications.versions.show` | Full content snapshot of one version. Requires team membership. |
| GET | `/specifications/{specification}/versions/compare?from=&to=` | `specifications.versions.compare` | Side-by-side field-by-field comparison of two versions (changed fields highlighted). Requires team membership. |
| POST | `/specifications/{specification}/versions/{version}/restore` | `specifications.versions.restore` | Rewinds `current_version` to that version's number and copies its content back onto the specification. No new version row is created, and nothing is ever deleted — every version stays in the table whether or not it's current. Any team member. |

### Comments

| Method | URI | Name | What it shows |
|---|---|---|---|
| POST | `/specifications/{specification}/comments` | `comments.store` | Adds a comment to the specification, or a reply if `parent_id` is set (must reference an existing comment on the same specification). Any team member. |
| DELETE | `/comments/{comment}` | `comments.destroy` | Deletes a comment, and cascades to delete its entire reply subtree. **Author only** — even the team owner can't delete someone else's comment. |

### AI Assistance

| Method | URI | Name | What it shows |
|---|---|---|---|
| POST | `/specifications/{specification}/ai/improve-text` | `ai.improve-text` | Queues an `ImproveTextJob` for one field (`field` param, one of the 5 improvable fields). No-ops with a flash message if the field is currently empty. Any team member. |
| POST | `/specifications/{specification}/ai/generate-next-steps` | `ai.generate-next-steps` | Queues a `GenerateNextStepsJob` analyzing the whole specification. Any team member. |
| POST | `/ai-requests/{aiRequest}/apply` | `ai-requests.apply` | Applies a completed `improve_text` suggestion to its field, running it through the normal versioning flow. 404s if the request isn't a completed `improve_text`. Any team member. |

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
projects often start as rough drafts and get filled in incrementally,
especially now that AI-assisted drafting (Phase 6) can improve a field after
the fact. Tighten any of these to `required` in
`Store*Request`/`Update*Request` if you'd rather enforce completeness up
front.

### Comment Form

| Field | Required? | What it's for |
|---|---|---|
| Body | Required, max 2000 chars | The comment text. Plain text only — no rich formatting or Markdown. |
| Parent (`parent_id`) | Optional, hidden field | Set only by the "Reply" form, not the top-level "Add a comment" form. Makes the comment a reply, nested under the given comment. |

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
php artisan queue:listen  # background Jobs — required for AI features (Phase 6) to do anything
```

> **Windows note:** the Composer `dev` script (`composer run dev`) bundles in
> `laravel/pail` for log tailing, which requires the `pcntl` extension. `pcntl`
> is not available on Windows PHP builds, so that combined script will fail
> on Windows — run the commands above individually instead.

### AI / OpenAI Setup

AI features ("Improve" per-field, "Generate Next Steps") need a real OpenAI
API key to do anything beyond queuing a request that will fail. Get one at
https://platform.openai.com, then add it to your own `.env` — never commit
it, and don't paste it into a chat/AI assistant session either:

```
OPENAI_API_KEY=sk-...
```

Without a key, requests still queue and process normally, but the job fails
after 3 retries (backoff `10s, 30s, 60s`) and the `ai_requests` row ends up
`status=failed` with the actual API error message — the UI surfaces this on
the specification's edit/show page rather than failing silently. This is by
design: the whole pipeline (dispatch → queue → retry → failure-handling → UI)
works and is tested independent of whether a key is configured.

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

## Running the Full Stack in Docker

This project is a faculty assignment, not a hosted service — there is no
cloud deployment. Instead, the entire application (web app + queue worker +
Postgres) can run in Docker on your own machine for a self-contained
presentation, with no dependency on XAMPP or a local PHP install.

This is **opt-in** via a Compose profile, so it doesn't change anything about
the `docker compose up` command already used for local development (that
still only starts Postgres, exactly as before).

```bash
# Build the app image (only needed once, or after changing app code)
docker compose --profile demo build

# Start everything: Postgres, migrations, the web app, and the queue worker
docker compose --profile demo up -d

# App is now at:
http://127.0.0.1:8080

# Watch the queue worker process AI jobs in real time
docker compose --profile demo logs -f queue-worker

# Stop everything
docker compose --profile demo down
```

### What happens on `up`

1. **`postgres`** starts and waits until healthy (`pg_isready`).
2. **`migrate`** runs `php artisan migrate --force` once and exits — `app`
   and `queue-worker` both wait for it to exit successfully before starting,
   so migrations never run more than once or race across containers.
3. **`app`** serves the web app on port 8080 (mapped from container port 80).
4. **`queue-worker`** runs `php artisan queue:work` continuously, so AI
   requests dispatched through `app` actually get processed — the two are
   separate containers communicating only through the shared Postgres queue,
   the same architecture a real deployment would use.

### Notes

- Inside the Docker network, Postgres is reachable at hostname `postgres`,
  not `127.0.0.1` — the `app`, `migrate`, and `queue-worker` services
  override `DB_HOST`/`DB_PORT` accordingly; your `.env` file itself doesn't
  need to change (it's still read for everything else — `APP_KEY`,
  `OPENAI_API_KEY`, etc. via `env_file`).
- The Docker image (`Dockerfile`) is a 3-stage build: Composer installs PHP
  dependencies, then a Node stage builds frontend assets (this must happen
  **after** Composer — `tailwind.config.js` scans
  `vendor/laravel/framework/.../resources/views` for class names, so
  `vendor/` has to exist before `npm run build` runs), then a final
  `php:8.2-apache` runtime image with `pdo_pgsql`/`pgsql` installed.
- Port 8080 (not 8000) is used deliberately, so this can run
  simultaneously with `php artisan serve` (which defaults to 8000) without a
  port conflict.
- No AI calls will succeed until you add a real `OPENAI_API_KEY` to `.env`
  yourself — everything else in the pipeline (dispatch, queue, retry,
  failure-handling, UI) works regardless, exactly as covered in the AI /
  OpenAI Setup section above.
