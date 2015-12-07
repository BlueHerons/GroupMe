<?php
namespace BlueHerons\GroupMe\Bots;

use Katzgrau\KLogger\Logger;
use Psr\Log\LogLevel;
use GroupMePHP;

define("GROUPME_BOT_URL", "https://api.groupme.com/v3/bots/post");

abstract class BaseBot {

    const CONFIG_FILE = "config.json";
    const LOG_DIR = "logs";

    private $global_config;

    protected $config;
    protected $logger;
    protected $gm;

    private $bot_id;
    private $user_id;
    private $payload;
    private $group;

    public function __construct($token, $bot_id) {
        $this->BOGUS_GROUPME_USER = json_decode('{"nickname": "Bogus User", "user_id": -1}');

        $this->gm = new GroupMePHP\groupme($token);

        $this->logger = new Logger(self::LOG_DIR, LogLevel::DEBUG, array(
            "extension" => "log",
            "logFormat" => "[" . substr($bot_id, 0, 6) . " - {date}] [{level}] {message}"
        ));

        $this->token = $token;
        $this->bot_id = $bot_id;

        $input = file_get_contents("php://input");
        $this->payload = json_decode($input, true);

        if (file_exists(self::CONFIG_FILE)) {       
            $config = json_decode(file_get_contents(self::CONFIG_FILE));
            $this->global_config = $config;

            if (isset($config->bots->{$bot_id})) {
                $this->config = $config->bots->{$bot_id};
                $this->config->autokick = is_array($this->config->autokick) ? 
                    $this->config->autokick :
                    array();
            }
            else {
                $this->logger->debug("No configuration for " . $bot_id);
                $this->config = (object) array(
                    "bot" => get_class($this),
                    "mods" => array(),
                    "blacklist" => array()
                );
            }
        }
        else {
            $this->logger->info("Config file doesnt exist");
            $this->config = (object) array(
                "bot" => get_class($this),
                "mods" => array(),
                "blacklist" => array()
            );
        }

        if (isset($config->log)) {
            $this->logger->setLogLevelThreshold($config->log);
        }

        $me = json_decode($this->gm->users->index())->response;
        $this->user_id = $me->user_id;

        $this->saveConfig();
    }

    public abstract function listen();

    /**
     * Creates an entry in the config file for a new bot
     *
     * @param string $bot_id
     * @param int    $group Name of the chat
     * @param string $class Fully=quailified name of the class for the bot
     */
    protected function initializeNewBot($bot_id, $group, $class = "BlueHerons\\GroupMe\\Bots\\HeronsBot") {
        $c = json_decode(file_get_contents(self::CONFIG_FILE));
        $c->bots->{$bot_id} = new \stdClass();
        $c->bots->{$bot_id}->bot = $class;
        $c->bots->{$bot_id}->group = $group;
        file_put_contents(self::CONFIG_FILE, json_encode($c, JSON_PRETTY_PRINT));
        $this->logger->info(sprintf("Bot %s initialized", substr($bot_id, 0, 6)));
    }

    /**
     * Determines if the given user is an admin
     *
     * @param mixed $user user_id or name to search users for
     *
     * @return boolean
     */
    protected function isAdmin($user) {
        $user = is_numeric($user) ?
                $this->getMemberByID($user) :
                $this->searchMemberByName($user);
        $admin = in_array($user->user_id, $this->getGlobalConfig("admin"));
        $this->logger->debug(sprintf("%s (%s) %s an admin.", $user->nickname, $user->user_id, $admin ? "is" : "is not"));
        return $admin;
    }

    /**
     * Determines if the bot is a member of a given group
     *
     * @param int $group_id The group Id to test for
     *
     * @return boolean
     */
    protected function isInGroup($group_id) {
        $groups = json_decode(utf8_encode($this->gm->groups->index(array("per_page" => 100))))->response;

        foreach ($groups as $group) {
            if ($group_id == $group->group_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if the given user is a mod
     *
     * @param mixed $user user_id or name to search users for
     *
     * @return boolean
     */
    protected function isMod($user) {
        $user = is_numeric($user) ?
                $this->getMemberByID($user) :
                $this->searchMemberByName($user);
        $mod = in_array($user->user_id, $this->config->mods);
        $this->logger->debug(sprintf("%s (%s) %s a mod.", $user->nickname, $user->user_id, $admin ? "is" : "is not"));
        return $mod;
    }

    /**
     * Determines if the given user is a member of the chat
     *
     * @param mixed $user user_id or name to search users for
     * @param int $chat chat_id of a group
     *
     * @return boolean
     */
    protected function isMember($user, $chat) {
        $id = is_numeric($user) ? $user : 0;
        $chat = is_numeric($chat) ? $chat : 0;

        foreach ($this->getGroupInfo($chat)->members as $member) {
            if ($member->user_id == $user)
                return true;
        }

        return false;
    }

    /**
     * Returns true if the given user id is not on the blacklist.
     *
     * @param int $user user ID
     *
     * @return boolean
     */
    protected function isAuthorized($user_id) {
        if (in_array($user_id, $this->config->blacklist) ||
            in_array($user_id, $this->config->autokick) ||
            in_array($user_id, $this->getGlobalConfig("autokick"))) {
            $this->logger->info(sprintf("%s is not authorized: blacklisted or marked for autokick.", $user_id));
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
     * @param int $user user ID
     */
    protected function addToBlacklist($user) {
        $this->config->blacklist[] = (int) $user;
        $this->logger->info(sprintf("%s was added to the blacklist.", $user));
        $this->saveConfig();
    }

    /**
     * Returns a setting from global configuration
     *
     * @return mixed setting from global configuration
     */
    protected function getGlobalConfig($key = "") {
        if (property_exists($this->global_config, $key)) {
            return $this->global_config->{$key};
        }
        else {
            $message = sprintf("Global configuration does not contain: %s", $key);
            $this->logger->error($message);
            $this->replyToSender(sprintf("ERROR: %s\n\n%s", $message));
            die();
        }
    }

    /**
     * Returns the configuration for this bot
     *
     * @return object
     */
    protected function getConfig($key = "") {
        return $this->config;
    }

    /**
     * Returns the user_id of the bot
     */
    protected function getUserID() {
        return $this->user_id;
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
        $c = (object) array_merge((array) $c, (array) $this->global_config);
        $c->bots->{$this->bot_id} = $this->config;
        file_put_contents(self::CONFIG_FILE, json_encode($c, JSON_PRETTY_PRINT));
        $this->logger->debug("Config saved");
    }

    /**
     * Sets a global configuration value. Global config values are applied to all
     * bots on the platform.
     *
     * @param string $key the setting's key
     * @param string $value the settings value
     */
    protected function setGlobalConfig($key, $value) {
        if (property_exists($this->global_config, $key)) {
            $this->global_config->{$key} = $value;
            $this->saveConfig();
        }
        else {
            $message = sprintf("Global configuration does not contain: %s", $key);
            $this->replyToSender(sprintf("ERROR: %s\n\n%s", $message));
            die();
        }
    }

    /**
     * Remove a user from the blacklist
     *
     * @param int $user user ID
     */
    protected function removeFromBlacklist($user) {
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
     * Gets the previous message as an object
     */
    protected function getPreviousMessage() {
        $id = $this->getPayload()['id'];
        $message = json_decode(utf8_decode($this->gm->messages->index($this->getGroupID(), array(
            "before_id" => $id,
            "limit" => 1))))->response->messages[0];
        return $message;
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
     * Broadcasts the same message to ALL groups that have a bot created by the token account
     *
     * @param string $message the message to send
     */
    protected function sendBroadcast($message) {
        $bots = json_decode($this->gm->bots->index());
        if ($bots->meta->code != 200) {
            $this->logger->error(sprintf("Error getting bots from GM API: %s", print_r($bots->meta, true)));
            return;
        }
        foreach ($this->global_config->bots as $bot_id => $bot) {
            if (!isset($bot->broadcast) || !$bot->broadcast) {
                $this->logger->info(sprintf("%s (%s) not configured for broadcast", $bot->group, substr($bot_id, 0, 6)));
                continue;
            }

            foreach ($bots->response as $gm_bot) {
                if (strpos($gm_bot->callback_url, $bot_id)) {
                    $group_id = $gm_bot->group_id;
                    $this->sendGroupMessage($message, $group_id);
                    $this->logger->info(sprintf("Broadcast sent to %s (%s).", $group_id, substr($bot_id, 0, 6)));
                }
            }
        }
    }

    /**
     * Sends a message to the same group that triggered the bot. The account who's $token is used
     * must be a member of that group for this to work.
     *
     * @param string $msg the message to send
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
     * @param string $msg the message to send
     * @param int $group_id the group id
     */
    protected function sendGroupMessage($msg, $group_id) {
        sleep(1);
        $result = $this->gm->messages->create($group_id, array(
            md5(time() . uniqid()),
            $msg,
            $this->getMentions($msg)
        ));
        $this->logger->debug(print_r($result, true));
    }

    /**
     * Sends a direct message to the user that triggered the bot.
     *
     * @param string $message
     */
    protected function replyToSender($message) {
        $this->sendDirectMessage($this->getPayload()['sender_id'], $message);
    }

    /**
     * Sends a direct message to the user.
     *
     * @param string $user_id
     * @param string $message
     */
    protected function sendDirectMessage($user_id, $message) {
        $this->logger->debug(sprintf("Sending %s a message: %s", $user_id, $message));
        $this->gm->directmessages->create(array(
            "source_guid" => uniqid(),
            "recipient_id" => $user_id,
            "text" => $message));
    }

    protected function getGroups() {
        if (!isset($this->groups)) {
            $this->groups = json_decode(utf8_encode($this->gm->groups->index(array("per_page" => 100))))->response;
        }

        return $this->groups;
    }

    /**
     * Gets the GroupMe group ID that triggered the message
     */
    protected function getGroupID() {
        $group_id = "";
        if (isset($this->payload) && isset($this->payload['group_id'])) {
            $group_id = $this->payload['group_id'];
        }
        else if (isset($this->group_id)) {
            $group_id = $this->group_id;
        }
        else {
            throw new \Exception("Group ID unavailable");
        }
        return $group_id;
    }

    /**
     * Gets information about the group that the message was sent from
     */
    protected function getGroupInfo($group_id = 0) {
        if ($this->group == null) {
            $group_id = $group_id == 0 ? $this->getGroupID() : $group_id;
            $this->group = json_decode($this->gm->groups->show($group_id))->response;
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
     * about them. There MUST be an exact match, or false will be returned.
     *
     * @param string $name the name
     *
     * @return mixed member object if found, or false
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
     * Searches the group members for a member having the given user_id, and returns information
     * about them.
     *
     * @param int $id user_id
     *
     * @return mixed member object if found, or false
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
     *
     * @return mixed member object if found, or false
     */
    protected function searchMemberByName($partial_name) {
        $partial_name = str_replace("@", "", $partial_name);
        // Exact Match search
        foreach ($this->getGroupMembers() as $member) {
            if (strtolower($member->nickname) === strtolower($partial_name)) {
                $this->logger->debug(sprintf("Found user '%s' searching for '%s'", $member->nickname, $partial_name));
                return $member;
            }
        }

        // "Starts With"  search
        foreach ($this->getGroupMembers() as $member) {
            if (strrpos($member->nickname, $partial_name, -strlen($member->nickname)) !== FALSE) {
                $this->logger->debug(sprintf("Found user '%s' searching for '%s'", $member->nickname, $partial_name));
                return $member;
            }
        }

        // case-sensitive "contains" search
        foreach ($this->getGroupMembers() as $member) {
            if (strpos($member->nickname, $partial_name) !== false) {
                $this->logger->debug(sprintf("Found user '%s' searching for '%s'", $member->nickname, $partial_name));
                return $member;
            }
        }

        // case-insensitive "contains" search
        foreach ($this->getGroupMembers() as $member) {
            if (stripos($member->nickname, $partial_name) !== false) {
                $this->logger->debug(sprintf("Found user '%s' searching for '%s'", $member->nickname, $partial_name));
                return $member;
            }
        }


        return $this->BOGUS_GROUPME_USER;
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
                $seq = array_values(array_filter(preg_split("/[^a-z0-9-\/ ()]/i", substr($message, $p))))[0];
                $i = strpos($message, " ", $p);
                $i = ($i === false) ? strlen($message) : $i;
                $loci[] = array($p, $p + strlen($seq));
            }

            // Extract the string between each loci, and use it to search for users
            foreach ($loci as $l) {
                $str = substr($message, $l[0] + strlen($needle), $l[1] - $l[0]);
                // If there are spaces, do a number of searches
                $user = false;
                if (strpos($str, " ") !== false) {
                    $tokens = array_merge(array($str), explode(" ", $str));
                    foreach ($tokens as $token) {
                        $user = $this->searchMemberByName($token);
                        if ($user !== false) {
                            break;
                        }
                    }
                }
                else {
                    $user = $this->searchMemberByName($str);
                }

                if ($user !== false && $user->user_id != -1) {
                    $mentions->loci[] = array($l[0], strlen($str) + 1);
                    $mentions->user_ids[] = $user->user_id;
                }
            }

            if (sizeof($mentions->user_ids) > 0) {
                return $mentions;
            }
        }
    }

    /**
     * Enable or disable group-wide notifications
     *
     * @param boolean $on true to enable, false to disable
     */
    protected function enableNotifications($on = true) {
        $this->gm->groups->update($this->getGroupID(), array("office_mode" => !$on));
    }

    /**
     * Upload and change the image for the group.
     *
     * @param $image_url URL to the image.
     */
    protected function changeGroupImage($image_url) {
        $img = $this->gm->images->pictures($image_url);
        $img = json_decode($img)->payload;
        $this->gm->groups->update($this->getGroupID(), array("image_url" => $img->url));
    }

    /**
     * Change the name of the token account in the group
     *
     * @param string $name name to change to
     */
    protected function changeName($name) {
        $this->gm->members->update($this->getGroupID(), $name);
    }

    protected function removeMember($id) {
        foreach ($this->getGroupMembers() as $member) {
            if ($member->user_id == $id) {
                $this->gm->members->remove($this->getGroupID(), $member->id);
                $this->logger->info(sprintf("%s was removed from %s", $member->nickname, $this->getGroupInfo()->name));
            }
        }   
    }

    protected function addMember($user_id, $nickname) {
        $response = json_decode(utf8_decode($this->gm->members->add($this->getGroupID(), array("members" => array(
            array(
                "nickname" => $nickname,
                "user_id" => $user_id
            )
        )))))->response;

        $this->logger->info(sprintf("Add request %s for %s to %s was sent.", $response->results_id, $user_id, $this->getGroupID()));
    }

    protected function padding($length, $char = ".") {
        $pad = "";
        while ($length > 0) {
            $pad .= $char;
            $length--;
        }
        return $pad;
    }
}
?>
