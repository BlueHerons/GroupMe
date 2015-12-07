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
        $this->unregisterCommand("lessons");
        $this->unregisterCommand("mods");
        $this->unregisterCommand("rules");
        $this->unregisterCommand("spin");

        $this->registerCommand("addmeto",      array($this, "addto"),      "Add to a group");
        $this->registerCommand("announce",     array($this, "announce"),   "Announce something to a single chat");
        $this->registerCommand("autokick",     array($this, "autokick"),   "Adds user to global autokick");
        $this->registerCommand("broadcast",    array($this, "broadcast"),  "Broadcast a message to all chats");
        $this->registerCommand("init",         array($this, "init"),       "Initialize the bot in a chat");
        $this->registerCommand("removemefrom", array($this, "removefrom"), "Remove from a group");
        $this->registerCommand("whereami",     array($this, "whereami"),   "Lists groups the bot is in");

        $this->payload = $chat;
    }

    public function addto() {
        if ($this->isAdmin($this->payload->other_user->id)) {
            $group_id = $this->getParams()[0];
            $this->logger->info(sprintf("%s (%s) requested add to %s",
                $this->payload->other_user->name,
                $this->payload->other_user->id,
                $group_id));
            $this->temp_group_id = $group_id;
            $this->addMember($this->payload->other_user->id,
                             $this->payload->other_user->name);
            $this->replyToSender("You were added to " . $this->getGroupInfo()->name);
        }
        else {
            $this->replyToSender("Sorry, you cannot use this command.");
        }
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

    public function autokick() {
        $user_id = $this->getParams()[0];
        if (!is_numeric($user_id)) { return "Please provide a user_id"; }

        $autokick = array_merge(
            ((array) $this->getGlobalConfig("autokick")),
            array((int) $user_id));

        sort($autokick);

        $this->setGlobalConfig("autokick", $autokick);

        // Kick from all rooms

        return print_r($this->getParams(), true);
    }

    public function broadcast() {
        if ($this->isAdmin($this->payload->other_user->id)) {
            $this->replyToSender("Sending broadcast...");
            $message= implode(" ", array_slice(func_get_args(), 1));
            $message = sprintf("** Broadcast from %s **\n\n%s", $this->payload->other_user->name, $message);
            $this->sendBroadcast($message);
            $this->replyToSender("Broadcast sent.");
        }
        else {
            $this->replyToSender("Sorry, you cannot use this command.");
        }
    }

    public function init() {
        if ($this->isAdmin($this->payload->other_user->id)) {
            $args = func_get_args();
            $group_id = $args[1];
            if (sizeof($args) < 2) {
                return "usage:\n\n" . CommandBot::COMMAND_CHAR . "init <group_id>";
            }
            else {
                $this->replyToSender(sprintf("Attempting to initialize in group %d...", $group_id));
                if ($this->isInGroup($group_id)) {
                    $info = $this->getGroupInfo($group_id);

                    // Does bot already exist?
                    $bots = json_decode($this->gm->bots->index())->response;

                    foreach ($bots as $bot) {
                        if ($bot->group_id == $group_id) {
                            return sprintf("I am already initialized in %s", $info->name);
                        }
                    }

                    // Create a actual GM bot
                    $hash = md5(uniqid($info->name, true));
                    $this->gm->bots->create(array(
                        "name" => sprintf("Listener: %s", $info->name),
                        "group_id" => $group_id,
                        "avatar_url" => "http://i.groupme.com/453x500.png.e9543114323c4e17abb244998152273b",
                        "callback_url" => sprintf($this->config->callback_url, $hash)
                    ));

                    // Add to config
                    $this->initializeNewBot($hash, $info->name);

                    // Send message to that group showing its online
                    $this->sendGroupMessage("! info", $group_id);
                    sleep(2);
                    $this->sendGroupMessage("! help", $group_id);

                    return sprintf("I have been successfully initialized in %s", $info->name);
                }
                else {
                    return sprintf("I am not a member of group %s", $group_id);
                }
            }
        }
        else {
            $this->replyToSender("Sorry, you cannot use this command.");
        }
    }

    // Override
    public function getGroupID() {
        return $this->temp_group_id;
    }

    // Override
    public function getMessage() {
        return $this->payload->last_message->text;
    }

    // Override
    public function getMemberByID($id) {
        if ($id != $this->payload->other_user->id) {
            throw new \Exception(sprintf("Attempted to get third-party user %s from PM", $id));
        }
        else {
            $other = new \stdClass();
            $other->user_id = $this->payload->other_user->id;
            $other->nickname = $this->payload->other_user->name;
            return $other;
        }
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
                $this->sendMessage("I am ARCC - Automated Resistance Command Construct. My direct messages are not monitored. Please join the Puget Sound Resistance Reception Room @ http://blueheronsresistance.com/chat");
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
        sleep(1);
    }

    public function removefrom() {
        $args = $this->getParams();
        if (sizeof($args) < 1) {
            return "usage:\n\n" . CommandBot::COMMAND_CHAR . "removemefrom <group_id>";
        }
        else {
            $group_id = $args[0];
            $this->logger->info(sprintf("%s (%s) requested removal from %s",
                $this->payload->other_user->name,
                $this->payload->other_user->id,
                $group_id));
            $this->temp_group_id = $group_id;
            $this->removeMember($this->payload->other_user->id);
            return sprintf("You have been removed from %s", $this->getGroupInfo($group_id)->name);
        }
    }

    // Override
    public function replyToSender($message) {
        $this->sendMessage($message);
    }

    public function whereami() {
        if ($this->isAdmin($this->payload->other_user->id)) {
            $groups = array();
            foreach ($this->getGroups() as $group) {
                array_push($groups, $group);
            }

            sort($groups);

            $message = "%s";
            foreach ($groups as $group) {
                $message = sprintf($message, sprintf("%d - %s\n%s", $group->id, $group->name, "%s"));
            }

            return sprintf($message, "");
        }
        else {
            return "Sorry, you cannot use this command.";
        }
    }
}
