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
npm run lint
npm run build
```

The automated frontend test script is added with the later frontend testing tasks; it is not
configured in the T0 skeleton.

Run backend tests and formatting inside the Compose backend container:

```text
docker compose exec backend php artisan test
docker compose exec backend ./vendor/bin/pint --test
```

The shadcn/ui preset code is `b2D2iPGaG` (Luma, Mist, Emerald, Outfit, Lucide, and the project's
selected Radix primitives). The frontend is JavaScript-only.
