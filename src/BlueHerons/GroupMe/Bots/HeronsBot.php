<?php
namespace BlueHerons\GroupMe\Bots;

use \BlueHerons\Cycle\Cycle;
use \DateTime;

class HeronsBot extends CommandBot {

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);
        $this->registerCommand("checkpoint", array($this, "next_checkpoint"),   "Show next cycle");
        $this->registerCommand("cycle",      array($this, "next_cycle"),        "Show next checkpoint");
        $this->registerCommand("lessons",    array($this, "smurfling_lessons"), "Smurfling Lessons link");

        // button should only be registered if configured
        if (isset($this->config->button)) {
            $this->registerCommand("button", array($this, "smash_button"), "Button link");
        }
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

    public function smash_button() {
        $button = $this->config->button;
        return sprintf("%s button: %s", $button->name, $button->url);
    }

    public function smurfling_lessons() {
        return sprintf("Smurfling lessons can be found at %s. These infographics are a great supplement to in-game training.", "http://blueheronsresistance.com/guide/lessons");
    }

}
?>
