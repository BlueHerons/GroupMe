<?php
namespace BlueHerons\GroupMe\Bots;

abstract class CommandBot extends EventBot {

    const COMMAND_CHAR = "! ";

    public function __construct($token, $bot_id) {
        parent::__construct($token, $bot_id);
        $this->registerCommand("help",   array($this, "listCommands"),    "Show available commands");
        $this->registerCommand("ignore", array($this, "ignoreUser"),      "Ignore the specified user");
        $this->registerCommand("ack",    array($this, "acknowledgeUser"), "Acknowledge the specified user");
        $this->registerCommand("info",   array($this, "info"),            "Show info");
    }

    private $commands = array();

    public function listen() {
        if (!$this->isSystemMessage() && $this->isCommandMessage()) {
            // Ignored?
            if ($this->isAuthorized($this->getPayload()['sender_id'])) {
                $command = $this->getCommand();
                $params = $this->getParams();
                if ($this->isRegisteredCommand($command)) {
                    $this->sendMessage($this->executeCommand($command, $params));
                    //echo $this->executeCommand($command);
                }
            }
        }
        else {
            parent::listen();
        }
    }

    protected function info() {
        $commitsh = substr(`git rev-parse HEAD`, 0, 8);
        $bot = array_pop(explode("\\", $this->config->bot));
        $message  = sprintf("Group..... : %s (ID: %s)\n", $this->getGroupInfo()->name, $this->getGroupInfo()->group_id);
        $message .= sprintf("Bot....... : %s (%s)\n", $bot, $commitsh);
        $message .= sprintf("Capacity.. : %s / %s\n", sizeof($this->getGroupInfo()->members), $this->getGroupInfo()->max_members);
        $message .= sprintf("Created On : %s\n", date("Y-m-d", $this->getGroupInfo()->created_at));
        $message .= sprintf("Created By : %s\n", $this->getMemberByID($this->getGroupInfo()->creator_user_id)->nickname);

        return $message;
    }

    private function ignoreUser() {
        $args = func_get_args();
        if (count($args) >= 2) {
            $user = implode(" ", array_slice($args, 1));
            if ($this->isAdmin($user) || $this->isMod($user)) {
                return sprintf("I'm sorry, @%s. I'm afraid I can't do that.", $args[0]['name']);
            }
            else {
                $id = is_numeric($user) ? $user : $this->searchMemberByName($user)->user_id;
                $name = $this->getMemberByID($id)->nickname;

                $this->addToBlacklist($id);
                $this->logger->info(sprintf("%s (%s) was added to the blacklist by %s", $name, $id, $this->getMemberByID($args[0]['sender_id'])->nickname));
                return sprintf("I will ignore commands from @%s.", $name);
            }
        }
    }

    private function acknowledgeUser() {
        $args = func_get_args();
        if (count($args) >= 2) {
            $user = implode(" ", array_slice($args, 1));
            $id = is_numeric($user) ? $user : $this->searchMemberByName($user)->user_id;
            $name = $this->getMemberByID($id)->nickname;
            $this->removeFromBlacklist($id);
            $this->logger->info(sprintf("%s (%s) was removed from the blacklist by %s", $name, $id, $this->getMemberByID($args[0]['sender_id'])->nickname));
            return sprintf("I will acknowledge commands from @%s.", $name);
        }
    }

    protected function listCommands() {
        $commands = array();
        $max_cmd_length = 0;
        foreach ($this->commands as $cmd => $c) {
            $commands[$cmd] = $c[1];
            if (strlen($cmd) > $max_cmd_length) {
                $max_cmd_length = strlen($cmd);
            }
        }

        ksort($commands);
        $msg = "";

        foreach ($commands as $c => $d) {
            $msg .= $c;
            for($i = 0; $i < $max_cmd_length - strlen($c); $i++) {
                $msg .= ".";
            }
            $msg .= " => ";
            $msg .= $d;
            $msg .= "\n";
        }

        return "I understand the following commands:\n\n" . $msg;
    }

    public function registerCommand($command, $function, $help = "") {
        $this->commands[$command] = array($function, $help);
    }

    public function unregisterCommand($command) {
        unset($this->commands[$command]);
    }

    public function executeCommand($command, $params = array()) {
        return call_user_func_array($this->commands[strtolower($command)][0], array_merge(array("payload" => $this->getPayload()), $params));
    }

    protected function getCommand() {
        $cmd = str_replace(self::COMMAND_CHAR, "", $this->getMessage());
        $cmd = str_replace(self::COMMAND_CHAR, "", explode(" ", $cmd)[0]);
        return $cmd;
    }

    protected function getParams() {
        $params = str_replace(self::COMMAND_CHAR, "", $this->getMessage());
        $params = str_replace($this->getCommand(), "", $params);
        $params = array_values(array_filter(explode(" ", $params)));
        return $params;
    }

    protected function isRegisteredCommand($command) {
        return array_key_exists(strtolower($command), $this->commands);
    }

    protected function isCommandMessage() {
        return self::COMMAND_CHAR === "" || strrpos($this->getMessage(), self::COMMAND_CHAR, -strlen($this->getMessage())) !== FALSE;
    }
}
?>
