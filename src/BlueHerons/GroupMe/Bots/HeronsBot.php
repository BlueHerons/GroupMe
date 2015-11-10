<?php
namespace BlueHerons\GroupMe\Bots;

use \BlueHerons\Cycle\Cycle;
use \DateTime;
use \ReflectionClass;
use \stdClass;

class HeronsBot extends CommandBot {

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);

        $this->registerHandler(EventBot::MEMBER_ADDED, array($this, "checkForAutoKick"));
        $this->registerHandler(EventBot::MEMBER_JOINED, array($this, "checkForAutoKick"));
        $this->registerHandler(EventBot::MEMBER_REJOINED, array($this, "checkForAutoKick"));

        if ($this->config->modAddOnly) {
            $this->registerHandler(EventBot::MEMBER_ADDED, array($this, "checkThatModeratorAddedMember"));
        }

        $this->registerCommand("config",     array($this, "config"),            "Shows or sets bot configuration");
        $this->registerCommand("checkpoint", array($this, "next_checkpoint"),   "Show next checkpoint");
        $this->registerCommand("cycle",      array($this, "next_cycle"),        "Show next cycle");
        $this->registerCommand("lessons",    array($this, "smurfling_lessons"), "Smurfling Lessons link");
        $this->registerCommand("spin",       array($this, "spin"),              "Spins all chat members, picking one");
        $this->registerCommand("mods",       array($this, "mods"),              "Chat mods");

        // button should only be registered if configured
        if (isset($this->config->button)) {
            $this->registerCommand("button", array($this, "smash_button"), "Button link");
        }

        if (isset($this->config->chores)) {
            $this->registerCommand("chores", array($this, "chores"), "Link to Chore wheel");
        }

        // rules should only be registered if configured
        if (isset($this->config->rules)) {
            $this->registerCommand("rules", array($this, "rules"), "Show chat rules");
        }

        // alertme should only be registered if configured
        if (isset($this->config->alert)) {
            $this->registerCommand("alertme", array($this, "alertme"), "Send name change alerts via PM");
            $this->registerHandler(self::GROUP_CHANGED, function($data) {
                if ($data['change'] == "name" && is_object($this->config->alert)) {
                    if (preg_match($this->config->alert->regex, $data['what'])) {
                        foreach ($this->config->alert->users as $user) {
                            $this->sendDirectMessage($user, $data['what']);
                        }
                        $this->sendMessage(sprintf("%d people have been alerted to the group name change.", sizeof($this->config->alert->users)));
                    }
                }
            });
        }
    }

    public function alertme() {
        if (!is_object($this->config->alert)) {
            $this->config->alert = new stdClass();
            $this->config->alert->regex = "/^$/";
            $this->config->alert->users = array();
            $this->saveConfig();
        }

        if (!is_array($this->config->alert->users)) {
            $this->config->alert->users = array();
        }

        if (!in_array($this->getPayload()['sender_id'], $this->config->alert->users)) {
            array_push($this->config->alert->users, $this->getPayload()['sender_id']);
            $this->replyToSender("You will receive group name change alerts via direct message.");
            $this->saveConfig();
        }
        else {
            $this->replyToSender("You have already opted-in to receiving alerts for this group.");
        }
    }

    public function checkForAutoKick($data) {
        $this->logger->info(sprintf("Checking %s (%s) for auto-kick", $data['who']->nickname, $data['who']->user_id));
        if (in_array($data['who']->user_id, $this->getGlobalConfig("autokick")) ||
            in_array($data['who']->user_id, $this->config->autokick)) {
            $this->logger->info(sprintf("%s is marked for auto-kick", $data['who']->nickname));
            $this->removeMember($data['who']->user_id);
            $this->sendDirectMessage($data['by']->user_id, sprintf("%s was automatically kicked from that group. Please contact an admin for details.", $data['who']->nickname));
        }
    }

    public function chores() {
        return $this->config->chores;
    }

    public function config() {
        $args = array_values(array_slice(func_get_args(), 1));
        $message = "";

        if (sizeof($args) == 0) {
            // Get current configuration
            foreach (((array) $this->config) as $c => $v) {
                if (is_array($v)) { $v = implode(",", $v); }
                if (is_bool($v)) { $v = $v ? "Yes" : "No"; }
                $message .= $c . ": " . $v . "\n";
            }
        }
        else {
            $setting = $args[0];
            $value = implode(" ", array_slice($args, 1));

            $user = $this->getMemberByID($this->getPayload()['sender_id']);

            if ($this->isAdmin($user->user_id) || $this->isMod($user->user_id)) {
                if (isset($this->config->{$setting})) {
                    // Validate individual config settings
                    if ($setting == "bot") {
                        if (!class_exists($value)) {
                            $this->replyToSender(sprintf("Class '%s' does not exist", $value));
                            return;
                        }

                        $class = new ReflectionClass($value);
                        if (!$class->isInstantiable()) {
                            $this->replyToSender(sprintf("Class '%s' can not be used.", $value));
                            return;
                        }
                    }
                    else if ($setting == "broadcast") {
                        $value = in_array(strtolower($value), array("no", "false", "off")) ? "false" : "true";
                    }
                    else if ($setting == "name") {
                        $this->changeName($value);
                        sleep(1);
                    }

                    $this->config->{$setting} = $value;
                    $this->logger->info(sprintf("%s updated configuration: %s",
                                        $this->getMemberByID($this->getPayload()['sender_id'])->nickname,
                                        print_r($this->config, true)));
                    $this->replyToSender("Bot configuration updated.");
                    $this->saveConfig();
                }
            }
            else {
                $this->replyToSender("Only mods can change bot configuration");
            }
        }

        return $message;
    }

    public function checkThatModeratorAddedMember($data) {
        if (!$this->isMod($data['by']->user_id)) {
            $this->logger->info(sprintf("%s (%s) is not a mod, cannot add new members.", $data['by']->nickname, $data['by']->user_id));
            $this->removeMember($data['who']->user_id);
            $this->sendMessage("I'm sorry, but this chat only allows mods to add new members.");
            // Die to prevent other handlers
            die();
        }
    }

    public function mods($cmd, $seperator = "\n", $prefix = "Moderators:\n\n") {
        $mods = $prefix . "%s";
        foreach ($this->config->mods as $mod) {
            $mods = sprintf($mods, $this->getMemberByID($mod)->nickname . $seperator . "%s");
        }
        $mods = sprintf($mods, "");
        return $mods;
    }

    public function next_checkpoint() {
        $next = Cycle::getNextCheckpoint();
        return sprintf("Next checkpoint at %s. (%s)",
                       $next->format("g A"),
                       $next->diff(new DateTime())->format("%h hours, %i mins"));
    }

    public function next_cycle() {
        $next = Cycle::getNextCycleStart();
        return sprintf("Next septicycle starts on %s. (%s)",
                       $next->format("l, F j \a\\t g A"),
                       $next->diff(new DateTime())->format("%d days, %h hours, %i mins"));
    }

    public function rules() {
        $rules = $this->config->rules;
        return $rules;
    }

    public function smash_button() {
        $button = $this->config->button;
        return sprintf("%s button: %s", $button->name, $button->url);
    }

    public function smurfling_lessons() {
        return sprintf("Smurfling lessons can be found at %s. These infographics are a great supplement to in-game training.", "http://blueheronsresistance.com/guide/lessons");
    }

    public function spin() {
        $i = array_rand($this->getGroupMembers());
        $user = $this->getGroupMembers()[$i];
        
        return sprintf("@%s", $user->nickname);
    }

    public function whoami() {
        if (sizeof($args) == 1) {
            $user = $this->searchMemberByName($this->getPayload()['sender_id']);
            if ($user->user_id != -1) {
                return sprintf("@%s's user_id is %d", $user->nickname, $user->user_id);
            }
            else {
                return sprintf("No user matching '%s' was found", $args[0]);
            }
        }
        else if (sizeof($args) == 0) {
            $user = $this->getMemberByID($this->getPayload()['sender_id']);
            return sprintf("@%s's user_id is %d", $user->nickname, $user->user_id);
        }
    }
}
?>
