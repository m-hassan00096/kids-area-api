# Identity Module — Module Reference

> **Status:** Approved V1 functional decisions, documented for review; individual feature specs, plans and tasks are not yet authored.
> **Path:** `docs/03-modules/identity.md`
> **Scope:** One Kids Area location; Laravel 12 API-first modular monolith, MySQL 8 / InnoDB.
> **Owning module:** `Modules/Identity`.
> **Related standards:** [Backend Architecture](../01-architecture/backend-architecture.md), [API Standards](../02-standards/api-standards.md), [Permissions Standards](../02-standards/permissions-standards.md), [Database Standards](../02-standards/database-standards.md), [Error Handling Standards](../02-standards/error-handling-standards.md), [Localization Standards](../02-standards/localization-standards.md), and [AI Workflow Rules](../02-standards/ai-workflow-rules.md).

## 1. Purpose and boundaries

Identity owns staff accounts, authentication, profile password changes, role and permission administration, initial-access provisioning, and access-change audit evidence. The system is an **internal staff application**. Each staff operator has an individual account. Children and guardians do not log in in V1.

The authenticated staff model is `Modules/Identity/app/Models/User.php`, configured as the canonical Laravel auth User with Spatie `HasRoles`. Do not introduce a parallel `App\Models\User` identity source. An HR `Employee` is a distinct entity owned by Employees; employment status, job title, contract or attendance does **not** automatically confer application access.

Identity owns authorization infrastructure, but **each business module owns its permission definitions and its own record/state authorization rules**. Identity does not directly edit attendance, subscriptions, payroll, payments or cashier records.

V1 exclusions: multi-tenant/branch access scopes, shared operator logins, guardian/child authentication, multiple roles per User, direct User permission grants, automatic super-user authorization, impersonation, password-reset emails/SMS, publicly exposed recovery endpoints, Redis, queues, cron/scheduled authentication workers and additional custom RBAC engines.

## 2. Actors and access model

| Actor | Agreed access model |
|---|---|
| `manager` | One of two seeded technical roles. More than **one User may have this role**. The initial mapping explicitly grants all approved at-install permissions; no automatic super-user bypass. |
| `reception` | Seeded technical role with approved routine reception/attendance/cashier permissions only. |
| Custom-role operator | One active, configurable role, with an explicitly assigned subset or full set of the fixed permission catalogue. |
| First User (`users.id = 1`) | Protected founding administrative account; rules are tied to its immutable numeric ID, **not to the current role name**. |
| Technical deployer | May run the one-time initial provisioning command and perform an explicitly controlled manual recovery outside the normal application UI. |

A staff User holds **at most one persisted role**; any authenticated protected operation requires **exactly one assigned active role** and an active User account. All role memberships go through Spatie `syncRoles()`; the compatible single-role database uniqueness constraint is required. Spatie permissions use **one `web` guard**, and Users never receive individual direct permissions.

An effective technical permission authorizes an attempted action. Required record-state checks, valid target conditions, business constraints, and the last viable administrator invariant still apply. Do not use `Gate::before()` or a wildcard Manager bypass.

## 3. Authentication — approved business behavior

### 3.1 Login and passwords

- Login identifier is a **unique `username` plus password**, not email. Email is optional.
- Password minimum is **8 characters**. Mixed-case letters, special symbols and digits are **not mandatory**. A password such as `mans123456` is syntactically valid. Hash passwords with Laravel's supported secure password hashing; never store or return plaintext.
- No public Forgot Password, self-service email/SMS recovery or password-reset API.
- **Profile:** every authenticated User may change their **own** password by entering `currentPassword`, `password` and `passwordConfirmation`. The current password is never revealed.
- **Users editing:** `PUT /api/v1/users/{user}` may include a new password and confirmation for **another** User under `identity.users.update`; the target's current password is not required. Missing or empty password means **do not change** it.
- A User cannot bypass the current-password check for their **own** password through the Users update route; the self-service flow is the Profile endpoint. This also applies to User ID 1.
- Successful password changes invalidate prior authenticated sessions of the affected User. Exact treatment of the initiating current session and browser reauthentication is specified and tested in the Authentication feature plan.
- Passwords, password hashes, session cookies, credentials and raw password-bearing request bodies are excluded from responses, audit and application logs.

### 3.2 Login protection and account states

- Five failed login attempts within one minute trigger a **one-minute temporary throttle** (`429`); no permanent account lockout solely from failed attempts. Apply controls to the login identifier and request origin to reduce brute force without exposing account existence.
- Use the same safe Arabic error for unknown username and incorrect password; no account enumeration.
- Disabled Users, Users without exactly one role, and Users whose assigned role is disabled cannot log in.
- Every **protected subsequent request** rechecks fresh User and role activation and effective authorization; disabling a User or role takes effect on newly authorized requests after commit.
- Normal unauthorized HTTP behavior: unauthenticated `401`; authenticated but inactive/role-invalid/forbidden `403`. Use the shared JSON error envelope.

### 3.3 Sanctum Cookie / Session authentication

**Approved transport:** Laravel Sanctum **stateful browser Cookie/Session** authentication rather than browser Bearer-token authentication.

- Protected business APIs use `auth:sanctum` with the approved `web` guard and separate staff-active/role and permission checks.
- Frontend obtains CSRF protection via Laravel/Sanctum's `GET /sanctum/csrf-cookie` as part of the login flow; write requests use appropriate CSRF and credentialed browser requests.
- Login regenerates the session identifier; Logout invalidates the **current** session and its CSRF state.
- Session expiry is **12 hours (720 minutes) of inactivity**. An authenticated server-observed request refreshes activity; an idle open browser tab does not itself keep a session valid.
- Password changes invalidate earlier sessions for the affected User; any current-session exception must use a secure and testable reauthentication policy, not silently retain stale access.
- Use a session backend viable on cPanel with no Redis or workers; database-backed sessions are preferred but exact configuration and cross-session revocation procedure belong to the Auth plan.
- Enforce HTTPS, secure cookie flags, correct SameSite/domain scope, explicitly allowed stateful frontend origins, credentials-aware CORS, CSRF failure mapping (including `419` where applicable), and no HTML redirect for Laravel-handled API errors.
- **Domain names and exact Cookie/CORS settings remain deployment-specific**, to be configured when the frontend origin is known. They do not reopen the approved Cookie/Session transport decision.

### 3.4 Initial Manager bootstrap and loss of access

- Idempotent seeders create the fixed technical role/permission catalogue and starting `manager` and `reception` roles with approved initial explicit grants.
- A **separate, secure, one-time Artisan command** provisions the first active Manager User; do not hardcode or print any default password in a seeder or source file.
- **Approved bootstrap precondition:** the entire `users` table **must be empty**. If **any User row exists**, regardless of its ID, role or active state, the command must **reject the run without changing data**. It must never overwrite, reuse or reset an existing User, nor create a second founding account. Re-running bootstrap after the first successful provisioning fails safely; this differs from the intentionally repeatable permission/role seeders.
- Because special protection uses **`users.id = 1`**, bootstrap must verify that the founding account will in fact have ID 1, or **fail safely**. An empty table alone does not guarantee the auto-increment next ID is 1 (e.g. previously removed rows). The implementation must not silently reset an existing table's auto-increment state or write a different founding ID; the precise safe check/creation and concurrent-run serialization belong in the approved plan.
- Normal reseeding never resets passwords, activation, role aliases or live customized grants.
- If the last usable administrative credentials are lost, the system has **no separate automated emergency-recovery command or hidden API**. Operators contact the technical owner for a **controlled manual server-side recovery**, with safe password hashing and appropriate documented audit evidence. This does not create a new user-facing feature.

## 4. Staff User management

### 4.1 Account fields and username

Proposed account core, subject to migration-level type/length verification: `id`, `name`, `username`, optional `email`, optional `phone`, hashed `password`, `is_active`, `created_at` and `updated_at`. Role assignment is a Spatie relation, **not `users.role_id`**. JSON uses `camelCase`; database columns use `snake_case`.

- `username` is required, unique across **all retained accounts including inactive Users**, and case-insensitive after normalization; allowed characters `a-z`, `0-9`, `.`, `_` and `-`; length **3–50**.
- Authorized Users update may change a username if the unique constraint remains valid; record relevant historical change metadata.
- All newly created staff Users are **active immediately** (and receive one active role and an initial password).
- Email and phone are optional. Whether they are unique and their exact format constraints are feature-spec validation details, not assumptions about identity.
- User-list search covers name and username; filters include active state and role. Use approved server pagination (default 15, maximum 100).
- User select and role input cannot bypass server-side authorization; inactive roles are not eligible for new assignments.

### 4.2 Operations and permissions

| Method | `/api/v1` path | Permission | Behavior |
|---|---|---|---|
| GET | `/users` | `identity.users.view` | Authorized index, search, filters, pagination |
| GET | `/users/{user}` | `identity.users.view` | Authorized detail |
| POST | `/users` | `identity.users.create` | Create active User, one active role and initial password |
| PUT | `/users/{user}` | `identity.users.update` | Update basic fields, optional password, role, activation state |
| DELETE | `/users/{user}` | `identity.users.delete` | Delete only if protected historical dependencies and special rules permit |

**`identity.users.update` intentionally covers password reset for another account, role replacement, and activation/deactivation**; there are no separate update-role, change-status or administrative password-reset capabilities/endpoints. Only `identity.users.delete` is distinct from update.

The `PUT` is a full update of the documented mutable non-password fields; `password` is a deliberate optional exception. If omitted or empty it preserves the existing hash. Nonempty new passwords require confirmation and validation. Every sensitive change rechecks the current state and required invariants in its Action.

An actor with `identity.users.update` may update another Manager's account just like another User, **except User ID 1**, and is not blocked merely because the target has a high-privilege role. This permission must therefore be understood as security-sensitive when granting it. Direct self password reset through Users is disallowed; self password change uses Profile.

### 4.3 Protected founding User (`id = 1`)

**This rule is based on immutable numeric User ID, not the Manager role or username.**

1. **No actor may ever hard-delete User ID 1**, including its owner.
2. **No actor may deactivate User ID 1**, including its owner. The record must stay active.
3. **No different User may update User ID 1**, even if that actor holds `identity.users.update`, all explicit permissions, or `manager`. An unauthorized update returns `403`.
4. User ID 1 may edit their own ordinary data and, if possessing the required permission, role assignment, **subject to last-viable-administrator protection**. Unchanged `roleId` and `isActive=true` in a full PUT do not constitute forbidden changes.
5. User ID 1 can change their own password **only from Profile with their current password**, not from the Users reset flow.
6. API, Action and authorization tests protect this rule even when the frontend would hide or disable a control.

### 4.4 Conditional hard deletion

Hard delete is explicitly allowed for a User **only when no important operational/historical dependencies exist**. Examples that **block** deletion include posted receipts/payments, cashier shifts, important attendance/action history, sensitive administrative evidence or other relationships identified by their owning modules. For a blocked deletion, return `409 Conflict` with a safe Arabic message recommending account deactivation.

- The founding User (ID 1) can never be deleted. An authorized deletion of another Manager still cannot remove the **last viable administrative path**.
- Deleted User credentials and live session state are invalidated. No delete cascade may remove protected payment, attendance, shift, invoice, activity or audit evidence.
- A mandatory identity audit record **by itself** (such as the creation event) must not permanently prevent deletion of a User who performed no important operation. Preserve the event with durable historical actor/target identification, using a reviewed FK/snapshot scheme in the feature plan.
- Retain the relevant history and technical identifiers after deletion, without retaining unnecessary secrets. Every cross-module data owner defines which FK/reference protects its important records.
- Check dependencies, special protections and transaction-safe locking **at deletion time**. Do not rely on a pre-delete UI check or permit a concurrent referenced operation to disappear.

Deactivation remains the alternative for any otherwise nondeletable account. Deactivation retains the role/history, blocks the next protected request and does not automatically reassign the User.

## 5. Roles and permission catalogue

### 5.1 Role lifecycle

The seeded technical roles are **`manager` and `reception`**. Both are permanently present, **must remain active**, and **cannot be disabled or deleted**, even if unassigned. Their display aliases and explicit role grants may be edited subject to authorization and the last viable administrative path invariant.

Custom roles may be created, assigned to Users, edited, activated/deactivated and **hard-deleted if and only if they have zero assigned Users**, including inactive accounts. Role deletion removes its permission pivots, **not the shared Permission catalogue or historical audit**. Reject deletion of a used custom role with `409`. No auto-reassignment occurs when a custom role is deactivated.

Role technical `name` and `guard_name` are stable after creation; `alias_name` is the display name stored in MySQL. `roles.is_active` determines eligibility for new role assignments and protected access. A custom role can be disabled while assigned, but its Users then lose protected access until the role is reactivated or they are reassigned.

### 5.2 Role CRUD and technical capabilities

| Method | `/api/v1` path | Required capability |
|---|---|---|
| GET | `/roles` | `identity.roles.index` |
| GET | `/roles/{role}` | `identity.roles.show` |
| POST | `/roles` | `identity.roles.store` |
| PUT | `/roles/{role}` | `identity.roles.update` |
| DELETE | `/roles/{role}` | `identity.roles.delete` |

The `PUT` edits the permitted display data, activation for custom roles, and the role's selected Permission identifiers. All five role operations are **independently permissioned**. `identity.roles.update` allows the actor to grant/revoke **any predefined catalogue Permission** to a Role, **even when the actor does not personally hold that permission**, subject to the protected baseline roles and last viable administrator rule. There is no separate `identity.roles.manage` shortcut and no unrestricted Manager bypass.

These are intentionally powerful administrative permissions. Grant them explicitly, apply all security invariants, and record the required audit **after the deferred Audit feature is implemented**; no synthetic authority is derived just from the role's name. Before then, sensitive changes lack the central audit trail and must not be described as fully audited.

### 5.3 Permission definition, ownership and seeding

- Fixed technical permission names use stable lowercase English dot notation and the `web` guard; each owning module declares its approved definitions and initial Arabic aliases.
- Identity aggregates and **idempotently seeds** the catalogue, initial Role records and the **explicitly approved first-install** role-to-permission mapping.
- Initial `manager` receives **all approved permissions at first installation**, explicitly, not through a wildcard or unconditional Laravel Gate.
- Initial `reception` receives the separately approved **routine operating capabilities**, not sensitive administration by default.
- On later deploy/reseed, **add missing definitions only**; do not reset aliases, active flags, custom role grants, passwords or any existing role's permissions; new catalogue entries are **not automatically granted to existing manager/reception roles**.
- The UI may assign/revoke existing Permission records **through role editing only**. It cannot create, rename or delete arbitrary technical Permission keys, and direct User grants are unsupported.
- `permissions.alias_name` and `roles.alias_name` are persisted display text. Do not use aliases for authorization or introduce translation tables for them.
- Permission-cache coherence, post-commit revocation visibility and consistent Laravel/Spatie guard configuration are required and verified in the RBAC plan.

### 5.4 Initial reception scope

Reception is operational rather than administrative. The initial permission set, to be enumerated **by the relevant module feature specs** before seeding, covers:

- Register/view the required child/guardian data for reception workflows.
- Record required child/staff/trainer attendance, within owning module rules.
- Open and operate hourly sessions and their Start/Pause/Resume/Finish timers.
- Café/POS sales, invoicing, allowed collections, and the operator's own cashier shift.
- Create, renew and view monthly activity/nursery subscriptions; record installments/collections; **edit operational registration or group/schedule data** when capacity, attendance, delivered-session and payment integrity rules permit.

Reception does **not** automatically get refunds, sensitive pricing/financial corrections, HR contracts, payroll, unrestricted financial reports, Identity user/role administration or Identity audit access. The approved list is **not a substitute for real module-owned technical keys**; no speculative permission names or final mapping are invented here.

## 6. Last viable administrative path

**Multiple Managers are permitted.** Do not enforce a one-Manager-only constraint.

Every committed User/Role/permission update or deletion must leave **at least one active, usable User with exactly one active Role and all six effective essential access-management capabilities**: `identity.users.view`, `identity.users.create`, `identity.users.update`, `identity.roles.index`, `identity.roles.show` and `identity.roles.update`. **The Role name is irrelevant**: an eligible active custom-Role User counts just as an eligible `manager` User does. No additional `identity.users.delete`, `identity.roles.delete` or `identity.audit.view` permission is necessary to satisfy this viability invariant. Protect the last viable path even if User ID 1 remains physically present: a present but unauthorized or otherwise unusable founding account is **not** proof of viable administration.

Apply one consistent MySQL InnoDB serialization/locking strategy across mutations affecting this invariant, re-evaluate inside the transaction and persist the authorized change atomically. **After the deferred Audit feature is implemented**, its mandatory change event must be written within that transaction. Return `409 Conflict` when an authorized attempted change would strand administration. Test genuine races between managers, assignments, role status changes, grant revocations and deletions. No hidden admin bypass.

## 7. Identity Audit and security logging — deferred implementation

**Scope scheduling decision (2026-11-25):** The user explicitly **postponed Audit Logs out of Feature 01**. No `audit_logs` table, Audit writer, actor/target snapshot history schema, audit API, or Audit persistence/failure tests are part of Identity Core & RBAC Foundation. **Option A — one shared, append-only audit log for the entire system — remains the preferred approved future design direction**, with module-scoped read permissions. The exact later implementation and release timing must be reviewed before exposing sensitive management changes in a real environment.

**Security limitation until Audit is implemented:** User/Role/permission/bootstrap mutations performed before the Audit capability exists **will not have a reliable central change-audit trail**, and later installation cannot reconstruct their exact historical before/after evidence. Do not claim they were audited or silently reconstruct events. Current authentication checks, account protections, transaction/locking invariants and normal application/error security controls remain mandatory. Reassess this known gap before production administration is enabled.

When the separate Audit feature is implemented, Identity must synchronously record **actual successful sensitive changes** with actor/system identity, target identity, action type, UTC instant, request ID when available, and minimal safe before/after fields:

- User creation, meaningful metadata edits, password changed (event only; **never the password/hash**), role assignment, activation/deactivation and permitted hard deletion.
- Role creation, alias/activation changes, permission grants/revocations and deletion.
- First Manager bootstrap and approved privileged setup/grant changes.

**Once implemented**, required audit writes are **atomic** with their sensitive Identity mutation; failure of the audit write rolls the mutation back. Audit records are append-only to ordinary application roles and remain interpretable after a permitted User/Role deletion. Access to audit listing/details requires `identity.audit.view`. Until then, this capability is a reserved technical permission with **no active Audit read API**. There are no interactive audit create/edit/delete APIs.

Successful routine Login and Logout events **do not populate the administrative change audit**. Select suspicious authentication attempts and throttling patterns for privacy-safe **Security Logs** under Error Handling Standards, rather than creating fake access-change audit records.

Proposed read API (final filter/Resource definitions in the Audit feature spec):

| Method | `/api/v1` path | Permission |
|---|---|---|
| GET | `/identity/audit-logs` | `identity.audit.view` |
| GET | `/identity/audit-logs/{auditLog}` | `identity.audit.view` |

Authorized filters may include actor, target, event type and date range. Never return credentials, hashes, raw requests, session IDs or unrelated private staff/payroll fields.

## 8. Select Registry and authorization

Use the **existing central** `GET /api/v1/selects?selects=roles,permissions` contract, not an Identity-specific dropdown endpoint or a different envelope:

- The requested `roles` options for **assignment** contain only active eligible roles; the administrator's full role list can show inactive roles.
- Role option `value` is the role ID and `label` comes from stored `roles.alias_name`.
- Permission option `value` is the stable technical name (e.g. `identity.users.update`); `label` comes from stored `permissions.alias_name`.
- Any unknown Select key causes `422`; **any unauthorized requested key denies the entire request with `403`**, no partially returned options.
- Each Select Provider authorizes based on its purpose. A User-creation form needing role choices must not gratuitously require the independent permission to view the entire role-management screen.

## 9. HTTP contracts, errors and sensitive mutations

All module APIs use `/api/v1`, `camelCase` request/Resource fields, Arabic translated error/success messages and the single shared response envelope:

- Success JSON: `success`, `message`, `data`; every successful GET/login/logout has `message: ""`.
- Failure JSON: `success`, `message`, `errors`, with no extra `data`; field validation errors use `camelCase`.
- Typical statuses: `200` read/update/login/logout, `201` create, `401` no authentication, `403` missing permission/forbidden target/inactive auth context, `404` missing or out-of-visibility record, `409` protected business-state conflict (e.g. linked User/Role, last Manager), `422` invalid fields, `429` login throttling, and relevant CSRF `419` mapping as finalized in Auth.
- `PUT /users/{user}` accepts complete ordinary mutable fields and a **deliberately optional** `password` plus `passwordConfirmation`. No separate `/users/{user}/password` endpoint exists.
- Preserve `X-Request-ID` correlation for Laravel-handled requests. Write Actions/transactions own mutations; thin controllers, Form Requests, Policies and explicit Resources enforce boundaries.

## 10. Data integrity and security acceptance scenarios

The feature specs and plans must concretize migrations, payloads, role/permission inventories and actual installed package-version behavior. At minimum, test:

1. Username uniqueness/canonicalization under concurrent creation and update; inactive User remains reserved.
2. First Manager one-time bootstrap truly establishes **User ID 1** and **refuses any nonempty `users` table** (even a different/inactive ID) without changes. A second or concurrent bootstrap fails safely; an empty table with non-1 next ID fails rather than silently rewriting IDs. Routine reseeding independently preserves existing credentials, grants and aliases.
3. Cookie/CSRF handshake, session regeneration, correct credentialed browser flow, 12-hour **idle** expiry, Logout, and invalid session handling.
4. Wrong username/password indistinguishable in public Login error; five failed attempts/minute triggers one-minute throttle with no permanent lock.
5. Personal Profile password change requires current password; Users PUT missing/empty password never changes it; administrative reset for **another** User needs `identity.users.update`, not their old password.
6. Changing a User password invalidates appropriate old sessions; no hashes or secrets in Resources, audit or error logs.
7. User and role active-state changes block the very next newly protected request; roleless/multi-role/direct-permission anomalies fail closed.
8. User ID 1 cannot be deleted/deactivated by anybody, updated by another actor, or have their password reset by another actor; own permitted edits and old-password Profile change behave correctly.
9. More than one Manager is permitted; an active custom-role User holding **all six essential permissions** counts as a viable admin; any change leaving zero viable paths fails safely and race-free even if User ID 1 remains.
10. Users PUT changes role/status/password only as authorized; `identity.users.delete` stays independently permissioned.
11. Hard delete of an unlinked non-founding User succeeds with audit history preserved; linked User fails `409` and retains records; a creation-audit event alone does not incorrectly block deletion.
12. Seeded `manager`/`reception` cannot be deleted/deactivated; unassigned custom Role can be deleted; assigned custom Role (including inactive User assignment) cannot; deleting Role does not delete Permission definitions.
13. Role `index/store/show/update/delete` permissions work independently; `identity.roles.update` may grant predefined permissions not held by the actor, but cannot remove last viable management.
14. Concurrent grant revocation, role assignment/deletion, activation changes and the Spatie permission cache do not leave a committed forbidden grant usable in a subsequent request.
15. **When the deferred Audit feature is delivered**, mandatory audit-write failures roll back protected mutations and deleted actors/targets retain interpretable history; these tests are **out of scope for Feature 01**.
16. Select Registry request with unknown key returns `422`, forbidden key returns all-or-nothing `403`, and legitimate role/permission options use stored aliases.
17. API envelopes, Arabic safe errors, `camelCase` serialization, target authorization and DB foreign-key restrictions are consistent with shared standards.

**Verification gate:** PHPUnit (Feature/Integration), real MySQL 8 for locking/uniqueness/cache races, Laravel Pint, and GitHub Actions checks when configured. This document **does not assert that Identity implementation or its tests have been run**.

## 11. Planned feature decomposition and approval gates

Feature ordering is based on dependencies; each substantial feature gets separately approved `spec.md`, `plan.md` and `tasks.md` before code, in its own later branch/PR to `develop`:

1. **Identity Core & RBAC Foundation:** canonical User model, Spatie migrations and constraints, active role/account foundation, approved permission catalogue and seeders, secure first-manager bootstrap, and last-admin guard. **No Audit table, writer or read API in this feature.**
2. **Authentication:** Sanctum Cookie/CSRF, login, logout, me, Profile password, throttling, idle/session invalidation and tests.
3. **Users Management:** list/show/create/PUT/delete, special ID-1 protection, conditional deletion and status/role changes. Its dependency rules must remain safe even while the Audit feature is deferred; any later audit integration is separately planned.
4. **Roles & Permissions Management:** five independent Role capabilities, role CRUD, assignments, fixed catalogue editing, active flags, cache and last-admin race safety.
5. **Shared Audit Logs (deferred; separate approval):** shared append-only Audit storage and writer for authorized modules, Identity-scoped audit read API, filtering, historical display, atomicity and API/DB tests. **Not a prerequisite for drafting Feature 01**, but an explicitly acknowledged production-control gap until implemented; its exact implementation order before live administration must be revisited.

**Do not implement speculative employee, child, payment or attendance permissions within Identity.** Their owning specs approve exact technical names and initial Reception mappings. A later frontend implementation must finalize actual deployment origins/cookie settings without changing the approved Sanctum Cookie approach.

## 12. Open technical details (not new business decisions)

The subsequent feature specs/plans must verify the installed Laravel 12, Sanctum and Spatie package versions and define the exact table/index shape, unique username implementation, session-store strategy, all-session invalidation, CSRF `419` JSON mapping, Role technical-name validation, complete initial role-to-permission mapping, last-viable-essential permission set/lock strategy, and exact request/Resource/filter contracts. **The shared Audit table, writer, historical actor/target preservation model and its tests belong to the deferred Audit feature**, while future per-module protected deletion dependencies still need explicit planning.

These implementation details must respect the approved rules above. If an actual conflict with a previously approved standard emerges, update the owning source explicitly through the documented review process; do not silently loosen invariants.
