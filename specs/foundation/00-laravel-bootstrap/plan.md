# Foundation — Laravel 12 Bootstrap: Technical Plan

> **Status:** Detailed technical elaboration of the approved high-level plan; pending user review with \`tasks.md\` before implementation.  
> **PHP runtime / CI:** **8.2.x**; no automatic PHP 8.3 or Laravel 13 upgrade.  
> **Scope:** \`specs/foundation/00-laravel-bootstrap/spec.md\` (FND-001–FND-010).  
> **Branch:** \`feature/foundation-laravel12-bootstrap\` from \`develop\`.  
> **No implementation yet:** This document is not evidence that any package has been installed, lock file generated, migration executed or test run.

## 1. Approach and boundaries

Start from the official, **Laravel 12** application skeleton in a **disposable workspace**, select a backend-only/API-first layout, and carefully transplant the supported project scaffold into the **existing GitHub repository**, preserving \`README.md\`, \`docs/\` and \`specs/\`. Do not initialize a replacement Git repository or overwrite the approved documents. Development is GitHub-first: application source changes are committed to this feature branch, and verification is subsequently run in an isolated **PHP 8.2 / MySQL 8** environment through GitHub Actions (or a separately authorized equivalent).

Foundation is a technical feature, not a twelfth business module. Do **not** generate all eleven modules or introduce Identity/User business rules, Select Registry, auth endpoints, finance, POS, audit persistence or frontend infrastructure in this feature.

## 2. Confirmed dependency compatibility and exact intended constraints

Verified against upstream framework/package metadata **before installation**:

| Dependency / constraint | Proposed direct constraint | Placement | Compatibility decision |
|---|---|---|---|
| PHP | \`^8.2\` in Laravel's \`composer.json\`; actual execution **8.2.x** | Runtime + CI | Approved PHP 8.2. Do not resolve the lock against a higher PHP minor. |
| Laravel framework | \`laravel/framework:^12.0\` | Runtime | Laravel 12 officially supports PHP 8.2. |
| Modular package | \`nwidart/laravel-modules:^12.0\` | Runtime | v12.0.6 manifest permits \`php: >=8.2\`, with Laravel \`^12.0\` as its dev test framework. |
| PHPUnit | \`phpunit/phpunit:^11.5.50\` | require-dev | Matches official Laravel 12 application template; PHPUnit 11.5 supports PHP 8.2. |
| Laravel Pint | \`laravel/pint:^1.24\` | require-dev | Matches official Laravel 12 application template; must confirm actual resolved version in PHP 8.2 lock. |
| Composer Merge Plugin | \`wikimedia/composer-merge-plugin:^2.1\` (transitive requirement of modular package) | Composer plugin | Necessary for the documented v12 module Composer-file autoload strategy; review its exact resolved version and selectively enable ONLY its \`allow-plugins\` entry **after explicit review**. |
| Other Laravel skeleton dependencies | Only those actually required by the selected official 12.x template | Runtime or dev, as applicable | Inspect before accepting, remove frontend/dev tooling not needed for this feature, and do not add extra application packages opportunistically. |

**Not part of this feature's dependency additions:** Sanctum, Spatie Permission, Query Builder, Excel/PDF exporters, Laravel Boost, queue drivers and frontend/UI starters. Their future installation belongs to separately approved work.

**Important:** \`^12.0\` and \`^11.5.50\` are **ranges**, not proof that an arbitrary package resolution on another PHP version is compatible. The **actual** \`composer.lock\` must be generated or verified using real PHP **8.2**, and passed through \`composer validate --strict\`, \`composer install\` and \`composer check-platform-reqs\` under PHP 8.2. No hand-crafted or copied unverified lock file. Use the current supported PHP **8.2 security patch** for the runner/hosting, not an old insecure patch.

**Composer plugin safety gate:** The nwidart package requires a Composer plugin that can execute during dependency installation. Enabling that plugin is an explicit item for task-plan approval; never enable all plugins globally, and never commit tokens or credentials for Composer. The minimum necessary allowlist is \`wikimedia/composer-merge-plugin\`, subject to inspection of the actual resolver and official instructions.

**Upstream references checked (2026-11-23):**
- Official Laravel 12 application template: https://github.com/laravel/laravel/blob/12.x/composer.json
- Laravel 12 runtime requirements: https://laravel.com/docs/12.x/deployment
- nwidart v12.0.6 manifest: https://github.com/nWidart/laravel-modules/blob/v12.0.6/composer.json
- nwidart v12 installation/autoloading: https://laravelmodules.com/docs/12/getting-started/installation-and-setup
- PHPUnit 11.5 manifest: https://github.com/sebastianbergmann/phpunit/blob/11.5/composer.json

## 3. Repository structure and bootstrap method

Anticipated framework directories/files, created only when the actual scaffold is reviewed:

\`\`\`text
app/                       # Framework shared application shell, no business implementations
bootstrap/app.php          # Route registration and framework bootstrap
config/                    # Framework config; narrow app/locale/timezone adjustments
database/migrations/       # Only reviewed framework migrations, not an approved Identity schema
lang/ar/                   # Shared system-owned Arabic translations as needed
public/index.php           # Only public web entry
routes/api.php             # API registration point; no unapproved business routes
tests/Feature/             # Bootstrap and configuration tests
tests/Unit/
phpunit.xml
composer.json
composer.lock              # Composer-generated + verified on PHP 8.2
.env.example
.github/workflows/ci.yml
docs/                      # Preserve existing authoritative docs
specs/foundation/00-laravel-bootstrap/{spec.md,plan.md,tasks.md}
\`\`\`

- Reconcile \`bootstrap/app.php\`, \`composer.json\` and \`composer.lock\` from the actual Laravel 12 skeleton. **Do not** keep the scaffold's default app name, database choice, credentials or frontend scripts by accident.
- Audit **all default migrations** before using them. In particular, do not silently finalize Laravel's sample \`users\`, \`sessions\`, \`cache\`, \`jobs\` or token-related tables as Kids Area's production domain schema. Prefer removing **unapplied scaffold-only migrations** for features intentionally deferred, before the first execution; record this in PR review. Do not edit deployed/applied migrations.
- Framework-provided default User model/factory or sample frontend files are **not** approved Identity behavior. Retain only minimal needed scaffolding, explicitly marking deferred decisions.
- Configure safe filesystem cache and session storage, and no background application queue/worker/cron dependency. No production migrations or secrets in this feature.

## 4. Routing and API boundary

- Laravel 12 exposes \`withRouting(api: ..., apiPrefix: ...)\`. Register a single \`routes/api.php\` with \`apiPrefix: 'api/v1'\` in \`bootstrap/app.php\`, or an equivalent verified Laravel 12 registration that ensures exactly one \`/api/v1\` prefix.
- **Do not also prefix \`api/v1\` in \`routes/api.php\`**, otherwise the URI can be duplicated.
- Do **not** invoke \`php artisan install:api\` without review: Laravel 12's command installs **Sanctum** and may introduce authentication migrations, which are explicitly outside this feature.
- Do not introduce permanent demo routes, user endpoints or other module APIs solely to demonstrate routing. If a route probe is necessary, it must be test-scoped and leave no production-facing route behind.
- Maintain the approved central API contract at the shared boundary, but defer response helpers and Select Registry to their own approved features.

## 5. MySQL 8 and migration/test isolation

- Default runtime connection is \`mysql\` with \`utf8mb4\`; explicitly check selected CI server **MySQL 8 / InnoDB**. No SQLite substitution for relevant database verification.
- Test database has an unambiguous disposable name (for example, \`kids_area_test\`) and separate **CI-only credentials**; never connect test automation to production, real client data or a production snapshot.
- Prefer GitHub Actions' ephemeral MySQL 8 service and narrowly scoped environment variables; do not commit live credentials.
- Initial schema migration checks run only against disposable test DB. \`migrate:fresh\` is **not a production command** and is not a fallback when a migration fails. If testing needs a fresh schema, provision a **new disposable CI service database** and run ordinary migrations there.
- On schema failure, report and investigate the root cause; never silently delete application records.

## 6. Locale, clocks and configuration

- Laravel default and fallback: \`APP_LOCALE=ar\`, \`APP_FALLBACK_LOCALE=ar\`; system-owned shared translation keys use \`lang/ar/\`. No language switcher or user-language preference.
- Framework's authoritative event timezone is UTC; add a **separate configurable IANA business timezone** setting (default \`Africa/Cairo\`) rather than setting application event storage to local time. Only use \`env()\` from config, not domain code.
- Use \`APP_ENV=testing\` with isolated config for CI, \`APP_DEBUG=false\` for eventual production config, and safe placeholder \`.env.example\`.
- For V1 shared hosting, files or arrays for cache/session as appropriate, without Redis, queue workers, Laravel Scheduler, cron or external always-on services.

## 7. PHPUnit and baseline verification coverage

Create focused bootstrap **PHPUnit**, not Pest, tests that validate:

| Test area | Proves |
|---|---|
| Application bootstrap | Framework loads under PHP 8.2 with no missing service/provider errors |
| Locale | Default and fallback remain \`ar\` and shared Arabic translations resolve |
| Timezone | Event clock stays UTC; operating timezone is independently configurable, default \`Africa/Cairo\` |
| Routing | API routes can be registered with exactly one \`/api/v1\` prefix; no permanent business route was introduced |
| Database | The configured test connection uses real MySQL 8/InnoDB and \`utf8mb4\`; test migrations run against disposable DB |
| Security/config | \`.env\` ignored; no committed secret values or unapproved background runtime |
| Modules | The approved nwidart v12 package boots without generating all eleven business modules |

Avoid a passing example test being mistaken for coverage of these acceptance criteria. Use \`php artisan test\`, \`vendor/bin/pint --test\`, \`composer validate --strict\` and \`composer check-platform-reqs\`; capture real execution results. MySQL integration checks run against actual MySQL 8.

## 8. CI configuration and lock-file bootstrapping

Create \`.github/workflows/ci.yml\` with **pull_request** targeting \`develop\`, \`ubuntu-latest\` (or an explicitly pinned compatible runner), \`shivammathur/setup-php@v2\` configured for **PHP 8.2**, required extensions including \`pdo_mysql\`, \`dom\`, \`xml\`, \`simplexml\`, \`mbstring\`, and a **MySQL 8** service with a temporary database/user. Tests run on an isolated test database only.

Baseline workflow:
1. Checkout project and set up PHP 8.2 with required extensions.
2. Validate Composer manifest and check lock file consistency.
3. \`composer install --no-interaction --prefer-dist --no-progress\` against a verified \`composer.lock\`.
4. Configure temporary CI-only app key and test DB values without publishing secrets.
5. Run \`php artisan migrate --force\` against the **disposable test database**, only after reviewing scaffold migrations.
6. Run focused/bootstrap \`php artisan test\`, required MySQL-backed tests and \`vendor/bin/pint --test\`; report failures honestly.
7. Optional Composer audit where supported, without treating network/tool unavailability as a passed check.

**Bootstrap dependency-resolution problem:** At the beginning this repository has no Composer lock file. Resolve dependencies **once on an actual PHP 8.2 environment** (a trusted disposable runner or controlled local equivalent), inspect the generated \`composer.lock\`, and commit it before switching CI to \`composer install\`-only. The ordinary CI workflow must **not** silently run \`composer update\` and hide drift. If PHP 8.2 execution is unavailable, record a blocker and do **not** claim the lock or tests were validated; use a separately reviewed temporary GitHub Actions bootstrap procedure if needed.

## 9. Safety, risks and deferred decisions

| Risk | Mitigation / boundary |
|---|---|
| PHP version drift | Resolve and verify Composer lock on PHP 8.2 and run CI on PHP 8.2 only |
| PHP 8.2 approaching end of support | PHP upstream security support ends **2026-12-31**. Flag future upgrade as a **separate decision**, especially before production go-live; this plan does not change the approved PHP version |
| Composer plugin execution | Review nwidart's transitive merge plugin and approve narrow \`allow-plugins\` before use; no blanket activation |
| Auto-generated auth/job tables | Review and omit unapproved scaffold migrations before executing them |
| Unavailable CLI environment | Use a controlled PHP 8.2 environment/CI; no fabricated lock file or invented test results |
| API prefix duplication | Test framework-level prefix once; no permanent test-only business route |
| Data exposure | Only synthetic test data and disposable MySQL service; never real children's, guardians', employee or payroll records |
| Hosting runtime | Check actual cPanel PHP 8.2 patch and required extensions before any approved deployment (outside this feature) |

## 10. Review/approval gates and handoff

- The **high-level technical approach and the listed Laravel/Modules/PHPUnit/Pint dependencies are approved** by the user with PHP 8.2 compatibility as a condition.
- This detailed plan is written for review with \`tasks.md\`. **Do not start code generation, dependency installation, Composer plugin activation, migration execution, GitHub Actions setup or deployment** until the tasks are reviewed and the required plugin/runner safeguards approved.
- The owning \`tasks.md\` has independently verifiable acceptance checks tied to **FND-001 through FND-010**. Mark statuses as \`Not Started\`, \`Blocked\`, or \`Done (verified)\`; never pre-check tasks.
- PR to \`develop\` only after running required checks; merge only with explicit user approval.
