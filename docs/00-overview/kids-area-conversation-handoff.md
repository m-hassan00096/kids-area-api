# Kids Area - Conversation Handoff

> Snapshot: 2026-11-25 (Africa/Cairo)
> Purpose: Resume the Kids Area backend architecture and Identity implementation discussions in a new ChatGPT conversation.
> Status: Feature 01/02 merged; actual develop 8174097e (PR #8), F02 CI 45638942711 passed. Feature 03 spec and technical plan approved 2026-11-25 (plan reply “تمام”); tasks.md explicitly APPROVED 2026-11-25 (0/49, 19 cases Not Run) on PR #9. Preliminary local PHP8.4 raw multipart evidence recorded (duplicate scalar collapsed), target PHP8.2/GD unavailable; T01–T04 still unchecked, platform setup/feasibility and separate execution approvals pending. No implementation/merge/deployment.
> Canonical handoff path in GitHub: `docs/00-overview/kids-area-conversation-handoff.md` on `develop` once the documentation PR is merged.
> Reading rule: Every new Kids Area conversation/AI assistant should start by fetching and reading the latest GitHub handoff, then verifying the relevant source-of-truth docs and live repository status; the handoff is a navigation/progress snapshot, not an authority overriding approved specs.
> Maintenance rule: Update this **single** handoff on each major approved decision, milestone, or meaningful blocker. Commit changes on the current approved feature/documentation branch and review them through the normal PR process; **never merge without explicit user approval**. New chats with GitHub access can fetch this file instead of reattaching it.

## 1. Repository and last verified state

- Repository: https://github.com/m-hassan00096/kids-area-api
- Working development branch: `develop`
- Verified `develop` HEAD: `8174097e93069cc20ae258ba236cf7acd8d37fb3` (PR #8 merge).
- Approved Identity docs merged via PR #3: https://github.com/m-hassan00096/kids-area-api/pull/3
- Living GitHub Handoff and AI entry instructions merged via PR #4: https://github.com/m-hassan00096/kids-area-api/pull/4
- Root `AGENTS.md`, `README.md` and AI Workflow Rules now direct new chats to this single handoff before relevant canonical sources.
- **Documentation PR #5:** https://github.com/m-hassan00096/kids-area-api/pull/5 merged into develop at b6031c88a0c30560f11803a1c4dba8e0138a7185. Approved spec/plan/tasks preserved.
- **Implementation PR #6:** https://github.com/m-hassan00096/kids-area-api/pull/6 merged into develop at f5d740b6ace3e10ec63a5a69844856b3d241ecd0. Latest pre-merge CI https://github.com/m-hassan00096/kids-area-api/actions/runs/45618607042 passed.
- **Feature 02 documentation PR #7:** merged at 4f9c4505. **Implementation PR #8:** https://github.com/m-hassan00096/kids-area-api/pull/8 merged at 8174097e93069cc20ae258ba236cf7acd8d37fb3; final pre-merge CI https://github.com/m-hassan00096/kids-area-api/actions/runs/45638942711 passed (102 full tests / 2289 assertions, 101 Pint files); merged tree verified against reviewed head ee115492. No deployment.
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
- Individual Username/password Login only. Username: 3-50 ASCII Latin letters in either case, digits/dot/underscore/hyphen, stored without letter-case rewriting. **Latest user correction (2026-11-25): Username is case-sensitive for both Login and MySQL uniqueness**: `Mohamed.Admin` and `mohamed.admin` are **different** identifiers that can be registered separately; only the exact same letter-case Username must not be duplicated, including among inactive Users. The MySQL constraint must use a verified case-sensitive collation/index.
- **Email and Phone:** both `NULLABLE` and independently **UNIQUE when non-NULL**, including inactive Users; multiple NULLs allowed, neither is a Login identifier. **Email** uniqueness is case-insensitive with entered casing preserved: `Ahmed@Gmail.com` conflicts with `ahmed@gmail.com`. Username remains case-sensitive for Login/uniqueness.
- **Phone validation APPROVED (2026-11-25):** Optional and **exactly 11 ASCII digits (`0`-`9`) if provided**. **Only** require digit characters and 11-digit length; do **not** require starting `01`, validate network/operator, normalize to `+20`/E.164, or accept international/spaces/hyphens in V1. Store as a string retaining leading zeros. Unique exact non-NULL 11-digit numbers across all retained Users. Empty optional input handling can be specified as null in the upcoming feature spec without a new normalization concept.
- Password >=8 characters; no required uppercase/symbol complexity.
- **Approved 2026-11-25:** Five failed Login attempts per exact case-sensitive username within one minute trigger a one-minute temporary throttle. Additional thirty failed attempts per trusted client IP within one minute across identifiers trigger a one-minute temporary IP throttle. Safe non-enumerating Arabic error; exact counting details in draft spec/plan.
- Sanctum **stateful browser Cookie/Session** authentication and Spatie `web` guard; CSRF; approved MySQL database sessions; 12-hour inactivity expiry; Logout current session only. **Successful password change invalidates all affected User sessions including the initiating current session; re-login required**. No Redis/cron. Me must omit the top-level User id (user correction 2026-11-25); internal users.id and founder protections unchanged.
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

- Merged Feature 01 via PR #6 at f5d740b6. Laravel 12.73.0, PHP 8.2, nwidart 12.0.6, Spatie Permission 6.25.0; canonical Identity User/provider, module-owned migrations, native RBAC, ten permissions/additive seeders, secure Argon2id founder bootstrap, single-role constraint and fresh access/last-admin protection are present.
- Latest pre-merge CI run 45618607042 passed: 56 focused tests / 812 assertions, full 61 tests / 830 assertions, Pint 54 files, MySQL 8 and genuine concurrency, Composer/platform, migrations, module/route/scope checks. All 32 Feature 01 tasks and 14 acceptance cases verified. No live data or deployment.
- Feature 02 implemented and merged via PR #8: native Sanctum 4.5.0 stateful sessions, CSRF, exact Login, Logout, Me, self password change, auth_version, session and throttle tables, shared HTTP/error/request ID infrastructure, R01–R08 verification. Users CRUD, role CRUD, Select Registry and Audit remain absent. No production verification.

## 5. Completed Feature 01 decisions and verification history

### Approved Feature 01 scope
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

The approved policy is now reflected in `docs/03-modules/identity.md` and `docs/02-standards/permissions-standards.md` in documentation PR #5, merged into develop at b6031c88.

### Approved decision: postpone Audit implementation (option A retained for later)

**User selected A and then expressly deferred Audit Logs, on 2026-11-25.** Future Audit architecture is **one shared append-only system audit store**, with role/module-scoped read authorization, but **no Audit model/table/writer/API in Feature 01**. `identity.audit.view` remains one of the 10 agreed seeded technical permissions, without a working endpoint until the Audit feature. No false claim of complete auditability for founder bootstrap or future admin mutations performed before Audit delivery. **Reassess the production rollout gate for sensitive Users/Roles management before going live.** The previous requirement that Audit writes must exist from the first sensitive mutation is superseded **for delivery timing only**; the full future audit policy and security controls remain intact.

### Approved decision: founding Manager Bootstrap requires completely empty Users table

**User expressly approved (2026-11-25):** The one-time `identity:bootstrap-manager` Artisan command may create the first active Manager only when **zero rows exist in `users`**. If **any User** already exists (regardless of ID, activation, or Role), it **must abort without writing any User or changing an existing account**. The new founder must be **ID = 1**. If an empty table has a non-1 auto-increment next value, or another bootstrap run races to provision the first row, the command must fail safely without silently resetting IDs or generating another founder. Role/permission seeders remain additive and repeatable; bootstrap **is not** a repeatable account-creation operation. No credentials hard-coded in source. Exact transaction/lock handling and controlled credential input remain Feature 01 plan details.

This approved rule is documented in both `docs/03-modules/identity.md` and `docs/02-standards/permissions-standards.md` in documentation PR #5, merged at b6031c88.

### Approved decision: bootstrap does not run seeders (option A)

**User expressly approved A on 2026-11-25:** `identity:bootstrap-manager` must validate existing RBAC prerequisites first: active seeded `manager` and `reception` Roles, all ten approved Identity technical permission definitions under `web`, and the complete initial explicit `manager` grant set. If any are absent or inconsistent, **fail with no DB mutations** and tell the operator to execute the approved additive seeders beforehand or follow the separately reviewed repair process for existing customized grants. **Never run a seeder inside bootstrap**; no silent creation, repair, reassignment or permission grant. Bootstrap is one-time and requires all `users` rows absent and creation of actual ID 1.

The decision is documented in `docs/03-modules/identity.md` and `docs/02-standards/permissions-standards.md` in PR #5, merged into develop at b6031c88.

### Approved decision: three-layer Spatie Single Role enforcement

**User expressly approved (2026-11-25):** In installed Spatie `model_has_roles` (Teams disabled), enforce **at most one** persisted Role assignment with a compatible DB unique index equivalent to `UNIQUE (model_type, model_id)`, **after verifying the exact vendor schema, indexes and morph type**. All role changes go through `syncRoles([$role])` inside an authorized MySQL/InnoDB transaction, serializing on the affected User row and additionally using the shared admin-viability lock when relevant. Every protected request validates an active User with **exactly one active Role** and current effective authorization; fail closed on zero/multiple/disabled-role/direct-permission anomalies. The DB unique index itself only guarantees at most one. Test concurrent reassignment on **real MySQL 8**, no SQLite-based race claim. Do not enable Spatie Teams or silently change other morph models.

### Approved simplification: no separate Identity access-guard table

**User rejected the proposed `identity_access_guard` table (2026-11-25)** as unnecessary complexity. **Do not add that table or any new dedicated lock/sentinel table in Feature 01.** Continue to enforce the **previously approved** invariant that at least one active User holding all six essential permissions through exactly one active Role remains after every sensitive change. Serialize conflicting User/Role/grant mutations using **existing, guaranteed persistent Identity records** inside MySQL transactions (a stable seeded `manager` Role row is one possible shared serialization point; it is permanent even if a Custom Role User is the final viable admin). Confirm an efficient safe locking order and isolation semantics in `plan.md` and concurrency tests, without changing the business invariant or adding a new table.

### New milestone — all Feature 01 documents approved

**2026-11-25:** User approved [spec.md](../../specs/identity/identity-core-rbac-foundation/spec.md) and [plan.md](../../specs/identity/identity-core-rbac-foundation/plan.md), then explicitly requested drafting/uploading [tasks.md](../../specs/identity/identity-core-rbac-foundation/tasks.md) on the same docs branch without code or PR merge. The user subsequently explicitly approved the task list on 2026-11-25. All three documents are now approved. The approved task list contains 32 execution tasks in six phases with traceability to all 14 scenarios. User later authorized scoped implementation and Spatie installation, and documentation PR #5 was merged. No new business requirements or implementation-merge authorization.

### Approved technical design and implementation verification

- Spatie ^6 for PHP 8.2/Laravel 12 (exact installed version/schema and module autoload must be verified after separate package authorization); module-owned schema, approved username/email/phone collations and indexes, secure interactive bootstrap, manager-row lock and current reads, additive seeding, last-admin protection, real MySQL concurrency tests and CI adjustments.
- MySQL may consume ID 1 on failed insertion despite rollback; subsequent bootstrap must fail safely without resetting it. The initial-ID, missing-prerequisite, no-auto-seeder and simultaneous-bootstrap rules remain unchanged.
- No dedicated guard table; Audit schema/writer/read API and related audit-write tests remain a separate later feature. Known production control gap until Audit is delivered.
- CI now allows Identity only while preserving Foundation checks. Actual PHP 8.2/MySQL 8 compatibility, metadata/collations and deterministic independent-process race tests verified in CI; no workstation or hosting/live environment tested.

### New milestone — Feature 01 implementation verified and merged

- Merged code and automated tests: [PR #6](https://github.com/m-hassan00096/kids-area-api/pull/6), branch feat/identity-core-rbac-foundation, base develop b6031c88. Verified code commit [4f786d65](https://github.com/m-hassan00096/kids-area-api/commit/4f786d65fc8cb9b8e07d2aa1c0396991230e3807).
- [GitHub Actions run 45618239178](https://github.com/m-hassan00096/kids-area-api/actions/runs/45618239178) passed on PHP 8.2/MySQL 8: focused Identity 56 tests (812 assertions); full PHPUnit 61 tests (830 assertions); Pint 54 files; Composer/platform checks, migrations, module/route and scope checks. All 32 tasks/14 acceptance scenarios verified; see tasks.md. User preferred direct GitHub; verification performed in disposable CI, not workstation.
- Actual implementation: canonical staff model, native Spatie pivots with single-role unique index, additive initial permissions, transaction-safe existing manager mutex, fresh eligibility/last admin, secure interactive first-manager command and strict generated ID 1, Argon2id, rollback and real independent-process concurrency tests. No Sanctum, HTTP auth/users/roles/selects CRUD, Audit schema or lock table.
- Limitations: hosting PHP Argon2id/secure terminal/MySQL metadata and backups not verified; consumed ID caveat; Audit control gap. Sensitive mutations conservatively lock small staff catalogue; ordinary reads do not. No production work; no branch-protection changes.

## 6. Completed Feature 02 Authentication — historical verification

User expressly approved [spec.md](../../specs/identity/authentication/spec.md) and [plan.md](../../specs/identity/authentication/plan.md) on 2026-11-25, then requested [tasks.md](../../specs/identity/authentication/tasks.md) on the same docs branch without code or PR merge. The exact four API routes + native CSRF handshake, minimal Me payload (no top-level User ID, nested Role ID), Login data [], authenticated re-login 409, wrong current password 422, threshold failure 401 then 429, all-session logout after password change and all 18 acceptance cases remain approved.

Approved technical design: Sanctum ^4.5 resolved and installed at 4.5.0 after separate authorization, native encrypted database session handler, users.auth_version and three explicit authoritative session fields; one transient identity_login_throttles table with rolling windows, IP then username locks, bounded cleanup and 5s wait/503 policy. Reuse existing manager mutex for final successful Login and credential mutations only; ordinary reads do not acquire it. Fresh access, successful 2xx idle activity, cookie-only origin/CSRF, central Arabic envelope/request ID/sensitive log redaction. No new business rules.

The user explicitly approved all Feature 02 planning documents; PR #7 merged at 4f9c4505, then user explicitly authorized scoped implementation, Sanctum ^4.5 installation and isolated tests, without merge/deployment.

### Feature 02 implementation verified — 2026-11-25

- PR #8: https://github.com/m-hassan00096/kids-area-api/pull/8; code commit `20f4edec6706b670fdba036c34ca03bea9404cfb`; [passing CI](https://github.com/m-hassan00096/kids-area-api/actions/runs/45633137808). Actual Sanctum 4.5.0 only; approved four API routes plus native CSRF handshake; native encrypted DB session, authoritative generation/owner/activity, 720-minute idle expiry, exact-case Username, 5/30 rolling-window throttles and safe 503 lock timeout; reusable internal credential revocation, current-device Logout, all-device logout on self password change, fresh role/grant projection with no top-level User ID, centralized Arabic errors/request ID and secret log redaction.
- Actual evidence PHP 8.2/MySQL 8 and isolated real HTTP/CSRF, independent concurrent processes, fault injection, schema and legacy regression: Authentication 32 tests / 1193 assertions, Identity 56 / 820, full 93 / 2031, Pint 96 files, Composer lock/platform/audit, migrations and module/route/cache/scope checks passed. All 42 execution tasks and 18 cases verified; see tasks.md. Small full-cost CI sample: three same-IP successful logins ~579 ms total, 7 SQL statements for Me; not a hosting capacity guarantee.
- Hosting prerequisites remain separately unverified: exact same-site origins/TLS/proxy/cookie configuration, APP_KEY and Argon2id compatibility, database/backup/capacity and external logs. No tokens, Users/Roles/Selects CRUD, Audit persistence, queues/Redis/cron or actual deployment. Audit deferred.

### Feature 02 pre-merge review remediation — 2026-11-25

Initial pre-merge review at `5c1ed6ad` had successful [CI 45633867130](https://github.com/m-hassan00096/kids-area-api/actions/runs/45633867130) (93 tests / 2030 assertions, Pint 96 files), but R01–R08 required remediation and R09 (small staff catalogue lock breadth) remains a performance watch item, not an architecture change. User explicitly approved R01–R08 fixes/tests/documentation only, no merge/deployment. Intermediate CI: 45635618083 failed on outdated cleanup-race query observation; 45635866974 and 45636144356 exposed FormRequest attribute copying in finalization; 45636321325 passed all 9 newly added review tests and 40/41 focused tests overall, but failed the prior founder flow due to artificially unsynchronized fixture clocks. Corrected original-request boundary and shared test clock; no assertion weakened. [review code `aeb08d80`](https://github.com/m-hassan00096/kids-area-api/commit/aeb08d800e24664c651d9c7968784f0d13f7bc90) — [CI 45636784138](https://github.com/m-hassan00096/kids-area-api/actions/runs/45636784138). Run 45636784138 completed: Authentication 41/1435 assertions, Identity 56/820, full PHPUnit 102/2269 all passed; Pint reported two import-order style issues (101 files), so overall CI failed and route/module/scope checks were skipped. Import ordering was corrected without changing executable behavior. Final reviewed code [`3c06a957`](https://github.com/m-hassan00096/kids-area-api/commit/3c06a957af4badb4a64b0f97f167694c42a5a582) — [CI 45637508996](https://github.com/m-hassan00096/kids-area-api/actions/runs/45637508996) completed successfully (job 139286833198): Authentication 41 tests / 1455 assertions; Identity 56 / 820; full PHPUnit 102 / 2295; Pint 101 files; Composer validate/locked install/platform/security audit (no advisories), MySQL 8 migrations, module/route/cache and out-of-scope checks all passed. All nine review regression tests passed in both focused and full runs. Code and corresponding assertions were re-reviewed; no new merge-blocking defect was identified within R01–R08. No production verification or merge authorization.

- R01–R08 addressed in code/tests: downstream login save failure, exact idle race, shared canonical browser origins, limiter-state consistency, restoration/commit uncertainty, safe correlated HTTP 503, scoped database diagnostics, actual CSRF token rotation. See Authentication tasks.md §13 for evidence map.
- Residual boundaries: if the database is entirely unreachable, physical deletion of an undelivered candidate is best effort; no new authenticated cookie is returned and native expiry still applies. A lost COMMIT acknowledgement is simulated after a real InnoDB commit, not an actual network partition. Corrupted limiter records require operator investigation; keep coherent UTC clock, monitor cleanup capacity. Cross-subdomain HTTPS/cookie tests use synthetic hosts and trusted forwarded HTTPS, not live DNS/TLS or actual browser hosting. No new dependency, migration, permission, public endpoint, or change to approved contract, threshold, idle policy or manager-lock strategy. Production hosting and audit remain separate gates.

### Final R01–R08 verification — 2026-11-25

Final reviewed code [`3c06a957`](https://github.com/m-hassan00096/kids-area-api/commit/3c06a957af4badb4a64b0f97f167694c42a5a582) — [CI 45637508996](https://github.com/m-hassan00096/kids-area-api/actions/runs/45637508996) completed successfully (job 139286833198): Authentication 41 tests / 1455 assertions; Identity 56 / 820; full PHPUnit 102 / 2295; Pint 101 files; Composer validate/locked install/platform/security audit (no advisories), MySQL 8 migrations, module/route/cache and out-of-scope checks all passed. All nine review regression tests passed in both focused and full runs. Code and corresponding assertions were re-reviewed; no new merge-blocking defect was identified within R01–R08. No production verification or merge authorization.

- Latest full-suite sample (Argon2id 64 MiB, time 4, one thread): three same-IP successful Logins 571.18 ms total (~5.25/s), individual 220.62–570.93 ms; first throttle SQL including wait 0.75–339.39 ms; manager lock-to-response upper bound 17.52–18.22 ms, not exact hold time. Two three-failure waves 430.01/431.62 ms. Me 7, successful Login 28, failed Login 13 SQL statements. Isolated sample only, not production capacity or SLA.
- Canonical tasks/acceptance ledger reconciled: 42/42 and 18/18. R09 monitoring only. This evidence update is documentation-only; verify its actual latest PR CI and unchanged code diff before any separately authorized merge. Keep the PR unmerged; do not deploy or start Feature 03.

## 7. Current topic — Feature 03 Users Management

- User explicitly approved all six discussion decisions: disable revokes all sessions; re-enable never restores; eligible active role change keeps sessions with fresh permissions next request; all self-deletes forbidden; unchanged inactive role retained on ordinary edits but no new assignment/activation; minimal central selects roles/userFilterRoles; previous API contracts with latest corrections.
- Latest correction: staff table avatar PNG/JPG/WebP max 2 MB; avatar/name/username/phone/account state/role; search name/username/phone; filter role/state with approved Spatie Laravel Query Builder; return userId; no createdAt. Staff login accounts remain distinct from Employees/HR.
- [Feature 03 spec](../../specs/identity/users-management/spec.md) APPROVED by user on 2026-11-25 including optional Avatar/private avatarUrl/removeAvatar/2 MiB, search/default sort, detail email, multipart override and all API contracts. [Technical plan](../../specs/identity/users-management/plan.md) APPROVED, same PR #9. Verified actual baseline Laravel 12.73.0/PHP8.2/Sanctum 4.5.0/Spatie Permission 6.25.0; Query Builder 6.4.4 upstream requires PHP ^8.2/Illuminate12 and package-tools ^1.11; v7.3.9 requires ^8.4. Actual Composer resolution/install not run. Proposed one nullable unique avatar_key, private local disk/native GD static canonical 512px encoding with 2048px decode bounds, scoped HTTP/session/manager transaction, literal filters, 200 options/provider overflow409. Review exact PHP multipart duplicate-name feasibility (wire duplicates can collapse), native image validation and host support; no silent contract weakening.
- [tasks.md](../../specs/identity/users-management/tasks.md) APPROVED by explicit user instruction on 2026-11-25, seven phases A–G, 0/49 complete and all 19 cases Not Run. Approval recorded in attached commit 76ed09ce; PR #9 remains open/unmerged. Subsequent “تمام كمل” treated as authorization of narrow preliminary exploration only. See tasks.md §12 for observed PHP8.4.24 parser behavior (duplicate/mixed scalar parts collapsed; no GD), not target PHP8.2 certification. T05 requires material gates resolved, explicit docs merge and implementation approval; T06 separate dependency/platform approval, T07 guarded disposable test scope. Only isolated exploratory raw HTTP probe and environment inspection; no feature acceptance tests, migration, package/platform install, feature code, merge or deployment. Audit and live prerequisites separate.

## 8. Next action and restrictions

1. Feature 03 spec/plan/tasks approved on PR #9; approval commit 76ed09ce attached. Preliminary exploration PHP8.4.24, fileinfo/Argon2id present, GD absent. Next obtain separate explicit authorization for temporary PHP8.2/GD isolated runner setup (or verified existing disposable environment), then run target T01–T04. Resolve actual native multipart duplicate rejection and image safety/codec feasibility before implementation; obtain explicit review of any required design/contract change. No silent weakening.
2. After material feasibility resolution, obtain explicit docs merge, implementation, dependency/platform and protected-operation authorizations. No code before gates; no deployment.
