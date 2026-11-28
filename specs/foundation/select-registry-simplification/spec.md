# Core Feature — Select Registry Simplification

> Status: Approved for implementation after documentation PR #16 merge. User explicitly decided no option limit, no pagination or server-side search, up to five Select keys, unchanged response shape. Existing runtime still 3/200 until the reviewed change is merged.
> Baseline: develop 3220d56e3b1b7c7c9842907b2a0266544834c64f, Activity Catalogue merged PR #15.

## 1. Goal and scope

Provide one simple read-only dropdown contract for the small single-location V1 application. Supersede old 3-key/200-options, limit+1 and SELECT_OPTIONS_LIMIT rules. Keep one existing endpoint, provider interface and dispatch/authorization semantics. No new People keys yet; they belong to their owning features. No migrations, new packages, frontend, cache, Redis, background jobs, server search, pagination, adaptive mode, meta or new endpoints. Ordinary paginated lists unchanged.

## 2. Contract

```http
GET /api/v1/selects?selects=roles,userFilterRoles,permissions,activities,activeActivities
```

Exactly one existing query field selects, comma-separated 1–5 distinct case-sensitive registered keys; existing strict malformed/unknown/duplicate handling unchanged. Reject more than five (including six distinct registered providers in guarded test-only registry), empty, array input, whitespace/unregistered key, extra query params including search/page/perPage/params, body or files with existing validation 422. Do not loosen browser/CSRF/auth rules or existing validation order. Authenticate first; validate all keys; authorize all requested providers before any provider option read. Entire request 403 for any forbidden valid key, no partial options; 401 unauthenticated. Do not execute a provider twice, silently ignore keys or add arbitrary lookup inputs.

Successful GET 200, message empty, exact unchanged response:
```json
{"success":true,"message":"","data":[{"label":"activities","options":[{"value":1,"label":"Swimming"}]},{"label":"activeActivities","options":[]}]}
```
Return every eligible authorized option in deterministic existing order. Empty set options []. No limit, pagination, total, mode, meta, silent truncation, overflow 409 or extra projection. No cap probing/count-for-cap. IDs and technical values retain existing types. Existing database or integrity faults still fail safely, not swallowed or turned into empty success.

| Key | Purpose / authorization | Eligibility, order and projection |
|---|---|---|
| roles | identity.users.create OR identity.users.update | active web roles, existing exact guard, ID ASC, numeric id/name |
| userFilterRoles | identity.users.view | active/inactive web roles, ID ASC, numeric id/name |
| permissions | identity.roles.store OR identity.roles.update | exact registered web PermissionCatalogue only, name ASC, technical name/alias_name, fail on missing alias |
| activities | activities.index | all activities, name ASC/id ASC, id/name |
| activeActivities | activities.index | active activities, name ASC/id ASC, id/name |

Keep primary reads, no N+1 or added counts, no extra fields. Do not weaken eligibility, expose unrelated records, or grant any new permission. Requesting five keys is allowed only when caller independently satisfies every provider's condition.

## 3. Acceptance criteria (all Not Run)

| ID | Required behavior |
|---|---|
| AC01 | 1–5 distinct registered keys valid; exactly all five real keys, one call per key |
| AC02 | six distinct registered test-only keys rejected before option reads; no production sixth provider |
| AC03 | invalid, unknown, duplicate and extra parameters/body/file rejected under existing validation |
| AC04 | complete options at 0/200/201/500/501/1,000/2,000/10,000 suitable synthetic rows; no old cap |
| AC05 | active-only vs all eligibility, exact role guard and exact catalogue filter preserved; no silent truncation |
| AC06 | exact success/failure envelopes, option types and fields unchanged; no meta/pagination |
| AC07 | requested key order and existing stable provider row order preserved |
| AC08 | every key authorized before any option queries; mixed forbidden valid keys in both orders zero option reads |
| AC09 | unauthenticated and forbidden remain 401/403, no leaks; invalid valid-looking keys 422; unexpected faults still fail safely |
| AC10 | all current provider capability distinctions preserved, role/permission revocation and session behavior unchanged |
| AC11 | existing routes, ordinary pagination, schemas, locks, seed/grants and dependencies unchanged; no additional feature |
| AC12 | real MySQL 8 HTTP/provider/performance regression, full suites, Pint and CI pass on exact head; measure response size, memory, SQL and HTTP growth without fabricated production SLA |

## 4. Risks and boundaries

Uncapped option results mean O(n) response/serialization/memory and potentially high latency on shared hosting, up to five sets per request. This is the user's explicit simplicity trade-off. Return only eligible fields; don't leak private data; do not hide a cap or introduce automatic adaptive behavior. Measure growth at realistic and stress scales; report failures or excessive resource use for decision rather than silently changing contract. Frontend local filtering and refresh behavior are future UI integration, not implemented now. No production data, migrations, grants or deployment.

## 5. Review

API Standards §8 owns the new contract; previous Identity/Activities feature specs, tasks, tests and CI evidence describe their historical tested heads. Do not retroactively rewrite evidence; implementation updates affected active tests and live code to the new contract. User approves final spec, plan and tasks before code. 
