# Project Instructions: Warehouse Receiving System

This is a specification-driven project. The documents in `docs/` define what to build. Your job is to implement them faithfully, in small steps, and to verify your work. Write code, comments, commit messages, and documentation in English.

## 1. Read before coding

Sources of truth, in this order of authority for their topic:

| Topic | File |
|---|---|
| Business behavior, rules, user stories | `docs/prd.md` |
| Architecture, code structure, authorization, API flow, testing, task plan | `docs/technical-spec.md` |
| Database schema, constraints, locking, migrations | `docs/database-design.md` and `docs/erd.mmd` |
| HTTP contract: endpoints, schemas, error codes | `docs/openapi.yaml` |
| Data flow overview | `docs/data-flow.mmd` |

Rules about the documents:

- If a document is silent or two documents conflict, **stop and ask**. Never invent business behavior.
- If you believe a document is wrong, say so and propose the change. Do not edit files in `docs/` unless the developer asks.
- Cite requirement IDs (`BR-07`, `FR-RCV-3`, `US-09`) in test names and in short comments where a rule is enforced.

## 2. Stack

- Runtimes (fixed): PHP **8.5.12**, Node.js **24.22.1**.
- Backend: **Laravel 13**, PostgreSQL 16, Laravel Sanctum bearer tokens, PHPUnit. Folder `backend/`.
- Frontend: Next.js (latest stable), App Router, **JavaScript only (no TypeScript)**, Tailwind CSS, **shadcn/ui**, **Zod** (with React Hook Form), TanStack Query, `lucide-react`, Outfit font. Folder `frontend/`.
- shadcn/ui preset: Luma style, Mist base color, Emerald theme, Emerald chart color, Outfit heading and body font, Lucide icons, default radius. Details and initialization in `docs/technical-spec.md` section 5.6.
- Containers: Docker and Docker Compose (`db`, `backend`, `frontend`).
- Do not add a dependency that the technical specification does not mention without stating why and getting approval.
- Your built-in knowledge may be older than Laravel 13, the current Next.js, or the current shadcn CLI. When unsure about an API or command, read the official documentation for the installed version instead of guessing, and never downgrade a version to make code from memory work.

## 3. Non-negotiable business rules

These must be enforced by the **backend**, never only by the frontend (BR-24):

1. A receipt quantity must not exceed `expected_quantity - received_quantity` of the item (BR-07).
2. A document can be completed only when every item is fully received (BR-08).
3. Receipts are allowed only on documents in `OPEN` or `PARTIALLY_RECEIVED` (BR-06).
4. A document with any receipt cannot be cancelled (BR-11).
5. Every receipt creates exactly one `RECEIVE` inventory transaction and increases stock by exactly the received quantity, in the same database transaction (BR-13, NFR-2).
6. A rejected or failed operation must change nothing (BR-14).
7. A request with the same `Idempotency-Key` is applied at most once (BR-15).
8. `receipts` and `inventory_transactions` are append-only. Never write code that updates or deletes them (BR-16).
9. Stock on hand always equals the sum of its transactions (BR-17).
10. Role permissions follow the matrix in `docs/technical-spec.md` section 12.2.

## 4. Backend rules (also see `.github/instructions/backend.instructions.md`)

- Layers: middleware, Form Request, thin controller, service, model. Business rules and transactions live **only in services**.
- Do not use implicit route-model binding for products, documents, or items. Services load rows themselves.
- Lock rows in this order, always: document, then item, then inventory. Use `lockForUpdate()` **inside** `DB::transaction`.
- All errors go through `ApiException` and the global handler, using the error shape and codes in `docs/openapi.yaml`.
- Migrations: one per table, explicit constraint names exactly as in `docs/database-design.md`, working `down()`. Never edit a migration that has already been applied; add a new one.
- Backend tests run against PostgreSQL, never SQLite.

## 5. Frontend rules (also see `.github/instructions/frontend.instructions.md`)

- Write JavaScript (`.js`, `.jsx`). Do not add TypeScript files, `tsconfig.json`, or generated type files.
- **Every UI element must be a shadcn/ui component** from `src/components/ui`. Use the component mapping and rules in `docs/technical-spec.md` section 5.6. Add missing components with `npx shadcn@latest add <name>`. No other UI library, no hand-built buttons, tables, dialogs, badges, or alerts, no hard-coded colors, fonts, or radius (use the theme tokens). Icons only from `lucide-react`.
- Only `lib/api/client.js` calls `fetch`.
- Forms use Zod schemas from `src/lib/schemas` with React Hook Form. Zod is a usability hint only; the backend validation is the authority.
- The UI never computes business rules. Use `remaining_quantity` and `all_items_received` from the API.
- Hide or disable actions by role and status as a convenience only. The backend decides.
- Follow the idempotency key behavior in `docs/technical-spec.md` section 5.4.
- Never use `dangerouslySetInnerHTML`.

## 6. Security

- Never commit secrets. Only `.env.example` with placeholders is committed. `.env` stays git-ignored.
- Hash passwords, validate all input server-side, use bindings (Eloquent or query builder) and never string-built SQL.
- Do not leak stack traces or internal details in error responses.

## 7. How to work

1. **One task at a time.** Tasks are T0 to T10 in `docs/technical-spec.md` section 16. Do only the task you were given. Do not start the next one.
2. **Plan first.** Before writing code, give a short plan: files to create or change, assumptions, open questions. If a question blocks you, ask and wait.
3. **Stay in scope.** Do not build anything listed as out of scope in `docs/prd.md` section 4.2. No reports, no extra endpoints, no abstractions "for later".
4. **Keep it simple.** Prefer plain, readable code that follows the structure in `docs/technical-spec.md` section 3. No repository pattern, no event system, no extra layers unless the specification asks for them.
5. **Test with the code.** Add automated tests for the rules you implement. Tests must assert both the response and the database state.
6. **Never weaken tests.** Do not delete, skip, or loosen a test to make it pass. If a test and the specification conflict, report it.
7. **Run before you claim.** Run the tests and the linter after changes. Report the real result. Never say something passes if you did not run it, and say clearly what you could not verify.
8. **Do not commit.** The developer reviews the diff and commits. Do not run `git commit`, `git push`, or change Git history.
9. **Finish with a summary:** files created or changed, requirement IDs covered, how you verified it, anything left undone or uncertain.

## 8. Commands

These are the expected commands. They are confirmed in task T0 and recorded in the README. If a command here does not work, fix the README and tell the developer.

| Purpose | Command |
|---|---|
| Start everything | `docker compose up --build` |
| Backend tests | `docker compose exec backend php artisan test` |
| Backend code style | `docker compose exec backend ./vendor/bin/pint` |
| Run migrations | `docker compose exec backend php artisan migrate` |
| Frontend lint and tests | `npm run lint`, `npm test` (in `frontend/`) |
| Add a shadcn component | `npx shadcn@latest add <component>` (in `frontend/`) |
| Health check | `curl http://localhost:8000/api/v1/health` |

## 9. Definition of done for a task

- The behavior in the referenced requirement IDs works as specified.
- Automated tests for it exist and pass, and earlier tests still pass.
- Code follows the layering rules and the contract in `docs/openapi.yaml`.
- No secrets, no debug leftovers, no out-of-scope features.
- Your final summary is honest about what was and was not verified.
