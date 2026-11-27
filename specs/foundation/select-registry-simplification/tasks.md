# Select Registry Simplification — Ordered Tasks

> Draft for review. All 11 tasks unchecked / Not Run. No implementation or tests claimed. AC numbers refer to spec.md.

- [ ] **T01 — Approved contract and baseline** (AC01–12). Review spec/plan/tasks and docs updates, verify live develop and existing 5 keys. Record exact branch and prior CI as historical.
- [ ] **T02 — Consumer and test inventory** (AC03, AC04, AC11). Exact live grep of cap settings/error/translation/SQL observers/performance and key validation; list affected code/tests/docs. Check no unrelated consumers before removal.
- [ ] **T03 — Registry key validation** (AC01–03, AC07–09). Allow 1–5 distinct registered keys; retain existing strict controller/authorization-before-read. Guarded isolated six-distinct-provider case.
- [ ] **T04 — Provider changes** (AC04–07, AC10). Remove cap/limit+1 branches from all three classes, preserve actual eligibility, primary read, sort/projection and permission alias integrity. Keep interface and registered keys.
- [ ] **T05 — Obsolete cap cleanup** (AC04, AC11). Remove unused config and obsolete translation only after consumer check; do not remove shared BusinessException or unrelated errors. Preserve historical evidence.
- [ ] **T06 — Core and Identity Select verification** (AC01–10). Update provider/native HTTP tests for exact 1/5, six valid test-only, invalid/duplicate, mixed denied both orders zero reads, exact eligible/complete results and ordering; retain session and exact catalogue checks. Adapt guarded SQL instrumentation to uncapped queries.
- [ ] **T07 — Activity Select and regression expectations** (AC04–07, AC10–12). Replace historical cap overflow tests and obsolete performance expectations; check full all/active results and unchanged CRUD/list/pagination/security; no skipped assertions unrelated to change.
- [ ] **T08 — Real MySQL performance** (AC04, AC12). Guarded synthetic scalable provider fixtures 200/201, 500/501, 1,000, 2,000, 10,000; appropriate fixed permission catalogue; actual complete returned values/ordering, mixed sets, no N+1, query counts, SQL plans/time, HTTP sample count/median/max, bytes and PHP memory. Record growth and residual host risk, no SLA or artificial cap.
- [ ] **T09 — Full verification** (AC01–12). Focused and full configured PHPUnit/MySQL 8/Pint/CI including existing guarded preflight/forward migrations, route cache and Composer checks. Review actual logs, compare scope/no schema/dependency/grant/route changes, map each AC.
- [ ] **T10 — PR review and explicit merge authorization** (AC12). Exact green head, test evidence, limitations, docs/handoff; mark ready only when checks verified. Ask explicit approval, no automatic merge.
- [ ] **T11 — Authorized merge and verification** (AC12). Merge exact reviewed head into develop only after approval, confirm resulting tree and live PR/handoff evidence without recursive status-only commits. No production operation; next People F01 specification.

## Verification ledger

All AC01–AC12 and T01–T11 Not Run; this documentation PR may run existing baseline CI only and is not new-contract evidence. Record actual command/environment, exact SHA, CI link/job and results when performed. 
