---
id: SLEEPER-6
title: >-
  Players endpoints: GET /players/{sport} and GET
  /players/{sport}/trending/{type}
status: Done
assignee: []
created_date: '2026-08-29 16:11'
updated_date: '2026-08-29 16:57'
labels: []
dependencies:
  - SLEEPER-5
modified_files:
  - src/Concerns/SupportsPlayerEndpoints.php
  - src/Requests/GetPlayersRequest.php
  - src/Requests/GetTrendingPlayersRequest.php
  - src/Data/Api/Player.php
  - src/Data/Api/ExternalPlayerIds.php
  - src/Data/Api/TrendingPlayer.php
  - src/Enums/TrendingType.php
  - examples/players.php
  - examples/trending.php
priority: medium
ordinal: 6000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
`GET /players/{sport}` with optional `position` and `active` filters, and `GET /players/{sport}/trending/{type}` with optional `lookback_hours` and `limit`.

Verified against the live API:

- `/players/nfl` returns an object keyed by player ID, not a list. `Payload::keys()` exists for exactly this.
- Unfiltered it is ~5MB and the docs ask that it be called at most once a day. `?position=DEF&active=true` returns 32 players in under 6KB and includes the non-numeric IDs, which makes it the right fixture. `?position=K&active=true` is already 190KB, so filtering alone is not enough — the filter has to be a narrow one.
- The unfiltered call must be hard to make by accident.
- Player IDs are strings and not always numeric: a team defense is `"DET"`, whose record carries only ten of the ~50 fields a human player has. Almost everything is therefore nullable.
- Trending returns a bare array of `{count, player_id}` and nothing else — resolving those IDs to players means a separate call.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A SupportsPlayerEndpoints trait exposes both routes in the API's own vocabulary, hydrating through each request's createDtoFromResponse
- [x] #2 The player map is keyed by the string player ID, and a team defense such as "DET" hydrates without coercion to int
- [x] #3 Unset filters register no empty query parameters, so an omitted position is not sent as position=
- [x] #4 Fetching every player unfiltered cannot happen by accident, and why is stated where a caller will see it
- [x] #5 A recorded fixture is filtered narrowly enough to stay small, and hydrates every mapped field including a record missing most of them
- [x] #6 Trending players hydrate from a recorded fixture and the lookback and limit parameters are covered
- [x] #7 One runnable example script per route prints something a human would want to read
- [x] #8 composer fmt, refactor, lint, test, test:arch and test:mutate are all green
<!-- AC:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Both player routes are in.

**Making the 5MB call hard to reach.** `players(Sport $sport, NflPosition $position, bool $activeOnly = true)` takes the position as a *required* argument, so you cannot fetch the whole map by leaving an argument off. The unfiltered call lives behind `everyPlayer()`, named so it has to be typed on purpose, with the once-a-day guidance and "this is a download, not a lookup" in its docblock.

**Two undocumented filter behaviours, both verified live:**
- `position` matches a player's **fantasy** positions, not his listed one. `?position=LEO` returns players whose `position` is `DE`, because LEO is a slot they are eligible for. The docs' wording ("players whose fantasy positions include this position") is right; its being called a position filter everywhere else is what misleads.
- `active` only responds to the literal string `true`. `active=false`, `active=0` and `active=nonsense` all return the unfiltered set — there is no way to ask for only retired players. So it is a flag expressed by presence, not a nullable boolean; sending `false` would look like a filter and do nothing.

A position nobody plays answers `200 {}`, and an unrecognised trending type answers `200 []`. Neither is an error, which is why both are enums here — a typo in a raw request looks like a quiet day rather than a mistake.

**Fixtures.** `?position=DEF&active=true` gives 32 records in 7.7KB and covers the sparse case: a team defense carries ten of the fifty-odd fields a human does, and its player ID is `"DET"`. For the dense case I needed a human record small enough to commit — `?position=K&active=true` is already 190KB — so the fixture is `?position=LEO`, which matches exactly two players in 3.6KB and carries every external ID.

**`height` is left raw.** Sleeper sends inches (`"77"`) for some players and feet-and-inches (`"6'5\""`) for others, with `"0"` and `"0'0\""` as placeholders. Parsing it would mean picking one reading and being wrong for the rest. An empty string folds to null; a literal zero is left alone rather than guessed at.

**One simplification found by mutation testing.** `name()` originally preferred `full_name` and fell back to joining the halves. Checking the full map showed `full_name` is byte-identical to `first_name . ' ' . last_name` for all 12,193 players that have it, and absent only for the 32 defenses — so the fallback could never be reached and the preference could never matter. `name()` now just joins the halves, and says why.

**`ExternalPlayerIds`.** The eight IDs that are actually populated for the NFL (ESPN, Yahoo, Sportradar, Rotowire, Rotoworld, FantasyData, GSIS, Stats). This is most of the reason to pull the map at all — a roster hands you Sleeper IDs and nothing else. The betting-provider IDs Sleeper also carries (`kalshi_id`, `oddsjam_id`, `swish_id`) and the always-null ones (`opta_id`, `pandascore_id`) are not modelled.

Gate green: 268 tests, 1,419 assertions, 0 deptrac violations, 100% MSI (334 mutants).
<!-- SECTION:FINAL_SUMMARY:END -->
