# Feature 04 — Roles & Permissions Management

> **Status:** APPROVED (2026-11-26). T01–T12, T15, T18–T22 verified in guarded CI 45832455232. Write routes, validation, atomic role mutations and session revocation authored; actual verification pending. No real DB, merge or deployment.
> **Baseline:** develop `41b5eeca7e9b04eb7b064f93464b290754fea16a`, PR #10 merged; [exact final CI 45811979133](https://github.com/m-hassan00096/kids-area-api/actions/runs/45811979133) 174 tests / 9101 assertions, Pint 159. Existing schema and historical F01–F03 evidence are not proof of this change.
> **Sources:** [Identity](../../../docs/03-modules/identity.md), [Permissions](../../../docs/02-standards/permissions-standards.md), [Localization](../../../docs/02-standards/localization-standards.md), [API](../../../docs/02-standards/api-standards.md), [Workflow](../../../docs/02-standards/ai-workflow-rules.md).

## 1. Approved scope and correction

Manage Roles, their active state and grants from the fixed catalogue; five independent Role permissions; list/detail/create/full PUT/conditional hard delete; central permissions Select; existing Users, Authentication and RBAC protections. No direct User grants, permission-definition CRUD, new module permissions, audit persistence (deferred), frontend implementation, new tables or packages planned.

**Approved naming correction (2026-11-26, Feature 04):** Role `name` is the single stored and displayed role name; remove `roles.alias_name` through a reviewed forward migration. Permission `name` stays a stable technical key and `permissions.alias_name` stays the stored display label. Permanent `manager` and `reception` names, IDs, active-state/deletion protections and mutex identity remain unchanged; authorized custom roles use unique Arabic/English names and can be renamed without changing their ID, assignments or permissions. Role resources omit `aliasName`; role Select labels use `roles.name`. The old F01–F03 implementation and historical test evidence still describe the pre-change schema until Feature 04 is implemented and verified.

Protected roles remain permanently active, undeletable and unrenamable. Custom role deletion requires no assigned User, including inactive accounts; no implicit reassignments. Editing permissions or renaming a role does not itself log Users out; fresh effective grants next protected request. Approved role disable invalidates all associated authoritative sessions atomically, advances affected credential generations and leaves User is_active and assignments unchanged; re-enable never revives old sessions. Last viable admin (six essential permissions, not a role-name test) must survive every mutation and concurrency ordering.

## 2. Approved Role API

| Method | Path | Permission |
| --- | --- | --- |
| GET | /api/v1/roles | identity.roles.index |
| GET | /api/v1/roles/{role} | identity.roles.show |
| POST | /api/v1/roles | identity.roles.store |
| PUT | /api/v1/roles/{role} | identity.roles.update |
| DELETE | /api/v1/roles/{role} | identity.roles.delete |

Index: name, assigned User count (all active/inactive), permission count, isActive, computed isProtected, roleId; paginated 15 default / 100 maximum; literal search by the single name, exact isActive filter; deterministic newest ID first. No per-row full permission lists. Detail adds permissions as objects with stable name and stored aliasName. No Role aliasName, timestamps or guardName exposed in these resources. Example:

```json
{"success":true,"message":"","data":{"items":[{"roleId":3,"name":"مشرف الأنشطة","usersCount":5,"permissionsCount":2,"isActive":true,"isProtected":false}],"pagination":{"total":1,"currentPage":1,"perPage":15,"lastPage":1}}}
```

Create JSON:
```json
{"name":"مشرف الأنشطة","permissions":["identity.users.view","identity.users.create"]}
```

Create active by default; empty permissions array allowed. Optional strict boolean isActive, default true; explicitly false is permitted for a custom role. Name is unique in web guard; no two-column name/alias contract; trimmed nonblank valid Unicode Arabic/English-capable display name, maximum 255 characters, validation and actual database collation per D02; reject reserved names and database collisions safely. Permission strings exact registered names, no duplicates, arrays only; no guard or ID/alias injection.

Full PUT:
```json
{"name":"مشرف الأطفال","isActive":true,"permissions":["identity.users.view"]}
```

For protected roles name must match its unchanged reserved name and isActive must remain true. Name edits preserve Role ID, memberships and grants except deliberate requested grant replacement. Minimal 201/200 success envelope; 401/403/404/409/422 safe Arabic errors as specified in D04. Reject protected-role deletion/deactivation/rename and used custom Role deletion with 409. Authorization before target lookup; recheck current actor and session under shared transaction lock. Approved D01: roles.update may grant/revoke any predefined approved web catalogue permission, and roles.store may grant any such permission on creation, even if the actor lacks it. No implicit assignment to creator; both capabilities are sensitive delegation authority. No implicit manager bypass.

## 3. Selects and cross-feature contract correction

Central GET /api/v1/selects?selects=permissions: permission options value = technical name, label = stored permission alias. Authorized by roles.store OR roles.update as approved. Existing roles and userFilterRoles retain authorization, eligibility, numeric ID values and result bounds; label changes from alias to name. Approved max keys 3 (was 2), provider max 200 with safe 409 overflow, unknown/duplicate 422, any unauthorized key 403 for the entire request. No grouped payload changes; frontend groups permission keys by owning prefix.

F02 /auth/me (and any login response using same projection) nested Role contract after F04: {"id":1,"name":"manager"}; unchanged User name/username and permission strings; no User ID addition, no role aliasName. F03 list/detail nested Role contract: {"id":2,"name":"reception","isActive":true} or null as before. No role aliasName; retain all unrelated User fields, exact shapes, filters, avatar and pagination. Role Select option examples {"value":1,"label":"manager"} and {"value":3,"label":"مشرف الأنشطة"}; do not change ID/eligibility. Permission aliasName remains unchanged. This is an intentionally approved breaking removal of the older Role display field; coordinate any existing API clients before deployment.

## 4. Migration and implementation review points (verified on synthetic CI only)

Existing roles.alias_name is NOT NULL in the actual historical migration. Keep old migrations intact; create a forward migration only after plan/tasks approval. Approved preservation policy for existing rows: preserve roles.name, ID, guard, active flags, pivots, users and sessions. Never change manager/reception technical names or IdentityMutex. Existing aliases may contain unique custom display information; before any destructive execution inspect actual schema and role names/aliases in an authorized environment, assess collisions and back up role alias mapping; require explicit reviewed decisions for any existing custom name/display mismatch rather than silently overwrite/lose it. Do not infer production data from seed fixtures. Rollback needs compatible application code and reviewed restoration from backup, not generated fake aliases. MySQL DDL auto-commit; coordinate deployment with app/client compatibility and maintenance, no production operation now. No new index without evidence; preserve current unique (name, guard_name), actual collation/length to be reviewed.

Actual source dependencies on role alias at baseline: SeedIdentityCatalogueAction (role seed attributes only; permission aliases unaffected), IdentityAccess.context (select and projection), MeResource, UsersQuery (role eager projection), UserManagementResource and RoleOptionsProvider. Review all role factory/fixture inserts, schema tests, authentication/resource/select assertions and concurrency harness fixtures; change only superseded Role alias expectations and preserve existing security/behavior assertions. Review F01 bootstrap, IdentityMutex, protected roles, permission cache, Role deletion vs assignment, Role disable vs login/profile/admin writes, bulk credential generation bounds and failure rollback. Do not add a second technical role column or use role display text as authority.

## 5. Approved acceptance criteria (Not Run)

| ID | Required verification |
| --- | --- |
| F04-01 | Exact paginated list/detail contract with accurate counts, stable search/filter/ordering, no N+1. |
| F04-02 | Arabic/English custom role creation with unique name, active default, empty grants accepted; invalid/reserved/duplicate names safely rejected. |
| F04-03 | Authorized custom rename preserves ID, assignments and sessions; protected names/guard immutable. |
| F04-04 | Permanent roles remain active and undeletable; custom deletion only when no assigned User, including inactive ones; Permission definitions preserved. |
| F04-05 | Fixed permission catalogue only; grant replacement atomic; duplicate, unknown, wrong-guard or privileged input rejected. |
| F04-06 | Independent five Role capabilities, native CSRF/origin, no target disclosure before authorization, fresh grants and stale-actor/session rejection. |
| F04-07 | Actual role disable revokes all affected sessions atomically, version changes once per affected account; re-enable cannot restore old sessions, unrelated sessions unchanged. |
| F04-08 | Last viable administrative path protected including custom roles and competing User/Role/permission updates. |
| F04-09 | Concurrent name collision, deletion/assignment, disable/login/re-enable and grant/mutation races; real MySQL rollback/fault and cache outcome evidence. |
| F04-10 | Permissions Select correct names/aliases, role Select labels now names, all-or-nothing authorization, bounds and malformed request behavior. |
| F04-11 | Forward migration preserves Role IDs/names/grants/membership, User credentials/session data and Permission aliases; no Role alias in schema/resources; safe preflight and backup/rollback review. |
| F04-12 | F01–F03 complete regression with intentional updated Role label contract, additive seeders, no unexpected schema/routes/permissions/dependencies, safe errors, Pint and CI. |

## 6. Approved contract decisions (2026-11-26)

User approved the presented specification, technical plan, tasks and decisions D01–D08.

| ID | Decision | Approved behavior |
| --- | --- | --- |
| D01 | Store grant authority | Like the already-approved roles.update, roles.store may assign any existing approved web catalogue permission, even if the actor lacks it. Treat this as a privileged delegation capability; it does not itself change the actor's grants. User create/update combined with Role store can establish a more privileged account, so grant these capabilities deliberately. No direct grants. |
| D02 | Name validation / uniqueness | Single trimmed, nonblank Arabic/English-capable Unicode name, maximum 255 characters, no invalid UTF-8, control characters or invisible directional formatting; retain normal spaces, digits and ordinary punctuation. Reject reserved-name variants under actual DB collation; permanent exact names immutable. Use existing unique (name, guard_name) and its actual case/accent equivalence (default utf8mb4_unicode_ci); confirm on MySQL 8 rather than assuming binary equality or adding a second index. |
| D03 | Create active state | Optional strict JSON boolean isActive, default true; allow explicit false for a custom role. Required permissions array, including []. |
| D04 | PUT / protected roles | Full JSON body name, isActive, permissions. For permanent roles the unchanged exact reserved name and true are required; grant replacement permitted. Protected rename/deactivation/deletion and assigned-role deletion: 409 business conflict; malformed fields, reserved names on create/custom rename and duplicate names/permissions: 422; unauthenticated 401, unauthorized 403 before lookup, missing 404. |
| D05 | List / detail | ID DESC fixed, page 15 default / 100 clamp, literal name search max 255 characters, filter[isActive] accepts canonical 0/1; reject unsupported sort/include/filter keys, malformed array/object values. Count actual assigned Users regardless of their state, do not multiply through permission joins. Detail grants sorted by stable technical name and return name/aliasName. |
| D06 | Select bounds | Max three registered keys per request, each provider max 200 options with explicit 409 overflow and no silent truncation. Preserve all-or-nothing authorization and requested order. Permission options ordered by stable technical name. No grouping or new lookup endpoint. Review a future large catalogue before increasing the bound. |
| D07 | Legacy name migration | Preserve current roles.name and all IDs/pivots, no automatic alias-to-name rename; inspect and back up legacy alias mapping and separately approve disposition of real custom names that differ from aliases before dropping. Permanent roles display manager/reception literally under the backend contract. |
| D08 | Role disable semantics | On a true-to-false transition, advance auth_version for every associated User including inactive Users, once, delete their authoritative sessions in the same transaction, keep assignments and User is_active unchanged. Repeat false->false and true reactivation do not advance versions. Same behavior for actor if authorized self-role disable leaves another viable admin; clear browser only after known commit. Grant/rename-only changes preserve sessions. |

D08 refines the already approved role-disable/re-enable behavior. The exact transaction, cache and failure design is in plan.md. Any later substantive change requires separate review before implementation.

## 7. Gate

Specification, plan and task breakdown, including D01–D08, approved on 2026-11-26. User specifically authorized testing the forward DROP COLUMN roles.alias_name only in the verified disposable GitHub Actions MySQL 8 database. This approval does not extend to real databases, new reset/rollback operations, data transformations, deployment or merge. The already approved test harness's routine guarded disposable fixture initialization remains part of the existing baseline; do not add new destructive reset/down procedures. Prefer a forward-only upgrade fixture in a guarded fresh CI service lifecycle with pre-drop export. See plan.md for exact guard and recovery requirements. Documentation PR #11 passed required CI and was explicitly authorized/merged at 92f8845a; feature implementation authorized. F04 forward migration and preservation verified in isolated CI 45821490179; baseline regression failed in historical Authentication rollback path, now repaired without new destructive operations. Historical regression and read/Select verification completed in CI 45832455232. Write checkpoint pending actual CI and remaining security, concurrency and scale checks. No real DB execution.
