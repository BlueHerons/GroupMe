<?php
namespace BlueHerons\GroupMe\Bots;

define("GROUPME_BOT_URL", "https://api.groupme.com/v3/bots/post");
define("GROUPME_BOT_TOKEN", "");

require("../vendor/autoload.php");

class PartyBusBot {

	public function listen() {
		$input = file_get_contents("php://input");
		$input = json_decode($input, true);
		$command = $input['text'];
		$matches = array();

		if (preg_match("/^\//", $command)) {
			$matches = array();
			if (preg_match("/^\/help$/", $command)) {
				$this->help();
			}
			else if (preg_match("/^\/mods$/", $command)) {
				$this->mods();
			}
			else if (preg_match("/^\/rules\s?([A-Za-z\s]+)?$/", $command, $matches)) {
				$this->rules($matches[1]);
			}
		}
	}

	public function help() {
		$message = "I'm the Party Bus bot. Here are the commands I understand:\n".
			   "  /mods      List the moderators of this chat\n".
		           "  /rules     List the rules of the Bus\n".
			   "  /roll xDy  Rolls x dice with y faces\n".
			   "  /help      Show a list of commands\n".
			   "\n".
			   "John (CaptCynicism) is my owner. Contact him if I malfunction";
		$this->sendMessage($message);
	}

	public function mods() {
		$message = "The moderators of this group are: \n" .
			   "  Aaron (horde)\n" .
                           "  JACKIE GROVE (FinneganJax)\n" .
                           "  John (CaptCynicism)\n" .
                           "  Mark Rice (Realmako)\n" .
                           "  Paul (R0CKnU)\n" .
                           "  Ranee (A0D42)\n" .
                           "  Sarah (sahararomeo)";
		$this->sendMessage($message);
	}

	public function rules() {
		$args = func_get_args();
		if (sizeof($args) != 0)
			$args = $args[0];

		$message = "";
		if (sizeof($args) == 0) {
			$message = "There are a lot of rules. Which do you want to see?\n".
			           "  /rules nomination[s]      See the rules for adding new people for the bus".
			           "\n\n".
			           "All rules are open for changing. Simply suggest a change and we -- the whole bus -- can discuss it";

		}
		else if (preg_match("/^nominations?$/", $args)) {
			$message = "Rules for nominations to the Bus:\n".
			           "  Format: Nomination: [a name we can recognize] - [email for GroupMe]\n".
			           "\n".
			           "  1) If you have met someone, you may nominate - open to all.\n".
			           "  2) Once you nominate, heart the nomination you just made.\n".
                                   "  3) Heart nominations for people you have met and can vouch for - open to all.\n".
                                   "  4) A moderator will add once 5 hearts has been reached.\n".
                                   "\n".
                                   "  Only a moderator may add a new person.";
		}
		$this->sendMessage($message);
	}

	public function roll() {
		$args = func_get_args();
		if (sizeof($args) != 0)
			$args = $args[0];

		$message = "";
		if (sizeof($args) == 0) {
			$this->sendMessage("ack");
		}
		else {
			// makes d00 = d100, adds d% as an alias
			if($args[2] == "00" || $args[2] == "%")
				$args[2] = 100;
			for ($i = 0; $i < $args[0]; $i++) {
				$message .= rand(1, $args[2]) . " ";
			}
		}

		$this->sendMessage($message);
	}

	private function sendMessage($msg) {
	        $payload = new \stdClass();
	        $payload->text = $msg;
		$payload->bot_id = GROUPME_BOT_TOKEN;

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
}

$bot = new PartyBusBot();
$bot->listen();
?>
