---
id: SLEEPER-1
title: 'Client foundation: connector, error mapping and the typed payload readers'
status: Done
assignee: []
created_date: '2026-08-29 16:10'
updated_date: '2026-08-29 16:23'
labels:
  - foundation
dependencies: []
documentation:
  - 'https://docs.sleeper.com'
  - PORTING.md
modified_files:
  - src/SleeperConnector.php
  - src/Exceptions/SleeperRequestException.php
  - src/Exceptions/ApiRequestException.php
  - src/Exceptions/NotFoundException.php
  - src/Exceptions/RateLimitException.php
  - src/Exceptions/UnexpectedPayloadException.php
  - src/Exceptions/RequestFailure.php
  - src/Data/Infrastructure/Payload.php
  - src/Data/Infrastructure/PayloadList.php
  - src/Data/Contracts/ApiDataContract.php
  - tests/TestCase.php
  - tests/RequestTestCase.php
  - tests/Doubles/StubRequest.php
  - tests/HarnessTest.php
  - tests/SleeperConnectorTest.php
  - tests/Data/PayloadTest.php
  - tests/Data/PayloadListTest.php
  - tests/Exceptions/RequestFailureTest.php
  - deptrac.php
  - infection.json5
priority: high
ordinal: 1000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Everything an endpoint needs before it can exist. Sleeper's read API takes no credentials, so the connector is constructed with no arguments; the rate limit (~1000 calls/minute before an IP block) is the only operational constraint, which keeps the retry and timeout plumbing from the fantasypros connector relevant.

Sleeper returns naked JSON with no envelope: a bare object for some endpoints and a bare array for others. `Payload` covers the object case; the array case needs a list-oriented counterpart in `Data/Infrastructure` rather than fake envelope DTOs invented to mirror fantasypros.

Reference implementation is the sibling checkout at `../fantasypros`. Port `Payload` structurally unchanged — it is earmarked for extraction into a shared `fantasyphp/client-core`.

Documented Sleeper error codes are 400, 404, 429, 500 and 503. There is no 401 or 403, so the `RequestFailure` match arms differ from fantasypros and `AuthenticationException` must not be ported.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 SleeperConnector extends Saloon\Http\Connector, resolves https://api.sleeper.app/v1, takes no constructor arguments, and uses AcceptsJson, AlwaysThrowOnErrors and HasTimeout
- [x] #2 A 429 or 5xx is retried three times with exponential backoff; a 400 or 404 is not retried
- [x] #3 Every HTTP failure surfaces as a type implementing SleeperRequestException that also extends Saloon's RequestException, so handleRetry() never sees a type outside its declared union
- [x] #4 Payload reads one decoded JSON object with no DTO touching mixed, and a list counterpart in Data/Infrastructure reads a bare JSON array response
- [x] #5 A response body that is not the shape the reader expects raises UnexpectedPayloadException rather than a type juggling surprise
- [x] #6 tests/TestCase.php manages only FANTASY_FIXTURES, and tests/RequestTestCase.php builds a connector that takes no arguments
- [x] #7 composer fmt, refactor, lint, test, test:arch and test:mutate are all green
<!-- AC:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Connector, error mapping and the payload readers are in and the whole gate is green.

**Connector.** `SleeperConnector` takes no constructor arguments — Sleeper performs no authentication at all, so there is no key to validate, no `fromEnvironment()` and no `defaultAuth()`. Everything else carries over from fantasypros: `AcceptsJson`, `AlwaysThrowOnErrors`, `HasTimeout`, three tries with exponential backoff, retry on 429 and 5xx.

**Errors.** `SleeperRequestException` is the one catchable interface; `NotFoundException` (404), `RateLimitException` (429) and `ApiRequestException` (everything else) implement it and extend Saloon's `RequestException`. `RequestFailure` reads no body, because Sleeper sends none worth reading: a 404 answers with a literal `null` and the mis-documented `loses_bracket` route answers with an HTML redirect page. There is no 401/403 arm — there is nothing to authenticate.

**Readers.** `Payload` is ported structurally unchanged from fantasypros with its docblocks re-pointed at Sleeper's own misbehaviour. Two additions were needed and both are deliberate:

- `Payload::fromResponseOrNull()` — an unknown *username* answers `200` with a body of literal `null` rather than a 404 (verified against the live API), so a miss has to be distinguishable from a body that is merely the wrong shape. Matched against the raw body rather than a decode so the two stay apart.
- `PayloadList` — the list-oriented counterpart. Sleeper has no envelopes; `/rosters`, `/users`, `/matchups`, `/transactions`, `/traded_picks`, the brackets and `/picks` all answer with a bare array, which leaves `Payload::fromResponse()` nothing to read.

**Deviation from the scaffolding.** `deptrac.php` shipped with a `data.envelopes` layer and a rule that `requests` may reach only that layer. Sleeper has no envelopes and the brief says not to invent fake ones, so the layer is removed and `requests` now reaches `data.api`, `data.contracts` and `data.infrastructure` directly — which is what the layering prose said all along ("requests reach data, enums, exceptions"). The direction of every arrow is unchanged: a DTO reaching back into a request is still a violation.

**Mutation testing.** One escapee, in `Payload::nullableFloat`: dropping the `(float)` cast is unobservable because PHP widens int to float on return even under `strict_types`, so a method declared `: ?float` returns the identical value either way. It carries an `infection.json5` ignore with that proof in a comment — the same one the reference repo carries for the same line.

Gate: `fmt`, `refactor`, `lint`, `test` (103 tests), `test:arch` (0 violations), `test:mutate` (100% MSI) all green.
<!-- SECTION:FINAL_SUMMARY:END -->
