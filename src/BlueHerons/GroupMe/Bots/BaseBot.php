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
    private $group;

    public function __construct($token, $bot_id) {
        $this->gm = new GroupMePHP\groupme($token);

        $this->logger = new Logger(self::LOG_DIR, LogLevel::DEBUG, array(
            "extension" => "log",
            "logFormat" => "[{date} - " . $bot_id . "] [{level}] {message}"
        ));

        $this->token = $token;
        $this->bot_id = $bot_id;

        $input = file_get_contents("php://input");
        $this->payload = json_decode($input, true);

        if (file_exists(self::CONFIG_FILE)) {       
            $config = json_decode(file_get_contents(self::CONFIG_FILE));

            if (property_exists($config, $bot_id)) {
                $this->config = $config->{$bot_id};
            }
            else {
                $this->logger->debug("No configuration for " . $bot_id);
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

        $this->saveConfig();
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
        return $this->payload['text'];
    }

    /**
     * Returns the entire payload that was sent by GroupMe
     *
     * @return object
     */
    protected function getPayload() {
        return $this->payload;
    }

    /**
     * Sends a message to the same group that triggered the bot. The account who's $token is used
     * must be a member of that group for this to work.
     *
     * @param $msg the message to send
     */
    protected function sendMessage($msg) {
        try {
            $this->sendGroupMessage($msg, $this->getGroupID());
        }
        catch (Exception $e) {
            $this->logger->fatal("EXCEPTION WHILE SENDING MESSAGE", $e);
        }
    }

    /**
     * Sends a message to the specified group. The account who's $token is used must be a member
     * of that group for this to work.
     *
     * @param $msg the message to send
     * @param $group_id the group id
     */
    protected function sendGroupMessage($msg, $group_id) {
        //$msg = print_r($this->getMentions($msg), true);
        $this->logger->debug(sprintf("Sending '%s' to group %s", $msg, $group_id));

        sleep(1);
        $result = $this->gm->messages->create($group_id, array(
            md5(time() . uniqid()),
            $msg,
            $this->getMentions($msg)
        ));
        $this->logger->debug(print_r($result, true));
    }

    /**
     * Gets the GroupMe group ID that triggered the message
     */
    protected function getGroupID() {
        $group_id = "";
        if (isset($this->payload) && isset($this->payload['group_id'])) {
            $group_id = $this->payload['group_id'];
        }
        else {
            //throw new \Exception("Group ID unavailable");
            $group_id = 15033208;
        }
        $this->logger->debug("Group id: " . $group_id);
        return $group_id;
    }

    /**
     * Gets information about the group that the message was sent from
     */
    protected function getGroupInfo() {
        if ($this->group == null) {
            $this->group = json_decode($this->gm->groups->show($this->getGroupID()))->response;
            $m = $this->group->members;
            usort($m, function($a, $b) {
                return strcmp($a->nickname, $b->nickname);
            });
            $this->group->members = $m;
        }

        return $this->group;
    }

    /**
     * Gets a list of members in the group
     */
    protected function getGroupMembers() {
        return $this->getGroupInfo()->members;
    }

    /**
     * Searches the group members for a member having the given name, and returns information
     * about them
     */
    protected function getMemberByName($name) {
        foreach ($this->getGroupMembers() as $member) {
            if ($member->nickname === $name) {
                return $member;
            }
        }
        return false;
    }

    /**
     * Searches the group members for a member having the given name, and returns information
     * about them
     */
    protected function getMemberByID($id) {
        $id .= ""; // force string
        foreach ($this->getGroupMembers() as $member) {
            if ($member->user_id === $id) {
                return $member;
            }
        }
        return false;
    }

    /**
     * Searches the group members for a member whose nickname matches the $partial_name parameter.
     * The first match is returned, or false if no matches
     *
     * @param $partial_name name to use for a search.
     */
    protected function searchMemberByName($partial_name) {
        $partial_name = preg_replace("/^[A-Za-z0-9]/", "", $partial_name);
        foreach ($this->getGroupMembers() as $member) {
            if (strpos($member->nickname, $partial_name) !== false) {
                $this->logger->debug(sprintf("Found user '%s' searching for '%s'", $member->nickname, $partial_name));
                return $member;
            }
        }
        return false;
    }

    /**
     * Returns an "attachment" for mentions in a message
     */
    protected function getMentions($message) {
        $needle = "@";

        if (strpos($message, $needle) !== false) {
            $mentions = new \stdClass();
            $mentions->type = "mentions";
            $mentions->loci = array();
            $mentions->user_ids = array();

            $loci = array();
            $lastPos = 0;
            $positions = array();

            // Search for all @'s in the message
            while (($lastPos = strpos($message, $needle, $lastPos)) !== false) {
                $positions[] = $lastPos;
                $lastPos = $lastPos + strlen($needle);
            }

            // After each @, get the length of the sequence of characters until something non-alphanumeric is
            // encoumtered
            foreach ($positions as $p) {
                $seq = array_values(array_filter(preg_split("/[^a-z0-9]/i", substr($message, $p))))[0];
                $i = strpos($message, " ", $p);
                $i = ($i === false) ? strlen($message) : $i;
                $loci[] = array($p, $p + strlen($seq));
            }

            // Extract the string between each loci, and use it to search for users
            foreach ($loci as $l) {
                $str = substr($message, $l[0] + strlen($needle), $l[1] - $l[0]);
                $id = $this->searchMemberByName($str);
                if ($id !== false) {
                    $mentions->loci[] = array($l[0], strlen($str) + 1);
                    $mentions->user_ids[] = $id->user_id;
                }
            }

            if (sizeof($mentions->user_ids) > 0) {
                return $mentions;
            }
        }
    }
}
?>
