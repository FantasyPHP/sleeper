<?php

declare(strict_types=1);

namespace Fantasyphp\Sleeper\Enums;

/**
 * Where a draft is in its own life cycle.
 *
 * A draft that has not started returns an empty `/picks` array rather than a
 * 404, so this is the field that tells the two apart.
 */
enum DraftStatus: string
{
    case PreDraft = 'pre_draft';

    case Drafting = 'drafting';

    case Paused = 'paused';

    case Complete = 'complete';
}
