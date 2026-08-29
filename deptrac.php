<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $config
        ->paths('./src')
        ->layers(
            $connector = Layer::withName('connector')->collectors(
                ClassLikeConfig::create(Fantasyphp\Sleeper\SleeperConnector::class),
                DirectoryConfig::create('Concerns'),
            ),
            $requests = Layer::withName('requests')->collectors(
                DirectoryConfig::create('Requests'),
            ),
            $apiData = Layer::withName('data.api')->collectors(
                DirectoryConfig::create('Data/Api'),
            ),
            $contracts = Layer::withName('data.contracts')->collectors(
                DirectoryConfig::create('Data/Contracts'),
            ),
            $infrastructure = Layer::withName('data.infrastructure')->collectors(
                DirectoryConfig::create('Data/Infrastructure'),
            ),
            $enums = Layer::withName('enums')->collectors(
                DirectoryConfig::create('Enums'),
            ),
            $exceptions = Layer::withName('exceptions')->collectors(
                DirectoryConfig::create('Exceptions'),
            ),
        )
        ->rulesets(
            Ruleset::forLayer($connector)->accesses(
                $requests, $apiData, $contracts, $infrastructure, $enums, $exceptions,
            ),
            // Sleeper has no envelopes, so a request hydrates its DTO straight
            // out of the payload readers instead of handing a whole Response to
            // a wrapper type. That is the layer fantasypros has here and this
            // one does not; the direction of the arrows is unchanged.
            Ruleset::forLayer($requests)->accesses($apiData, $contracts, $infrastructure, $enums, $exceptions),
            Ruleset::forLayer($apiData)->accesses($contracts, $infrastructure, $enums, $exceptions),
            Ruleset::forLayer($contracts)->accesses($infrastructure),
            Ruleset::forLayer($infrastructure)->accesses($exceptions),
            Ruleset::forLayer($enums),
            Ruleset::forLayer($exceptions),
        );
};
