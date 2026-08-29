<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Concerns;

use Fantasyphp\Sleeper\Data\Api\BracketMatchup;
use Fantasyphp\Sleeper\Data\Api\League;
use Fantasyphp\Sleeper\Data\Api\LeagueUser;
use Fantasyphp\Sleeper\Data\Api\Matchup;
use Fantasyphp\Sleeper\Data\Api\Roster;
use Fantasyphp\Sleeper\Data\Api\TradedPick;
use Fantasyphp\Sleeper\Data\Api\Transaction;
use Fantasyphp\Sleeper\Enums\BracketType;
use Fantasyphp\Sleeper\Enums\Sport;
use Fantasyphp\Sleeper\Requests\GetLeagueRequest;
use Fantasyphp\Sleeper\Requests\GetLeaguesForUserRequest;
use Fantasyphp\Sleeper\Requests\GetLeagueUsersRequest;
use Fantasyphp\Sleeper\Requests\GetMatchupsRequest;
use Fantasyphp\Sleeper\Requests\GetPlayoffBracketRequest;
use Fantasyphp\Sleeper\Requests\GetRostersRequest;
use Fantasyphp\Sleeper\Requests\GetTradedPicksRequest;
use Fantasyphp\Sleeper\Requests\GetTransactionsRequest;
use Saloon\Http\Connector;

/**
 * The league endpoints, in the API's own vocabulary.
 *
 * Every method here hydrates through the request's `createDtoFromResponse`
 * rather than `Response::dto()`, which is typed `mixed` and would erase the
 * DTO type at the call site.
 *
 * Nothing in this group is joined up for you, because Sleeper does not join it
 * up either: rosters, league members and matchups are three calls returning
 * three views keyed by two different IDs. A roster carries `ownerId` (a user)
 * and `rosterId` (a team); matchups and brackets speak only in roster IDs;
 * league members speak only in user IDs. Getting from a bracket to a team name
 * means going through both.
 *
 * @mixin Connector
 *
 * @phpstan-require-extends Connector
 */
trait SupportsLeagueEndpoints
{
    /**
     * GET /user/{user_id}/leagues/{sport}/{season}: every league an account is
     * in for one season.
     *
     * Takes the numeric user ID rather than the username; a username answers
     * 404 here even though `user()` accepts one.
     *
     * @return list<League>
     */
    public function leaguesForUser(string $userId, Sport $sport, string $season): array
    {
        $request = new GetLeaguesForUserRequest($userId, $sport, $season);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /league/{league_id}: one league's configuration.
     */
    public function league(string $leagueId): League
    {
        $request = new GetLeagueRequest($leagueId);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /league/{league_id}/rosters: every team's squad and season record.
     *
     * @return list<Roster>
     */
    public function rosters(string $leagueId): array
    {
        $request = new GetRostersRequest($leagueId);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /league/{league_id}/users: every member of a league.
     *
     * @return list<LeagueUser>
     */
    public function leagueUsers(string $leagueId): array
    {
        $request = new GetLeagueUsersRequest($leagueId);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /league/{league_id}/matchups/{week}: one week, one row per team.
     *
     * Two rows sharing a `matchupId` are playing each other. A row with none is
     * a team with no fixture that week, which is what the playoffs leave.
     *
     * @return list<Matchup>
     */
    public function matchups(string $leagueId, int $week): array
    {
        $request = new GetMatchupsRequest($leagueId, $week);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /league/{league_id}/winners_bracket: the championship bracket.
     *
     * @return list<BracketMatchup>
     */
    public function winnersBracket(string $leagueId): array
    {
        return $this->playoffBracket($leagueId, BracketType::Winners);
    }

    /**
     * GET /league/{league_id}/losers_bracket: the consolation bracket.
     *
     * Note the route: the docs' `HTTP Request` line says `loses_bracket`, which
     * 404s. `losers_bracket` is what works and what their own example calls.
     *
     * @return list<BracketMatchup>
     */
    public function losersBracket(string $leagueId): array
    {
        return $this->playoffBracket($leagueId, BracketType::Losers);
    }

    /**
     * GET /league/{league_id}/transactions/{round}: every move made in one week.
     *
     * Failed claims are included. There is no whole-season variant, so a
     * league's history is one call per week.
     *
     * @return list<Transaction>
     */
    public function transactions(string $leagueId, int $round): array
    {
        $request = new GetTransactionsRequest($leagueId, $round);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * GET /league/{league_id}/traded_picks: every pick that has changed hands,
     * including ones for seasons that have not happened yet.
     *
     * @return list<TradedPick>
     */
    public function tradedPicks(string $leagueId): array
    {
        $request = new GetTradedPicksRequest($leagueId);

        return $request->createDtoFromResponse($this->send($request));
    }

    /**
     * @return list<BracketMatchup>
     */
    private function playoffBracket(string $leagueId, BracketType $bracket): array
    {
        $request = new GetPlayoffBracketRequest($leagueId, $bracket);

        return $request->createDtoFromResponse($this->send($request));
    }
}
