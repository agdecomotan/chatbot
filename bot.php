<?php

$hubVerifyToken = 'reminddeertoken';
$accessToken =   "EAAFDrdjEq7QBAIaR9Ws1ViFjn7LEyXYADRzXqZB5UPjp9SKxwVyKSzvqFz4T1m0lyQJabdqu3Lrr5Sxsg8fzkFHcCgnETBAiA2XFMQ20HtTgfmtKmgVn0ZAuUu2GZAWvrbqBZC7EXb7Hv5fVfaLUQL2pCMFdW9YIlmFXNLTcjQZDZD";


if ($_REQUEST['hub_verify_token'] === $hubVerifyToken) {
  echo $_REQUEST['hub_challenge'];
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$senderId = $input['entry'][0]['messaging'][0]['sender']['id'];
$messageText = $input['entry'][0]['messaging'][0]['message']['text'];
$response = null;
$command = strtolower($messageText);

$reminder = 'reminder';
$history = 'history';
$historyFile = fopen($history, 'a');
$data = file($history);

$lastMsg = $data[count($data)-1];  
$lastCommand = strtolower($lastMsg);
$record = true;

if($lastCommand == "remind"){
  $messageText = "what:" . $messageText;
  $answer = "When do you want to be reminded?";  
}
elseif(substr($lastCommand, 0, 5) === "what:") {
  $answer = "Okay! I will remind you about this on ";  
  $remindMsg = substr($lastMsg, 5);
    
  $reminderFile = fopen($reminder, 'a');
  $data = file($reminderFile);
  fwrite($reminderFile, $remindMsg . "\n");
  fclose($reminderFile); 
        
  $output = shell_exec('crontab -l');
  $cronCommand = '/usr/bin/php /var/www/html/chatbot/reminderScript.php '.$senderId.' '.$accessToken.' '.urlencode($remindMsg).' > /dev/null 2>/dev/null &';
  file_put_contents('crontab.txt', $output.'* * * * * '.$cronCommand.PHP_EOL);     
  shell_exec('crontab crontab.txt');  
}
else{
  if($command == "hi" or $command == "hello") {
    $answer = "Welcome to Remindeer!";
  }
  elseif ($command == "remind") {
    $answer = "What is the reminder about?";
  }
  elseif ($command == "list") {
    $answer = file_get_contents($reminder);
    $remindList = "";
    
    $handle = fopen($reminder, "r");
    $lineNumber = 1;
    if ($handle) {
        while (($line = fgets($handle)) !== false) {        
          if($line !== "")
          {
            $remindList = $remindList . "[ " . $lineNumber . " ] " . $line;
            $lineNumber++;
          }
        }    
        fclose($handle);
    } 
    
    $answer = $remindList;
  }
  elseif ($command == "last") {
    $answer = $lastMsg;
  }
  elseif ($command == "listen") {
    //INSERT TWITTER HASHTAG HERE
  }  
  else{
    $answer = "Command not found.";     
  }
}

if($record == true)
{
  fwrite($historyFile, "\n". $messageText);
  fclose($historyFile);
}


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