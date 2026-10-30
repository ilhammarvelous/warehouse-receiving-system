# Warehouse Receiving System

Warehouse Receiving System is a Docker Compose application for managing products, receiving
documents, receipts, and inventory. The implementation is specification-driven; see `docs/` for the
business requirements, API contract, database design, and technical specification.

## Stack

| Component | Version |
|---|---|
| PHP | 8.5.12 |
| Laravel | 13 |
| PostgreSQL | 16 |
| Node.js | 24.22.1 |
| Next.js | 16.4.3 |
| Frontend | JavaScript, React, shadcn/ui |
| E2E tests | Playwright |

## Requirements

- Docker Desktop with Docker Compose
- Node.js 24.22.1 and npm for local frontend tests and Playwright
- Playwright Chromium (`npx playwright install chromium`)
- PHP 8.5.12 and Composer only when running backend commands outside Docker
- Git Bash (or another Bash environment) for `scripts/concurrency-demo.sh`

## Start the application

1. Copy `.env.example` to `.env`. On a first setup:

   ```powershell
   Copy-Item .env.example .env
   ```

2. Build the backend image:

   ```text
   docker compose build backend
   ```

3. Generate an application key. This overrides the entrypoint because the normal entrypoint
   refuses to start without `APP_KEY`:

   ```text
   docker compose run --rm --no-deps --entrypoint php backend artisan key:generate --show
   ```

   Copy the generated value into `.env` as `APP_KEY`.
4. Start the services:

   ```text
   docker compose up --build
   ```

The frontend is available at `http://localhost:3000`, the API at
`http://localhost:8000/api/v1`, and the health check at
`http://localhost:8000/api/v1/health`.

The `db` service is private to the Compose network. Its data is stored in the `pgdata` volume.
`docker compose down` stops and removes containers but preserves the volume. **`docker compose down
-v` deletes the database volume and all data in it.** Do not use the volume-reset steps below if you
need to keep local data or credentials in `.env`.

### Clean start and full reset

This resets the local database and replaces `.env` with the example configuration. Back up anything
you need first. The example demo credentials are for local use only.

```powershell
docker compose down --volumes --remove-orphans
Copy-Item .env.example .env -Force
docker compose run --rm --no-deps --entrypoint php backend artisan key:generate --show
```

Put the generated key in `.env` as `APP_KEY`, then start the services:

```text
docker compose up --build
```

## Local demo accounts

With the untouched `.env.example` defaults:

| Role | Email | Password |
|---|---|---|
| Supervisor | `supervisor@example.com` | `change-me-supervisor` |
| Operator | `operator@example.com` | `change-me-operator` |

The seed credentials are configurable with `SEED_SUPERVISOR_EMAIL`,
`SEED_SUPERVISOR_PASSWORD`, `SEED_OPERATOR_EMAIL`, and `SEED_OPERATOR_PASSWORD` in `.env`. Do not
use the demo passwords in a shared or production deployment.

## Verification

### Frontend unit and component tests

Run from `frontend/`:

```text
npm ci
npm test
npm run lint
npm run build
```

### Browser end-to-end tests

For a safe clean E2E run that does not alter the regular development database, use a separate Compose
project and a temporary environment file. Keep the commands in the same PowerShell window so the
Compose arguments and environment settings remain available. Run from the repository root:

```powershell
Copy-Item .env.example .env.e2e
$env:NEXT_PUBLIC_API_URL = "http://localhost:8001/api/v1"
$env:CORS_ALLOWED_ORIGINS = "http://localhost:3001"
$env:SEED_DEMO_DATA = "true"
$compose = @("--project-name", "warehouse-receiving-e2e", "--env-file", ".env.e2e", "-f", "docker-compose.yml", "-f", "docker-compose.e2e.yml")
docker compose @compose build backend
$env:APP_KEY = docker compose @compose run --rm --no-deps --entrypoint php backend artisan key:generate --show
docker compose @compose up --build -d
Invoke-WebRequest http://localhost:8001/api/v1/health
```

Install the browser once and run the E2E suite from `frontend/`:

```powershell
Set-Location frontend
npx playwright install chromium
$env:E2E_BASE_URL = "http://localhost:3001"
$env:E2E_API_URL = "http://localhost:8001/api/v1"
npm run test:e2e
```

The suite exercises the eight user journeys in `docs/technical-spec.md` section 15.4 against the
running application. It creates uniquely named products and receiving documents and records
receipts; those rows remain in the E2E database. This dedicated project binds the frontend and API
to ports 3001 and 8001 and uses its own `pgdata` volume, leaving the normal Compose stack and its
database untouched. To run backend tests in this environment, return to the repository root and use
the same `$compose` array with the backend commands below. PHPUnit refreshes the database, so do not
run it against the normal development database.

The test defaults to the demo credentials above. To use different accounts, set these variables
before running it in PowerShell:

```powershell
$env:E2E_SUPERVISOR_EMAIL = "your-supervisor@example.com"
$env:E2E_SUPERVISOR_PASSWORD = "your-supervisor-password"
$env:E2E_OPERATOR_EMAIL = "your-operator@example.com"
$env:E2E_OPERATOR_PASSWORD = "your-operator-password"
npm run test:e2e
```

Optional endpoints can be set with `E2E_BASE_URL` and `E2E_API_URL`; the defaults are
`http://localhost:3000` and `http://localhost:8000/api/v1`.

When finished, stop and remove only the disposable E2E project and volume from the repository root:

```text
Set-Location ..
docker compose @compose down --volumes --remove-orphans
Remove-Item .env.e2e
```

The regular development Compose stack stays running throughout this process.

### Backend tests and style

Run from the repository root. For a safe run, return to the repository root with `Set-Location ..`
and use the isolated E2E Compose project described above. The PHPUnit configuration targets the
PostgreSQL database named `warehouse`; feature tests do not clear pre-existing rows. Reset only the
disposable E2E database before the backend suite. **Never run backend tests or this reset against a
development database containing data you need.**

```text
docker compose @compose build backend
docker compose @compose run --rm --no-deps --entrypoint php backend artisan migrate:fresh --force
docker compose @compose run --rm --no-deps --entrypoint php backend artisan test
docker compose @compose run --rm --no-deps --entrypoint ./vendor/bin/pint backend --test
docker compose @compose run --rm --no-deps --entrypoint php backend artisan test --testsuite=Concurrency
```

`migrate:fresh` deletes all data in that Compose project's `warehouse` database. The concurrency
suite creates a uniquely named, isolated PostgreSQL database using the configured
database role, runs real service calls in separate PHP processes, truncates its fixtures, and drops
that database. The role must have permission to create databases. Each concurrency scenario is
repeated three times.

### OpenAPI contract

Run the OpenAPI linter from the repository root:

```text
npx --yes @redocly/cli lint docs/openapi.yaml
```

### Concurrency demonstration

Run the script from the repository root in Git Bash or another Bash environment with `curl`,
Node.js, and Docker Compose:

```text
bash scripts/concurrency-demo.sh
```

It uses the local demo credentials by default. Override `SEED_SUPERVISOR_EMAIL`,
`SEED_SUPERVISOR_PASSWORD`, `SEED_OPERATOR_EMAIL`, and `SEED_OPERATOR_PASSWORD` in the shell if
they differ from the backend configuration. The demo creates a product and receiving document and
leaves them in the database; it reports stock before and after and expects one simultaneous receipt
to succeed while the other is rejected.

## API and project documentation

- API contract: [`docs/openapi.yaml`](./docs/openapi.yaml)
- Product requirements: [`docs/prd.md`](./docs/prd.md)
- Technical specification and task plan: [`docs/technical-spec.md`](./docs/technical-spec.md)
- Database design and integrity queries: [`docs/database-design.md`](./docs/database-design.md)
- Test strategy and recorded results: [`docs/testing.md`](./docs/testing.md)

The shadcn/ui preset code is `b2D2iPGaG` (Luma, Mist, Emerald, Outfit, Lucide, and the project's
selected Radix primitives). The frontend is JavaScript-only.
