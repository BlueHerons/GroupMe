<?php
define("GROUPME_BOT_URL", "https://api.groupme.com/v3/bots/post");
define("MESSAGE", "Hi there! Please let us know your agent name and area of operations so we can arrange a meetup with you and get you added to more appropriate chat rooms.");

$text = file_get_contents("php://input");
$data = json_decode($text, true);

if ($data['system'] == 1 && strstr($data['text'], "has joined the group")) {
	sendMessage(MESSAGE, "");
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
