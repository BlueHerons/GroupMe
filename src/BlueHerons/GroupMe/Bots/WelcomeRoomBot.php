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
        //$this->sendMessage(sprintf("The groups %s was changed to \"%s\" by @%s.", $data['change'], $data['what'], $data['who']));
    }

    public function onMemberAdded($data) {
        $this->sendMessage(sprintf("Thanks, %s.\n\nWelcome, %s! Please let us know your agent name and where you play.\n\nThe purpose of this group is to get you connected with other local players in your area.", $data['by'], $data['who']));
    }

    public function onMemberRemoved($data) {
        //$this->sendMessage(sprintf("Goodbye %s!", $data['removee']));
    }

    public function onMemberJoined($data) {
        $this->sendMessage(sprintf("Welcome, %s! Please let us know your agent name and where you play.\n\nThe purpose of this group is to get you connected with other local players in your area.", $data['who']));
    }

    public function onMemberLeft($data) {
        //$this->sendMessage(sprintf("Goodbye %s!", $data['who']));
    }

    public function onMemberRejoined($data) {
        $this->sendMessage(sprintf("Welcome back, %s!", $data['who']));
    }
    
    public function onMemberNameChange($data) {
        //$this->sendMessage(sprintf("I've recorded %s's name change to %s", $data['who'], $data['what']));
    }

    public function onOfficeModeChanged($data) {
        //$this->sendMessage(sprintf("Room notifications have been %s", $data['what']));
    }

    public function onEventRSVP($data) {
        //$this->sendMessage(sprintf("%s RSVP'd \"%s\" to \"%s\"", $data['who'], $data['rsvp'], $data['what']));
    }

    public function onEventChanged($data) {
        //$this->sendMessage(sprintf("%s changed the %s for the event \"%s\"", $data['who'], $data['change'], $data['what']));
    }

    public function onEventCanceled($data) {
        //$this->sendMessage(sprintf("%s canceled \"%s\"", $data['who'], $data['what']));
    }
}
?>
