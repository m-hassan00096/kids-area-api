# Feature 03 — Users Management: Execution Tasks

> **Status:** APPROVED — user explicitly approved task list and temporary isolated PHP8.2/GD/fileinfo T01–T04 feasibility on 2026-11-25; no Feature 03 implementation or PR #9 merge authorized.
> **Approved inputs:** [spec.md](spec.md) and [plan.md](plan.md). Technical plan accepted by user reply “تمام” to the preceding plan review; not proof of feasibility.
> **Owner:** Identity and shared Core HTTP/Select Registry.
> **Baseline:** develop 8174097e93069cc20ae258ba236cf7acd8d37fb3, PR #8 merged; docs PR #9 open. Historical F02 CI 45638942711 passed 102 tests/2289 assertions, Pint 101. No F03 execution. 
> **Progress:** 2/49 tasks complete (T01/T02); 19/19 acceptance scenarios Not Run. Authorized target PHP8.2/GD probes completed in isolated CI 45650686580 and 45650969241 (§13); T03/T04 executed but blocked by native multipart observability and a JPEG structural validation counterexample. No project dependencies, feature code, migration, acceptance test execution, merge or deployment. Ready to Implement blocked.

## 1. Gates and evidence rules

Task-list review explicitly approved on 2026-11-25. User subsequently authorized temporary isolated GitHub Actions PHP8.2/GD/fileinfo T01–T04 preliminary feasibility only (see §13); any further material design change/probe requires appropriate separate review, no live host, no feature code or probe code on docs branch. T02 native platform and T03/T04 multipart/image safety are hard readiness gates; if wire duplicates collapse or approved rejection cannot be proven, record evidence and seek explicit reviewed ingress/design/contract resolution, no silent weakening, custom parser or global host change. Isolated evidence does not certify live hosting. T05 separate docs merge and implementation approvals; no feature code before material unknowns resolved. T06 separate exact dependency/platform approval; stop on unapproved solver change. T07 verify isolated testing and separate protected disposable reset/cleanup authorization. No production operations or sensitive data. Dependencies use IDs/ranges inclusive. Completion requires actual tests/source/CI evidence, not test files or checkboxes. No required check waived; no Ready for Review on failed/unrun checks. Test authoring can accompany code but mark complete only after execution. Preserve approved contracts and scope.

## 2. Phase A — Approval, preliminary feasibility and preparation (T01–T07)

- [x] **T01 — Review gates and environment.** Dependencies: —. Verify docs PR #9, actual develop and reviewed task list; obtain explicit scoped authorization for temporary isolated T02–T04 probes. Identify verified disposable PHP8.2 runtime and test scope, no live data, no changes to docs branch code. Evidence: Recorded approvals, refs and isolation evidence.

- [x] **T02 — Native platform feasibility.** Dependencies: T01. Inspect fileinfo/GD PNG/JPEG/WebP decode/encode, native Argon2id, PHP limits, private temp root and synthetic image smoke/memory. No platform installation or actual host change without separate approval. Evidence: PHP version, gd_info, codecs/limits/memory results; missing support blocks readiness.

- [ ] **T03 — Raw multipart feasibility.** Dependencies: T01. Real disposable native PHP8.2 HTTP probe with duplicate scalar avatar wire parts, bracketed/mixed shapes and upload/body limits. Verify observable parsed data and native method override feasibility. Never infer duplicate detection from FormRequest count. Evidence: Actual parser evidence; collapsed duplicates or unproven rejection block F03-14, obtain reviewed ingress/contract solution; no custom parser or global host change silently.

- [ ] **T04 — Image validation feasibility.** Dependencies: T02,T03. Synthetic valid exact2MiB and malformed/truncated/animated/dual-format/trailing fixtures, dimension/pixel/codec bounds and canonical encoding; determine feasible structural validation strategy and measured memory. No universal polyglot claim. Evidence: Actual evidence or blocking decision request; no silent relaxation of F03-14.

- [ ] **T05 — Resolve blockers and verify implementation gates.** Dependencies: T01–T04. Resolve material unknowns and explicitly review any necessary plan/spec changes. Obtain separate docs merge approval, verify PR #9 latest CI/diff, merged develop; obtain explicit implementation permission and create dedicated branch from merged ref. Evidence: Resolved decisions, approval, merge SHA and actual branch; no feature code if unresolved.

- [ ] **T06 — Authorized dependency resolution.** Dependencies: T05. Separate exact package/platform approval; dry-run composer require spatie/laravel-query-builder:^6.4.4 --dry-run --no-interaction --prefer-dist --no-scripts, review 6.4.4 and mandatory package-tools ^1.11 candidate, stop on unapproved changes; then approved actual require, authentic lock, validate --strict, locked install, platform/audit. GD/fileinfo requirement and CI as approved; no image package. Evidence: Actual solver/lock diff, installation and audit/platform evidence.

- [ ] **T07 — Guarded fixtures and baseline regression.** Dependencies: T05,T06. Verify APP_ENV testing, localhost MySQL8/InnoDB kids_area_test/kids_area_ci, synthetic accounts/private temp root; separate exact destructive disposable reset approval, independent HTTP/cookie jars/shared clock/barriers; run baseline regression. Evidence: Actual guarded DB/FS, baseline test results, no production operations.

## 3. Phase B — Schema and shared HTTP boundary (T08–T13)

- [ ] **T08 — Avatar reference migration.** Dependencies: T07. One forward module migration users.avatar_key VARCHAR(40) ASCII/bin nullable UNIQUE default NULL; hidden internal key, no other schema/pivot/index changes. Evidence: Reviewed migration and schema check.

- [ ] **T09 — Migration preservation.** Dependencies: T08. Forward migrate existing synthetic founder/users/hashes/roles/sessions, verify DDL, collation, multiple NULL and duplicate key denial; destructive down only approved disposable fixture. Evidence: Actual MySQL preservation/constraints F03-19.

- [ ] **T10 — Protected routes and CORS.** Dependencies: T07. Exact approved CRUD/avatar GET/selects GET; first-party gate users root/subpaths/selects, canonical origin, DELETE CORS, existing Sanctum/StaffSession, native CSRF/preflight and approved multipart override only; no bearer. Evidence: Route cache/inventory and real HTTP 401/403/419/preflight F03-07.

- [ ] **T11 — Capability before target lookup.** Dependencies: T10. Fresh staff context, independent view/create/update/delete, numeric target explicitly resolved after permission, no Manager bypass or implicit binding enumeration, original Request attributes. Evidence: Permission-before-query and missing target tests F03-07/08.

- [ ] **T12 — Scoped normalization and API errors.** Dependencies: T10,T11. Preserve exact username/email/phone/password/confirmation; trim name/search only, strict types and multipart normalization, approved empty/null semantics, safe 413/422/409/503 and known package errors, Arabic Request ID; F02 behavior unchanged. Evidence: Real type/whitespace/query/error tests F03-05/06/07/18.

- [ ] **T13 — Sensitive diagnostics.** Dependencies: T10,T12. Scope suppression to Users writes and PII/key/session queries, preserve native private handler and unrelated DB events; no raw password/SQL bindings/multipart/path/secret logging. Evidence: Sentinel/log/event restoration evidence F03-18.

## 4. Phase C — Users reads and Select Registry (T14–T20)

- [ ] **T14 — Index query validation.** Dependencies: T11,T12. Allow only scalar search/roleId/isActive, page/perPage, max255 search, positive integer overflow check, default15 clamp100, preserve 0/false, reject unknown filters/query/sort/include/fields 422. Evidence: Input matrix F03-02/03.

- [ ] **T15 — Spatie allowed filters and bounded query.** Dependencies: T06,T08,T14. Validated custom callbacks preserve comma/true/false/0 despite package coercion; literal escaped LIKE !/%/_ grouped name/username/phone AND role EXISTS/status, exact morph/guard, primary read, id DESC, identical count/page predicates and eager role load; short consistent read snapshot if needed, no global delimiter changes. Evidence: MySQL literal semantics/SQL assertions F03-02/03.

- [ ] **T16 — Explicit resources.** Dependencies: T08,T11,T15. Exact userId/avatarUrl/name/username/phone/isActive/role, email only detail, no timestamp/secret, role null on anomaly, no arbitrary first/no per-row file stat; approved pagination, Me unchanged. Evidence: Exact key sets and query evidence F03-01/03.

- [ ] **T17 — Index/detail HTTP tests.** Dependencies: T14–T16. Arabic/name, exact username case, phone leading zero, wildcard/comma/boolean words, inactive role/state/false, filters AND, unknown role, malformed, empty/out-of-range/default/max100, permission before lookup. Evidence: Real HTTP/MySQL assertions F03-01/02/03/07.

- [ ] **T18 — Core Select Registry.** Dependencies: T10–T12. Explicit registered providers, validate exact ordered unique keys max2, all authorized before dispatch, no partial results/dynamic classes/new permissions. Evidence: Registry authorization/dispatch proof F03-17.

- [ ] **T19 — Identity role options.** Dependencies: T18. roles active web create OR update; userFilterRoles all web view; numeric ID/stored alias ordered ID ASC, max200 per provider limit201 probe, overflow409 entire response, no permissions key. Evidence: Provider SQL/options/bounds tests F03-17.

- [ ] **T20 — Select HTTP tests.** Dependencies: T18,T19. Create-only/update-only/view-only, mixed forbidden no provider executes, unknown/duplicate/malformed, requested order, empty and limit+1, no partial output. Evidence: Actual response/provider checks F03-17/18.

## 5. Phase D — Private Avatar infrastructure (T21–T25)

- [ ] **T21 — Private storage and keys.** Dependencies: T02–T06,T08,T13. Dedicated local identity-avatars root, no public link/serve, canonical immutable random32hex+extension key, exclusive creation and checked write/close/size, no client path/filename. Evidence: Temp root, collision/no-overwrite/private path tests F03-14/15/16.

- [ ] **T22 — Native image validation and normalization.** Dependencies: T04,T21. Proven fileinfo/GD and reviewed structural checks: 2MiB input/output, PNG/JPEG/WebP matched extension, static max2048x2048/4.19MP, longest edge512 preserve ratio/transparency no upscale/crop, strip metadata canonical encode. Reject invalid/animated/unsafe; follow resolved gates. Evidence: Actual valid/invalid formats, dimensions, memory and output bounds F03-14.

- [ ] **T23 — Staged file lifecycle.** Dependencies: T21,T22. Stage complete file before locks; capture old key under lock; known rollback candidate cleanup only unreferenced, commit old cleanup only after primary reference check, ambiguity retain both; no in-flight/other-user/age-only deletion. Document quiesced backup/reconciliation. Evidence: Storage failure and safe reference checks F03-16.

- [ ] **T24 — Private avatar read.** Dependencies: T11,T16,T21. Authorized primary current key; bounded bytes before response, fixed MIME inline/private no-store/nosniff; absent user/key404, missing referenced file500 with one reread on replacement race; no streaming lock/public path. Evidence: Actual binary/access/header/missing/replacement tests F03-15.

- [ ] **T25 — Real native transport tests.** Dependencies: T03,T04,T10,T21,T22,T24. Real PHP8.2 HTTP multipart POST/create and POST _method=PUT update feasibility/transport probe; resolved duplicate enforcement, unknown/mixed parts, upload errors exact2MiB/+1/body limit, mismatched format, CSRF/preflight. Repeat on actual CRUD T35/T36; no production probe route. Evidence: Raw multipart and codec proof F03-07/14/15.

## 6. Phase E — Transactional user management (T26–T37)

- [ ] **T26 — Create/update Form Requests.** Dependencies: T11,T12,T22,T25. Strict allowlists, full PUT ordinary fields/contact presence, approved username/email/phone exact, password>=8/confirmation/preserve/orphan rules, strict role/boolean/avatar/removal, no ID/status override/direct grants/currentPassword/paths; authorized early founder/self checks. Evidence: Validation/type/presence tests F03-04/05/06/08/14.

- [ ] **T27 — Known constraint errors.** Dependencies: T08,T12,T26. Primary unique validation excluding trusted target; map verified MySQL1062 named username/email/phone indexes to field422 after rollback, relevant protected FK1451 to409, unknown500, no sensitive values/ambiguous retry. Evidence: Actual constraints/error classification F03-05/13/18.

- [ ] **T28 — Extend IdentityAccessMutation.** Dependencies: T07,T11,T13,T27. Reuse manager mutex/ordered access rows, one outer InnoDB transaction attempts1, permanent roles and six-capability postcondition; expose commit/rollback/unknown state, preserve F01 callers; postcommit cache failure not rollback; 5s per conflicting lock restore prior setting/preserve primary error/discard uncertain connection. Evidence: Mutation regression and timeout/outcome proof F03-12/16/19.

- [ ] **T29 — Current actor and initiating session.** Dependencies: T28. Under manager mutex lock current access/target rows then actual session row, owner/version/current user/idle valid iff last_activity > now-43200; current permission; stale401 vs missing capability403, retire only invalid binding after rollback, original request, no reverse limiter locks. Evidence: Current-read/idle/actor tests F03-07/10/12.

- [ ] **T30 — Reusable revocation.** Dependencies: T28,T29. Generation-only revoke or optional new Argon2id hash and target-only session deletion in transaction, compare version/no wrap/exact row; combined disable+reset one increment; reenable no restore; Profile compatibility. Evidence: Hash/version/session/Profile assertions F03-10/11.

- [ ] **T31 — CreateUserAction.** Dependencies: T23,T26–T30. Founder exists/new ID!=1; active single web role syncRoles, explicit fields, hash/stage outside locks, active true, postcondition and known outcome; 201 minimal, duplicate rollback cleanup. Evidence: User/pivot/hash/file/rollback proof F03-04/05/12/14/16.

- [ ] **T32 — UpdateUserAction.** Dependencies: T23,T26–T30. Full fields, founder/self-password, inactive retain/new active/reenable rules, roleless repair, no multi/direct grant auto-repair; preserve/reset password and preserve/remove/replace avatar, capture old key under lock; revoke only disable/reset, postcondition. Evidence: Atomic state/role/version/file and 200 F03-05/06/08–12/14/16.

- [ ] **T33 — DeleteUserAction.** Dependencies: T23,T27–T30. Block founder/all self deletes; independent permission, native own membership removal and target session delete, actual RESTRICT conflict409, no historical cascade, last-admin check and postcommit file cleanup. Evidence: Target-only delete/rollback proof F03-08/12/13/16.

- [ ] **T34 — Postcommit and self-disable lifecycle.** Dependencies: T28–T33. Known commit clear self-revoked device and suppress touch, stale native save no authority, active role preserves session, unrelated device unaffected; distinguish cache/cleanup failure and ambiguous commit, no retry. Evidence: Browser/final save/cache/cleanup proof F03-09/10/11/16/18.

- [ ] **T35 — Create/update HTTP integration.** Dependencies: T25,T31,T32,T34. Actual JSON/multipart create and update/201/200, validated fields/duplicates, reset/preserve/self rejection, avatar optional/replace/remove/invalid retains old, native CSRF/origin and independent permission. Evidence: HTTP/DB/filesystem assertions F03-04–07/14–16.

- [ ] **T36 — Founder, role and revocation tests.** Dependencies: T30–T32,T34,T35. Founder/self rules incl image/reset, active/inactive role rules and anomaly denial, fresh Me/grants, all-device disable/reenable, combined increment, unrelated devices/Profile unchanged. Evidence: Real multi-cookie/DB state F03-08–12.

- [ ] **T37 — Delete and FK tests.** Dependencies: T33,T34. Authorized nonself/nonfounder, disposable actual FK RESTRICT fixture and 1451 conflict retaining row/pivots/session/file, no role/other account removal; no synthetic production table. Evidence: Real MySQL/HTTP evidence F03-13/16.

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
| T01–T04 isolated feasibility authorization/evidence | Explicitly authorized; target PHP8.2.36/GD2.3.3 runs 45650686580 and 45650969241 completed. T01/T02 passed; T03/T04 executed, unresolved blockers (§13), remain unchecked. |
| T05 documentation merge and implementation | Not authorized / Not Run |
| T06 package/platform | Not authorized / Not Run |
| T07–T09 guarded DB/FS/migration | Not Run |
| T10–T37 HTTP/reads/selects/avatar/writes | Not Run |
| T38–T46 races/faults/performance/scope | Not Run |
| T47–T49 full tests/Pint/CI | Not Run |
| Implementation merge / deployment | Not authorized |

Risks: PHP8.2 parser observability and strict image structural validation; GD/host memory/upload limits; ambiguous commit and cache/file cleanup; manager-lock breadth R09; contains search scans; select cap; real TLS/cookie/DB/disk/backups/logs unverified. Audit deferred. Manual orphan reconciliation only authorized maintenance with backup/quiescence/current references, no age-only deletion.

## 11. Definition of done and next action

All 49 tasks and 19 scenarios actually verified, no material blocker, exact source/diff and latest full CI reviewed, docs/handoff updated. Separate merge/deploy approval. **Next:** review confirmed multipart observability and JPEG structural gaps in §13; obtain explicit contract/technical decision and any necessary follow-up isolated probe authorization. Resolve T03/T04 before T05 and any code. Docs merge, implementation, dependencies and protected operations remain separate gates.

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
