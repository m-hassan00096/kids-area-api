# Feature 03 — Users Management

> **Status:** APPROVED by user (2026-11-25), including all Avatar, search, and API contract review defaults.
> **Baseline:** `develop` `8174097e93069cc20ae258ba236cf7acd8d37fb3`, PR #8 merged. Foundation, Feature 01 (Identity Core & RBAC) and Feature 02 (Authentication) are implemented. Latest pre-merge CI [45638942711](https://github.com/m-hassan00096/kids-area-api/actions/runs/45638942711): 102 tests / 2289 assertions, Pint 101 files; no Feature 03 execution.
> **User decisions approved:** six functional decisions from the Users Management discussion, plus subsequent correction: avatar PNG/JPG/WebP max 2 MB; list shows image/name/username/phone/state/role; search includes phone; filter by role/state using Laravel Query Builder; return `userId`; omit `createdAt`. The approved specification supersedes the earlier proposed list shape.
> **Delivery gate:** technical plan and [tasks.md](tasks.md) explicitly approved on 2026-11-25; approval is documentation only. Authorized isolated target PHP8.2/GD preliminary feasibility recorded in tasks.md §§12–13. T03/T04 material blockers remain; strict approved contract unchanged pending explicit decision. No project dependency installation, migration, PHP implementation, acceptance tests, merge or deployment authorized.

## 1. Purpose and scope

Manage individual staff application accounts owned by `Modules/Identity`. “Employees” in the requested Users table means staff login accounts, not the future Employees/HR entity: no employment linkage, contracts, attendance or payroll fields here. Each User holds one assigned role; only an active User with exactly one active `web` role and no direct grants is eligible for protected access.

Included: paginated Users index, detail, create, full update (ordinary data, role, active state, optional reset of another User's password), conditional hard delete, optional staff avatar upload/replacement/read/removal as proposed below, and the initial shared Select Registry with exactly `roles` and `userFilterRoles`. Reuse existing API/Identity infrastructure. No UI implementation, role CRUD, permissions select/CRUD, user direct grants, HR mapping, public registration/recovery, Bearer tokens, multi-branch, Audit persistence, Redis/queues/cron or other business modules. Existing `/auth/me` contract stays unchanged (no User ID and no avatar added to that response).

Sources: [Identity module reference](../../../docs/03-modules/identity.md), [API](../../../docs/02-standards/api-standards.md), [Permissions](../../../docs/02-standards/permissions-standards.md), [Database](../../../docs/02-standards/database-standards.md), [Error handling](../../../docs/02-standards/error-handling-standards.md), [Localization](../../../docs/02-standards/localization-standards.md), [Code](../../../docs/02-standards/code-standards.md), [Workflow](../../../docs/02-standards/ai-workflow-rules.md), [Feature 01](../identity-core-rbac-foundation/spec.md), [Authentication](../authentication/spec.md).

## 2. Approved business rules and contract choices

| Rule | Decision |
|---|---|
| Disable / re-enable | Disable invalidates every session of the target atomically. Re-enable never restores old sessions; a fresh Login is required. |
| Role change | No forced logout for an eligible active-to-active role change. Fresh role/grants apply at the next protected request. A disabled/missing role cannot authorize access; retaining an inactive assignment does not reactivate it. |
| Self-deletion | Never permitted, even for non-founder Users. Another actor with the independent delete capability may delete if all constraints pass. |
| Inactive role | Retain the already assigned inactive role on an ordinary edit (and disabling is allowed); unchanged active flag is not reactivation. Never newly assign an inactive role or transition disabled User to active with inactive role. |
| Selects | Shared Core registry; `roles` active assignment options for Users create OR update, `userFilterRoles` active/inactive role options for Users view. No permission options until Feature 04. |
| API | Earlier proposed minimal mutation envelopes and full PUT retained, with latest list corrections below. |
| Avatar | User-approved formats PNG/JPG/WebP, max 2 MB. Approved defaults: optional (existing and founder accounts remain valid without one), one per User, absent = null; file field `avatar`, output `avatarUrl`, private read, optional explicit removal. Interpret 2 MB as 2 MiB = 2,097,152 bytes, Laravel 2048 KiB. JPEG accepts .jpg or .jpeg as the same approved image format. |
| Search | Approved fields name/username/phone only. Approved defaults: literal substring, name under its DB text collation, username case-sensitive (ASCII/binary), phone exact digit-string substring; no wildcard injection. Default stable newest ID first; no client-defined sorts/includes in V1. |
| Detail | Use `userId` in both management resources; list omits email, timestamps; detail includes email for editing, no timestamps. Nested role retains `id` and stored aliases. |

The user explicitly approved the complete specification, including the previously proposed defaults, on 2026-11-25. Technical design choices and feasibility checks in plan.md remain separately reviewable.

## 3. Access and API inventory

| Method | Path | Required effective permission |
|---|---|---|
| GET | /api/v1/users | identity.users.view |
| GET | /api/v1/users/{user} | identity.users.view |
| POST | /api/v1/users | identity.users.create |
| PUT | /api/v1/users/{user} | identity.users.update |
| DELETE | /api/v1/users/{user} | identity.users.delete |
| GET | /api/v1/users/{user}/avatar | identity.users.view (private binary read) |
| GET | /api/v1/selects?selects=roles,userFilterRoles | Per-key authorization, all-or-nothing |

All use the existing first-party stateful Sanctum browser transport, web guard, native CSRF for writes, fresh authoritative staff session/eligibility and effective permission checks. Extend the existing strict Origin/Referer/CORS gate to these routes (including private avatar read and selects); preserve its security rules. Permissions independent, no role-name bypass, no direct grants; an authorized actor may edit other Managers except ID 1. Check permission before target resolution to avoid unauthorized enumeration. Actual allowed mutation actor is revalidated inside the existing transaction/serialization boundary against current state and initiating session, not only route admission; exact ordering belongs to plan. No endpoint for administrative password reset/status/role changes or independent avatar write. No new technical permission names.

## 4. Users list and detail

### 4.1 Table

Columns in the requested UI: avatar, name, username, phone, state, role. `userId` is the internal record identifier returned for edit/delete/detail actions, not a required visible column. `isActive` describes the account flag, not effective eligibility; nested role flag clarifies disabled roles. Both active and inactive accounts visible to authorized readers, including founder. No emails, timestamps, password, password hash, auth_version, session data, grants or pivots in table rows.

```http
GET /api/v1/users?filter[search]=010&filter[roleId]=2&filter[isActive]=1&page=1&perPage=15
```

```json
{
  "success": true,
  "message": "",
  "data": {
    "items": [
      {
        "userId": 2,
        "avatarUrl": "/api/v1/users/2/avatar",
        "name": "أحمد محمد",
        "username": "Ahmed",
        "phone": "01012345678",
        "isActive": true,
        "role": {
          "id": 2,
          "name": "reception",
          "aliasName": "استقبال",
          "isActive": true
        }
      }
    ],
    "pagination": {"total": 1, "currentPage": 1, "perPage": 15, "lastPage": 1}
  }
}
```

`avatarUrl` is a relative API URL (client resolves against API origin) or null, never a filesystem path/public storage URL. `phone` is string or null. Nested role is exactly the documented object or null for a missing/ambiguous/unexpected guard assignment; never select an arbitrary role for a corrupted record. Direct grant anomalies do not authorize that target and no grant internals are serialized. The list shows account state separately from role state; no additional status fields. Show a frontend placeholder when no image.

### 4.2 Query contract

- Use the approved Spatie `spatie/laravel-query-builder` allowlisted index query over Eloquent/Laravel Query Builder. It is **not installed** in the actual baseline; exact compatible release, package-install approval and safe query design belong to plan. Do not install it now or bypass the standard by inventing a generic query framework.
- `filter[search]`: optional scalar text, trimmed outer whitespace, empty = no search, max 255 characters. Group OR conditions across name, username and phone, then AND with independently provided role/state filters. Literal substring only; bind values and escape SQL wildcard metacharacters (% and _) and escape character. Do not search email or ID. Login and uniqueness remain exact-case for username. Phone comparisons use string values and preserve leading zeros.
- `filter[roleId]`: optional positive integer, exact assigned `web` role ID; inactive roles may be filtered. Well-formed unknown role ID yields an empty result, not unauthorized access. `filter[isActive]`: optional exact 0/1; support explicit canonical boolean query spellings true/false as approved; false is a real filter, not omitted. Invalid shapes or values => 422. Combine all filters before counting/pagination. Reject unsupported filter/sort/include/fields/other query parameters, arrays where scalars expected, no raw SQL. No user-selected sort/include; order users.id DESC deterministic.
- `page`: positive integer default 1; `perPage`: positive integer default 15, clamp valid >100 to 100 before query. Invalid => 422. Return exact pagination keys `total/currentPage/perPage/lastPage`, even when empty (`items: []`). No paginator links/meta. Select only required columns; bounded eager-loading/join with one role per User, no N+1 or duplicate pagination counts. Actual queries, indexing, EXPLAIN and measured read performance in plan; no speculative new indexes.
- Detail GET /api/v1/users/2 returns exact same fields as a list item, plus nullable `email` for editing, under `data` (no items/pagination). No `createdAt` or `updatedAt` on either resource; `userId` only for the User identifier. Stored DB timestamps remain unchanged. Missing record => 404. Every successful JSON GET message is empty.

## 5. Create

```http
POST /api/v1/users
Content-Type: application/json
```

```json
{
  "name": "أحمد محمد",
  "username": "Ahmed",
  "email": null,
  "phone": "01012345678",
  "roleId": 2,
  "password": "example1234",
  "passwordConfirmation": "example1234"
}
```

With an image send the same fields using `multipart/form-data` and one binary `avatar` part. See §7. No base64 JSON. New accounts are active immediately; client may not submit an ID, status override, auth_version, grants, role arrays, avatar URL/path or timestamps. Authorize create and active eligible role at execution. Use existing canonical User, one role through `syncRoles([$role])`, existing MySQL unique constraints, Argon2id. No invitation or bootstrap reuse. Creation after seeded founder provisioning preserves last viable admin. Missing founder/invalid baseline must fail safely rather than create a new accidental ID-1 founder via CRUD.

| Field | Contract |
|---|---|
| name | Required nonblank string, max 255 characters; normal outer-space trimming for display name. |
| username | Required exact string, 3–50 ASCII A–Z/a–z/digits/dot/underscore/hyphen; preserve casing, no trimming or lowercase. MySQL ASCII/bin UNIQUE across retained accounts. |
| email | Optional null/empty string or valid RFC email string <=254, preserve case; case-insensitive unique across retained accounts. Empty becomes NULL, whitespace-only/invalid input rejected, no hidden trimming. |
| phone | Optional null/empty or exactly 11 ASCII digits as string; empty becomes NULL; no prefix rule, no spaces, +20, numeric conversion or normalization. Unique across retained accounts. |
| roleId | Required positive numeric record ID; multipart numeric string normalized explicitly; active web role exists; no arbitrary grant fields. |
| password | Required string, >=8 characters, no extra composition rules or trim/truncation; Argon2id hash only. |
| passwordConfirmation | Required exact equality; no trim. |
| avatar | Optional one validated binary image; omitted/null absent; see §7. |

Return 201:
```json
{"success":true,"message":"تم إضافة المستخدم بنجاح.","data":[]}
```

Duplicate username/email/phone, including concurrent MySQL uniqueness collision, => 422 field-specific safe Arabic validation errors; do not expose DB constraint messages. No partial User/role/avatar reference on failure.

## 6. Full update and session effects

```http
PUT /api/v1/users/2
Content-Type: application/json
```

```json
{
  "name": "أحمد محمد",
  "username": "Ahmed",
  "email": null,
  "phone": "01012345678",
  "roleId": 2,
  "isActive": true
}
```

Full PUT of ordinary mutable fields: name, username, roleId, isActive required; email and phone must be present but may be null/empty to clear (creation can omit them). JSON isActive is boolean; multipart explicitly normalize 1/0 (or true/false), reject ambiguous values. Validate uniqueness excluding target's immutable DB ID, including concurrent writes. Optional password plus confirmation and avatar operations are deliberate exceptions to full replacement. Password absent/null/empty preserves existing hash; whitespace is not silently trimmed; nonempty new password requires >=8 chars and exact confirmation. Nonempty confirmation without new password is invalid. Reset another account needs update only and no current password; a nonempty self-reset request is forbidden (403), including founder; use existing Profile endpoint. Never accept currentPassword in admin PUT. Unchanged roleId or state does not count as a state transition.

| Change | Effect |
|---|---|
| name, email, phone, username, avatar | No forced logout; next Me uses current stored name/username. Username's new exact value needed at next login. |
| active eligible role -> another active role | No forced logout, fresh effective permissions on next request; no copied/direct grants. |
| unchanged inactive role | Ordinary edits allowed; no new assignment/activation; account with inactive role remains ineligible. |
| isActive true -> false | Within same DB transaction, generation advances and all authoritative target sessions revoked. Stale native writes cannot restore authority; self-disable (except founder) permitted only if last-admin rule survives. |
| isActive false -> true | Active role mandatory; does not revive prior sessions. |
| new password for another User | Use existing reusable credential revocation operation; hash and auth generation updated with all target-session invalidation atomically; unrelated actor/device unaffected. |
| disable + password in same request | Apply both effects atomically; one credential generation increment for the mutation is sufficient; never wrap. |

Success 200: `{"success":true,"message":"تم تعديل المستخدم بنجاح.","data":[]}`. Request already admitted before revocation cannot authorize a new sensitive mutation using stale state; use the existing manager-row mutex/current-read invariant and recheck actual actor authorization and session/target state. Maintain consistent lock order with Login/Profile; no new lock table. Concurrent role changes, disable/re-enable, password reset, and stale saves must be covered in plan/tests. A malformed target with missing role may be assigned an eligible role under authorized update; unexpected multi-role/direct grants fail safely rather than creating additional grants or silently repairing privileges.

## 7. Avatar — approved upload and private read contract

- A single optional avatar belongs to the staff User, including an existing User after authorized edit. Existing Users/founder without image remain valid; default null and frontend placeholder. No avatar on HR entity and no public uploads endpoint.
- Allowed actual image formats PNG, JPEG/JPG (.jpg/.jpeg) and WebP only, max 2,097,152 bytes (2048 KiB); reject empty files, mismatched declared extension/real type, non-image or corrupt content, SVG/GIF and disguised executable/polyglot content. Bound decode dimensions/memory and decide safe validation/re-encoding approach with actual host capabilities in plan; no external service or image library installed now. Do not trust client filenames/paths or MIME claims; no image content, paths, credentials or raw multipart body in logs.
- Create POST multipart; update canonical PUT with JSON for text-only. To replace image on PHP 8.2 browser uploads, send POST /api/v1/users/{user} with multipart fields, `_method=PUT`, one binary `avatar` part and the normal full ordinary fields (Laravel method override, exact native flow verify in plan). It must resolve to the same authorized PUT contract, not a separate action; CSRF and first-party checks apply to both wire/effective methods. Reject unrelated override fields. PHP/webserver request and upload limits must allow file + multipart overhead; Laravel-handled oversize file => 422 avatar error, rejected whole request size => 413 safe response where Laravel handles it.
- On update no file => preserve current image; `removeAvatar: true` explicitly clears an existing image (false/omitted preserves; cannot combine true with file). JSON removeAvatar boolean, multipart explicit same normalization as isActive. Do not accept avatar URLs, client paths or arbitrary file IDs for writes. Replacement only after new file validation and safe storage; invalid input leaves original intact.
- Store bytes through existing Laravel Filesystem private disk, no public symlink, no DB image blob/base64. Add only nullable User-owned reference/metadata as needed by reviewed plan (actual column names/types, MIME/size handling, storage root and migration/backfill/rollback deferred). No new general attachment platform. Return relative `avatarUrl` or null; URLs do not expose internal key. Authorized GET /api/v1/users/{user}/avatar returns image bytes with verified Content-Type (image/png, image/jpeg, image/webp), inline, private/no-store and no-sniff headers; no JSON success wrapper for binary. Require current session + users.view, target lookup; missing User/image => 404, no auth => 401, forbidden => 403, safe JSON errors. Use credentialed same-site browser fetch respecting existing Origin/Referer checks; no public or bearer access and no signed public URL.
- Filesystem and MySQL cannot share one ACID transaction: stage/validate/write new unique private file before committing DB reference; don't remove old file until successful DB commit. Known rollback => clean staged file; ambiguous commit => verify authoritative reference or retain file for controlled cleanup, never delete possibly referenced new/old file. Replacement/removal/delete must prevent stale concurrent writes, missing committed images and deletion of another user's file; cleanup of superseded/unreferenced files is best-effort and safe, documented operator recovery with no cron/queue. A file error before commit fails safely with no partial DB mutation. If cleanup fails after commit, preserve successful authoritative state and record privacy-safe operational evidence rather than claim rollback. Delete user removes only its avatar after commit; protected/failed delete retains original. Exact storage/fault-injection design, limits and backup/recovery are technical-plan decisions. Do not introduce independent avatar mutation endpoint.

## 8. Founder, last viable admin and deletion

- User DB ID 1 is permanently protected, regardless of username or role: no deletion or deactivation by any actor; another User cannot update it (including avatar or password). Owner with users.update can change ordinary fields/active role subject to invariants; self password only Profile; unchanged role and isActive=true allowed. Forbidden attempts 403 with safe Arabic error.
- All self-deletes forbidden (403). Another authorized User may hard-delete non-founder even if Manager, only without protected important dependencies and if a viable admin remains. Return 409 conflict with Arabic message advising deactivation for protected historical/operational references; do not cascade payment/shift/attendance/HR/audit evidence. Use actual FK RESTRICT/owning-module rules; current business modules absent, synthetic disposable FK fixture is for tests only, not production schema. Creation-audit evidence alone must not permanently block deletion once Audit exists. Audit persistence itself deferred.
- Reuse `IdentityAccessMutation`, permanent manager-row mutex, ordered current reads and transaction; at least one eligible active User with a single active web role, no direct grants and all six capabilities after mutation: identity.users.view/create/update, identity.roles.index/show/update. A custom role qualifies; no role-name or founder bypass. 409 for loss of viability, rollback User/role/status/password/session/reference. Delete removes only the target's role membership and ephemeral sessions and user row; no role catalogue, permission definitions, other accounts or historical evidence removed. Generation/owner checks and row absence make late session saves unauthorized. Race-safe dependency check/DB constraint; translate known relevant FK conflicts to 409, unexpected faults safe 500. No soft delete, automatic role reassignment, new sentinel or new production history table.
- Successful DELETE 200: `{"success":true,"message":"تم حذف المستخدم بنجاح.","data":[]}`.

## 9. Shared Select Registry

```http
GET /api/v1/selects?selects=roles,userFilterRoles
```

```json
{
  "success": true,
  "message": "",
  "data": [
    {"label":"roles","options":[{"value":1,"label":"مدير"},{"value":2,"label":"استقبال"}]},
    {"label":"userFilterRoles","options":[{"value":1,"label":"مدير"},{"value":2,"label":"استقبال"},{"value":3,"label":"دور غير نشط"}]}
  ]
}
```

Synthetic example. One read-only Core registry/aggregator, module-owned providers. `roles`: eligible active web roles, actor has users.create OR users.update. `userFilterRoles`: all web roles including inactive, users.view. No separate roles.index requirement; role option value numeric ID, label stored aliasName text, not a new translation. Options stable order by role ID; no password/user/pivot fields. Request required comma-separated unique registered keys (max two in current inventory; reject malformed/duplicates/unknown 422); preserve requested key order and empty options array. Authenticate, validate all keys, authorize ALL before any provider output, then execute only requested ones. Any forbidden key => 403 entire response, not partial. Configured bounded results, never silently truncate eligible options or return an unbounded catalogue; exact shared result limit and safe overflow handling to be reviewed in plan. No permissions key or extra dropdown endpoints, no client-supplied classes/SQL. Server create/update still revalidate target role; lookup isn't authorization to mutate it.

## 10. Shared contracts, security and technical constraints

Reuse existing ApiResponse, centralized Arabic ApiErrors, RequestId, localization, staff stateful/session middleware, IdentityAccess and credential revocation; adapt explicitly for Users, avatar and selects. No new response helper or login transport. 401 invalid/expired session; 403 permission/forbidden founder/self/role context or untrusted origin; 404 missing resource/file; 409 valid operation conflicts with state/last-admin/historical FK; 419 missing/invalid CSRF; 422 invalid input/duplicate fields/query/unknown select or invalid role assignment; 413 request too large where Laravel handles; safe 500/503 operational failures. Preserve existing safe correlation ID; no HTML redirects or leaked raw errors. Every API returns only explicit Resource fields; no auth_version, hash, cookie, raw storage path or sensitive request content. Password-bearing Users writes and native/authoritative session queries must keep the redaction boundary from Feature 02; don't disable unrelated business query events indiscriminately. No audit claim until deferred Audit delivered; production access administration remains a separate rollout gate.

Existing schema: users (name, username, nullable unique email/phone, hashed password, is_active, timestamps, auth_version), roles and native Spatie pivots with single-role unique constraint, sessions and login-throttles. Avatar requires narrowly scoped nullable reference/metadata migration; no migration written now. No new tables planned for Users/Select Registry and no extra indexes assumed; verify actual query plans. Preserve existing Login/Me/Profile/Logout contracts, 12h idle and 5/30 throttles, no token storage. Reuse PHP 8.2/Laravel 12/MySQL 8/shared hosting constraints. Plan to resolve Query Builder dependency compatibility/approval, private image processing capabilities, path lifecycle, current actor/session mutation check, permission cache coherence, index/EXPLAIN and test isolation. No package install or code before subsequent approved gates.

## 11. Acceptance scenarios — all Not Run

| ID | Expected scenario |
|---|---|
| F03-01 | Authorized table list exactly userId/avatarUrl/name/username/phone/isActive/role; nullable values and role anomaly safe; no id alias, email, createdAt, updatedAt or secrets. Detail includes email and no timestamps. Me unchanged. |
| F03-02 | Partial literal name, exact-case username and phone string search including leading zero; OR search grouped AND role/state, including inactive roles/accounts and false filter. Escaped wildcard values, unknown/malformed filters, sort/include rejected. |
| F03-03 | Default pagination 15, perPage>100 clamps, invalid values 422, stable newest-first ordering, empty page and correct filtered total; no N+1 or duplicated rows; measured SQL/EXPLAIN in plan. |
| F03-04 | Create active account with one active role, optional nullable contacts/avatar, correct 201 minimal envelope, no status/role-array/direct grant/ID injection. Bootstrap rules intact. |
| F03-05 | Username exact-case distinct accounts and duplicate rejection; email case-insensitive unique preserves case; phone exact ASCII digits/string and multiple NULLs. Concurrent collisions mapped to safe field 422 and no partial rows. |
| F03-06 | Full PUT, uniqueness excludes target, invalid/missing ordinary fields fail; absent/null/empty password retains hash; valid another-user reset exact confirmation/Argon2id. Self-reset forbidden; Profile works unchanged. |
| F03-07 | Independent create/view/update/delete capabilities; no Manager bypass; 401/403/404 and permission-before-lookup, malformed fields safe; real native Cookie/CSRF/Origin coverage for JSON/multipart writes. |
| F03-08 | Founder cannot delete/deactivate or be edited by another, including avatar; permitted own edits and role change subject last admin. Self-deletion blocked for every account. |
| F03-09 | Active role replacement singular and fresh grants next request without forced logout. Retain inactive assigned role for ordinary edits/disable; reject new inactive assignment or reactivation with it; anomalies fail closed. |
| F03-10 | Disable target revokes all sessions atomically; re-enable cannot revive them; unrelated sessions remain. Old delayed native save cannot restore authority, concurrent login/reset/disable orders safe. |
| F03-11 | Admin password reset revokes only target devices; old hash not accepted, new Login works; failed/competing updates roll back credential version and role/state/session changes. |
| F03-12 | Last viable admin invariant by six actual permissions incl custom role, founder presence insufficient; concurrent removal/disable/role changes and stale actor authority cannot leave zero paths; safe 409 rollback. |
| F03-13 | Hard-delete eligible non-founder/non-self unlinked User, remove own pivot/sessions/reference only, no role/permission deletion; linked User FK conflict 409 retains data/file; dependency race tested with isolated MySQL fixture. |
| F03-14 | Optional PNG/JPEG/WebP avatar create/update at allowed size boundary, reject disallowed types, spoof/corrupt/multiple/over-limit files and malformed image; JSON text edit retains image, multipart method override real CSRF enforced; no base64/path acceptance. |
| F03-15 | Private avatar URL or null; authorized binary correct type/no-store, absent image 404, anonymous/forbidden/origin denial; no public storage/path leak; remove/replace only target's image. |
| F03-16 | Storage failure, DB uniqueness/transaction/last-admin failure and concurrent avatar replacement/delete preserve authoritative references and old valid images; staged cleanup, uncertain commit and post-commit cleanup failure safe. |
| F03-17 | Select roles active-only for create OR update; userFilterRoles includes inactive for view; stored aliases, numeric IDs, exact key order/options; unknown/duplicate 422 and mixed unauthorized 403 with no partial provider output; bounded results. |
| F03-18 | HTTP envelopes Arabic/camelCase/X-Request-ID; no passwords/hashes/session IDs/raw multipart/storage keys/SQL in output or logs; native session protection and unrelated DB events intact. |
| F03-19 | Real MySQL concurrency, forward avatar migration preserves existing founder/users and baseline RBAC, full Authentication/Identity/Foundation regression, route/permission/module scope, Pint and CI. No audit, Roles CRUD/permissions select, HR or token/queue/Redis/cron implementation. |

All scenarios remain approved requirements, not executed results. User approved the full spec on 2026-11-25. Next: review actual target feasibility [tasks.md](tasks.md) §13 and explicitly resolve strict native multipart duplicate rejection and image structural validation blockers; no change to this approved functional contract without review. Separate probe, dependency, documentation merge and implementation authorizations. No code or deployment.
