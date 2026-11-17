<?php
require "vendor/autoload.php";

define("APP_ID", "629815533892815");
define("APP_SECRET", "f26c315139206cbc69545d6a3b374e05");
define("APP_NAMESPACE", "unifato_feed");

use \Mute\Facebook\App;

$defaults = [
  'user' => 'envato',
  'limit' => 3,
];

header('Content-type: text/json');
try {
  $options = $_GET;
  $options = array_merge( $defaults, $options);

  $customApp = new App(APP_ID, APP_SECRET, APP_NAMESPACE);
  $response = $customApp->batch(function($app) use ( $options ) {
    $app->get( $options['user'] . '/feed', $options);
    $app->get( $options['user'] , array('fields'=>'name,username,picture'));
  });
  echo json_encode( $response );
} catch( Exception $e ){
  $err = array(
    'error'   => 1,
    'file' => $e->getFile(),
    'line' => $e->getLine(),
    'message' => $e->getMessage(),
  );
  echo json_encode( $err );
}
