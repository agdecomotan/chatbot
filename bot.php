<?php
require 'library/twitteroauth/autoload.php';

use Abraham\TwitterOAuth\TwitterOAuth;

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
    $consumer = "XixVwN9KqQrFlFnVWN2AN6z7t";
	$consumersecret = "2NLUfCxpjeIlOq7ZeQHErYiafUIBzkv2biTYM36UkbqlvfoMf0";
	$access_token = "426150797-o7EVUFe61K9Z8D8khiNiDtVseS1H2STgj7XVXnOd";
	$access_tokensecret = "4YhheEa8hZbRxIcJ6awnlhIsTlxmiwPN5D2DEVmBR68Eh";
	$connection = new TwitterOAuth($consumer, $consumersecret, $access_token, $access_tokensecret);
    $content = $connection->get("account/verify_credentials");
    if($content){
		if($content->errors){
			$answer = $content->errors[0]->message;
		}else{
            session_start();
            $hashtags = [];
            $get_hashtags = [];
            $updated_hashtags = [];
            $cancelled_hashtags = [];
            if (strpos($command, 'LISTEN #') !== false) {
                $hashtags =  array_unique(beliefmedia_hashtags($command));
                $_SESSION['hashtags'] = $hashtags;
                
                $answer =  "You are now listening to ".implode(', ',$hashtags)." tweets.";
            }else if (strpos($command, 'CANCEL #') !== false) {
                $get_hashtags =  array_unique(beliefmedia_hashtags($command));
                $old_hashtags = $_SESSION['hashtags'];
                $hashtags = array_diff($old_hashtags, $get_hashtags);
                $_SESSION['hashtags'] = $hashtags;
                $cancelled_hashtags = array_diff($get_hashtags, $old_hashtags);
                
                $answer =  "You are no longer listening to ".implode(', ',$get_hashtags)." tweets.";
            }

            $hashtags = ' '.implode(' OR ',$hashtags).' ';
            $tweets = $connection->get("search/tweets", [
                "q" => $hashtags, // 'result_type' => 'recent', // "count"=> 1
            ]);
            
            if($tweets){
                foreach ($tweets as $key => $tweet) {
                    if($key == 'statuses'){
                        $elements = [];
                        foreach ($tweet as $keyT => $t) {
                            $item_url = "https://twitter.com/".$t->user->screen_name."/status/".$t->id_str;
                            $elements[$keyT]['title'] = $t->user->name.' (@'.$t->user->screen_name.')';
                            $elements[$keyT]['item_url'] = $item_url;
                            $elements[$keyT]['image_url'] = $t->user->profile_image_url_https;
                            $elements[$keyT]['subtitle'] = $t->text;
                            $elements[$keyT]['buttons'][] = array('type' => "web_url",'url' => $item_url,'title' => 'View Website');
                        }
                    }
                }
                if($elements){
                    $answer = "A tweet matched ".implode(', ',$_SESSION['hashtags']).":";
                    $answer = ["attachment"=>[
                        "type"=>"template",
                        "payload"=>[
                        "template_type"=>"list",
                        "elements"=> $elements
                        ]
                    ]];
                }
            }else{
                $answer = "No tweets found.";
            }
        }
	}else{
		$answer = "Invalid!";
	}
    
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