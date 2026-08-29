---
id: SLEEPER-4
title: >-
  Leagues endpoints: leagues, rosters, users, matchups, brackets, transactions
  and traded picks
status: Done
assignee: []
created_date: '2026-08-29 16:11'
updated_date: '2026-08-29 16:43'
labels: []
dependencies:
  - SLEEPER-3
modified_files:
  - src/Concerns/SupportsLeagueEndpoints.php
  - src/Requests/GetLeagueRequest.php
  - src/Requests/GetLeaguesForUserRequest.php
  - src/Requests/GetLeagueUsersRequest.php
  - src/Requests/GetRostersRequest.php
  - src/Requests/GetMatchupsRequest.php
  - src/Requests/GetPlayoffBracketRequest.php
  - src/Requests/GetTransactionsRequest.php
  - src/Requests/GetTradedPicksRequest.php
  - src/Data/Api/League.php
  - src/Data/Api/LeagueUser.php
  - src/Data/Api/Roster.php
  - src/Data/Api/RosterSettings.php
  - src/Data/Api/Matchup.php
  - src/Data/Api/BracketMatchup.php
  - src/Data/Api/BracketSlot.php
  - src/Data/Api/Transaction.php
  - src/Data/Api/TradedPick.php
  - src/Data/Api/WaiverBudgetTransfer.php
  - src/Enums/LeagueStatus.php
  - src/Enums/TransactionType.php
  - src/Enums/TransactionStatus.php
  - src/Enums/BracketType.php
  - src/Data/Infrastructure/Payload.php
  - examples/league.php
  - examples/matchups.php
  - examples/playoffs.php
  - examples/transactions.php
priority: high
ordinal: 4000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The largest endpoint group and the one carrying most of Sleeper's quirks.

`GET /user/{user_id}/leagues/{sport}/{season}`, `GET /league/{league_id}`, `/rosters`, `/users`, `/matchups/{week}`, `/winners_bracket`, `/losers_bracket`, `/transactions/{round}` and `/traded_picks`.

Verified against the live API:

- Roster points arrive split into integer and decimal halves (`fpts: 1776` alongside `fpts_decimal: 6`), and the same for `fpts_against` and `ppts`. The DTO should expose one float and keep the recombination in one place.
- Player IDs are strings and are not always numeric — a team defense is `"DET"`. Never coerce to int.
- The docs' `HTTP Request` line says `loses_bracket`; only `losers_bracket` resolves, and `loses_bracket` answers 404 with an HTML redirect body rather than JSON.
- Bracket rows carry `t1_from`/`t2_from` objects holding either `{"w": n}` or `{"l": n}`, and an optional `p` placement field.
- `settings` and `scoring_settings` on a league are large flat maps of numbers rather than fixed schemas.
- A league's `roster_positions` includes bench and flex slots, not just starting positions.

League `289646328504385536` is a completed public 2018 league and is a stable fixture source for every route in this group.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A SupportsLeagueEndpoints trait exposes every route in this group in the API's own vocabulary, hydrating through each request's createDtoFromResponse
- [x] #2 Roster and matchup points are exposed as single floats recombined from the integer and decimal halves, with the recombination in one place
- [x] #3 Player IDs are typed as string everywhere and a team defense such as "DET" round-trips unchanged
- [x] #4 Both bracket routes resolve, the losers bracket uses the route that actually works rather than the one the docs print, and bracket progression references are modelled
- [x] #5 Transaction type and status are enums that tolerate an unrecognised value rather than throwing
- [x] #6 A recorded fixture per route hydrates every mapped field
- [x] #7 One runnable example script per meaningful league workflow prints something a human would want to read
- [x] #8 composer fmt, refactor, lint, test, test:arch and test:mutate are all green
<!-- AC:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
All nine league routes are in, with fixtures recorded from three real leagues.

**The points split.** `fpts: 1776` plus `fpts_decimal: 6` is 1776.06 — hundredths, not tenths. Verified empirically rather than assumed: decimal halves up to 98 appear in the same league, and two of three rosters' recorded fractional parts match a week-by-week sum exactly under the hundredths reading. `ppts` (perfect-lineup score) splits the same way and is optional. The recombination lives in one private method on `RosterSettings`; nothing else knows the halves are halves. Weekly matchup points, by contrast, arrive as one number — no recombination there.

**Player IDs.** Typed `string` everywhere. A team defense is `"DET"`/`"CLE"`, sitting in the same array as `"4035"`; casting to int would zero the defenses silently. Asserted directly against the recorded roster.

**The documented route is wrong.** `loses_bracket` 404s — and answers with an HTML redirect page, not JSON. `losers_bracket` works. `BracketType` holds both segments and the explanation; a test asserts the URL does *not* contain the documented spelling.

**Things the docs don't mention, found by recording:**
- A league can have more members than rosters. The fixture league runs 12 teams between 14 members, so zipping members to rosters drops two. It also has four `is_owner` members, not one.
- `matchup_id` is null for every eliminated team during the playoffs — they still get a row, with a lineup and a score and no opponent.
- `is_owner` and `is_bot` are only ever `true` or `null`; neither is ever sent as `false`.
- Transaction timestamps are epoch **milliseconds**. `createdAt()`/`statusUpdatedAt()` convert, so the unit is remembered in one place.
- Every ID on a traded pick is a roster ID, including the two named `owner_id` and `previous_owner_id`.
- `bracket_id` and `loser_bracket_id` are the only IDs Sleeper sends unquoted; both are read back as strings so they match their two dozen siblings.

**Deliberate omissions.** `settings` and `scoring_settings` are read as flat maps rather than frozen into properties — there is no schema, a league sends only the rules its format uses, and Sleeper adds new ones without notice. Freezing them would mean a DTO that throws the first time somebody enables an unfamiliar rule. About a dozen chat fields (`last_message_id`, `last_author_avatar`, …) are not modelled at all: they are state for Sleeper's own client, not facts about the league.

**Fixtures.** League `289646328504385536` (a completed, frozen 2018 public league) covers eight routes. Two extra leagues were needed for the trade shapes, since the main one has neither: `337383787396628480` for a trade carrying draft picks and `423022503628124160` for one carrying FAAB. Total fixture weight for this group is under 110KB.

**Payload additions.** `object()`/`nullableObject()` for reading a nested JSON object as its own payload (roster settings, bracket `t1_from`, transaction settings), and `floats()` to complete the `ints()`/`strings()` set for `starters_points`.

**Examples.** Four: standings (the three-call join Sleeper doesn't do for you), a week's fixtures paired up by `matchupId`, both playoff brackets with progression, and a week of transactions plus a dynasty league's traded picks.

Gate green: 209 tests, 930 assertions, 0 deptrac violations, 100% MSI (261 mutants, all killed — the last two needed a roster with non-zero ties and moves, since every roster in the recorded league happens to have zero of both).
<!-- SECTION:FINAL_SUMMARY:END -->
