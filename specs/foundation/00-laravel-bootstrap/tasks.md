# Foundation — Laravel 12 Bootstrap: Implementation Tasks

> **Status: DRAFT — USER REVIEW REQUIRED. NO IMPLEMENTATION AUTHORIZED YET.**  
> **Approved high-level plan:** Laravel 12, **PHP 8.2**, MySQL 8, nwidart v12, versioned API bootstrap, Arabic/UTC, PHPUnit, Pint and GitHub Actions.  
> **Detailed technical plan:** `specs/foundation/00-laravel-bootstrap/plan.md`  
> **Feature spec:** `specs/foundation/00-laravel-bootstrap/spec.md` (FND-001–FND-010)  
> **Branch:** `feature/foundation-laravel12-bootstrap` → eventual PR to `develop`; **manual merge approval only**.  
> **Current evidence:** Source documents reviewed; Composer/CI/application commands **Not Run**. No application source, migrations or dependencies have been installed.

## 0. Execution gates and working rules

**Gate A — Before implementation:** User reviews these tasks and the detailed plan. Review explicitly includes the required `wikimedia/composer-merge-plugin` dependency and the **narrow Composer `allow-plugins` entry** needed for nwidart v12 module autoloading. No package installation or plugin execution before approval.

**Gate B — Before any Composer lock or database operation:** Prove a safe, isolated **PHP 8.2.x / MySQL 8** execution environment is available. Confirm real runtime extensions and that the test database is disposable, not a production clone. If none exists, stop and document the blocker; do not fake lock/test results.

**Gate C — Before a PR is Ready for Review:** Confirm the required checks actually ran and passed; changes limited to the approved Foundation scope. Incomplete checks keep the feature **Not Ready** and any PR **Draft**.

**Task status convention:** `[ ]` = Not Started; `[x]` = Done only with cited actual implementation + verification evidence. If blocked, keep the checkbox clear and add a dated `Blocked: ...` note describing why. Approval of this checklist will authorize routine feature work, **not** destructive/production actions.

## 1. Preflight and dependency checks

- [ ] **FND-T01 — Confirm source/branch baseline.**  
  **Depends on:** Gate A.  
  **Work:** Read current `develop` and feature branch head, approved `spec.md`, `plan.md`, Architecture and relevant Code/API/Database/Localization/AI Workflow Standards. Ensure repository is docs-only and no newer commits conflict.  
  **Proof:** Record source commit SHA and scoped target files.  
  **Maps to:** FND-001, FND-009, FND-010.

- [ ] **FND-T02 — Verify execution environment and PHP extensions.**  
  **Depends on:** FND-T01.  
  **Work:** Identify **real PHP 8.2.x** runtime, Composer version and isolated execution environment; verify `pdo_mysql`, `dom`, `simplexml`, `mbstring`, `xml` and the full Laravel-required extension set. Confirm MySQL 8 service can be safely provisioned.  
  **Proof:** Capture actual `php -v`, `php -m`, `composer --version` and MySQL version outputs (sanitized).  
  **Blocker:** A PHP 8.3/8.4-only machine or an unverified PHP runtime is **not** valid evidence for compatibility.  
  **Maps to:** FND-001, FND-002, FND-003.

- [ ] **FND-T03 — Review exact package graph and Composer plugin security.**  
  **Depends on:** FND-T02; explicit Composer plugin review under Gate A.  
  **Work:** Inspect official Laravel 12 skeleton dependency manifest; plan direct requirements `laravel/framework:^12.0`, `nwidart/laravel-modules:^12.0`, dev `phpunit/phpunit:^11.5.50` and `laravel/pint:^1.24`. Confirm PHP 8.2 compatibility of all actual transitive dependencies and review `wikimedia/composer-merge-plugin:^2.1`; allow only this plugin if approved. Do not install Sanctum, Spatie Permission, Query Builder, Laravel Boost or additional UI/dev packages without a separate reason/approval.  
  **Proof:** Approved dependency manifest and compatibility review; Composer plugin allowlist scope.  
  **Maps to:** FND-001, FND-009.

## 2. Laravel skeleton, packages and source structure

- [ ] **FND-T04 — Generate official Laravel 12 skeleton in isolation.**  
  **Depends on:** FND-T02–T03.  
  **Work:** Use the official Laravel 12 skeleton in an isolated disposable workspace (no starter kit). Inspect generated files/scripts; preserve the existing repository's `README.md`, `docs/` and `specs/` when bringing framework sources onto the feature branch. Do not overwrite Git history or start a new repository.  
  **Proof:** Reviewed file diff with expected Laravel 12 structure; no framework 13 or frontend starter.  
  **Maps to:** FND-001, FND-002, FND-009.

- [ ] **FND-T05 — Review/remove unapproved scaffold artifacts and migrations.**  
  **Depends on:** FND-T04.  
  **Work:** Inventory default Laravel sample users, sessions, cache and jobs migrations, models, factories, routes, Vite/frontend scripts and queue settings. Before **any migration is executed**, remove or isolate unapplied scaffold-only migrations/outputs not part of Foundation; defer all Identity schema design. Retain only necessary framework shell. No existing/applied migration is modified.  
  **Proof:** Migration inventory and rationale included in PR; no unapproved business tables created.  
  **Maps to:** FND-003, FND-009.

- [ ] **FND-T06 — Add nwidart v12 and verify modular boot.**  
  **Depends on:** FND-T03–T05; Gate A plugin approval.  
  **Work:** Add approved `nwidart/laravel-modules:^12.0`, its reviewed Composer merge-plugin configuration and minimal module package configuration. Follow actual installed v12 package paths, not old v11/v13 assumptions. Do **not** generate the eleven modules.  
  **Proof:** Composer package list/installed versions, successful application boot and appropriate `module:list` output with no fabricated business modules.  
  **Maps to:** FND-001, FND-002, FND-009.

- [ ] **FND-T07 — Produce genuine PHP 8.2 Composer lock.**  
  **Depends on:** FND-T03–T06.  
  **Work:** Resolve dependencies on **actual PHP 8.2**, review Composer package/plugin output and commit Composer-generated `composer.lock`. For CI-only initialization, use a separately reviewed temporary disposable runner if necessary; ordinary CI must subsequently use `composer install` rather than silently resolving fresh versions. Never fabricate or copy an unverifiable lock file.  
  **Proof:** `composer validate --strict`, clean `composer install --no-interaction --prefer-dist --no-progress` and `composer check-platform-reqs` **executed on PHP 8.2**; record installed versions.  
  **Maps to:** FND-001, FND-002.

## 3. Shared runtime and API configuration

- [ ] **FND-T08 — Configure secure runtime defaults and environment examples.**  
  **Depends on:** FND-T04–T05.  
  **Work:** Set safe `.env.example`, ignore real `.env` and runtime artifacts, review file/array cache and file sessions as applicable, avoid Redis/background queue/cron dependencies, avoid public exposure of protected files. No real secrets or production edits.  
  **Proof:** Git status/ignore checks, config inspection, PHPUnit config smoke test.  
  **Maps to:** FND-002, FND-006, FND-009.

- [ ] **FND-T09 — Configure MySQL 8, utf8mb4 and isolated testing.**  
  **Depends on:** FND-T05, Gate B.  
  **Work:** Use MySQL `mysql` connection with `utf8mb4` and InnoDB; configure an explicit disposable test database and test-only credentials. Test MySQL connectivity and the reviewed migration set **only** in isolated CI/dev. Do not use production data or run destructive recovery commands.  
  **Proof:** Actual MySQL 8 version, charset/engine evidence and successful migration or empty-schema verification on disposable DB.  
  **Maps to:** FND-003.

- [ ] **FND-T10 — Apply Arabic-only locale and shared translation baseline.**  
  **Depends on:** FND-T04, FND-T08.  
  **Work:** Set `APP_LOCALE=ar` and `APP_FALLBACK_LOCALE=ar`; add minimal shared Arabic translation resources only as needed. Do not add locale negotiation middleware, language switch endpoints or business translations.  
  **Proof:** PHPUnit tests of default, fallback and minimal Arabic system translation behavior.  
  **Maps to:** FND-004.

- [ ] **FND-T11 — Separate UTC events from configurable business timezone.**  
  **Depends on:** FND-T04, FND-T08.  
  **Work:** Keep Laravel event-time source UTC; configure an independent IANA business timezone defaulting to `Africa/Cairo`, obtained via config rather than hardcoded in business code. Do not change timestamps when reading.  
  **Proof:** PHPUnit assertions for default, override and unchanged UTC event timezone.  
  **Maps to:** FND-004.

- [ ] **FND-T12 — Register the API v1 prefix exactly once.**  
  **Depends on:** FND-T04, FND-T06.  
  **Work:** Prepare `routes/api.php` and Laravel 12 `bootstrap/app.php` routing with a single prefix `api/v1`. **Do not run `install:api`** if it would install Sanctum; defer auth. Add no permanent public demo or business endpoint.  
  **Proof:** Actual framework route-registration/config test proving no duplicated prefix; explain test-only mechanism.  
  **Maps to:** FND-005, FND-009.

## 4. Tests, formatter and continuous integration

- [ ] **FND-T13 — Configure baseline PHPUnit and bootstrap tests.**  
  **Depends on:** FND-T07–T08.  
  **Work:** Review `phpunit.xml` and test bootstrap, remove irrelevant template examples, write focused tests for framework boot, no unapproved module behaviors, and safe default config.  
  **Proof:** `php artisan test` (or `vendor/bin/phpunit`) actual pass results under PHP 8.2.  
  **Maps to:** FND-002, FND-007, FND-009.

- [ ] **FND-T14 — Add locale/timezone/API prefix regression tests.**  
  **Depends on:** FND-T10–T13.  
  **Work:** Cover Arabic fallback; independently configurable `Africa/Cairo` business time; UTC timestamps; single `/api/v1` registration and no phantom production routes.  
  **Proof:** Test names and executed results; verify route-test fixtures are not exposed publicly.  
  **Maps to:** FND-004, FND-005, FND-007.

- [ ] **FND-T15 — Add MySQL 8 database integration smoke tests.**  
  **Depends on:** FND-T09, FND-T13.  
  **Work:** Use real disposable MySQL 8 / InnoDB to assert connection, charset/collation and safe reviewed migration behavior. Do not imply financial concurrency guarantees from simple smoke tests; those belong to later features.  
  **Proof:** Actual MySQL service version, migration run and PHPUnit results.  
  **Maps to:** FND-003, FND-007.

- [ ] **FND-T16 — Create GitHub Actions CI for develop PRs.**  
  **Depends on:** FND-T07, FND-T13–T15.  
  **Work:** Add `.github/workflows/ci.yml` triggered on `pull_request` to `develop`; setup PHP **8.2**, required extensions, ephemeral **MySQL 8** service, safe CI credentials, dependency install from lock, isolated migrations, `php artisan test` and `vendor/bin/pint --test`. Cache only safe dependencies; do not put secrets in repo.  
  **Proof:** Workflow YAML inspection plus **actual GitHub Actions run ID**, logs and conclusions.  
  **Maps to:** FND-007, FND-008.

- [ ] **FND-T17 — Run all mandatory checks and fix failures in scope.**  
  **Depends on:** FND-T13–T16.  
  **Work:** Run `php -v`, `composer validate --strict`, `composer check-platform-reqs`, `php artisan test`, `vendor/bin/pint --test` and required DB/CI checks. Correct code or setup errors within the approved scope without weakening valid tests.  
  **Proof:** Record each command as `Passed` / `Failed` / `Not Run` with environment and result; do not mark complete with any required failure or unrun check.  
  **Maps to:** FND-001–FND-008, FND-010.

## 5. Final safety check and human handoff

- [ ] **FND-T18 — Perform scope, security and dependency audit.**  
  **Depends on:** FND-T17.  
  **Work:** Inspect diff for forbidden Redis/jobs/cron, credentials, web starters, unapproved auth/permissions/migrations, generated module sprawl and unrelated refactors. Review exact Composer package graph and PHP 8.2 support risk.  
  **Proof:** Focused audit notes and file list in PR; outstanding known risks documented.  
  **Maps to:** FND-006, FND-009.

- [ ] **FND-T19 — Update documentation and task evidence.**  
  **Depends on:** FND-T18.  
  **Work:** Record final implemented decisions, version/extension requirements, true test results, any exception and remaining risks without duplicating canonically owned architectural/business rules. Keep statuses honest.  
  **Proof:** Reviewed `tasks.md` and PR report reflecting actual Git and CI.  
  **Maps to:** FND-010.

- [ ] **FND-T20 — Open PR for explicit approval, do not merge.**  
  **Depends on:** FND-T17–T19.  
  **Work:** Open a PR from `feature/foundation-laravel12-bootstrap` to `develop` with dependency graph, migrations, check results, file list and risks. If mandatory verification is not complete, use a **Draft PR** and mark Feature **Not Ready**.  
  **Proof:** PR link, CI status and explicit request for user approval; **no merge** without that approval.  
  **Maps to:** FND-010.

## 6. Required review decisions before execution

**Review item R-01 — Composer plugin:** The approved nwidart v12 dependency **requires** `wikimedia/composer-merge-plugin:^2.1`. The implementation needs a **narrow Composer allowlist entry for that plugin**, not blanket plugin activation. This was not explicitly named in the earlier high-level plan; confirm its inclusion before FND-T03/T06.

**Review item R-02 — First dependency lock:** Decide/verify where real **PHP 8.2** and Composer can run. A disposable PHP 8.2 GitHub Actions bootstrap workflow is a possible controlled approach; no fabricated `composer.lock` is acceptable. Until the environment is confirmed, FND-T02/T07 remain unverified.

**Review item R-03 — Default scaffold migrations:** Review removal/deferral of unapproved sample `users`, `jobs`, `cache`, `sessions` data tables before first database migration. This is about **new, unapplied skeleton files only**, not deleting any real database table.

**Review item R-04 — PHP lifecycle:** Current user-approved runtime remains **PHP 8.2**. PHP upstream support expires **2026-12-31**. Record a separate maintenance/upgrade decision before a production launch after this date; do not silently upgrade PHP in this feature.

## 7. Definition of Done / verification matrix

| Acceptance criterion | Primary tasks | Evidence required |
|---|---|---|
| FND-001 — Versions | T02, T03, T04, T07, T17 | PHP 8.2 + Laravel 12 + nwidart v12 + generated Composer lock + platform checks |
| FND-002 — Boot | T04, T06, T08, T13 | Actual Laravel boot under PHP 8.2 |
| FND-003 — Database | T05, T09, T15 | MySQL 8/InnoDB/utf8mb4 test connection + isolated migrations |
| FND-004 — Locale/time | T10, T11, T14 | Executed Arabic/default/fallback/UTC/IANA tests |
| FND-005 — Routing | T12, T14 | No duplicate `/api/v1` and no added business route |
| FND-006 — Configuration | T08, T18 | Secret hygiene and safe configuration review |
| FND-007 — Testing | T13–T17 | Actual PHPUnit and Pint pass |
| FND-008 — CI | T16, T17 | GitHub Actions workflow **and successful run** on a PR to `develop` |
| FND-009 — Scope | T04–T06, T12, T18 | No out-of-scope modules, auth endpoints or background workers |
| FND-010 — Review | T17–T20 | Complete verified report, PR and explicit human merge approval |

**Current implementation state: NOT STARTED.** All checkboxes intentionally remain empty; no source code, dependencies, app migrations, tests or CI have been executed by creating these planning files.
