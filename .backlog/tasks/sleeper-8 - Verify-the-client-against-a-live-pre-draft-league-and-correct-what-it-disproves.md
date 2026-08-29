---
id: SLEEPER-8
title: >-
  Verify the client against a live pre-draft league and correct what it
  disproves
status: Done
assignee: []
created_date: '2026-08-29 18:58'
updated_date: '2026-08-29 19:06'
labels:
  - correction
dependencies: []
modified_files:
  - src/Data/Api/League.php
  - src/Data/Api/LeagueUser.php
  - src/Data/Api/Roster.php
  - src/Data/Api/Draft.php
  - tests/Requests/GetLeagueRequestTest.php
  - tests/Requests/GetRostersRequestTest.php
  - tests/Requests/GetLeagueUsersRequestTest.php
  - tests/Requests/GetPlayoffBracketRequestTest.php
  - tests/Requests/GetDraftRequestTest.php
  - tests/Requests/GetLeagueDraftsRequestTest.php
  - examples/ids.php
  - examples/league.php
  - examples/matchups.php
  - examples/playoffs.php
  - examples/transactions.php
  - examples/draft.php
  - examples/user.php
  - README.md
  - composer.json
  - devenv.nix
priority: high
ordinal: 8000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Every fixture recorded so far comes from a completed 2018 or 2019 league. That skewed the modelling: states that only exist before a season starts were never seen, and two docblock claims were written from a single frozen league and are wrong.

Verified against league `1399461312144084992` (a `pre_draft` 2026 league, 12 rosters, 3 members, auction draft) and user `1399295919517605888`:

- `previous_league_id` is `null` for a league with no predecessor — the `League` docblock claims it is `"0"`. Both spellings occur: across 75 leagues, 55 send `null` and 5 send `"0"`. A caller testing `!== null` gets it wrong for the second group.
- `is_bot` and `is_owner` do arrive as real `false`/`true` booleans. The `LeagueUser` docblock claims neither is ever sent as `false`, which was true of the 2018 league only.
- An undrafted roster's `starters` is ten literal `"0"` placeholder IDs, not an empty array.
- Nine of twelve rosters have `owner_id: null` — orphan teams, modelled but never exercised against a real response.
- Playoff brackets are populated before the season starts, with `w`/`l` null and `_from` references standing in for unplayed matches. The only coverage of that shape is a hand-written payload.
- A draft can have `draft_order: null` and no `slot_to_roster_id` key at all.
- A league member's `metadata.avatar` is a full `sleepercdn.com/uploads/…` URL, distinct from the hex `avatar` ID. Both resolve; only the latter goes through `Avatar`.

Not doing: adding `.env` or `vlucas/phpdotenv` back for fixture IDs. `.env` is gitignored, so recording would only work for whoever holds that file, which breaks the property that any contributor and CI can re-record from scratch with no secrets. Fixture IDs stay as visible constants in the test that uses them.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The previous_league_id docblock states both absence spellings, and reading it safely does not require the caller to know about them
- [x] #2 The is_owner and is_bot docblocks describe what the API actually sends, without changing the true-only-when-true reading
- [x] #3 Recorded fixtures from a pre-draft league cover the orphan roster, the placeholder starters, the unplayed bracket and the unordered draft
- [x] #4 Fixtures recorded from a league that is still live carry a note saying so, and what to do when a refresh no longer matches
- [x] #5 Example scripts take a league and user ID from the environment, falling back to the public leagues they use today, with no new dependency
- [x] #6 composer fmt, refactor, lint, test, test:arch and test:mutate are all green
<!-- AC:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
The live pre-draft league disproved two documented claims and surfaced a third difference nobody had noticed. All three are now corrected and covered.

**`previous_league_id` has two spellings of "none".** The docblock claimed it is always `"0"`. Checked across 75 leagues: 55 send `null`, 5 send `"0"`. A caller testing the raw property against null would try to fetch league `"0"`. Added `League::previousSeason()`, which folds both into one absence, so walking a dynasty league's seasons backwards terminates either way.

**`is_bot` and `is_owner` do arrive as real booleans.** The docblock claimed neither is ever sent as `false` — true of the 2018 league, false of the 2026 one, which sends `is_bot: false` on ordinary members. The `bool()` reading ("true only when Sleeper says true") was already correct; only the stated reason was wrong. Both fixtures now assert the flags hydrate identically whether the API says `false` or says `null`.

**`slot_to_roster_id` depends on which route you ask through.** `/draft/{id}` sends it; `/league/{id}/drafts` omits the key entirely — for a finished draft as much as a pending one. Confirmed on both a 2019 and a 2026 draft. Anything reading the slot map off the list route would see an empty map and conclude the draft was never seeded. Documented on `Draft` and asserted both ways.

**Pre-draft states, now recorded rather than hand-written.** Five new fixtures from league `1399461312144084992`:
- An undrafted roster's `starters` is ten literal `"0"` placeholder IDs, not an empty array. Left in place rather than filtered, because stripping them would mean this DTO deciding `"0"` can never be a real player ID.
- Nine of twelve rosters have `owner_id: null`. Orphan teams were modelled from the start but only ever exercised against a hand-written payload.
- The same list fields that arrive as `null` in 2018 arrive as `[]` in 2026 — sometimes within one response. Both read as an empty list, which is why they are not typed nullable.
- Playoff brackets are fully seeded before a ball is thrown, including a bye where one slot holds a roster ID and the other holds only a `_from` reference. The unplayed-bracket shape had synthetic coverage only.
- An auction draft with `draft_order: null`, and `settings` carrying `budget` and `nomination_timer` — keys a snake draft has no use for, which is exactly why `settings` is a map rather than fixed properties.
- Three members against twelve rosters: the opposite direction from the 2018 league's fourteen-against-twelve, so members and teams fail to correspond both ways round.
- A member who uploaded a picture gets a `metadata.avatar` that is already an absolute `sleepercdn.com/uploads/…` URL, distinct from the hex `avatar` ID beside it. Both resolve; only the hex one goes through `Avatar`, and putting the metadata value through it would double the host.

Each fixture recorded from the still-live league carries a note on its constant saying so, and what to do when a refresh stops matching: re-point at another pre-draft league rather than relaxing the assertion.

**Examples take their IDs from the environment.** `examples/ids.php` loads `.env` via `vlucas/phpdotenv` (dev-only, and the same thing the sibling fantasypros examples do) and falls back to the public leagues. Exported variables win over the file. devenv's own `dotenv.enable` was tried first and rejected — it requires the C-Nix CLI and errors out through the flake integration, taking the whole shell with it; `dotenv.disableHint` is set instead so the unused-file hint stays quiet.

The test suite still reads no environment beyond `FANTASY_FIXTURES`. Verified `composer test` passes with `.env` moved away.

Gate green: 283 tests, 1,602 assertions, 0 deptrac violations, 336 mutants at 100% MSI. All nine examples run against both the default public leagues and the live one.
<!-- SECTION:FINAL_SUMMARY:END -->
