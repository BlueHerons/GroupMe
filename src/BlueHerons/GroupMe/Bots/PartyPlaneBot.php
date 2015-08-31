<?php
namespace BlueHerons\GroupMe\Bots;

class PartyPlaneBot extends EventBot {

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);

        parent::registerHandler(EventBot::MEMBER_ADDED, array($this, "onMemberAdded"));
    }

    public function onMemberAdded($data) {
        $this->sendMessage(sprintf("Welcome @%s", $data['who']->nickname));
        $this->sendMessage("RULES FOR NOMINATIONS
---------------------

1. Only mods add people to the chat.
2. Nominate a person if you have met them in real life.
3. Heart a nomination if you've met a person in real life.
4. After 3 vouches (nomination + 2 hearts) a mod will add.

FORM = 
   Nomination: [a name we can recognize]{area they play in, or tag those that have also met}

!!!  Any adds by non-mods will be removed  !!");
        $this->sendMessage("Guideline for posting:  Let's keep the plane SFW.  If you're not sure if it is SFW, maybe share it not in the plane, but somewhere else.");
        $this->sendMessage("MODERATORS
----------
Christyan (onlineannoyance)
John (CaptCynicism)
Jackie (FinneganJax)
Ranee (AoD42)
Sarah (sahararomeo)
Snare (Snare theMonkey)");
    }
}
?>