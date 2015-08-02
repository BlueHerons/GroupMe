<?php
require("vendor/autoload.php");

$payload = json_decode(file_get_contents("php://input"));
$bot_id = $_GET['bot'];

if (empty($payload) || empty($bot_id)) {
    die("No data");
}

$config = json_decode(file_get_contents("config.json"));

if (!isset($config->bots->{$bot_id})) {
    die("Invalid bot id.");
}

$global_config = $config;
$config = $config->bots->{$bot_id};

$gm_token = $global_config->token;
$class = $config->bot;

$bot = new $class($gm_token, $bot_id);
$bot->listen();
?>
