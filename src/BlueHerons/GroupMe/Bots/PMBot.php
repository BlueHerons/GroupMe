<?php
namespace BlueHerons\GroupMe\Bots;

use \BlueHerons\Cycle\Cycle;
use \DateTime;
use \ReflectionClass;
use \stdClass;

class PMBot extends ResWueBot {

    private $payload;

    public function __construct($token) {
        global $chat;
        parent::__construct($token, "listen");

        $this->unregisterCommand("ack");
        $this->unregisterCommand("config");
        $this->unregisterCommand("help");
        $this->unregisterCommand("ignore");
        $this->unregisterCommand("info");
        $this->unregisterCommand("mods");
        $this->unregisterCommand("rules");

        $this->registerCommand("announce",  array($this, "announce"),  "Announce something to a single chat");
        $this->registerCommand("broadcast", array($this, "broadcast"), "Broadcast a message to all chats");

        $this->payload = $chat;
    }

    public function announce() {
        $args = func_get_args();
        if (sizeof($args) < 2) {
            return "usage:\n\n" . CommandBot::COMMAND_CHAR . "announce <group_id> <message>";
        }
        else {
            $group_id = $args[1];
            $message = implode(" ", array_slice($args, 2));
            $groups = json_decode(utf8_encode($this->gm->groups->index(array("per_page" => 100))))->response;
            foreach ($groups as $group) {
                if ($group_id == $group->group_id) {
                    // User must be admin or member of group
                    if ($this->isAdmin($this->payload->other_user->id) || $this->isMember($this->payload->other_user->id, $group_id)) {
                        $this->sendGroupMessage(sprintf("** Announcement from %s **\n\n%s", $this->payload->other_user->name, $message), $group->group_id);
                        return sprintf("Announcement sent to %s", $group->name);
                    }
                    else {
                        return "Sorry, you cannot use this command.";
                    }
                }
            }
            return sprintf("I am not a member of group %s", $group_id);
        }
    }

    public function broadcast() {
        if ($this->isAdmin($this->payload->other_user->id)) {
            $message= implode(" ", array_slice(func_get_args(), 1));
            $message = sprintf("** Broadcast from %s **\n\n%s", $this->payload->other_user->name, $message);
            $this->sendBroadcast($message);
        }
        else {
            $this->replyToSender("Sorry, you cannot use this command.");
        }
    }

    // Override
    public function getMessage() {
        return $this->payload->last_message->text;
    }

    // Override
    public function listen() {
        if ($this->isAuthorized($this->payload->other_user->id)) {
            if ($this->isCommandMessage()) {
                $command = $this->getCommand();
                $params = $this->getParams();
                if ($this->isRegisteredCommand($command)) {
                    $this->sendMessage($this->executeCommand($command, $params));
                }
                else {
                    $this->sendMessage($this->listCommands());
                }
            }
            else {
                $this->sendMessage("This is a bot account. Private messages are not monitored. Please join the Puget Sound Resistance Reception Room @ http://blueheronsresistance.com/chat");
            }
        }
        else {
            $this->logger->info(sprintf("%s sent a PM to the bot, but is unauthorized.", $this->payload->other_user->name));
            $this->sendMessage("You are not an authorized user.");
        }
    }

    // Override
    public function sendMessage($message) {
        $this->gm->directmessages->create(array(
            "source_guid" => uniqid(),
            "recipient_id" => $this->payload->other_user->id,
            "text" => $message
        ));
    }

    // Override
    public function replyToSender($message) {
        $this->sendMessage($message);
    }
}
