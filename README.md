# Warehouse Receiving System

This repository contains the Laravel API, Next.js frontend, and PostgreSQL development stack.
The project is being implemented in tasks T0–T10; business features are not included in the T0
skeleton.

## Requirements

- Docker Desktop with Docker Compose
- Node.js 24.22.1 for local frontend commands
- PHP 8.5.12 and Composer for local backend commands

## Start the development stack

1. Copy `.env.example` to `.env` and replace the placeholder values for any shared environment.
2. Build the backend image:

   ```text
   docker compose build backend
   ```

3. Generate an application key. This command overrides the entrypoint because the normal entrypoint
   intentionally refuses to start without `APP_KEY`:

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
`docker compose down -v` removes that database volume and its data.

## Verification commands

Run frontend checks from `frontend/`:

```text
npm test
npm run lint
npm run build
```

`npm test` runs the Vitest and Testing Library suite.

After changing backend source, rebuild its image so test runs use the workspace files. Run backend
tests and formatting in one-off Compose containers:

```text
docker compose build backend
docker compose run --rm --no-deps --entrypoint php backend artisan test
docker compose run --rm --no-deps --entrypoint ./vendor/bin/pint backend --test
docker compose run --rm --no-deps --entrypoint php backend artisan test --testsuite=Concurrency
```

The concurrency suite creates a uniquely named, isolated PostgreSQL database using the configured
database role, runs real service calls in separate PHP processes, truncates its fixtures, and drops
that database. The role must have permission to create databases. It repeats each concurrency
scenario three times.

To demonstrate two simultaneous receipt requests against the running Compose stack, run the Bash
script from the repository root in Git Bash on Windows, or another Bash environment with `curl`,
Node.js, and Docker Compose:

```text
bash scripts/concurrency-demo.sh
```

The script uses the local demo credentials by default. Override `SEED_SUPERVISOR_EMAIL`,
`SEED_SUPERVISOR_PASSWORD`, `SEED_OPERATOR_EMAIL`, and `SEED_OPERATOR_PASSWORD` in the shell if
they differ from the values configured for the backend. It creates a new product and document for
each run and leaves them in the database; it reports stock before and after and expects one receipt
to succeed while the other is rejected.

The shadcn/ui preset code is `b2D2iPGaG` (Luma, Mist, Emerald, Outfit, Lucide, and the project's
selected Radix primitives). The frontend is JavaScript-only.
