# Kids Area — Development & AI Workflow Rules

> **Canonical path:** `docs/02-standards/ai-workflow-rules.md`  
> **Status:** Approved governance; documentation only, not evidence of implemented code, tests or CI.  
> **Current mode:** Direct GitHub editing (GitHub-first). Codex and Laravel Boost remain inactive. Root `AGENTS.md` is now an approved **read-first guidance file only**, not a tool integration.  
> **Scope:** All Kids Area backend modules and approved feature changes.

## 1. Purpose and project context

Deliver Kids Area incrementally through approved, reviewable and verifiable features, with clear business ownership, careful financial/timekeeping behavior and no unrequested complexity.

V1 is a **Laravel 12 API-first modular monolith** using **MySQL 8 / InnoDB**, operating at one physical location. The eleven business modules are **Identity, People, Employees, Workforce, Payroll, Activities, Sessions, Cafe, POS, Finance and Reports**. The backend uses `/api/v1`, Arabic-first system messages, exact money arithmetic, explicitly assigned permissions and auditable sensitive operations.

This file governs **how development work is planned, changed, verified and reviewed**. The overview, architecture, other standards and module references own the actual business and implementation contracts.

## 2. Current GitHub-first development mode

- Discuss and approve module and feature documents before creating or revising their content **directly on GitHub**.
- Implement each substantive approved feature on its own branch from `develop`. Open a PR targeting `develop` and **do not merge without the user's explicit approval**.
- Author feature files manually. **Do not require Spec Kit CLI, `.specify/`, a separate project constitution or generated Spec Kit artifacts.**
- Do not install Codex or Laravel Boost as a prerequisite. The explicitly requested root `AGENTS.md` is a short **reading guide for any new assistant**, not a requirement to enable those tools. Their future use is addressed in section 14.
- **Committing code to GitHub does not execute it.** A written migration, test or workflow definition is not proof that it ran successfully.

## 3. Sources of truth, reading order and conflict handling

**Mandatory new-chat / resume entrypoint:** Read the latest [Kids Area Conversation Handoff](../00-overview/kids-area-conversation-handoff.md) from the current GitHub branch (use `develop` after merge), then check the actual branch/PR/commit status. Read task-relevant canonical sources below; the handoff is a progress/navigation summary **only**, not a higher-priority requirements authority. If no GitHub access exists, ask for the link/file/text rather than pretending it was loaded.

For ongoing focused edits, read sources **relevant to the task**, rather than reloading every project document on every edit.

| Source | Responsible for |
|---|---|
| Current explicitly approved user decision | Authorized change; affected canonical documents must be updated before implementation |
| `docs/00-overview/project-overview.md` | Project goals, V1 scope, major business rules and journeys |
| `docs/01-architecture/backend-architecture.md` | System structure, module ownership, dependencies, deployment constraints |
| Relevant `docs/02-standards/*.md` | API, code, database, error handling, localization, permissions and workflow conventions |
| `docs/03-modules/<module>.md` | Approved detailed rules and contracts for the owning module |
| Feature `spec.md` | Approved behavior, boundaries, edge cases and acceptance criteria |
| Feature `plan.md` | Approved technical design, schemas, endpoints, risk and test strategy |
| Feature `tasks.md` | Reviewed execution steps, dependencies, verified task status and blockers |
| Existing code, applied migrations and actual tests | Implemented reality **only**, not permission to override requirements |

These authorities have **distinct subject ownership**. A compatible detail in a feature spec is **not a conflict**. If there is a material contradiction between approved documents or between a document and existing code, explain the exact difference and impact, recommend a resolution and stop only the **affected** implementation until approved. Record the approved decision in its owning canonical document and update impacted references before proceeding.

Never choose a rule merely because its file is newer, because the existing code happens to work that way, or because a framework/tool suggests a different approach. Trivial technical details not affecting an approved requirement may follow the relevant standards without unnecessary escalation.

## 4. Module references and feature artifacts

The canonical module reference is `docs/03-modules/<module>.md`, such as `docs/03-modules/sessions.md`. It contains approved module purpose, actors, workflows, domain rules, ownership boundaries, sensitive operations, relevant API/data expectations and acceptance examples. It **does not** contain task checklists or daily progress notes.

For a substantive feature, directly author:

```text
specs/<module>/<feature>/
  spec.md
  plan.md
  tasks.md
```

- **`spec.md`:** approved functional scenarios, scope, business exceptions and testable acceptance criteria.
- **`plan.md`:** affected modules, technical approach, routes/requests/resources, schema/migrations, permissions, transaction boundaries, security, verification and trade-offs.
- **`tasks.md`:** small ordered tasks, dependencies, relevant tests, verified completion state and blockers.

Use stable descriptive feature slugs; numbering is optional. A cross-module feature lives under a lead module but must examine all affected module references, ownership and tests. Do not invent missing financial, access, subscription or state-transition behavior. Small unambiguous fixes may use a proportionate code-and-regression-test process without three new documents.

## 5. Approval gates and autonomous execution within scope

For substantive work:

1. **Approve** the module reference when required.
2. **Approve** feature `spec.md`, including acceptance criteria.
3. **Approve** `plan.md`, including schema, contracts and verification.
4. **Review** `tasks.md` before implementation.
5. Implement reviewed tasks incrementally without requesting approval for every routine file, test, query or commit.
6. Return for approval when business rules, architecture, dependencies, destructive/production changes or scope would change.

Approval to build a feature is **not** approval to carry out an otherwise protected environment operation. Only mark a task complete when its applicable checks have actually been verified.

## 6. Git workflow, review and merge

- Create **one feature branch from `develop` for each feature** (or a dedicated documentation branch for an approved documentation change), not one branch per task.
- Write coherent, reviewable commits. Keep code, tests and affected documentation aligned; do not modify unrelated modules for cleanup.
- Open a PR to `develop` containing the implementation summary, associated approved artifacts, files and schema changes, verification evidence, blockers and risks.
- A **Draft PR** may share incomplete work. It does not mean the feature is `Ready for Review`.
- GitHub Actions should execute relevant PHPUnit, Laravel Pint and real MySQL 8 checks **when configured**. Until then, report checks as **Not Run**; do not claim feature readiness with missing mandatory verification.
- **Merge to `develop` only with the user's explicit approval**, regardless of CI status. `main` and production releases require separate authorization. A merge to `develop` never authorizes a production deployment.

## 7. Focused implementation and coding constraints

Implement the approved feature and the smallest necessary technical improvements. Directly related fixes/refactors are allowed when needed for correctness, clarity or tests; unrelated tech debt is reported, not silently changed. Do not create speculative abstraction layers or install packages without approval.

- Respect the eleven module boundaries; each business write has a defined owning module. Cross-module interactions must follow the approved architecture.
- Follow Laravel 12 conventions: thin Controllers, **Form Requests** for validation, **Policies/Gates** for authorization, **Resources** for API serialization, focused **Actions** for every create/update/delete and state transition. Add Services/contracts/DTOs only when justified.
- Conform to `/api/v1` response and error standards, safe Arabic system messages and stored role names and permission `alias_name` display metadata; never authorize on display text.
- Persist money as **DECIMAL(12,2)** with exact arithmetic; no authoritative float math. Preserve canonical append-only posted financial movements, historical evidence, atomic writes, required uniqueness, idempotency and row locking.
- Do not add runtime dependencies on **Redis, queues/Jobs, cron, Laravel Scheduler, WebSockets or long-running workers** in V1.
- Do not use a feature as a pretext to redesign another module, add a generic workflow engine or expand approved scope.

## 8. Database safety, environments and protected changes

Within a **verified safe development/test environment** and the approved plan, ordinary code changes, tests, read-only schema inspection and **non-destructive approved migrations** may proceed without an extra approval.

Obtain **explicit separate approval** before data deletion/truncation, destructive schema changes or historical data rewriting; production migrations or mutations; changing production configuration or secrets; adding packages; changing architecture/business rules; or executing outside scope. State the **exact operation, environment, impact, backup/recovery or rollback plan and verification** before requesting approval. Approval is specific to the described operation.

Treat databases with unknown provenance as protected. A "development" database cloned from production is still sensitive. Inspect the current schema and applied migration history first; do not rewrite already-deployed migrations to change existing production schema. Use reviewed forward migrations. On constraint or migration failure, investigate the actual state instead of issuing `migrate:fresh`, `TRUNCATE` or arbitrary cleanup. Rollback tests belong only in approved disposable environments.

Do not edit `vendor/`, secrets, deployed backups, or protected environment files as a shortcut.

## 9. Security, privacy and controlled inspection

- Prefer **synthetic test fixtures** for children, guardians, employees, pay, subscriptions, invoices, payments and refunds.
- Access to real children's/guardians' records, actual payroll, production-derived data or dumps, `.env` secrets and production logs requires **specific approval**, even when a database is labeled "dev".
- Do not expose names, guardian contact numbers, payroll amounts, credentials, tokens or raw sensitive payloads in commits, PRs, screenshots, reports or technical logs.
- Minimize inspected data; do not use blanket `SELECT *` dumps of private tables simply because inspection tools allow it.
- Follow the Permissions Standard: single effective role for protected access, active-role checks, explicitly granted manager permissions, Policy/Gate enforcement, sensitive audit logging and protected record visibility.
- Never disclose raw SQL internals, stack traces, secrets or personal data in API error responses.

## 10. Testing, failure recovery and blockers

Verify progressively: **focused Task tests → affected module tests → relevant integration and concurrency tests → Pint → available CI**. Financial transactions, locks, race conditions, duplicate requests and database constraints require **real MySQL 8** verification where applicable; SQLite does not prove InnoDB behavior.

For each check, distinguish **Passed**, **Failed**, **Not Run** and give the real command/environment/result when available. A committed test file is **Not Run** unless it was actually executed.

When tests fail, investigate and repair technical causes **within approved scope**, add or maintain regression coverage and rerun affected checks. Do not rewrite correct tests to fit a broken implementation. Distinguish new regressions from known baseline failures.

When a blocking business decision, migration risk, environment restriction or scope conflict needs approval, leave the task unchecked, record the blocker in `tasks.md` and request the decision. Independent tasks may proceed if unaffected. **A feature with required failed or unrun checks is not Ready for Review.**

## 11. Context and documentation lifecycle

The persistent **authoritative** evidence is **module reference + approved feature `spec.md`/`plan.md`/`tasks.md` + actual Git history and source code**. There is also **one explicitly requested living project-wide handoff** at [`docs/00-overview/kids-area-conversation-handoff.md`](../00-overview/kids-area-conversation-handoff.md) to carry a concise conversation-resume snapshot. Do **not** create redundant `context.md`, `ai-memory.md`, or separate handoff files per feature.

At the start of a new chat/session, **read the living handoff first**, then inspect the actual current branch/PRs, relevant commits and code, feature documents, needed standards and test evidence. Verify checked tasks against implementation; a handoff statement or checkbox alone is not proof.

At major approved decisions, feature milestone completions or meaningful blockers, update **that same handoff** with a timestamp, verified Git ref/PR, approved versus proposed decisions, current work/test status, unresolved question and the next action. Follow the normal branch/PR review flow; do not merge handoff edits without explicit authorization. Keep personal/secret material out of Git. The handoff never silently changes canonical requirements.

Update only the document that owns changed information: `tasks.md` for verified progress/blockers; `plan.md` for approved meaningful technical design changes; `spec.md` for approved feature behavior; module references for module-wide rules; overview/architecture/standards for their approved domain decisions. An optional **single** project tracker may summarize major milestones, not duplicate tasks; do not create one by default.

## 12. Definition of Ready and Done

- **Ready to Implement:** feature ownership and boundaries are clear, module reference and feature `spec.md`/`plan.md` are approved, `tasks.md` is reviewed, and material unknowns/risks are resolved.
- **Task Completed:** work meets its acceptance conditions and required evidence has been verified; `tasks.md` reflects actual status.
- **Feature Ready for Review:** mandatory work and acceptance criteria are met; relevant PHPUnit, MySQL 8 and Pint checks have actually passed; affected migrations/security/contracts are reviewed; PR and completion report disclose any remaining limitations.
- **Feature Merged:** the user has approved the merge and GitHub confirms the PR was merged into `develop`. A written PR or passing tests alone do not establish this status.

## 13. Evidence-based completion reporting

Keep the completion report brief, factual and useful for PR review:

| Report field | Include |
|---|---|
| Scope | Feature and owning/affected modules |
| Git | Branch, meaningful commits and PR URL |
| Changes | Main files, schemas, API/security and migration implications |
| Verification | Actual PHPUnit, Pint, MySQL 8/CI commands and **Passed / Failed / Not Run** results |
| Risks/blocks | Remaining failures, unrun checks, access restrictions, unresolved decisions |
| Next action | Specific review/merge approval or blocker resolution |

Never invent test counts, successful migrations, CI results, deployments or merged status. For documentation-only work, report documentation/diff checks; do not imply that an uncreated Laravel application was tested.

## 14. Optional future Codex and Laravel Boost integration

**Codex and Laravel Boost remain inactive for the current GitHub-first development mode.** Do not install or configure either until separately requested. The user **has explicitly approved a root `AGENTS.md` solely as a read-first, link-based handoff guide for future AI conversations**; its presence does not enable Codex, Boost or any automatic background work.

If Codex or Boost is separately enabled later, the same sources, approval gates, branch/PR rules, task and verification standards, privacy boundaries and protected-operation approvals apply. Laravel Boost may help inspect actual installed versions, routes, schema, logs and official package documentation; it is **not a business-rule authority** or a substitute for test execution. Treat Boost queries/Tinker as live operations subject to environment and sensitive-data restrictions. Review any generated configuration. Keep `AGENTS.md` short, link-based and nonduplicative.

**Change control:** Amend this workflow and other canonical documents only after the underlying decision is approved; never silently change business rules to make existing code or tools easier to use.
