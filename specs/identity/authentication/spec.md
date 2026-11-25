# Feature 02 — Authentication

> **Status:** DRAFT — for user review and explicit approval (2026-11-25).
> **Owner:** Modules/Identity.
> **Baseline:** Feature 01 implementation merged via PR #6 into develop at `f5d740b6ace3e10ec63a5a69844856b3d241ecd0`. The latest verified pre-merge CI (45618607042) passed 61 tests / 830 assertions and Pint. These results are Feature 01/Foundation evidence, not Authentication implementation evidence.
> **Sources:** [Identity Module Reference](../../../docs/03-modules/identity.md), [API Standards](../../../docs/02-standards/api-standards.md), [Error Handling Standards](../../../docs/02-standards/error-handling-standards.md), [Permissions Standards](../../../docs/02-standards/permissions-standards.md), [Localization Standards](../../../docs/02-standards/localization-standards.md), [Backend Architecture](../../../docs/01-architecture/backend-architecture.md), [AI Workflow Rules](../../../docs/02-standards/ai-workflow-rules.md), [Feature 01](../identity-core-rbac-foundation/spec.md).
> **Delivery gate:** The four latest user decisions below are approved. This complete spec and its proposed contract details still require review/approval, then a separate technical plan and task list before implementation. No Sanctum installation, code, migration, executed Authentication tests, PR merge or production operation is authorized by this draft.

## 1. Purpose and scope

Enable individual internal staff accounts to authenticate in a browser, retrieve their own safe access context, change their own password, and end authenticated sessions. Build upon the canonical User, Spatie web-guard RBAC, IdentityAccess and approved security invariants of Feature 01. The Feature 01 Manager bootstrap remains a separate one-time provisioning command.

In scope:
- Laravel Sanctum stateful browser cookie/session transport and CSRF handshake.
- Username/password Login, current-session Logout, authenticated Me, self-service Profile password change.
- MySQL-backed sessions; 12-hour idle expiry, secure regeneration, multiple devices, all-session revocation after password changes.
- Login rate limiting by exact username and client IP; safe Arabic non-enumerating responses.
- Reusable authenticated active-User/active-single-Role middleware and fresh role-granted permission checks for later protected APIs.
- Shared minimal App\\Support\\ApiResponse helper and centralized Laravel API exception rendering with request correlation; retain existing Feature 01 exception semantics.
- Synthetic tests covering HTTP/session behavior and true MySQL concurrency where relevant.

Out of scope: Users CRUD or administrator password-reset API (Feature 03), Roles/Permissions CRUD (Feature 04), Select Registry implementation, shared Audit persistence/reader/writer, new permission keys, child/guardian authentication, public registration, email/phone login, Forgot Password, password recovery emails/SMS, bearer/API token issuance or storage, remember me, device/session-management UI, impersonation, 2FA, Redis, queues/workers, scheduled jobs, frontend implementation and live deployment. Do not broaden Feature 01's model or bootstrap behavior unnecessarily.

## 2. Actors and approved rules

- Active individual staff User with exactly one active Role under guard web and no direct grants may authenticate, irrespective of role name or permission count. Reception with zero Identity administrative grants can log in and access their own profile.
- User ID 1 remains protected internally. Its own Profile password change requires its current password like every other User. No account ID supplied by a client can choose the password-change target.
- Existing ten permissions remain unchanged. Login/Logout/Me/self password change require no additional technical permission beyond the applicable authenticated/eligible context. Later business endpoints must check their own permission and record-state policies; no Manager bypass.
- Username is 3–50 ASCII letters, digits, dot, underscore or hyphen; exact letter case matters. Do not lowercase or silently trim it, including when checking throttle keys. Email/phone are not login identifiers. Passwords are not trimmed, normalized, truncated or stored as plaintext. Preserve Feature 01 Argon2id policy and minimum 8 characters for newly chosen passwords, with no composition requirement.

**Latest user decisions expressly approved on 2026-11-25:**
1. Database sessions in MySQL 8 (not file sessions).
2. Successful password change invalidates all sessions for the affected User, including the initiating session; require re-login.
3. Five failed login attempts per exact username within one minute; one-minute temporary throttle. Additional limit: thirty failed attempts per client IP within one minute across usernames; one-minute temporary throttle.
4. Remove the top-level User `id` from the `me` response. This is not removal of the internal users.id column, Role ID, or founder protection.

## 3. Proposed endpoint contract

All application endpoints use /api/v1. The native Sanctum CSRF-cookie endpoint is the explicit framework handshake exception, not a new business endpoint.

| Method | Path | Access | Successful result |
|---|---|---|---|
| GET | /sanctum/csrf-cookie | Approved stateful browser origin, no login required | Native 204, CSRF cookie, no JSON body |
| POST | /api/v1/auth/login | Approved stateful browser + valid CSRF; guest login | 200, empty message/data; regenerated authenticated session |
| POST | /api/v1/auth/logout | Authenticated current eligible staff session + valid CSRF | 200, empty message/data; current session invalidated |
| GET | /api/v1/auth/me | Authenticated current eligible staff session | 200, minimal self Resource |
| PUT | /api/v1/profile/password | Authenticated current eligible staff session + valid CSRF | 200, Arabic success message, data []; affected User's sessions revoked |

These exact application route names/methods and payload shapes are proposed for this spec's approval; the functional requirements in section 2 are already approved. Login/Logout/Me use the shared API envelope. No /api/v1/users/{user}/password endpoint. A subsequent Users PUT may reset another User's password under approved rules, but does not exist here.

### 3.1 Login

Request:

```json
{
  "username": "Mohamed.Admin",
  "password": "example-only-not-a-real-credential"
}
```

Validate required strings and username syntax. A nonempty string password submitted for login is checked as entered, not silently altered or validated as a new password; malformed/missing input returns 422. Unknown username, wrong password or otherwise unusable account returns the same safe 401 message and empty errors, without disclosing existence, activation or role state. No login for inactive, roleless, multi-role, inactive-role, wrong-guard or direct-grant-anomalous accounts. The plan must address equivalent work for unknown credentials, avoid unnecessary expensive hashes during a throttle, and preserve safe response behavior.

The valid credential and current access state must hold at authorization time. A concurrent password change must not allow a login verified only against an old hash to establish a valid session after the change commits. Regenerate the session identifier after successful login; the old identifier must not grant access. No password or account content in success output; the frontend calls Me for its access context.

Successful Login (HTTP 200):

```json
{
  "success": true,
  "message": "",
  "data": []
}
```

Draft behavior for an already authenticated eligible browser: refuse re-login/account switching with 409 and require explicit Logout first; never silently overwrite another active browser session. A stale/invalid authenticated context does not become valid merely by calling Login; use the safe revocation/re-authentication path. Review this small contract detail with the spec.

### 3.2 Me

Returns only the caller's own access context. Example (illustrative role ID and subset of permissions, not a fixed Manager list):

```json
{
  "success": true,
  "message": "",
  "data": {
    "name": "مدير النظام",
    "username": "Manager",
    "role": {
      "id": 1,
      "name": "manager",
      "aliasName": "مدير"
    },
    "permissions": [
      "identity.users.create",
      "identity.users.update",
      "identity.users.view"
    ]
  }
}
```

- **Do not expose User id as data.id.** Keep Role id as the proposed nested role identifier, not the User identifier. Use exact stored name, username and Role alias; public fields are camelCase.
- permissions is a deduplicated array of currently effective technical role-granted names (proposed stable lexical order); [] is valid. Do not infer permissions from role name, merge direct grants, add wildcards, return arbitrary relations, or cache stale authorization state.
- No contact fields, password/hash, cookies, session IDs, pivot internals, audit history or employee/business data in this Resource. The frontend may use permissions for display only; backend checks remain authoritative. GET success message is empty.
- Return current grants on the first newly authorized request after committed changes. Account/role/anomaly checks fail closed even if a previously rendered UI still shows an old role/permission.

### 3.3 Logout

Requires valid current authenticated eligible session and CSRF. Invalidate the current server-side authenticated session, retire its old session ID and rotate CSRF state; the other devices' valid sessions remain authenticated. The response is HTTP 200 with success true, message "", data []. An unauthenticated or expired context returns 401 once CSRF is valid; invalid/missing CSRF on a stateful write returns 419. An authenticated but ineligible context returns 403 and must not retain access; the plan specifies safe session retirement/re-authentication for invalid contexts.

### 3.4 Profile password change

Request:

```json
{
  "currentPassword": "example-old-password",
  "password": "example-new-password",
  "passwordConfirmation": "example-new-password"
}
```

- Only the authenticated User may change their own password. No target ID, role change, email/phone edit, or administrative-reset parameter is accepted.
- Validate required strings, minimum eight characters for password, exact confirmation; never trim secrets. Wrong current password yields a safe 422 field error on currentPassword (draft contract choice); unchanged credentials and sessions. Invalid fields use camelCase errors.
- Verify current password against actual fresh credentials; make the approved write and logical all-session revocation coherent/atomic. Any failed write or failed required revocation leaves the old valid state, not a half-completed password change. No plaintext or hashes in responses or logs.
- On successful change, *all* previous authenticated sessions of this User are invalid, including the initiating browser and any older session that an in-flight request attempts to save. Other Users' sessions remain valid. Require login again using the new password; old password no longer works.
- Two competing password changes using the same current password must not both succeed if the first changes it; recheck fresh credentials under the approved transaction/serialization strategy. Password-change versus concurrent login/session-save races must not revive old authentication. The plan selects the concrete revocation/version-check strategy and safe transaction/lock order, rather than treating a simple session-row deletion as sufficient.
- The reusable revocation contract must support future administrative resets, without implementing Users CRUD. No hidden recovery route.

Successful password change (HTTP 200; wording proposed):

```json
{
  "success": true,
  "message": "تم تغيير كلمة المرور بنجاح. يرجى تسجيل الدخول مرة أخرى.",
  "data": []
}
```

## 4. Session and browser-security requirements

- Database session storage in MySQL 8 with suitable per-User lookup and last-activity handling. Exact schema, indexes, retention, lifetime and revocation mechanics belong to plan.md. No Redis, worker or cron dependency. Expired sessions are unusable independently of when physical cleanup occurs.
- 720 minutes (12 hours) of inactivity, not a fixed 12-hour lifetime after Login. At the 720-minute idle boundary or later the session is no longer valid. A valid authenticated server-observed request before expiry refreshes last activity. An idle browser tab or an anonymous/failed-authentication request must not keep the authenticated session alive. Multiple concurrent devices allowed; no remember-me option.
- Login session ID regeneration; Logout current-session invalidation and CSRF rotation; no reuse of expired, invalidated or pre-login session identifiers. Password revocation must fail closed on newly authorized requests after commit and on stale concurrent session writes. A request authorized before commit is not retroactively claimed to be cancelled; future sensitive write actions must recheck authorization at their own mutation boundary.
- Protected routes use Sanctum stateful browser authentication with web guard, plus active User/Role eligibility and fresh current grants. No personal access tokens, token tables, browser Bearer alternative, or authentication by client-supplied identifiers.
- Credentialed browser requests use approved HTTPS origins, secure HttpOnly session cookie, compatible SameSite/domain/path settings and the native readable CSRF cookie solely for XSRF header flow. No wildcard credentialed CORS. Reject unapproved/non-stateful clients rather than falling back to stateless login or bypassing CSRF. Exact origin allowlist, trusted proxies, cookie attributes and CORS configuration are deployment-specific; tests use controlled synthetic origin(s). Do not invent live domains.
- CSRF before protected writes, including Login, Logout and Profile change. Safe 419 JSON error for invalid or missing CSRF when applicable, no credential operation performed. For expired/missing authentication with an otherwise valid CSRF context, 401. If the session also lost CSRF state, a write may return 419 before auth; the frontend must obtain fresh CSRF and log in as required. No HTML redirect or generic success on failed auth.
- Current eligible staff state and grants are re-evaluated on newly protected requests after commit. Authenticated but inactive/role-invalid or missing required permission => 403. Missing or expired authentication => 401. Avoid reliance on stale Eloquent relations or Spatie cached authorization for the fresh-access boundary.

## 5. Login throttling

Approved behavior:
- Per exact username, five failed valid-form credential attempts within 60 seconds, including attempts for non-existing identifiers and unusable accounts. Crossing the threshold triggers a 60-second temporary block; attempts while blocked return 429 without checking credentials. Case variants remain separate username identities, not case-folded.
- Per trusted resolved client IP, thirty failed login attempts within 60 seconds across usernames trigger an additional 60-second temporary block. Either active block denies Login; do not log or reveal whether the username exists or which counter caused the denial.
- No permanent account lock, no User mutation, no lockout based only on successful Logins. Respect Retry-After for 429; avoid leaking sensitive counter metadata. Scope rate limiting to Login, not business endpoints.
- Draft boundary proposal: the fifth/30th failure receives the normal 401 failure and starts the corresponding block; subsequent attempts, even with correct credentials, receive 429 while either block is active. A blocked attempt does not prolong the block; after its expiry, resume under applicable limits. A successful Login may reset that username's failure state but must not clear the shared IP failure state (so an attacker cannot bypass the IP limit using one correct account). Malformed form/invalid CSRF follows 422/419 without credential verification; plan must avoid any security bypass through malformed payloads. These precise counting semantics are for spec review.
- Use trusted proxy configuration to resolve client IP, not untrusted forwarded headers. Implement atomic cross-request counters and check/increment behavior under real concurrent attempts; verify threshold behavior, bounded temporary storage and safe expiry without Redis/cron. Exact cache/store, key privacy, window algorithm, serialization and infrastructure trade-offs belong to plan.md. Do not select a new dependency or speculative rate-limit table in this spec.

## 6. Shared API responses, errors and logging

Implement the existing shared standards once: minimal App\\Support\\ApiResponse and centralized Laravel exception rendering, not per-module wrappers or controller-wide catch blocks. Every Laravel-handled /api/* response carries a server-generated X-Request-ID; use safe Arabic translated messages and camelCase validation keys. Preserve valid status/headers (including Retry-After), no stack traces, raw SQL, internal exception detail or debug HTML. Framework native successful CSRF-cookie handshake remains bodyless 204. Do not log password-bearing requests, passwords/hashes, cookies, session IDs, tokens or raw session payload. Select privacy-safe security diagnostics as permitted by Error Handling Standards; routine Login/Logout is not administrative Audit, and Audit remains deferred.

| Case | Status | Body / rule |
|---|---|---|
| Valid Login, Logout, Me, Profile change | 200 | Standard success envelope |
| Unknown/incorrect credentials or ineligible Login | 401 | Identical safe Arabic message, errors [] |
| Missing/expired authenticated session (valid CSRF where applicable) | 401 | Standard auth error |
| Authenticated inactive/invalid role/direct-grant context, missing permission or unapproved stateful access | 403 | Safe generic forbidden response |
| Already authenticated Login | 409 | Draft contract; explicit logout required |
| CSRF mismatch on browser write | 419 | Standard Arabic JSON failure, no side effect |
| Missing/invalid fields, incorrect currentPassword | 422 | camelCase validation errors |
| Either Login throttle active | 429 | Safe Arabic response and accurate Retry-After |
| Other expected API errors (e.g. 404/405) | Appropriate HTTP status | Central JSON mapping, preserve required headers |
| Unexpected failure | 500 | Generic safe Arabic error; technical details only in sanitized logs |

Example incorrect Login (all invalid-credential cases):

```json
{
  "success": false,
  "message": "بيانات تسجيل الدخول غير صحيحة.",
  "errors": []
}
```

Example Profile validation error:

```json
{
  "success": false,
  "message": "البيانات المدخلة غير صحيحة.",
  "errors": {
    "currentPassword": ["كلمة المرور الحالية غير صحيحة."]
  }
}
```

Example 429:

```json
{
  "success": false,
  "message": "تم تجاوز عدد محاولات تسجيل الدخول. يرجى المحاولة لاحقًا.",
  "errors": []
}
```

Examples define proposed safe wording; use localization keys, not hardcoded duplicated messages. Failure has no data field; success has exactly one data field and no errors. No top-level code. Any stable business code, if needed, remains inside errors.code per existing standard.

## 7. Acceptance scenarios (proposed, all Not Run)

| ID | Scenario / required observable result |
|---|---|
| F02-01 | Real browser-style CSRF handshake: approved stateful origin, credentials/cookies and valid XSRF header; valid Login sets authenticated session; old pre-login session ID cannot authenticate; response has empty message/data, no token. |
| F02-02 | Exact-case usernames can log into separate existing accounts; case variants do not accidentally authenticate another User; email/phone are not accepted as login identifiers; identifiers/secrets not silently trimmed or normalized. |
| F02-03 | Unknown username, wrong password, inactive User, roleless/multiple-role/disabled-role/wrong-guard/direct-grant anomaly: same 401 envelope and no authenticated session; non-enumerating behavior. |
| F02-04 | Malformed/missing Login fields return safe 422, no authenticated state, no password in validation/logs; already authenticated Login does not silently switch account. |
| F02-05 | Five failed attempts for one exact username in 60s, including distributed IPs and unknown names; next attempt denied 429 for 60s, correct credentials blocked during limit, Retry-After correct, no persistent User lock; after expiry valid Login can succeed subject to IP limit. |
| F02-06 | Thirty failed attempts across identifiers from same trusted client IP trigger IP throttle; independent IP remains usable unless username limit applies; username case variants distinct; successful Login cannot reset shared IP failure state; blocked attempts do not extend lock. |
| F02-07 | Concurrent failures cannot bypass either threshold; atomic check/accounting and safe expiry verified on actual configured shared test store, with independent processes/connections; trust only approved proxy headers. |
| F02-08 | Me returns exactly the proposed minimal self Resource, **no top-level User ID**, no secret/contact/pivot data; role id is nested only, lexical deduplicated actual role grants, [] for eligible Reception; no artificial Manager bypass. |
| F02-09 | Fresh protected access blocks the next newly authorized request after User/Role deactivation or invalid role/direct grant state; changed role grants visible immediately after commit despite warmed cache. |
| F02-10 | MySQL-backed separate sessions for two or more devices; genuine authenticated activity before idle boundary refreshes session; at 720 minutes of inactivity session invalid; unauthenticated/idle/failed request cannot preserve it; physical expired-row cleanup timing not relied on. |
| F02-11 | Logout invalidates only current session and CSRF state, old cookie unusable; other device remains valid; repeated/missing-auth behavior matches 401/419 as applicable. |
| F02-12 | Invalid/missing CSRF for Login/Logout/Profile yields 419 and no credential/session mutation; disallowed/non-stateful origins cannot bypass cookie/CSRF protection; credentialed CORS/HTTPS flags verified in controlled environment; Bearer auth unsupported. |
| F02-13 | Profile change with correct current password, valid new password and confirmation, including User ID 1; success response safe, new hash verifies, old password fails, affected User must re-login. No target-ID bypass. |
| F02-14 | Wrong current password, mismatched confirmation, missing/short/invalid values fail 422 with camelCase errors; existing hash and all valid sessions preserved. Long supported password handled without truncation. |
| F02-15 | Successful password change invalidates all affected User sessions including initiator, leaves another User's sessions valid; failed write/revocation rolls back coherent state; session cleanup not the sole authority for stale credentials. |
| F02-16 | True concurrent Login versus password change / stale session save versus password change / competing changes with same old password: no old authenticated state valid after commit, no half-committed revocation, no stale session resurrection. |
| F02-17 | Shared envelope and Arabic errors for 401/403/404/405/409/419/422/429/500 as applicable; X-Request-ID, proper headers, no HTML, no raw exception/SQL, no leaked plaintext/hash/session cookie/session IDs; synthetic failure log redaction. |
| F02-18 | Scope regression: Feature 01 protections, seeder and bootstrap behavior still pass; no new permission grants, token table/issuance, Users/Roles/Selects CRUD, Audit implementation, public reset, remember me, queue/Redis/cron or real data operations. |

The plan must map every acceptance scenario to executable test cases, including tests with real stateful cookie/CSRF middleware enabled (not just a mocked actingAs path), time control for idle/throttle boundaries and MySQL concurrency. Preserve Feature 01/Foundation tests except the deliberately superseded Feature 01 absence checks for this now-approved feature, updated explicitly in its implementation PR. Use isolated synthetic fixtures; no test or runtime check claimed in this draft.

## 8. Technical details deferred to plan and explicit gates

- Verify actual Laravel 12/PHP 8.2 and compatible Sanctum release, exact dependency installation impact and separate authorization. Verify existing schema/provider/Feature 01 interfaces and add forward migrations only for reviewed session/revocation requirements.
- Design database sessions, idle activity enforcement, safe cookie/CSRF/stateful middleware order, session invalidation and credential-change concurrency on the same primary database. Consider native framework mechanisms first; no unnecessary custom framework or sentinel table.
- Decide atomic rate-limit store/algorithm compatible with single shared hosting and no Redis/cron; preserve exact approved thresholds and privacy. Specify indexes, transactions/lock order and tests rather than claiming Laravel's default limiter alone fulfills concurrent behavior.
- Define safe API error mapping, X-Request-ID generation and sanitization; reuse existing localization and BusinessException. Review any conflict with canonical standards instead of silently changing them.
- Define precise implementation files, migrations, dependency order, test harness and CI verification in plan.md/tasks.md. No live environment/real staff data or deployment configuration; actual hosting domains, proxy/TLS, Argon2id and database compatibility still need verification separately.

**Next:** review and approve this draft spec, including the proposed endpoint/payload contracts and minor error/throttle-boundary choices. Then prepare plan.md, then tasks.md for review. Authentication code has not started.
