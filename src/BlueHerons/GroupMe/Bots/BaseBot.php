<?php
namespace BlueHerons\GroupMe\Bots;

define("GROUPME_BOT_URL", "https://api.groupme.com/v3/bots/post");

abstract class BaseBot {

    public function __construct($token) {
        $this->token = $token;
    }

    // Base listen method. Returns message object
    public abstract function listen();

    protected function isSystemMessage() {
        return $this->getInput()['system'] === true;
    }

    protected function getInput() {
        $input = file_get_contents("php://input");
        $input = json_decode($input, true);
        return $input;
    }

     protected function sendMessage($msg) {
        $payload = new \stdClass();
	$payload->text = $msg;
	$payload->bot_id = $this->token;

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

        sleep(1);
	$result = curl_exec($hndl);
	curl_close($hndl);
    }
}
?>
