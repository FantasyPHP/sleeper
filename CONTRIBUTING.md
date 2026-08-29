# Contributing

## Getting set up

The toolchain is defined by [devenv](https://devenv.sh), so the shell gives you the right
PHP and extensions without installing anything globally:

```bash
devenv shell     # installs composer dependencies on first entry
composer test    # fully offline, no network needed
```

## The quality gate

Everything below must pass before a change lands. Run them in this order — Pint and Rector
disagree about formatting if you run them the other way around.

```bash
composer fmt          # Laravel Pint
composer refactor     # Rector
composer lint         # PHPStan level max, 100% type coverage
composer test         # PHPUnit, offline
composer test:arch    # Deptrac
composer test:mutate  # Infection, minMsi 100
```

`composer fmt:check` and `composer refactor:check` are the non-mutating variants.

GitHub Actions runs exactly this gate, through the two devenv scripts it is split into —
`devenv shell ci-lint` (fmt:check, refactor:check, lint, test:arch) and `devenv shell
ci-test` (test, test:mutate). Both are runnable locally and use the same devenv shell CI
does, so a green run here and a green run there mean the same thing. CI needs no secrets.

**Deptrac enforces the internal layering**, outermost first: `connector` may reach
anything; `requests` may reach `data`, `enums` and `exceptions`; `data` may reach `enums`
and `exceptions`; `enums` and `exceptions` are leaves. A DTO reaching back up into the
request that returns it is a violation, and so is anything depending on the connector.

**Infection escapees get a real test, not an ignore.** Any exception added to
`infection.json5` must be a mutant that is provably unobservable — a cast that exists only
to satisfy PHPStan, say — and must carry a comment saying why. Prove the mutant is
equivalent first.

## Tests and fixtures

The suite runs in two modes, selected by `FANTASY_FIXTURES`:

- **`composer test`** (default) — fully offline. Fixtures under `tests/Fixtures/Saloon/` are
  replayed, and `MockConfig::throwOnMissingFixtures()` turns a missing fixture into a hard
  failure. The suite can never quietly reach the live API, which is why CI needs no network.
- **`composer test:record`** — records any missing fixture from the live API. Sleeper's read
  API is unauthenticated, so this needs no credentials, but it does spend rate-limit budget.
  `composer fixtures:refresh` wipes and re-records everything.

Fixtures are committed on purpose: the offline suite exercises real payload shapes rather
than guesses at documentation.

**Record a fixture and read it before modelling any DTO.** Sleeper publishes prose docs
rather than a machine-readable schema, and the live payloads carry keys the docs never
mention along with nulls where the docs imply a value. Model from a recorded response, not
from the docs. Keep new fixtures small; narrow the request rather than recording an
unfiltered endpoint — `/players/nfl` alone is several megabytes.

## Releasing

Versions follow [SemVer](https://semver.org) and are published to Packagist from git tags.

1. Make sure the full gate above is green.
2. Tag the release: `git tag -a v1.2.3 -m "v1.2.3" && git push origin v1.2.3`.
3. Packagist picks the tag up through the GitHub webhook.

Because this is a library, the public surface is the contract: the connector, the request
classes and their constructor signatures, the DTOs and their properties, and the enum
cases. Anything that changes those in an incompatible way is a major bump. Adding an
endpoint, a request parameter with a default, or a new DTO property is a minor bump.

The supported PHP range lives in `composer.json` (`require.php`). Raising it is a major
bump; it must move together with `rector.php`'s target version and whatever CI verifies.
