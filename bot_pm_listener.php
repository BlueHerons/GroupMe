<?php

require("vendor/autoload.php");

use BlueHerons\GroupMe\Bots\PMBot;
use Katzgrau\KLogger\Logger;
use Psr\Log\LogLevel;

// This script is intended to be run on a schedule. It is NOT triggered by an action from the
// GroupMe API.
//
// On each trigger, it will retrieve the last X direct messages
//
// If the user id of the recipient of the direct message is the user id that sent the last message
// in the direct message, the script will fire.
//
// IT IS IMPORTANT THAT THE BOT SENDS A REPLY, SO THAT THE "LAST MESSAGE SENDER ID" IS NOT THE USER
// ID OF THE RECIEPENT OF THE DIRECT MESSAGE, ELSE THE SCRIPT WILL GET CAUGHT IN A LOOP

// Number of DMs to retrieve per execution
define("DM_COUNT", 10);

$config = json_decode(file_get_contents("config.json"));

// This endpoint is undocumented in the GroupMe API. It was reverse engineered from the web client.
// Only direct message conversations are returned.
function getGroupMeDirectMessages() {
    global $config;
    $url = "https://v2.groupme.com/chats?token=" . $config->token . "&page=1&per_page=" . DM_COUNT;

    $hndl = curl_init();
    curl_setopt($hndl, CURLOPT_URL, $url);
    curl_setopt($hndl, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($hndl, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($hndl);
    curl_close($hndl);

    return json_decode($result);
}

$data = getGroupMeDirectMessages();

// Use a GroupMe API client for documented API calls
$gm = new GroupMePHP\groupme($config->token);

$logger = new Logger("logs", LogLevel::DEBUG, array(
    "extension" => "log",
    "logFormat" => "[listen - {date}] [{level}] {message}"
));
//$logger->setLogLevelThreshold($config->log);

if ($data->meta->code != 200) {
    // Ignore some codes
    if ($data->meta->code == 408) { // Timeout
        // Do nothing
    }
    else {
        $logger->debug(sprintf("An error occured getting chat list (%s)", print_r($data, true)));
    }
    die();
}

foreach ($data->response->chats as $chat) {
    if ($chat->last_message->sender_id == $chat->other_user->id) {
        // Parse commands like a normal bot
        $bot = new PMBot($config->token);
        $bot->listen();
    }
}
