<?php
// Quick and dirty GroupMe bot script to relay messages from one chat to another.
// Two bots are required: one that "listens" to chat A, and another that broadcasts to chat B.
//
// Host this script on a webserver, and set the callback URL of the GroupMe bot to URL of this file, and
// ?bot_id=listening_bot_id appended.

define("GROUPME_BOT_URL", "https://api.groupme.com/v3/bots/post");

$map = array(
  // This key is the "listening" bot in chat A -- aka, the one that has the URL to this file in its configuration
  // This one script can be the configuration for many bots.
  "listening_bot_id" => array(
    // This key is a regular expression to match incoming messages against
    "/[eE]8/" => array(
      // These keys are the IDs of the bots that should send the message if it matches the above regex
      "broadcast_bot_id"
    )
  ),
  "listening_bot_2_ id" => array(
    "regex" => array(
      "broadcast_bot_2_id",
      "boradcast_bot_3_id"
    )
  )
);

if (isset($_REQUEST['bot_id']) && array_key_exists($_REQUEST['bot_id'], $map)) {
    $rules = $map[$_REQUEST['bot_id']];

    $payload = file_get_contents("php://input");
    $json = json_decode($payload);

    logMsg("Payload recieved: " . $payload);

    foreach ($rules as $condition => $bot_id) {
        if (preg_match($condition, $json->text) === 1) {
            logMsg("\"" . $json->text . "\" matches pattern: " . $condition);
            foreach ($bot_id as $bot) {
                logMsg("Sending message via bot: " . $bot);
                sendMessage(sprintf("%s: %s", $json->name, $json->text), $bot);
            }
        }
    }

    logMessage("Complete");
}
else {
    die("Invalid parameters");
}

function logMsg($msg) {
    file_put_contents("proxy.log", "[" . date("Y-m-d H:i:s") . "] " . $msg .                                                                        PHP_EOL, FILE_APPEND);
}

function sendMessage($msg, $bot_id) {
        $payload = new stdClass();
        $payload->text = $msg;
        $payload->bot_id = $bot_id;

        $payloadStr = json_encode($payload);

        $url = GROUPME_BOT_URL;

        $hndl = curl_init();
        curl_setopt($hndl, CURLOPT_URL, $url);
        curl_setopt($hndl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($hndl, CURLOPT_POSTFIELDS, $payloadStr);
        curl_setopt($hndl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($hndl, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "Content-Length: " .strlen($payloadStr)
        ));

        $result = curl_exec($hndl);
        curl_close($hndl);
}

?>
