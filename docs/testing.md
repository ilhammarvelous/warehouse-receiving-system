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
