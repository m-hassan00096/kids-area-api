# Feature 04 — Roles & Permissions Management: Tasks

> **Status:** APPROVED task breakdown (2026-11-26). **4/44 preparation tasks completed; all 12 acceptance criteria Not Run.** User approved the documented decisions, plan and limited isolated forward-drop test. Pre-drop test infrastructure authored; no F04 migration or verified runtime results yet. Prior F03 green CI is baseline only.
> **Sources:** [spec.md](spec.md), [plan.md](plan.md). Dependencies are numbered Txx (ranges inclusive); acceptance IDs abbreviated F04-xx. Do not treat review drafts or authored tests as completed tasks.
> **Gate:** D01–D08 and spec/plan/tasks approved; guarded disposable CI forward-drop testing authorized only. New destructive reset/down procedures are not authorized. Documentation gate passed; implementation authorized. Do not execute against a real or unknown DB.

## Phase A — Review and preparation (T01–T06)
- [x] **T01 — Review approved baseline and decisions.** Verify develop/PR #10 exact merge, lock, migrations, canonical specs and affected alias call sites; approve D01-D08. No implementation.
- [x] **T02 — Approve specification, plan and task scope.** Review docs PR; record explicit user approvals, no speculative scope or new dependencies.
- [x] **T03 — Authorize disposable destructive migration verification.** User authorized only the guarded CI forward DROP; record exact environment, required synthetic backup/export and preservation checks. No new reset/down procedures or real DB. Actual guard/fixture verification belongs to T05–T07.
- [x] **T04 — Documentation gate and implementation branch.** Merge approved docs only on explicit authorization; verify tree; create feature branch from develop after implementation approval.
- [ ] **T05 — Inspect actual schema, collation and index metadata.** Read-only MySQL metadata in guarded disposable CI; exact role unique/FK/index names, collation equivalence, permission/morph guard and sessions owner index.
- [ ] **T06 — Migration safety design and synthetic legacy fixture.** Fixture baseline roles (including differing aliases), grants, active/inactive members, sessions and permission aliases; snapshot values and backup/recovery proof.

## Phase B — Single Role name and backward regression (T07–T12)
- [ ] **T07 — Forward migration remove roles.alias_name only.** Preserve historical migration; exact one-column change and no synthetic down restoration; verify schema/row/pivot/session preservation on isolated historical upgrade.
- [ ] **T08 — Seeders and bootstrap compatibility.** Role seeds no alias; permission alias kept; repeat seeding preserves role names/state/grants, bootstrap prerequisites and permanent identity.
- [ ] **T09 — Auth context and MeResource projection.** Remove Role alias field only; retain exact authentication/permissions/eligibility and no User ID.
- [ ] **T10 — Users query and Resource compatibility.** Remove role alias projection/output; exact previous list/detail/nullable role and performance shape otherwise unchanged.
- [ ] **T11 — Role Select labels and baseline fixture migration.** ID and eligibility stable, label name; adjust actual role-only fixtures/assertions across prior tests and guarded probes, preserve permission aliases.
- [ ] **T12 — Baseline regression checkpoint after naming change.** F01/F02/F03 focused and full suite, schema/seed/Me/Users/Select checks; actual upgrade and fresh CI evidence.

## Phase C — Role transport, reads and Select Registry (T13–T22)
- [ ] **T13 — Role route inventory and protected transport.** Five independent routes, native Cookie/CSRF/Origin/CORS, numeric ID contract, no target resolution before capability.
- [ ] **T14 — Role capability and trusted target binding.** Independent authority from fresh context, exact guard/ID, stale and wrong guard handling; no hidden manager bypass.
- [ ] **T15 — Role index input validation.** Allowlisted filters, literal search, state, bounded page/perPage, reject malformed/unknown sort/include.
- [ ] **T16 — Mutation input contract and Arabic validation.** Strict JSON shape, name length/content/reserved/collation, typed bool, required list including [], no alias/guard/ID injection.
- [ ] **T17 — Permission catalogue resolver.** Exact registered web names, strict uniqueness/guard and safe missing/anomalous state handling, no auto-seed.
- [ ] **T18 — Role index query and batched counts.** Snapshot count/page/count maps, no N+1, exact count/ordering/status and out-of-range semantics.
- [ ] **T19 — Detail query and Role Resource.** Snapshot detail counts and ordered permission names/aliases, exact safe projection and computed protected flag.
- [ ] **T20 — Actual read HTTP integration.** Authorized index/show, forbidden lookup count, valid/invalid filters, exact Resources.
- [ ] **T21 — PermissionOptionsProvider and registration.** Fixed approved keys, stored aliases, independent store/update purpose authorization, exact guard and deterministic options.
- [ ] **T22 — Central Select Registry key limit and tests.** Max three, all-or-nothing validation/auth before dispatch; bounds/overflow/empty, preserve existing keys and ordering.

## Phase D — Transactions and write Actions (T23–T32)
- [ ] **T23 — Current actor/session boundary refactor review.** Factor minimal shared authority primitive or dedicated Role boundary, do not call Users-target-specific lock unchanged; preserve F01-F03 contracts.
- [ ] **T24 — Role transaction/lock/outcome boundary.** Manager mutex, compatible ordered rows/session locks, one attempt, timeout restore/disconnect, last-admin postcondition, known/unknown outcomes.
- [ ] **T25 — Known constraint and safe error mapping.** Reviewed role name 1062 -> 422, invariant conflicts, 1205/1213/connectivity/unknown commit -> 503; safe unknown errors.
- [ ] **T26 — CreateRoleAction with atomic grants.** Use native package, exact D01 approved grant scope, optional inactive per D03, no implicit assignment or direct grants.
- [ ] **T27 — Protected role and custom rename lifecycle.** Protect manager/reception exact names and state; custom unique name stable ID, no session changes on rename.
- [ ] **T28 — Role-disable revocation primitive.** All affected users incl inactive, safe version bounds, owner-index session deletion, single transaction, no account active-state changes.
- [ ] **T29 — UpdateRoleAction grant replacement and status.** Atomic name/state/grants, disable only transition, initiating self-revoked handling after commit, re-enable no old sessions.
- [ ] **T30 — DeleteRoleAction membership protection.** Zero assignments including inactive users, fail closed orphan/foreign morph, serialized native deletion, no permission definition deletion.
- [ ] **T31 — Permission cache and commit-failure behavior.** Native package + postcommit/rollback eviction, cross-process visibility, safe known/unknown outcome, no blind retries.
- [ ] **T32 — Wire write controllers and actual HTTP tests.** End-to-end JSON, all five independent capabilities, protected/error/validation cases, grant scope and last-admin.

## Phase E — Sessions, concurrency, faults, security, scale (T33–T40)
- [ ] **T33 — Role lifecycle session matrix.** Affected/all devices/inactive users, unaffected others, no-change edit, disable->enable, self-role disable, stale native saves and old session rejection.
- [ ] **T34 — Real MySQL race: unique names and role assignment/delete.** Independent connections/barriers both orderings; actual constraint or current-state conflict; no cascaded assignment loss.
- [ ] **T35 — Real MySQL race: role disable/Login/Profile.** Both winner orderings, concurrent enable/assignment, old credentials/session generation non-restoration, stale actor rejection.
- [ ] **T36 — Real MySQL race: grants/last admin and Users mutations.** Competing administrative changes across Role/User endpoints, role grants revoked while actor waits, last viable custom path, exact rollback.
- [ ] **T37 — Fault injection and transaction outcomes.** Actual DB/pivot/session failure, native deadlock/timeout, cache/commit acknowledgement probes; state checks and redaction, no 500-only proof.
- [ ] **T38 — Security and boundary regression.** Native CSRF/origin and capability ordering, wrong guard/anomalies, request IDs, safe Arabic responses, no sensitive bindings, no production probes.
- [ ] **T39 — Scale fixture and actual list query plans.** 10/100/200 roles and 100/1k/10k users, 15/100 pages, search/filters/skew, EXPLAIN+ANALYZE, count/query ceiling.
- [ ] **T40 — Role-disable performance and cache measurement.** Synthetic 1/100/1k target users, measured lock/query/memory/commit; no speculative new index or live SLA.

## Phase F — Full verification and review (T41–T44)
- [ ] **T41 — Final schema/routes/scope and dependency regression.** Only one forward column removal and five Role routes, exact ten permissions, no new packages/tables/modules, previous F03 behavior apart from approved shape.
- [ ] **T42 — Scoped CI discovery and full regression.** Guarded real MySQL upgrade/fresh as authorized, Role focused, existing Auth/Identity/Users/Avatar/Select, full PHPUnit, Pint, Composer audit/platform, route cache/scope.
- [ ] **T43 — Acceptance reconciliation and draft PR evidence.** Inspect exact head and logs; map 12 criteria to actual assertions and measured evidence, close only verified tasks; record risks and blockers.
- [ ] **T44 — Final handoff and review gate.** Update same handoff and PR with exact counts/links, final doc-only CI if needed; request explicit merge approval, no deployment.

## Dependencies and evidence matrix

| Task | Depends on | Acceptance | Completion evidence |
| --- | --- | --- | --- |
| T01 | — | F04-01–12 | Verify develop/PR #10 exact merge, lock, migrations, canonical specs and affected alias call sites; approve D01-D08. No implementation. |
| T02 | 01 | All | Review docs PR; record explicit user approvals, no speculative scope or new dependencies. |
| T03 | 02 | 11,12 | Exact CI host/database/principal/APP_ENV guard, backup/export synthetic legacy aliases; separately authorize DROP and any isolated reset/rollback. No real DB. |
| T04 | 02,03 | 12 | Merge approved docs only on explicit authorization; verify tree; create feature branch from develop after implementation approval. |
| T05 | 04 | 02,04,11 | Read-only MySQL metadata in guarded disposable CI; exact role unique/FK/index names, collation equivalence, permission/morph guard and sessions owner index. |
| T06 | 03,05 | 11 | Fixture baseline roles (including differing aliases), grants, active/inactive members, sessions and permission aliases; snapshot values and backup/recovery proof. |
| T07 | 06 | 11 | Preserve historical migration; exact one-column change and no synthetic down restoration; verify schema/row/pivot/session preservation on isolated historical upgrade. |
| T08 | 07 | 11,12 | Role seeds no alias; permission alias kept; repeat seeding preserves role names/state/grants, bootstrap prerequisites and permanent identity. |
| T09 | 07 | 11,12 | Remove Role alias field only; retain exact authentication/permissions/eligibility and no User ID. |
| T10 | 07 | 11,12 | Remove role alias projection/output; exact previous list/detail/nullable role and performance shape otherwise unchanged. |
| T11 | 07 | 10,11,12 | ID and eligibility stable, label name; adjust actual role-only fixtures/assertions across prior tests and guarded probes, preserve permission aliases. |
| T12 | 08–11 | 11,12 | F01/F02/F03 focused and full suite, schema/seed/Me/Users/Select checks; actual upgrade and fresh CI evidence. |
| T13 | 04,12 | 06,12 | Five independent routes, native Cookie/CSRF/Origin/CORS, numeric ID contract, no target resolution before capability. |
| T14 | 13 | 06 | Independent authority from fresh context, exact guard/ID, stale and wrong guard handling; no hidden manager bypass. |
| T15 | 13 | 01 | Allowlisted filters, literal search, state, bounded page/perPage, reject malformed/unknown sort/include. |
| T16 | 13 | 02,03,05,06 | Strict JSON shape, name length/content/reserved/collation, typed bool, required list including [], no alias/guard/ID injection. |
| T17 | 16 | 05 | Exact registered web names, strict uniqueness/guard and safe missing/anomalous state handling, no auto-seed. |
| T18 | 05,15 | 01 | Snapshot count/page/count maps, no N+1, exact count/ordering/status and out-of-range semantics. |
| T19 | 14,18 | 01,05 | Snapshot detail counts and ordered permission names/aliases, exact safe projection and computed protected flag. |
| T20 | 13–19 | 01,06 | Authorized index/show, forbidden lookup count, valid/invalid filters, exact Resources. |
| T21 | 11,17 | 10 | Fixed approved keys, stored aliases, independent store/update purpose authorization, exact guard and deterministic options. |
| T22 | 21 | 10 | Max three, all-or-nothing validation/auth before dispatch; bounds/overflow/empty, preserve existing keys and ordering. |
| T23 | 12,14 | 06,08,09 | Factor minimal shared authority primitive or dedicated Role boundary, do not call Users-target-specific lock unchanged; preserve F01-F03 contracts. |
| T24 | 23 | 06,08,09 | Manager mutex, compatible ordered rows/session locks, one attempt, timeout restore/disconnect, last-admin postcondition, known/unknown outcomes. |
| T25 | 05,16,24 | 02,04,05,09 | Reviewed role name 1062 -> 422, invariant conflicts, 1205/1213/connectivity/unknown commit -> 503; safe unknown errors. |
| T26 | 17,24,25 | 02,05,08 | Use native package, exact D01 approved grant scope, optional inactive per D03, no implicit assignment or direct grants. |
| T27 | 16,24,25 | 03,04,08 | Protect manager/reception exact names and state; custom unique name stable ID, no session changes on rename. |
| T28 | 23,24 | 07,08,09 | All affected users incl inactive, safe version bounds, owner-index session deletion, single transaction, no account active-state changes. |
| T29 | 17,24,27,28 | 03,05,07,08 | Atomic name/state/grants, disable only transition, initiating self-revoked handling after commit, re-enable no old sessions. |
| T30 | 24,25,27 | 04,08,09 | Zero assignments including inactive users, fail closed orphan/foreign morph, serialized native deletion, no permission definition deletion. |
| T31 | 24,26,29,30 | 05,06,09 | Native package + postcommit/rollback eviction, cross-process visibility, safe known/unknown outcome, no blind retries. |
| T32 | 26–31 | 02–08 | End-to-end JSON, all five independent capabilities, protected/error/validation cases, grant scope and last-admin. |
| T33 | 28,29,32 | 03,07,08 | Affected/all devices/inactive users, unaffected others, no-change edit, disable->enable, self-role disable, stale native saves and old session rejection. |
| T34 | 24,26,27,30,32 | 02,04,09 | Independent connections/barriers both orderings; actual constraint or current-state conflict; no cascaded assignment loss. |
| T35 | 28,29,33 | 07,09 | Both winner orderings, concurrent enable/assignment, old credentials/session generation non-restoration, stale actor rejection. |
| T36 | 24,29,30,32 | 06,08,09 | Competing administrative changes across Role/User endpoints, role grants revoked while actor waits, last viable custom path, exact rollback. |
| T37 | 31,32,33 | 05,07,09 | Actual DB/pivot/session failure, native deadlock/timeout, cache/commit acknowledgement probes; state checks and redaction, no 500-only proof. |
| T38 | 20,22,32–37 | 06,09,10,12 | Native CSRF/origin and capability ordering, wrong guard/anomalies, request IDs, safe Arabic responses, no sensitive bindings, no production probes. |
| T39 | 18,19,20 | 01,12 | 10/100/200 roles and 100/1k/10k users, 15/100 pages, search/filters/skew, EXPLAIN+ANALYZE, count/query ceiling. |
| T40 | 28,29,31,33,39 | 07,09,12 | Synthetic 1/100/1k target users, measured lock/query/memory/commit; no speculative new index or live SLA. |
| T41 | 12,22,32,38–40 | 11,12 | Only one forward column removal and five Role routes, exact ten permissions, no new packages/tables/modules, previous F03 behavior apart from approved shape. |
| T42 | 03,41 | 12 | Guarded real MySQL upgrade/fresh as authorized, Role focused, existing Auth/Identity/Users/Avatar/Select, full PHPUnit, Pint, Composer audit/platform, route cache/scope. |
| T43 | 34–42 | All | Inspect exact head and logs; map 12 criteria to actual assertions and measured evidence, close only verified tasks; record risks and blockers. |
| T44 | 43 | All | Update same handoff and PR with exact counts/links, final doc-only CI if needed; request explicit merge approval, no deployment. |

## Acceptance status

| Scenario | Status |
| --- | --- |
| F04-01 | Not Run |
| F04-02 | Not Run |
| F04-03 | Not Run |
| F04-04 | Not Run |
| F04-05 | Not Run |
| F04-06 | Not Run |
| F04-07 | Not Run |
| F04-08 | Not Run |
| F04-09 | Not Run |
| F04-10 | Not Run |
| F04-11 | Not Run |
| F04-12 | Not Run |

## Completion rules

Record actual commit, command, CI run/job, pass/fail counts, relevant assertions and limitations. No task marked done merely because its code or a test was written. No unsafe data deletion or scope change; no weaken existing assertions. Final required CI exact head must succeed before Ready for Review; no merge without separate explicit user approval, no production deployment. Keep prior F02 intermittent observation risk, deferred Audit and real-host/backup/restore caveats in final report. Next action: verify actual T05/T06 preflight job logs and synthetic backup evidence; then author and verify forward migration and compatibility changes. The new migration has not executed.


## Approval evidence (2026-11-26)

T01: verified PR #10 merged at develop 41b5eeca7e9b04eb7b064f93464b290754fea16a, reviewed actual pinned dependencies, historical migrations, current Identity services and role-only alias call sites, canonical documents and the eight decision defaults. T02: the user replied affirmatively to the explicit request to approve spec.md, plan.md, tasks.md and proposed decisions. T03: the same reply authorized the specifically requested test of removing roles.alias_name only inside isolated GitHub Actions MySQL 8 test DB. Existing CI guard and guarded IdentityDatabaseTestCase fixture initialization inspected; planned pre-drop export and preservation checks remain T05–T07, not claimed executed. No new destructive reset/down procedures authorized; use fresh CI lifecycle and existing approved fixture setup. No code, real DB, merge or deployment. All 12 acceptance criteria Not Run. T04 requires exact head green CI and explicit documentation merge/implementation approval. Historical F03 test results are not F04 evidence.


## Implementation checkpoint — T04 verified; T05/T06 authored (2026-11-26)

User explicitly authorized docs merge and implementation. Exact docs head 772d084d8becccb52dc4166f42b7e69b29d4f6ae [CI 45818002441](https://github.com/m-hassan00096/kids-area-api/actions/runs/45818002441) completed green, job 139902613229: full 174/9042, Authentication 41/1446, Identity 56/828, focused 73/6757, Pint 159; all steps passed. PR #11 merged at 92f8845a5cb15526965448eab93735902936d612, verified tree identical to reviewed head and develop ref exact; implementation branch feature/identity-roles-management created from that merge. T04 complete. Added only CI/test support preflight: fresh legacy schema after reviewed existing migrations, recheck GitHub Actions/APP_ENV/host/DB/principal/DB_URL/MySQL8/InnoDB, verify actual column/index/FK metadata and collation equivalence, synthetic legacy roles and changed permission alias, four Users with distinct active state/generation and two actual native authoritative browser sessions, export aliases and per-table non-alias state fingerprints to exclusive private runner-temp file and verify round-trip. No new reset/down/drop, no production code, no real DB. T05/T06 remain open until exact CI logs inspected; backup round-trip is not actual production restore or forward preservation proof. T07 and all acceptance criteria Not Run.
