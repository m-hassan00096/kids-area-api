# Foundation — Laravel 12 Bootstrap: Implementation Tasks

> **Status: APPROVED — IMPLEMENTED; FINAL PR REVIEW IN PROGRESS.**  
> **Approved high-level plan:** Laravel 12, **PHP 8.2**, MySQL 8, nwidart v12, versioned API bootstrap, Arabic/UTC, PHPUnit, Pint and GitHub Actions.  
> **Detailed technical plan:** `specs/foundation/00-laravel-bootstrap/plan.md`  
> **Feature spec:** `specs/foundation/00-laravel-bootstrap/spec.md` (FND-001–FND-010)  
> **Branch:** `feature/foundation-laravel12-bootstrap` → eventual PR to `develop`; **manual merge approval only**.  
> **Current evidence:** PHP 8.2 lock generation run #45229235644; isolated PHP 8.2 / MySQL 8 preflight run #45228217625; successful PR CI run #45230018052. See section 8 below.

## 0. Execution gates and working rules

**Gate A — Approved:** User approved all 21 tasks and specifically authorized `wikimedia/composer-merge-plugin` with a narrow Composer `allow-plugins` entry. The installed dependency lock was generated later under PHP 8.2.

**Gate B — Verified:** GitHub Actions run #45228217625 established isolated PHP **8.2** and disposable MySQL **8** service before Composer lock generation or database tests.

**Gate C — PR review pending:** Foundation code verification passed in PR CI run #45230018052, including PHPUnit, Pint, MySQL and module CLI. PR #2 remains Draft until the final documentation commit and subsequent CI are verified.

**Task status convention:** `[ ]` = Not Started; `[x]` = Done only with cited actual implementation + verification evidence. If blocked, keep the checkbox clear and add a dated `Blocked: ...` note describing why. Approval of this checklist will authorize routine feature work, **not** destructive/production actions.

## 1. Preflight and dependency checks

- [x] **FND-T01 — Confirm source/branch baseline.**  
  **Depends on:** Gate A.  
  **Work:** Read current `develop` and feature branch head, approved `spec.md`, `plan.md`, Architecture and relevant Code/API/Database/Localization/AI Workflow Standards. Ensure repository is docs-only and no newer commits conflict.  
  **Proof:** Record source commit SHA and scoped target files.  
  **Maps to:** FND-001, FND-009, FND-010.

- [x] **FND-T02 — Verify execution environment and PHP extensions.**  
  **Depends on:** FND-T01.  
  **Work:** Identify **real PHP 8.2.x** runtime, Composer version and isolated execution environment; verify `pdo_mysql`, `dom`, `simplexml`, `mbstring`, `xml` and the full Laravel-required extension set. Confirm MySQL 8 service can be safely provisioned.  
  **Proof:** Capture actual `php -v`, `php -m`, `composer --version` and MySQL version outputs (sanitized).  
  **Blocker:** A PHP 8.3/8.4-only machine or an unverified PHP runtime is **not** valid evidence for compatibility.  
  **Maps to:** FND-001, FND-002, FND-003.

- [x] **FND-T03 — Review exact package graph and Composer plugin security.**  
  **Depends on:** FND-T02; explicit Composer plugin review under Gate A.  
  **Work:** Inspect official Laravel 12 skeleton dependency manifest; plan direct requirements `laravel/framework:^12.0`, `nwidart/laravel-modules:^12.0`, dev `phpunit/phpunit:^11.5.50` and `laravel/pint:^1.24`. Confirm PHP 8.2 compatibility of all actual transitive dependencies and review `wikimedia/composer-merge-plugin:^2.1`; allow only this plugin if approved. Do not install Sanctum, Spatie Permission, Query Builder, Laravel Boost or additional UI/dev packages without a separate reason/approval.  
  **Proof:** Approved dependency manifest and compatibility review; Composer plugin allowlist scope.  
  **Maps to:** FND-001, FND-009.

## 2. Laravel skeleton, packages and source structure

- [x] **FND-T04 — Generate official Laravel 12 skeleton in isolation.**  
  **Depends on:** FND-T02–T03.  
  **Work:** Use the official Laravel 12 skeleton in an isolated disposable workspace (no starter kit). Inspect generated files/scripts; preserve the existing repository's `README.md`, `docs/` and `specs/` when bringing framework sources onto the feature branch. Do not overwrite Git history or start a new repository.  
  **Proof:** Reviewed file diff with expected Laravel 12 structure; no framework 13 or frontend starter.  
  **Maps to:** FND-001, FND-002, FND-009.

- [x] **FND-T05 — Review/remove unapproved scaffold artifacts and migrations.**  
  **Depends on:** FND-T04.  
  **Work:** Inventory default Laravel sample users, sessions, cache and jobs migrations, models, factories, routes, Vite/frontend scripts and queue settings. Before **any migration is executed**, remove or isolate unapplied scaffold-only migrations/outputs not part of Foundation; defer all Identity schema design. Retain only necessary framework shell. No existing/applied migration is modified.  
  **Proof:** Migration inventory and rationale included in PR; no unapproved business tables created.  
  **Maps to:** FND-003, FND-009.

- [x] **FND-T06 — Add nwidart v12 and verify modular boot.**  
  **Depends on:** FND-T03–T05; Gate A plugin approval.  
  **Work:** Add approved `nwidart/laravel-modules:^12.0`, its reviewed Composer merge-plugin configuration and minimal module package configuration. Follow actual installed v12 package paths, not old v11/v13 assumptions. Do **not** generate the eleven modules.  
  **Proof:** Composer package list/installed versions, successful application boot and appropriate `module:list` output with no fabricated business modules.  
  **Maps to:** FND-001, FND-002, FND-009.

- [x] **FND-T07 — Produce genuine PHP 8.2 Composer lock.**  
  **Depends on:** FND-T03–T06.  
  **Work:** Resolve dependencies on **actual PHP 8.2**, review Composer package/plugin output and commit Composer-generated `composer.lock`. For CI-only initialization, use a separately reviewed temporary disposable runner if necessary; ordinary CI must subsequently use `composer install` rather than silently resolving fresh versions. Never fabricate or copy an unverifiable lock file.  
  **Proof:** `composer validate --strict`, clean `composer install --no-interaction --prefer-dist --no-progress` and `composer check-platform-reqs` **executed on PHP 8.2**; record installed versions.  
  **Maps to:** FND-001, FND-002.

## 3. Shared runtime and API configuration

- [x] **FND-T08 — Configure secure runtime defaults and environment examples.**  
  **Depends on:** FND-T04–T05.  
  **Work:** Set safe `.env.example`, ignore real `.env` and runtime artifacts, review file/array cache and file sessions as applicable, avoid Redis/background queue/cron dependencies, avoid public exposure of protected files. No real secrets or production edits.  
  **Proof:** Git status/ignore checks, config inspection, PHPUnit config smoke test.  
  **Maps to:** FND-002, FND-006, FND-009.

- [x] **FND-T09 — Configure MySQL 8, utf8mb4 and isolated testing.**  
  **Depends on:** FND-T05, Gate B.  
  **Work:** Use MySQL `mysql` connection with `utf8mb4` and InnoDB; configure an explicit disposable test database and test-only credentials. Test MySQL connectivity and the reviewed migration set **only** in isolated CI/dev. Do not use production data or run destructive recovery commands.  
  **Proof:** Actual MySQL 8 version, charset/engine evidence and successful migration or empty-schema verification on disposable DB.  
  **Maps to:** FND-003.

- [x] **FND-T10 — Apply Arabic-only locale and shared translation baseline.**  
  **Depends on:** FND-T04, FND-T08.  
  **Work:** Set `APP_LOCALE=ar` and `APP_FALLBACK_LOCALE=ar`; add minimal shared Arabic translation resources only as needed. Do not add locale negotiation middleware, language switch endpoints or business translations.  
  **Proof:** PHPUnit tests of default, fallback and minimal Arabic system translation behavior.  
  **Maps to:** FND-004.

- [x] **FND-T11 — Separate UTC events from configurable business timezone.**  
  **Depends on:** FND-T04, FND-T08.  
  **Work:** Keep Laravel event-time source UTC; configure an independent IANA business timezone defaulting to `Africa/Cairo`, obtained via config rather than hardcoded in business code. Do not change timestamps when reading.  
  **Proof:** PHPUnit assertions for default, override and unchanged UTC event timezone.  
  **Maps to:** FND-004.

- [x] **FND-T12 — Register the API v1 prefix exactly once.**  
  **Depends on:** FND-T04, FND-T06.  
  **Work:** Prepare `routes/api.php` and Laravel 12 `bootstrap/app.php` routing with a single prefix `api/v1`. **Do not run `install:api`** if it would install Sanctum; defer auth. Add no permanent public demo or business endpoint.  
  **Proof:** Actual framework route-registration/config test proving no duplicated prefix; explain test-only mechanism.  
  **Maps to:** FND-005, FND-009.

## 4. Tests, formatter and continuous integration

- [x] **FND-T13 — Configure baseline PHPUnit and bootstrap tests.**  
  **Depends on:** FND-T07–T08.  
  **Work:** Review `phpunit.xml` and test bootstrap, remove irrelevant template examples, write focused tests for framework boot, no unapproved module behaviors, and safe default config.  
  **Proof:** `php artisan test` (or `vendor/bin/phpunit`) actual pass results under PHP 8.2.  
  **Maps to:** FND-002, FND-007, FND-009.

- [x] **FND-T14 — Add locale/timezone/API prefix regression tests.**  
  **Depends on:** FND-T10–T13.  
  **Work:** Cover Arabic fallback; independently configurable `Africa/Cairo` business time; UTC timestamps; single `/api/v1` registration and no phantom production routes.  
  **Proof:** Test names and executed results; verify route-test fixtures are not exposed publicly.  
  **Maps to:** FND-004, FND-005, FND-007.

- [x] **FND-T15 — Add MySQL 8 database integration smoke tests.**  
  **Depends on:** FND-T09, FND-T13.  
  **Work:** Use real disposable MySQL 8 / InnoDB to assert connection, charset/collation and safe reviewed migration behavior. Do not imply financial concurrency guarantees from simple smoke tests; those belong to later features.  
  **Proof:** Actual MySQL service version, migration run and PHPUnit results.  
  **Maps to:** FND-003, FND-007.

- [x] **FND-T16 — Create GitHub Actions CI for develop PRs.**  
  **Depends on:** FND-T07, FND-T13–T15.  
  **Work:** Add `.github/workflows/ci.yml` triggered on `pull_request` to `develop`; setup PHP **8.2**, required extensions, ephemeral **MySQL 8** service, safe CI credentials, dependency install from lock, isolated migrations, `php artisan test` and `vendor/bin/pint --test`. Cache only safe dependencies; do not put secrets in repo.  
  **Proof:** Workflow YAML inspected and committed on the branch, with PHP 8.2/MySQL 8 service and mandatory commands configured. **Actual PR-run proof is collected in FND-T21 after opening the Draft PR.**  
  **Maps to:** FND-007, FND-008.

- [x] **FND-T17 — Run all mandatory checks and fix failures in scope.**  
  **Depends on:** FND-T13–T16.  
  **Work:** Run `php -v`, `composer validate --strict`, `composer check-platform-reqs`, `php artisan test`, `vendor/bin/pint --test` and required DB checks in a verified PHP 8.2 / MySQL 8 execution environment **before PR**. Correct code or setup errors within the approved scope without weakening valid tests. **PR-triggered CI runs only after FND-T20 and is verified in FND-T21.**  
  **Proof:** Record each pre-PR command as `Passed` / `Failed` / `Not Run` with environment and result. CI remains `Not Run` until FND-T21; do not claim the feature is Ready for Review.  
  **Maps to:** FND-001–FND-008, FND-010.

## 5. Final safety check and human handoff

- [x] **FND-T18 — Perform scope, security and dependency audit.**  
  **Depends on:** FND-T17.  
  **Work:** Inspect diff for forbidden Redis/jobs/cron, credentials, web starters, unapproved auth/permissions/migrations, generated module sprawl and unrelated refactors. Review exact Composer package graph and PHP 8.2 support risk.  
  **Proof:** Focused audit notes and file list in PR; outstanding known risks documented.  
  **Maps to:** FND-006, FND-009.

- [x] **FND-T19 — Update documentation and task evidence.**  
  **Depends on:** FND-T18.  
  **Work:** Record final implemented decisions, version/extension requirements, true test results, any exception and remaining risks without duplicating canonically owned architectural/business rules. Keep statuses honest.  
  **Proof:** Reviewed `tasks.md` and PR report reflecting actual Git and CI.  
  **Maps to:** FND-010.

- [x] **FND-T20 — Open Draft PR to trigger GitHub Actions; do not merge.**  
  **Depends on:** FND-T17–T19.  
  **Work:** Open a **Draft PR** from `feature/foundation-laravel12-bootstrap` to `develop` with dependency graph, migrations, pre-PR check results, file list and risks. This triggers the first true PR-targeted CI workflow. The feature remains **Not Ready** while CI is pending.  
  **Proof:** Draft PR URL, commit SHA and the resulting GitHub Actions run ID (when it starts). **No merge**.  
  **Maps to:** FND-008, FND-010.

- [ ] **FND-T21 — Verify PR checks, review evidence and request user merge approval.**  
  **Depends on:** FND-T20 and completion of the actual PR CI run.  
  **Work:** Review GitHub Actions logs, PHPUnit/Pint/MySQL 8 results and any check failures; repair only in-scope errors and rerun as needed. Once required checks actually pass, mark the PR Ready for Review, provide a complete evidence report and request explicit user approval for merge. If any mandatory check is Failed or Not Run, leave PR Draft and feature Not Ready.  
  **Proof:** Actual green PR CI run URLs/results and final review report; **do not merge without explicit approval**.  
  **Maps to:** FND-007, FND-008, FND-010.

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
| FND-008 — CI | T16, T20, T21 | GitHub Actions workflow **and successful run** on a PR to `develop` |
| FND-009 — Scope | T04–T06, T12, T18 | No out-of-scope modules, auth endpoints or background workers |
| FND-010 — Review | T17–T21 | Complete verified report, PR and explicit human merge approval |

## 8. Actual execution and verification evidence — 2026-11-23

| Check / artifact | Verified result |
|---|---|
| Safe platform preflight | **Passed** — PHP 8.2 / isolated MySQL 8, GitHub Actions [run #45228217625](https://github.com/m-hassan00096/kids-area-api/actions/runs/45228217625) |
| Approved dependency resolution | **Passed** — Composer update/validate/platform requirements on real PHP 8.2, isolated generation job in [run #45229235644](https://github.com/m-hassan00096/kids-area-api/actions/runs/45229235644) |
| Composer lock | **Committed** — Laravel v12.73.0, nwidart v12.0.6, wikimedia merge plugin v2.1.0, PHPUnit 11.5.58, Pint v1.30.4 |
| PHP and database versions in CI | **PHP 8.2.36 / MySQL 8.0.46**, disposable `kids_area_test` |
| `composer validate --strict`, `composer install`, `composer check-platform-reqs` | **Passed**, [run #45230018052](https://github.com/m-hassan00096/kids-area-api/actions/runs/45230018052) |
| `php artisan migrate --force` | **Passed** on disposable database: created migration tracking table, **no business migrations to run** |
| `php artisan test` | **Passed: 5 tests, 18 assertions** |
| `vendor/bin/pint --test` | **Passed: 23 PHP files** |
| `php artisan module:list --no-ansi` | **Passed**; no business module generated |
| Scope and sensitive-data audit | **Passed**: no `.env`, credentials, users/jobs tables, frontend starter, business modules, Redis/queue workers, or production changes in the PR diff |
| Temporary CI bootstrap workflows | **Removed from final branch**; permanent `.github/workflows/ci.yml` retained |
| Draft PR | [#2](https://github.com/m-hassan00096/kids-area-api/pull/2), targets `develop`; **not merged** |

**Remaining step: FND-T21.** Verify GitHub Actions on this updated documentation commit, change PR #2 from Draft to Ready for Review only when the checks pass, and request explicit user approval to merge. No production environment has been changed. PHP 8.2 end-of-security-support remains a separately tracked maintenance risk, not an automatic upgrade.

