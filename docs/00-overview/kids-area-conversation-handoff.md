# Kids Area - Conversation Handoff

> Snapshot: 2026-11-25 (Africa/Cairo)
> Purpose: Resume the Kids Area backend architecture and Identity implementation discussions in a new ChatGPT conversation.
> Status: Identity Module Reference approved and merged; Feature 01 (Identity Core & RBAC Foundation) remains DISCUSSION. Phased permission seeding, six-permission role-agnostic Last Viable Administrator Protection, deferring Audit Logs beyond Feature 01, and **founding Manager Bootstrap only when `users` is completely empty** are APPROVED. No Feature 01 spec/plan/tasks, implementation branch, code, or tests have been created.
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
- **Current docs PR #5:** https://github.com/m-hassan00096/kids-area-api/pull/5 (`docs/feature01-seeding-handoff` -> `develop`); captures phased permissions, six-permission role-agnostic admin protection, deferred Audit, and empty-users-table Bootstrap restriction. It remains open and **not merged**, pending explicit user permission.
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
- **Long-term Audit design (DEFERRED):** Shared append-only `audit_logs` (option A), module-scoped reads including `identity.audit.view`, atomic sensitive change history and preservation after eligible deletes. No routine Login/Logout change audit. **User postponed all Audit implementation beyond Feature 01 on 2026-11-25**; these capabilities are **not yet available**. Mutations before Audit delivery will lack central before/after history and cannot be fully reconstructed retroactively. Retain other security controls and reassess the production rollout risk before live admin actions.
- Identity Select Registry includes role options (active for assignment) and predefined permissions with stored aliases. Unknown select -> 422; any unauthorized requested key -> 403 for entire lookup.

## 4. Implementation status actually verified on `develop`

- `composer.json` requires Laravel `^12.0`, PHP `^8.2`, `nwidart/laravel-modules` `^12.0`.
- **Sanctum and Spatie Permission are not yet listed in `composer.json`**. Installing/configuring Spatie is part of Feature 01; Sanctum browser login is part of Feature 02 (the exact install/dependency staging can be reviewed in the plan).
- `Modules/` currently contains only `.gitkeep`; there is no Identity Module code.
- `app/Models/User.php` was not found, while `config/auth.php` still imports `App\Models\User`; Feature 01 must create/configure canonical `Modules/Identity/app/Models/User.php` and update Laravel auth provider.
- `bootstrap/app.php` sets API prefix `api/v1` and basic middleware/exception scaffolding.
- No actual Feature 01 spec/plan/tasks or **implementation** branch has been authored. A documentation-only branch `docs/feature01-seeding-handoff` and PR #5 are open; do not confuse these with implementation.

## 5. Current topic - Feature 01: Identity Core & RBAC Foundation

### Initial proposed scope (not yet approved as Feature 01 spec)
- Generate canonical Identity Module and User model, configure Laravel authentication User provider.
- Install and configure Spatie Permission with guard `web`, native tables/relationships, aliases and active status.
- Design User and RBAC migrations, single-role DB constraint, username uniqueness, and protective access checks for future features.
- Implement module-owned permission definitions and additive Identity seeders; two built-in roles and first-install explicit grants.
- Implement secure one-time Artisan bootstrap for initial Manager with exactly User ID 1, fail closed if ID 1 occupied; no plaintext default credentials.
- Implement the transaction-safe last-viable-admin guard and security test foundation. **Do not implement Audit tables/writers/read APIs or Audit tests in Feature 01**; they are deferred to a separate later feature.
- Provide relevant PHPUnit/MySQL 8 tests and Pint checks.
- **Not in Feature 01:** Login/Logout/Sanctum session functionality (Feature 02); Users CRUD (Feature 03); Roles CRUD (Feature 04); **all shared Audit functionality (table, writer, read APIs, audit-atomicity tests; deferred separate feature)**; business module permissions not yet formally specified.

### Approved decision: phased permission catalogue and explicit rollout grants

**User approved 2026-11-25:** Feature 01 seeds only the **10 agreed Identity technical permissions**: four for Users (`identity.users.view/create/update/delete`), five for Roles (`identity.roles.index/store/show/update/delete`), and `identity.audit.view`. The first Manager receives these 10 explicitly; Reception starts without Identity administrative grants. In later business features, the owning module provides its reviewed permission definitions, Identity adds them **additively**, and assigning new grants to existing Manager/Reception roles is a **separate explicitly approved, auditable rollout action** — never an automatic routine Seeder side-effect. Do not pre-invent other module keys or overwrite customized live grants.

### Approved decision: last viable administrator by six effective permissions (option A)

**User expressly approved option A on 2026-11-25:** A viable administrative path means at least one **usable active User with exactly one active Role** that effectively grants **all six**: `identity.users.view`, `identity.users.create`, `identity.users.update`, `identity.roles.index`, `identity.roles.show`, `identity.roles.update`. **The Role name is irrelevant**: a custom role qualifies equally to `manager`; not all six => not viable. Neither `identity.users.delete`, `identity.roles.delete` nor `identity.audit.view` is needed for viability. Any relevant committed change must preserve one path, with a consistent MySQL/InnoDB transaction/serialization guard, race checks and a `409` on an otherwise authorized state conflict. User ID 1 remains separately protected even when it does **not** qualify as viable.

The approved policy is now reflected in `docs/03-modules/identity.md` and `docs/02-standards/permissions-standards.md` on the open documentation branch/PR; not yet merged into `develop`.

### Approved decision: postpone Audit implementation (option A retained for later)

**User selected A and then expressly deferred Audit Logs, on 2026-11-25.** Future Audit architecture is **one shared append-only system audit store**, with role/module-scoped read authorization, but **no Audit model/table/writer/API in Feature 01**. `identity.audit.view` remains one of the 10 agreed seeded technical permissions, without a working endpoint until the Audit feature. No false claim of complete auditability for founder bootstrap or future admin mutations performed before Audit delivery. **Reassess the production rollout gate for sensitive Users/Roles management before going live.** The previous requirement that Audit writes must exist from the first sensitive mutation is superseded **for delivery timing only**; the full future audit policy and security controls remain intact.

### Approved decision: founding Manager Bootstrap requires completely empty Users table

**User expressly approved (2026-11-25):** The one-time `identity:bootstrap-manager` Artisan command may create the first active Manager only when **zero rows exist in `users`**. If **any User** already exists (regardless of ID, activation, or Role), it **must abort without writing any User or changing an existing account**. The new founder must be **ID = 1**. If an empty table has a non-1 auto-increment next value, or another bootstrap run races to provision the first row, the command must fail safely without silently resetting IDs or generating another founder. Role/permission seeders remain additive and repeatable; bootstrap **is not** a repeatable account-creation operation. No credentials hard-coded in source. Exact transaction/lock handling and controlled credential input remain Feature 01 plan details.

This approved rule is documented in both `docs/03-modules/identity.md` and `docs/02-standards/permissions-standards.md` on the open documentation PR.

### Next design topic (PROPOSED; not approved)

Clarify **bootstrap readiness behavior** if required seeded `manager` Role / approved Identity permissions are missing: (A) fail safely and instruct operator to execute reviewed permission/role seeders first; or (B) let bootstrap invoke the seeder automatically. Proposed A minimizes hidden side effects. After that, review foundational DB schema (`users`, Spatie roles/permissions/pivots) and the shared admin serialization guard. No Audit schema is in Feature 01 scope.

### Further details for Feature 01 plan (not silently decided)
- Identity's 10 initial permissions and the exact **six-permission role-agnostic viability rule** are approved. The concrete implementation strategy, table names, lock-row schema and MySQL tests remain technical plan work.
- Installed Spatie version and morph-pivot unique index compatibility; actual MySQL 8 constraints and safe lock strategy.
- Audit schema, actor/target snapshot strategy and audit-write failure tests belong to the **deferred separate Audit feature**, not Feature 01. Future deletion dependencies and integrity rules remain mandatory.
- Approved bootstrap rejects any nonempty `users` table and requires founder ID 1; credentials input mechanism, missing-seeder readiness behavior, empty-table/non-1 sequence and concurrency tests remain to be specified in the Feature 01 plan.
- Test harness and CI actual status. All unrun tests must be identified as Not Run.

## 6. Next action and restrictions

1. Continue **discussion only** of Feature 01 scope, permission inventory and acceptance tests.
2. Phased seeding/grants, six-permission role-agnostic viability, postponing Audit, and strict **empty-`users`-table Bootstrap** are APPROVED. Next determine behavior if prerequisite seeded RBAC definitions are missing, then essential DB constraints, admin serialization and Feature 01 acceptance scenarios. Audit remains a separate future feature.
3. Only after approval, prepare/review `specs/identity/identity-core-rbac-foundation/spec.md`, then `plan.md`, then `tasks.md`.
4. Do not create implementation branch or write code before approved gates; do not merge any PR without explicit approval.
5. Update **this same GitHub handoff** at every approved decision, milestone or blocker; capture a timestamp, current verified branch/commit/PR state, *approved vs proposed* decisions, work actually done/tests run, current question, and exact next step. Do not use the handoff to override canonical docs or invent completed work.
6. On a new ChatGPT conversation, open the current `develop` version of this handoff through the connected GitHub app (or supply its exact URL if unavailable), then inspect the current repo/PRs and relevant canonical files before continuing.