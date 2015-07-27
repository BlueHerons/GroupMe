<?php
namespace BlueHerons\GroupMe\Bots;

class SmurflingBot extends CommandBot {

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);
        $this->registerCommand("lessons",      array($this, "lessons_link"),      "Posts link to Smurfling Lessons");
    }

    public function lessons_link() {
        return sprintf("Smurfling lessons an be found at %s. These infographics are a great supplement to in-game training.", "http://blueheronsresistance.com/guide/lessons");
    }
}
?>
