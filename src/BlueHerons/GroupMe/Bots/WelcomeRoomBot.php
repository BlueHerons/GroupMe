<?php
namespace BlueHerons\GroupMe\Bots;

class WelcomeRoomBot extends HeronsBot {

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);

        parent::registerHandler(EventBot::GROUP_CHANGED, array($this, "onGroupChanged"));
        parent::registerHandler(EventBot::MEMBER_ADDED, array($this, "onMemberAdded"));
        parent::registerHandler(EventBot::MEMBER_JOINED, array($this, "onMemberJoined"));
        parent::registerHandler(EventBot::MEMBER_REJOINED, array($this, "onMemberRejoined"));
        parent::registerHandler(EventBot::OFFICE_MODE_CHANGED, array($this, "onOfficeModeChanged"));
    }

    public function onGroupChanged($data) {
        if ($data['change'] == "avatar" && $data['who']->user_id != $this->getUserID()) {
            $this->sendMessage(sprintf("@%s, I think you meant to change your personal avatar, not the group avatar, so I will put the old image back.", $data['who']->nickname));
            $this->changeGroupImage("http://i.groupme.com/960x877.png.3a4569dc0a734a1da5d5090390e0d83c");
        }
    }

    public function onMemberAdded($data) {
        $this->sendMessage(sprintf("Thanks, @%s.\n\nWelcome, @%s! Please let us know your agent name and where you play.\n\nThe purpose of this group is to get you connected with other local players in your area.", $data['by']->nickname, $data['who']->nickname));
    }

    public function onMemberJoined($data) {
        $this->sendMessage(sprintf("Welcome, @%s! Please let us know your agent name and where you play.\n\nThe purpose of this group is to get you connected with other local players in your area.", $data['who']->nickname));
    }

    public function onMemberRejoined($data) {
        $this->sendMessage(sprintf("Welcome back, @%s!", $data['who']->nickname));
    }
    
    public function onOfficeModeChanged($data) {
        if ($data['what'] != "enabled") {
            $this->sendMessage(sprintf("If you dont mind, @%s, I'm going to turn that back on so liason agents will get notifications from this room.\n\nThe \"Office Mode\" setting affects notifications for everyone in the group. To disable notifications for you only, use the \"mute\" option, or check out this tutorial: %s", $data['who']->nickname, "http://blueheronsresistance.com/faq/disable-notifications-in-groupme"));
            $this->enableNotifications();
        }
    }
}
?>
