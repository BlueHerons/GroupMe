<?php

namespace BlueHerons\Cycle;

use \DateTime;
use \DateInterval;

class Cycle {

    const CHECKPOINT_LENGTH = 18000;
    const CHECKPOINTS_IN_CYCLE = 35;
    const CYCLE_LENGTH = 630000;

    public static function getLastCheckpoint() {
        $ts = self::CHECKPOINT_LENGTH * floor( time() / self::CHECKPOINT_LENGTH );
        $cp = new DateTime();
        $cp->setTimestamp($ts);
        return $cp;
    }

    public static function getNextCheckpoint() {
        return self::getLastCheckpoint()->add(new DateInterval("PT5H"));
    }

    public static function getLastCycleStart() {
        $ts = self::CYCLE_LENGTH * floor( time() / self::CYCLE_LENGTH );
        $cy = new DateTime();
        $cy->setTimestamp($ts);
        return $cy;
    }

    public static function getNextCycleStart() {
        return self::getLastCycleStart()->add(new DateInterval("PT175H"));
    }
}
