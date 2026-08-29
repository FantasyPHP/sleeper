# Porting notes: fantasypros → sleeper

Scaffolding for this client was lifted from [`FantasyPHP/fantasypros`](https://github.com/FantasyPHP/fantasypros),
which is the reference implementation for how these clients are built. When something here
is ambiguous, go read the equivalent file in that repo — it is a sibling checkout at
`../fantasypros`.

Delete this file once the client is real.

## What came over

| File | Notes |
| --- | --- |
| `composer.json` | Same dev toolchain and scripts. See the deltas below. |
| `phpstan.neon` | Level max, 100% type coverage across `src`, `tests`, `examples`. Unchanged. |
| `rector.php` | Unchanged. |
| `phpunit.xml` | Suite renamed to `Sleeper`. |
| `deptrac.php` | Same layer scheme, Sleeper namespaces. **Layer names are aspirational** — they describe the `src/` layout fantasypros settled on, which does not exist here yet. |
| `infection.json5` | `minMsi: 100`. The fantasypros mutator ignores were project-specific and were dropped. |
| `.gitattributes` | `export-ignore` set so the Packagist archive ships only `src`, licence and manifest. |
| `.github/workflows/ci.yml` | Calls the shared reusable workflow `FantasyPHP/devenv/.github/workflows/php-client.yaml@main`. No secrets. |
| `.github/dependabot.yml` | Weekly, grouped, 5-day cooldown. Unchanged. |
| `devenv.nix` / `devenv.yaml` / `.envrc` | Imports the shared `github:FantasyPHP/devenv` module. |
| `.backlog/config.yml` | `project_name: Sleeper`, `task_prefix: SLEEPER`. |
| `CLAUDE.md` / `AGENTS.md` | The Backlog.md MCP block, verbatim. |
| `CONTRIBUTING.md` | Adapted: the quality gate and layering are identical, the fixture section reflects Sleeper's unauthenticated API. |
| `tests/bootstrap.php`, `tests/FixtureMode.php` | The record/replay harness, minus the API-key half. |

## What was deliberately NOT copied

- **`pint.json` and `cliff.toml`** — the `FantasyPHP/devenv` module writes both on shell
  entry, and `.gitignore` already excludes them. Do not commit them; run `devenv shell`.
- **`.pre-commit-config.yaml`, `.mcp.json`, `opencode.jsonc`** — also devenv-generated,
  symlinks into the nix store in fantasypros.
- **`vlucas/phpdotenv`** — only existed to load `FANTASYPROS_API_KEY` for fixture recording.
  Sleeper's read API is unauthenticated, so there is no `.env` to load. Add it back if the
  client ever grows a write path.
- **`symfony/yaml`** — only used by `EnumsMatchSpecTest`, which asserted the enums matched
  the OpenAPI spec in `docs/`. Sleeper publishes no machine-readable spec, so both the
  dependency and that test shape are gone.
- **`tests/TestCase.php`, `tests/RequestTestCase.php`, `tests/FixtureSafetyTest.php`** — worth
  porting, but all three reference the connector, which does not exist yet. See below.
- **`src/`, `README.md`, `CHANGELOG.md`, `examples/`** — this is the actual work.

## How Sleeper differs from FantasyPros

This is the part that invalidates a straight copy of the fantasypros connector.

1. **No authentication.** No API key, no header, no `.env`. Delete every instinct to port
   `MissingApiKeyException`, `AuthenticationException`, `defaultAuth()`,
   `fromEnvironment()`, or `FixtureSafetyTest`'s key-leak guards. The connector constructor
   should take nothing.
2. **Read-only.** `https://api.sleeper.app/v1`. There are no write endpoints, so no request
   bodies and no idempotency concerns.
3. **Rate limit is the constraint, not the key.** Sleeper documents a soft ceiling around
   1000 calls/minute. Keep `HasTimeout` and the 429/5xx retry logic from the fantasypros
   connector; that carries over as-is.
4. **Some payloads are huge.** `/players/nfl` is several MB and the docs say to call it at
   most once a day. Never record it as a fixture unfiltered.
5. **Prose docs, no schema.** fantasypros modelled DTOs against `docs/*.yml` and got burned
   where the spec had drifted. Here there is no spec at all — record a fixture, read it,
   then model. `docs/` is empty on purpose.

## Suggested order of work

1. `devenv shell` → confirm `pint.json` and `cliff.toml` appear, then `composer update`.
2. Port `tests/TestCase.php` from fantasypros, trimmed to manage only `FANTASY_FIXTURES`
   (the API-key var is gone) — this unblocks writing any test at all.
3. Build `src/SleeperConnector.php` from `../fantasypros/src/FantasyProsConnector.php`:
   keep `AcceptsJson`, `AlwaysThrowOnErrors`, `HasTimeout`, `handleRetry`,
   `getRequestException`; drop the whole auth block.
4. Port `src/Exceptions/` (`RequestFailure`, `ApiRequestException`, `RateLimitException`,
   `UnexpectedPayloadException`) and `src/Data/Infrastructure/Payload.php` — these are the
   generic pieces and are already earmarked for extraction into `fantasyphp/client-core`
   (see fantasypros backlog task `fantasy-18`). Keep them structurally identical so that
   extraction stays cheap.
5. Port `tests/RequestTestCase.php`, then the first endpoint end to end — `GET /league/{id}`
   is a good first one: small payload, no auth, stable shape.
6. `composer test:arch` only becomes meaningful once `src/` has the layered directories
   deptrac.php names.

`composer test` will fail with "no tests executed" until step 5 lands. That is expected.
