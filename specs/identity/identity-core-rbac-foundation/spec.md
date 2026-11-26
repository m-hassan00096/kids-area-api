# Feature 01 — Identity Core & RBAC Foundation

> **Status:** APPROVED — user approved the functional specification on 2026-11-25; **scoped implementation subsequently authorized on 2026-11-25**.
> **Owner:** `Modules/Identity`
> **Target stack:** Laravel 12, MySQL 8/InnoDB, `nwidart/laravel-modules` ^12, Spatie Laravel Permission (`web` guard, Teams disabled).
> **Sources:** `docs/03-modules/identity.md`, `docs/02-standards/permissions-standards.md`, `docs/02-standards/database-standards.md`, `docs/02-standards/api-standards.md`, `docs/02-standards/ai-workflow-rules.md`.
> **Delivery gate:** `spec.md`, `plan.md` and `tasks.md` approved on 2026-11-25. Documentation PR #5 merged; user subsequently authorized scoped implementation and approved dependency installation. No implementation merge or production deployment authorized.

> **Feature 04 supersession (approved 2026-11-26):** Historical F01–F03 schema, payload examples, tests and evidence below document the version merged before this change. The target contract in [Roles & Permissions Management](../roles-management/spec.md) removes `roles.alias_name` and nested Role `aliasName`, uses `roles.name` for Role Select labels, preserves permission `alias_name` and all other previous access/session/Users rules. Do not treat older examples as a new requirement or rewrite the historical verified results. The target requires its own migration, implementation and regression evidence.

## 1. Goal

Establish the minimum reliable Identity data model and Spatie RBAC foundation, and provision **one first Manager User (ID 1)** on a new installation. Future Authentication, Users Management and Roles Management features must be able to reuse these rules instead of creating parallel identity models or authorization engines. Keep V1 simple for shared hosting.

## 2. In scope

1. Generate the Identity module and its **single canonical staff User model** at `Modules/Identity/app/Models/User.php`; configure Laravel's authentication provider to use it rather than a parallel `App\Models\User`.
2. Configure native Spatie roles, permissions, pivot tables and the **`web` guard**. Use stored `alias_name` on Roles and Permissions; add `roles.is_active`. Seed technical `manager` and `reception` as active, permanent roles.
3. Define the `users` foundation with `id`, `name`, `username`, optional `email`/`phone`, securely hashed `password`, `is_active`, and timestamps. Role membership is through Spatie, **not** `users.role_id`.
4. Database-enforced unique constraints and validation foundation for Username/Email/Phone, plus **at most one persisted Role per User** on Spatie's installed pivot schema.
5. Idempotent, additive Identity technical permission and baseline Role seeders, with **explicit first-install grants**.
6. One separate, secure Artisan `identity:bootstrap-manager` command. It must create the first active Manager as **User ID 1** only after seeders have been run separately.
7. Foundational authorization checks for active User/Role, no direct User grants, no super-admin bypass; reusable **last viable administrative path** rule and transaction-safe protection for subsequent Identity mutation features.
8. Focused PHPUnit/integration tests and real MySQL 8 tests for schema uniqueness, bootstrap edge cases and concurrency, plus Pint/CI checks when configured.

## 3. User data and validation rules

| Field | Required? | Uniqueness and accepted value |
|---|---|---|
| `username` | Yes | 3–50 ASCII letters `A-Z`/`a-z`, digits, dot, underscore or hyphen. **Case-sensitive** for storage, UNIQUE and Login. `Mohamed.Admin` and `mohamed.admin` are different values. |
| `email` | No (`NULL`) | UNIQUE if supplied, **case-insensitive** comparison; preserve input casing for storage/display. Multiple `NULL` values allowed. |
| `phone` | No (`NULL`) | Exactly **11 ASCII digits** when supplied, stored as a string with leading zeros, UNIQUE if supplied. No `+20` conversion, international formats, spaces, punctuation, or required `01` prefix. |
| `name` | Yes | Staff display name; not required to be unique. |
| `password` | Yes for first User | At least 8 characters, securely hashed and never exposed or logged. |
| `is_active` | Yes | Boolean; newly provisioned User is active. |

Uniqueness is enforced by **MySQL constraints**, including against inactive Users and during concurrent writes, not only by Laravel validation. Email and Phone are **not** Login identifiers. Empty optional input handling and exact index/collation choices are implementation-plan details; do not store meaningless duplicate empty contact values.

## 4. Roles, permissions, and grants

**Exactly ten initial technical permissions**, seeded under the `web` guard with Arabic stored `alias_name` labels:

| Area | Technical permission keys |
|---|---|
| Users | `identity.users.view`, `identity.users.create`, `identity.users.update`, `identity.users.delete` |
| Roles | `identity.roles.index`, `identity.roles.store`, `identity.roles.show`, `identity.roles.update`, `identity.roles.delete` |
| Audit catalogue only | `identity.audit.view` — **no working Audit API in this feature** |

- **Fresh install:** `manager` receives all 10 permissions **explicitly**; `reception` receives **no Identity administration permissions**. Do not invent permissions from other modules yet.
- **Later seeding:** insert missing catalogue definitions / required roles only. Never silently restore revoked role grants, overwrite existing role aliases, reset account passwords/status, or grant newly added module permissions to live roles.
- The technical role names `manager` and `reception` are permanent and cannot be deactivated/deleted. More than one Manager User is allowed. No `Gate::before` unconditional Manager authorization.
- A staff User has **at most one Role assignment** persisted. After checking the actual Spatie schema with Teams disabled, add a compatible unique index equivalent to `UNIQUE (model_type, model_id)` on `model_has_roles`. Role changes in later management features must use authorized `syncRoles([$role])` inside a User-serialized transaction.
- A protected operation is permitted only for an **active User with exactly one assigned active Role** and its actual effective permission. Zero roles, multiple roles, inactive role/user or unexpected direct User grants **fail closed**. `model_has_permissions` may exist as a native Spatie table, but direct User grants are not allowed.
- Role permission definitions belong to the relevant business modules; Identity only aggregates and seeds the approved catalogue.

## 5. Founding Manager Bootstrap

An operator first runs the approved additive Role/Permission seeders **separately** and then invokes `identity:bootstrap-manager`.

The command must:

1. Verify that **both** seeded `manager` and `reception` roles exist, are active and use `web`; all ten technical Identity permissions exist; and the initial `manager` role possesses all ten grants. **Missing/inconsistent prerequisites => fail without mutations**, with an actionable message. Do **not** execute seeders or silently repair grants.
2. Verify `users` contains **zero rows**, including inactive accounts. Any existing User, irrespective of ID or role, blocks the command; it never creates an additional founder or resets an existing account.
3. Obtain initial account details/credentials securely; no hard-coded/demo/default passwords, password echo, password disclosure in CLI output, or raw credential logging.
4. Create one active User, assign exactly the active `manager` Role, and verify the resulting User has **`id=1`**. The first User and its Role link are atomic. If ID 1 cannot be obtained (including an empty table with a non-1 auto-increment sequence), **fail safely**: never reset the table/sequence to force ID 1 or preserve a partly configured account.
5. Be safe under **two concurrent invocations**: at most one successful founding account; the other exits safely without a second User. Rerunning after successful provisioning is rejected. The exact locking/ID mechanism belongs to `plan.md`.
6. Never change an already established User or role/permission grants during ordinary bootstrap/reseed.

**User ID 1** is a protected founder: no one may delete/deactivate it; no other User may modify it, and its owner may only change their own password later through Profile with the current password. Those future HTTP flows are out of this feature.

## 6. Last viable administrator — foundational rule

After any authorized change to staff access, at least **one usable active User** must remain with **exactly one active Role** granting all six:

- `identity.users.view`, `identity.users.create`, `identity.users.update`
- `identity.roles.index`, `identity.roles.show`, `identity.roles.update`

A Custom Role qualifies exactly like `manager`; the role name is **not** the qualification. Having five of six is insufficient; `identity.users.delete`, `identity.roles.delete` and `identity.audit.view` are not required. User ID 1's existence alone does not prove viability.

Provide the **reusable invariant and safe transaction boundary** for later role/User mutations. An authorized attempted change leaving zero viable administrators must be rejected with `409 Conflict` and rolled back. Use existing permanent Identity rows for necessary InnoDB serialization; **do not create `identity_access_guard` or another sentinel/lock table**. Exact row lock order and race implementation are `plan.md` work, with real MySQL concurrency tests. Actual Users/Roles mutation endpoints and their end-to-end mutation tests belong to later features.

## 7. Acceptance scenarios and tests

| ID | Scenario | Expected evidence |
|---|---|---|
| F01-01 | Fresh schema, valid separate seeders | Two active technical roles, exactly 10 Identity permissions, all 10 explicit `manager` grants, zero Reception Identity grants |
| F01-02 | Rerun ordinary seeders after customized alias/grant | No duplicated definitions, overwritten aliases, restored/revoked grants or credential/status resets |
| F01-03 | Bootstrap after valid seed, empty `users` | Exactly one active User with `id=1`, one `manager` Role, secure password hash, no plaintext secrets |
| F01-04 | Any existing User (even inactive or not ID 1) / second Bootstrap | Reject; existing Users and grants remain untouched |
| F01-05 | Missing Role, missing Permission, incomplete Manager grants | Reject with operator guidance; **no automatic Seeder call**, repair or User creation |
| F01-06 | `users` empty but next auto-increment value is not 1 | Reject without a newly persisted User; no forced sequence reset |
| F01-07 | Two simultaneous Bootstrap processes | Exactly one founder User ID 1 and single-role assignment; competing attempt fails safely |
| F01-08 | Failure midway through founder creation/Role pivot | Roll back account and assignment; no partial founder |
| F01-09 | Username case variants and exact duplicates | Case variants coexist; identical exact Username cannot be duplicated, including for inactive Users |
| F01-10 | Optional Email and Phone | Multiple `NULL` permitted; provided emails case-insensitively unique; 11-digit Phone strings unique with leading zeros retained; invalid length/characters rejected |
| F01-11 | Direct second Role assignment in DB | Installed Spatie pivot unique constraint rejects two Roles for one staff User; zero/multiple/inactive anomalies fail authorization |
| F01-12 | Last viable admin predicate and conflicting changes | Custom Role with all six qualifies; partial grants do not; shared-row transaction/locking prevents concurrent loss of last viable path |
| F01-13 | Role, User and permission configuration | Single canonical User provider, `web` guard, no direct User grants, no unconditional Manager bypass |
| F01-14 | Deferred work absent | No Login/Users/Roles CRUD routes or active Audit endpoint/table/writer introduced by Feature 01; `identity.audit.view` remains just a seeded definition |

**Verification:** Focused PHPUnit tests; **actual MySQL 8 with independent connections/processes** for concurrency and unique-index behavior; Pint and available CI checks. These are **approved acceptance checks, not executed results**.

## 8. Explicitly out of scope

- **Feature 02:** Sanctum browser Cookie/CSRF login/logout, Profile, throttling, session invalidation and 12-hour idle expiry.
- **Feature 03:** Users CRUD, reassignment/deactivation APIs, conditional hard delete and full User ID 1 HTTP policy behavior.
- **Feature 04:** Roles CRUD, custom roles, permission editing, and their full API/race test matrix.
- **Deferred separate feature:** Shared Audit Logs table/writer/reader, atomic historical snapshots, API and Audit tests. `identity.audit.view` is still part of the ten technical permission keys; it has no endpoint now.
- Business-module permission catalogues, speculative Employee/People/Finance/POS fields, public recovery APIs, direct User grants, Spatie Teams, branches/tenants, queues/Redis/cron and any dedicated admin lock table.
- No production deployment or data changes are authorized by approval of this `spec.md`.

## 9. Implementation-plan questions (not new business decisions)

The separately reviewed `plan.md` must pin and inspect actual installed Spatie/Laravel versions and indexes; confirm MySQL 8 collations for case-sensitive Username and case-insensitive Email; choose safe blank-contact input handling; define transaction boundaries and the exact **existing-row** serialization strategy; confirm robust initial ID=1 and concurrent Bootstrap behavior; document safe CLI credential collection and failure messages; and identify the available real-MySQL test/CI environment. Do not start implementation or package installation before the project approval gates.
