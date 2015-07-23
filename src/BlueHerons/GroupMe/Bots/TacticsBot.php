<?php
namespace BlueHerons\GroupMe\Bots;

class TacticsBot extends BaseBot {

    protected $group_id;

    public function __construct($token) {
        parent::__construct($token, "no_bot_id");
    }

    public function broadcast($group_id, $message) {
        $this->group_id = $group_id;
        $this->sendMessage($message);
    }

    public function listen() {
    }

    protected function getGroupID() {
        return $this->group_id;
    }
}

?>
