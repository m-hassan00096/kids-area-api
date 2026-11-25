# Kids Area — AI / Conversation Entry Point

**Read this file first when working on this repository.** This is a small navigation guide for both coding assistants and ChatGPT conversations with GitHub access. It does **not** install, enable, or require Codex or Laravel Boost.

## Mandatory resume order

1. **Read the latest handoff:** [Kids Area Conversation Handoff](docs/00-overview/kids-area-conversation-handoff.md) from the active GitHub branch (prefer `develop` once merged). This file identifies the last confirmed milestone, decisions, current blockers, and next discussion step.
2. **Verify freshness:** inspect the actual `develop` HEAD, open PRs/active branch, existing feature files and code. The handoff is a progress summary; never treat an old snapshot as proof that code, tests, or merges happened.
3. **Read the relevant canonical sources:** [AI Workflow Rules](docs/02-standards/ai-workflow-rules.md), [Project Overview](docs/00-overview/project-overview.md), [Backend Architecture](docs/01-architecture/backend-architecture.md), relevant `docs/02-standards/*.md`, `docs/03-modules/<module>.md`, and any applicable `specs/<module>/<feature>/{spec,plan,tasks}.md`.
4. **Continue the last unresolved question** instead of restarting the project discovery, unless the user explicitly changes direction.

## Handoff maintenance

- Update **the same single handoff file** at major approved decisions, milestones, handoffs between chats, and meaningful blockers; keep the current date, verified branch/PR/commit, approved vs proposed decisions, actual validation state, and the precise next action.
- Do not invent decisions, completed tasks, merge status, or test results. Preserve working context while keeping credentials, user/child information, private logs and secrets **out of Git**.
- The handoff **does not supersede** the user's latest approved decisions, module references, standards, or verified code. If they conflict, flag the mismatch and correct the appropriate document after approval.
- Use the project's GitHub-first review workflow: documentation changes on an approved branch/PR; **never merge to `develop` without the user's explicit approval**. No implementation or deployment without the relevant gates.
- When GitHub access is unavailable in a new conversation, ask the user to provide the file/link or its text; **do not claim the file was read automatically**.

**Current workflow:** Consult the **latest GitHub handoff** for the active feature and next pending decision. This file deliberately avoids hard-coded, quickly outdated discussion status.
