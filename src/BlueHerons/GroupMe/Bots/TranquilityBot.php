<?php
namespace BlueHerons\GroupMe\Bots;

class TranquilityBot extends CommandBot {

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);
        $this->registerCommand("button",      array($this, "button_link"),      "Posts link to the button");
    }

    public function button_link() {
        return sprintf("Broadway Button: %s", "http://blueheronsresistance.com/broadway-timer/");
    }
}
?>
