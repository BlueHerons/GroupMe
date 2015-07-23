<?php
namespace BlueHerons\GroupMe\Bots;

class TacticsBot extends BaseBot {

    public function __construct($token) {
        parent::_construct($token, "no_bot_id");
    }

    public function broadcast($group_id, $message) {
        $this->sendMessage($message);
    }
}

?>
