# Feature 03 — Users Management: Execution Tasks

> **Status:** APPROVED — implementation authorized; PR #9 merged, draft implementation PR #10 open; Phase A verified.
> **Approved inputs:** [spec.md](spec.md) and [plan.md](plan.md). Technical plan accepted by user reply “تمام” to the preceding plan review; not proof of feasibility.
> **Owner:** Identity and shared Core HTTP/Select Registry.
> **Baseline:** develop `aa931b65fb4799086151e9d32d9a7371773dadb5` (PR #9 merged); active `feature/identity-users-management` created from that exact ref. Pre-merge CI 45652428628: 102 tests / 2268 assertions; Pint 101.
> **Progress:** 37/49 complete (T01–T37); standard CI 45804312638 success (§24.2). T38–T43 race/fault matrix next when authorized; 19 full acceptance scenarios Not Run; no merge.

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

- [ ] **T38 — Independent race harness.** Dependencies: T07,T28–T34. Real HTTP server processes/cookie jars, independent MySQL connection IDs, shared clock and deterministic barriers observed contention, designated shared private temp root; test-only fault hooks. Evidence: Independent connections and observed interleavings, not sleep-only.

- [ ] **T39 — Concurrent uniqueness/roles.** Dependencies: T31,T32,T38. Overlapping duplicate create/update username/email/phone, one valid outcome, safe422 no partial user/pivot/file; competing singular role writes. Evidence: Actual lock/constraint/final state F03-05/09/16.

- [ ] **T40 — Last-admin and stale actor races.** Dependencies: T29,T32,T33,T38. Concurrent disable/delete/role mutations threaten six capabilities incl custom role, stale actor permission/session revoked during wait, Logout first vs mutation first, exact idle cutoff, no zero viable admin. Evidence: Both commit orders and rollback F03-08/12/13.

- [ ] **T41 — Credential and stale session races.** Dependencies: T30,T32–T34,T38. Both Login vs disable/reset/delete orders, Profile/admin reset, delayed native reinsert, self-disable/reenable, active role no forced logout; unrelated sessions safe. Evidence: Real HTTP/MySQL hash/generation/marker evidence F03-09–11/13.

- [ ] **T42 — Avatar and FK races.** Dependencies: T23,T24,T32,T33,T38. Replacement/replacement, replacement/delete, read/replacement/delete, FK insert/delete race; final reference and bytes valid, cleanup only unreferenced. Evidence: Observed interleavings/DB/FS evidence F03-13/15/16.

- [ ] **T43 — Fault injection and uncertain outcome.** Dependencies: T23,T28,T31–T34,T38. Stage/write/close failure, duplicate/FK/last-admin/hash/session/pivot/reference rollback, real commit with simulated lost acknowledgement/disconnect, cache and cleanup faults, lock timeout plus restore failure; retain possibly referenced keys, no blind retry. Evidence: Actual synthetic fault proof, no false rollback F03-05/11/12/13/16.

- [ ] **T44 — Security and log regression.** Dependencies: T13,T20,T25,T35–T37,T43. Real origin/CSRF/override/DELETE/permission ordering, forbidden mixed selects, no public path, malformed input, Arabic Request ID and no secrets/path/raw SQL/multipart log; preserve native session redaction and business events. Evidence: Actual safe boundary assertions F03-07/14/15/17/18.

- [ ] **T45 — Measure performance and EXPLAIN.** Dependencies: T15–T17,T22,T38–T44. Synthetic 100/1000/10000 accounts; count/page/order/offset and search/role/state EXPLAIN, 15/100 records max3 domain queries, auth separate, no N+1/file stat; image IO/peak memory full Argon2id, lock metrics. New index only measured need + reviewed approval. Evidence: Actual plans/counts/times/memory; no SLA F03-03/19.

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
