# Feature 03 — Users Management: Execution Tasks

> **Status:** APPROVED — implementation authorized; PR #9 merged, draft implementation PR #10 open; Phase A verified.
> **Approved inputs:** [spec.md](spec.md) and [plan.md](plan.md). Technical plan accepted by user reply “تمام” to the preceding plan review; not proof of feasibility.
> **Owner:** Identity and shared Core HTTP/Select Registry.
> **Baseline:** develop `aa931b65fb4799086151e9d32d9a7371773dadb5` (PR #9 merged); active `feature/identity-users-management` created from that exact ref. Pre-merge CI 45652428628: 102 tests / 2268 assertions; Pint 101.
> **Progress:** 45/49 task-level verified T01–T45; T46–T49 open. See §25.1, T43–T44 closure and T45 measured performance evidence. Full feature gate not met; 19 full acceptance scenarios Not Run; no merge.

## 1. Gates and evidence rules

Task-list review explicitly approved on 2026-11-25. User subsequently authorized temporary isolated GitHub Actions PHP8.2/GD/fileinfo T01–T04 preliminary feasibility only (see §13); any further material design change/probe requires appropriate separate review, no live host, no feature code or probe code on docs branch. T02 native platform and T03/T04 multipart/image safety are hard readiness gates; apply approved parsed-file boundary and verify stronger format-aware structure before decode; record any remaining counterexample, no silent weakening, custom parser or global host change. Isolated evidence does not certify live hosting. T05 separate docs merge and implementation approvals; no feature code before material unknowns resolved. T06 separate exact dependency/platform approval; stop on unapproved solver change. T07 verify isolated testing and separate protected disposable reset/cleanup authorization. No production operations or sensitive data. Dependencies use IDs/ranges inclusive. Completion requires actual tests/source/CI evidence, not test files or checkboxes. No required check waived; no Ready for Review on failed/unrun checks. Test authoring can accompany code but mark complete only after execution. Preserve approved contracts and scope.

## 2. Phase A — Approval, preliminary feasibility and preparation (T01–T07)

- [x] **T01 — Review gates and environment.** Dependencies: —. Verify docs PR #9, actual develop and reviewed task list; obtain explicit scoped authorization for temporary isolated T02–T04 probes. Identify verified disposable PHP8.2 runtime and test scope, no live data, no changes to docs branch code. Evidence: Recorded approvals, refs and isolation evidence.

- [x] **T02 — Native platform feasibility.** Dependencies: T01. Inspect fileinfo/GD PNG/JPEG/WebP decode/encode, native Argon2id, PHP limits, private temp root and synthetic image smoke/memory. No platform installation or actual host change without separate approval. Evidence: PHP version, gd_info, codecs/limits/memory results; missing support blocks readiness.

- [x] **T03 — Raw multipart feasibility.** Dependencies: T01. Real disposable native PHP8.2 HTTP probe with duplicate scalar avatar wire parts, bracketed/mixed shapes and upload/body limits. Verify observable parsed data and native method override feasibility. Never infer duplicate detection from FormRequest count. Evidence: Actual parser evidence; prove acceptance/rejection of native visible shapes and retained upload; collapsed raw duplicates outside the explicitly approved detection guarantee; no custom parser or global host change silently.

- [x] **T04 — Image validation feasibility.** Dependencies: T02,T03. Synthetic valid exact2MiB and malformed/truncated/animated/dual-format/trailing fixtures, dimension/pixel/codec bounds and canonical encoding; verify JPEG marker/segment/scan handling including real EOI, PNG/WebP static container checks, and measured isolated memory. No universal polyglot claim. Evidence: Actual evidence or blocking decision request; no silent relaxation of F03-14.

- [x] **T05 — Resolve blockers and verify implementation gates.** Dependencies: T01–T04. Resolve material unknowns and explicitly review any necessary plan/spec changes. Obtain separate docs merge approval, verify PR #9 latest CI/diff, merged develop; obtain explicit implementation permission and create dedicated branch from merged ref. Evidence: Resolved decisions, approval, merge SHA and actual branch; no feature code if unresolved.

- [x] **T06 — Authorized dependency resolution.** Dependencies: T05. Separate exact package/platform approval; dry-run composer require spatie/laravel-query-builder:^6.4.4 --dry-run --no-interaction --prefer-dist --no-scripts, review 6.4.4 and mandatory package-tools ^1.11 candidate, stop on unapproved changes; then approved actual require, authentic lock, validate --strict, locked install, platform/audit. GD/fileinfo requirement and CI as approved; no image package. Evidence: Actual solver/lock diff, installation and audit/platform evidence.

- [x] **T07 — Guarded fixtures and baseline regression.** Dependencies: T05,T06. Verify APP_ENV testing, localhost MySQL8/InnoDB kids_area_test/kids_area_ci, synthetic accounts/private temp root; separate exact destructive disposable reset approval, independent HTTP/cookie jars/shared clock/barriers; run baseline regression. Evidence: Actual guarded DB/FS, baseline test results, no production operations.

## 3. Phase B — Schema and shared HTTP boundary (T08–T13)

- [x] **T08 — Avatar reference migration.** Dependencies: T07. One forward module migration users.avatar_key VARCHAR(40) ASCII/bin nullable UNIQUE default NULL; hidden internal key, no other schema/pivot/index changes. Evidence: Reviewed migration and schema check.

- [x] **T09 — Migration preservation.** Dependencies: T08. Forward migrate existing synthetic founder/users/hashes/roles/sessions, verify DDL, collation, multiple NULL and duplicate key denial; destructive down only approved disposable fixture. Evidence: Actual MySQL preservation/constraints F03-19.

- [x] **T10 — Protected routes and CORS.** Dependencies: T07. Exact approved CRUD/avatar GET/selects GET; first-party gate users root/subpaths/selects, canonical origin, DELETE CORS, existing Sanctum/StaffSession, native CSRF/preflight and approved multipart override only; no bearer. Evidence: Route cache/inventory and real HTTP 401/403/419/preflight F03-07.

- [x] **T11 — Capability before target lookup.** Dependencies: T10. Fresh staff context, independent view/create/update/delete, numeric target explicitly resolved after permission, no Manager bypass or implicit binding enumeration, original Request attributes. Evidence: Permission-before-query and missing target tests F03-07/08.

- [x] **T12 — Scoped normalization and API errors.** Dependencies: T10,T11. Preserve exact username/email/phone/password/confirmation; trim name/search only, strict types and multipart normalization, approved empty/null semantics, safe 413/422/409/503 and known package errors, Arabic Request ID; F02 behavior unchanged. Evidence: Real type/whitespace/query/error tests F03-05/06/07/18.

- [x] **T13 — Sensitive diagnostics.** Dependencies: T10,T12. Scope suppression to Users writes and PII/key/session queries, preserve native private handler and unrelated DB events; no raw password/SQL bindings/multipart/path/secret logging. Evidence: Sentinel/log/event restoration evidence F03-18.

## 4. Phase C — Users reads and Select Registry (T14–T20)

- [x] **T14 — Index query validation.** Dependencies: T11,T12. Allow only scalar search/roleId/isActive, page/perPage, max255 search, positive integer overflow check, default15 clamp100, preserve 0/false, reject unknown filters/query/sort/include/fields 422. Evidence: Input matrix F03-02/03.

- [x] **T15 — Spatie allowed filters and bounded query.** Dependencies: T06,T08,T14. Validated custom callbacks preserve comma/true/false/0 despite package coercion; literal escaped LIKE !/%/_ grouped name/username/phone AND role EXISTS/status, exact morph/guard, primary read, id DESC, identical count/page predicates and eager role load; short consistent read snapshot if needed, no global delimiter changes. Evidence: MySQL literal semantics/SQL assertions F03-02/03.

- [x] **T16 — Explicit resources.** Dependencies: T08,T11,T15. Exact userId/avatarUrl/name/username/phone/isActive/role, email only detail, no timestamp/secret, role null on anomaly, no arbitrary first/no per-row file stat; approved pagination, Me unchanged. Evidence: Exact key sets and query evidence F03-01/03.

- [x] **T17 — Index/detail HTTP tests.** Dependencies: T14–T16. Arabic/name, exact username case, phone leading zero, wildcard/comma/boolean words, inactive role/state/false, filters AND, unknown role, malformed, empty/out-of-range/default/max100, permission before lookup. Evidence: Real HTTP/MySQL assertions F03-01/02/03/07.

- [x] **T18 — Core Select Registry.** Dependencies: T10–T12. Explicit registered providers, validate exact ordered unique keys max2, all authorized before dispatch, no partial results/dynamic classes/new permissions. Evidence: Registry authorization/dispatch proof F03-17.

- [x] **T19 — Identity role options.** Dependencies: T18. roles active web create OR update; userFilterRoles all web view; numeric ID/stored alias ordered ID ASC, max200 per provider limit201 probe, overflow409 entire response, no permissions key. Evidence: Provider SQL/options/bounds tests F03-17.

- [x] **T20 — Select HTTP tests.** Dependencies: T18,T19. Create-only/update-only/view-only, mixed forbidden no provider executes, unknown/duplicate/malformed, requested order, empty and limit+1, no partial output. Evidence: Actual response/provider checks F03-17/18.

## 5. Phase D — Private Avatar infrastructure (T21–T25)

- [x] **T21 — Private storage and keys.** Dependencies: T02–T06,T08,T13. Dedicated local identity-avatars root, no public link/serve, canonical immutable random32hex+extension key, exclusive creation and checked write/close/size, no client path/filename. Evidence: Temp root, collision/no-overwrite/private path tests F03-14/15/16.

- [x] **T22 — Native image validation and normalization.** Dependencies: T04,T21. Proven fileinfo/GD and reviewed structural checks: 2MiB input/output, PNG/JPEG/WebP matched extension, static max2048x2048/4.19MP, longest edge512 preserve ratio/transparency no upscale/crop, strip metadata canonical encode. Reject invalid/animated/unsafe; follow resolved gates. Evidence: Actual valid/invalid formats, dimensions, memory and output bounds F03-14.

- [x] **T23 — Staged file lifecycle.** Dependencies: T21,T22. Stage complete file before locks; capture old key under lock; known rollback candidate cleanup only unreferenced, commit old cleanup only after primary reference check, ambiguity retain both; no in-flight/other-user/age-only deletion. Document quiesced backup/reconciliation. Evidence: Storage failure and safe reference checks F03-16.

- [x] **T24 — Private avatar read.** Dependencies: T11,T16,T21. Authorized primary current key; bounded bytes before response, fixed MIME inline/private no-store/nosniff; absent user/key404, missing referenced file500 with one reread on replacement race; no streaming lock/public path. Evidence: Actual binary/access/header/missing/replacement tests F03-15.

- [x] **T25 — Real native transport tests.** Dependencies: T03,T04,T10,T21,T22,T24. Real PHP8.2 HTTP multipart POST/create and POST _method=PUT update feasibility/transport probe; approved parsed-file shape enforcement, unknown/mixed parts, upload errors exact2MiB/+1/body limit, mismatched format, CSRF/preflight. Repeat on actual CRUD T35/T36; no production probe route. Evidence: Raw multipart and codec proof F03-07/14/15.

## 6. Phase E — Transactional user management (T26–T37)

- [x] **T26 — Create/update Form Requests.** Dependencies: T11,T12,T22,T25. Strict allowlists, full PUT ordinary fields/contact presence, approved username/email/phone exact, password>=8/confirmation/preserve/orphan rules, strict role/boolean/avatar/removal, no ID/status override/direct grants/currentPassword/paths; authorized early founder/self checks. Evidence: Validation/type/presence tests F03-04/05/06/08/14.

- [x] **T27 — Known constraint errors.** Dependencies: T08,T12,T26. Primary unique validation excluding trusted target; map verified MySQL1062 named username/email/phone indexes to field422 after rollback, relevant protected FK1451 to409, unknown500, no sensitive values/ambiguous retry. Evidence: Actual constraints/error classification F03-05/13/18.

- [x] **T28 — Extend IdentityAccessMutation.** Dependencies: T07,T11,T13,T27. Reuse manager mutex/ordered access rows, one outer InnoDB transaction attempts1, permanent roles and six-capability postcondition; expose commit/rollback/unknown state, preserve F01 callers; postcommit cache failure not rollback; 5s per conflicting lock restore prior setting/preserve primary error/discard uncertain connection. Evidence: Mutation regression and timeout/outcome proof F03-12/16/19.

- [x] **T29 — Current actor and initiating session.** Dependencies: T28. Under manager mutex lock current access/target rows then actual session row, owner/version/current user/idle valid iff last_activity > now-43200; current permission; stale401 vs missing capability403, retire only invalid binding after rollback, original request, no reverse limiter locks. Evidence: Current-read/idle/actor tests F03-07/10/12.

- [x] **T30 — Reusable revocation.** Dependencies: T28,T29. Generation-only revoke or optional new Argon2id hash and target-only session deletion in transaction, compare version/no wrap/exact row; combined disable+reset one increment; reenable no restore; Profile compatibility. Evidence: Hash/version/session/Profile assertions F03-10/11.

- [x] **T31 — CreateUserAction.** Dependencies: T23,T26–T30. Founder exists/new ID!=1; active single web role syncRoles, explicit fields, hash/stage outside locks, active true, postcondition and known outcome; 201 minimal, duplicate rollback cleanup. Evidence: User/pivot/hash/file/rollback proof F03-04/05/12/14/16.

- [x] **T32 — UpdateUserAction.** Dependencies: T23,T26–T30. Full fields, founder/self-password, inactive retain/new active/reenable rules, roleless repair, no multi/direct grant auto-repair; preserve/reset password and preserve/remove/replace avatar, capture old key under lock; revoke only disable/reset, postcondition. Evidence: Atomic state/role/version/file and 200 F03-05/06/08–12/14/16.

- [x] **T33 — DeleteUserAction.** Dependencies: T23,T27–T30. Block founder/all self deletes; independent permission, native own membership removal and target session delete, actual RESTRICT conflict409, no historical cascade, last-admin check and postcommit file cleanup. Evidence: Target-only delete/rollback proof F03-08/12/13/16.

- [x] **T34 — Postcommit and self-disable lifecycle.** Dependencies: T28–T33. Known commit clear self-revoked device and suppress touch, stale native save no authority, active role preserves session, unrelated device unaffected; distinguish cache/cleanup failure and ambiguous commit, no retry. Evidence: Browser/final save/cache/cleanup proof F03-09/10/11/16/18.

- [x] **T35 — Create/update HTTP integration.** Dependencies: T25,T31,T32,T34. Actual JSON/multipart create and update/201/200, validated fields/duplicates, reset/preserve/self rejection, avatar optional/replace/remove/invalid retains old, native CSRF/origin and independent permission. Evidence: HTTP/DB/filesystem assertions F03-04–07/14–16.

- [x] **T36 — Founder, role and revocation tests.** Dependencies: T30–T32,T34,T35. Founder/self rules incl image/reset, active/inactive role rules and anomaly denial, fresh Me/grants, all-device disable/reenable, combined increment, unrelated devices/Profile unchanged. Evidence: Real multi-cookie/DB state F03-08–12.

- [x] **T37 — Delete and FK tests.** Dependencies: T33,T34. Authorized nonself/nonfounder, disposable actual FK RESTRICT fixture and 1451 conflict retaining row/pivots/session/file, no role/other account removal; no synthetic production table. Evidence: Real MySQL/HTTP evidence F03-13/16.

## 7. Phase F — Concurrency, faults, security and performance (T38–T46)

- [x] **T38 — Independent race harness.** Dependencies: T07,T28–T34. Real HTTP server processes/cookie jars, independent MySQL connection IDs, shared clock and deterministic barriers observed contention, designated shared private temp root; test-only fault hooks. Evidence: Independent connections and observed interleavings, not sleep-only.

- [x] **T39 — Concurrent uniqueness/roles.** Dependencies: T31,T32,T38. Overlapping duplicate create/update username/email/phone, one valid outcome, safe422 no partial user/pivot/file; competing singular role writes. Evidence: Actual lock/constraint/final state F03-05/09/16.

- [x] **T40 — Last-admin and stale actor races.** Dependencies: T29,T32,T33,T38. Concurrent disable/delete/role mutations threaten six capabilities incl custom role, stale actor permission/session revoked during wait, Logout first vs mutation first, exact idle cutoff, no zero viable admin. Evidence: Both commit orders and rollback F03-08/12/13.

- [x] **T41 — Credential and stale session races.** Dependencies: T30,T32–T34,T38. Both Login vs disable/reset/delete orders, Profile/admin reset, delayed native reinsert, self-disable/reenable, active role no forced logout; unrelated sessions safe. Evidence: Real HTTP/MySQL hash/generation/marker evidence F03-09–11/13.

- [x] **T42 — Avatar and FK races.** Dependencies: T23,T24,T32,T33,T38. Replacement/replacement, replacement/delete, read/replacement/delete, FK insert/delete race; final reference and bytes valid, cleanup only unreferenced. Evidence: Observed interleavings/DB/FS evidence F03-13/15/16.

- [x] **T43 — Fault injection and uncertain outcome.** Dependencies: T23,T28,T31–T34,T38. Stage/write/close failure, duplicate/FK/last-admin/hash/session/pivot/reference rollback, real commit with simulated lost acknowledgement/disconnect, cache and cleanup faults, lock timeout plus restore failure; retain possibly referenced keys, no blind retry. Evidence: Actual synthetic fault proof, no false rollback F03-05/11/12/13/16.

- [x] **T44 — Security and log regression.** Dependencies: T13,T20,T25,T35–T37,T43. Real origin/CSRF/override/DELETE/permission ordering, forbidden mixed selects, no public path, malformed input, Arabic Request ID and no secrets/path/raw SQL/multipart log; preserve native session redaction and business events. Evidence: Actual safe boundary assertions F03-07/14/15/17/18.

- [x] **T45 — Measure performance and EXPLAIN.** Dependencies: T15–T17,T22,T38–T44. Synthetic 100/1000/10000 accounts; count/page/order/offset and search/role/state EXPLAIN, 15/100 records max3 domain queries, auth separate, no N+1/file stat; image IO/peak memory full Argon2id, lock metrics. New index only measured need + reviewed approval. Evidence: Actual plans/counts/times/memory; no SLA F03-03/19.

- [ ] **T46 — Scope and F01/F02 regression.** Dependencies: T09,T17,T20,T35–T45. Only adjust superseded exact schema/routes absence checks; preserve auth and all ten permissions, approved column/index/route inventory, no new table/module/role CRUD/permissions select/token/audit/HR/queue/Redis/cron. Evidence: Legacy regression/schema/diff/route cache proof F03-19.

## 8. Phase G — CI and handoff (T47–T49)

- [ ] **T47 — Scoped CI checks.** Dependencies: T06,T46. Explicit GD/fileinfo/codec checks, guarded test disk, focused UsersManagement/Selects discovery, preserve Auth/Identity/full, locked Composer/migration/Pint/module/route-cache/scope. No deployment workflow. Evidence: Reviewed actual CI config F03-19.

- [ ] **T48 — Run checks and open implementation PR.** Dependencies: T39–T47. Execute isolated focused/full/Pint/platform/migration checks, fix failures without waivers, verify actual diff; open draft PR to develop and link evidence. No code on docs branch. Evidence: Actual command logs/PR SHA and blockers.

- [ ] **T49 — Final CI and evidence reconciliation.** Dependencies: T48. Inspect exact latest PR head/jobs/logs, reconcile 49 tasks/19 cases, actual metrics and review blockers, update handoff; keep not Ready if mandatory check missing/failed. Evidence: Actual CI proof, no merge/deploy without separate explicit approval.

## 9. Acceptance traceability

All approved cases in spec §11 and plan §8 remain Not Run.

| Scenario | Planned evidence tasks | State |
|---|---|---|
| F03-01 | T16–T17 | Not Run |
| F03-02 | T14–T17,T45 | Not Run |
| F03-03 | T14–T17,T45 | Not Run |
| F03-04 | T26,T31,T35,T43 | Not Run |
| F03-05 | T27,T35,T39,T43 | Not Run |
| F03-06 | T26,T32,T35–T36 | Not Run |
| F03-07 | T10–T13,T25,T29,T35,T44 | Not Run |
| F03-08 | T26,T29,T32–T37,T40 | Not Run |
| F03-09 | T32,T34,T36,T39,T41 | Not Run |
| F03-10 | T29–T30,T32,T34,T36,T41 | Not Run |
| F03-11 | T30,T32,T36,T41,T43 | Not Run |
| F03-12 | T28–T29,T31–T33,T36,T40,T43 | Not Run |
| F03-13 | T27,T33,T37,T41–T43 | Not Run |
| F03-14 | T02–T04,T21–T22,T25–T26,T35,T44 | Not Run |
| F03-15 | T21,T24–T25,T35,T42,T44 | Not Run |
| F03-16 | T23,T28,T31–T34,T39,T42–T43 | Not Run |
| F03-17 | T18–T20,T44 | Not Run |
| F03-18 | T12–T13,T20,T34–T35,T43–T44 | Not Run |
| F03-19 | T06–T09,T28,T45–T49 | Not Run |

## 10. Verification ledger

| Gate | Status |
|---|---|
| Spec and technical plan | Approved 2026-11-25; feasibility not proved |
| Task review | Approved — explicit user approval 2026-11-25 |
| T01–T04 isolated feasibility authorization/evidence | Explicitly authorized; target PHP8.2.36/GD2.3.3 runs 45650686580 and 45650969241 completed. T01–T04 passed preliminary feasibility; original blockers (§13) resolved by approved contract and follow-up proof (§14). |
| T05 documentation merge and implementation | Complete; PR #9 merged and implementation branch approved |
| T06 package/platform | Verified approved additions, CI 45673916280 success |
| T07–T09 guarded DB/FS/migration | T07 verified 45678335241; T08/T09 verified 45678811050 |
| T10–T37 HTTP/reads/selects/avatar/writes | T10–T37 verified |
| T38–T46 races/faults/performance/scope | Not Run |
| T47–T49 full tests/Pint/CI | Not Run |
| Implementation merge / deployment | Not authorized |

Risks: PHP8.2 parser observability and strict image structural validation; GD/host memory/upload limits; ambiguous commit and cache/file cleanup; manager-lock breadth R09; contains search scans; select cap; real TLS/cookie/DB/disk/backups/logs unverified. Audit deferred. Manual orphan reconciliation only authorized maintenance with backup/quiescence/current references, no age-only deletion.

## 11. Definition of done and next action

All 49 tasks and 19 scenarios actually verified, no material blocker, exact source/diff and latest full CI reviewed, docs/handoff updated. Separate merge/deploy approval. **Next:** T11 capability before target lookup, then T12/T13. No production or feature merge.

## 12. Preliminary local feasibility evidence — 2026-11-25

User replied “تمام كمل” after approval-recording follow-up and the proposed isolated preliminary T01–T04 checks. This was treated as permission to reconcile the approved documentation and perform narrow disposable preliminary inspection/probes only, not to install a PHP version/extension or implement Feature 03. Approval commit `76ed09ce88814578182b956bf3de7459146953bd` was successfully attached to the documentation branch; PR #9 remains open. Actual develop remains `8174097e93069cc20ae258ba236cf7acd8d37fb3`.

### Available environment and T01/T02

Isolated assistant container, no access to live application, real accounts, database or hosting. The installed CLI and built-in HTTP server are PHP 8.4.24 (only PHP 8.4 available), with fileinfo and Argon2id availability, **without GD**. Native default upload_max_filesize=2M, post_max_size=8M, memory_limit=-1; exploratory HTTP process used explicit temporary upload_max_filesize=2M, post_max_size=4M and memory_limit=128M. No PHP/extension/package installation or global config change. No target PHP8.2 runtime verified, no GD codec or image normalization/Argon2id memory benchmark completed. T01 and T02 remain unchecked.

### T03 exploratory real HTTP observations (PHP 8.4.24, not target PHP8.2)

Temporary local-only PHP server on 127.0.0.1, private disposable directory, synthetic 8x8 valid PNGs (77 bytes each) and a valid synthetic noisy 1024x1024 PNG (3,151,217 bytes). Raw multipart POST requests were sent; PHP parsed `$_FILES` and `$_POST` directly. No Laravel boot, CSRF, application validation, GD, database, or live host.

| Raw multipart case | Observed PHP result |
|---|---|
| One scalar avatar | One scalar first.png, upload error 0 |
| Repeated scalar avatar | Only second.png visible, error 0; first part collapsed |
| Two avatar[] parts | Both names visible as array |
| avatar[] followed by scalar avatar | Only second.png visible as scalar |
| Scalar avatar followed by avatar[] | Only second.png visible as one-element array |
| avatar and unknown extra file | Two separate visible file fields |
| _method=PUT plus avatar | Actual HTTP method POST; _method field PUT present; native PHP result only, not proof of Laravel method override |
| 3,151,217-byte image, file limit 2M | UPLOAD_ERR_INI_SIZE (1), reported size zero |
| Two oversized images, body 6,302,749 bytes, post limit 4M | Both POST and FILES empty |

Server stopped and temporary directory removed at end. These actual observations support the previously documented parser risk, but are **not** PHP8.2 certification and do not prove strict duplicate-part rejection or the approved Laravel contract. T03 remains unchecked. No exact 2 MiB valid boundary fixture, structural image rejection, GD canonical encode or peak-memory proof; T04 remains unchecked. All 19 acceptance scenarios remain Not Run; 0/49 tasks complete.

### Next isolated-environment decision

Recommend a separately authorized, temporary isolated GitHub Actions probe branch/runner using the project's existing setup-php approach with PHP 8.2 + gd/fileinfo, synthetic fixtures only, no application implementation/dependency install/database/production access and no merge. Existing CI uses setup-php@v2 with PHP8.2/fileinfo but does not explicitly request GD; this proposal is not a claim that GD or target runtime has been tested. Alternatively use an already provisioned verified disposable PHP8.2/GD environment. Obtain approval for the exact temporary platform setup before executing it. Re-run T02/T03/T04 on target runtime, including raw duplicate/mixed parts, actual image safety/limits and method override feasibility. If strict raw duplicate rejection is impossible with the native parser, return with evidence for a narrow explicit parsed-file contract or strict ingress design decision; no custom parser, package or contract change silently. T05 remains blocked. No docs merge or Feature 03 implementation authorized.

## 13. Authorized target-runtime feasibility — 2026-11-25

The user explicitly authorized temporary isolated GitHub Actions PHP 8.2/GD/fileinfo setup and preliminary T01–T04 probes, not Feature 03 implementation, PR #9 merge, production/hosting changes or project dependencies. Verified develop 8174097e93069cc20ae258ba236cf7acd8d37fb3 (PR #8 merged) and approved docs PR #9 head 68b873baa5f8220313f9c83e4a0e01a01dee7c75. Probe-only branch `probe/identity-avatar-php82` created directly from develop; workflow triggered on push to that exact branch. No PR opened for probe, no Composer install, Laravel boot, database/service, application migration or credentials/production access. Contents permission read, checkout did not persist credentials, synthetic fixtures generated in private mode 0700 disposable runner directory, local PHP HTTP server 127.0.0.1 and EXIT cleanup. No probe files on docs or develop.

| Run | Source commit | Result |
|---|---|---|
| [45650686580](https://github.com/m-hassan00096/kids-area-api/actions/runs/45650686580) / job 139334038477 | 3b148f9ed5eb740dbc88b322ae1a29c45740caae | Success: PHP8.2 platform, native GD, size/dimensions, structural sample checks and raw multipart |
| [45650969241](https://github.com/m-hassan00096/kids-area-api/actions/runs/45650969241) / job 139335029702 | 51b78e02dbcea26747ff6ca04a34902e4c610537 | Success: repeated observations, raw input observability, masked oversize, two-frame container fixtures and JPEG structural gap |

Success means probe completed, **not** the proposed policy passed all cases. Actual logs inspected. Temporary workflow and scripts removed with forward-only retirement commit `393c2c0cdb00c54268017b2f0dbef8dfe5d4ebbd`, whose tree exactly matches original develop tree ec4f0c083b9e691d37499ead5652302b32c8b261; branch retained solely for historical evidence, no active probe workflow at its HEAD. Both hosted runners completed and disposable server/files cleaned by EXIT trap. Neither docs nor develop contains executable probe artifacts.

### T01/T02 — platform and isolation verified

T01 complete: actual base/PR/approval and verified disposable PHP 8.2 runner recorded. T02 complete for this **isolated platform feasibility**, not production deployment: PHP 8.2.36 (CLI and cli-server), GD 2.3.3 with PNG/JPEG/WebP read/write, fileinfo and native Argon2id; explicit temporary memory_limit=128M, upload_max_filesize=2M, post_max_size=4M, private directory mode 0700. All three formats roundtripped with no warnings; PNG/WebP synthetic alpha sample 40 preserved. Static image 2048x1024 -> 512x256, bytes 10804 -> 1211; 2048x2048 -> 512x512, 21342 -> 2267; 2049x1 rejected before GD decode. Native valid 8x8 PNG with CRC-correct legitimate ancillary tEXt metadata constructed at exactly 2097152 bytes; GD accepted and re-encoded to 115 bytes, known metadata marker absent. At 2097153 bytes selected policy rejected. Small images remained 8x8. Hash+verify full Argon2id 65536 KiB, time cost 4, threads 1: 207.25 and 256.09 ms. PHP-managed peak 10489856 bytes, max Linux process RSS 129216 and 129640 KiB respectively; sequential image then Argon hash/verify, no app boot, process peak includes native allocations. Do not equate PHP memory_limit or memory_get_peak_usage with process RSS, assume actual shared host supports same limits, or claim a production capacity benchmark. Repeat isolated full application request and host deployment checks later.

### T03 — actual native multipart blocker

Raw HTTP native parser tested under PHP8.2.36 with default POST parsing, explicit 2M file/4M body limits, no Laravel boot.

| Submitted wire parts | Observable result |
|---|---|
| One scalar avatar | first.png, 115 bytes, error 0 |
| Repeated scalar avatar | only second.png, 115 bytes, error 0 |
| avatar[] twice | array first.png and second.png |
| avatar[] then scalar avatar | second.png scalar only |
| scalar avatar then avatar[] | array containing only second.png |
| avatar + other distinct field | both visible |
| _method=PUT + avatar | HTTP method POST and field PUT visible; Laravel effective method/CSRF not exercised |
| Exact 2097152 bytes | error 0, reported size 2097152 |
| 2097153 bytes alone | error 1 (UPLOAD_ERR_INI_SIZE), size 0 |
| Zero-byte file | native error 0, size 0; application must reject |
| Two exact-size files (body 4194600) | POST and FILES empty at 4M post limit |
| Oversize first avatar then valid same-name avatar | only valid second.png, error 0; prior upload error no longer observable |
| php://input after duplicate multipart native parsing | 0 bytes, unavailable to recover lost part in this tested configuration |

Strict raw duplicate detection is **not feasible using only the observed native parsed request**. This is a confirmed contract-level blocker, not a Laravel FormRequest failure. T03 executed but remains unchecked; F03-14 remains Not Run. Do not claim max_file_uploads, avatar[] alone, or another FormRequest rule can detect all wire duplicates. Actual Laravel method override and CSRF remain later integration proof.

### T04 — structural validation feasibility, one additional gap

The test-only selected validation combined finfo, extension/expected format, bounds, GD result/warnings and limited format structure probes (PNG chunks/CRC/end/animation markers, WebP RIFF size/chunks/animation, JPEG start/end only). Rejected samples: empty, JPEG supplied as PNG, truncated PNG/WebP/JPEG, CRC-corrupt PNG, SVG/GIF, PNG+JPEG/JPEG+PNG/WebP+PNG with trailing bytes, animation marker fixtures and constructed two-frame APNG/WebP containers (not independently certified by another decoder). GD alone accepted several trailing/dual-format samples and truncated JPEG without warnings. **Supplemental JPEG+PNG+second EOI (860 bytes) passed the simplistic JPEG boundary probe and GD with zero warnings.** A terminal EOI check does not identify the real first image end. Canonical re-encode strips trailing data in served output but does not prove approved rejection at upload. T04 executed in preliminary isolation as authorized in parallel with T03, but remains unchecked: approved rejection criterion unresolved, no production validator created, no universal polyglot claim. Need stronger format-aware structural validation feasibility (JPEG segment and scan parsing respecting marker lengths/escaping/restarts; actual terminating EOI at end, reject extra payload and malformed streams; verify PNG chunk integrity/static limits and WebP RIFF/container/chunk bounds), bounded decoding then canonical encoding, positive and negative fixtures including this counterexample. No custom multipart parser or new library authorized; if adequate native-format validation cannot be established, return for approved design change, never silently lower the spec.

### Review decision before T05 (proposed, NOT approved)

1. Recommended V1 narrow transport contract: one effective scalar uploaded file in the native parsed request, no avatar[]/visible multiple or unknown file fields, validate the retained file's type/size and full request limit, reject zero-size and known errors. Native repeated same-name/mixed fields that collapse before application are outside the detection guarantee; only the retained valid scalar file can be saved, never more than one avatar; disclose the specific observed first-oversize masking behavior. This **changes the strict wire-duplicate rejection requirement** in approved spec/plan/tasks (especially F03-14), so obtain explicit user decision and revise only authorized owning documents before claiming readiness. No behavioral change made here. Alternative: keep strict wire rejection, require independently verified ingress solution or single-binary upload contract (would alter API/atomicity and require review); no custom parser/global PHP config or hosting change silently.
2. Keep approved strict image behavior and selected bounds; demonstrate stronger JPEG structural check on disposable probe before declaring T04 resolved. T04 does not pass on current limited probe. Do not introduce Composer image package or rely solely on re-encoding. Review exact technical approach and any extra dependency separately.

Current status **2/49 completed** (T01, T02), T03/T04 executed but blocked/unchecked, T05–T49 unchecked. All 19 feature acceptance scenarios Not Run. No app implementation, Composer changes, migrations, DB operations, docs PR merge, deploy, or actual hosting modification. Next: explicit contract/technical decision on both blockers and authorize any focused follow-up probe; then reconcile readiness and separate merge/implementation/package/protected-operation gates.

## 14. Approved follow-up (2026-11-25)

User expressly approved the revised native parsed-file boundary and stronger image structural checks, and authorized isolated preliminary tests only. This supersedes the proposed-decision state in §13, not its historical evidence. Accept at most one effective scalar `avatar`; reject visible arrays/additional file fields and invalid/empty/oversized retained uploads. Raw collapsed duplicate/mixed names and masked earlier errors are outside detection guarantee. Require JPEG length-aware marker/scan handling with actual EOI at EOF, PNG chunk bounds/CRC/IEND, WebP RIFF/chunk bounds/static checks before bounded native GD decode and canonical encode. No universal polyglot claim. Actual Laravel CSRF/override and full-request memory/RSS remain T25/T35/T45. Isolated branch `probe/identity-avatar-followup` from develop `8174097e93069cc20ae258ba236cf7acd8d37fb3`; [run 45652182251](https://github.com/m-hassan00096/kids-area-api/actions/runs/45652182251) head `0e0ba29b84b3aec16f32258410e0944a501a2441` completed Success (job 139339316119); actual job logs reviewed. T03/T04 checked. Retired at `00189a5c81194764269dd78fc03baf661ac5bd9e`, zero file diff vs develop. 19/19 acceptance scenarios Not Run; no app implementation or merge authorized.

### 14.1 Actual follow-up evidence

PHP 8.2.36 / GD 2.3.3, fileinfo, synthetic fixtures, temporary 128M/2M/4M limits. Native HTTP 14 cases: single, duplicate scalar, array-then-scalar, masked prior oversized then valid accepted only after retained-file validation; bracket arrays, scalar-then-array, extra/unknown-only, empty/corrupt, +1 byte and 4194600-byte body rejected; exact 2097152 accepted. Native `_method=PUT` observed, no Laravel boot/CSRF. `T03_PARSED_CONTRACT_ASSERTIONS_PASSED` logged. Raw duplicates collapse and are intentionally outside detection guarantee.

Baseline/progressive JPEG, PNG/WebP and length-delimited APP containing EOI-like bytes passed; 2048x1024 -> 512x256, max square -> 512x512, alpha 40 preserved, exact 2MiB -> 115 bytes, metadata removed. Prior 860-byte JPEG+PNG+second EOI rejected `early_eoi_or_trailing` before GD decode. Invalid segment lengths, concatenated JPEG, missing scan/EOI, PNG duplicate IHDR/CRC, WebP RIFF mismatch, trailing payloads, spoof/truncation, animation-marker and constructed two-frame cases, SVG/GIF, dimensions and +1 byte rejected. Argon2id hash+verify 166.75ms; PHP peak 10489856 bytes, process RSS 129872 KiB. Selected synthetic feasibility only: no universal polyglot claim, production validator not written. Broader independently generated restart/stuffed-byte JPEG and PNG/WebP edge fixtures, canonical output and Laravel override/CSRF remain T22/T25/T35; full application memory/RSS T45 and actual host deployment checks remain. No Composer, Laravel boot, DB, migration, feature code or merge. **4/49** tasks complete; **19/19 acceptance scenarios Not Run**; T05 awaits separate approval. Probe-only files removed from branch tip; history retained.

## 15. T05 — implementation authorization and verified branch (2026-11-25)

PR #9 merged into develop at `aa931b65fb4799086151e9d32d9a7371773dadb5`; user separately approved implementation and creation of `feature/identity-users-management` on 2026-11-25. Latest pre-merge CI 45652428628 succeeded (102 tests, 2268 assertions, Pint 101 files); actual logs checked. T05 complete; 5/49 tasks complete, all 19 acceptance scenarios Not Run. T06 dependency dry-run authorized for compatibility review only; exact installation, protected reset, future merge and deployment require separate approval. No application code changed.

T06 preliminary solver probe uses isolated branch `probe/feature03-dependency-resolution`, commit `6a96dc2972055d0a7e68385ad563b3ec5a674978`, no app boot or dependency installation. Actual logs reviewed; preliminary solver passed (see evidence below). Keep T06 unchecked until exact approved package/platform resolution and actual install, lock validation, platform/audit evidence. T07 separate protected disposable reset approval.

### 15.1 T06 preliminary dependency resolution

T06 preliminary solver dry-run succeeded in [45658008076](https://github.com/m-hassan00096/kids-area-api/actions/runs/45658008076), job 139359842222, PHP 8.2.36 / Composer 2.11.3. Actual lock operations: add spatie/laravel-query-builder 6.4.4 and mandatory spatie/laravel-package-tools 1.93.3, zero updates/removals. Baseline composer validate --strict and audit --locked succeeded (no known advisories for baseline). Dry-run used --no-scripts --no-plugins, no vendor before/after, composer.json/lock unchanged, no app boot/DB. Package operations printed simulated installs only. Probe retired at `c5e205f794b468f113df5f1f4e108df9fc653909`, zero changed files vs merged develop. T06 remains unchecked: obtain exact approval for two additions and explicit GD/fileinfo platform requirements/CI GD, then actual require and authentic lock, locked install, validate, platform checks, new-lock audit and regression. No host change, protected reset, feature merge or deployment authorized.

### 15.2 Approved actual dependency installation

User approved exact installation and explicit PHP GD/fileinfo requirements on 2026-11-25. Isolated install [45673686431](https://github.com/m-hassan00096/kids-area-api/actions/runs/45673686431), job 139415422829, PHP 8.2.36; actual logs reviewed. Composer-generated lock adds only spatie/laravel-query-builder 6.4.4 and spatie/laravel-package-tools 1.93.3, zero updates/removals, existing package metadata identical; manifest requires ext-gd and ext-fileinfo, query-builder ^6.4.4. composer validate --strict, audit --locked (no known advisories), locked composer install --no-scripts, check-platform-reqs and installed-version/class check passed. No app/DB boot or protected reset on the isolated runner. Authentic generated blobs verified by matching Git SHA composer.json `56229fc06bb8ee52fe0f9556658b125d29b2a936`, composer.lock `516c88cd918e667800f8ad090a6f62ca70b6e90e`. CI updated to provision GD. Normal application regression pending; T06 remains unchecked until regression evidence reviewed. T07 separate explicit disposable reset approval required. No hosting change or merge.

## 16. T06/T07 verified regression and approved isolated environment

User explicitly authorized `migrate:fresh` only in disposable CI MySQL `kids_area_test` / `kids_area_ci`, APP_ENV testing, host 127.0.0.1, MySQL 8/InnoDB, synthetic fixtures, no production or external database. T06 approved install regression [45673916280](https://github.com/m-hassan00096/kids-area-api/actions/runs/45673916280), exact dependency commit `bdf142759bcd953029e983447690dba66eeb41d0`, job 139416248611 completed Success: Authentication 41/1457 assertions, Identity 56/820, full 102/2296, Pint 101 files, validate/install/audit/platform/route/module/scope checks passed. T07 dedicated [45678335241](https://github.com/m-hassan00096/kids-area-api/actions/runs/45678335241), job 139431824332, probe commit `681d60624869302f4afb7874d311026f0deab6d7`, actual logs reviewed Success: PHP 8.2.36 GD PNG/JPEG/WebP + fileinfo, pre-reset environment/connection guard, actual MySQL database/user/8.x/InnoDB, empty reset schema, synthetic manager/reception, independent HTTP servers and cookie jars/shared clock/authoritative sessions, private 0700 temp root with 0600 PNG and cleanup, barrier dir checked; UsersManagementEnvironmentTest 1 test / 43 assertions and AuthenticationConcurrencyTest 12 / 500; focused Pint passed. Retain exact verified test in feature branch. No Feature 03 business acceptance scenario executed; all 19 Not Run. T01–T07 complete (7/49). Next T08 forward avatar reference migration, T09 migration preservation. No production DB/hosting change or merge.

## 17. T08/T09 avatar schema implementation (verification pending)

Added only `2026_11_25_000007_add_avatar_key_to_users.php`: nullable ASCII/bin VARCHAR(40), UNIQUE index `users_avatar_key_unique`; `User.avatar_key` hidden and not fillable. No old migration changed. `UsersManagementSchemaTest` checks actual DDL/nullable unique ownership/hidden field and uses the approved disposable `migrate:fresh` with explicit six pre-F03 files to simulate forward upgrade with real synthetic founder/staff logins, roles and session fingerprints. Adjusted only the exact Identity schema column assertion and existing Authentication forward-upgrade test's empty disposable rollback depth (4 instead of 3, because F03 migration follows F02). No production migration/rollback. T08/T09 remain unchecked until actual test and CI evidence reviewed. No new routes, storage, or business behavior yet.

## 18. T08/T09 actual evidence and T10 protected transport

T08/T09 verified on exact `e16568daca348083df2d16e8f67548436a9e40a5` in [45678811050](https://github.com/m-hassan00096/kids-area-api/actions/runs/45678811050), job 139433506742: forward avatar migration succeeded; UsersManagementSchemaTest and EnvironmentTest passed; Auth 41/1456, Identity 56/820, full 105/2400, Pint 104, route cache/module/scope passed. T08/T09 complete.

T10 implemented exact seven operations plus Laravel implicit HEAD; module CRUD routes and Core selects route use existing stateful Sanctum/staff middleware. FirstPartyCors scope extends users root/subpaths and selects, explicit DELETE added. Header/query/file override and unrelated body `_method` rejected; only real multipart POST with exact PUT body on positive numeric users/{user} allowed. Controllers intentionally fail closed 503 until later permission/operation tasks; no target lookup or write. Added real HTTP transport, CSRF, preflight and route inventory tests; T10 unchecked until actual CI. Full acceptance scenarios still Not Run.

### 18.1 T10 actual transport and regression evidence

T10 protected transport verified on exact source commit `ba0021cc80651de95dec137026cbc4cef3b7545e`, [CI 45679742034](https://github.com/m-hassan00096/kids-area-api/actions/runs/45679742034), job 139436791612 completed Success, actual logs reviewed. All four UsersManagementTransportTest cases passed: exact route inventory and existing staff middleware; first-party/guest failures and disabled staff; credentialed canonical DELETE preflight without creating session; real native cookie/CSRF for POST/PUT/DELETE and multipart POST `_method=PUT` with PNG, unrelated/header/query/JSON overrides rejected, invalid target paths 404, no separate POST update or user mutation. Auth 41/1440 assertions; Identity 56/827; full 109/2536; Pint 107 files; locked install/platform/audit, forward migration, module and route cache/list checks passed. T10 complete. Seven approved operations registered (+ implicit HEAD), no new permissions. Temporary fail-closed 503 controllers only; capability-before-target T11, normalization T12, sensitive diagnostics T13, actual business operations/Select Registry/avatar storage and full acceptance scenarios remain later tasks. 10/49 complete (T01–T10), 19/19 full acceptance scenarios Not Run. No production, hosting or merge.

## 19. T11–T13 implementation slice (verification pending)

User authorized only T11–T13 before T14. Exact per-route capability on fresh staff_context, explicit bounded primary target projection on original request after permission (no implicit binding or name bypass). Users/Selects skip generic trim/null middleware; Users boundary normalizes present fields and approved multipart coercions, preserving credentials and contact exactness, omitted keys and typed false; required-field/uniqueness/role/state/image validation belongs to T14/T22/T26 and later Actions. Known pinned Spatie invalid filter/sort/include/fields map safe 422; unknown package errors safe 500. Query/event suppression limited to dedicated Users PII/credential/key routes and existing Auth/Profile; native session handling unchanged. Tasks remain unchecked until actual tests, Pint and CI; no business CRUD, no T14, no production or merge.

### 19.1 T11–T13 actual verification

T11–T13 verified at `b7dc09cd953530c82ea84403ba8b0fb46013039e`, [CI 45706014872 attempt 2](https://github.com/m-hassan00096/kids-area-api/actions/runs/45706014872/attempts/2), job 139535687860, actual logs reviewed. All seven boundary tests passed: independent capability and target lookup counts, exact JSON and multipart normalization/fingerprints, invalid types and safe package exceptions/413, sensitive bindings/fault redaction and scope restoration. Auth 41/1449, Identity 56/827, full 116/2898, Pint 112 files, route cache/module/scope all passed. Attempt 1 (job 139528058619) passed the seven new tests but failed the existing competing-password-changes concurrency test: second MySQL connection not observed on the contested query (115 pass/1 fail). Exact same commit rerun passed all; intermittent observation failure cause not established, retain evidence and watch recurrence; no test weakened or Auth code changed to mask it. T11–T13 complete; no full feature acceptance scenario run.

## 20. T14–T20 read and Select Registry slice (verification pending)

Approved scope: query-only index FormRequest, typed allowlist and overflow-safe pagination; pinned Query Builder callbacks with literal escaped grouped LIKE and exact role/status predicates; one primary read-only REPEATABLE READ transaction for count/page/role projection (one-shot setting, no global/session isolation change); skip out-of-range pages before OFFSET. Explicit list/detail Resource, email only detail, no per-row role or file read. Core registry with Identity-registered `roles` and `userFilterRoles`; authorize all before provider SQL; cap200/probe201, overflow409 SELECT_OPTIONS_LIMIT. All other operations remain fail closed; T21+ deferred. T14–T20 unchecked until actual CI evidence; full scale EXPLAIN/performance T45.

### Phase C first regression attempt

CI 45713619929 failed 1 of 124 tests; literal search returned HTTP 500, remaining 123 passed. Candidate correction: skip impossible ASCII username/phone matches for non-ASCII search literals, retaining name search and original literal semantics. Add synthetic search case and exception-class context to assertion. Root cause not confirmed until fresh CI; T14–T20 remain unchecked.

### 20.1 Phase C actual verification

T14–T20 verified on exact `8e3a1f9d4dd54cc530897424aaf0f1ac46813500`, [CI 45719749580](https://github.com/m-hassan00096/kids-area-api/actions/runs/45719749580), job 139574786921 success, actual logs reviewed: Auth 41/1445 assertions, Identity 56/827, full 124/3559, Pint 122, route cache and module/scope checks passed. Four UsersManagementReadTest cases, three SelectRegistryHttpTest cases and one SelectRegistryProviderTest case passed. Verified literal Arabic/ASCII/wildcard/comma/boolean search, grouped role/state predicates, exact resources and Me unchanged, invalid input before domain reads, three domain SQL reads per nonempty page and bounded pagination, independent provider authorization, zero provider reads on mixed forbidden, exact 200 options/201 overflow409, empty options. Earlier CI 45713619929 failed literal search HTTP500; Unicode/ASCII comparison avoidance corrected the tested failure, exact original exception not independently captured. No schema/index/package change. Full scale EXPLAIN/load T45 still due; all 19 feature acceptance scenarios Not Run. T14–T20 complete, 20/49. User authorized T21–T25 only next, no production or merge.

## 21. Phase D private avatar infrastructure (implementation in progress)

Private identity-avatars disk, exclusive immutable staged files, format-aware JPEG/PNG/WebP structural validation and bounded GD normalization, outcome-aware cleanup and authorized binary read authored. Disabled generic local disk serving because avatar root is nested within it; dedicated disk also serve=false. Native file bag metadata rejects visible errors/arrays/extra fields even when Symfony drops an empty upload. Actual CRUD remains fail closed; T23 real mutation integration and fault/replacement concurrency remain T28–T36/T41; T25 guarded native tests next. No new packages or migrations. Tasks unchecked until actual evidence.

### Phase D tests authored (not yet verified)

Synthetic PHP/GD fixtures including an independently generated JPEG with restart markers and stuffed entropy bytes; canonical output, dimensions, alpha, metadata, invalid structure, upload byte limits, actual private exclusive storage and no symlink following, known outcome cleanup and primary reference check, read replacement retry, actual protected binary HTTP and guarded native multipart POST/PUT probe on existing routes (no production probe or write). Tests use isolated private root; real mutation integration remains later. Manual reconciliation: stop all writes/requests and wait for in-flight work to drain; coordinated DB/private-tree backup; inventory only canonical keys and compare against primary current users.avatar_key, investigate missing references and preserve ambiguous files; separate approval before deleting any unreferenced file; never auto-delete by age alone. No host operation authorized.

### 21.1 Phase D verification blockers (2026-11-26)

Main CI 45792700708 stopped at `composer audit --locked`: five advisories against existing symfony/http-kernel v7.4.23 (CVE-2026-133743), symfony/mailer v7.4.22 (CVE-2026-133751/133755), symfony/routing v7.4.23 (CVE-2026-133746/133754), fixed in 7.4.24 according to logged affected ranges. No Composer change or audit suppression; separate exact patch approval required. No Laravel Phase D tests ran. Isolated PHP8.2 native-only probe 45792957589 passed lint and JPEG/PNG/WebP resize + alpha; then failed on independent JPEG fixture. The committed Base64 was 1544 rather than generated 1548 characters (transcription error); restored independently generated 1159-byte fixture with exact SHA256 assertion. Native proof and security solver to be rerun independently, no vendor install. Added storage normalized-output header/dimension guard and distinct collision fixture. T21–T25 remain unchecked.

### 21.2 Verified native proof and minimal security patch candidates

Actual native-only and exact security solver evidence: [45793273798](https://github.com/m-hassan00096/kids-area-api/actions/runs/45793273798), job 139817400834 Success; PHP 8.2.36/GD/fileinfo and no vendor, lint passed; corrected independently generated restart/stuffed JPEG SHA256 verified, baseline/progressive and static PNG/WebP resize/alpha, malformed CRC/restart, dual JPEG+PNG+EOI, APNG marker, WebP trailing, exact2MiB normalization/metadata removal and +1 rejected; PHP managed peak 10493952 bytes with 128M memory_limit (native-only, not whole app). Initial three-package dry-run 45793175982 failed because http-kernel 7.4.24 requires http-foundation ^7.4.24|^8.1.12. Four-package exact dry-run with Composer 2.11.3 passed: http-foundation v7.4.23->v7.4.24, http-kernel v7.4.23->v7.4.24, mailer v7.4.22->v7.4.24, routing v7.4.23->v7.4.24, zero additions/removals, no other updates; simulated solution reported no security vulnerability advisories. Manifest/lock unchanged, no vendor install, no app/DB boot or bypass of main audit. Probe branch retired at `de237f71e0586a296c756f2054672857616ddfdf`, tree SHA verified identical to feature source `922fbc9cb374b07c9bae74f8fcb253aadf403d73`. Separate explicit approval of these four exact patch updates required, then actual authentic Composer lock, security audit, platform/install, full PHP/MySQL/Pint/CI. T21–T25 remain unchecked; no full Laravel tests executed for this phase. No production, hosting change or feature merge.

### 21.3 Approved actual security patch installation

User authorized proceeding with the exact four reviewed Symfony security patches on 2026-11-26. Actual Composer resolution/installation in isolated [45794232176](https://github.com/m-hassan00096/kids-area-api/actions/runs/45794232176), job 139820643216, completed Success; exactly 4 existing package updates to v7.4.24 (http-foundation, http-kernel, mailer, routing), zero additions/removals/other metadata changes, manifest unchanged. Composer validate --strict, locked audit, locked install --no-scripts, check-platform-reqs, installed version checks and final audit passed. No known advisories at execution. Authenticated Composer-generated composer.lock blob `1a6b29c032aec1f508decd006ee5022256d43b95` replayed exactly and SHA verified. No app or DB boot during install probe. Standard CI on updated feature branch still required for T21–T25, no new packages/schema/production/hosting/merge.

### 21.4 Phase D task-level verification and remaining standard CI gate

Phase D task-level verification (2026-11-26): approved Symfony patch install/audit [45794232176](https://github.com/m-hassan00096/kids-area-api/actions/runs/45794232176), exact four updates only, lock SHA verified in §21.3. Standard regression on `5e130615894cf79ed5c6c693dc85aa1726458c12` [45794373713](https://github.com/m-hassan00096/kids-area-api/actions/runs/45794373713), job 139821135806: Auth 41/1445, Identity 56/825, full PHPUnit 131/3852 passed. Overall run failed only Pint integer_literal_case in AvatarFormat.php; later checks skipped. Focused [45794628109](https://github.com/m-hassan00096/kids-area-api/actions/runs/45794628109) 7/284 passed, same Pint issue. Corrected source `5dcf00bf22c318e24b85491023e9914414026e6e` differs solely in hexadecimal integer literal letter casing (actual file comparison verified, no values or behavior changed). Exact corrected source with temporary workflow only [45794886277](https://github.com/m-hassan00096/kids-area-api/actions/runs/45794886277), job 139822884195 completed Success: focused AvatarHttpTest + AvatarInfrastructureTest 7 tests/284 assertions, Pint 131 files, locked install/platform/audit, migrations, route cache/list, module scope and out-of-scope artifact checks passed. Verified real private binary reads/access/headers/missing reference, native multipart limits/CSRF/parsed shape, JPEG/PNG/WebP normalization/structural rejection/alpha/size/metadata, exclusive private storage/collision/symlink, known rollback/commit/ref safety/unknown outcome retention and bounded replacement reread. Retired install probe 46066cc9b12fb442da7616557a91fcdff2f85e12 and focused probe 776a7a17d5752ed5ee21b105825c746757d7a7d7; both tree equality to their source verified. Task-level T21–T25 verified and closed using successful full regression plus exact corrected-source focused/Pint/route checks; NOT a claim that latest standard CI is green. Standard run [45794833416](https://github.com/m-hassan00096/kids-area-api/actions/runs/45794833416) attempt 1 failed one pre-existing AuthenticationConcurrencyTest (login commit first is revoked by waiting password change; second MySQL connection not observed on contested query), 40 passed/1 failed; same intermittent observation issue recorded earlier §19.1. Root cause not independently established. No test altered or weakened; rerun attempt 2 requested, result pending at this checkpoint. Require latest standard CI green and resolve/review recurring concurrency instability before merge. Actual user mutations and full acceptance, crash/commit fault/concurrent replacement integration remain T26 onward; no production/hosting changes or merge. All 19 full acceptance scenarios remain Not Run.

### 21.5 Final Phase D regression

Standard retry 45794833416 attempt 2 success (131/3845, Pint 131); exact docs closure head `10cc3789ce39b830fc8138b7ef95bf2c090ab1a4` CI 45795260666, job 139824170132 success (Auth 41/1446, Identity 56/825, full 131/3837, Pint 131, route/scope/audit passed). Intermittent concurrency observation failure remains an unresolved test-stability risk; no waiver. User approved T26–T29.

## 22. Phase E first slice (implementation in progress)

T26–T27 authored: actual FormRequests before fail-closed create/update handlers, exact presence/password/contact/role and early founder/self checks, primary unique validation excluding trusted target, normalized avatar retained in request; no hash/stage/write. Post-rollback mapper for vetted users duplicate indexes and explicit protected FK allowlist (no production FK invented). Earlier transport/capability-only tests use existing guarded controller probe for writes to isolate their original boundary; real FormRequest tests next. Verification pending. T28–T29 next.

T28–T29 authored: existing F01 execute contract preserved, extracted unchanged invariant; Users-only native outer transaction (attempts=1), manager mutex, current primary authorization, target/pivot/session locks, strict initiating owner/version/idle check, valid missing permission 403 without retirement, stale 401 conditional retirement after known rollback; commit/rollback/unknown state plus conservative file cleanup decision; bounded session lock timeout and restoration/disconnect, safe known constraint mapping only after verified rollback, cache invalidation failure cannot reinterpret a known commit. Test-only callbacks will exercise boundary; no production CRUD actions wired. Verification pending.

T26–T29 actual HTTP, real MySQL constraint, and guarded synthetic mutation callback tests authored. Test-only session GC lottery made deterministic (explicit probe-events still forces GC); random native GC could otherwise acquire a session lock before the intended manager query, plausible but not proven cause of prior flaky observation. Production session config unchanged, no assertion or lock wait threshold weakened. T26–T29 remain unchecked until verified.

Added six native HTTP transaction tests using guarded synthetic mutation callbacks: real commit/rollback/invariants/duplicate, actual lost-ack commit injection, postcommit cache/timeout restore faults, real manager lock timeout, exact idle/revoked/wrong-owner revalidation and permission loss with valid session preserved. Actual business CRUD remains intentionally fail closed. T26–T29 verification pending.

### 22.1 Phase E first slice verified

Phase E first slice actual verification (2026-11-26). Standard CI [45798695716](https://github.com/m-hassan00096/kids-area-api/actions/runs/45798695716), job 139835934696, exact feature source `f365ceabda6b18c63114ff7525ccd71751adff87`, completed Success: Auth 41/1440 assertions, Identity 56/825, full 142/4214, Pint 140, locked install/platform/audit, guarded migrations, route cache/list/module and scope checks passed. Focused isolated [45798774647](https://github.com/m-hassan00096/kids-area-api/actions/runs/45798774647), job 139836200028 Success, same source plus temporary workflow only: 22 tests/877 assertions (11 new tests: actual Create/Update FormRequests, real MySQL duplicate/RESTRICT constraints, six synthetic real HTTP transaction boundary cases; 11 existing transport/boundary tests). Actual manager lock timeout, committed and rolled-back states, last-admin/permanent roles, duplicate mapping, real commit followed by simulated lost acknowledgement, cache/restore faults, exact idle boundary/revoked/wrong-owner initiating session, current permission loss without retiring a valid session all passed. Earlier transport/capability-only tests use existing guarded test controller probe for write requests; new FormRequest tests exercise actual controller dispatch and confirm no mutation. Previous intermittent contested-query observation reviewed: only the guarded test router's default random native GC lottery disabled, explicit GC probe remains forced; production configuration unchanged and no lock/assertion thresholds weakened. Targeted login-commit-first/password-change contention repeated 5/5 successful (42/42/42/43/42 assertions), AuthenticationApiBoundaryTest 4/79 passed. Random native GC is a plausible contributor, not a proven root cause. Temporary probe retired at `7e8bcde09f03187df19abe4b20a862d522aafdf8`, tree verified equal to exact feature source. T26–T29 complete, 29/49. No new dependencies/migrations/permissions/routes. Actual create/update/delete handlers remain fail closed until T31–T33; generation revocation T30 next, then approved action tasks. Protected FK mapper tested with a named disposable fixture; production owning-module FK allowlists require future reviewed integration. Synthetic callback tests are not full business CRUD acceptance. All 19 full acceptance scenarios remain Not Run. No production, hosting operation or merge.

## 23. Phase E actual Users mutations (2026-11-26)

User authorized T30–T33. Previous exact closure head 991d44e242059c8f1dc6f627ecc910d1848a298a CI 45799369958 success. Reused CredentialRevocation with generation-only/optional Argon2id replacement, target-only session deletion and existing Profile signature. Implemented CreateUserAction, UpdateUserAction, DeleteUserAction and role assignment checks, actual controller dispatch with exact 201/200 minimal Arabic envelopes. Hash and stage outside locks; actual actor/target/session checks and role/founder/last-admin rules under existing mutex, no extra retries; lifecycle on known/unknown outcomes. Minimal known-commit self-disable browser clear and touch suppression wired because actual writes must not expose stale session authority; dedicated T34 lifecycle/fault acceptance remains open. Delete protected FK names default empty (no current business FK), explicit named owning-module contract injection for future reviewed integration and guarded fixture tests. No package/schema/permission/route change. T30–T33 unchecked until actual evidence.

Task-level real HTTP/DB/file tests authored: generation-only/replacement/no-wrap/stale generation; actual create/full update including normalized contact/password and avatar replacement/removal; actual missing-founder and post-validation duplicate with candidate cleanup; combined disable/reset, reactivation, session/role changes; founder/last-admin/self-disable; roleless repair/direct-grant anomaly; actual DeleteAction with reviewed disposable RESTRICT FK injection, full rollback and target-only session/pivot/avatar cleanup. Prior placeholder validation test expectations replaced by actual approved 201/200 and data assertions, original validation denials retained. No T34–T49 complete; CI pending.

### 23.1 Actual Users Actions task-level verification

Phase E actual Users Actions task-level verification (2026-11-26). User authorized T30–T33. Standard regression [45801207276](https://github.com/m-hassan00096/kids-area-api/actions/runs/45801207276), job 139844571780 on `97f39e32af4ecaa80ad7700c4810ee85f2aa9572`: Auth 41/1455, Identity 56/825, full PHPUnit 149/4511 passed, locked audit/install/platform and guarded migrations passed. Overall run failed solely on Pint ordered_imports in one test; later route/scope steps skipped. Focused [45801247203](https://github.com/m-hassan00096/kids-area-api/actions/runs/45801247203) 23 tests/858 assertions passed, same Pint issue. Corrected feature source `400046db134b5f39b3daa9b91b0c22075b158e99` differs ONLY by reordering two independent test imports (exact file comparison verified). Exact corrected source plus temporary workflow [45801507524](https://github.com/m-hassan00096/kids-area-api/actions/runs/45801507524), job 139845616288 Success: 23/858, Pint 146, locked audit/platform/install, guarded MySQL migrations, route cache/module/scope checks passed. Temporary probe retired at `d558342e7a941a86e09644c08e2f274a9e61da22`, tree verified identical to corrected feature source. Corrected-source standard CI [45801479064](https://github.com/m-hassan00096/kids-area-api/actions/runs/45801479064) still in progress at closure; focused Auth/Identity steps passed; require green latest standard CI before merge. Actual 201/200 creation/update/delete responses, Argon2id/explicit fields, native single role and nullable contacts, missing founder rejection, real duplicate injected after validation/staging and candidate cleanup, image create/replace/preserve/remove, role changes, inactive role rules and roleless repair, direct-grant denial, target-only generation/session revoke, no wrap/stale version, disable+reset single increment, reenable no revival, protected founder/self and last-admin rollback, known-commit self-disable browser clear, real disposable RESTRICT FK with test-only explicit named contract and target-only pivot/session/file cleanup all verified at task level. Existing Profile and prior boundary tests passed. No current production business FK; future protected references require reviewed named owning-module contracts, unknown constraints fail safely. T30–T33 closed using full regression on identical application code plus exact corrected-source focused/Pint/route checks. Minimal self-disable implementation necessary for safe CRUD, dedicated T34 lifecycle fault/concurrent integration still open, T35–T37 dedicated complete integration and T38+ not closed. All 19 full acceptance scenarios remain Not Run as complete scenario suites. No dependency/schema/routes/permissions changes, no production/hosting/deployment/merge.

## 24. T34–T37 actual integration and postcommit lifecycle

Verified previous closure and corrected standard CI succeeded. User authorized T34–T37 only. Added safe per-request mutation outcome diagnostic for later API operational failures (including native session save after a known commit); no public response contract change. Guarded router actual-Action-only synthetic cache, cleanup and final-save faults, touch/outcome/log evidence; include real synthetic avatar keys in redaction checks. Extracted existing deterministic HTTP race helper verbatim for reuse in T34 late native save proof; no weaker assertions, broader T38–T43 remain open. Actual integration tests pending.

T34–T37 tests authored: real self-disable with native final save and cache faults (touch suppression, authoritative markers), actual avatar cache/cleanup/commit-ack outcomes, two-server old native INSERT after disable and reenable using existing deterministic barrier, real independent capabilities/CSRF/origin/JSON/multipart and allowed-size JPEG/PNG/WebP, founder avatar protections/custom six-capability last-admin/own role and Profile, inactive/wrong-guard constraints, actual delete reviewed/unknown FK rollback preserving two target devices and bytes. Existing assertions retained and extended. Full integration verification pending; 19 complete acceptance cases remain Not Run pending later matrices.

### 24.1 Actual integration verification

Actual T34–T37 verification (2026-11-26). Exact feature source `5c9e86bc09df3d4ffd3577c5a181a3da033b6797`. Focused isolated [45804378519](https://github.com/m-hassan00096/kids-area-api/actions/runs/45804378519), job 139855617507 completed Success: 25 tests/1087 assertions, Pint 149 files, locked install/platform/audit, guarded MySQL migrations, route cache/module/scope all passed. Eight new tests and expanded delete proof cover actual independent permissions, native CSRF/origin, genuine multipart create/update at 2MiB and +1, canonical formats, invalid image retention, founder own/other avatar protections, custom six-capability last-admin, fresh role/session and Profile, inactive/wrong guard, real named vs unknown RESTRICT FK preserving entire row/two authoritative sessions/old bytes/pivots; actual self-disable suppresses touch and clears initiating device on known commit; real delayed native INSERT after disable remains anonymous even after reenable using distinct HTTP server/MySQL connection and deterministic barriers; actual replacement cleanup/cache faults preserve referenced bytes with success, real commit plus synthetic lost acknowledgement returns 503, one commit and one generation increment, retains files. Native final-session-save failure after known commit returns safe 500 while DB change persists and revoked sessions remain nonauthoritative; safe correlated diagnostic explicitly reports committed (not rollback). No retry or public outcome API change. Existing Auth race/await bodies and old test body verified identical after extraction to shared trait; no thresholds/assertions weakened. Temporary probe retired at `0d0208c30553899938247bfbb7b3f6fb53871f27`, exact source tree restored. Standard CI [45804312638](https://github.com/m-hassan00096/kids-area-api/actions/runs/45804312638), job 139855388971: in_progress. Authentication and Identity focused steps passed; full suite not yet confirmed. T34–T37 remain unchecked, 33/49, pending full standard regression. T38–T43 full contention/fault matrix, T44–T49 security/scale/acceptance/reconciliation remain open; all 19 complete acceptance scenarios Not Run. No package/schema/routes/permissions change, production/hosting operation or merge.

### 24.2 Standard regression and closure

Standard CI [45804312638](https://github.com/m-hassan00096/kids-area-api/actions/runs/45804312638), job 139855388971, exact source `5c9e86bc09df3d4ffd3577c5a181a3da033b6797`, completed Success. Actual logs: No security vulnerability advisories found.; Tests:    41 passed (1443 assertions); Tests:    56 passed (825 assertions); Tests:    157 passed (4941 assertions); PASS   ......................................................... 149 files; INFO  Routes cached successfully.. All required standard steps passed. Compared checkpoint `c0bf4e836491e108e07f607b97811a96113fa80c` to tested source; documentation-only delta. T34–T37 verified/closed; 37/49. T38–T43 broader deterministic race/fault matrix next when authorized; T44–T49 remain open; all 19 complete acceptance scenarios Not Run. No merge or production operation.

## 25. T38–T43 concurrency and fault matrix

User authorized T38–T43. Prior verified source CI 45804312638 success; docs closure 733649df checked. Extend existing independent real HTTP/MySQL barrier harness with per-interleaving fresh barrier files and parent connection check, actual Users write/session/FK contention, guarded private read barriers and synthetic native write/close faults. No new production routes, dependencies, schema or config. Test-only FK route/table belongs to disposable synthetic tests. Verification pending.

T38–T43 authored test matrix: create/update unique collisions for all approved fields, duplicate create and update races, competing singular role writes; two viable custom administrators and stale role/session authority, real logout both orderings, exact idle cutoff; Login vs disable/reset/delete both orderings, Profile/admin reset both orderings; replacement/replacement, replacement/delete both orderings, read-before/after immutable file replacement/deletion, real fixture FK insert/delete both orders; actual Action precommit stage/native write/close/hash failure, actual reference/pivot/password/session rollback and real lock timeout with synthetic failed setting restoration. Existing verified postcommit cache/cleanup/lost acknowledgement and late-save tests retained. All new evidence pending CI; no production hook or real DB/file operation.

Initial focused CI 45806086048: 38 passed, 1 failed (2243 assertions); all uniqueness, authority, credential, avatar/FK races passed. One injected pretransaction failure returned HTTP500 without router redaction evidence; cause not yet established. Pint reported one router formatting issue. Replaced the bootstrap mock with a native HashManager subclass (test only), added mode-labelled safe diagnostics and exact correlated error assertions, fixed imports per actual Pint diff, and added both orderings of delete-only actor vs self-disable last-admin conflict. No assertions weakened; rerun pending.

Added actual InnoDB deadlock proof with an explicitly inverted disposable FK writer holding the parent S lock and many undo entries, real Users manager/user lock contention, and server Innodb_deadlocks counter increment, 503/rollback/no retry and original file preservation. Test-only inversion must not be copied into future production modules. Verification pending.

Second focused CI 45806707607: 40 passed, 1 failed (2393 assertions). All prior fault tests now passed, both last-admin orderings passed. New deadlock test stopped before interleaving because MySQL8 did not expose `Innodb_deadlocks` via SHOW GLOBAL STATUS; no deadlock claim from this attempt. Pint one named-argument whitespace issue. Corrected instrumentation to test-only native PDOStatement subclass recording real driver error 1213 and count (no raw SQL/bindings, no simulated error); retain actual contention and no-retry/rollback/file assertions. Corrected exact Pint diff. Rerun pending.

### 25.1 Race/fault verification checkpoint

T38–T42 task-level verification (2026-11-26). Original standard CI [45806056628](https://github.com/m-hassan00096/kids-area-api/actions/runs/45806056628), job 139861492364: 169 passed, 1 failed, 6094 assertions; original focused [45806086048](https://github.com/m-hassan00096/kids-area-api/actions/runs/45806086048), job 139861596318: 38 passed, 1 failed, 2243 assertions; failure in actual fault test missing redaction header on HTTP500. Revised focused [45806707607](https://github.com/m-hassan00096/kids-area-api/actions/runs/45806707607), job 139863750016, exact feature source `5ac6f2d6f608d62797ed95bf551175316e86d1de` plus temporary workflow: 40 passed, 1 failed, 2393 assertions. Actual stage/native write/close/hash and pivot/reference/password/revoke fault tests all passed, as did timeout plus restore failure, all uniqueness/role/authority/login/profile races, both last-admin disable/delete commit orders, real concurrent avatar replace/read/delete and FK insert/delete both orders. T38 independent server and parent connection IDs/observed contested queries verified; T39 real DB uniqueness and singular roles; T40 current authority/idle/logout/last admin; T41 both Login vs disable/reset/delete and Profile vs reset orders; T42 actual DB/FS avatar and FK races all verified. Extra real deadlock test failed before interleaving because SHOW GLOBAL STATUS did not expose Innodb_deadlocks; no 1213 proof from that run. Pint reported named argument whitespace. Final authored source `2c9246232030b182aa89bcd5c5d1c86e7e65a5b4` changes only guarded test instrumentation for observe-deadlock (native PDOStatement catches actual 1213/count), added headers and exact formatting; no prior assertions waived, no application code changed. Latest standard [45807182228](https://github.com/m-hassan00096/kids-area-api/actions/runs/45807182228) in_progress and focused [45807220043](https://github.com/m-hassan00096/kids-area-api/actions/runs/45807220043) in_progress. 45/49 task-level verified T01–T45; T46–T49 open. Full latest CI required before feature review; no merge, no production, no new dependencies/schema/routes/permissions. Test-only deliberate lock-order inversion is not a production design. All 19 full acceptance scenarios Not Run.


### T43–T44 verification and closure (2026-11-26)

Verified standard [45807182228](https://github.com/m-hassan00096/kids-area-api/actions/runs/45807182228), job 139865405577, exact code head `2c9246232030b182aa89bcd5c5d1c86e7e65a5b4`, Success: 172 tests / 6201 assertions, Pint 157 files, locked Composer audit/platform, migrations, route cache, module/scope all passed. Focused [45807220043](https://github.com/m-hassan00096/kids-area-api/actions/runs/45807220043), job 139865532823, probe head `fa454626363542c09d14ab768e1b87f09f5dbd34`, Success: 11 tests / 730 assertions (targeted subset, not all race classes), Pint 157. Both logs show the real InnoDB deadlock test passed: native driver 1213 observed, one attempt, rollback and original file preserved. The full regression is on the feature source itself; checkpoint `1924e626ffdbc4f2d43b99dccf2cc0cb0fab0129` differs only in four documentation files. T43 closed, including prior verified real commit/lost-ack, cache, file, session, constraint and timeout/restore cases.

T44 reviewed against its approved evidence and successful full run: UsersManagementTransportTest (origin, native CSRF, DELETE preflight, allowed/denied override); UsersHttpIntegrationTest (actual controller/action independent capabilities before lookup, native CSRF/origin/guest, actual multipart and delete); UsersManagementBoundaryTest (malformed input, safe envelopes, request IDs, synthetic redaction and nested restoration, unrelated events and native session suppression); SelectRegistryHttpTest (all-or-nothing authorization and no provider dispatch); AvatarHttpTest (private paths and headers, missing file safe failure); UsersActualFaultTest and UsersPostCommitIntegrationTest (actual write/storage/session errors, correlated safe reports and preserved state). Authentication regression also passed. Existing guarded probes intentionally inject synthetic failures; actual CRUD integration and fault tests separately exercise production dispatch. No claim of universal penetration testing or production hosting certification. No test requirement waived. T44 closed at task level; 44/49. T45 scale/EXPLAIN measurements, T46–T49 still open; all 19 complete acceptance scenarios remain Not Run. No merge/deploy, new dependency, migration or production-code change.


### T45 measured performance evidence (2026-11-26)

Actual [CI 45808834262](https://github.com/m-hassan00096/kids-area-api/actions/runs/45808834262), job 139871059521, exact source `a9e80026fbadce4d12892bbf667bdaadec5ce1a5`: Authentication 41/1455, Identity 56/825, full 174 tests / 9089 assertions passed. Both UsersManagementPerformanceTest cases passed. All 27 real list scenarios and 81 actual SELECT plans (EXPLAIN and EXPLAIN ANALYZE), 4 real create measurements and 3 actual private avatar fetches were emitted. Pint reported 1 file with a style issue (authentication-router.php), therefore overall CI failed and following route/module scope steps were skipped. T45 measured task itself verified; T46–T49 remain unchecked pending corrected exact-source verification and acceptance reconciliation. Fix only import/style in test router; add exact table/index/route inventory and approved explicit CI preflight/discovery/focused checks. No new production code, dependencies, migrations, routes, permissions or indexes.

Each of the 27 nonempty list scenarios returned the exact expected IDs, total, role and avatar URL (including absent synthetic files, no storage directory created), 3 domain SELECTs (count, page, roles), 1 read snapshot setup, 7 other HTTP SQL statements (authentication/session/permission etc.; total 11). Source review confirms the list resource constructs the URL from the key without filesystem calls. Single measurements, not p95, capacity, production SLA or real hosting data. PHP8.2/GitHub Actions/MySQL8 only; low-entropy synthetic image fixture does not represent every image's peak memory.

| Accounts | Case | Total | Returned | HTTP ms | Direct service ms | SQL ms count/page/roles |
| ---: | --- | ---: | ---: | ---: | ---: | --- |
| 100 | first15 | 100 | 15 | 30.666 | 4.491 | 0.31 / 0.29 / 0.53 |
| 100 | first100 | 100 | 100 | 22.76 | 5.124 | 0.28 / 0.46 / 0.95 |
| 100 | deep15 | 100 | 10 | 16.646 | 2.448 | 0.28 / 0.32 / 0.53 |
| 100 | arabic | 50 | 15 | 15.943 | 2.649 | 0.3 / 0.33 / 0.52 |
| 100 | username | 99 | 99 | 18.588 | 4.993 | 0.33 / 0.46 / 0.88 |
| 100 | phone | 99 | 15 | 16.176 | 2.718 | 0.38 / 0.32 / 0.51 |
| 100 | role | 50 | 15 | 23.848 | 3.036 | 0.56 / 0.48 / 0.51 |
| 100 | inactive | 33 | 33 | 15.947 | 2.972 | 0.29 / 0.33 / 0.63 |
| 100 | combined | 34 | 15 | 16.923 | 3.218 | 0.58 / 0.55 / 0.5 |
| 1000 | first15 | 1000 | 15 | 16.56 | 2.627 | 0.34 / 0.28 / 0.58 |
| 1000 | first100 | 1000 | 100 | 17.253 | 5.236 | 0.34 / 0.38 / 1.13 |
| 1000 | deep15 | 1000 | 10 | 17.084 | 2.681 | 0.35 / 0.53 / 0.49 |
| 1000 | arabic | 500 | 15 | 15.565 | 3.214 | 0.56 / 0.34 / 0.72 |
| 1000 | username | 999 | 100 | 17.161 | 5.3 | 0.59 / 0.43 / 1.12 |
| 1000 | phone | 999 | 15 | 14.71 | 2.89 | 0.64 / 0.33 / 0.53 |
| 1000 | role | 500 | 15 | 15.301 | 3.63 | 1.01 / 0.59 / 0.53 |
| 1000 | inactive | 333 | 100 | 17.149 | 5.28 | 0.43 / 0.5 / 1.18 |
| 1000 | combined | 334 | 15 | 16.403 | 4.151 | 1.61 / 0.55 / 0.51 |
| 10000 | first15 | 10000 | 15 | 114.305 | 3.654 | 1.14 / 0.3 / 0.62 |
| 10000 | first100 | 10000 | 100 | 18.501 | 5.723 | 1.05 / 0.43 / 1.17 |
| 10000 | deep15 | 10000 | 10 | 21.159 | 6.067 | 1.06 / 3.06 / 0.5 |
| 10000 | arabic | 5000 | 15 | 17.172 | 4.515 | 2.18 / 0.33 / 0.51 |
| 10000 | username | 9999 | 100 | 19.516 | 7.799 | 3.07 / 0.44 / 1.13 |
| 10000 | phone | 9999 | 15 | 18.556 | 5.941 | 3.52 / 0.33 / 0.54 |
| 10000 | role | 5000 | 15 | 30.073 | 16.423 | 5.8 / 8.31 / 0.63 |
| 10000 | inactive | 3333 | 100 | 23.589 | 7.642 | 1.79 / 0.72 / 1.31 |
| 10000 | combined | 3334 | 15 | 30.357 | 14.975 | 12.03 / 0.64 / 0.53 |

At 10,000 users, unfiltered count used users_phone_unique covering index; page used PRIMARY reverse scan, role eager query identity_single_role_unique and two-row role table. Deep OFFSET 9990 examined 10,000 index rows to return 10, actual iterator end 2.88ms; direct page SQL 3.06ms. Contains LIKE count scanned 10,000 users (actual count iterator 2.50ms Arabic, 3.52ms username, 3.87ms phone); low-selectivity inactive scan count 1.86ms. Role filter count used indexed PRIMARY role_id and 5,000 PK lookups; page optimizer chose the role-first join and sort of 5,000 rows (actual page 9.09ms, observed page SQL 8.31ms). Combined count scanned 10,000 users and 6,666 membership lookups (13.6ms iterator, 12.03ms SQL); page used reverse PRIMARY with bounded 43 user rows. First request after bulk fill of 10,000 was 114.305ms (direct 3.654ms), other HTTP cases 17.172–30.357ms; cause of isolated outlier not established. The role and combined count/offset growth are future watch points. Ordinary B-tree indexes cannot accelerate arbitrary contains LIKE; a speculative new index, cursor pagination, full-text or contract change is not justified by these measurements and remains unapproved.

Full native Argon2id verified for new users (65536 KiB, 4 iterations, one thread). Actual real HTTP creates all 34 SQL statements; lock acquisition elapsed includes SQL round trip, acquired-to-commit-ack is measured using guarded native PDO observation, not exact server lock hold. Avatar image dimensions 2048x2048 input -> 512x512 output, private file bytes matched. PHP allocator peak reset before request; RSS is Linux high-water process memory including bootstrap/login and native allocation, not simply PHP heap.

| Format | Input bytes | Stored bytes | Create ms | Manager acquire ms | Acquired-to-ack ms | PHP peak bytes | Linux RSS KiB | Read ms |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| none | 0 | — | 171.425 | 0.203 | 35.647 | 4194304 | 170112 | — |
| png | 21254 | 2225 | 278.986 | 0.211 | 29.256 | 6295552 | 187296 | 16.302 |
| jpg | 66226 | 4786 | 236.023 | 0.233 | 25.241 | 6295552 | 170900 | 17.119 |
| webp | 7760 | 602 | 252.893 | 0.192 | 25.82 | 6295552 | 191380 | 17.01 |

No hosting SLA: validate actual cPanel GD/fileinfo/Argon2id/native memory and private storage, PHP request limits, worker memory/concurrency, backup/reconciliation before deployment. 45/49 task-level verified; T46–T49 and 19 complete scenario statuses remain open until exact final evidence.


### Final acceptance evidence review — provisional (2026-11-26)

User authorized continuing T46–T49. Reopened actual approved spec and test assertions and checked the recorded full PHPUnit success (174/9089) from [45808834262](https://github.com/m-hassan00096/kids-area-api/actions/runs/45808834262), exact source a9e80026. Overall prior CI failed Pint, so not a final delivery gate. Compared exact next source d076f9f8 to that source: only CI, IdentityScopeTest, test router import and four documentation files changed. New run [45809861556](https://github.com/m-hassan00096/kids-area-api/actions/runs/45809861556), job 139874579115, had successful Composer/platform/audit, native GD/fileinfo/codecs, guarded MySQL/migrations, focused Authentication/Identity, test discovery and focused Users/Avatar/SelectRegistry; full regression/Pint/route cache/scope not yet confirmed at this review. No production code differences.

Traceability below identifies actual assertion-level evidence already executed in the earlier successful full PHPUnit step, not a new independent scenario execution or final acceptance approval. Current/latest CI and final reconciliation still required. Test classes under tests/Feature; abbreviated names refer to corresponding Test.php.

| Scenario | Executed evidence mapped for final reconciliation |
| --- | --- |
| F03-01 | UsersManagementReadTest explicit exact list/detail/Me shape, nullable fields, anomalous roles and lookup boundaries; AuthenticationBrowserTest exact Me contract. |
| F03-02 | UsersManagementReadTest literal search including Arabic, exact-case username, leading-zero phone, wildcard/escape/comma/boolean literals, SQL-injection-like input, no email match, inactive role/state, combined predicates, invalid query shapes; UsersManagementPerformanceTest actual scale plans. |
| F03-03 | UsersManagementReadTest default/clamp/empty/overflow/ordering/count predicates, three domain SELECTs; UsersManagementPerformanceTest all 27 scale cases, expected IDs/role/total, actual EXPLAIN and EXPLAIN ANALYZE. |
| F03-04 | UsersMutationValidationTest actual 201 minimal envelope, active state, one role/no direct grants, nullable fields, Argon2id and uniqueness; UsersHttpIntegrationTest inactive/wrong guard and genuine multipart; UsersActionsTest absent founder rollback; IdentityBootstrapTest baseline. Explicit otherwise-valid create injection cases strengthened in this checkpoint, pending CI. |
| F03-05 | IdentitySchemaTest actual MySQL ASCII/bin username, email case-insensitive and nullable unique constraints; UsersMutationValidationTest actual API validation and exact-case variant; UsersUniquenessRaceTest real concurrent three-field collisions and complete rollback; UsersConstraintErrorsTest reviewed named constraint mapping. |
| F03-06 | UsersMutationValidationTest full ordinary PUT, own-ID uniqueness, absent password/hash, empty normalization, explicit new hash and confirmation rejection; UsersHttpIntegrationTest forbidden self reset and real Profile; UsersCredentialRaceTest both orders. Strengthened separate actual absent/null/empty hash/version/session preservation, explicit Argon2id and session revocation assertions in this checkpoint, pending CI. |
| F03-07 | UsersManagementTransportTest native CSRF, Origin, override and preflight; UsersManagementBoundaryTest independent permissions, zero target reads before authorization, invalid shapes/IDs; UsersHttpIntegrationTest actual independent CRUD permissions and native JSON/multipart/DELETE. |
| F03-08 | UsersActionsTest founder/self-delete/last-admin rollback; UsersHttpIntegrationTest founder avatar and permitted self-edit/role change, another actor denied, deactivation/self-reset denied; UsersAuthorityRaceTest stale actor and competing admin paths. |
| F03-09 | UsersActionsTest active-role replacement and fresh grants without revocation, roleless repair/direct-grant anomaly; UsersMutationValidationTest retained inactive role/ordinary update/disable, invalid reactivation and inactive reassignment; UsersHttpIntegrationTest wrong guard rejection; UsersUniquenessRaceTest singular role. |
| F03-10 | UsersActionsTest atomic disable/reset/re-enable and unrelated session authority; UsersCredentialRevocationTest target-only revocation; UsersCredentialRaceTest Login/mutation orderings; UsersPostCommitIntegrationTest actual late native INSERT remains anonymous after re-enable. |
| F03-11 | UsersActionsTest old login rejected/new password accepted, unrelated session survives; UsersCredentialRevocationTest target-only and generation guard; UsersCredentialRaceTest Login/Profile/admin reset orderings; UsersActualFaultTest reference/pivot/password/session rollback. |
| F03-12 | IdentityAccessTest six-permission invariant, not role name/founder; UsersHttpIntegrationTest custom six-capability administrator and staged file rollback; UsersAuthorityRaceTest both orderings of last-admin/stale actor/delete/disable, current actor authority; UsersMutationTransactionTest transaction rollback/current eligibility. |
| F03-13 | UsersActionsTest exact successful hard delete, target pivot/session/file only, founder/self prohibition, unknown FK safe 500 and reviewed linked FK 409 preserving row/bytes/devices; UsersAvatarFkRaceTest real FK insertion/delete both orders and real deadlock; UsersConstraintErrorsTest reviewed FK mapping. |
| F03-14 | UsersHttpIntegrationTest actual 2,097,152-byte PNG create and update, JPG/WebP, 2MiB+1 invalid, spoof/trailing/array/extra part/SVG/path/base64 and old bytes retained; AvatarInfrastructureTest structural fixtures, canonical formats/dimensions/metadata; AvatarHttpTest native limits; UsersMutationValidationTest JSON retention/removal; approved parsed-part collapse limitation unchanged. |
| F03-15 | AvatarHttpTest actual private binary URL/type/cache/no-store/nosniff, no public path, 401/403/404, missing referenced file safe; UsersManagementReadTest relative/null URL; UsersMutationValidationTest target-only removal/replacement; UsersAvatarFkRaceTest immutable read/replacement/deletion. |
| F03-16 | UsersActualFaultTest actual stage/write/close/hash/reference/pivot/session/lock faults and rollback; UsersUniquenessRaceTest duplicate and cleanup; UsersHttpIntegrationTest custom last-admin and candidate cleanup; UsersAvatarFkRaceTest competing replacements/delete/deadlock; UsersPostCommitIntegrationTest real commit with synthetic lost ack and cleanup/cache failures; AvatarInfrastructureTest ownership/lifecycle. |
| F03-17 | SelectRegistryHttpTest active/inactive options by independent permissions, exact stored aliases/order/numeric values, no mixed dispatch, malformed/unknown/duplicate 422, cap/overflow; SelectRegistryProviderTest empty catalogue. |
| F03-18 | UsersManagementBoundaryTest native JSON/multipart shape, safe Arabic envelopes/Request-ID, synthetic sensitive SQL/multipart/path/password probes, log restoration/native sessions/ordinary events; UsersActualFaultTest and UsersPostCommitIntegrationTest actual failures safe correlated; AuthenticationApiBoundaryTest baseline. |
| F03-19 | UsersManagementSchemaTest exact forward migration preserves existing founder/users/roles/grants/sessions; IdentitySchemaTest/IdentityScopeTest/IdentityCatalogueTest/Authentication and Foundation full suite; real race/fault tests. Latest mandatory expanded full/Pint/route cache/module scope and final exact diff remain pending. |

Review found that the earlier tests covered normalized empty password and final credential generation indirectly, but independent actual explicit null/empty retention assertions provide stronger proof of F03-06. Added those and otherwise-valid create field-injection negative cases for F03-04 without changing any production behavior, existing assertion strength, contract, dependency, index or migration. No new full acceptance status claimed before new exact-source CI. Current task-level count remains 45/49 pending T46–T49.
