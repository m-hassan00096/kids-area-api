# People — Module Reference

> Status: Customer F01 scope, three routes/capabilities, minimal data and integer-backed CustomerStatus approved on 2026-11-28. F01 feature specification including R01–R04 approved on 2026-11-28. Technical plan and tasks remain separate review gates. F02 Children & Guardians decisions remain intact; their exact contracts remain subject to separate approvals. No People code, migration, grant or API implemented.
> Owner: People module; one physical location, staff-only V1. Canonical reference: docs/03-modules/people.md.

## 1. Purpose and boundaries

Keep reception registration and selection simple while preserving stable person IDs, integrity and history. The module owns Customers, Guardians, Children and the current child-to-guardian relationship. Customers are independent from Guardians/Children even when the same human exists in both records: no relation, shared identity, automatic matching, merge or cross-table uniqueness. A child has exactly one guardian; one guardian may have zero or many children. The child is the primary UI entry point; there is no standalone Guardian screen, standalone Guardian create or delete workflow, account login, child membership code, family account, branch scope, financial wallet or separate medical record.

| Owner | Contract boundary |
|---|---|
| People | Current personal/contact data, eligibility flags, registration, relationship, photo and notes |
| Sessions | Hourly visit under a written customer name or selected registered customer, timers and settlement; no mandatory child or permanent customer |
| Activities | Subscription, group/class participation, attendance, eligibility and makeup; uses a registered child/guardian |
| POS / Finance | Authoritative invoice, collection/refund, payer/agreement evidence and balances |
| Reports | Authorized projections of actual owning-module records, not parallel People ledgers |
| Identity / Core | Staff/session authority, technical permission catalogue, Select Registry and shared response/error contracts |

Subscription pricing, expiry, enrollment, attendance and money are not implemented by People. Nursery uses ordinary subscription-only Activities. Existing entitlements and financial obligations must remain traceable despite later changes to People. Do not create placeholder tables for future modules.

## 2. Minimal data

Required fields below are business inputs; IDs, activation defaults and technical timestamps are server managed according to feature contracts.

| Entity | Required | Optional | Lifecycle |
|---|---|---|---|
| Customer | name | mobile | is_active = CustomerStatus::ACTIVE (1) |
| Guardian | name, mobile | address | is_active default true |
| Child | name, guardian, gender | birth date, relationship to guardian, photo, notes | is_active default true |

Exactly one general `notes` field on Child, including any necessary health information. No Guardian notes, extra phone, email or national ID; no Customer address or notes. Age is computed only if a birth date exists, never persisted; absent date means age unspecified. Do not infer birth date or age. No artificial required photo, relationship or notes. Gender/relationship values, remaining Child/Guardian length limits, date validation, optional null semantics and file contract belong to F02 spec/plan.

F01 owns one `customers` table: unsigned bigint auto-increment `id`, required `name` VARCHAR(255), nullable unique `mobile` VARCHAR(11), `is_active` unsigned TINYINT with default 1, normal `created_at` / `updated_at` timestamps. No `deleted_at` or additional business columns. `CustomerStatus: int` defines INACTIVE = 0 and ACTIVE = 1, with Eloquent enum casting and no magic status numbers in code. This is an explicit feature-level exception to the general boolean flag convention. Keep fixed-value integer-backed enums in the owning People module whenever needed; avoid enums for dynamic user-managed values. F02's proposed `guardians`, `children`, unsigned bigint IDs, required `children.guardian_id` with restrictive FK and normal timestamps are unchanged; exact status and other enum contracts are reviewed with F02. Minimal child columns: name, guardian_id, gender, birth_date (nullable), relationship (nullable), photo_path (nullable private reference), notes (nullable). No guardian/customer pivot, copied guardian columns on Child, stored age or extra domain table. Exact DDL, named constraints, collation, indexes, remaining F02 enum codes and technical permission-provision requirements need feature plan review; this is not migration approval.

## 3. Identity and duplicates

- Customer name and Guardian name may repeat; non-null Customer mobile unique within Customers, required Guardian mobile unique within Guardians. The same mobile can appear in both tables, or in Identity staff records; no cross-entity lookup/collision check.
- Use a string preserving leading zero; discovery convention follows Identity's exactly 11 ASCII digits, no E.164 conversion, prefix inference or punctuation. Approved F01: trim name/mobile edges; name nonblank and max 255 characters; absent/null/blank mobile becomes NULL on POST and full PUT; numeric JSON and non-ASCII digits rejected. Same customer's unchanged mobile accepted. Known duplicate mobile is 409 CUSTOMER_MOBILE_ALREADY_EXISTS; other invalid fields are 422. F02 retains its separate validation review.
- Multiple customers with absent mobile are permitted. Uniqueness includes inactive retained records; never duplicate or implicitly reactivate an existing record. Customer reactivation is deferred. Real MySQL unique constraints are required.
- Child name is unique under the same Guardian, including inactive Children, by `UNIQUE(guardian_id, name)`. The same name under different Guardians is allowed. Date of birth is neither required nor part of uniqueness. Exact name equality/collation and trim semantics must be reviewed with real MySQL 8 Arabic/English cases; do not implicitly inherit a different feature's collation choice.
- Duplicate registration or rename is rejected safely even under concurrent requests. No silent deduplication, auto-link by phone, overwriting an existing Guardian, or automatic merge.

## 4. Customer workflow

Customers are a supporting entity, not a standalone management screen. Manager and Reception may view a specific Customer, add a Customer, and update a Customer according to their granted permissions. No Customer index, table screen, delete, activation/deactivation or dedicated status permission in F01. Within a future authorized session/cashier workflow, staff may select an existing active Customer through the shared Select Registry, or enter the required name and optional mobile in an inline/modal registration form. Successful explicit creation returns the new record so it can be selected immediately; edits are available in the same host workflow when authorized, and the selector is refreshed after writes. For future hourly Sessions, a written name without a permanent record remains valid. Never create a Customer or Child implicitly from a typed session name. Cafe/POS standalone operation follows its own spec. Retain historical session/invoice evidence; inactive Customers are not offered for new customer selection. Do not infer a financial balance or a required permanent customer from a receipt. No Sessions, Cafe, POS or frontend implementation in F01.

## 5. Child and Guardian workflow

1. Open Children table; search by Child name, Guardian name/mobile, filter by child active status/gender/Guardian; open Add Child or Edit Child.
2. Enter Child name and gender, optional birth date/relationship/photo/notes. Choose an existing Guardian from a single searchable Select, whose option label contains only Guardian name and mobile, or enter a new Guardian's name/mobile/optional address inside the same modal. No independent Guardian administration page.
3. Existing selection uses stable guardian ID; creation uses new Guardian input; mutually exclusive. Both new Guardian and Child are persisted in one transaction; any validation/duplicate/storage/domain failure follows the reviewed atomicity and cleanup contract. Never leave a new Guardian as a side effect of failed registration. A pre-existing Guardian is not deleted or overwritten when Child creation fails.
4. After saving, refresh affected list and selects. For edit, show current Guardian even if now inactive (through authorized detail); only active Guardians are eligible for a new association. Ordinary Child edit can preserve the existing inactive Guardian; no new link to an inactive one.
5. From Child detail, show authorized current Guardian contact fields and allow authorized shared Guardian update. The confirmation should explain that changing shared details affects all linked children. It is not equivalent to changing the Child's guardian. Do not mutate shared Guardian fields through an ordinary child-update capability.
6. Changing Guardian requires its separately authorized capability and explicit target (existing active Guardian or new one created within the same transaction). Reject conflicts under destination Guardian, retain prior relationship/payer evidence where required by owning features. Do not rewrite old agreements/invoices or cancel active rights. Exact transfer route and correction/history mechanism require feature-level review. No default generic relationship-history table or audit subsystem.

## 6. Status and deletion

- Child and Guardian status independent; no automatic cascade of state or deletion.
- Inactive Child or Guardian blocks new subscriptions and renewals for affected Children; inactive Guardian also blocks new child registration or reassociation to that Guardian. Existing authorized attendance/makeup, agreements and debt collections remain available according to Activities/POS rules. Status does not automatically terminate subscriptions, clear balances or change active flags of linked records.
- Customer F01 stores INACTIVE = 0 / ACTIVE = 1 internally; all new Customers are ACTIVE. Inactive Customer is excluded from new selection; preserve previous history. Customer deletion and status-change/reactivation are deferred: no route, permission, UI or Action for either, and ordinary writes cannot accept isActive/is_active or status. Revisit downstream eligibility at Sessions/POS feature review.
- Authorized conditional hard delete of unused Child only (F02); all actual active/historical business references protected by real FK RESTRICT and safe domain conflict mapping. Add downstream references when their actual owning feature exists. Customer deletion is not part of F01. Inactive status is not itself proof that deletion is safe.
- No standalone Guardian delete in V1. Keep Guardian when last Child is deleted or reassociated; the active retained Guardian can be selected again. No garbage collection or cascading delete.
- Use is_active, not automatic SoftDeletes. Data-retention/purge/anonymization is separate protected approval, not ordinary delete. Audit is deferred under project-wide rules; do not claim audit persistence exists.

## 7. Authorization and privacy

Explicit capabilities, current active staff/role/session, no role-name bypass; custom roles supported. Default access matrix agreed in discovery:

| Capability | Manager | Reception |
|---|---|---|
| Customer detail/create/update | yes | yes |
| Customer index / status change / delete | not in F01 | not in F01 |
| Child list/detail/create/update | yes | yes |
| Child status change / delete / guardian change | yes | no |
| Shared Guardian ordinary update from child workflow | yes | yes |
| Guardian status change | yes | no |

F01 approved Customer capability names: `people.customers.show`, `people.customers.store`, `people.customers.update`; both initial roles receive these three by default through reviewed Identity provisioning, with custom role configuration supported. No index/delete/change_status grant. Remaining F02 candidate names (review in F02): people.children.index/show/store/update/delete/change_status/change_guardian; people.guardians.update/change_status. Use lowercase English dot-notation per Permission Standards. These nine F02 names are candidates, not registered grants; do not provision them in F01. New Guardian creation within child create/change-guardian is covered by that command's permission. Ordinary update must not bypass separately controlled status/reassociation, including nested or unexpected inputs. Provider permission/visibility by purpose must be specified independently; assignment-only access does not imply unrestricted detail read. The F01 customers provider requires people.customers.show; do not infer other module permissions or add a separate Customer index permission. Extend current central catalogue/one-time grant provisioning only as needed by each approved feature; six essential Identity capabilities unchanged, routine seeds never regrant revoked rights.

Return only authorized minimal projections. Guardian Select label name and mobile only, no address or child/notes/photo data; no unrestricted Guardian list endpoint. Notes only in authorized Child detail, not ordinary list or selectors; health content is sensitive. Child photos private storage, never public URL, never DB BLOB; controlled authorized retrieval and mutation, validate MIME/size/file bytes and cleanup on failure. Review existing Identity avatar pattern without granting Identity privileges or copying unnecessary infrastructure. Use synthetic fixtures, do not log names, mobiles, notes, paths/secrets or raw request payloads. Do not access real children/guardian records without separate authorization.

## 8. API and UI contract

All APIs follow current standards: /api/v1, camelCase public fields, snake_case database, Arabic messages, explicit projections/allowlists, stateful staff session/CSRF, exact capability checks, paginated ordinary list data.items/data.pagination (15 default, max 100), literal bounded name/mobile search, stable id/name sorting and default newest ID first. Customer F01 has no normal list/index. Detail/create projections use exactly id, name, mobile; ordinary update returns data: []. Authorized detail/update may access a retained inactive Customer by ID without reactivation. Selection remains active-only. Child list minimal child identity, status, gender and relevant guardian name/mobile, no notes/private photo contents. Birth date/age display and photo thumbnail access exact projections in spec. Customer select local search name/mobile, no server-side search/status filter. Child search child name/guardian name/guardian mobile, filters status/gender/guardian ID. No extra dashboard counts, search infrastructure, cache or reports without need.

Approved F01 routes (all protected): GET /api/v1/customers/{id} with people.customers.show; POST /api/v1/customers with people.customers.store; PUT /api/v1/customers/{id} with people.customers.update. POST and PUT accept name and nullable mobile only; the public name is mobile, never phone. No Customer index/PATCH/DELETE/status route. F02 candidate endpoints (not final technical contracts): five CRUD routes for children at their plural path, shared Guardian update at /api/v1/guardians/{id} used from Child UI. Separate permission-sensitive commands for status, guardian change, private photo; finalize exact routes, methods, payloads, errors, required permission conjunctions and target admission in feature specs. In add Child POST, choose precisely one guardianId or guardian object; new Guardian fields name/mobile/address. No duplicate guardian id plus object, no silent unknown fields. Validate current relationship/eligibility at action boundary.

Use the approved Core Select Registry simplification before People: /api/v1/selects?selects=guardians,customers; one to five registered keys, all authorized eligible options, no per-provider limit or pagination/server search, exact existing array of label/options with value/label only, frontend local search. F01 registers only customers: numeric customer ID as value, name alone when mobile absent, "Name - Mobile" otherwise, active only, deterministic ordering name ASC then id ASC, local name/mobile filtering. F02 candidate keys guardians, children, genders, guardianRelationships; register only those justified by its reviewed feature. Guardian option value numeric ID, label "Name - Mobile", active eligible only for new assignment, deterministic ordering. Inactive current selection display comes from authorized detail, not an inactive-options bypass. Refresh after changes and recheck at save. No query params for search/pagination, no meta, no independent guardian dropdown route. If future data grows, measured Adaptive Select is a separately approved change, not V1.

## 9. Consistency and performance

Use actual unique/FK constraints and transactions, fresh authorization and state under documented lock ordering, no blind replay after uncertain commit. Verify F01 duplicate mobile on creation/update including simultaneous requests, multiple null mobiles, active-only select, fresh permission enforcement and no status injection. F02 must verify child create vs guardian duplicate/create/deactivation, same child name create/rename/transfer, shared guardian update, child delete vs real downstream reference and actor permission revocation. Do not invent fake subscription/session tables. Concurrent ordinary edit conflict policy and photo/database crash cleanup require technical plan approval; do not assume filesystem rollback with SQL. Safe Arabic business conflict errors, no raw SQL/private data, no blanket exception-to-duplicate mapping. Separate status and relation permissions even if HTTP route design differs.

Select complete minimal option columns; one domain query per data-backed provider where practical, no N+1, no unnecessary counts. Ordinary lists use bounded queries, selected columns and stable pagination. Measure actual synthetic 200/500/2,000/10,000 appropriate rows, explain query plans, memory, response bytes, query counts and lock waits; no implied production load or SLA. No speculative indexes: exact mobile unique lookup, composite guardian/name, FK and other list query indexes justified by actual query shape. Keep risk of unbounded-by-row-count option results visible; no hidden truncation.

## 10. Acceptance scenarios and delivery gates

- F01: explicit Customer create/detail/update only; duplicate names and multiple null mobiles accepted; duplicate non-null mobile rejected including inactive/concurrency; unchanged own mobile on update accepted. Active default, enum casting, no status mutation, no index/delete/status route or grant. Active-only selector returns full authorized options and supports local name/mobile search; no separate search endpoint. Customer/Guardian independent; same mobile across them is allowed when F02 exists.
- Register Child with existing Guardian and with new Guardian atomically; required name/gender/Guardian; optional date/relationship/photo/one notes; duplicate under same Guardian rejected even inactive, same name other Guardian accepted.
- Shared Guardian edit across siblings; separate guarded change-guardian/status commands; duplicate destination or inactive destination fails without partial writes.
- Child/Guardian status independent, no new registrations/enrollments when ineligible; preserve existing obligations, no automatic cancellations. Future downstream enforcement tested by owning features. F02 unused Child conditional deletion, retain last child's Guardian; actual linked history protected when dependencies exist. Customer deletion deferred.
- Minimal authorized list/detail/Select fields; stable paging/search; full authorized options and no partial unauthorized selects; private photo and notes restrictions; no unauthorized reads/writes or hidden guardian creation.
- Actual MySQL 8 schema/constraints, concurrency and transaction failure tests, native HTTP, scope regression, Pint and CI. No tests claimed for unimplemented People.

Delivery order: merged Activity Catalogue PR #15; merged Core Select Registry Simplification PR #17; People F01 Customers Management (see [feature spec](../../specs/people/customers-management/spec.md)); People F02 Children & Guardians Management; Employees Core; Groups/Schedules/Class Generation. Each feature separately reviews spec.md, plan.md, tasks.md and has its own implementation branch/PR, explicit merge approval. No production operations. The exact routes, keys, enums, column sizes/collations, privacy/file implementation, provision markers, concurrency/error/measurement plans are feature-level review gates. 
