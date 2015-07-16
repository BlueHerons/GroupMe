<?php
namespace BlueHerons\GroupMe\Bots;

use Katzgrau\KLogger\Logger;
use Psr\Log\LogLevel;
use GroupMePHP;

define("GROUPME_BOT_URL", "https://api.groupme.com/v3/bots/post");

abstract class BaseBot {

    const CONFIG_FILE = "config.json";
    const LOG_DIR = "logs";

    protected $config;
    protected $logger;
    protected $gm;

    private $bot_id;
    private $payload;

    public function __construct($token, $bot_id) {
        $this->gm = new GroupMePHP\groupme($token);

        $this->logger = new Logger(self::LOG_DIR, LogLevel::DEBUG, array(
            "extension" => "log",
            "logFormat" => "[{date} - " . $bot_id . "] [{level}] {message}"
        ));

        $this->token = $token;
        $this->bot_id = $bot_id;

        if (file_exists(self::CONFIG_FILE)) {       
            $config = json_decode(file_get_contents(self::CONFIG_FILE));

            if (property_exists($config, $bot_id)) {
                $this->config = $config->{$bot_id};
            }
            else {
                $this->logger->debug("No configuration for this bot");
                $this->config = (object) array(
                    "admin" => array(),
                    "blacklist" => array()
                );
            }
        }
        else {
            $this->logger->debug("Config file doesnt exist");
            $this->config = (object) array(
                "admin" => array(),
                "blacklist" => array()
            );
        }
    }

    public abstract function listen();

    /**
     * Determines if the given user is an admin
     *
     * @param $user User ID
     *
     * @return boolean
     */
    protected function isAdmin($user) {
        return in_array($user, $this->config->admin);
    }

    /**
     * Returns true if the given user id is not on the blacklist.
     *
     * @param $user user ID
     *
     * @return boolean
     */
    protected function isAuthorized($user) {
        if (in_array($user, $this->config->blacklist)) {
            $this->logger->info(sprintf("%s tried to execute a command, but is blacklisted.", $user));
            return false;
        }
        return true;
    }

    /**
     * Determines if the message is a system message
     *
     * @return boolean
     */
    protected function isSystemMessage() {
        return $this->getPayload()['system'] === true;
    }

    /**
     * Adds a user to the blacklist
     *
     * @param $user user ID
     */
    protected function blackListUser($user) {
        $this->config->blacklist[] = $user;
        $this->logger->info(sprintf("%s was added to the blacklist.", $user));
        $this->saveConfig();
    }

    /**
     * Returns the configuration for this bot
     *
     * @return object
     */
    protected function getConfig() {
        return $this->config;
    }

    /**
     * Returns the logger for this bot
     *
     * @return Logger
     */
    protected function getLogger() {
        return $this->logger;
    }

    /**
     * Saves configuration from memory to file
     */
    protected function saveConfig() {
        $c = json_decode(file_get_contents(self::CONFIG_FILE));
        $c->{$this->bot_id} = $this->config;
        file_put_contents(self::CONFIG_FILE, json_encode($c));
        $this->logger->debug("Config saved");
    }

    /**
     * Remove a user from the blacklist
     *
     * @param $user user ID
     */
    protected function whitelistUser($user) {
        $key = array_search($user, $this->config->blacklist);
        if ($key !== false) {
            unset($this->config->blacklist[$key]);
            $this->config->blacklist = array_values($this->config->blacklist);
            $this->logger->info(sprintf("%s was removed from the blacklist.", $user));
            $this->saveConfig();
        }
    }

    /**
     * Gets the message that was posted to the chat
     *
     * @return string
     */
    protected function getMessage() {
        return $this->getPayload()['text'];
    }

    /**
     * Returns the entire payload that was sent by GroupMe
     *
     * @return object
     */
    protected function getPayload() {
        if ($this->payload == null) {
            $input = file_get_contents("php://input");
            $this->payload = json_decode($input, true);
        }

        return $this->payload;
    }

    protected function sendMessage($msg) {
        $this->sendGroupMessage($msg, $this->getGroupID());
    }

    protected function sendGroupMessage($msg, $group_id) {
        $this->gm->messages->create($group_id, array(
            uniqid(),
            $msg
        ));
    }

    /**
     * Gets the GroupMe group ID for the bot
     */
    private function getGroupID() {
        $data = $this->gm->bots->index();
        $data = json_decode($data);
        foreach ($data->response as $bot) {
            if ($bot->bot_id == $this->bot_id) {
                return $bot->group_id;
            }
        }
        return null;
    }
}
?>
