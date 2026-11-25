# Kids Area - Conversation Handoff

> Snapshot: 2026-11-25 (Africa/Cairo)
> Purpose: Resume the Kids Area backend architecture and Identity implementation discussions in a new ChatGPT conversation.
> Status: Identity Module Reference approved and merged; Feature 01 (Identity Core & RBAC Foundation) is in DISCUSSION. The phased permission-catalogue / explicit grant rollout decision is APPROVED. No Feature 01 specification, plan, tasks, implementation branch, implementation or tests have been created.
> Canonical handoff path in GitHub: `docs/00-overview/kids-area-conversation-handoff.md` on `develop` once the documentation PR is merged.
> Reading rule: Every new Kids Area conversation/AI assistant should start by fetching and reading the latest GitHub handoff, then verifying the relevant source-of-truth docs and live repository status; the handoff is a navigation/progress snapshot, not an authority overriding approved specs.
> Maintenance rule: Update this **single** handoff on each major approved decision, milestone, or meaningful blocker. Commit changes on the current approved feature/documentation branch and review them through the normal PR process; **never merge without explicit user approval**. New chats with GitHub access can fetch this file instead of reattaching it.

## 1. Repository and last verified state

- Repository: https://github.com/m-hassan00096/kids-area-api
- Working development branch: `develop`
- Verified `develop` HEAD: `48211e74058c9effe496068f9ab2f33c8e3174d0` (2026-11-25 verification).
- Approved Identity docs merged via PR #3: https://github.com/m-hassan00096/kids-area-api/pull/3
- Living GitHub Handoff and AI entry instructions merged via PR #4: https://github.com/m-hassan00096/kids-area-api/pull/4
- Root `AGENTS.md`, `README.md` and AI Workflow Rules now direct new chats to this single handoff before relevant canonical sources.
- Canonical Identity Module Reference: https://github.com/m-hassan00096/kids-area-api/blob/develop/docs/03-modules/identity.md
- Relevant standards:
  - `docs/02-standards/permissions-standards.md`
  - `docs/02-standards/api-standards.md`
  - `docs/02-standards/database-standards.md`
  - `docs/02-standards/code-standards.md`
  - `docs/02-standards/error-handling-standards.md`
  - `docs/02-standards/localization-standards.md`
  - `docs/02-standards/ai-workflow-rules.md`
  - `docs/01-architecture/backend-architecture.md`
  - `docs/00-overview/project-overview.md`

## 2. Project-wide approved constraints

- One Kids Area location only; three operational areas: Cafe, Games/Activities (hourly or monthly), Nursery (monthly-only).
- Laravel 12 API-first hybrid modular monolith with `nwidart/laravel-modules` and one MySQL 8 / InnoDB database; PHP >=8.2 as compatible with host.
- Shared hosting/cPanel, host-managed daily backups, Arabic V1, no runtime Redis/queues/workers/cron/WebSockets.
- Versioned JSON API at `/api/v1`; camelCase external keys, snake_case database keys; standard success/error envelopes; central Select Registry `/api/v1/selects?selects=...`.
- Spatie roles/permissions, Laravel Policies/Gates; no super-admin Gate bypass.
- Git workflow: each approved substantive feature gets `spec.md`, `plan.md`, `tasks.md`; one feature branch from `develop`, PR to `develop`; explicit user approval is required before merge. Do not implement or deploy without the relevant approval gates.

## 3. Identity Module decisions - APPROVED

### Authentication
- Individual username + password; optional email; username case-insensitive unique, 3-50 characters, English letters/digits/dot/underscore/hyphen.
- Password >=8 characters; no required uppercase/symbol complexity.
- Five failed login attempts in one minute leads to one-minute temporary throttle, safe non-enumerating Arabic error.
- Sanctum **stateful browser Cookie/Session** authentication and Spatie `web` guard; CSRF; 12-hour inactivity expiry; logout current session only; password change invalidates previous affected sessions.
- Self password change from Profile requires current password. Another user's password may be reset within the single `PUT /api/v1/users/{user}` under `identity.users.update`, without old password; missing/empty password preserves it. No self-reset bypass through Users PUT.
- No public Forgot Password or email/SMS reset. Initial founder Manager created via separate secure one-time Artisan command. No extra automated emergency recovery command; owner handles exceptional loss of access manually.

### Users and founder protection
- Multiple Users may have `manager`; no one-Manager-only rule.
- Every User has a maximum of one active role; no direct User permission grants.
- New Users active by default; roles must be active to assign.
- Permissions: `identity.users.view`, `.create`, `.update`, `.delete` (delete is separate).
- General `identity.users.update` can edit other Managers (data, status, role, password) except protected User ID 1.
- **User ID 1:** cannot be deleted/deactivated by anybody, including owner; no other User may update it despite their permissions; owner may make other authorized ordinary account/role changes subject to last viable admin, and may only change own password through Profile with old password.
- Other Users can be hard-deleted only when they have no important historical/operational dependencies; otherwise deactivate. Preserve financial, attendance, shifts, privileged audit evidence; creation-audit event alone must not make every User undeletable.

### Roles / permissions / audit
- Seed built-in technical Roles: `manager` and `reception`, never disabled or hard-deleted.
- Custom Roles may be created/updated/disabled/reactivated; hard delete allowed only when assigned to zero Users, including inactive Users.
- Five separately authorized Role CRUD capabilities: `identity.roles.index`, `.store`, `.show`, `.update`, `.delete`.
- `identity.roles.update` may grant/revoke any existing technical permission to a Role, even if the actor does not personally have it, while retaining essential admin access.
- Role and Permission technical names stable; aliases stored in `alias_name` in database. Role changes audited. Fixed permission catalogue owned by business modules, aggregated/seeded by Identity.
- Fresh installation: `manager` receives all then-approved permissions explicitly; `reception` receives approved daily operational permissions. Later reseeds only add missing definitions; do not reset aliases or role grants; new technical permissions are not automatically granted to existing roles.
- Reception scope includes children/guardian attendance, hourly sessions, Cafe/POS and cashier, monthly/nursery subscriptions, renewals, installments, constrained operational edits; no sensitive refunds, payroll, HR contract or Identity permissions by default. Exact permission keys belong to the later modules.
- Last viable administrative access path must remain after all sensitive mutations, using MySQL transaction/locking strategy.
- Audit sensitive Identity changes synchronously/atomically, preserving history even after eligible deletes; `identity.audit.view` controls audit reads. No routine successful Login/Logout change audit. Suspicious authentication attempts may be handled in separate security logs.
- Identity Select Registry includes role options (active for assignment) and predefined permissions with stored aliases. Unknown select -> 422; any unauthorized requested key -> 403 for entire lookup.

## 4. Implementation status actually verified on `develop`

- `composer.json` requires Laravel `^12.0`, PHP `^8.2`, `nwidart/laravel-modules` `^12.0`.
- **Sanctum and Spatie Permission are not yet listed in `composer.json`**. Installing/configuring Spatie is part of Feature 01; Sanctum browser login is part of Feature 02 (the exact install/dependency staging can be reviewed in the plan).
- `Modules/` currently contains only `.gitkeep`; there is no Identity Module code.
- `app/Models/User.php` was not found, while `config/auth.php` still imports `App\Models\User`; Feature 01 must create/configure canonical `Modules/Identity/app/Models/User.php` and update Laravel auth provider.
- `bootstrap/app.php` sets API prefix `api/v1` and basic middleware/exception scaffolding.
- No actual Feature 01 spec/plan/tasks or feature branch has been authored.

## 5. Current topic - Feature 01: Identity Core & RBAC Foundation

### Initial proposed scope (not yet approved as Feature 01 spec)
- Generate canonical Identity Module and User model, configure Laravel authentication User provider.
- Install and configure Spatie Permission with guard `web`, native tables/relationships, aliases and active status.
- Design User and RBAC migrations, single-role DB constraint, username uniqueness, and protective access checks for future features.
- Implement module-owned permission definitions and additive Identity seeders; two built-in roles and first-install explicit grants.
- Implement secure one-time Artisan bootstrap for initial Manager with exactly User ID 1, fail closed if ID 1 occupied; no plaintext default credentials.
- Implement foundational audit-write infrastructure (actor/target snapshots safe for future deletion), transaction-safe last-viable-admin guard and security test foundation.
- Provide relevant PHPUnit/MySQL 8 tests and Pint checks.
- **Not in Feature 01:** Login/Logout/Sanctum session functionality (Feature 02); Users CRUD (Feature 03); Roles CRUD (Feature 04); Audit listing/detail API (Feature 05); business module permissions not yet formally specified.

### Approved decision: phased permission catalogue and explicit rollout grants

**User approved 2026-11-25:** Feature 01 seeds only the **10 agreed Identity technical permissions**: four for Users (`identity.users.view/create/update/delete`), five for Roles (`identity.roles.index/store/show/update/delete`), and `identity.audit.view`. The first Manager receives these 10 explicitly; Reception starts without Identity administrative grants. In later business features, the owning module provides its reviewed permission definitions, Identity adds them **additively**, and assigning new grants to existing Manager/Reception roles is a **separate explicitly approved, auditable rollout action** — never an automatic routine Seeder side-effect. Do not pre-invent other module keys or overwrite customized live grants.

### Next design decision (PROPOSED; not yet approved)

Define the minimal **essential access-management permission set** for the last viable administrator and whether an active custom-role account holding that effective set can count as the viable admin. Proposed six: `identity.users.view`, `identity.users.create`, `identity.users.update`, `identity.roles.index`, `identity.roles.show`, `identity.roles.update`; `identity.users.delete`, `identity.roles.delete` and `identity.audit.view` are not strictly necessary to preserve access management. Protect the invariant under InnoDB concurrency and always enforce User ID 1 rules. Confirm before adding to the Feature 01 spec.

### Further details for Feature 01 plan (not silently decided)
- Identity's 10 first-install permission keys have been confirmed; the exact **essential subset** for last-viable-admin protection and custom-role eligibility are still proposed.
- Installed Spatie version and morph-pivot unique index compatibility; actual MySQL 8 constraints and safe lock strategy.
- Exact audit schema/FK snapshot strategy allowing deletion of unused Users while preserving history.
- First-manager bootstrap command credentials and idempotent/fail-safe behavior.
- Test harness and CI actual status. All unrun tests must be identified as Not Run.

## 6. Next action and restrictions

1. Continue **discussion only** of Feature 01 scope, permission inventory and acceptance tests.
2. Phased seeding/grants is APPROVED. Next confirm the minimal **essential admin** capabilities / custom-role eligibility, then approve Feature 01 functional scenarios and acceptance criteria.
3. Only after approval, prepare/review `specs/identity/identity-core-rbac-foundation/spec.md`, then `plan.md`, then `tasks.md`.
4. Do not create implementation branch or write code before approved gates; do not merge any PR without explicit approval.
5. Update **this same GitHub handoff** at every approved decision, milestone or blocker; capture a timestamp, current verified branch/commit/PR state, *approved vs proposed* decisions, work actually done/tests run, current question, and exact next step. Do not use the handoff to override canonical docs or invent completed work.
6. On a new ChatGPT conversation, open the current `develop` version of this handoff through the connected GitHub app (or supply its exact URL if unavailable), then inspect the current repo/PRs and relevant canonical files before continuing.