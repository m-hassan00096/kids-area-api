# Feature 04 — Roles & Permissions Management

> **Status:** Specification review draft. User approved the 12 business decisions and explicitly corrected naming on 2026-11-26: remove Role alias, retain Permission alias. Other contract details below are proposed unless already required by the canonical sources. No implementation or migration execution.
> **Baseline:** develop `41b5eeca7e9b04eb7b064f93464b290754fea16a`, PR #10 merged; [exact final CI 45811979133](https://github.com/m-hassan00096/kids-area-api/actions/runs/45811979133) 174 tests / 9101 assertions, Pint 159. Existing schema and historical F01–F03 evidence are not proof of this change.
> **Sources:** [Identity](../../../docs/03-modules/identity.md), [Permissions](../../../docs/02-standards/permissions-standards.md), [Localization](../../../docs/02-standards/localization-standards.md), [API](../../../docs/02-standards/api-standards.md), [Workflow](../../../docs/02-standards/ai-workflow-rules.md).

## 1. Approved scope and correction

Manage Roles, their active state and grants from the fixed catalogue; five independent Role permissions; list/detail/create/full PUT/conditional hard delete; central permissions Select; existing Users, Authentication and RBAC protections. No direct User grants, permission-definition CRUD, new module permissions, audit persistence (deferred), frontend implementation, new tables or packages planned.

**Approved naming correction (2026-11-26, Feature 04):** Role `name` is the single stored and displayed role name; remove `roles.alias_name` through a reviewed forward migration. Permission `name` stays a stable technical key and `permissions.alias_name` stays the stored display label. Permanent `manager` and `reception` names, IDs, active-state/deletion protections and mutex identity remain unchanged; authorized custom roles use unique Arabic/English names and can be renamed without changing their ID, assignments or permissions. Role resources omit `aliasName`; role Select labels use `roles.name`. The old F01–F03 implementation and historical test evidence still describe the pre-change schema until Feature 04 is implemented and verified.

Protected roles remain permanently active, undeletable and unrenamable. Custom role deletion requires no assigned User, including inactive accounts; no implicit reassignments. Editing permissions or renaming a role does not itself log Users out; fresh effective grants next protected request. Approved role disable invalidates all associated authoritative sessions atomically, advances affected credential generations and leaves User is_active and assignments unchanged; re-enable never revives old sessions. Last viable admin (six essential permissions, not a role-name test) must survive every mutation and concurrency ordering.

## 2. Role API proposal (review)

| Method | Path | Permission |
| --- | --- | --- |
| GET | /api/v1/roles | identity.roles.index |
| GET | /api/v1/roles/{role} | identity.roles.show |
| POST | /api/v1/roles | identity.roles.store |
| PUT | /api/v1/roles/{role} | identity.roles.update |
| DELETE | /api/v1/roles/{role} | identity.roles.delete |

Index: name, assigned User count (all active/inactive), permission count, isActive, computed isProtected, roleId; paginated 15 default / 100 maximum; literal search by the single name, exact isActive filter; deterministic newest ID first proposed. No per-row full permission lists. Detail adds permissions as objects with stable name and stored aliasName. No Role aliasName, timestamps or guardName exposed in these resources. Example:

```json
{"success":true,"message":"","data":{"items":[{"roleId":3,"name":"مشرف الأنشطة","usersCount":5,"permissionsCount":2,"isActive":true,"isProtected":false}],"pagination":{"total":1,"currentPage":1,"perPage":15,"lastPage":1}}}
```

Create JSON:
```json
{"name":"مشرف الأنشطة","permissions":["identity.users.view","identity.users.create"]}
```

Create active by default; empty permissions array allowed. Proposed optional boolean isActive; whether initial inactive creation is allowed to be confirmed. Name is unique in web guard; no two-column name/alias contract; proposed trimmed nonblank valid Unicode Arabic/English display name, length/normalization/collation behavior to be finalized against actual DB; reject reserved names and database collisions safely. Permission strings exact registered names, no duplicates, arrays only; no guard or ID/alias injection.

Full PUT proposal:
```json
{"name":"مشرف الأطفال","isActive":true,"permissions":["identity.users.view"]}
```

For protected roles name must match its unchanged reserved name and isActive must remain true. Name edits preserve Role ID, memberships and grants except deliberate requested grant replacement. Proposed minimal 201/200 success envelope; 401/403/404/409/422 safe Arabic errors as appropriate. Reject protected-role deletion/deactivation/rename and used custom Role deletion; confirm exact HTTP error codes in review. Authorization before target lookup; recheck current actor and session under shared transaction lock. Existing approved roles.update may grant/revoke any predefined permission even if actor lacks it. **Open review:** whether roles.store can grant any predefined permission at creation as well; do not implement until approved. No implicit manager bypass.

## 3. Selects and cross-feature contract correction

Central GET /api/v1/selects?selects=permissions: permission options value = technical name, label = stored permission alias. Authorized by roles.store OR roles.update as approved. Existing roles and userFilterRoles retain authorization, eligibility, numeric ID values and result bounds; label changes from alias to name. Proposed max keys 3 (was 2), provider max 200 with safe overflow (review scalability), unknown/duplicate 422, any unauthorized key 403 for the entire request. No grouped payload changes; frontend groups permission keys by owning prefix.

F02 /auth/me (and any login response using same projection) nested Role contract after F04: {"id":1,"name":"manager"}; unchanged User name/username and permission strings; no User ID addition, no role aliasName. F03 list/detail nested Role contract: {"id":2,"name":"reception","isActive":true} or null as before. No role aliasName; retain all unrelated User fields, exact shapes, filters, avatar and pagination. Role Select option examples {"value":1,"label":"manager"} and {"value":3,"label":"مشرف الأنشطة"}; do not change ID/eligibility. Permission aliasName remains unchanged. This is an intentionally approved breaking removal of the older Role display field; coordinate any existing API clients before deployment.

## 4. Migration and implementation review points (not executed)

Existing roles.alias_name is NOT NULL in the actual historical migration. Keep old migrations intact; create a forward migration only after plan/tasks approval. Proposed safe default for existing rows: preserve roles.name, ID, guard, active flags, pivots, users and sessions. Never change manager/reception technical names or IdentityMutex. Existing aliases may contain unique custom display information; before any destructive execution inspect actual schema and role names/aliases in an authorized environment, assess collisions and back up role alias mapping; require explicit reviewed decisions for any existing custom name/display mismatch rather than silently overwrite/lose it. Do not infer production data from seed fixtures. Rollback needs compatible application code and reviewed restoration from backup, not generated fake aliases. MySQL DDL auto-commit; coordinate deployment with app/client compatibility and maintenance, no production operation now. No new index without evidence; preserve current unique (name, guard_name), actual collation/length to be reviewed.

Actual source dependencies on role alias at baseline: SeedIdentityCatalogueAction (role seed attributes only; permission aliases unaffected), IdentityAccess.context (select and projection), MeResource, UsersQuery (role eager projection), UserManagementResource and RoleOptionsProvider. Review all role factory/fixture inserts, schema tests, authentication/resource/select assertions and concurrency harness fixtures; change only superseded Role alias expectations and preserve existing security/behavior assertions. Review F01 bootstrap, IdentityMutex, protected roles, permission cache, Role deletion vs assignment, Role disable vs login/profile/admin writes, bulk credential generation bounds and failure rollback. Do not add a second technical role column or use role display text as authority.

## 5. Proposed acceptance criteria (Not Run)

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

## 6. Gate

Naming correction and the previously approved business decisions are recorded. Remaining API details and Store grant scope require review before the complete specification is approved. Then review technical plan, ordered tasks and exact protected-operation authorization for disposable migration tests. No code, migration execution, merge or deployment authorized by this documentation-only checkpoint.
