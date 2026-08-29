<?php

declare(strict_types=1);

use Fantasyphp\Sleeper\SleeperConnector;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/ids.php';

$connector = new SleeperConnector;

/**
 * GET /user/{username} or GET /user/{user_id}: one Sleeper account.
 *
 * The same path segment takes either, so a lookup by handle and a lookup by ID
 * are the same call. Store the ID: a username belongs to whoever holds it
 * today.
 */
$lookups = ['sleeperbot', sleeper_user_id(), 'thisuserdoesnotexist999zzz'];

foreach ($lookups as $lookup) {
    $user = $connector->user($lookup);

    // Not a 404. Sleeper answers a miss with 200 and a body of literal null,
    // so an unknown handle is an absence rather than a failure.
    if (! $user instanceof Fantasyphp\Sleeper\Data\Api\User) {
        printf("%-26s no such account\n", $lookup);

        continue;
    }

    printf(
        "%-26s %s (@%s, id %s)%s\n",
        $lookup,
        $user->displayName,
        $user->username,
        $user->userId,
        $user->isBot ? ' [bot]' : '',
    );

    // Avatars are served from sleepercdn.com, not from the API host.
    if ($user->avatar instanceof Fantasyphp\Sleeper\Data\Api\Avatar) {
        printf("%-26s   %s\n", '', $user->avatar->thumbnailUrl());
    }
}
