<?php

// parameters

$hubVerifyToken = 'reminddeertoken';

$accessToken =   "EAAFDrdjEq7QBAIaR9Ws1ViFjn7LEyXYADRzXqZB5UPjp9SKxwVyKSzvqFz4T1m0lyQJabdqu3Lrr5Sxsg8fzkFHcCgnETBAiA2XFMQ20HtTgfmtKmgVn0ZAuUu2GZAWvrbqBZC7EXb7Hv5fVfaLUQL2pCMFdW9YIlmFXNLTcjQZDZD";

// check token at setup

if ($_REQUEST['hub_verify_token'] === $hubVerifyToken) {

  echo $_REQUEST['hub_challenge'];

  exit;

}

// handle bot's anwser

$input = json_decode(file_get_contents('php://input'), true);

$senderId = $input['entry'][0]['messaging'][0]['sender']['id'];

$messageText = $input['entry'][0]['messaging'][0]['message']['text'];

$response = null;

//set Message

if($messageText == "hi") {

    $answer = "Welcome to remindeer!";

}

//send message to facebook bot

$response = [

    'recipient' => [ 'id' => $senderId ],

    'message' => [ 'text' => $answer ]

];

$ch = curl_init('https://graph.facebook.com/v2.6/me/messages?access_token='.$accessToken);

curl_setopt($ch, CURLOPT_POST, 1);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($response));

curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

if(!empty($input)){

$result = curl_exec($ch);

}

curl_close($ch);