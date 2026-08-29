<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Data\Api;

/**
 * A user's or league's avatar.
 *
 * Sleeper sends only the ID. The images themselves are not on the API host at
 * all -- they are served from `sleepercdn.com`, which is why fetching one is
 * not a request this client makes: there is no JSON to hydrate and no
 * `/v1` prefix to resolve against. This exists so the two URLs are built in
 * one place rather than sprintf'd into three DTOs.
 *
 * The ID is usually 32 hex characters, but not always -- Sleeper's own bot
 * account carries `sleeperbot_black.png`. Both forms resolve, so nothing here
 * validates the shape.
 */
final readonly class Avatar
{
    public const string BASE_URL = 'https://sleepercdn.com/avatars';

    public function __construct(public string $id) {}

    /**
     * The full-size image.
     */
    public function url(): string
    {
        return sprintf('%s/%s', self::BASE_URL, $this->id);
    }

    /**
     * The thumbnail, for anywhere a list of teams is being rendered.
     */
    public function thumbnailUrl(): string
    {
        return sprintf('%s/thumbs/%s', self::BASE_URL, $this->id);
    }
}
