# Kids Area — Backend Permissions Standards

> **Status:** Approved V1 standards; implementation and automated tests not yet started.  
> **Canonical path:** `docs/02-standards/permissions-standards.md`  
> **Stack:** Laravel 12 API-first modular monolith, MySQL 8/InnoDB, Laravel Sanctum, Spatie Laravel Permission, Laravel Policies/Gates, `nwidart/laravel-modules`.  
> **Language:** Arabic (`ar`) system text in V1; technical names remain stable English.  
> **Owner:** Identity module for user access, role management, permission catalog aggregation, and RBAC administration.  
> **Related:** [Project Overview](../00-overview/project-overview.md), [Backend Architecture](../01-architecture/backend-architecture.md), [Code Standards](code-standards.md), [API Standards](api-standards.md), [Database Standards](database-standards.md), [Error Handling Standards](error-handling-standards.md), and [Localization Standards](localization-standards.md).

## 1. Purpose, Scope and Boundaries

This document governs **how staff access is defined, stored, assigned, validated, changed, audited and tested** across Kids Area backend modules. It records **thirteen approved decisions** without inventing the final business permission inventory or the full Identity REST API.

**In scope**

- Staff authentication/authorization separation; Sanctum and one Spatie `web` permission guard.
- Single-role assignment with no user-level direct permissions.
- Permission-controlled Manager; no unconditional administrator shortcut.
- Unique Role names and stable technical Permission keys with stored permission display aliases (`alias_name`).
- Role creation, role activation/deactivation, account access checks, last-manager protection and race safety.
- Module-owned permission definitions with Identity-controlled idempotent seeding.
- Permission-cache coherence, synchronous RBAC audits, appropriate security logs and test requirements.
- Defense at route, Policy, Select Provider, Resource and Action boundaries.

**Not defined here:** exact feature-level request/Resource payloads, installed package/migration details, initial bootstrap credential-delivery implementation, approved manual recovery runbook, every permission's authorization sensitivity class, deployment-specific cookie/CORS settings, or global business state rules. The approved Identity-wide business decisions and Cookie/Session transport are recorded in [Identity Module Reference](../03-modules/identity.md); technical details belong to the feature specifications.

**V1 non-goals:** multi-tenancy, Spatie Teams, multi-role accounts, direct user grants, implicit administrator wildcard, role/permission translation tables, user impersonation, real-time permission synchronization, Redis, queues, jobs, cron, polling, WebSockets and custom RBAC engines.

## 2. Approved V1 Decisions

| # | Decision | Mandatory standard |
|---|---|---|
| 1 | **Single Role + No Direct Permissions** | Each staff `User` has **at most one** role, and protected access requires **exactly one active** role; assignment through `syncRoles()`; permissions only through roles |
| 2 | **Permission-Controlled Manager** | `manager` receives explicit approved permissions; **no custom `Gate::before()` / super-admin bypass** |
| 3 | **Active User & Role Enforcement** | Check current account/role state on every protected request; no access for inactive/no-role users |
| 4 | **Last Manager Protection** | No update may remove the last viable administrative management path; use common InnoDB locking and atomic checks |
| 5 | **Configurable Roles, Fixed Catalog** | Authorized users may create/configure custom roles; cannot invent technical permissions |
| 6 | **Module-Owned Definitions, Central Seeding** | Each module declares needed permissions; Identity aggregates and seeds them idempotently |
| 7 | **Single `web` Guard + Sanctum Cookie** | Spatie guard is `web`; protected APIs use `auth:sanctum`; the approved browser transport is stateful Cookie/Session with CSRF; exact frontend origins remain deployment-specific |
| 8 | **Native Spatie Cache + Fresh Authorization** | Use package mutation methods, correct post-commit cache handling and fresh account/role checks |
| 9 | **Complete RBAC Audit** | Audit all actual access-management changes; restrictive audit-read permission; sensitive changes atomic with audit |
| 10 | **Additive, Non-Destructive Seeders** | Initial installation sets approved defaults; subsequent routine seeding only adds missing definitions and never overwrites customized role grants or aliases |
| 11 | **Protected Seeded Roles + Conditional Custom-Role Delete** | Seeded `manager`/`reception` cannot be disabled or deleted; custom roles may be disabled, and hard-deleted only if assigned to zero Users (including inactive Users) |
| 12 | **RBAC Security Matrix + PHPUnit Tests** | Core Identity plus module authorization tests, realistic MySQL 8 concurrency/cache/audit regression in CI |
| 13 | **Database + Application Single Role** | `syncRoles()` in protected Actions plus a validated, compatible unique constraint on Spatie's role-assignment pivot |

**Important:** permission possession authorizes *attempting a capability*; it does **not** override current-state invariants (e.g., refundable amount, finished timers, membership capacity, payroll approval).

## 3. Identity Model, Roles and Security Boundaries

- `Modules/Identity/app/Models/User.php` is the canonical authenticated staff User; configure the Laravel auth provider accordingly. The User uses Spatie's `HasRoles` trait. Do not create a parallel `App\Models\User` identity source.
- An `Employee` is a distinct HR entity; optionally link an Employee and User only where the feature requires it. A contract or job title does **not** automatically grant system permissions.
- Children and guardians have no required sign-in accounts in V1.
- Every operator must have an **individual login**; no shared cashier/reception accounts.
- Seed starting roles with the stable technical names `manager` and `reception`; **neither can be deactivated or deleted**. New authorized custom roles may be added later without expanding the permission catalog. **More than one staff User may have the `manager` role.**
- Each staff User has **at most one** assigned role in persisted state. A User intended to operate protected APIs must have **exactly one** role and it must be active.
- There is no cross-location/tenant permission scope in V1. Row-level visibility still applies to protected personal/financial records.
- Ensure explicit account-active status under the Identity schema (`users.is_active`), with login and request-time enforcement. New Users are active by default. **User ID 1 is a protected founding account:** no one may delete/deactivate it; only its owner may make permitted updates, and own password changes require Profile/current password. Conditional hard deletion of other unused Users is allowed only without important business/historical dependencies. Exact user-table migration is decided in the Identity feature spec.

**Technical roles are not job titles.** Never assume that a user may close a session solely because they are named "Reception", hold the `reception` role, or have a related HR employee record.

## 4. Technical Permission Naming and Catalog Ownership

Use stable lowercase English dot-notation permission names:

- `{module}.{action}` for a simple module capability, e.g., `sessions.close`.
- `{module}.{resource}.{action}` when a module manages multiple distinct resources, e.g., `activities.subscriptions.create`.
- Use only descriptive, consistently named, genuinely separate capabilities. Do not generate exhaustive CRUD permissions for unused endpoints.
- Technical permission identifiers are **strings**. Integer-backed enums for **business statuses** are a separate convention.
- Use consistent module-level constants or an equivalently simple registry so spelling errors are caught in review/tests; no required interface or dynamic permission framework for every module.
- Each module owns its technical definitions and default display alias. Identity aggregates the approved definitions and owns deployment seeding and RBAC administration.
- Feature specs approve the **final catalog, who may use each capability, and any record-specific policy conditions**.

**Illustrative names (not final catalog):**

| Owning module | Technical name | Example `alias_name` |
|---|---|---|
| Identity | `identity.roles.manage` | إدارة الأدوار |
| Identity | `identity.audit.view` | عرض سجل تغييرات الصلاحيات |
| People | `people.children.view` | عرض بيانات الأطفال |
| People | `people.children.create` | تسجيل طفل |
| Activities | `activities.subscriptions.create` | إنشاء اشتراك |
| Sessions | `sessions.close` | إغلاق الجلسات |
| POS | `pos.sales.create` | تسجيل عملية بيع |
| Finance | `finance.refunds.create` | تنفيذ استرداد مالي |
| Payroll | `payroll.view` | عرض الرواتب |

Names above are **naming examples only**; do not interpret this table as an approved seed inventory or guaranteed API endpoint list.

**Illustrative module definition (not a mandated base abstraction):**

```php
final class SessionPermissions
{
    public const VIEW = 'sessions.view';
    public const CLOSE = 'sessions.close';

    public static function definitions(): array
    {
        return [
            self::VIEW => 'عرض الجلسات',
            self::CLOSE => 'إغلاق الجلسات',
        ];
    }
}
```

- The dashboard may assign/revoke a **predefined** permission to/from a role when the actor is authorized.
- The dashboard must **not** create arbitrary new `permissions.name` keys or rename/remove them.
- New technical permissions, and any retirement/rename, require a reviewed code/database change, compatibility analysis and approved seeding/migration.
- An alias edit never changes which capability is authorized.

## 5. Database: Spatie Tables, Aliases and Constraints

**Approved naming correction (2026-11-26, Feature 04):** Role `name` is the single stored and displayed role name; remove `roles.alias_name` through a reviewed forward migration. Permission `name` stays a stable technical key and `permissions.alias_name` stays the stored display label. Permanent `manager` and `reception` names, IDs, active-state/deletion protections and mutex identity remain unchanged; authorized custom roles use unique Arabic/English names and can be renamed without changing their ID, assignments or permissions. Role resources omit `aliasName`; role Select labels use `roles.name`. The old F01–F03 implementation and historical test evidence still describe the pre-change schema until Feature 04 is implemented and verified.

Use Spatie's native tables, pivot relationships and permission-check semantics. Extend **only what is required**, using application migrations reviewed against the installed Spatie version.

| Table / column | Purpose |
|---|---|
| `roles.id` | Primary identifier |
| `roles.name` | Unique stored and displayed role name; permanent names `manager`/`reception` immutable, custom names editable under authorization |
| `roles.guard_name` | Always `web` in V1 |
| `roles.is_active` | Boolean activation flag; enforced by the application |
| `permissions.id` | Primary identifier |
| `permissions.name` | Stable technical permission name |
| `permissions.alias_name` | Persisted human-readable permission display name; returned unchanged |
| `permissions.guard_name` | Always `web` in V1 |
| `role_has_permissions` | Spatie-maintained role/permission assignments |
| `model_has_roles` | Spatie-maintained staff User role assignment with additional single-role uniqueness |
| `model_has_permissions` | Spatie table, **no direct User permission assignments permitted** by Kids Area V1 |

**Schema rules**

1. Maintain `alias_name` only on the actual `permissions` table and `is_active` on `roles`. Feature 04 removes the historical `roles.alias_name` column via a reviewed forward migration; do not edit already-applied migrations. Do **not** call the permission field `alias`.
2. Keep the package's approved uniqueness for names within guard, matching the deployed Spatie/Teams settings; do not duplicate or conflict with package primary/unique keys.
3. New system-defined permission rows require a meaningful **nonempty** Arabic `alias_name`. Custom role names may be Arabic or English, returned as stored subject to validation; permanent roles use their exact reserved names. No automatic translation.
4. Missing permission `alias_name` is a **data quality/integrity** issue, not a missing translation key; never authorize by display text. Roles intentionally use their `name` directly.
5. Do not introduce role/permission translations tables, per-language aliases, JSON locale maps, custom authorization join tables or copied permission lists.
6. Preserve identifiers, pivots and historical audit evidence when migrating. Review and back up existing role display data before removing the legacy alias column. Do not alter an already-applied vendor migration in production: use reviewed application migrations and safe backfill phases if data exists.
7. Never accept client changes to `guard_name`, permission technical names or the two permanent role names. Validate custom role renames via authorized Role Actions and the actual database uniqueness constraint.

### 5.1 Database-level single-role invariant

**Approved and explicitly reconfirmed (2026-11-25):** enforce Single Role **in three layers**: a MySQL unique constraint on the installed Spatie assignment pivot, `syncRoles([$role])` under an authorized transaction with User-row serialization, and an active-User/active-Role/one-assignment check on every protected request. Do not substitute a UI-only or pre-insert check for the actual database invariant.

Spatie normally supports multiple role records per model. On the **actual installed schema**, with **Teams disabled** and only the approved staff User role assignment, verify an additional unique index equivalent to:

```sql
-- Conceptual, not a copy-and-run production migration:
UNIQUE (model_type, model_id)
```

- That constraint blocks a **second assigned role row** for one User regardless of which `role_id` it references.
- First verify the installed table/column names, morph model type representation, default indexes, field lengths, database migration state, and that no other morph model genuinely requires multi-role behavior. Do not assume this exact index is deployable without inspection.
- Audit and clean existing multi-role anomalies **safely before adding** the unique index; do not pick an arbitrary surviving role.
- Spatie Teams must remain off for V1; enabling Teams later requires a separate schema/invariant redesign.
- The index enforces **at most one**, **not exactly one**. Authentication middleware rejects a protected request if the User has **zero** roles. Administrative Actions must maintain valid assignments under their approved rules.
- All User-role changes use an authorized transaction with a row lock/consistent serialization for that User, plus `syncRoles([$role])`, to avoid conflicting concurrent replacements. Do not manually write pivot rows or rely on a check-then-insert pattern alone. **Test truly overlapping reassignments with distinct MySQL 8 connections**, verifying the final persisted User has no more than one Role; if the target is part of the last viable administrator path, the shared admin guard/invariant applies additionally.
- If an unexpected multi-role/direct-grant legacy state is detected, **fail closed and remediate**. Do not choose whichever role looks most powerful.
- Do not use this design to disable unrelated Spatie relationships without an explicit future review.

### 5.2 No direct User permissions

- Roles hold permissions; Users inherit from their current role.
- Application workflows **must not call** `givePermissionTo()` / `syncPermissions()` on the **User** for individual grants.
- Verify the User's `model_has_permissions` records are empty for staff identity models; detect/migrate any unexpected existing direct grants, and ensure they cannot silently bypass role deactivation.
- Use Spatie's role mutation APIs for role permissions, and a centralized authorized Action for user role reassignment.
- Keep audit references to historical technical permission/role names and record IDs, even after aliases change.

## 6. Role Management and Assignment Policy

**Seeded V1 roles:** `manager` and `reception`. Their exact initial grants must be defined in the Identity/RBAC feature spec and approved per capability; do not infer grants from the role names.

**Authorized operations and invariant checks**

| Operation | Approved behavior |
|---|---|
| Create custom Role | Allowed to actor with appropriate role-management permission and scope |
| Set Role `alias_name` | Not supported after Feature 04; one stored role name only |
| Change Role `name` | Authorized custom-role rename only; permanent names immutable |
| Assign permissions to Role | Allowed from **fixed seeded permission catalog**, with sensitive-role policies |
| Assign/change a User's Role | One role only via authorized `syncRoles()` in an Action and transaction |
| Assign role marked inactive | **Rejected** |
| Grant direct permission to User | **Rejected / unsupported** |
| Activate/deactivate a Role | Allowed for **custom roles only**, if authorized and Last Manager Protection passes; seeded roles stay active |
| Delete Role via application | **Custom roles only**, with `identity.roles.delete`, **only if no Users are assigned**, including inactive Users; seeded `manager`/`reception` cannot be deleted |
| Modify seeded Manager grants | Permission-controlled and subject to Last Manager Protection; no automatic bypass |
| Reassign staff from inactive Role | Explicit action by an authorized manager, never implicit |
| Read RBAC audit events | Separate permission and Resource scoping |

**Approved administrative capability scope and safeguards**

- **Users:** `identity.users.view`, `identity.users.create`, `identity.users.update`, and independent `identity.users.delete`. `identity.users.update` covers user data, role, activation and password reset for **another** account through the same `PUT /users/{user}`. Password remains unchanged if omitted/empty. Self password changes always use Profile with current password.
- **Roles:** independent `identity.roles.index`, `identity.roles.store`, `identity.roles.show`, `identity.roles.update`, `identity.roles.delete`; `identity.roles.update` includes all predefined Role Permission grants/revocations and custom-role activation.
- **Grants are intentionally broad:** the holder of `identity.roles.update` may grant/revoke **any existing approved web catalogue Permission**, including one they do not personally possess. **Approved F04 D01 (2026-11-26):** the holder of `identity.roles.store` may grant any such Permission on creation of a custom Role; this does not automatically assign the Role or change the creator's grants. There is no per-permission dominance restriction or implicit manager bypass. Treat these as sensitive delegation capabilities.
- **Account targets are intentionally broad:** the holder of `identity.users.update` may edit another Manager just like other Users, **except User ID 1**. No generic higher-role target restriction is added. Changing a User's own password through Users PUT remains prohibited.
- **User ID 1:** nobody else may update it, regardless of permissions or Role; nobody may deactivate or delete it (including itself). The owner may perform otherwise authorized ordinary updates and can change its password only through Profile/current password. Last Manager Protection still applies to self role changes.
- **Last viable admin protection remains mandatory** for every affected role/User/grant mutation; no permission authorizes deleting/stranding the final viable access-management path.
- Validate real target/state conditions in Policies and Actions, use safe audit, and treat these high-impact capabilities as powerful permissions to assign deliberately.
- Role `name` uniqueness/valid syntax must be validated, including the `web` guard; reject reserved names and collisions as specified by the Identity feature spec.
- Validate custom role names for acceptable length, nonempty content, uniqueness and safe display; do not automatically translate them. Permission aliases remain catalogue metadata, not Role form fields.

## 7. Active User and Active Role: Every Protected Request

Spatie does **not** enforce a custom `roles.is_active` flag by default. A disabled role must not continue authorizing a logged-in user.

**Required order**

```text
1. auth:sanctum
2. fresh active-account and current single-role/active-role verification
3. feature permission check (Spatie/Laravel authorization)
4. Policy/scoped record visibility
5. owning read operation / Action, including current business invariants
```

- At login, reject a disabled User or User assigned to an inactive/no role, with the authentication flow's safe Arabic error contract.
- For **each protected API request**, ensure the authenticated account is currently active and its **single** currently assigned role is active; an extra lightweight DB read is acceptable in V1.
- Do not trust stale Eloquent relations carried over from other work, JWT-like client claims, user-supplied permissions, the frontend's last permission list, or Spatie cache as proof that `is_active` is true.
- An authenticated request without an active Role returns **`403`**; a genuinely unauthenticated request returns **`401`**. Keep the shared JSON failure envelope.
- Revocation/deactivation is effective for the **next newly authorized request** once the change is committed and permission state coherently refreshed; in-flight requests cannot be retroactively canceled. Business-critical Actions still re-check their own authority/state at their trusted execution boundary where appropriate.
- Never assign an inactive role to a new user. Existing User-role links are **preserved** when the role becomes inactive; **do not automatically reassign** users.
- **Approved F04 D08 (2026-11-26):** on active-to-inactive Role transition, atomically increment every assigned User's credential generation once and invalidate authoritative sessions, including inactive Users. Preserve User activation and role assignment; repeat disable/re-enable does not increment versions again. Reactivating a role restores only its currently granted permissions, never prior sessions or an implicit managerial bypass.
- Active/inactive roles remain visible to properly authorized administration/audit views; ordinary assignment selectors should return only eligible active roles.
- Register the custom active-staff-role middleware once in the Laravel 12 middleware configuration. Its public alias may be `staff.active` or a documented equivalent; test middleware ordering against installed Spatie middleware.

## 8. Permission-Controlled Manager and Last Manager Protection

**No `Gate::before()` super-admin shortcut.** The `manager` role is subject to normal explicit Spatie permission membership, Policies and business rules.

- An initial `manager` role is seeded with **all approved first-install technical permissions explicitly**. Multiple Users may hold `manager`; role-name membership is not an authorization bypass.
- A new technical permission does **not** automatically attach to `manager` in a running installation. Granting it is a reviewed, auditable deployment/admin decision.
- A Manager without `finance.refunds.create` must not refund; a Manager who has it still cannot refund more than is refundable.
- A successful `can()` is not a replacement for Policy record visibility, current state, or Action invariants.

### 8.1 Protect the last viable administrative path

**Approved:** never allow a committed change to leave **zero viable administrators capable of managing staff access**.

A **viable administrative User** must meet **all** of the following:

1. A usable, active staff User account, not disabled/deleted.
2. Exactly **one assigned active Role**, with no invalid direct grants or multi-role anomaly.
3. Effective membership, through that Role, of **all six approved essential technical permissions**:
   - `identity.users.view`
   - `identity.users.create`
   - `identity.users.update`
   - `identity.roles.index`
   - `identity.roles.show`
   - `identity.roles.update`

**Role name does not matter.** An active User in `manager`, an eligible custom Role or any other active Role can count if its effective permissions include **all six**. Having fewer than six does not count. `identity.users.delete`, `identity.roles.delete` and `identity.audit.view` are **not** required for this viability check. This is not a new super-admin grant; each action still requires its own explicit permission and business checks.

**Prohibited outcomes include:**

- Disabling or deleting the final viable administrative User.
- Reassigning that User to a Role without all six capabilities.
- Disabling an assigned custom Role when doing so would remove the last viable path (seeded `manager`/`reception` cannot be disabled under their separate invariant).
- Revoking any of the six essential grants from a Role if that strands every viable administrator, including through indirect changes affecting several Users.
- Replacing Role grant mappings during deployment/seed updates or bypassing the invariant through another Action, CLI operation, bulk write or racing update.
- Treating protected User ID 1 as automatically viable merely because it cannot be disabled/deleted: its effective six permissions must still be checked.

**Concurrency and transaction requirements**

1. All mutations that can change viability use one consistent InnoDB transaction/locking strategy. A mere `COUNT(*)` or `exists()` before editing is **not race-safe**.
2. **Do not create a dedicated `identity_access_guard` table or any extra lock/sentinel table** (explicit user simplification, 2026-11-25). For administrative-access mutations, serialize on an **existing, reliably present Identity row** using `SELECT ... FOR UPDATE` (for example, the never-deletable seeded `roles` record with `name=manager` and `guard_name=web`), then lock any affected User/Role/assignment rows in a consistent order. This shared lock is for **serialization only**, and **does not** make the `manager` role a privileged bypass or exclude viable Custom Role users. Define the exact approach/order during planning and validate concurrent writes with real MySQL 8.
3. **Recompute viability inside the lock**, using effective current permissions and active states, then perform the requested mutation atomically; **include mandatory audit writes in that transaction once the deferred Audit feature exists**.
4. Reject the command when it would remove the last viable administrator. Use an expected **`409 Conflict`** and safe Arabic business message for an authorized request conflicting with this state invariant.
5. Any deadlock retry must restart the **entire rollback-safe Action/transaction**, never continue after partial effects, and must follow Database/Error Handling Standards.
6. Recheck the invariant when the final operation actually executes; do not rely only on Form Request validation or a prior UI confirmation.
7. Test two Managers concurrently disabling/reassigning each other, simultaneous account/role deactivation, and racing removal of critical permissions.

**Important:** Last Manager Protection is a safety invariant, **not** a super-user mechanism. A person without the required current authorization cannot modify administrative access merely because they are the last Manager.

### 8.2 Initial bootstrap and manual recovery

- Seeders create the approved `manager`/`reception` roles, fixed catalogue and reviewed first-install grants; a **separate secure, one-time Artisan command** creates the founding administrative User. **The `users` table must be completely empty before any bootstrap run**: if a User of **any ID**, active state or Role already exists, reject without mutation. On success the first account must have **`users.id=1`**, otherwise rollback/fail safely (an empty table does not guarantee the next auto-increment is 1). Do not overwrite/reset existing accounts, silently reset auto-increment state or create a second founder. Concurrent bootstrap runs must not each provision an account; the exact InnoDB serialization is defined in the plan.
- **Bootstrap readiness — approved option A:** The separate bootstrap command must validate that the `manager` and `reception` seeded active Roles, all ten approved Identity permissions and the explicit initial Manager Role grants are **already present and valid** with `guard_name=web`. If any prerequisite is missing/inconsistent, the command **fails without modifying any data** and instructs the operator to run the reviewed additive role/permission seeders beforehand (or follow a separately approved correction for existing customized grants). **No seeder is called from bootstrap and bootstrap never silently creates or repairs Role/Permission/grant records.**
- No demo/hardcoded credentials in code or seeders; running seeders again must not change credentials or grants.
- If all administrative credentials are lost, the technical owner handles **controlled manual server-side recovery** with secure password hashing and documented audit evidence. **No automated emergency recovery command, public Forgot Password endpoint or hidden Manager-reset API** is part of V1.
- `manager` and `reception` are always active and cannot be deleted/deactivated. The invariant also protects any last viable administrative grant path from being removed.

## 9. Sanctum, Guard and Authorization in Modules

Use **`auth:sanctum`** to authenticate API requests, with Spatie roles and permissions consistently using **`guard_name = web`**.

- Pin and verify the installed versions of Laravel 12, Sanctum, Spatie Laravel Permission and `nwidart/laravel-modules` before copying snippets.
- Ensure the staff User and any custom Spatie Role/Permission models resolve the `web` guard consistently (e.g., documented `$guard_name` / default guard handling as supported by the installed package).
- Configure `config/permission.php` consistently, and register Spatie middleware aliases in Laravel 12 where needed.
- Do not create duplicate technical permission rows under both `api` and `web` for the same staff system.
- **Sanctum browser transport is approved as stateful Cookie/Session**, not Bearer-token SPA authentication. Use CSRF, session regeneration/invalidation, 12-hour inactivity expiry, and the approved JSON error mapping. Exact cookie domain, allowed frontend origins, session store and CSRF `419` implementation details are decided and tested in the Auth feature plan.

**Illustrative route (not a finalized endpoint definition):**

```php
Route::middleware([
    'auth:sanctum',
    'staff.active',
    'permission:sessions.close,web',
])->post('sessions/{session}/close', [SessionController::class, 'close']);
```

**Responsibility separation**

| Mechanism | Responsibility |
|---|---|
| `auth:sanctum` | Authenticate the staff User |
| Active account/role middleware | Enforce fresh account status, **one assigned role**, and role activation |
| Spatie permission middleware / `can()` | Authorize a general technical capability |
| Laravel Policy / scoped query | Authorize this **particular** child, employee, session, payment, report or other record |
| Action `execute()` | Enforce current-state business rules and transactions; additional trusted actor authorization when invoked outside the normal HTTP route |
| Explicit API Resource | Return only authorized fields, including technical names / aliases as approved |
| Select Provider | Authorize each key, bound query, option eligibility and visibility |

- Every protected GET, mutation, report, attachment/download, export, dashboard and dropdown needs appropriate permission/visibility enforcement.
- Denied **feature-level capability** → `403`. Unseen/nonexistent **particular record** → consistent scoped `404` to avoid leaking existence. Do not mask unexpected failures as `404`.
- Select Registry is an explicit exception: authenticate, validate requested keys, authorize **every key**, then execute. Any forbidden Select key → **`403` for the whole request**, unknown key → **`422`**; no partial options.
- Do not bypass authorization when a business Action is triggered from another Action, an internal command or a future alternate entrypoint.
- Do not treat displaying or hiding a frontend button as security.

**Select label example (conceptual)**

```json
{
  "success": true,
  "message": "",
  "data": [
    {
      "label": "roles",
      "options": [
        { "value": 1, "label": "المدير" },
        { "value": 2, "label": "الاستقبال" }
      ]
    }
  ]
}
```

The outer `label` is the **technical Select key**. For role options, `value` is the authorized role ID and `label` comes from stored `roles.name`. Return stored names unchanged; do not automatically translate them. For permission options, the owner may use technical `name` as `value` and stored `alias_name` as `label` per the agreed Select contract.

## 10. Permission Changes, Cache Coherence and No Realtime Sync

**Approved: Spatie native cache + fresh authorization.** No custom RBAC cache service is needed in V1.

- For role permissions, use Spatie's `syncPermissions()`, `givePermissionTo()` and `revokePermissionTo()` (on a **Role**), not raw pivot SQL.
- For user role replacement, use `syncRoles()` (on a **User**) within an authorized Action. Reload stale models/relations when needed; do not rely on an old role collection or cached client permissions.
- Spatie normally invalidates relevant permission cache on its own supported role-permission mutation APIs. Confirm exact behavior for the installed version and chosen Laravel cache driver.
- `roles.is_active` is a custom field and is **never** enforced solely by Spatie's permission cache. Perform the fresh active-role check on each protected request.
- Avoid clearing cache **only before** a DB commit: an overlapping request can repopulate it with stale permission data while the transaction is uncommitted. Coordinate a **synchronous, post-commit cache invalidation/refresh** when needed; verify actual installed-package callback behavior.
- For permission removals/revocation, the application must not report a reliable effective update while a stale cached grant is still knowingly usable. Handle post-commit invalidation failures as **uncertain authorization state requiring safe operational reconciliation**, not as an excuse to re-run a committed mutation blindly.
- Test two independent requests/connections changing or checking grants during/after a transaction; ensure subsequent authorized requests use the committed assignment/state.
- Use the existing Laravel cache store compatible with cPanel; **no Redis requirement**. Check file-cache permissions/configuration in deployment tests.
- No `permissionsVersion` field/header, client polling, WebSockets, broadcast invalidations, per-user permission snapshots, or frontend synchronization protocol in V1.
- The frontend may update the displayed permission list during its normal profile/read flow, but backend authorization **never** relies on frontend refresh.

**Concurrency caveat:** Transactions/locks protect mutable database state. They do not automatically make the *cache* transactional. A combined DB-and-cache approach must be tested as a coherent authorization lifecycle.

## 11. Seeders: Additive and Non-Destructive

**Approved: module-owned catalog + Identity-controlled, idempotent database seeding.**

**First install only**

1. Collect the approved technical permission catalog from the owning modules.
2. Create the missing permissions with `guard_name=web` and a meaningful `alias_name`.
3. Create the seeded `manager` and `reception` roles with `guard_name=web` and `is_active=true`; Feature 04 seeders no longer write role aliases.
4. Apply an **explicitly approved** initial role-to-permission mapping for a fresh installation: `manager` receives all approved at-install technical permissions, while `reception` receives only the approved day-to-day operational set. Do not imply automatic future grants.
5. Provision the initial administrative User through its independently secured bootstrap flow. Do not seed plaintext/demo credentials into production.

**Every later routine deploy/reseed**

- **Only add missing** technical permission definitions and missing required seeded Role records; do not duplicate existing rows.
- Use `firstOrCreate()` or another repeatable insert-if-missing strategy keyed by technical `name + guard_name`, with database uniqueness.
- **Do not overwrite any existing permission `alias_name`**, custom Role names/metadata, active status, or user-modified role-to-permission mappings through routine seed runs.
- Do **not** automatically attach every new permission to `manager` or `reception` on a running installation. Approve and execute sensitive grant updates explicitly, with authorization, audit and Last Manager Protection.
- Do not silently turn an inactive seeded role back on at redeploy.
- Never detach previously selected permissions, delete definitions, rename keys or reset custom roles during ordinary catalog seeding.
- A deliberate existing-definition rename/alias correction or explicit change to role grants requires a **separate reviewed upgrade/action plan**, migration/compatibility analysis and required audit behavior.
- All permanent/immutable unique definitions are version-controlled. Arbitrary technical permission creation from the frontend is forbidden.
- Seeders use Spatie APIs for role assignments and permission changes; do not manually alter package pivots without a justified reviewed migration.
- Ensure cache coherence after a successful approved seeding or migration as required by the installed Spatie version.

**Conceptual insert-only definition**

```php
Permission::firstOrCreate(
    [
        'name' => 'sessions.close',
        'guard_name' => 'web',
    ],
    [
        'alias_name' => 'إغلاق الجلسات',
    ],
);
```

This shows a **single definition**, not an approved automatic assignment to `manager` or `reception`.

## 12. Activation, Conditional Deletion and Historical Integrity

**Approved: protected seeded roles and controlled hard deletion of unassigned custom roles.**

- Seeded `manager` and `reception` are **always active** and cannot be deleted or deactivated, even when no Users are currently assigned.
- Custom roles may be deactivated/reactivated by an authorized actor using `identity.roles.update`, subject to Last Manager Protection. A deactivated role cannot be newly assigned and its currently assigned Users lose protected access on subsequent requests; **do not auto-reassign them**.
- Hard deletion through `identity.roles.delete` is permitted **only** for a custom role with **no assigned Users at all**, including inactive accounts. A linked role fails with `409 Conflict`. Use FK/lock-safe enforcement so creation/assignment cannot race the delete. Deleting a role may clear its role-permission pivots but **must not delete underlying Permission definitions**.
- Custom-role reactivation restores only the currently granted permissions, not historical or implicit grants. `roles.is_active`, permanent role names and unique custom-role renames remain guarded by the owning Action.
- **User hard deletion** is permitted with `identity.users.delete` only for a non-founding account without important historical/operational links; otherwise disable the account. User ID 1 is never deletable or deactivatable. Preserve financial, attendance, cashier and administrative history; do not use cascading deletes to erase protected business facts.
- A historical audit event for account creation alone is not an unconditional deletion blocker. Preserve interpretable actor/target event snapshots without retaining credentials. The detailed audit-FK/deletion plan must be tested before implementation.
- Keep permanent `role.name` stable; authorized custom-role names are editable without changing ID or pivots, and permission aliases are catalogue metadata. Spatie pivots describe **current** role/permission state, not historical grants; the append-only audit retains before/after evidence.
- A future privacy purge/anonymization procedure is separate from V1 CRUD and cannot silently erase protected records.

## 13. Complete RBAC Audit and Security Logging — deferred implementation

**Scheduling exception approved 2026-11-25:** **Audit Logs implementation is postponed beyond Feature 01**. Identity Core & RBAC Foundation will **not** create a shared audit table, audit writer, audit-read API or Audit persistence tests. The future target design is **one shared append-only audit log**, with module-scoped read access; it requires a later approved feature/spec. `identity.audit.view` can exist as a seeded technical permission while it has **no live endpoint**.

**Full RBAC Change Audit + Restricted Read Access remains the future policy**, not evidence of present enforcement. **Until the deferred Audit is built, successful sensitive mutations (including founding-user bootstrap) are not covered by a durable central change-audit trail**, cannot be completely reconstructed retroactively, and must **not** be reported as audited. Revisit this release risk before allowing real-world admin workflows. Permission checks, founder safeguards, transaction locks, rate limits and other security constraints are **not** deferred.

**Once the separate Audit feature is implemented, audit every actual successful change** affecting:

- Creation of roles, custom-role name edit, activation/deactivation.
- Addition/removal/replacement of a role's permission assignments.
- Assignment/replacement of a user's single role.
- Enable/disable of staff accounts or access-related user state, permitted hard deletion of unused Users or custom Roles, and password changes (event metadata **only**, never values/hashes).
- Explicit migration/seeding access grants or security-sensitive administrative changes where a human/system actor needs attribution.
- The Last Manager Protection-sensitive changes, whether successful or blocked, with blocked attempts considered for **separate security logs**, not fake successful audit entries.

**Required safe fields, when applicable**

| Field | Meaning |
|---|---|
| `actor_user_id` | Authenticated operator or explicitly distinguished system/deployment actor |
| `action` | Stable technical event name, e.g. `permissions.updated` |
| `target_type` / `target_id` | Affected Role or User |
| `before` / `after` | Minimal relevant previous/new role IDs/names, permission names, permission alias or activation values |
| `reason` | Approved operator reason when relevant/required |
| `created_at` | UTC event time |
| `request_id` | Safe linkage to `X-Request-ID` when available |

**Rules**

1. **After Audit delivery**, role/permission/user access changes and required critical audit evidence must complete **atomically in one MySQL transaction**; a failed mandatory audit write rolls back the change. **Before delivery**, the business mutation itself must still be transaction-safe and preserve the last viable administrator invariant, but no central audit evidence is created.
2. Do not log the entire User model or raw request: exclude passwords, tokens, session/CSRF headers, financial payloads, children's details, guardian contacts and unrelated payroll data.
3. Preserve the **technical identifiers** in history even if display aliases are later edited.
4. RBAC audit records are **append-only** in normal app flows; authorized users may read them with a dedicated capability, illustrative `identity.audit.view`, but do not edit/delete the records through the dashboard.
5. Apply query limits/pagination and data minimization to audit listing and exports; do not reveal private unrelated staff data through broad includes.
6. Ordinary GET, `can()`, successful routine Login and Logout are **not administrative change-audit events**. Suspicious or denied access-management/authentication attempts may create **selective security logs** under Error Handling Standards, but must **not** appear as successful change audits.
7. Do not use the audit log instead of Spatie's current role pivots, and do not use error logs as change history.
8. Cache refreshes/logging of permission-cache diagnostics do not represent successful role/permission changes.
9. **In the later Audit feature**, test rollback of a sensitive mutation when mandatory audit persistence fails, plus successful before/after history for each RBAC mutation category; **not a Feature 01 acceptance test**.

**Illustrative mutation transaction outline**

```text
Authorize actor + target + requested capability changes
  -> start transaction
  -> lock shared admin-viability point where required
  -> lock affected account/role rows in consistent order
  -> re-check current authorization and Last Manager invariant
  -> update using approved Spatie role/permission APIs
  -> once deferred Audit feature is implemented, persist required RBAC audit event
  -> commit
  -> ensure coherent post-commit Spatie cache handling
```

## 14. API and Error Handling Contract

Keep all details of [API Standards](api-standards.md) and [Error Handling Standards](error-handling-standards.md) intact.

**General status semantics**

| Case | Status |
|---|---|
| Unauthenticated | `401` |
| Authenticated, missing required feature permission | `403` |
| Authenticated but account/role inactive or role absent | `403` for protected API access |
| Record missing / not visible to caller | Scoped `404` with non-disclosing body |
| Authorized admin mutation would remove last viable Manager | `409` expected business-state conflict |
| Invalid client payload / invalid option | `422` |
| Unexpected failure | Safe Arabic `500`; no SQL or private details |
| Temporary established service unavailability | `503` per Error Handling Standards |

**Examples (illustrative only)**

```json
{
  "success": false,
  "message": "ليس لديك صلاحية لتنفيذ هذه العملية.",
  "errors": []
}
```

```json
{
  "name": "sessions.close",
  "aliasName": "إغلاق الجلسات"
}
```

- Error envelope remains `success=false`, Arabic `message`, and `errors` without `data`; optional stable business code belongs in `errors.code` only.
- Every successful GET and successful login/logout uses `"message": ""`; do not fill with default translated text.
- Public field names use `camelCase`; the permission database `alias_name` is serialized as `aliasName` when included. Role Resources omit `aliasName` after Feature 04.
- User-entered custom role names and stored permission aliases are returned **as recorded**, without auto-translation.
- Role/permission queries, audit lists and Select Providers require scope-aware authorization and explicit Resources.
- The backend never trusts a client-supplied role, permission list or "is manager" flag as proof of access.
- Always apply Select Registry's `403` all-or-nothing rule for a forbidden requested select and `422` for an unknown key.
- Sensitive operations and exports must not leak `users`, child or payroll details through accessible responses.
- Preserve the existing `X-Request-ID` response-header behavior and safe request-logging boundaries.

## 15. PHPUnit RBAC Security Matrix and MySQL Concurrency Tests

**Approved: shared Identity security tests plus each module's authorization regression tests**, run with PHPUnit and actual MySQL 8 where uniqueness, locking or cache race behavior matters.

| Test area | Required assertion |
|---|---|
| Baseline authentication | No authentication returns `401` JSON |
| Role missing | Authenticated User with no role is denied protected access |
| Active-account check | Disabled User cannot log in/continue protected requests |
| Active-role check | Role disabled **after login** causes next protected request to fail `403` |
| Re-activation | Restores only actually granted permissions |
| Role assignment | `syncRoles()` replaces old role; no stale second assignment |
| DB uniqueness | MySQL unique index rejects two `model_has_roles` rows for the same User/model type |
| Direct-grant invariant | No staff direct permission assignment; anomalous grants detected/denied or safely migrated |
| Guard | `auth:sanctum` plus Spatie `web` guard works; no GuardDoesNotMatch in normal flows |
| Manager | `manager` cannot invoke missing permissions; no `Gate::before()` bypass |
| Capability vs business rule | Authorized User still cannot perform invalid refund/timer/finance transition |
| Record scoping | Unauthorized sensitive record `404` as specified; feature permission `403` |
| Select Registry | Unknown key `422`; any forbidden key `403` with **no partial options** |
| Role management | Only authorized actor can manage each eligible Role/permission set |
| Administrative grants | `identity.roles.update` may grant/revoke and `identity.roles.store` may grant any existing approved web catalogue Permission (including not personally held); `identity.users.update` may manage other Managers except User ID 1; test Last Manager safeguards |
| Last viable administrator | Any active single-Role User (including custom Roles) with all six essential effective permissions counts; no concurrent mutation may leave zero viable paths |
| True concurrency | Two independently overlapping MySQL transactions cannot remove two final Managers |
| Admin-grant race | Concurrent role permission removals honor the same serialization invariant |
| Cache coherence | Role permission revocation during an active session is enforced on subsequent requests |
| Cache/commit overlap | No known stale grant persists due to pre-commit invalidation and a racing reader |
| Seeder re-run | Repeated runs yield no duplicates; existing aliases, role grants and active state unchanged |
| New deployment permission | Added to catalog but not implicitly granted to current roles |
| Display | Role `name` returned directly and used as Role Select label; permission `name` remains technical and `alias_name` returned as `aliasName` / Permission Select label |
| Audit write (deferred feature) | After Audit is implemented, RBAC changes log actor, target and minimal before/after; **not built in Feature 01** |
| Audit atomicity (deferred feature) | After Audit is implemented, failed mandatory audit write rolls back the sensitive change |
| Audit read (deferred feature) | Separate permission required when endpoint is implemented; seeded `identity.audit.view` does not expose an endpoint in Feature 01 |
| Conditional deletion | Seeded roles cannot be deleted/deactivated; only unused custom roles and historically unlinked non-founding Users may be hard-deleted, preserving audit and protected history |
| API shape | Safe Arabic failures, `camelCase`, status/codes, empty messages where required |

**Implementation/testing rules**

1. Identity owns foundational RBAC tests; each new module tests its own permissions, Policies, sensitive record scoping, Select Providers and protected Actions.
2. For true race testing, run **independent MySQL connections/processes with overlapping transactions**. Sequential tests or SQLite are **not** evidence of actual lock behavior.
3. Avoid automatic PHPUnit DB transaction wrappers when they hide/serialize concurrency rather than exercising real overlapping sessions. Document test harness concurrency setup.
4. Check both results **and effects**: no unauthorized persisted changes, no forbidden extra roles, no data leaks, no missing audit events, no stale effective permission after completed revocation.
5. Test initial install versus repeated deploy seed semantics with real role customizations.
6. Include a code-review gate for any newly added endpoint to ensure intended capability, correct Record Policy, Business Action restrictions and test coverage.
7. Run approved PHPUnit tests, MySQL 8 integration suites and `vendor/bin/pint --test` on pull requests to `develop` **once GitHub Actions and app code are implemented**. Do not claim those checks already exist or have passed.
8. If a production environment differs in cache store or package versions, rerun relevant Spatie guard/cache tests against that environment's configuration.

## 16. Implementation Checklist

- [ ] Use **Laravel 12** and compatible installed versions of Sanctum, Spatie and nwidart.
- [ ] Identity owns the one canonical User model; Staff User and HR Employee remain separate.
- [ ] Use `auth:sanctum` with approved stateful Sanctum Cookie/Session transport, CSRF and 12-hour idle expiry; configure actual frontend origins in Auth plan.
- [ ] Keep all Role/Permission `guard_name` values `web`.
- [ ] Maintain native `permissions.alias_name` and `roles.is_active`; remove legacy role alias in Feature 04 using reviewed forward migration, preserving existing relationships.
- [ ] Verify and add compatible single-role database uniqueness; no Teams in V1.
- [ ] Protect user-role updates with a transaction, lock and `syncRoles()`.
- [ ] Never grant User-direct permissions, and detect legacy `model_has_permissions` anomalies.
- [ ] Seed `manager`/`reception` plus catalog once without production demo credentials.
- [ ] Make repeated seeders additive, preserving existing `alias_name`, active states and role grants.
- [ ] Do not auto-grant new permissions to `manager` on running systems.
- [ ] Use no `Gate::before()` admin bypass; all capabilities explicit.
- [ ] Active User/Role verified at login and on each protected request with **fresh state**.
- [ ] Protect the **last viable administrator** under common lock/transaction on every relevant mutation.
- [ ] Enforce User ID 1 protection, owner-only permitted updates, nondeletable/nondeactivatable state, own password change via Profile, and safe authorized grants with Last Manager Protection.
- [ ] Keep seeded roles active and nondeletable; permit audited hard deletion of only custom Roles with zero User assignments (including inactive accounts), plus conditional deletion of historically unlinked non-founding Users.
- [ ] Permissions and aliases never replace technical names in authorization.
- [ ] Register and test Spatie middleware guards/aliases for actual installed Laravel version.
- [ ] Use native Spatie mutation methods, fresh roles and safe post-commit cache coherence.
- [ ] **Deferred separate Audit feature:** implement append-only RBAC change evidence, atomic audit writes and tests; **not part of Feature 01**.
- [ ] **Deferred separate Audit feature:** expose scoped audit reads under `identity.audit.view`; **not part of Feature 01**.
- [ ] Protect records, reports, exports and strict Select Registry providers.
- [ ] Preserve Arabic/error/`camelCase` API contract and stored aliases.
- [ ] Cover all approved security matrix cases and actual MySQL 8 concurrency in PHPUnit.
- [ ] Confirm CI checks really exist and ran; do not infer verification from this documentation.

## 17. Deferred Details, Cross-Standard Alignment and Change Rules

**Identity/RBAC feature specification still must define:**

1. Exact technical permission list and its owner module; initial `manager`/`reception` assignments.
2. Exact request/Policy enforcement for the **already approved** broad `identity.roles.update` catalogue-grant scope, Users CRUD scope, and protected User ID 1; confirm target changes with audits and Last Manager Protection.
3. Implement the **approved six essential access-management capabilities and Role-name-independent viability predicate**; specify shared InnoDB locking; secure one-time first-admin bootstrap at User ID 1 and a documented **manual technical recovery** runbook (no extra recovery command).
4. Request/Resource/Action contracts for users, roles, role assignments, audits and account status; no endpoints invented in this standards file.
5. Actual Spatie package version and published table structure; index/migration plan, guard settings, model class/trait and Teams configuration.
6. Exact locking serialization strategy and MySQL test harness for parallel mutations; approved failure messages/machine codes.
7. Cache-driver configuration, post-commit invalidation, operational recovery for uncertain change state.
8. Exact deployment origin, secure Cookie/CSRF/CORS configuration, `419` mapping, session store and cross-session revocation for the **approved Sanctum Cookie/Session transport**.
9. Which administrative changes require a reason or additional confirmation; audit retention/access implementation.

**Related-document ownership**

| Document | Owns |
|---|---|
| [Backend Architecture](../01-architecture/backend-architecture.md) | Identity boundaries, deployment constraints, module orchestration, required security |
| [Code Standards](code-standards.md) | Actions for writes, Policy/Request/Resource structure, coding conventions, testing gates |
| [API Standards](api-standards.md) | HTTP routes/conventions, `camelCase`, response envelopes, Select contract |
| [Database Standards](database-standards.md) | General MySQL uniqueness, transactions, history, migration discipline |
| [Error Handling Standards](error-handling-standards.md) | 401/403/404/409/422/500/503 behavior, safe logs, request IDs |
| [Localization Standards](localization-standards.md) | Stored role names and permission `alias_name`, translation boundaries and frontend labels |
| **This document** | Cross-module identity permissions, guards, roles, cache, audit, seeders, tests |
| Identity/RBAC feature spec | Concrete role/permission assignments, selected target-state/Policy details, endpoints, bootstrap/manual recovery |
| Owning module feature specs | Individual permissions, record constraints and regression tests |

**Review/approval boundary**

- This is a **normative cross-module V1 standard**, not an implemented migration, middleware or automated test suite.
- Do not transplant Tabaa/printing-specific roles (`designer`, `production_supervisor`, `delivery`), Laravel 13 assumptions, `alias` naming, or **unreviewed cookie configuration copied from another SPA**. Kids Area explicitly approved Sanctum Cookie/Session; actual origins and CSRF behavior are specified for this deployment.
- Any change allowing multiple roles **per User**, direct User permissions, Teams, **deleting assigned or seeded roles**, automatic manager bypass, multilingual alias storage or background permission sync requires a separate design review and updated related standards. **Multiple Users holding `manager` and deletion of unassigned custom roles are already approved.**
- New role/permission definitions and security-sensitive exceptions require an explicit feature or architecture decision, not an accidental implementation shortcut.
