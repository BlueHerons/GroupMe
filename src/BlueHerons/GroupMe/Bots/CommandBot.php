<?php
namespace BlueHerons\GroupMe\Bots;

abstract class CommandBot extends BaseBot {

    const COMMAND_CHAR = "! ";

    public function __construct($token) {
        parent::__construct($token);
        $this->registerCommand("help",   array($this, "listCommands"));
        $this->registerCommand("ignore", array($this, "ignoreUser"));
        $this->registerCommand("ack",    array($this, "acknowledgeUser"));
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
    }

    private function ignoreUser() {
        $args = func_get_args();
        if (count($args) == 2) {
            $user = $args[1];
            if ($this->isAdmin($user)) {
                return sprintf("I'm sorry, %s. I'm afraid I can't do that.", $args[0]['name']);
            }
            else {
                $this->blacklistUser($user);
                $this->logger->info(sprintf("%s was added to the blacklist by %s", $user, $args[0]['sender_id']));
                return sprintf("I will ignore commands from user %s.", $user);
            }
        }
    }

    private function acknowledgeUser() {
        $args = func_get_args();
        if (count($args) == 2) {
            $user = $args[1];
            $this->whitelistUser($user);
            $this->logger->info(sprintf("%s was removed from the blacklist by %s", $user, $args[0]['sender_id']));
            return sprintf("I will acknowledge commands from user %s.", $user);
        }
    }

    private function listCommands() {
        $commands = array();
        foreach ($this->commands as $cmd => $c) {
            $commands[] = $cmd;
        }

        asort($commands);

        return "I understand the following commands:\n\n" . implode("\n", $commands);
    }

    public function registerCommand($command, $function, $params = array()) {
        $this->commands[$command] = array($function, $params);
    }

    public function executeCommand($command, $params = array()) {
        return call_user_func_array($this->commands[$command][0], array_merge(array("payload" => $this->getPayload()), $params));
    }

    protected function getCommand() {
        $cmd = str_replace(self::COMMAND_CHAR, "", $this->getMessage());
        $cmd = str_replace(self::COMMAND_CHAR, "", explode(" ", $cmd)[0]);
        return strtolower($cmd);
    }

    protected function getParams() {
        $params = str_replace(self::COMMAND_CHAR, "", $this->getMessage());
        $params = str_replace($this->getCommand(), "", $params);
        return array_values(array_filter(explode(" ", $params)));
    }

    protected function isRegisteredCommand($command) {
        return array_key_exists($command, $this->commands);
    }

    protected function isCommandMessage() {
        return self::COMMAND_CHAR === "" || strrpos($this->getMessage(), self::COMMAND_CHAR, -strlen($this->getMessage())) !== FALSE;
    }
}
?>
