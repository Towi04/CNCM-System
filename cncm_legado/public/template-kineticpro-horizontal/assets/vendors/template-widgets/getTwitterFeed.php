<?php
require "vendor/autoload.php";
require "api_info.php";
use Abraham\TwitterOAuth\TwitterOAuth;

$queryArr = [
  "screen_name" => "envato",
  "count" => 3,
];

$getArr = $_GET;
$queryArr = array_merge( $queryArr, $getArr );

$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_KEY_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);
$content = $connection->get("account/verify_credentials");
$statuses = $connection->get("statuses/user_timeline", $queryArr);

header('Content-Type: text/json');
echo json_encode($statuses);

