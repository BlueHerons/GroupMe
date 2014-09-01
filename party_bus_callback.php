<?php
define("GROUPME_BOT_URL", "https://api.groupme.com/v3/bots/post");
define("GROUPME_BOT_TOKEN", "");

$text = file_get_contents("php://input");
$data = json_decode($text, true);

$command = $data['text'];

// All commands start with a /
if (preg_match("/^\//", $command)) {
	$matches = array();
	if (preg_match("/^\/help/", $command)) {
		$message = "I'm the Party Bus bot. Here are the commands I understand:\n".
			   "  /mods      List the moderators of this chat\n".
		           "  /rules     List the rules of the Bus\n".
			   "  /roll xDy  Rolls x dice with y faces\n".
			   "  /help      Show a list of commands\n".
			   "\n".
			   "John (CaptCynicism) is my owner. Contact him if I malfunction";
		sendMessage($message, GROUPME_BOT_TOKEN);
	}
	else if (preg_match("/^\/mods/", $command)) {
		$message = "The moderators of this group are: \n" .
			   "  Aaron (horde)\n" .
                           "  JACKIE GROVE (FinneganJax)\n" .
                           "  John (CaptCynicism)\n" .
                           "  Mark Rice (Realmako)\n" .
                           "  Paul (R0CKnU)\n" .
                           "  Ranee (A0D42)\n" .
                           "  Sarah (sahararomeo)";
		sendMessage($message, GROUPME_BOT_TOKEN);
	}
	else if (preg_match("/^\/rules/", $command)) {
		if (preg_match("/^\/rules nominations?/", $command)) {
			$message = "Rules for nominations to the Bus:\n".
			           "  Format: Nomination: [a name we can recognize] - [email for GroupMe]\n".
			           "\n".
			           "  1) If you have met someone, you may nominate - open to all.\n".
			           "  2) Once you nominate, heart the nomination you just made.\n".
                                   "  3) Heart nominations for people you have met and can vouch for - open to all.\n".
                                   "  4) A moderator will add once 5 hearts has been reached.\n".
                                   "\n".
                                   "  Only a moderator may add a new person.";
			sendMessage($message, GROUPME_BOT_TOKEN);
		}
		else {
			$message = "There are a lot of rules. Which do you want to see?\n".
			           "  /rules nomination[s]      See the rules for adding new people for the bus".
			           "\n\n".
			           "All rules are open for changing. Simply suggest a change and we -- the whole bus -- can discuss it";
			sendMessage($message, GROUPME_BOT_TOKEN);
		}
	}
	else if (preg_match("/^\/roll ([0-9])[Dd](100|%|[0-9]{1,2})/", $command, $matches)) {
		$message = print_r($matches, true);
		// makes d00 = d100, adds d% as an alias
		if($matches[2] == "00" || $matches[2] == "%")
			$matches[2] = 100;
		$result = "";
		for ($i = 0; $i < $matches[1]; $i++) {
			$result .= rand(1, $matches[2]) . " ";
		}
		sendMessage($result, GROUPME_BOT_TOKEN);
	}
	else {
		//sendMessage("Invalid command", GROUPME_BOT_TOKEN);
	}
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
