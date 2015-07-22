<?php
namespace BlueHerons\GroupMe\Bots;

class WelcomeRoomBot extends EventBot {

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);

        parent::registerHandler(EventBot::GROUP_CHANGED, array($this, "onGroupChanged"));
        parent::registerHandler(EventBot::MEMBER_ADDED, array($this, "onMemberAdded"));
        parent::registerHandler(EventBot::MEMBER_REMOVED, array($this, "onMemberRemoved"));
        parent::registerHandler(EventBot::MEMBER_JOINED, array($this, "onMemberJoined"));
        parent::registerHandler(EventBot::MEMBER_LEFT, array($this, "onMemberLeft"));
        parent::registerHandler(EventBot::MEMBER_REJOINED, array($this, "onMemberRejoined"));
        parent::registerHandler(EventBot::MEMBER_CHANGED_NAME, array($this, "onMemberNameChange"));
        parent::registerHandler(EventBot::OFFICE_MODE_CHANGED, array($this, "onOfficeModeChanged"));
        parent::registerHandler(EventBot::EVENT_RSVP, array($this, "onEventRSVP"));
        parent::registerHandler(EventBot::EVENT_CHANGED, array($this, "onEventChanged"));
        parent::registerHandler(EventBot::EVENT_CANCELED, array($this, "onEventCanceled"));
    }

    public function onGroupChanged($data) {
    }

    public function onMemberAdded($data) {
        $this->sendMessage(sprintf("Thanks, @%s.\n\nWelcome, @%s! Please let us know your agent name and where you play.\n\nThe purpose of this group is to get you connected with other local players in your area.", $data['by'], $data['who']->nickname));
    }

    public function onMemberRemoved($data) {
    }

    public function onMemberJoined($data) {
        $this->sendMessage(sprintf("Welcome, @%s! Please let us know your agent name and where you play.\n\nThe purpose of this group is to get you connected with other local players in your area.", $data['who']->nickname));
    }

    public function onMemberLeft($data) {
    }

    public function onMemberRejoined($data) {
        $this->sendMessage(sprintf("Welcome back, @%s!", $data['who']->nickname));
    }
    
    public function onMemberNameChange($data) {
    }

    public function onOfficeModeChanged($data) {
        if ($data['what'] != "enabled") {
            $this->sendMessage(sprintf("If you dont mind, @%s, I'm going to turn that back on so liason agents will get notifications from this room.\n\nThe \"Office Mode\" setting affects notifications for everyone in the group. To disable notifications for you only, use the \"mute\" option, or check out this tutorial: %s", $data['who']->nickname, "http://blueheronsresistance.com/faq/disable-notifications-in-groupme"));
            $this->enableNotifications();
        }
    }

    public function onEventRSVP($data) {
    }

    public function onEventChanged($data) {
    }

    public function onEventCanceled($data) {
    }
}
?>
