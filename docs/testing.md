# Testing Evidence

## T7: Backend concurrency and quality gates

Environment: PHP 8.5.12, Laravel 13, PostgreSQL 16, Docker Compose.

Commands:

```text
docker compose build backend
docker compose run --rm --no-deps --entrypoint php backend artisan test
docker compose run --rm --no-deps --entrypoint ./vendor/bin/pint backend --test
docker compose run --rm --no-deps --entrypoint php backend artisan test --testsuite=Concurrency
```

| Check | Result |
|---|---|
| Full backend suite | 93 tests passed, 1,065 assertions |
| Concurrency suite | Passed; each of four scenarios ran three times in separate PHP worker processes |
| Integrity queries | All six queries from `database-design.md` section 9 returned zero rows after each full round of all four scenarios |
| Laravel Pint | Passed for 99 files |
| Curl demo | Passed against an isolated API and disposable database; stock moved from 0 to 5, with one HTTP 201 and one HTTP 422 `QUANTITY_EXCEEDS_REMAINING` |
| Health endpoint | `{"status":"ok","database":"ok"}` |
| Shell and workspace checks | Bash syntax, Compose configuration, and `git diff --check` passed |

The concurrency suite does not use a per-test transaction. It creates a uniquely named PostgreSQL
database, writes fixtures that are visible to separate worker processes, truncates the fixture
tables, and drops the isolated database. The database role used for tests must have `CREATEDB`
permission. The curl demo was also run against a temporary database; the database was dropped after
the demonstration. When run against the regular development database, the demo intentionally leaves
its generated product, document, and receipt in place.

The cancel-versus-receipt test accepts either operation as the winner while asserting that the
losing operation is rejected and the resulting database state is consistent. The winning operation
can vary by run; no unexpected deadlock or inconsistent outcome was observed.

No lightweight OpenAPI response-schema validator is installed in the project dependencies. The
existing feature tests check selected API response structures, but full response validation against
`docs/openapi.yaml` was not run.

## T8: Frontend foundation

Environment: Node.js 24.22.1, Next.js 16.4.3.

Commands, run from `frontend/`:

```text
npm test
npm run lint
npm run build
```

| Check | Result |
|---|---|
| Vitest and Testing Library | 8 test files and 21 tests passed |
| ESLint | Passed |
| Next.js production build | Passed |
| Browser smoke check | `/login` returned HTTP 200 and rendered the sign-in form |

The browser check covered rendering only; authenticated login against the running Compose API was
not run as part of T8.

## T9: Frontend features

Environment: Node.js 24.22.1, Next.js 16.4.3.

Commands, run from `frontend/`:

```text
npm test
npm run lint
npm run build
```

| Check | Result |
|---|---|
| Vitest and Testing Library | 12 test files and 35 tests passed |
| ESLint | Passed |
| Next.js production build | Passed; products, receiving documents, inventory, and transaction routes were built |
| Receipt behavior | Tests cover double-click suppression, key reuse after network/5xx failures, new key after 422, remaining-quantity error display/refetch, and invalidation of document/list/inventory/transaction queries |
| Role and status actions | Tests cover Supervisor-only actions, cancel availability, and completion readiness from the API flag |

T9 component and hook tests use mocked API responses. A live login and browser end-to-end run
against the Docker Compose API were not performed; the E2E scenarios are part of T10.

## T10: End-to-end and delivery

Environment: PHP 8.5.12, Laravel 13, PostgreSQL 16, Node.js 24.22.1, Next.js 16.4.3,
Playwright 1.64.1, Docker Compose 5.1.4.

### Strategy and commands

- Vitest and Testing Library cover frontend unit and component behavior.
- Playwright runs the eight serial journeys in `frontend/tests/e2e/warehouse-receiving.spec.js`
  against a clean, isolated Compose project. The project uses a fresh PostgreSQL volume and ports
  3001/8001, so it does not touch the regular development stack or database.
- PHPUnit, Pint, and the concurrency suite run in Docker against PostgreSQL. The disposable
  `warehouse` database is reset with `artisan migrate:fresh --force` before the full feature suite;
  the concurrency suite creates and drops its own database.
- The six SQL queries in `database-design.md` section 9 run after the browser journeys and the
  concurrency demonstration.

The setup and exact PowerShell commands are documented in the README's isolated E2E and backend test
sections. From `frontend/`, run:

```text
npm test
npm run lint
npm run build
npm run test:e2e
```

In the disposable Compose project, run:

```text
docker compose @compose run --rm --no-deps --entrypoint php backend artisan migrate:fresh --force
docker compose @compose run --rm --no-deps --entrypoint php backend artisan test
docker compose @compose run --rm --no-deps --entrypoint ./vendor/bin/pint backend --test
docker compose @compose run --rm --no-deps --entrypoint php backend artisan test --testsuite=Concurrency
```

### T10 results

| Check | Result |
|---|---|
| Isolated clean Compose start | Started with a new project and PostgreSQL volume using a copy of `.env.example`; API health returned `{"status":"ok","database":"ok"}`. The existing default project and its `.env`/volume were left untouched. |
| Vitest and Testing Library | 12 files and 35 tests passed. |
| ESLint | Passed. |
| Next.js production build | Passed; product, receiving-document, inventory, and transaction routes were built. |
| Playwright | All 8 user journeys passed against the isolated Compose stack. |
| Sonner notifications | Playwright verified visible success notifications after login, product/document creation, receipt, confirmation, and completion; invalid receipt input showed validation feedback rather than a success toast. |
| Backend PHPUnit | 93 tests passed, 1,064 assertions, after resetting the disposable database. |
| Laravel Pint | Passed for 99 files. |
| Concurrency suite | 1 test passed, 289 assertions; all four concurrency scenarios ran three times. |
| Integrity queries | All six queries returned zero rows after the E2E suite and concurrency demonstration. |
| Concurrent receipt demonstration | SKU `CONC-066C5411C829`; stock moved from 0 to 5; one request returned HTTP 201 for quantity 5 and the other HTTP 422 `QUANTITY_EXCEEDS_REMAINING`. |
| Invalid receipt (BR-07, BR-14) | The UI displayed the remaining-quantity validation; the direct API attempt returned 422 and `remaining_quantity: 3`. The received quantity stayed 2, remaining quantity stayed 3, and inventory stayed 2. |
| Double-click receipt (BR-13, BR-15, BR-17) | One receipt request and one history row were recorded; inventory increased by exactly 2. |
| OpenAPI lint | **Failed:** 1 error and 3 warnings in the existing contract; details below. |

The concurrency demonstration was run from Git Bash against the disposable API. Its output was:

```text
Stock before: 0
Stock after: 5
Request 1: HTTP 201, 5
Request 2: HTTP 422, QUANTITY_EXCEEDS_REMAINING
Concurrency check passed: exactly one receipt changed stock.
```

The actual request order can vary. The document number and SKU are generated per run.

### OpenAPI and clean-start follow-ups

- `npx --yes @redocly/cli lint docs/openapi.yaml` reported an error at
  `ReceivingDocumentDetail.status_changed_by` (line 1331): `nullable: true` is used beside `allOf`
  without a `type`. It also reported warnings for the missing `info.license`, the localhost server
  URL, and the health operation's missing 4xx response. The OpenAPI document is a source-of-truth
  specification and was not changed; the spec owner should approve any correction.
- **TODO — canonical destructive reset:** the exact reset of the regular Compose project
  (`docker compose down -v` and replacement of `.env`) was not run because that project already had
  a live database volume and user configuration. Instead, the same Compose services were verified
  from a fresh isolated project and volume using `.env.example`; the regular database was preserved.
- Full runtime response validation against the OpenAPI schemas was not run. Feature tests assert
  selected response structures, and the contract lint currently has the defect above.

### Known limitations from `technical-spec.md` section 18

- JavaScript does not provide compile-time checking of request and response shapes; Zod, ESLint,
  component tests, and Playwright provide partial mitigation.
- shadcn/ui presets and CLI behavior can change over time; the setup details are recorded in the
  README.
- Access tokens in `localStorage` are exposed to XSS; cookie-based sessions would be safer but add
  an application layer.
- Document-level locking serializes receipts for one document; this trades throughput for
  simplicity.
- Idempotency keys have no cleanup job, so the table can grow without bound.
- Stock corrections and receipt reversals are not supported.
- Short deliveries cannot be closed; the document remains partially received.
- `NEXT_PUBLIC_API_URL` is baked into the frontend at build time; changing it requires a rebuild.
- There is a single backend instance, and migrations run in its entrypoint.
- Login has no rate limiting; this was not required by the PRD and should be addressed before
  production use.
- Product and document search use `ILIKE '%term%'`; a trigram index may be needed at larger scale.
