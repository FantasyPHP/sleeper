---
id: SLEEPER-3
title: 'Users endpoints: GET /user/{username} and GET /user/{user_id}'
status: Done
assignee: []
created_date: '2026-08-29 16:10'
updated_date: '2026-08-29 16:43'
labels: []
dependencies:
  - SLEEPER-2
modified_files:
  - src/Concerns/SupportsUserEndpoints.php
  - src/Requests/GetUserRequest.php
  - src/Data/Api/User.php
  - src/Data/Api/Avatar.php
  - src/SleeperConnector.php
  - tests/Requests/GetUserRequestTest.php
  - examples/user.php
priority: high
ordinal: 3000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
One shape, two lookups. Both routes return the same user object, so they share a DTO and differ only in what goes in the path segment.

The miss behaviour is not what the docs imply and has been verified against the live API: an unknown *username* comes back as `200` with a literal `null` body, while an unknown *user_id* on the leagues route comes back as `404`. Model what is observed — the lookup returns a nullable user rather than throwing, and `Payload::fromResponse()` cannot be used unguarded because a `null` body is not an object.

Avatars are not Saloon requests: they live on `https://sleepercdn.com/avatars/<avatar_id>` with thumbnails under `/avatars/thumbs/<avatar_id>`, so they belong on the DTO as URL builders.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A SupportsUserEndpoints trait exposes both lookups, hydrating through the request's createDtoFromResponse
- [x] #2 A username that does not exist returns null rather than raising, matching the 200-with-null-body the API actually sends
- [x] #3 A recorded fixture hydrates every mapped field of the user DTO
- [x] #4 The user DTO exposes full-size and thumbnail avatar URLs against sleepercdn.com, returning null when the user has no avatar
- [x] #5 examples/user.php runs and prints something a human would want to read
- [x] #6 composer fmt, refactor, lint, test, test:arch and test:mutate are all green
<!-- AC:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
The user lookup is in, with two findings that changed the design.

**The docs list two endpoints; there is one.** `/user/{username}` and `/user/{user_id}` are the same route — the path segment accepts either form and the API does not care which it got. So there is one `GetUserRequest` and one `user(string $usernameOrId)` method rather than two identical pairs.

**A miss is a 200, not a 404.** Verified against the live API for both a username nobody has and a user ID nobody has: the response is `200` with a body of literal `null`. That is why `user()` returns `?User` and why `Payload::fromResponseOrNull()` exists. It matches the raw body rather than a decode, so "the API said null" stays distinguishable from "the API said something unreadable" — a fixture asserts that `"null"` (the quoted string) still raises.

**The payload is mostly a shape, not data.** Twenty-one keys, eighteen of them always null: `email`, `phone`, `real_name`, `token` and the rest are stripped because the endpoint is public. Only `user_id`, `username`, `display_name`, `avatar` and `is_bot` ever carry anything, and `is_bot` arrives as `false` for some accounts and `null` for others with no difference in meaning, so it is read as "true only when Sleeper says true".

**Avatars.** `Avatar` is a small value object rather than a pair of methods duplicated across three DTOs (`User`, and later `LeagueUser` and `League` all carry one). It holds the one fact that matters — the images are on `sleepercdn.com`, not the API host — in one place. Note the ID is not always a hex string: Sleeper's own bot account carries `sleeperbot_black.png`, so nothing validates the shape.

Gate green: 129 tests, 0 deptrac violations, 100% MSI.
<!-- SECTION:FINAL_SUMMARY:END -->
