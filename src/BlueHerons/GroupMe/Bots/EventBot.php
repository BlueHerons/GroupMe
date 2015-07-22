<?php
namespace BlueHerons\GroupMe\Bots;

abstract class EventBot extends BaseBot {

        const GROUP_CHANGED             = "group_changed";
        const MEMBER_ADDED              = "member_added";
        const MEMBER_REMOVED            = "member_removed";
        const MEMBER_JOINED             = "member_joined";
        const MEMBER_LEFT               = "member_left";
        const MEMBER_REJOINED           = "member_rejoined";
        const MEMBER_CHANGED_NAME       = "member_changed_name";
        const OFFICE_MODE_CHANGED       = "office_mode_changed";
        const EVENT_RSVP                = "event_rsvp";
        const EVENT_CHANGED             = "event_changed";
        const EVENT_CANCELED            = "event_canceled";
        const MESSAGE                   = "message";

        private $events = array(
            self::GROUP_CHANGED             => "/^(.*)( changed the ((topic) to:|group's (name) to|group's (avatar)) ?)(.*)?$/",
            self::MEMBER_ADDED              => "/^(.*)( added )(.*)( to the group)$/",
            self::MEMBER_REMOVED            => "/^(.*)( removed )(.*)( from the group\.)$/",
            self::MEMBER_JOINED             => "/^(.*)( has joined the group)$/",
            self::MEMBER_LEFT               => "/^(.*)( has left the group\.)$/",
            self::MEMBER_REJOINED           => "/^(.*)( has rejoined the group)$/",
            self::MEMBER_CHANGED_NAME       => "/^(.*)( changed name to )(.*)$/",
            self::OFFICE_MODE_CHANGED       => "/^(.*)( ((dis|en)abled) Office Mode. Messages (will|won't) buzz your phone\.)$/",
            self::EVENT_RSVP                => "/^(.*)(is (going|not going) to )'(.*)'$/",
            self::EVENT_CHANGED             => "/^(.*)( updated the (description|image url|name) for the event )'(.*)'$/",
            self::EVENT_CANCELED            => "/^(.*)( canceled )'(.*)'$/"
        );

        private $handlers = array();

        public function __construct($token, $bot_id) {
            parent::__construct($token, $bot_id);
        }

	public function listen() {
            if ($this->isSystemMessage()) {
                switch ($this->getSystemMessageType()) {
                    case self::GROUP_CHANGED:
                        $this->executeHandlers(self::GROUP_CHANGED, $this->parseGroupChangedMessage());
                        break;
                    case self::MEMBER_ADDED:
                        $this->executeHandlers(self::MEMBER_ADDED, $this->parseMemberAddedMessage());
                        break;
                    case self::MEMBER_REMOVED:
                        $this->executeHandlers(self::MEMBER_REMOVED, $this->parseMemberRemovedMessage());
                        break;
                    case self::MEMBER_JOINED:
                        $this->executeHandlers(self::MEMBER_JOINED, $this->parseMemberJoinedMessage());
                        break;
                    case self::MEMBER_LEFT:
                        $this->executeHandlers(self::MEMBER_LEFT, $this->parseMemberLeftMessage());
                        break;
                    case self::MEMBER_REJOINED:
                        $this->executeHandlers(self::MEMBER_REJOINED, $this->parseMemberRejoinedMessage());
                        break;
                    case self::MEMBER_CHANGED_NAME:
                        $this->executeHandlers(self::MEMBER_CHANGED_NAME, $this->parseMemberNameChangeMessage());
                        break;
                    case self::OFFICE_MODE_CHANGED:
                        $this->executeHandlers(self::OFFICE_MODE_CHANGED, $this->parseOfficeModeChangeMessage());
                        break;
                    case self::EVENT_RSVP:
                        $this->executeHandlers(self::EVENT_RSVP, $this->parseEventRSVPMessage());
                        break;
                    case self::EVENT_CHANGED:
                        $this->executeHandlers(self::EVENT_CHANGED, $this->parseEventChangedMessage());
                        break;
                    case self::EVENT_CANCELED:
                        $this->executeHandlers(self::EVENT_CANCELED, $this->parseEventCanceledMessage());
                        break;
                    default:
                        //$this->sendMessage($this->getSystemMessageType());
                        return;
                }
            }
            else {
            }
	}

        public function registerHandler($event, $callee) {
            $this->handlers[$event][] = $callee;    
        }

        private function executeHandlers($event, $data) {
            foreach ($this->handlers[$event] as $function) {
                call_user_func($function, $data);
            }
        }

        private function extractData($event) {
            $matches = array();
            preg_match($this->events[$event], $this->getMessage(), $matches);
            return array_values(array_filter($matches));
        }

        private function parseGroupChangedMessage() {
            $matches = $this->extractData(self::GROUP_CHANGED);
            $data = array(
                "event" => self::GROUP_CHANGED,
                "change" => $matches[4],
                "who" => $this->searchMemberByName($matches[1]),
                "what" => $matches[3] == "group's avatar" ? "url_unavailable" : $matches[5]
            );
            return $data;
        }

        private function parseMemberAddedMessage() {
            $matches = $this->extractData(self::MEMBER_ADDED);
            $data = array(
                "event" => self::MEMBER_ADDED,
                "who" => $this->searchMemberByName($matches[3]),
                "by" => $this->searchMemberByName($matches[1])
            );
            return $data;
        }

        private function parseMemberRemovedMessage() {
            $matches = $this->extractData(self::MEMBER_REMOVED);
            $data = array(
                "event" => self::MEMBER_REMOVED,
                "who" => $this->searchMemberByName($matches[3]),
                "by" => $this->searchMemberByName($matches[1])
            );
            return $data;
        }

        private function parseMemberJoinedMessage() {
            $matches = $this->extractData(self::MEMBER_JOINED);
            $data = array(
                "event" => self::MEMBER_JOINED,
                "who" => $this->searchMemberByName($matches[1])
            );
            return $data;
        }

        private function parseMemberLeftMessage() {
            $matches = $this->extractData(self::MEMBER_LEFT);
            $data = array(
                "event" => self::MEMBER_LEFT,
                "who" => $matches[1]
            );
            return $data;
        }

        private function parseMemberRejoinedMessage() {
            $matches = $this->extractData(self::MEMBER_REJOINED);
            $data = array(
                "event" => self::MEMBER_REJOINED,
                "who" => $this->searchMemberByName($matches[1])
            );
            return $data;
        }

        private function parseMemberNameChangeMessage() {
            $matches = $this->extractData(self::MEMBER_CHANGED_NAME);
            $data = array(
                "event" => self::MEMBER_CHANGED_NAME,
                "who" => $matches[1],
                "what" => $matches[3]
            );
            return $data;
        }

        private function parseOfficeModeChangeMessage() {
            $matches = $this->extractData(self::OFFICE_MODE_CHANGED);
            $data = array(
                "event" => self::OFFICE_MODE_CHANGED,
                "who" => $this->searchMemberByName($matches[1]),
                "what" => $matches[4] == "dis" ? "enabled" : "disabled",
            );
            return $data;
        }

        private function parseEventRSVPMessage() {
            $matches = $this->extractData(self::EVENT_RSVP);
            $data = array(
                "event" => self::EVENT_RSVP,
                "who" => $this->searchMemberByName($matches[1]),
                "what" => $matches[4],
                "rsvp" => $matches[3] == "going" ? "yes" : "no"
            );
            return $data;
        }

        private function parseEventChangedMessage() {
            $matches = $this->extractData(self::EVENT_CHANGED);
            $data = array(
                "event" => self::EVENT_CHANGED,
                "who" => $this->searchMemberByName($matches[1]),
                "what" => $matches[4],
                "change" => $matches[3]
            );
            return $data;
        }

        private function parseEventCanceledMessage() {
            $matches = $this->extractData(self::EVENT_CANCELED);
            $data = array(
                "event" => self::EVENT_CANCELED,
                "who" => $this->searchMemberByName($matches[1]),
                "what" => $matches[3]
            );
            return $data;
        }

        private function getSystemMessageType() {
            $message =  $this->getMessage();
            foreach ($this->events as $type => $regex) {
                if (preg_match($regex, $message)) {
                    return $type;
                }
            }
            return "unknown_event";
        }
}
?>
