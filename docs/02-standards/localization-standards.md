# Kids Area — Backend Localization Standards

> **Status:** Approved V1 conventions; implementation not yet started.  
> **Canonical path:** `docs/02-standards/localization-standards.md`  
> **Stack:** Laravel 12 API-first hybrid modular monolith, MySQL 8, `nwidart/laravel-modules` (version compatible with installed Laravel).  
> **Scope:** System-owned text, validation, fixed labels, role/permission display aliases, API presentation boundaries, future frontend integration, and generated documents.  
> **Related:** [Project Overview](../00-overview/project-overview.md), [Backend Architecture](../01-architecture/backend-architecture.md), [Code Standards](code-standards.md), [API Standards](api-standards.md), [Database Standards](database-standards.md), [Error Handling Standards](error-handling-standards.md), and future [Permissions Standards](permissions-standards.md).

## 1. Purpose and Explicit Boundary

This standard defines **Arabic-first application localization**, without translating or changing stored user-entered business data.

**In scope:**

- Laravel-owned success/error messages, when a nonempty message is appropriate.
- Authentication, authorization, validation and generic safe failure text.
- Module-owned business success and failure messages.
- Human-readable labels for **fixed integer-backed enums** and system-generated descriptions.
- Arabic display metadata for system-defined roles and permissions, specifically **`roles.alias_name` and `permissions.alias_name`**, which are **stored display values**, not language-file translations.
- Static frontend interface text and RTL presentation ownership once frontend development begins.
- Backend-created PDF, Excel and any approved synchronous reports.
- Safe missing-translation behavior and PHPUnit localization tests.

**Out of scope in V1:** translating child, guardian, coach, employee, activity, group, cafe product or other persisted user-entered records; multilingual CMS; `*_translations` data tables; multilingual slugs; user language preferences; language-switching endpoints; a locale negotiation middleware; multi-branch/tenant locale policies; queued localization jobs.

**Nonnegotiable distinction:** **Laravel translation files are for system-owned messages and fixed labels. Business data stored in MySQL is returned as recorded, without automatic translation.** The permission/role `alias_name` values are themselves business display data and are **not translated by this localization layer**.

## 2. Approved Decisions Summary

| Area | Approved V1 choice |
|---|---|
| Operating language | Arabic (`ar`) only in V1; prepare for future languages without enabling them |
| Translation ownership | **Hybrid Module-Owned Translations:** Core owns shared Laravel messages; each business module owns its business messages |
| Translation key convention | **Context-Based:** stable descriptive English `snake_case` keys under `errors` / `success` (and other groups only when needed); parameters and pluralization |
| Data scope | **System-Owned Localization:** never translate or rewrite persisted user-entered values |
| Fixed enum status display | Stable integer `status`; add localized `statusLabel` in Resources **only when needed** |
| Validation | **Hybrid Validation Localization:** Core for shared rules; modules for their field names and custom rules |
| Missing translation | Safe Arabic fallback plus bounded warning and PHPUnit coverage; no raw key leak or change to transaction/status |
| Formatting | API machine values are invariant; frontend formats presentation; backend formats its own Select labels/documents |
| RTL | Frontend owns screen RTL/mixed text; backend owns localized messages and generated PDF/Excel layout |
| Roles and permissions | **DB `alias_name` is the display label.** `name` is the stable technical identifier for authorization. **No translation-key mapping for role/permission aliases.** |
| Testing | Shared Laravel/API localization tests **plus module-specific tests** for messages, parameters, enums and alias display; PHPUnit/MySQL 8 where relevant |

These decisions implement the approved API/Code/Database/Error Handling standards; they do not replace those contracts.

## 3. Locale Strategy and Future Readiness

Set the normal Laravel environment/configuration to:

```env
APP_LOCALE=ar
APP_FALLBACK_LOCALE=ar
```

- `ar` is the only active locale in V1. The global default and fallback are both `ar`.
- The application must **not** silently select a different locale from `Accept-Language`, a browser setting, a per-user preference or query string.
- Do **not** implement an `Accept-Language` middleware, a locale-switching API, per-user language settings or a multilingual business-content schema in V1.
- Prepare system-owned text structurally with stable translation keys; an eventual English release requires English translations, frontend resources, an explicit selection policy and tests.
- Adding a future `lang/en` folder does **not** itself mean the system supports English. Existing DB-backed `alias_name`, child names and activity names remain unchanged by such a change.
- Backend locale affects localization of **system-generated human-readable text only**; never affect IDs, permission keys, HTTP status, enums, money arithmetic, stored schedule values or timestamps.
- MySQL and canonical API property naming stay stable English conventions independent of locale.
- Only one location operates in V1; its configured IANA time zone is initially `Africa/Cairo`, independent of the app's language setting.

## 4. Hybrid Module-Owned Translation Organization

Use **Laravel native localization** (PHP translation arrays, `__()`, `trans_choice()`) and the existing `nwidart/laravel-modules` architecture.

**Illustrative structure, created only as features need files:**

```text
lang/
└── ar/
    ├── auth.php               # shared authentication messages
    ├── validation.php         # common Laravel validation rules
    ├── errors.php             # shared safe errors / generic failures
    ├── common.php             # other shared system labels, if needed
    └── enums.php              # only for genuinely cross-module fixed enums

Modules/
├── People/
│   └── lang/ar/
│       ├── messages.php       # People business success/errors
│       └── validation.php     # People field names and custom validation
├── Activities/
│   └── lang/ar/
│       ├── messages.php
│       └── validation.php
├── Sessions/
│   └── lang/ar/messages.php
├── Finance/
│   └── lang/ar/messages.php
└── Payroll/
    └── lang/ar/messages.php

# When a second locale is specifically approved:
# lang/en/ and applicable Modules/*/lang/en/ files with equivalent keys
```

**Ownership rules**

1. Laravel framework-wide text goes in shared `lang/ar`; a module's business-specific messages and field labels go in the owning module.
2. Avoid copying module messages into Core or introducing duplicate/conflicting translation sources for the same key.
3. Use the **actual translation namespace and path supported by the installed** `nwidart/laravel-modules` version. During Foundation implementation, verify that module translations register and load correctly in Laravel 12. Do not claim this has already been tested.
4. Namespaced calls can look like `__('sessions::messages.errors.timers_not_finished')` **once the namespace is registered**.
5. No separate "Localization module", database translation service or runtime translation API is required.
6. A module should not edit another module's translation files to implement its own feature.
7. Fixed technical names, such as Spatie role/permission `name` fields, do not become translation keys or change on locale updates.

## 5. Context-Based Translation Keys and Interpolation

**Approved:** group messages by purpose (`errors`, `success`), then choose stable descriptive lowercase `snake_case` keys.

```php
// Illustrative: Modules/Sessions/lang/ar/messages.php
return [
    'errors' => [
        'timers_not_finished' => 'لا يمكن إغلاق الجلسة قبل إنهاء جميع المؤقتات.',
        'already_closed' => 'تم إغلاق الجلسة بالفعل.',
    ],
    'success' => [
        'closed' => 'تم إغلاق الجلسة بنجاح.',
    ],
];
```

```php
// Illustrative Action, assuming module translation registration:
throw new BusinessException(
    message: __('sessions::messages.errors.timers_not_finished'),
    httpStatus: 409,
    errorCode: 'SESSION_TIMERS_NOT_FINISHED',
);
```

- Prefer stable lookup IDs, not source Arabic/English sentences as keys.
- Translation keys are not the same as machine error codes. `SESSION_TIMERS_NOT_FINISHED` remains a separate optional stable identifier under `errors.code`.
- Use Laravel replacement parameters for dynamic data. Do not concatenate Arabic fragments in different places.
- Prefer `trans_choice()` for genuinely count-sensitive grammar rather than scattering quantity-specific conditional branches.
- Do not insert unrestricted personal data into an error message; parameters should be safe, validated and useful to staff.
- Rewording a translated sentence must not require changing its key or the corresponding business error code.
- Never make business decisions, permission checks, status comparisons or database queries based on localized strings.

**Example: interpolation**

```php
// Illustrative translation within the Activities module
'group_capacity_reached' => 'وصلت المجموعة إلى الحد الأقصى المسموح (:capacity).',

// In the owning Action, within 'errors' group:
__('activities::messages.errors.group_capacity_reached', [
    'capacity' => $group->max_children,
]);
```

## 6. System-Owned Text vs Stored Business Data

**No user-entered data in MySQL is translated automatically.** Return names, descriptions and notes exactly as stored (subject to ordinary API serialization and security rules), even if their writing system differs from the Arabic UI.

| Kind | Example | Localized? |
|---|---|---|
| System success/error sentence | "تم تسجيل الحضور بنجاح." | **Yes:** translation key |
| Laravel validation rule or attribute display name | "حقل اسم الطفل مطلوب." | **Yes:** translation key |
| Fixed system status label | "نشط" / "مغلق" | **Yes:** translation key, while the stored enum value is stable |
| System-generated schedule description | "السبت والاثنين — ٤ مساءً" | **Yes:** constructed from structured data, not stored as authoritative schedule text |
| Child/guardian/employee/coach name | User-entered Arabic or English | **No:** return as stored |
| Activity/group/cafe product name | E.g. `Swimming Academy` | **No:** return as stored |
| Notes and custom descriptions | Staff-entered text | **No:** return as stored |
| Role/permission `alias_name` | E.g. "إغلاق الجلسات" | **No translation:** stored display value, returned as stored |
| Role/permission `name` | `sessions.close` | **No translation:** security identifier |

Example: an activity saved as `Swimming Academy` is **not** automatically changed to "أكاديمية السباحة" in either the database or the API because the application language is Arabic.

**Computed labels:** A group schedule label may combine authoritative weekdays, wall-clock schedule time and names. Construct it at the Resource/Select Provider boundary; **do not store a derived Arabic sentence as authoritative schedule data**. Distinguish that computed description from any user-provided group name.

Avoid `*_ar`/`*_en` duplication, translated business tables, translation writes on read, machine translation, or extra language columns unless a later product decision explicitly adopts multilingual business content.

## 7. Arabic Validation, Authentication and Business Errors

**Approved: Hybrid Validation Localization.**

- Shared Laravel validation messages live in Core's `lang/ar/validation.php`.
- A module owns human-friendly names of its **own** fields and custom domain-validation messages, such as `Modules/People/lang/ar/validation.php`.
- Use normal Laravel **Form Requests**, per-request `attributes()` or `messages()` as appropriate, and the already approved central `App\Support\ApiResponse` / global Laravel exception renderer.
- The public JSON error **keys** remain exact `camelCase` field names, while the **sentences** can use Arabic field descriptions.
- If a business error depends on persisted current state (rather than malformed input), the owning Action raises `BusinessException`, formatted centrally with the appropriate status (often `409`).
- An unknown exception becomes a safe Arabic `500`; do not expose SQL, stack traces, internal paths or raw translation keys.

**Illustrative Form Request mapping**

```php
public function attributes(): array
{
    return [
        'childName' => __('people::validation.attributes.child_name'),
        'guardianId' => __('people::validation.attributes.guardian'),
    ];
}
```

**Illustrative 422 API response**

```json
{
  "success": false,
  "message": "البيانات المدخلة غير صحيحة.",
  "errors": {
    "childName": ["حقل اسم الطفل مطلوب."],
    "guardianId": ["حقل ولي الأمر مطلوب."]
  }
}
```

Never change names of public fields merely to make them read naturally in Arabic.

## 8. Fixed Enum Labels and Select Registry

**Fixed business state:** use an **integer-backed PHP enum** as approved in Code/API Standards; the numeric value is authoritative, and a translated label is optional presentation data.

```json
{
  "id": 125,
  "status": 2,
  "statusLabel": "مغلقة"
}
```

- Add `statusLabel` only in Resources whose consumers need a display label; do not force redundant label fields on every endpoint.
- Resolve fixed labels through Core/module-owned translation keys, **not** a hardcoded Arabic switch in every Resource or stored translated status text.
- A label change must **never** alter the underlying enum integer, permitted transitions, money calculation or filters.
- All defined enum cases relevant to a view must have safe, tested Arabic labels.
- The displayed value of an activity/group/cafe product or employee in a selection list is the **stored business name**, not an automatically translated name.

**Select Registry maintains the approved API shape:**

```json
{
  "success": true,
  "message": "",
  "data": [
    {
      "label": "activities",
      "options": [
        { "value": 1, "label": "Swimming Academy" },
        { "value": 2, "label": "رسم" }
      ]
    }
  ]
}
```

- The outer `label` (e.g., `activities`) is the **technical requested Select key**, not an Arabic category heading.
- `options[].value` is the approved stable ID or technical key, depending on the Select.
- `options[].label` is a **display value from its owning source**: stored name, DB `alias_name` for roles/permissions, or a localized/computed fixed system label. It is **not automatically Arabic** if the user entered English.
- The Select Registry remains **all-or-nothing for authorization**: an unauthorized key means `403` for the entire request; an unknown key means `422`. Localization must not weaken these rules.
- Do not translate Select Registry option values, numeric identifiers or underlying technical permission keys.

## 9. Roles and Permissions: DB `alias_name` Is the Display Source

**User-approved final decision:** For Spatie roles and permissions, the persisted field **`alias_name`** (exact database identifier) is what the user sees. This **overrides the earlier preliminary suggestion** to derive these labels from language-file keys and differs from the printing reference's `alias` naming.

| Spatie model/table | Technical identifier | Display source |
|---|---|---|
| `roles` | `roles.name` (e.g. `manager`, `reception`) | **`roles.alias_name`** (e.g. "المدير", "الاستقبال") |
| `permissions` | `permissions.name` (e.g. `sessions.close`) | **`permissions.alias_name`** (e.g. "إغلاق الجلسات") |

**Rules**

1. **`name` is the stable technical key** used in Spatie permission assignments/checks, policies and application logic. Never authorize or branch on `alias_name`.
2. **`alias_name` is human-visible display text** stored in the database; return it exactly as stored, with no `__()` or automatic language conversion.
3. Add/maintain `alias_name` on the **actual Spatie `roles` and `permissions` tables** when implementing the relevant schema changes, using reviewed app migrations. Do not edit already-run vendor migrations in production. Exact schema/backfill and any allowed editing rules belong to [Permissions Standards](permissions-standards.md).
4. Seeders for system-defined roles/permissions must provide meaningful **nonempty Arabic `alias_name`** values from the approved catalogue. Their technical names remain unchanged.
5. If the product later permits custom roles, their user-supplied `alias_name` must remain as entered; validate the display field as a data attribute, **not** a localization key. The allowed role-management behavior will be specified in Permissions Standards.
6. Do not create `role_translations`, `permission_translations`, `alias` instead of `alias_name`, per-locale JSON maps, or duplicate role/permission label translation keys in V1.
7. Changing `alias_name` is a **controlled database metadata change**, subject to the eventual permissions/admin validation and audit policy, not a language-file deployment.
8. Missing/blank `alias_name` is a **data integrity/seed/validation defect**, not a missing Laravel translation. Reject invalid new definitions and test seed completeness; do not silently substitute technical `name` as the expected Arabic display label.
9. Adding English `lang/en` later will **not** translate database `alias_name`. Supporting multilingual role/permission display labels requires a separate approved data/design decision that preserves technical keys and assignments.

**Illustrative Resource**

```json
{
  "name": "sessions.close",
  "aliasName": "إغلاق الجلسات"
}
```

- `aliasName` is the **camelCase API representation of DB `alias_name`**, when that field is included.
- For a permission Select Provider, use `options[].value = name` and `options[].label = alias_name`, matching the existing Select Registry contract, rather than adding a second name translation layer.
- Any endpoint/permission model that should expose the `aliasName` field requires normal backend authorization and explicit Resource mapping. This is **not** a requirement to expose permission metadata on every response.

## 10. API Response Contract Must Stay Intact

Localization does **not** redefine [API Standards](api-standards.md) or [Error Handling Standards](error-handling-standards.md).

- JSON keys for requests/responses/filters/sorts stay **`camelCase`**, never Arabic.
- Success response outer keys are `success`, `message` and `data`.
- **Every successful GET/login/logout** keeps `"message": ""`; do not look up a localization key just to populate those messages.
- Other successful mutations can have a localized nonempty Arabic message when useful, or an empty message if the feature permits.
- Failure outer keys are `success: false`, safe Arabic `message` and `errors`, **without `data`**.
- Validation errors retain `camelCase` keys with arrays of Arabic messages.
- Stable optional machine business codes are **English `UPPER_SNAKE_CASE`** under `errors.code` only; never translate them or introduce a top-level `code`.
- Status visibility (`403`/`404`), `409` business conflict, `500` internal error, `503` unavailability, `X-Request-ID` and Idempotency-Key recovery remain governed by the existing standards.
- Do **not** add automatic `locale`, `direction` or `requestId` fields to every JSON response.
- A successful HTTP `204` has no JSON body.

**GET example**

```json
{
  "success": true,
  "message": "",
  "data": {
    "items": []
  }
}
```

**Business error example**

```json
{
  "success": false,
  "message": "لا يمكن إغلاق الجلسة قبل إنهاء جميع المؤقتات.",
  "errors": {
    "code": "SESSION_TIMERS_NOT_FINISHED"
  }
}
```

## 11. Dates, Times, Durations and Monetary Formatting

**Approved: Presentation-Layer Formatting.** Machine values in the API remain locale-independent; UI formatting is separate.

| Data | Backend authoritative representation | Display owner |
|---|---|---|
| Actual event instant | ISO 8601 UTC/explicit offset, with UTC persistence | Frontend renders in appropriate location timezone |
| Calendar date | `YYYY-MM-DD` | Frontend renders desired locale date |
| Weekly recurrence | Structured weekday + local `HH:mm` + IANA timezone | Frontend; backend may compose approved Select display |
| Time duration | Integer seconds | Frontend displays hours/minutes; backend performs authoritative billing |
| Money | MySQL `DECIMAL(12,2)`, API exact decimal string like `"1250.50"` | Frontend displays amount/currency; backend calculates safely |
| Currency | Stable `EGP` technical value | UI/report may display currency label |
| IDs, enum values, permissions | Stable technical values | Never translated |

**Example (illustrative Resource fields, not an endpoint specification):**

```json
{
  "startedAt": "2026-11-23T10:00:00Z",
  "scheduledDate": "2026-11-24",
  "scheduledTime": "16:00",
  "timezone": "Africa/Cairo",
  "durationSeconds": 5400,
  "amount": "1250.50",
  "currency": "EGP"
}
```

- Never return Arabic digits in an authoritative money string, or formatted Arabic dates in authoritative API timestamp fields.
- Never localize record IDs, user phone identifiers, permission names, binary file MIME types, currency codes or machine error codes.
- Do not store a fixed UTC offset as the only description of an Egyptian weekly schedule; the location IANA timezone and daylight-saving rules remain controlled by Database Standards.
- The backend owns financial rounding/calculation and elapsed-time rules, **not** merely presentation formatting.
- The frontend may display digits as Arabic or Latin according to its UI design; it must not change the canonical posted amount or state.
- A backend-created Excel sheet should keep actual numeric monetary cells numeric, even if headings and visual formatting are Arabic.

## 12. Frontend RTL and Backend PDF/Excel Boundaries

**Approved: Frontend-Owned RTL + Backend Document Formatting.**

**Future frontend responsibilities**

- Own RTL screen direction, navigation layout, controls, tables, dialogs, text alignment and mixed Arabic/English presentation.
- Own static UI chrome translations: page titles, navigation, buttons, empty-state prompts and help text.
- Display backend `message`, enum `statusLabel` when present, database `aliasName` and permitted Select option labels as provided, without retranslating the underlying business values.
- Own normal UI date/time/amount formatting. Use appropriate isolation/bidi-safe presentation of English names/IDs inside Arabic layouts.
- Do not assume the backend emits a `direction` field or special bidi-control characters in stored names.
- Do not introduce React/Next.js-specific libraries or files in the backend-only V1 phase. The actual frontend framework and locale library will be decided when its implementation begins.
- Do not add an API endpoint solely to ship translations for static frontend buttons.

**Backend responsibilities for generated documents**

- Generate localized **PDF/Excel** and approved server-created reports synchronously, with Arabic headings and appropriate RTL fonts/flow/alignment as needed.
- Preserve stored user text without translation or destructive rewriting.
- Keep numeric calculations and editable Excel money cells as actual numeric data; visual formatting cannot change numeric truth.
- Keep dates/times and report timezone presentation explicit and consistent with the owning report spec; stored UTC timestamps remain untouched.
- Do not treat PDF rendering failure as evidence that an already committed financial payment or cashier session close failed. See [Error Handling Standards](error-handling-standards.md).

## 13. Missing Translation: Safe Fallback and Coverage Tests

**Approved: Safe Fallback + Translation Coverage Tests.**

Because both `APP_LOCALE` and `APP_FALLBACK_LOCALE` are `ar` in V1, Laravel's normal fallback locale alone **cannot repair a missing Arabic key**.

**Requirements**

1. When a system-generated message or fixed label is missing, resolve to a **safe generic Arabic fallback appropriate for the response context** instead of exposing `sessions::messages.errors.invalid_key`.
2. Preserve the **already determined HTTP status**, `errors.code` where documented, transaction outcome and business effect; translation failure is **not** a new payment or session-state transition.
3. Emit a **bounded, sanitized Warning** with the missing key, owning module and safe Request ID context when possible. No personal child/payroll/payment details; avoid flood logging for a frequently missing key.
4. Cover required Arabic keys and their dynamic placeholders through PHPUnit tests in Core and owning modules.
5. Avoid normalizing "missing translation" as acceptable release quality. Use fallback only as runtime resilience and **fix incomplete translation coverage** before deployment.
6. An intentionally user-supplied value, a stored `alias_name` or an English-entered activity name is **not a missing translation** and must not be replaced by a fallback.
7. A missing `alias_name` is instead a permission/role **data integrity** issue, covered by the relevant schema/seed/validation tests.
8. Future locale fallback rules must be explicitly approved before adding a second active language.

Example safe generic error: `تعذر إتمام العملية. يرجى التواصل مع المسؤول.` The specific Arabic fallback can vary by context; do not change the response envelope.

**Implementation note:** Don't assume raw `__()` automatically guarantees these safe fallbacks. Centralize or consistently encapsulate missing-key detection where needed and test the installed Laravel/module translation behavior; do not scatter duplicated fallback logic throughout Actions.

## 14. Localization Testing and CI Expectations

**Approved: Shared + Module Localization Tests** using PHPUnit, aligned with Code Standards and MySQL 8 wherever permissions/DB metadata are involved.

| Test | Required result |
|---|---|
| Locale configuration | `ar` default and `ar` fallback in V1; no unapproved runtime language switch |
| Shared Laravel keys | Authentication, validation and safe generic errors resolve to Arabic |
| Module translation loading | `nwidart` namespaces registered/loaded; correct owning message returned |
| Context-based keys | `errors` / `success` groups resolve consistently |
| Parameters/pluralization | Named placeholders are substituted; count-sensitive grammar tested when used |
| Missing translations | No key leak, safe Arabic fallback, sanitized bounded Warning, no status/business-result change |
| Validation | Human-readable Arabic attribute names, public `camelCase` error keys and agreed 422 envelope |
| Business exceptions | Correct Arabic error message and optional stable `errors.code` |
| Successful GET/login/logout | `message` is exactly `""` |
| Enum labels | Stable integer enum value; `statusLabel` appears only where Resource contract requests it |
| User-entered data | English/Arabic child, coach, activity, group and cafe product names remain **unchanged** |
| Computed labels | Structured weekday/local time yields human-readable label without persisting translated text |
| Roles | `roles.name` remains technical, `roles.alias_name` returned unchanged as `aliasName`/Select label |
| Permissions | `permissions.name` governs checks; `permissions.alias_name` returned unchanged as display; never authorize by alias |
| Seeder quality | System role/permission entries have nonempty approved Arabic `alias_name` |
| Select Registry | `label` technical requested key; `options[].label` from correct source, including DB aliases; permission checks unchanged |
| API machine values | UTC/ISO, `YYYY-MM-DD`, seconds, exact decimal money strings and status integer unchanged |
| Frontend contract | No universal `locale`/`direction` fields; untranslated business data safe to display |
| PDF/Excel integration | Arabic presentation when implemented; correct values and no irreversible-effect retries for render failures |
| Unauthorized requests | Arabic-safe responses follow 401/403/404 rules; no translation exposes protected values |

**Workflow**

1. On each feature, read this standard and the owning feature's `spec.md`/`tasks.md` plus API/Error Handling/Permissions Standards as relevant.
2. Add or update only the needed Core/module translation keys and associated Arabic strings.
3. Add focused PHPUnit tests for the new messages, alias display or fixed labels; use MySQL 8 for Spatie role/permission persistence tests.
4. Review for **raw translation keys**, translation of stored user data, accidental localization of technical permission names and duplicate translation sources.
5. Run relevant PHPUnit tests and `vendor/bin/pint --test` in local development/CI before claiming checks pass. GitHub Actions on PRs targeting `develop` must be configured and actually run; publishing this document is **not evidence** the checks exist or passed.
6. Changes to role/permission `alias_name` migration, seeders, validation and audit rules must agree with the future Permissions Standards.

### Definition of Done

- Every system-owned user-facing message/label required by a feature has a stable, resolvable Arabic translation or documented safe fallback.
- Relevant Context-Based module keys load properly, without a new localization framework or duplicate ownership.
- The API preserves `camelCase`, its success/error envelopes, unchanged machine values, empty GET/login/logout messages and optional business error-code location.
- Stored user content and Spatie `alias_name` display values are returned **as stored**, with stable technical `name` identifiers.
- Fixed integer enum statuses remain unchanged; `statusLabel` appears only where needed.
- Scoped permissions, selective logging, Idempotency, atomic finance and published history are unaffected by translations.
- Applicable PHP/MySQL8/Pint tests have been **actually executed** and passed before a verified implementation claim.

## 15. Alignment Notes, Future Changes and Non-Goals

**Source-of-truth boundaries**

| File | Responsibility |
|---|---|
| [Project Overview](../00-overview/project-overview.md) | Business concepts and V1 scope |
| [Backend Architecture](../01-architecture/backend-architecture.md) | Module ownership, hosting, sync-only operations, locale baseline |
| [Code Standards](code-standards.md) | Laravel/Action structure, naming, shared Core, PHPUnit and Pint |
| [API Standards](api-standards.md) | Public envelope, `camelCase`, status/Select shapes, idempotency and export transport |
| [Database Standards](database-standards.md) | Stored technical values, UTC, fixed money, data/transaction integrity |
| [Error Handling Standards](error-handling-standards.md) | Safe error rendering/logging, missing-data protection, 500/503, recovery |
| **This document** | Message translation ownership, labels, validation, RTL/formatting and translation tests |
| [Permissions Standards](permissions-standards.md) (forthcoming) | Spatie `roles.name` / `permissions.name`, `alias_name` schema, authorization, seed/update/audit permissions |
| Owning feature specs | Exact business messages, status cases, selector labels and acceptance tests |

**Final alias decision supersedes an earlier discussion:** The role/permission label source is **database `alias_name`**, **not** Laravel translation keys. The Tabaa reference used a different field (`alias`); Kids Area's approved field is exactly **`alias_name`**. This doc does not add `alias_name` to the database by itself; implementation requires a reviewed Permissions migration/seeders and coverage.

**API wording clarification to synchronize later:** Existing API examples call `options[].label` an "Arabic option name". A **user-entered** English name or stored `alias_name` must instead be displayed **as stored**; the outer `label` remains the English technical requested Select key. This decision should be reflected in the API documentation through a separately reviewed change; no API shape is changed here.

**Do not introduce without separate approval:**

- Database translation tables/columns for ordinary business data, automatic text translation or localized slugs.
- `alias` instead of `alias_name`, permission-label translation files as a second source of truth, or authorization based on localized text.
- Runtime language choice or `Accept-Language` middleware in V1.
- React/Next.js localization dependencies in this backend-only phase.
- Queue-based localized notifications/reports, Redis, scheduler work, or external translation services.
- Changes to error response shape, fixed enum code, money/UTC semantics or finance posting based on locale.

**Implementation status:** This file documents **approved standards**, including the future `alias_name` field requirement. It does not claim Laravel translation files, Spatie migrations/columns, namespace registration, frontend UI, tests or GitHub Actions have already been implemented.
