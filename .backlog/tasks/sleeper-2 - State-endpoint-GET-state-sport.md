---
id: SLEEPER-2
title: 'State endpoint: GET /state/{sport}'
status: Done
assignee: []
created_date: '2026-08-29 16:10'
updated_date: '2026-08-29 16:42'
labels: []
dependencies:
  - SLEEPER-1
modified_files:
  - src/Concerns/SupportsStateEndpoints.php
  - src/Requests/GetStateRequest.php
  - src/Data/Api/SportState.php
  - src/Enums/Sport.php
  - src/Enums/SeasonType.php
  - src/SleeperConnector.php
  - tests/Requests/GetStateRequestTest.php
  - examples/state.php
  - deptrac.php
priority: high
ordinal: 2000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The smallest endpoint Sleeper has: one path argument, no query string, a bare JSON object, and a payload that is stable enough to be a reliable fixture. It is the proof that the connector, the payload reader, the fixture harness and the example script all fit together, so it lands before any other endpoint.

The live response carries fields the docs do not mention (`season_has_scores`), and the docs mention `leg` and `display_week` without explaining that `display_week` can differ from `week`. Record the fixture and model what is in it.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A SupportsStateEndpoints trait exposes the endpoint in the API's own vocabulary and hydrates through the request's createDtoFromResponse, never Response::dto()
- [x] #2 GetStateRequest resolves /state/nfl and sends no query string
- [x] #3 A recorded fixture hydrates every mapped field of the state DTO, including the fields the docs omit
- [x] #4 Season type is exposed as an enum that tolerates a value outside pre/regular/post rather than throwing
- [x] #5 examples/state.php runs and prints something a human would want to read
- [x] #6 composer fmt, refactor, lint, test, test:arch and test:mutate are all green
<!-- AC:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
`/state/{sport}` is in end to end and the spine works.

Recording the fixture first paid for itself immediately — the endpoint is far less uniform than the docs suggest, and modelling from `/state/nfl` alone would have produced a DTO that throws on four of the six sports:

- `season` is not always a year. `/state/lcs` answers `"2022-summer"`, so it is a string.
- `previous_season` arrives quoted for NFL and unquoted for MLB. Same field, same endpoint, two JSON types.
- `season_type` has an undocumented fourth value, `off`, which is what NBA and NHL answer for most of the year. The docs name only pre/regular/post.
- `season_has_scores` is in every response and in no documentation.
- Fields are *absent*, not null, for sports that don't have them: `/state/wnba` sends five keys where `/state/nfl` sends ten.

Three fixtures are recorded rather than one — `nfl`, `lcs` and `wnba` — because the last two are the ones that break naive modelling.

`Sport` covers the six that answer; `SeasonType` is read through `tryFrom` so a fifth phase cannot cost a caller the week number sitting next to it.

Also settled the layering deviation here: `deptrac.php`'s `data.envelopes` layer was removed (Sleeper has no envelopes) and `requests` now reaches `data.api` / `data.infrastructure` directly.

Gate green: 116 tests, 0 deptrac violations, 100% MSI.
<!-- SECTION:FINAL_SUMMARY:END -->
