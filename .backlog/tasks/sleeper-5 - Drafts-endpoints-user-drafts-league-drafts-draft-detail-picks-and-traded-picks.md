---
id: SLEEPER-5
title: >-
  Drafts endpoints: user drafts, league drafts, draft detail, picks and traded
  picks
status: Done
assignee: []
created_date: '2026-08-29 16:11'
updated_date: '2026-08-29 16:57'
labels: []
dependencies:
  - SLEEPER-4
modified_files:
  - src/Concerns/SupportsDraftEndpoints.php
  - src/Requests/GetDraftRequest.php
  - src/Requests/GetDraftsForUserRequest.php
  - src/Requests/GetLeagueDraftsRequest.php
  - src/Requests/GetDraftPicksRequest.php
  - src/Requests/GetDraftTradedPicksRequest.php
  - src/Data/Api/Draft.php
  - src/Data/Api/DraftPick.php
  - src/Data/Api/DraftedPlayer.php
  - src/Data/Api/TradedPick.php
  - src/Enums/DraftType.php
  - src/Enums/DraftStatus.php
  - src/Enums/NflPosition.php
  - src/Enums/InjuryStatus.php
  - src/Enums/RosterStatus.php
  - examples/draft.php
priority: medium
ordinal: 5000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
`GET /user/{user_id}/drafts/{sport}/{season}`, `GET /league/{league_id}/drafts`, `GET /draft/{draft_id}`, `GET /draft/{draft_id}/picks` and `GET /draft/{draft_id}/traded_picks`.

Verified against the live API:

- A draft carries `draft_order` and `slot_to_roster_id` as objects keyed by user ID and draft slot respectively, both mapping to integers.
- `metadata` on a draft holds `name`, `description` and `scoring_type`; `metadata` on a pick holds a denormalised snapshot of the player (name, position, team, status, years of experience) with every numeric value sent as a string.
- Draft `type` (`snake`, `linear`, `auction`) and `status` (`complete`, `drafting`, `pre_draft`, `paused`) are worth enums that tolerate an unrecognised value.
- A draft's traded picks can legitimately be an empty array.

Draft `289646328508579840` belongs to the completed public league `289646328504385536` and is a stable fixture source. Its picks response is ~70KB, so narrow the fixture rather than recording it whole if the whole thing is not needed.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A SupportsDraftEndpoints trait exposes every route in this group in the API's own vocabulary, hydrating through each request's createDtoFromResponse
- [x] #2 Draft type and status are enums that tolerate an unrecognised value rather than throwing
- [x] #3 The denormalised player snapshot on a pick is modelled, including that its numeric fields arrive as strings
- [x] #4 An empty traded-picks response hydrates to an empty list rather than raising
- [x] #5 A recorded fixture per route hydrates every mapped field
- [x] #6 One runnable example script per meaningful draft workflow prints something a human would want to read
- [x] #7 composer fmt, refactor, lint, test, test:arch and test:mutate are all green
<!-- AC:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
All five draft routes are in.

**The pick's player snapshot.** `DraftedPlayer` models the denormalised `metadata` on each pick. The point worth knowing is that every value in it is a string — `number`, `years_exp` and `player_id` all arrive quoted — while the *same fields on the same players* come back as integers from `/players/nfl`. Same API, two encodings; both read through the coercing payload accessors so a caller sees `?int` either way. The snapshot is deliberately stale: it is what the player looked like when the pick was made, which is why rendering a 2019 draft needs no second call.

**Empty string as absence.** `injury_status` inside a pick's metadata is `""` rather than null when there is nothing to report — the only place in the API that does this — and `picked_by` is `""` for an autodrafted pick. Both are folded into null, with the reason stated where it happens.

**Two maps, two key types.** A draft's `draft_order` is keyed by *user* ID and `slot_to_roster_id` by draft slot; getting from a slot to the person who drafted from it needs both. Asserted directly, because they look interchangeable and are not.

**`TradedPick` now covers three shapes.** The league route sends neither extra ID, a trade's `draft_picks` sends `league_id` (always null so far), and `/draft/{id}/traded_picks` sends `draft_id` — unquoted, unlike the same ID everywhere else. One DTO, both fields nullable, each tested for which one it actually gets.

**Enums.** `DraftType` and `DraftStatus` as required. `NflPosition`, `RosterStatus` and `InjuryStatus` were needed here too and were enumerated from the full player map rather than guessed — 30 positions, 6 roster statuses, 9 injury designations, none of which Sleeper documents. Half the injury values are not injuries (`Sus` is a suspension, `DNR` is did-not-report); the enum says so.

**Fixtures.** Draft `418854418453114880` — a three-round, 36-pick dynasty rookie draft — was chosen over the obvious one from the docs' example league, which has 252 picks and ~70KB, and no traded picks. This one has 12. A second draft (`384109584450625537`, superseded before anybody picked) covers the empty-picks case: a draft nobody started answers `[]`, not 404.

Gate green: 242 tests, 0 deptrac violations, 100% MSI (296 mutants).
<!-- SECTION:FINAL_SUMMARY:END -->
