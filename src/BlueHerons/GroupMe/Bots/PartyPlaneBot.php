<?php
namespace BlueHerons\GroupMe\Bots;

class PartyPlaneBot extends HeronsBot {

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);

        parent::unregisterCommand("alertme");
        parent::unregisterCommand("broadcast");
        parent::unregisterCommand("config");
        parent::unregisterCommand("lessons");

        parent::registerHandler(EventBot::MEMBER_ADDED, array($this, "onMemberAdded"));
    }

    public function onMemberAdded($data) {
        $this->sendMessage(sprintf("Welcome @%s", $data['who']->nickname));
        $this->rules();
    }

    public function rules() {
        $rules = parent::rules();
        $rules = str_replace("{MODS}", $this->mods("", "\n", "MODERATORS\n----------\n"), $rules);

        $messages = explode("{BREAK}", $rules);
        foreach ($messages as $message) {
            $this->sendMessage($message);
        }
    }

}
?>
