<?php
namespace BlueHerons\GroupMe\Bots;

class WelcomeRoomBot extends EventBot {

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
            $this->sendMessage(sprintf("@%s, I think you meant to change your personal avatar, not the group avatar, so I will put the old image back. To change your personal avatar, check out <url>", $data['who']->nickname));
            $this->changeGroupImage("https://camo.githubusercontent.com/222c6a021b280859b439c4e69ca6c05ef28c85fe/687474703a2f2f6d6164656972612e686363616e65742e6f72672f70726f6a656374322f6d696368656c735f70322f77656273697465253230706963732f62656e6465722e6a7067");
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
