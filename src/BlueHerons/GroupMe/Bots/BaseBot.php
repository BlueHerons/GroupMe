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
            $this->gconfig = $config;

            if (isset($config->bots->{$bot_id})) {
                $this->config = $config->bots->{$bot_id};
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
            $this->logger->debug("Config file doesnt exist");
            $this->config = (object) array(
                "bot" => get_class($this),
                "mods" => array(),
                "blacklist" => array()
            );
        }

        $me = json_decode($this->gm->users->index())->response;
        $this->user_id = $me->user_id;

        $this->saveConfig();
    }

    public abstract function listen();

    /**
     * Determines if the given user is an admin
     *
     * @param mixed $user user_id or name to search users for
     *
     * @return boolean
     */
    protected function isAdmin($user) {
        $id = is_numeric($user) ?
                $user :
                $this->searchMemberByName($user)->user_id;
        return in_array($id, $this->gconfig->admin);
    }

    /**
     * Determines if the given user is a mod
     *
     * @param mixed $user user_id or name to search users for
     *
     * @return boolean
     */
    protected function isMod($user) {
        $id = is_numeric($user) ?
                $user :
                $this->searchMemberByName($user)->user_id;
        if (sizeof($this->config->mods) == 0) {
            return true;
        }
        else {
            return in_array($id, $this->config->mods);
        }
    }

    /**
     * Returns true if the given user id is not on the blacklist.
     *
     * @param int $user user ID
     *
     * @return boolean
     */
    protected function isAuthorized($user) {
        if (in_array($user, $this->config->blacklist)) {
            $this->logger->info(sprintf("%s is blacklisted.", $user));
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
        $c->bots->{$this->bot_id} = $this->config;
        file_put_contents(self::CONFIG_FILE, json_encode($c, JSON_PRETTY_PRINT));
        $this->logger->debug("Config saved");
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
        foreach ($bots->response as $bot) {
            if (isset($this->gconfig->bots->{$bot->bot_id}) &&
                isset($this->gconfig->bots->{$bot->bot_id}->broadcast) &&
                $this->gconfig->bots->{$bot->bot_id}->broadcast) {
                $this->sendGroupMessage($message, $bot->group_id);
                $this->logger->info(sprintf("Broadcast sent to %s.", substr($bot->bot_id, 0, 6)));
            }
            else {
                $this->logger->info(sprintf("%s not configured for broadcast", substr($bot->bot_id, 0, 6)));
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
        $this->logger->debug(sprintf("Replying to sender with message: %s", $message));
        $message = sprintf("[%s] %s", $this->getGroupInfo()->name, $message);
        $this->gm->directmessages->create(array(
            "source_guid" => uniqid(),
            "recipient_id" => $this->getPayload()['sender_id'],
            "text" => $message));
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
            throw new \Exception("Group ID unavailable");
        }
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
                $seq = array_values(array_filter(preg_split("/[^a-z0-9 ()]/i", substr($message, $p))))[0];
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
                if ($user !== false) {
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
}
?>
