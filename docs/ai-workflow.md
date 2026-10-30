# AI Agent Workflow

This document describes how an AI coding agent was used to build the Warehouse Receiving System, what the agent received as input, how its output was reviewed, and what was corrected.

> **How to use this file.** Sections 1 to 6 and the prompt texts in Section 7 are the plan. Every block marked **`TODO`** is filled in **during** development, right after each task, with what actually happened. Do not fill them in from memory at the end, and do not write results that did not happen. Remove this note before submission.

---

## 1. Approach

The project is **specification-driven**. The agent does not receive a large one-off prompt describing the application. Instead:

1. The requirements were defined first by the developer, as documents: PRD, technical specification, database design, ERD, OpenAPI contract, and data flow diagram.
2. The documents live in the repository (`docs/`). They are the primary input for the agent.
3. Permanent agent instructions tell it which documents to read, which rules are non-negotiable, and how to work.
4. Work is split into small tasks (T0 to T10, defined in `technical-spec.md` section 16). Each prompt is short, names the task, and points to the exact document sections to follow.
5. The developer reviews every change, runs the tests, corrects the agent, and commits.

The goal is not to produce code quickly but to show that requirements, structure, and verification, not typing, determine the quality of the result.

## 2. Tooling

| Item | Value |
|---|---|
| Agent | GitHub Copilot, Agent mode, in Visual Studio Code |
| Model(s) used | `TODO` (record the model selected in the Copilot chat model picker for each task) |
| VS Code version | `TODO` |
| Copilot extension version | `TODO` |
| Other tools | Docker Compose, Git, GitHub |

## 3. Inputs given to the agent

### 3.1 Specification documents

| File | Role for the agent |
|---|---|
| `docs/prd.md` | What the system must do: roles, requirements, user stories, business rules, validation, scope |
| `docs/technical-spec.md` | How it is built: architecture, code structure, authorization, API flow, state machine, deployment, testing, **task plan** |
| `docs/database-design.md`, `docs/erd.mmd` | Schema, constraints, locking, migrations, integrity queries |
| `docs/openapi.yaml` | HTTP contract: endpoints, schemas, error codes |
| `docs/data-flow.mmd` | Data flow overview |

### 3.2 Project instruction files

| File | Applies to | Purpose |
|---|---|---|
| `.github/copilot-instructions.md` | Every chat request | Read-first documents, stack, non-negotiable business rules, way of working (one task at a time, plan first, never weaken tests, never commit, honest reporting) |
| `.github/instructions/backend.instructions.md` | `backend/**` | Laravel layering, error handling, database and locking rules, backend testing rules |
| `.github/instructions/frontend.instructions.md` | `frontend/**` | Next.js structure, API client, idempotency key behavior, role-aware UI, frontend testing |
| `.github/instructions/docs.instructions.md` | `docs/**` | The agent must not edit the specification without approval and must not invent evidence |

These files were written by the developer before any code was generated. Changes made to them during the project are recorded in Section 9.

## 4. Division of responsibility

| Done by the developer | Done by the agent (reviewed by the developer) |
|---|---|
| Interpreting the unclear business scenario and choosing assumptions (PRD section 9) | Generating code for the agreed tasks |
| Choosing the document lifecycle, roles, permission matrix, error codes | Writing migrations, models, services, controllers, Form Requests |
| Designing the schema, constraints, lock order, and idempotency approach | Writing tests for the rules listed in the specification |
| Writing the specification and the task plan | Writing the Next.js pages, components, and API hooks |
| Reviewing each diff, running tests, rejecting or correcting output | Writing Dockerfiles and Compose configuration |
| Deciding when a specification change is needed | Proposing fixes when tests fail |
| Committing with meaningful messages | Summarizing what it did and what it could not verify |

## 5. Working loop (applied to every task)

1. **Choose the task** from the plan (Section 6) and open a clean Copilot Agent chat.
2. **Prompt** with the task text from Section 7, attaching the referenced documents.
3. **Plan check.** The agent is asked to give a plan, assumptions, and open questions first. The developer answers or corrects before any code is written.
4. **Generation.** The agent implements the task.
5. **Review the diff** with the checklist in Section 8. Reject out-of-scope code and unexplained dependencies.
6. **Verify.** Run the tests, linter, and type checks myself. Do not rely on the agent's claim.
7. **Correct.** Ask for fixes with a targeted follow-up prompt, or fix by hand when faster. Record every correction.
8. **Commit** with a meaningful message. One task usually means several small commits.
9. **Log** the result in the task's log block (Section 7).

## 6. Task plan and status

Tasks are defined in `technical-spec.md` section 16.

| Task | Scope | Status | Commit(s) |
|---|---|---|---|
| T0 | Skeleton, Docker, health endpoint | `done` | `287774d` |
| T1 | Database migrations, models, enums | `done` | `4b1f12b` |
| T2 | Auth, role middleware, error handling | `done` | `5ccb6e8` |
| T3 | Products | `done` | `1095909` |
| T4 | Receiving documents (create, list, detail, edit) | `done` | `7f68654` |
| T5 | Document lifecycle (confirm, complete, cancel) | `done` | `a78bf36` |
| T6 | Receiving, idempotency, inventory endpoints | `done` | |
| T7 | Backend hardening: concurrency tests, demo script | `TODO` | |
| T8 | Frontend foundation | `TODO` | |
| T9 | Frontend features | `TODO` | |
| T10 | Quality and delivery | `TODO` | |
| R1 | Adversarial review of the receiving operation | `TODO` | |
| R2 | Requirement change exercise | `TODO` | |

Suggested status values: `not started`, `in progress`, `done`, `done with corrections`.

---

## 7. Prompts and task logs

Each prompt below is written to be pasted into a new Copilot Agent chat. Attach the files named under **Read first** using `#file:` references or drag and drop. The prompts are short on purpose: the detail lives in the documents.

Every task block ends with a log table to fill in.

### T0. Project skeleton

**Prompt**

```text
Task T0: project skeleton.

Read first: .github/copilot-instructions.md, docs/technical-spec.md (sections 1.2, 3, 5.6, 14),
docs/openapi.yaml (only the /health operation).

Goal: create the empty but runnable skeleton of the system.
- backend/: new Laravel 13 project (PHP 8.5.12) for PostgreSQL (pgsql), timezone UTC, Sanctum installed,
  PHPUnit (not Pest), API only (no sessions or cookies). Implement GET /api/v1/health exactly as in openapi.yaml
  (SELECT 1; 200 {"status":"ok","database":"ok"} or 503 {"status":"error","database":"unreachable"}).
- frontend/: new Next.js project (latest stable, App Router, JAVASCRIPT only, Tailwind, ESLint, src dir,
  alias @/*) running on Node.js 24.22.1 (.nvmrc). Initialize shadcn/ui with the preset I give you
  (Luma, Mist, Emerald, Outfit, Lucide, default radius): run `npx shadcn@latest init --preset b2D2iPGaG --template next`
  with PRESET_CODE = <paste the code from ui.shadcn.com/create>. Confirm components.json has "tsx": false and
  "iconLibrary": "lucide", Outfit is applied to body and headings, and components/ui contains .jsx files.
  Add the Button component as a smoke test. Install zod, react-hook-form, @hookform/resolvers, @tanstack/react-query.
  Add a placeholder home page built only from shadcn components.
- backend/Dockerfile (multi-stage, php:8.5.12-apache, pdo_pgsql) and backend/docker/entrypoint.sh
  following section 14.1; frontend/Dockerfile (node:24.22.1-alpine, standalone output,
  NEXT_PUBLIC_API_URL as build arg). If an image tag does not exist, tell me and use the closest patch version.
- docker-compose.yml with db, backend, frontend, healthchecks, and depends_on service_healthy.
- .env.example with every variable from section 14.2 (placeholders only) and a proper .gitignore.

Constraints: no business tables or endpoints yet, no secrets, pinned image tags, no extra dependencies,
no TypeScript files. Your built-in knowledge may predate Laravel 13 and the current shadcn CLI: read the
official docs when unsure.

Before coding: give a short plan and list your assumptions.
After: summarize the files you created and anything you could not verify.
Verify: docker compose up --build gives three healthy services and /api/v1/health returns 200.
```

**Log**

| Field | Entry |
|---|---|
| Date / model | 2026-10-30 / Copilot SDK in VS Code (model not surfaced) |
| What the agent produced | Laravel 13 API-only foundation with Sanctum, PostgreSQL health route and PHPUnit tests; Next.js JavaScript/shadcn Radix foundation; Dockerfiles, Compose, environment template, README, and runtime configuration. |
| Problems found in review | Host PHP lacked extensions needed by Composer/PHPUnit; the initial frontend package install ran at the repository root; Laravel defaults added web/Vite scaffolding, sample migrations/seeding, and SQLite setup outside the API-only T0 scope; container checks exposed shell line-ending, frontend bind-address, and dotenv warnings. |
| Corrections made | Removed the accidental root npm artifacts and installed the specified packages in `frontend/`; installed Sanctum with the available PHP extensions; removed generated web/Vite/sample database setup; corrected container scripts, healthchecks, and test environment; pinned Composer. |
| Verification run | `docker compose up --build -d`: all three services healthy; API health returned `200 {"status":"ok","database":"ok"}` and frontend returned 200; only `migrations` and Sanctum's `personal_access_tokens` tables were created; `docker compose exec backend php artisan test`: 4 passed, 9 assertions; Pint: 27 files passed; frontend `npm run lint` and `npm run build` passed; production `npm audit --omit=dev`: 0 advisories. |
| Commit(s) | `287774d` (committed by the developer). |

### T1. Database migrations, models, enums

**Prompt**

```text
Task T1: database layer.

Read first: .github/copilot-instructions.md, docs/database-design.md (sections 2, 4, 8), docs/erd.mmd,
docs/technical-spec.md (sections 3 and 4.3).

Goal: implement the schema exactly as specified.
- One migration per table in the order of database-design.md section 8.2 (11 migrations including
  the append-only triggers). Use DB::statement() for CHECK constraints, expression indexes, and
  triggers. Constraint and index names must match the document exactly. Working down() methods.
  Remove the unused default Laravel tables (password_reset_tokens, sessions).
- Eloquent models with $fillable and relationships, and the enums Role, DocumentStatus,
  TransactionType, ErrorCode (error codes from openapi.yaml).
- Do not write services, controllers, or routes.

Tests (PHPUnit, PostgreSQL): for each CHECK, unique, and foreign key constraint, insert violating data
directly and assert the database rejects it; assert UPDATE and DELETE on receipts and
inventory_transactions raise the append-only exception; assert migrate:fresh and rollback both work.

Before coding: give a plan and list assumptions. After: list the constraints you tested and
anything in the document you found ambiguous. Do not change docs/.
```

**Log**

| Field | Entry |
|---|---|
| Date / model | 2026-10-30 / Copilot SDK in VS Code (model not surfaced) |
| What the agent produced | Ten new migrations (the nine business tables plus the append-only trigger migration) and database protection tests; Eloquent models and relations for the database entities; `Role`, `DocumentStatus`, and `TransactionType` enums; updated `User` model and factory. |
| Problems found in review | Eloquent inferred `inventories` instead of the specified `inventory` table. PostgreSQL functions survive Laravel `migrate:fresh`, so a plain `CREATE FUNCTION` prevented a repeat clean migration. |
| Corrections made | Set the explicit `inventory` model table name; made the append-only trigger function creation replace-safe; corrected the user factory for the specified `users` columns. |
| Verification run | PostgreSQL `migrate:fresh --force` passed; `migrate:rollback --step=11` and `migrate --force` both passed; backend suite: 18 tests, 102 assertions; Pint: 49 files passed. Database tests cover all defined check, unique, and foreign-key constraints, append-only updates/deletes, and restricted product deletion. Compose services remained healthy. |
| Commit(s) | `4b1f12b` (committed by the developer). |

### T2. Authentication, authorization, and error handling

**Prompt**

```text
Task T2: auth, role middleware, and the global error handler.

Read first: .github/copilot-instructions.md, docs/technical-spec.md (sections 4.1, 4.4, 4.5, 8.5, 12),
docs/prd.md (US-01, section 8.5), docs/openapi.yaml (Auth operations and the Error schema).

Goal:
- POST /auth/login, POST /auth/logout, GET /auth/me with Sanctum tokens, expiry from
  SANCTUM_TOKEN_EXPIRATION_MINUTES. Wrong email and wrong password return the same 401.
- EnsureRole middleware (role:SUPERVISOR, role:OPERATOR,SUPERVISOR) returning 403 FORBIDDEN.
- ApiException and the global handler so every /api/* error uses the standard JSON shape and the
  code mapping in section 4.4 (including constraint-name mapping). Treat all /api/* requests as JSON.
- UserSeeder reading credentials from environment variables; idempotent.
- No implicit route-model binding anywhere.

Tests: login success and failure, logout revokes the token, 401 without token, 403 for wrong role,
validation errors use VALIDATION_ERROR with details.fields, unknown route gives 404 NOT_FOUND in the
standard shape, an unexpected exception gives 500 INTERNAL_ERROR without a stack trace.

Before coding: plan and assumptions. After: summary with requirement IDs covered.
```

| Field | Entry |
|---|---|
| Date / model | 2026-10-30 / Copilot SDK in VS Code (model not surfaced) |
| What the agent produced | Sanctum login, logout, and current-user endpoints; case-insensitive login; role middleware; API error rendering and database constraint mapping; environment-configured, idempotent user seeder; PostgreSQL feature tests. |
| Problems found in review | The first test run used the pre-T2 container image. After rebuilding, seeded demo users also conflicted with fixed emails in test fixtures; the database-exception test used the wrong constructor signature, and the logout test needed to clear Laravel's cached test guard before checking the revoked token. |
| Corrections made | Rebuilt the backend from the workspace; changed test fixtures to isolated email addresses, corrected the test exception construction, reset guards between logout requests, and formatted the PHP files with Pint. |
| Verification run | `docker compose exec -T -e LOG_CHANNEL=null backend php artisan test`: 32 tests passed, 163 assertions; Pint: 61 files passed; `docker compose ps`: db, backend, and frontend healthy; `GET /api/v1/health`: 200 `{"status":"ok","database":"ok"}`; auth routes verified with `route:list`. |
| Commit(s) | `5ccb6e8` (committed by the developer). |

### T3. Products

**Prompt**

```text
Task T3: products.

Read first: .github/copilot-instructions.md, docs/prd.md (US-02, US-03, section 8.2 Product),
docs/technical-spec.md (sections 4.2 ProductService, 8.3), docs/openapi.yaml (Products).

Goal: GET /products (pagination, search), GET /products/{productId}, POST /products (Supervisor only).
- SKU is trimmed and uppercased before validation. Duplicate SKU in any letter case returns 422
  SKU_ALREADY_EXISTS, including when two requests race (map the unique constraint).
- Creating a product creates its inventory row with stock 0 in the same transaction (FR-PROD-5).
- Follow the layering: Form Request, thin controller, ProductService, API Resource.

Tests (name them after requirement IDs): normalization, duplicate in different case, inventory row
created, invalid SKU and name cases, Operator gets 403 (and 403 wins over 422), search, pagination limits.
```

| Field | Entry |
|---|---|
| Date / model | 2026-10-30 / Copilot SDK in VS Code (model not surfaced) |
| What the agent produced | Authenticated product list, search and detail endpoints; Supervisor-only product creation; request normalization and validation; transactional product plus zero-stock inventory creation; duplicate-SKU handling including unique-index races; PostgreSQL feature tests. |
| Problems found in review | The first search-pagination test fixture expected name-matched results before SKU-matched results, contrary to the specified ascending SKU order. |
| Corrections made | Adjusted the fixture ordering and verified page contents against ascending SKU order; added a service-level test for mapping a unique-index race to `SKU_ALREADY_EXISTS`. |
| Verification run | `docker compose exec -T -e LOG_CHANNEL=null backend php artisan test`: 40 tests passed, 222 assertions; Pint: 68 files passed; `docker compose ps`: db, backend, and frontend healthy; `GET /api/v1/health`: 200 `{"status":"ok","database":"ok"}`; product routes verified with `route:list`; `git diff --check` clean. |
| Commit(s) | `1095909` (committed by the developer). |

### T4. Receiving documents

**Prompt**

```text
Task T4: receiving documents (create, list, detail, edit draft).

Read first: .github/copilot-instructions.md, docs/prd.md (US-04 to US-07, section 8.2),
docs/technical-spec.md (sections 4.2, 10.4), docs/database-design.md (section 6.4),
docs/openapi.yaml (Receiving Documents: list, create, get, update).

Goal:
- DocumentNumberGenerator using the atomic upsert from database-design.md section 6.4,
  format RCV-YYYYMMDD-NNNN in UTC, run inside the creation transaction.
- POST creates a DRAFT with items; PUT replaces header and items, allowed only in DRAFT
  (409 DOCUMENT_NOT_EDITABLE), and locks the document row first.
- GET list (status filter, search, pagination, item_count and totals via aggregates) and
  GET detail (items with products, receipts newest first, all_items_received, audit fields).
  Avoid N+1 queries.
- Duplicate product in a document returns 422 DUPLICATE_PRODUCT_IN_DOCUMENT. Supervisor only for writes.

Tests: each validation case in US-04, number format and uniqueness (including parallel creation),
edit only in DRAFT, list filters, detail shape matches openapi.yaml.
Do not implement confirm, complete, cancel, or receipts.
```

| Field | Entry |
|---|---|
| Date / model | 2026-10-30 / Copilot SDK in VS Code (model not surfaced) |
| What the agent produced | Atomic UTC daily document numbering; Supervisor-only document create, list, detail, and DRAFT edit endpoints; request validation, aggregate list fields, eager-loaded detail resources, and PostgreSQL feature tests. |
| Problems found in review | Initial document requests returned 500 because `ReceivingDocument::receipts()` declared `HasMany` while returning `HasManyThrough`; the original test coverage also did not exercise parallel numbering. |
| Corrections made | Corrected the relationship return type and added a barrier-synchronized multiprocess test for unique numbers under concurrent generation, with isolated sequence cleanup. |
| Verification run | After rebuilding the backend: `docker compose exec -T -e LOG_CHANNEL=null backend php artisan test`: 52 tests passed, 383 assertions; Pint: 82 files passed; document routes: 4 verified; `GET /api/v1/health`: 200 `{"status":"ok","database":"ok"}`; db, backend, and frontend healthy; `git diff --check` clean. |
| Commit(s) | `7f68654` (committed by the developer). |

### T5. Document lifecycle (featured prompt)

**Prompt**

```text
Task T5: document lifecycle (confirm, complete, cancel) and the state machine.

Read first: .github/copilot-instructions.md, docs/technical-spec.md (sections 4.3, 10.4, 11),
docs/prd.md (US-08, US-12, US-13, section 8.3), docs/database-design.md (sections 6.1, 6.2, 6.5),
docs/openapi.yaml (confirm, complete, cancel).

Goal:
- Implement the transition table in technical-spec.md section 11.1 ONCE, in the DocumentStatus enum
  (or a small state machine class). No service method may compare status strings directly.
- confirm, complete, cancel each: open a transaction, lock the document row FIRST, then check the
  transition, then change status and set status_changed_by and status_changed_at.
- Complete requires every item fully received (409 DOCUMENT_NOT_FULLY_RECEIVED with incomplete_items
  in details). Cancel on PARTIALLY_RECEIVED returns 409 DOCUMENT_HAS_RECEIPTS. Any other invalid
  transition returns 409 INVALID_STATUS_TRANSITION with from and action in details.
- Supervisor only.

Tests: a data-driven test that covers EVERY (status, action) pair of the matrix, asserting both the
response and the database state; Operator gets 403; completed and cancelled documents cannot change.
Because receipts are not implemented yet, create PARTIALLY_RECEIVED fixtures with a factory and
note that clearly in the test.

Before coding: plan, assumptions, and open questions. Do not implement receipts.
```

| Field | Entry |
|---|---|
| Date / model | 2026-10-30 / Copilot SDK in VS Code (model not surfaced) |
| What the agent produced | Centralized document lifecycle transitions in `DocumentStatus`; Supervisor-only confirm, complete, and cancel endpoints; transactional state changes with document-first row locking, audit fields, and completion/cancellation guards; PostgreSQL tests for every status/action pair. |
| Problems found in review | The current task has no receipt-creation flow, so lifecycle tests cannot create real receipts to establish `PARTIALLY_RECEIVED` state. |
| Corrections made | Added a factory-backed partial-document fixture that sets received quantities without receipt rows, explicitly documented as temporary test setup until T6 implements receipts. |
| Verification run | `docker compose exec -T -e LOG_CHANNEL=null backend php artisan test`: 70 tests passed, 515 assertions; Pint: 85 files passed; 7 receiving-document routes verified; `GET /api/v1/health`: 200 `{"status":"ok","database":"ok"}`; db, backend, and frontend healthy. |
| Commit(s) | `a78bf36` (committed by the developer). |

### T6. Receiving, idempotency, inventory (featured prompt)

**Prompt**

```text
Task T6: the receiving operation, idempotency, and the inventory endpoints.

Read first: .github/copilot-instructions.md, docs/technical-spec.md (sections 4.2, 8.5, 10),
docs/database-design.md (sections 6.1 to 6.3, 9), docs/prd.md (US-09, US-10, US-11, US-14, US-15,
BR-06, BR-07, BR-10, BR-13 to BR-17), docs/openapi.yaml (recordReceipt, listInventory,
listInventoryTransactions).

Goal: implement ReceivingService::recordReceipt following technical-spec.md section 10.3 STEP BY STEP,
in this exact order, inside DB::transaction(..., attempts: 3). Do not reorder or merge steps:
lock document, idempotency lookup, status check, lock item, remaining check, update item,
insert receipt, update inventory RETURNING balance, insert transaction with balance_after,
move OPEN to PARTIALLY_RECEIVED, store idempotency record.
- Idempotency: request hash is sha256 of {document_id, item_id, quantity}. Same key and hash returns
  the stored response with header Idempotent-Replayed: true; different hash returns 409
  IDEMPOTENCY_KEY_REUSED. Only successful results are stored.
- ReceiptController is thin; the Form Request validates quantity and the Idempotency-Key header (8 to 128 chars).
- GET /inventory and GET /inventory-transactions per openapi.yaml (read only, newest first, filters).
  There must be no endpoint that changes transactions.
- Response of POST receipts must match the ReceiptResult schema.

Tests (name them after requirement IDs): partial then full receipt updates item, stock, ledger,
status; quantity above remaining returns 422 with details and changes NOTHING in any table; zero,
negative, decimal quantity; wrong item for the document gives 404; DRAFT, COMPLETED, CANCELLED give
409 DOCUMENT_NOT_RECEIVABLE; replay with the same key creates no second receipt; same key with a
different quantity gives 409; missing key gives 422; a rejected request followed by a corrected one
with a new key succeeds; after a mixed scenario every query in database-design.md section 9 returns
zero rows.

Before coding: plan, assumptions, open questions. Do not implement the frontend.
```

| Field | Entry |
|---|---|
| Date / model | 2026-10-30 / Copilot SDK in VS Code (model not surfaced) |
| What the agent produced | Transactional receipt recording in the specified lock and validation order; scoped idempotency with persisted SHA-256 request hash and replay response; receipt result resource; searchable, paginated inventory and filtered newest-first transaction history endpoints. |
| Problems found in review | PostgreSQL JSONB does not preserve JSON object key order, so replayed payload objects can have a different serialization order despite identical content. |
| Corrections made | Tests compare replay bodies as JSON objects with recursively canonicalized object keys, while preserving array order; verified that replay returns the stored body and does not apply the receipt again. |
| Verification run | `docker compose exec -T -e LOG_CHANNEL=null backend php artisan test`: 92 tests passed, 775 assertions; Pint: 97 files passed; 17 API routes verified; `GET /api/v1/health`: 200 `{"status":"ok","database":"ok"}`; db, backend, and frontend healthy; `git diff --check` clean. The six integrity queries in `database-design.md` returned zero rows after the mixed receipt/rejection/replay scenario. |
| Commit(s) | None; left uncommitted for developer review. |

### R1. Adversarial review of the receiving operation (featured prompt)

Run this after T6, in a **new** chat, so the agent reviews the code without the context of having written it.

**Prompt**

```text
Task R1: review only, do NOT change any file.

Read: docs/technical-spec.md (section 10.3), docs/database-design.md (section 6), docs/prd.md (BR-06,
BR-07, BR-13 to BR-17), then the implementation in backend/app/Services/ReceivingService.php and
its tests.

Act as a skeptical reviewer. Find places where this code could:
1. record more than the remaining quantity, or let stock and the ledger disagree;
2. break when two requests arrive at the same time (lock order, lockForUpdate outside a transaction,
   missing lock, wrong transaction scope);
3. apply the same Idempotency-Key twice, or return the wrong response on replay;
4. leave partial changes after an error;
5. deviate from the step order or the error precedence in the specification;
6. have tests that would still pass if the code were wrong (assertions on the response only, no
   database assertions, mocked database, SQLite).
For each finding give: severity, file and line, why it is a problem, and a proposed fix. If you find
nothing in a category, say what you checked. Do not praise.
```

| Field | Entry |
|---|---|
| Date / model | `TODO` |
| Findings reported by the agent | `TODO` |
| Findings I confirmed | `TODO` |
| Findings I rejected, and why | `TODO` |
| Fixes made | `TODO` |
| Commit(s) | `TODO` |

### T7. Backend hardening

**Prompt**

```text
Task T7: concurrency tests, demo script, and backend quality gates.

Read first: .github/copilot-instructions.md, docs/technical-spec.md (sections 15.1, 15.3, 15.3.1),
docs/database-design.md (sections 6, 9).

Goal:
- tests/Concurrency: committed fixtures (no per-test transaction), cleaned with truncation. Run at least
  2 processes at the same time for these scenarios from section 15.3.1: two receipts of 5 on an item with
  remaining 5 (exactly one succeeds); the same idempotency key sent twice at once (one receipt, same
  response for both); a first receipt racing with a cancel on the same document (never both
  succeed); many parallel receipts on different items of the same product (final stock equals the sum of
  accepted receipts, no deadlock error). Repeat each scenario several times in one run.
- scripts/concurrency-demo.sh: parallel curl requests against the running Compose stack that prints
  stock before and after and the result of each request.
- An integrity test that runs all queries of database-design.md section 9 after the scenarios.
- Run Laravel Pint and fix style issues. Add a test that checks responses of the main endpoints against
  docs/openapi.yaml if a lightweight validator is available; otherwise say so.

Report honestly any scenario that is flaky or that you could not reproduce.
```

| Field | Entry |
|---|---|
| Date / model | 2026-10-30 / Copilot SDK in VS Code (model not surfaced) |
| What the agent produced | Added PostgreSQL multiprocess tests for concurrent over-receipt, idempotent replay, receipt-versus-cancel, and parallel receipts sharing a product; each scenario runs three times with six integrity queries after every round. Added a curl demo and backend test instructions. |
| Problems found in review | The backend container is image-based rather than source-mounted, so `exec` continues using the running container's old image after source edits. Stock also accumulates across repeated scenarios, so assertions must check each operation's delta rather than assume a reset between rounds. |
| Corrections made | Tests use a temporary, isolated PostgreSQL database, independent PHP workers synchronized at a start barrier, non-transactional fixture writes, truncation cleanup, and database removal. README commands now build the backend image and use one-off containers. |
| Verification run (include how many repeated runs) | `docker compose run --rm --no-deps --entrypoint php backend artisan test`: 93 passed, 1,065 assertions. The targeted concurrency suite passed, with all four scenarios repeated three times; the six integrity queries returned zero rows after each full round. Pint passed for 99 files. The curl demo ran against a disposable API/database: stock 0 to 5, one HTTP 201 and one HTTP 422 `QUANTITY_EXCEEDS_REMAINING`; the temporary database was removed. Bash syntax, Compose config, `git diff --check`, and the health endpoint passed. No OpenAPI schema validator is installed, so a full YAML-contract validation was not run. |
| Commit(s) | None; left uncommitted for developer review. |

### T8. Frontend foundation

**Prompt**

```text
Task T8: frontend foundation.

Read first: .github/copilot-instructions.md, .github/instructions/frontend.instructions.md,
docs/technical-spec.md (sections 5.1, 5.2, 5.5, 5.6), docs/openapi.yaml (Auth operations, Error schema).

Goal:
- src/lib/api/client.js (only place that uses fetch): base URL from NEXT_PUBLIC_API_URL, headers, bearer
  token, ApiError in src/lib/api/errors.js (checks the error body shape with Zod), 401 handling.
- Zod schemas in src/lib/schemas for login, product, receiving document (with items), and receipt,
  mirroring docs/prd.md section 8.2.
- AuthProvider with token storage, /auth/me loading, logout; route guard; app layout with navigation
  that reflects the role; TanStack Query provider.
- Login page. Placeholder pages for the other routes in section 5.1.
- Add the shadcn components from the mapping table in section 5.6 that these pieces need (npx shadcn@latest add ...).
- Reusable pieces built ONLY from shadcn components: ErrorAlert (Alert, shows message and field errors),
  StatusBadge (Badge, one variant per status), pagination (Pagination), loading and empty states
  (Skeleton, Empty), and a confirm dialog helper (AlertDialog).

Tests (Vitest and Testing Library): the API client turns the standard error shape into ApiError, 401
clears the token, navigation shows only allowed items for each role, the Zod schemas accept and reject
the cases of PRD section 8.2.
Do not build the feature pages yet.
```

| Field | Entry |
|---|---|
| Date / model | Not recorded. |
| What the agent produced | Added the API client and error handling, Zod schemas, token storage and `AuthProvider`, login and protected layouts, role-aware navigation and guards, shared feedback/status/dialog/pagination components, placeholder routes, shadcn components required by section 5.6, and Vitest/Testing Library coverage. |
| Problems found in review | Lint rejected the generated mobile hook's effect state update; Vitest initially did not transform JSX in `.js` files and retained rendered DOM between tests; the initial SKU regex was stricter than the documented rule. |
| Corrections made | Reworked the mobile hook to use `useSyncExternalStore`, used `.jsx` for the tested JSX routes, added per-test DOM cleanup, and aligned the SKU regex with the PRD. |
| Verification run | `npm test`: 8 test files and 21 tests passed. `npm run lint` and `npm run build` passed. The login route returned HTTP 200 and the rendered login screen was checked in a browser. Authenticated end-to-end login against the Compose backend was not run. |
| Commit(s) | None; left uncommitted for developer review. |

### T9. Frontend features

Run this as two chats: **T9a** (products, document list, create, edit, detail read-only) and **T9b** (receipt form, lifecycle actions, inventory, transactions).

**Prompt T9a**

```text
Task T9a: products and receiving document pages.

Read first: .github/copilot-instructions.md, .github/instructions/frontend.instructions.md,
docs/technical-spec.md (sections 5.1 to 5.3, 5.5, 5.6), docs/prd.md (FR-UI-2, FR-UI-3, FR-UI-4, FR-UI-8),
docs/openapi.yaml. Use ONLY shadcn/ui components and the Zod schemas in src/lib/schemas for every form.

Goal: /products (list, search, create form for Supervisors only), /receiving-documents (list, status
filter, search, pagination), /receiving-documents/new and /[id]/edit (one shared form with an items editor
that prevents duplicate products and non-positive quantities as a hint only), and the read-only part of
/receiving-documents/[id] (header, items with expected, received, remaining, status badge, receipt history).
Show backend field errors and general errors. Use only API-provided computed values.
Add component tests for the form (duplicate product hint, backend error display).
```

**Prompt T9b**

```text
Task T9b: receipt form, lifecycle actions, inventory pages.

Read first: .github/copilot-instructions.md, .github/instructions/frontend.instructions.md,
docs/technical-spec.md (sections 5.3, 5.4, 5.6, 10.2), docs/prd.md (US-09, US-10, FR-UI-5 to FR-UI-9).
Use ONLY shadcn/ui components (Confirm, Complete, Cancel go through AlertDialog).

Goal: on /receiving-documents/[id]: the receipt form per item and the Confirm, Complete, Cancel, Edit
buttons shown by role and status exactly as the table in section 5.3 ("Ready to complete" when
all_items_received is true; no Cancel on PARTIALLY_RECEIVED). Implement the idempotency key behavior of
section 5.4 in lib/api/idempotency.ts and use it in the form: new key per attempt, disabled button while
in flight, same key kept after network error, timeout, or 5xx, discarded after any 2xx or 4xx.
On QUANTITY_EXCEEDS_REMAINING show remaining_quantity and refetch. Invalidate document, list,
inventory, and transactions queries after success.
Also /inventory and /inventory/transactions (filters by product and document).

Tests: double click sends one request; key is reused after a network failure and replaced after a 422;
buttons per role and status; over-receive error display.
```

| Field | T9a | T9b |
|---|---|---|
| Date / model | `TODO` | `TODO` |
| What the agent produced | `TODO` | `TODO` |
| Problems found in review | `TODO` | `TODO` |
| Corrections made | `TODO` | `TODO` |
| Verification run | `TODO` | `TODO` |
| Commit(s) | `TODO` | `TODO` |

### T10. Quality and delivery

**Prompt**

```text
Task T10: end-to-end tests, clean-start check, and documentation.

Read first: .github/copilot-instructions.md, docs/technical-spec.md (sections 14, 15.4, 15.5, 18),
docs/prd.md (section 12).

Goal:
- Playwright tests for the 8 end-to-end scenarios in section 15.4, running against the Compose stack.
- README.md with: stack and versions, requirements, clean-start steps (section 14.3), demo accounts, how to
  run each test suite, how to run the concurrency demo, API docs location, and how to stop and reset.
- Verify a clean start: remove containers and volumes, copy .env.example to .env, start, and run all suites.
  Report exactly what you ran and what happened.
- Draft docs/testing.md from the real results only: strategy, how to run, evidence that stock stays
  correct (integrity queries, concurrency demo output), evidence that duplicate and invalid receipts do
  not corrupt stock, and an honest list of known limitations from technical-spec.md section 18.
  Do not invent results; leave a clearly marked TODO for anything you could not run.
Do not change application behavior in this task. Report any defect you find instead of silently fixing it.
```

| Field | Entry |
|---|---|
| Date / model | `TODO` |
| What the agent produced | `TODO` |
| Problems found in review | `TODO` |
| Corrections made | `TODO` |
| Verification run | `TODO` |
| Commit(s) | `TODO` |

### R2. Requirement change exercise (practice for the technical review)

The technical review may ask for a requirement to be changed and the implementation updated. This exercise practices that on a **separate branch** and shows how the specification-first approach works when requirements change.

**Example change:** "Operators may also create `DRAFT` documents, but only Supervisors can confirm them." Replace it with another realistic change if you prefer, but keep it small and affecting both the documents and the code.

**Prompt**

```text
Task R2: requirement change. Work on branch change/operator-create-draft. Do not edit code first.

Change: Operators may also create DRAFT receiving documents (POST /receiving-documents) and edit their own
drafts. Confirm, complete, and cancel remain Supervisor only.

Step 1: list every place in docs/ that must change (PRD requirements, business rules, permission
matrix, assumptions A-04 and question Q-05, technical-spec sections 4.1, 5.3, 12.2, 15.3, openapi.yaml
x-allowed-roles). Give me the list and wait.
Step 2 (after my approval): propose the exact text changes to the documents.
Step 3 (after my approval): apply them, then change the code and tests that follow from them.
Step 4: run all suites and report results.
```

| Field | Entry |
|---|---|
| Change chosen | `TODO` |
| Documents the agent listed | `TODO` |
| Documents it missed (found by me) | `TODO` |
| Code and tests changed | `TODO` |
| Result of the test run | `TODO` |
| Branch kept or discarded | `TODO` |

---

## 8. Review checklist for agent output

Applied to every diff before committing.

### 8.1 General

- [ ] Only the requested task was done. No out-of-scope features (PRD section 4.2).
- [ ] No new dependency without a stated reason. Every package actually exists and is the one intended (agents sometimes invent package names).
- [ ] No secrets, `.env`, debug output, or leftover TODO code committed.
- [ ] Tests were not deleted, skipped, or loosened. A failing test was fixed by fixing the cause.
- [ ] The agent's claims were checked by running the commands myself.
- [ ] Naming and structure follow `technical-spec.md` section 3.

### 8.2 Backend

- [ ] Business rules are in services only. Controllers are thin.
- [ ] Every write service uses `DB::transaction` and locks rows in the order document, item, inventory.
- [ ] `lockForUpdate()` is used inside a transaction, never outside.
- [ ] No implicit route-model binding for documents, items, or products.
- [ ] Errors use `ApiException` and the standard shape; codes match `openapi.yaml`.
- [ ] Constraint and index names match `database-design.md` exactly.
- [ ] Migrations have `down()` and none of the already applied migrations was edited.
- [ ] No update or delete on `receipts` or `inventory_transactions`.
- [ ] No N+1 queries on list endpoints.
- [ ] Tests run against PostgreSQL and assert the database state, including that rejected requests change nothing.

### 8.3 Frontend

- [ ] `fetch` is used only in `lib/api/client.js`. No TypeScript files, `tsconfig.json`, or generated types were added.
- [ ] Every UI element is a shadcn/ui component. No other UI library, no hand-built buttons, tables, or dialogs, no hard-coded colors, fonts, or radius, icons only from `lucide-react`.
- [ ] Forms use Zod schemas with React Hook Form, and server field errors are copied into the form.
- [ ] No business rule is computed in the UI.
- [ ] Role and status handling matches `technical-spec.md` section 5.3.
- [ ] Idempotency key behavior matches section 5.4.
- [ ] Loading, empty, and error states exist. Unknown error codes do not crash the page.
- [ ] No `dangerouslySetInnerHTML`, no token logging.

### 8.4 Known agent failure modes for this project

| Failure mode | Why it matters here | Check |
|---|---|---|
| Business logic placed in a controller or in the frontend | Breaks BR-24 | Read the controller and the component |
| `lockForUpdate()` without a surrounding transaction, or locks taken in the wrong order | Race conditions or deadlocks | Read the service; run the concurrency tests |
| Idempotency lookup placed after the status check | A retry after completion would fail instead of replaying | Compare with section 10.3 |
| Tests that only assert the HTTP response | A bug that corrupts stock would still pass | Look for database assertions |
| Using SQLite or mocked database in tests | Constraints, triggers, and locks are not exercised | Check `phpunit.xml` |
| Adding a repository layer, events, or reports | Scope creep | Compare with section 3 and PRD 4.2 |
| Editing an already applied migration | Breaks existing databases | Check `git diff` on `database/migrations` |
| Inventing endpoints or fields not in `openapi.yaml` | Contract drift | Diff against the contract |
| Silently changing the specification | Documents stop being the source of truth | Check `git diff` on `docs/` |
| Reporting "all tests pass" without running them | False confidence | Run them myself |

---

## 9. Changes to instructions and specification during development

Every change to the agent instruction files or to the specification is recorded here, with the reason. The documents are updated **before** the code that depends on them.

| Date | File changed | What changed | Why (what went wrong or what was learned) | Commit |
|---|---|---|---|---|
| `TODO` | | | | |

## 10. Notable agent mistakes and how they were caught

Fill in as they happen. These are the most useful evidence of review.

| # | Task | What the agent got wrong | How I noticed | How it was fixed |
|---|---|---|---|---|
| 1 | T0 | The initial frontend dependency install ran at the repository root, creating unintended root npm artifacts. | Git status and manifest inspection showed the files outside `frontend/`. | Removed the session-created root artifacts and reinstalled the specified packages in `frontend/`. |
| 2 | `TODO` | | | |
| 3 | `TODO` | | | |

## 11. Verification evidence

Details and outputs are in `docs/testing.md`. Commands used to verify the agent's work independently:

| Purpose | Command |
|---|---|
| Rebuild backend image | `docker compose build backend` |
| Backend tests | `docker compose run --rm --no-deps --entrypoint php backend artisan test` |
| Code style | `docker compose run --rm --no-deps --entrypoint ./vendor/bin/pint backend --test` |
| Frontend checks | `npm run lint`, `npm test` |
| End to end | `npx playwright test` (stack running) |
| Concurrency tests | `docker compose run --rm --no-deps --entrypoint php backend artisan test --testsuite=Concurrency` |
| Concurrency demonstration | `scripts/concurrency-demo.sh` |
| Integrity queries | SQL from `database-design.md` section 9, expected zero rows |
| Clean start | `docker compose down -v`, then `docker compose up --build` |

## 12. Reflection

`TODO` (write after finishing, 5 to 10 sentences): what worked well when giving the agent documents instead of long prompts, where the agent was weakest, which specification gaps were found only during implementation, and what I would improve with more time.

## 13. Declaration

I reviewed all code in this repository, including the code generated by the AI agent, and I can explain and defend the design decisions, the receiving workflow from the frontend to the database, and any part of the implementation.
