# Sleeper PHP

A PHP client for the [Sleeper API](https://docs.sleeper.com): leagues, rosters, drafts,
players and transactions. Built on [Saloon](https://docs.saloon.dev).

You call methods on a connector and get back typed objects. No `Request` classes to
construct, no `->dto()`, no `assert()` to convince your static analyser:

```php
$connector = new SleeperConnector;

$league = $connector->league('289646328504385536');
$rosters = $connector->rosters($league->leagueId);
```

There is nothing to configure. Sleeper's read API performs no authentication at all — no
key, no header, no account.

## Before you start

**The API is free for personal, non-commercial use only.** Sleeper's terms say commercial
use needs a separate arrangement with them, so if this is going into something you charge
for, [talk to Sleeper first](https://docs.sleeper.com). Nothing in this package changes
that.

**Stay under about 1000 calls a minute.** Sleeper documents that ceiling and warns that
crossing it risks an IP block, which is considerably harder to recover from than a 429.
The connector retries transient failures for you; that is not a licence to hammer it.

**Do not fetch the whole player map more than once a day.** `/players/nfl` unfiltered is
roughly 5MB across twelve thousand players and the docs ask you to treat it as a daily
download rather than a lookup. This client makes that call awkward on purpose — see
[Players](#players).

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Endpoints](#endpoints)
  - [State](#state)
  - [Users](#users)
  - [Leagues](#leagues)
  - [Drafts](#drafts)
  - [Players](#players)
- [Avatars](#avatars)
- [Handling failures](#handling-failures)
- [Quirks worth knowing about](#quirks-worth-knowing-about)
- [Contributing](#contributing)
- [License](#license)

## Requirements

PHP 8.5 or newer.

## Installation

```bash
composer require fantasyphp/sleeper
```

```php
use Fantasyphp\Sleeper\SleeperConnector;

$connector = new SleeperConnector;
```

## Endpoints

Every example below is a trimmed version of a runnable script in [`examples/`](examples).
Those run against the live API, so if you want to poke at a response shape, start there.

They default to public leagues that have already finished. To point them at your own, drop
a `.env` at the repo root:

```dotenv
SLEEPER_LEAGUE_ID=1399461312144084992
SLEEPER_USER_ID=1399295919517605888
```

Neither is a secret — Sleeper takes no credentials and league IDs are public — but `.env`
is gitignored regardless. Anything already exported wins over the file. Note that a league
still in its preseason has less to show than a finished one: no matchups, no transactions
and no draft picks.

None of this touches the test suite, which reads no environment beyond `FANTASY_FIXTURES`.

### State

Which week and season a sport is currently in. The cheapest call Sleeper has, and worth
making before anything that takes a week or a season — week numbers restart at 1 in the
preseason, so "week 3" on its own does not say which week 3.

```php
$state = $connector->state(Sport::Nfl);

printf("%s season %s, week %d\n", $state->seasonType, $state->season, $state->week);
```

### Users

One account, by username or by numeric ID. The API accepts either in the same slot, so
this is one method rather than two.

```php
$user = $connector->user('sleeperbot');

if ($user === null) {
    // No such account. Not an exception -- see Quirks below.
}
```

Store the ID rather than the handle: a username belongs to whoever holds it today.

### Leagues

Nine routes, and none of them are joined up for you — because Sleeper does not join them
up either. Rosters know their `ownerId`, league members know their `userId`, and matchups
and brackets speak only in `rosterId`. Getting from a bracket to a team name goes through
all three.

```php
$leagues = $connector->leaguesForUser($userId, Sport::Nfl, '2025');
$league  = $connector->league($leagueId);
$rosters = $connector->rosters($leagueId);
$members = $connector->leagueUsers($leagueId);

$matchups = $connector->matchups($leagueId, week: 14);
$winners  = $connector->winnersBracket($leagueId);
$losers   = $connector->losersBracket($leagueId);

$moves = $connector->transactions($leagueId, round: 15);
$picks = $connector->tradedPicks($leagueId);
```

Standings, with the two-call join and the points already recombined:

```php
foreach ($rosters as $roster) {
    printf(
        "%d-%d-%d  %.2f for, %.2f against\n",
        $roster->settings->wins,
        $roster->settings->losses,
        $roster->settings->ties,
        $roster->settings->pointsFor,
        $roster->settings->pointsAgainst,
    );
}
```

`matchups()` returns a row per *team*, not per fixture. Two rows sharing a `matchupId` are
playing each other:

```php
$fixtures = [];

foreach ($matchups as $matchup) {
    // Null during the playoffs for every team that has been knocked out.
    if ($matchup->matchupId !== null) {
        $fixtures[$matchup->matchupId][] = $matchup;
    }
}
```

### Drafts

A league's `draftId` is only its most recent draft. A dynasty league holds one per season,
and `leagueDrafts()` is the only way to see the rest.

```php
$drafts = $connector->leagueDrafts($leagueId);
$draft  = $connector->draft($draftId);
$picks  = $connector->draftPicks($draftId);
$traded = $connector->draftTradedPicks($draftId);

$mine = $connector->draftsForUser($userId, Sport::Nfl, '2025');
```

Each pick carries a snapshot of the player as he was when it was made, so rendering an old
draft needs no second call:

```php
foreach ($picks as $pick) {
    printf(
        "%3d  %s (%s, %s)\n",
        $pick->pickNumber,
        $pick->player->name(),
        $pick->player->position ?? '-',
        $pick->player->team ?? 'FA',
    );
}
```

That snapshot is stale by design — the team is the one the player was on at the time.

### Players

`players()` takes the position as a **required** argument, so the 5MB unfiltered call
cannot happen by leaving something off:

```php
$kickers  = $connector->players(Sport::Nfl, NflPosition::Kicker);
$defenses = $connector->players(Sport::Nfl, NflPosition::TeamDefense);

foreach ($defenses as $defense) {
    // "DET", not a number. See Quirks below.
    printf("%s %s\n", $defense->playerId, $defense->name());
}
```

If you genuinely want the whole map, say so:

```php
$everyone = $connector->everyPlayer(Sport::Nfl);
```

Cache the result and drive everything else off the cache. This is a download, not a lookup.

Trending adds and drops across every Sleeper league:

```php
$trending = $connector->trendingPlayers(Sport::Nfl, TrendingType::Add, limit: 10);

foreach ($trending as $player) {
    printf("%s was added in %d leagues\n", $player->playerId, $player->count);
}
```

It returns IDs and counts and nothing else, so putting names to them means a player-map
lookup. Sleeper asks for attribution if you publish this data.

## Avatars

Avatars are not on the API host and are not requests this client makes. `User`,
`LeagueUser` and `League` each carry an `Avatar` that builds the two URLs:

```php
$user->avatar?->url();           // https://sleepercdn.com/avatars/<id>
$user->avatar?->thumbnailUrl();  // https://sleepercdn.com/avatars/thumbs/<id>
```

## Handling failures

Every HTTP failure arrives as a Sleeper type, so nothing Saloon-shaped leaks out of a
connector method:

```php
use Fantasyphp\Sleeper\Exceptions\NotFoundException;
use Fantasyphp\Sleeper\Exceptions\RateLimitException;
use Fantasyphp\Sleeper\Exceptions\SleeperRequestException;

try {
    $league = $connector->league($leagueId);
} catch (NotFoundException $e) {
    // No league under that ID.
} catch (RateLimitException $e) {
    // Too many calls. The connector already retried three times with backoff.
} catch (SleeperRequestException $e) {
    // Anything else the API returned.
}
```

429s and 5xx responses are retried three times with exponential backoff. A 404 is not: an
ID that does not exist will not start existing.

There is no authentication failure to catch, because there is no authentication.

## Quirks worth knowing about

These cost real debugging time, so they are worth stating up front. Every one of them was
found by recording a real response, not by reading the docs.

**A missing user is a 200, not a 404.** `/user/{name}` answers `200` with a body of literal
`null` for a username nobody has — and for a user ID nobody has. That is why `user()`
returns `?User` rather than throwing. A missing *league* or *draft*, by contrast, is a
genuine 404.

**Bad filters do not fail either.** A position nobody plays answers `200 {}`; a trending
type nobody recognises answers `200 []`; an unknown sport answers `200 null`. A typo looks
like a quiet day rather than a mistake, which is why the filters here are enums.

**Player IDs are strings, and not always numeric.** A team defense's ID is its team
abbreviation — `"DET"` sits in the same `players` array as `"4035"`. Casting to int loses
every defense in silence.

**Points arrive in two halves.** A roster's `fpts: 1776` and `fpts_decimal: 6` mean
1776.06 — hundredths, not tenths. `RosterSettings` recombines them, so `pointsFor` is a
single float. Weekly matchup points, confusingly, arrive as one number already.

**The docs print one route wrong.** The playoff bracket section's `HTTP Request` line says
`loses_bracket`. That route does not exist and answers 404 with an HTML page. The working
one is `losers_bracket`, which is what `losersBracket()` calls.

**Timestamps are milliseconds.** `Transaction::$created` and `Draft::$startTime` are epoch
*milliseconds*. Handing either to a date function directly lands you tens of thousands of
years out; use `createdAt()` and `startsAt()`.

**The position filter matches fantasy eligibility.** `?position=LEO` returns players whose
listed `position` is `DE`, because LEO is a slot they are eligible for. And `active` only
responds to the literal `true` — `active=false` is ignored, so there is no way to ask for
only the retired players.

**A league has as many members as it has people, not teams.** Twelve rosters can be shared
between fourteen members, and more than one of them can be a commissioner. Zipping members
to rosters one-for-one silently drops the difference.

**The same field is not always the same type.** `previous_season` is quoted for NFL and
unquoted for MLB. `bracket_id` is unquoted where every sibling ID is a string. Every value
in a draft pick's `metadata` is a string, including the numbers that are integers on the
same player elsewhere. All of it is read through one coercing payload reader so you see one
type per field.

**Half of `/state/{sport}` is optional.** `/state/nfl` sends ten fields; `/state/wnba`
sends five. `season` is not always a year — `/state/lcs` answers `"2022-summer"`. And
`season_type` has an undocumented fourth value, `off`.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for the toolchain, the quality gate and how
fixtures work.

## License

MIT. See [LICENSE](LICENSE).
