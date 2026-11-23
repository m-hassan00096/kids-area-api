# Foundation — Laravel 12 Bootstrap: Feature Specification

> **Status:** Approved for technical planning  
> **Approved PHP version:** **PHP 8.2** (replaces the earlier 8.3 proposal)  
> **Delivery mode:** GitHub-first, feature branch from `develop`  
> **Feature location:** `specs/foundation/00-laravel-bootstrap/`  
> **Source references:** `docs/00-overview/project-overview.md`, `docs/01-architecture/backend-architecture.md`, and relevant `docs/02-standards/` files.

## 1. Objective

Establish the first executable backend foundation for Kids Area as a **Laravel 12, API-first, single-location modular-monolith project**, on **PHP 8.2**, with **MySQL 8 / InnoDB**, basic configuration safety, test tooling and a GitHub Actions verification pipeline. This feature does not implement a business module or business endpoint.

## 2. In scope

1. Bootstrap the Laravel **12.x** application source structure with a PHP **8.2** runtime and PHP-8.2-compatible, locked Composer dependencies.
2. Configure MySQL **8 / InnoDB** and `utf8mb4` as the intended runtime and integration-test database baseline; keep testing isolated from operational data.
3. Set Laravel's default/fallback locale to **Arabic (`ar`)** for system-owned text, without introducing a second active user language.
4. Preserve **UTC** as the authoritative timezone for persisted event instants; configure the business schedule/display timezone as an adjustable IANA setting with default **`Africa/Cairo`**.
5. Prepare consistent, single-application registration of future **`/api/v1`** routes, without inventing the Identity or other business-module endpoint inventory.
6. Maintain safe `.env.example` placeholders and Git ignore rules; never commit actual secrets or runtime-generated dependency directories.
7. Provide **PHPUnit** and **Laravel Pint** as development verification tools.
8. Configure **GitHub Actions** for pull requests targeting `develop`, using **PHP 8.2** and **MySQL 8**, with real dependency installation, database initialization, tests and Pint checks.
9. Keep the layout compatible with the approved `nwidart/laravel-modules`-based architecture. Exact package installation/configuration belongs to the reviewed technical plan; do **not** generate all eleven modules in this feature.

## 3. Explicitly out of scope

- Authentication, login/logout, user management, Sanctum transport, role/permission assignment or RBAC endpoints.
- Creating business entities, business tables, business seed data or any of the eleven functional module implementations.
- Implementing Select Registry, audit persistence, finance, timer, activity, café, POS or report behaviors.
- Frontend/starter-kit UI, SPA/mobile application, deployment to hosting or production configuration changes.
- Redis, application queues/workers, scheduler/cron runtime dependencies, WebSockets, or unrelated packages.
- Enabling Codex, Laravel Boost, `AGENTS.md`, Spec Kit CLI, `.specify/` or a project constitution.

## 4. Acceptance criteria

- **FND-001 — Versions:** The committed project declares Laravel 12 and PHP 8.2 support; the resolved dependency lock file is verified on a real PHP 8.2 runtime. No package is added before the technical plan/dependency list is approved.
- **FND-002 — Boot:** The Laravel application can bootstrap under PHP 8.2, with its required PHP extensions verified; executable checks provide evidence.
- **FND-003 — Database:** MySQL 8 test connection is independently configured; migration and database checks use an isolated disposable test database and `utf8mb4`/InnoDB.
- **FND-004 — Localization/time:** Arabic is the default and fallback for system-owned text; event timestamps follow UTC conventions; operating timezone is configurable and defaults to `Africa/Cairo`.
- **FND-005 — API organization:** The shared route-registration mechanism supports `/api/v1` once, without duplicated prefixes or unapproved business endpoints.
- **FND-006 — Configuration hygiene:** `.env.example` has no live credentials, `.env` is not committed, and sensitive/runtime-generated files are excluded.
- **FND-007 — Local checks:** The baseline PHPUnit suite and Pint check are executable, and their real results are reported.
- **FND-008 — CI:** A GitHub Actions workflow targets PRs to `develop` with PHP 8.2 and MySQL 8, installs locked dependencies and runs the baseline tests and Pint.
- **FND-009 — No scope expansion:** No business modules, auth endpoints, finance records, user credentials or production operations are introduced.
- **FND-010 — Review gate:** The feature is marked Ready for Review only after mandatory checks actually pass; the PR may merge into `develop` only after explicit user approval.

## 5. Verification and handoff

The technical plan must name the exact dependency versions/constraints, installation method, structure/configuration changes, CI jobs, baseline tests and commands. The reviewed `tasks.md` will track implementation and blockers. A committed test or migration is **not proof of execution**; report each check as `Passed`, `Failed` or `Not Run`.

## 6. Approved boundary / deferred decisions

The current version choice is **PHP 8.2**. Future PHP upgrades are separate, explicitly approved maintenance changes. Detailed module routing, authenticated API contracts and all business behavior remain in their owning module/feature specifications. Laravel Boost and Codex are not part of the current development method.
