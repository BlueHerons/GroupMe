<?php
namespace BlueHerons\GroupMe\Bots;

class SPSRBot extends EventBot {

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);

        parent::registerHandler(EventBot::MEMBER_ADDED, array($this, "checkForAutoKick"));
        parent::registerHandler(EventBot::MEMBER_JOINED, array($this, "checkForAutoKick"));
        parent::registerHandler(EventBot::MEMBER_REJOINED, array($this, "checkForAutoKick"));
    }

    public function checkForAutoKick($data) {
        if (isset($this->config->autokick) && is_array($this->config->autokick)) {
            if (in_array($data['who']->user_id, $this->config->autokick)) {
                $this->logger->info(sprintf("%s is marked for auto-kick", $data['who']->nickname));
                $this->removeMember($data['who']->user_id);
            }
        }
        else {
            $this->config->autokick = array();
            $this->saveConfig();
        }
    }
}
?>
