<?php

declare(strict_types=1);

use Fantasyphp\Sleeper\Tests\FixtureMode;
use Saloon\MockConfig;

require_once __DIR__.'/../vendor/autoload.php';

MockConfig::setFixturePath(__DIR__.'/Fixtures/Saloon');

if (FixtureMode::current() === FixtureMode::Strict) {
    // Offline. A missing fixture has to fail loudly, otherwise Saloon
    // quietly reaches for the live API to record one.
    MockConfig::throwOnMissingFixtures();
}
