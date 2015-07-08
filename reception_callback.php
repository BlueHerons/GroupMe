<?php
require("vendor/autoload.php");

use \BlueHerons\GroupMe\Bots\WelcomeRoomBot;

$bot = new WelcomeRoomBot(/* Put bot ID here */);
$bot->listen();
?>
