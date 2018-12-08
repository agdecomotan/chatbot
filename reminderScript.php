<?php
global $argv;
$senderId = $argv[1];
$accessToken = $argv[2]; 
$user = $argv[3];
$delay = $argv[4];
$message = $argv[5];
sleep((int)$delay);
$messageToSend = [
    'recipient' => [ 'id' => $senderId  ],
    'message' =>["attachment"=>[
    "type"=>"template",
    "payload"=>[
        "template_type"=>"generic",
        "elements"=>[
        [
            "title"=>"Hey ".$user.", It's time!",
            "subtitle"=> urldecode($message)
        ]
        ]
    ]
    ]]]; 
$ch = curl_init('https://graph.facebook.com/v2.11/me/messages?access_token='.$accessToken);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageToSend));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$result = curl_exec($ch);
curl_close($ch);