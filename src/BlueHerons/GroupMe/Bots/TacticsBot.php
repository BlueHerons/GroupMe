<?php
namespace BlueHerons\GroupMe\Bots;

class TacticsBot extends BaseBot {

    protected $group_id;

    public function __construct($token) {
        parent::__construct($token, "no_bot_id");
    }

    public function listen() {
    }

    public function buttonPress($room, $message) {
        $this->group_id = $room;
        $this->sendGroupMessage($message, $room);
    }
}

?>
