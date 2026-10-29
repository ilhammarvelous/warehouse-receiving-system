---
applyTo: "backend/**"
---

# Backend instructions (Laravel 13, PHP 8.5.12)

Runtime: Laravel 13 on PHP 8.5.12 with PostgreSQL 16 and PHPUnit (not Pest). Your built-in knowledge may predate Laravel 13: when unsure about a framework API, configuration file, or default, read the official Laravel 13 documentation instead of guessing, and do not downgrade versions.

Source of truth: `docs/technical-spec.md` sections 4, 8, 10, 11, 12; `docs/database-design.md`; `docs/openapi.yaml`.

## Structure

- Follow the folder layout in `docs/technical-spec.md` section 3: `Enums`, `Exceptions`, `Http/{Controllers/Api/V1,Middleware,Requests,Resources}`, `Models`, `Services`.
- Routes live in `routes/api.php` under `/api/v1`. API only: do not enable sessions, cookies, or `statefulApi()`.
- Controllers: call one service method and return a Resource. No business rules, no `DB::` calls, no Eloquent writes.
- Form Requests: validation and normalization (trim, uppercase SKU). Rules come from `docs/prd.md` section 8.2.
- Services: the only place for business rules and `DB::transaction(..., attempts: 3)`.
- Use `$fillable` on models. Use enums for `Role`, `DocumentStatus`, `TransactionType`, `ErrorCode`.

## Errors

- Throw `ApiException` with an `ErrorCode` for every business or state violation.
- The global handler renders all `/api/*` exceptions as `{ "error": { "code", "message", "details" } }`.
- Map database constraint names to error codes exactly as in `docs/technical-spec.md` section 4.4. Unknown errors become `500 INTERNAL_ERROR` with a generic message.
- Error precedence: 401, 403, 422, 404, then 409 or 422 for business rules (`docs/technical-spec.md` section 8.5).

## Database

- Migrations: one per table, in the order of `docs/database-design.md` section 8.2. Use `DB::statement()` for `CHECK` constraints, expression indexes, and triggers. Constraint and index names must match the design exactly.
- Timestamps are `timestampsTz()`. Application and database timezone are UTC.
- Lock order: document, item, inventory. `lockForUpdate()` only inside a transaction.
- The receipt operation follows `docs/technical-spec.md` section 10.3 step by step. Do not reorder the steps.
- Never update or delete rows in `receipts` or `inventory_transactions`.

## Tests

- PHPUnit feature and unit tests against PostgreSQL. Never SQLite.
- Name tests after requirements, for example `test_br07_receipt_above_remaining_is_rejected`.
- Assert the response **and** the database state, including that rejected requests change nothing.
- Concurrency tests commit real data (no per-test transaction) and clean up with truncation.
- Code style: Laravel Pint.
