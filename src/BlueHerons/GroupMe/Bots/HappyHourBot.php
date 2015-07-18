<?php
namespace BlueHerons\GroupMe\Bots;

use \BlueHerons\Cycle\Cycle;
use \DateTime;

class HappyHourBot extends CommandBot {

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);
        $this->registerCommand("cycle",      array($this, "next_cycle"),      "Show next checkpoint");
        $this->registerCommand("checkpoint", array($this, "next_checkpoint"), "Show next cycle");
    }

    public function next_checkpoint() {
        $next = Cycle::getNextCheckpoint();
        return sprintf("Next checkpoint at %s. (%s)",
                       $next->format("g A"),
                       $next->diff(new DateTime())->format("%h hours, %i mins"));
    }

    public function next_cycle() {
        $next = Cycle::getNextCycleStart();
         return sprintf("Next septicycle starts on %s. (%s)",
                       $next->format("l, F j \a\\t g A"),
                       $next->diff(new DateTime())->format("%d days, %h hours, %i mins"));
    }
}
?>
