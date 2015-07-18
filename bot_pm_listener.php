<?php

require("vendor/autoload.php");

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

// GroupMe API Token
define("API_TOKEN", "");
// GroupMe User ID to notify
define("NOTIFY_ID", "");
// Number of DMs to retrieve per execution
define("DM_COUNT", 10);

// This endpoint is undocumented in the GroupMe API. It was reverse engineered from the web client.
// Only direct message conversations are returned.
$url = "https://v2.groupme.com/chats?token=" . API_TOKEN . "&page=1&per_page=" . DM_COUNT;

$hndl = curl_init();
curl_setopt($hndl, CURLOPT_URL, $url);
curl_setopt($hndl, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($hndl, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($hndl);
curl_close($hndl);

$data = json_decode($result);

// Use a GroupMe API client for documented API calls
$gm = new GroupMePHP\groupme(API_TOKEN);

$logger = new Logger("logs", LogLevel::DEBUG, array(
    "extension" => "log",
    "logFormat" => "[{date} Bot PM Listener] [{level}] {message}"
));

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
        $gm->directmessages->create(array(
            "source_guid" => uniqid(),
            "recipient_id" => $chat->other_user->id,
            "text" => "I am a bot. I do not yet understand commands sent via direct message."
        ));

        if (defined("NOTIFY_ID")) {
           $gm->directmessages->create(array(
               "source_guid" => uniqid(),
               "recipient_id" => NOTIFY_ID,
               "text" => sprintf("[Bot PM Listener] %s sent a message: %s", $chat->other_user->name, $chat->last_message->text)
           ));
        }
    }
}
