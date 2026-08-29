---
id: SLEEPER-7
title: README and release housekeeping
status: Done
assignee: []
created_date: '2026-08-29 16:11'
updated_date: '2026-08-29 16:59'
labels: []
dependencies:
  - SLEEPER-6
modified_files:
  - README.md
  - PORTING.md
  - KICKSTART.md
priority: medium
ordinal: 7000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The public front door for the package, written once the endpoint surface is real rather than guessed at.

Sleeper's API is free for personal, non-commercial use only — the docs state that commercial use needs a separate licensing arrangement with Sleeper. That belongs near the top of the README, not buried in a footnote. The ~1000 calls/minute ceiling and the once-a-day guidance for the full player map belong there too, since both are things a reader will otherwise learn by being IP-blocked.

`PORTING.md` and `KICKSTART.md` are scaffolding notes for building the client and should not outlive it.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 README covers installation, a first request, and one worked example per endpoint group
- [x] #2 The non-commercial licensing restriction is stated prominently rather than buried
- [x] #3 The rate limit and the once-a-day guidance for the full player map are documented where a reader will see them before hitting either
- [x] #4 PORTING.md and KICKSTART.md are removed
- [x] #5 composer fmt, refactor, lint, test, test:arch and test:mutate are all green
<!-- AC:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
README written, scaffolding notes removed.

The three operational constraints sit in a "Before you start" section above the table of contents, so a reader hits them before the first code sample: the non-commercial licensing restriction (with a pointer to talk to Sleeper directly for anything commercial), the ~1000 calls/minute ceiling and its IP-block consequence, and the once-a-day guidance for the full player map.

One worked example per endpoint group — state, users, leagues, drafts, players — each a trimmed version of a runnable script in `examples/`. Plus sections on avatars (the different host), handling failures (three catch clauses, and a note that there is no authentication failure to catch), and a "Quirks worth knowing about" list of the ten findings that cost real time: the 200-with-null miss, filters that fail silently, string player IDs, split points, the mis-documented bracket route, millisecond timestamps, the fantasy-eligibility position filter, members outnumbering rosters, fields that change JSON type between sports, and how much of `/state/{sport}` is optional.

`PORTING.md` and `KICKSTART.md` are deleted. `CONTRIBUTING.md` was checked and needed no change — its layering description ("`requests` may reach `data`") already matched what shipped.

Left alone deliberately: the empty `docs/` directory (its `.gitattributes` export-ignore entry still points at it, and touching one means touching the other), and `CHANGELOG.md`, which git-cliff generates from tags and there are none yet.

Gate green, including the two non-mutating variants CI runs: `fmt:check`, `refactor:check`, `lint`, `test` (268 tests, 1,419 assertions), `test:arch` (0 violations), `test:mutate` (334 mutants, 100% MSI). All nine example scripts run against the live API.
<!-- SECTION:FINAL_SUMMARY:END -->
