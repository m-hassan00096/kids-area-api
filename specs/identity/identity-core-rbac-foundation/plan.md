# Feature 01 — Identity Core & RBAC Foundation — Technical Plan

> **Status:** DRAFT — for explicit technical review/approval (2026-11-25). Functional [spec.md](spec.md) approved by user. No implementation, dependency installation, migrations, tests or merge authorized by this draft.
> **Owner:** Modules/Identity; Laravel 12, PHP >=8.2, MySQL 8/InnoDB, nwidart ^12; single location, shared hosting.
> **Sources:** [Identity reference](../../../docs/03-modules/identity.md), [workflow](../../../docs/02-standards/ai-workflow-rules.md), [database](../../../docs/02-standards/database-standards.md), [permissions](../../../docs/02-standards/permissions-standards.md), [code](../../../docs/02-standards/code-standards.md), [localization](../../../docs/02-standards/localization-standards.md). Project-specific Kids Area documents take precedence over reference projects.

## 1. Verified repository baseline and delivery

Inspected PR #5 on docs/feature01-seeding-handoff, targeting develop (base 48211e74058c9effe496068f9ab2f33c8e3174d0). The repository contains a Laravel foundation, Modules/.gitkeep only, no root database/migrations directory, empty root DatabaseSeeder, and an auth provider referencing missing App\\Models\\User. composer.json requires PHP ^8.2, Laravel ^12.0 and nwidart ^12.0; no Spatie Permission or Sanctum dependency. phpunit.xml and CI already target PHP 8.2 / MySQL 8; CI checks no business modules and must be deliberately updated. No runtime/vendor or actual live database inspected.

- Proposed dependency: spatie/laravel-permission ^6.0, compatible with Laravel 12/PHP 8.2; confirm exact resolved version, security/platform constraints and its migration/config/trait APIs against the installed release before writing dependent code. Include composer.json and composer.lock only after explicit package-install authorization. Do not install Sanctum until Feature 02; no other runtime or development packages.
- Use the installed module generator/PSR-4 conventions and check composer autoload discovery; verify generated paths and module enabled state. Remove only unused newly generated scaffolding; never create an App\\Models\\User fallback. If module discovery/autoload requires an unapproved extra dependency, report it before installing.
- Manual feature documents (no Spec Kit CLI or .specify requirement). After plan approval, prepare tasks.md with traceability and separate review; after required approvals/merge of the docs, branch from actual develop for implementation and PR back to develop. Never merge or deploy implicitly.

## 2. Schema and persistence

All tables InnoDB; UTF-8 (utf8mb4) by default, explicit per-column comparison semantics. The migration path is module-owned, with Spatie's actual published migration/config integrated once. No duplicate default users migration, jobs, password reset, token, sessions or cache tables in this feature. Review migrations and registered ordering before executing on isolated DB; never edit deployed migrations.

| users column | Proposed MySQL definition | Rule |
|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | DB-generated; founder must obtain 1 |
| name | VARCHAR(255) NOT NULL | Non-empty human display name, not unique |
| username | VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE | ASCII 3–50; preserve case, distinct exact names |
| email | VARCHAR(254) COLLATE utf8mb4_0900_as_ci NULL UNIQUE | Case-insensitive, accent-sensitive; preserve entered casing |
| phone | VARCHAR(11) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE | Exact 11 ASCII digits, preserve zeroes |
| password | VARCHAR(255) NOT NULL | Hash only; hidden from serialization |
| is_active | BOOLEAN NOT NULL DEFAULT TRUE | Boolean cast |
| created_at / updated_at | Laravel timestamps | UTC |

Technical choices (lengths/collations) are proposed for review. Verify column character sets, collations, representative equality, strict SQL mode, uniqueness across inactive users, multiple NULLs and indexing on actual MySQL 8. For username use exact regex anchors and reject whitespace/invalid characters; do not lowercase, silently trim an invalid username, or apply a default case-insensitive lookup. For phone reject any supplied nonempty value not exactly 11 ASCII digits (including surrounding whitespace, Arabic-Indic digits, +20). Map omitted/null/empty optional contacts to NULL; do not coerce a nonempty invalid phone to a valid one. Validate email syntax and length before write, preserve case; reject invalid nonempty inputs. Apply the same comparison/validation rules to later Users actions and Feature 02 Login; HTTP request normalization must not silently alter these identifiers. Name max 255; password >=8 characters, no composition rule, no trimming. Select Laravel's secure hash configuration and confirm it can handle accepted password input without silent truncation; avoid logging inputs. No soft-deletes or additional identity fields, since approved future deletion is conditional hard delete/deactivate. Do not add redundant role_id or speculative indexes.

Spatie: native roles, permissions, model_has_roles, model_has_permissions, role_has_permissions; Teams disabled; web guard. Preserve vendor indexes/FKs; use installed schema/config table names and key types, ensure role_id/permission_id use matching unsigned keys. Add roles.alias_name and permissions.alias_name (VARCHAR(255), required; Arabic display text) and roles.is_active (boolean default true). Use small Role/Permission models extending the vendor classes as needed, and configure config/permission.php to reference them. Add a separate compatible unique index on model_has_roles(model_type, model_id), preserving native PK. That index covers every morph type in this one-staff-model system; verify actual installed keys/lengths and no duplicate rows before adding to a populated schema. Do not replace Spatie pivots with custom RBAC; no extra guard table, teams or direct User permissions. A polymorphic pivot has no native user FK; future user deletion actions must handle association cleanup using the supported APIs. No cascading deletion of future business history.

## 3. Minimal code boundaries (proposed file names)

- Modules/Identity/app/Models/User.php: canonical Authenticatable with HasRoles, explicit web guard, hidden password, boolean is_active, approved fields and factory integration. Update config/auth.php provider to canonical model; do not permit a second model override. No login/session endpoints.
- Models/Role.php and Permission.php: minimal vendor extensions for approved attributes only; migrations and seeders in module database directories; root DatabaseSeeder explicitly calls module seeder.
- One module-owned permission catalogue (e.g. IdentityPermissions) with stable names and Arabic aliases; six essential permissions derived from same source. Keep one source of truth, no global registry framework.
- Actions/SeedIdentityCatalogueAction and Actions/BootstrapManagerAction own writes/transactions. IdentitySeeder and thin Console/BootstrapManagerCommand delegate; ordinary seeder never creates users. Focused shared validation for initial user fields; no mandatory DTO/repository layers.
- Services (or equally small focused classes): IdentityAccess / LastViableAdministrator and transaction guard for locking the permanent manager row. Verify actual user, membership, role and grants; use the same fresh predicate for viable-administrator detection. No HTTP routes/controller/resources or generic policy engine; later features attach active-role checks to every protected request and enforce founder HTTP policy before mutations.
- Put Arabic CLI failure/success strings in existing language structure; use safe known business failures, nonzero exit codes, no raw stack trace or SQL/credential bindings. Future last-admin business rejection maps to the existing 409 error standard, not an HTTP response from an Action.

## 4. Catalogue and seeding

| Permission | Proposed Arabic alias |
|---|---|
| identity.users.view | عرض المستخدمين |
| identity.users.create | إنشاء مستخدم |
| identity.users.update | تعديل المستخدمين |
| identity.users.delete | حذف المستخدمين |
| identity.roles.index | عرض قائمة الأدوار |
| identity.roles.store | إنشاء دور |
| identity.roles.show | عرض تفاصيل الدور |
| identity.roles.update | تعديل الأدوار والصلاحيات |
| identity.roles.delete | حذف الأدوار |
| identity.audit.view | عرض سجل التدقيق |

Seed manager (مدير) and reception (استقبال) under web. Both active/permanent; no role-name authorization bypass. Only fresh install assigns manager all 10 explicit permissions and Reception none. On routine runs insert missing definitions and required roles, preserving existing labels, status, IDs and grants; no overwrite/upsert of existing values, no broad syncPermissions on live roles, no account writes, no permission grants to existing roles. A missing grant is an operator-reviewed correction, not a reseed effect.

Seed transaction: establish or locate manager by its unique (name, guard_name) key, using an atomic insert-or-find that never overwrites a row; handle only its identified uniqueness race, then take the manager row lock as first serialization point. A fresh install is the transaction that actually creates manager while the rest of the Identity catalogue, users and pivots are empty (check with current reads after the lock; ignore only that new manager row). No grants on partial/pre-existing state, including missing manager in a populated catalogue. In the entirely empty schema two competing seeders converge on the one creator; if initial seeding fails, roll back all seed rows/grants. Reruns after commit only add definitions. No DDL in seeding transaction. Production schema setup/seeding runs under operator-controlled installation, not competing with app migrations or manual SQL. Add MySQL test for concurrent seeding and pre-existing partially populated catalogue.

Use Spatie APIs for role grants and user assignments. Bypass stale cached permission data when checking integrity (DB current reads). Invalidate package permission cache after successful commit, never rely on it for active status, direct-grant detection or last-admin validity. Do not introduce Redis or a custom permission cache. Test warmed cache and independent instances for fresh reads; verify installed Spatie cache invalidation behavior including rollback; file cache as existing shared-host default, array in CI.

## 5. One-time bootstrap command

Proposed invocation after approved migration and separate seeding:
```sh
php artisan db:seed
php artisan identity:bootstrap-manager
```
Illustrative only; no commands executed. Command interactive terminal only; no plaintext password argument/option, env secret or default, no prompt fallback that echoes. Ask for name, username, optional email/phone and hidden password + hidden confirmation. Validate/prepare hash before acquiring locks; fail noninteractively or when secure hidden input unavailable. No arbitrary model mass assignment; explicit fields. Suppress query/debug/request logging of values/hash; sanitize unexpected CLI errors and never print raw exception bindings. Safe operational context only; Arabic success after commit and nonzero failure otherwise.

Inside BootstrapManagerAction, on the same MySQL primary connection:
1. Start outer transaction. First operation: exclusive SELECT ... FOR UPDATE on permanent roles row (manager, web) via its unique key. No cached/pre-lock snapshot; if missing, fail without repair. Re-read both roles, active flags, all ten definitions and actual role-permission pivots with current reads; require the complete initial Manager grant set. Do not invoke seeders.
2. Current locking read of users (including inactive, no global filtering). Abort if any row exists. Never use count/max(id) as proof of sequence state. Verify next AUTO_INCREMENT = 1 with fresh metadata from information_schema.tables (session information_schema_stats_expiry=0, restore original value in finally; verify increment/offset settings yield 1, same connection, actual current schema). If metadata unavailable/ambiguous or non-1, fail before insert. This preflight is not a reservation.
3. Insert one active User with generated ID (no explicit ID, no forced sequence reset). Assert actual returned id === 1 before role assignment. Assign via syncRoles([manager]); verify one active role, zero direct grants, active User, complete initial permissions from DB. Any failure throws and rolls back all row/pivot writes. Never swallow exception inside transaction.
4. Commit; release manager lock automatically; return success without secret. Second process gets same lock after commit, re-reads current users and refuses before allocating ID. No cache lock, table lock or separate lock table. Every approved Identity mutation path must obey common lock protocol; manual SQL outside it is not supported or protected. Do not retry founder transaction blindly after allocation or ambiguous commit; re-read state, report safe failure and require operator investigation.

**Known MySQL edge case:** AUTO_INCREMENT allocation is not rolled back when a transaction fails. A failed founder insert/role assignment can leave users empty but next ID > 1 even though all rows rolled back. Subsequent bootstrap must refuse as specified, not reset sequence, insert explicit 1 or silently repair. Check all prerequisites before insert; validate this outcome in a disposable MySQL database and document the separately approved manual recovery gate (e.g. restoring a verified pre-bootstrap clean DB, not automatic recovery). If seed or prerequisites fail before any insert, no User ID should be consumed. A strict first-ID guarantee and automatic re-provision after allocation failure cannot both be promised under the approved no-reset rule.

## 6. Last viable administrator: serialization and predicate

The shared serialization record is the permanent manager role (name=manager, guard=web), not a special table. It is a mutex, **not** the definition of an administrator.

- All future creation, assignment, activation/deactivation, deletion and role-grant mutations affecting administrative access (including future explicitly approved grant rollouts) run in an outer DB::transaction, acquire that manager row first, then target role rows ascending IDs, then target user rows ascending IDs, then needed pivots in consistent key order. Do not hold locks while waiting for operator input, hashing passwords, doing filesystem/network I/O or using a separate connection. If target is manager it is already locked; never rename/delete/deactivate the mutex role. Avoid nested independent transactions or taking a target lock before manager. Bootstrap/seeding use the same coordination; missing mutex fails closed except explicit catalogue initialization.
- Check actor's actual permissions and status fresh after acquiring guard for future access mutations. Apply authorized proposed write via Spatie, then evaluate post-change viability inside the same transaction before commit. Query fresh database state, not a cached loaded Eloquent relation or earlier consistent-read snapshot under REPEATABLE READ. Use locking/current reads for role membership, active statuses and required grants on the same primary; minimal indexed candidates; no raw aggregate FOR UPDATE assumptions. Guard is sufficient to serialize all cooperating writes; verify read/lock behavior under MySQL default isolation.
- A candidate is an active User, exactly one membership of canonical staff model_type, active role under web, no direct grants and all six actual role-granted keys: identity.users.view/create/update and identity.roles.index/show/update. Reject malformed state (including roleless, wrong guard, multiple roles, direct grants) as unusable. Role names irrelevant; founder existence alone does not count. Query actual grant/pivot set, not can() cached output or aliases. Return 409 via approved future business exception and rollback if zero qualify. Keep ordinary protected reads lightweight and fresh; do not take the global mutex on each normal protected API read. Feature 01 tests the reusable boundary using controlled test-only changes; production CRUD APIs remain future features.
- No unsafe partial retry on lock timeout/deadlock; abort entire transaction. Later ordinary reversible mutation retry may be bounded only when whole operation is safe to replay, rechecking authorization/state after reacquiring lock. Cache reset after commit; prove rollback does not leave effective permissions inconsistent. No generic distributed lock engine.

## 7. Tests and CI (all planned, not executed)

Use PHPUnit real MySQL 8 for constraints and concurrency; add module-focused tests and explicitly register them in phpunit.xml or root tests/Feature according to the existing suite so CI discovers them. Fixtures synthetic. Isolated database kids_area_test only; no production data. Use migrations/fresh setup in test DB only; tests that span processes must not use a parent uncommitted RefreshDatabase wrapper; fixtures committed, independent connections or PHP processes, deterministic synchronization barriers, bounded timeouts, collect both exit codes/results, confirm final DB state independently. Ensure cleanup/reset of isolated database between scenarios, including AUTO_INCREMENT tests; no destructive ops outside verified test DB. No PHPUnit success claims until execution.

| Acceptance ID | Evidence to implement |
|---|---|
| F01-01 | migrate fresh + separate seeding: 10 keys, two active roles, exact grant sets, no users |
| F01-02 | repeat seed with changed alias/revoked grant/inactive staff; no overwrite, no duplicate, missing catalogue key added without granting; partial install and concurrent first seed |
| F01-03 | successful command: id=1, one active role, hash check with synthetic password, no secrets in captured stdout/stderr/logs; empty contact normalization, hidden confirmation |
| F01-04 | existing active/inactive/non-1 User and repeat invocation rejected, snapshot rows and grants unchanged |
| F01-05 | missing each prerequisite, wrong guard/inactive baseline role, incomplete Manager grants; no seeder invoked/no mutation |
| F01-06 | empty users with next ID>1 rejected before insert; metadata refresh and exact generated ID validation |
| F01-07 | two overlapping independent bootstrap invocations, exactly one success and founder/pivot, loser refuses; distinct and identical usernames |
| F01-08 | inject failure after insert or during pivot attach: all rows/pivots rolled back; document/verify consumed sequence and safe refusal on retry |
| F01-09 | username case variants coexist, exact duplicate rejected including inactive; real concurrent duplicate uniqueness |
| F01-10 | nullable email/phone, case-insensitive duplicate email, retained casing/leading zeros, reject invalid format; independent concurrent duplicates |
| F01-11 | direct second pivot insert rejected by unique constraint; fresh eligibility rejects zero roles, inactive/roleless User, direct grants, wrong guard; verify syncRoles replacement inside transaction |
| F01-12 | custom-role six-permission administrator qualifies, five fail; two independent transactions whose individually valid updates jointly strand access: one must roll back; also role grant change vs assignment/status with warm cache |
| F01-13 | single canonical auth provider, web guard, Teams off, no direct grants/bypass; test actual permission denial |
| F01-14 | no auth/login/logout, User/Role CRUD, Selects or Audit routes/schema/writer; only seeded identity.audit.view |

Test exact constraint names/error types for expected conflicts, not swallow other DB failures. Do not add unsupported direct SQL writes to production code merely to simulate failures; test fixtures/fault injection only.

Existing .github/workflows/ci.yml uses mysql:8.0, PHP 8.2, Composer lock validation, migration, PHPUnit and Pint. Update the foundation-only Modules-empty assertions and step text to allow Identity only (retain checks against unrelated business modules, root duplicate User/migration, jobs, frontend and tracked .env). Ensure module migrations actually run, tests discover required suites, CI checks real InnoDB, collations, schema and process concurrency. Keep existing Foundation tests. Verify composer validate --strict, composer check-platform-reqs, artisan module:list, artisan migrate (disposable DB), php artisan test --filter=Identity then php artisan test, vendor/bin/pint --test, route:list absence checks. Do not weaken CI to pass. Inspect actual CI run and results on implementation PR; docs-only changes do not count as verifying this feature.

## 8. Gates, risks and explicit non-goals

- Plan choices pending approval: Spatie ^6 with exact lock resolved at implementation, schema types/collations and lengths, alias labels, NULL normalization, manager-row lock, no automatic bootstrap retry/reset and command interaction, minimal class naming and CI adjustments. Verify compatibility before implementation; report any conflict rather than silently changing decisions. No package install authorized solely by this plan; explicit dependency approval required.
- If existing/dirty schema, collation compatibility, unavailable metadata privilege, module autoload, or concurrency harness cannot be verified, stop affected work and record blocker in tasks.md. Production backups/restore and live provisioning separately authorized.
- Founder protection rules remain approved; full HTTP enforcement belongs to Features 02–04. No Sanctum install/login/session/CSRF/profile, Users/Roles CRUD, selects, Audit storage/writer/API, other module keys or operational features. Audit absence remains known production control gap; review before live privileged admin rollout.
- No production operations, no destructive migration or SQL, no Redis/queues/cron/WebSockets, no sentinel table, no automatic sequence reset.
- Next: explicit technical plan review and approval; then draft/review tasks.md; then implementation approvals, tests and PR. No tasks checked and no tests run here.

### Technical references

- [Spatie version compatibility](https://github.com/spatie/laravel-permission/blob/main/docs/prerequisites.md) (v6 compatible with PHP 8.2/Laravel 12; confirm resolved v6 docs/code).
- [MySQL 8 InnoDB AUTO_INCREMENT handling](https://dev.mysql.com/doc/refman/8.0/en/innodb-auto-increment-handling.html)
- [MySQL 8 metadata freshness](https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_information_schema_stats_expiry)
- [MySQL 8 character sets/collations](https://dev.mysql.com/doc/refman/8.0/en/charset-mysql.html)
