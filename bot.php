<?php
require 'library/twitteroauth/autoload.php';

use Abraham\TwitterOAuth\TwitterOAuth;

$hubVerifyToken = 'reminddeertoken';
$accessToken =   "EAAFDrdjEq7QBAIaR9Ws1ViFjn7LEyXYADRzXqZB5UPjp9SKxwVyKSzvqFz4T1m0lyQJabdqu3Lrr5Sxsg8fzkFHcCgnETBAiA2XFMQ20HtTgfmtKmgVn0ZAuUu2GZAWvrbqBZC7EXb7Hv5fVfaLUQL2pCMFdW9YIlmFXNLTcjQZDZD";

$consumer = "XixVwN9KqQrFlFnVWN2AN6z7t";
$consumersecret = "2NLUfCxpjeIlOq7ZeQHErYiafUIBzkv2biTYM36UkbqlvfoMf0";
$access_token = "426150797-o7EVUFe61K9Z8D8khiNiDtVseS1H2STgj7XVXnOd";
$access_tokensecret = "4YhheEa8hZbRxIcJ6awnlhIsTlxmiwPN5D2DEVmBR68Eh";
$connection = new TwitterOAuth($consumer, $consumersecret, $access_token, $access_tokensecret);
$content = $connection->get("account/verify_credentials");
function beliefmedia_hashtags($string) {
 	preg_match_all('/#(\w+)/', $string, $matches);
  	foreach ($matches[1] as $match) {
    	$keywords[] = '#'.$match;
  	} 
 	return (array) $keywords;
}

function callAPI($accessToken,$response,$input){
    $ch = curl_init('https://graph.facebook.com/v2.6/me/messages?access_token='.$accessToken);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($response));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    if(!empty($input)){
      $result = curl_exec($ch);
    }
}

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

$response_tweet = false;

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
  elseif(substr($command, 0, 8) === "listen #") {
		$get_hashtag =  array_unique(beliefmedia_hashtags($command));
		$hashtag = $get_hashtag[0];
		$answer =  "You are now listening to ".$hashtag." tweets.";
		
		$response = [
            'recipient' => [ 'id' => $senderId ],
            'message' => [ 'text' => $answer ]
        ];
        callAPI($accessToken,$response,$input);
		
		$tweets = $connection->get("search/tweets", [
			"q" => $hashtag,
			"include_entities"=>true,
			"count"=> 10
		]);
		
		if($tweets){
			$attachment = null;
			
			foreach ($tweets as $key => $tweet) {
				if($key == 'statuses'){
					$elements = [];
					$counter = 0;
					foreach ($tweet as $keyT => $t) {
						$item_url = "https://twitter.com/".$t->user->screen_name."/status/".$t->id_str;
						$elements[$counter]['title'] = $t->user->name.' (@'.$t->user->screen_name.')';
						$elements[$counter]['item_url'] = $item_url;
						$elements[$counter]['image_url'] = str_replace('_normal', '', $t->user->profile_image_url_https);
						$elements[$counter]['subtitle'] = $t->text;
						$elements[$counter]['buttons'][] = array('type' => "web_url",'url' => $item_url,'title' => 'View Tweet');
						$counter++;
					}
				}
			}
			if($elements){
				$answer = "A tweet matched ".$hashtag.":";
				$response = [
                    'recipient' => [ 'id' => $senderId ],
                    'message' => [ 'text' => $answer ]
                ];
                callAPI($accessToken,$response,$input);
                
				$attachment = ["attachment"=>[
					"type"=>"template",
					"payload"=>[
					 "template_type"=> "generic",
					 "image_aspect_ratio"=> "horizontal",
					"elements"=> $elements
					]
				]];
				$response_tweet = true;
			}else{
			    $answer = "No tweets found.";
    			$response = [
                    'recipient' => [ 'id' => $senderId ],
                    'message' => [ 'text' => $answer ]
                ];
                callAPI($accessToken,$response,$input);
			}
		}else{
			$answer = "No tweets found.";
			$response = [
                'recipient' => [ 'id' => $senderId ],
                'message' => [ 'text' => $answer ]
            ];
            callAPI($accessToken,$response,$input);
		}
	} elseif(substr($command, 0, 8) === "cancel #") {	
    $get_hashtag =  array_unique(beliefmedia_hashtags($command)); 
    $hashtag = $get_hashtag[0];		
    $answer =  "You are no longer listening to ".implode(', ',$get_hashtags)." tweets.";	
  } elseif (substr($command, 0, 6) === "cancel") {
    $num = substr($command, 6);
    $handle = fopen($reminder, "r");
    $lineNumber = 1;
    $selectedLine = "";
    if ($handle) {
        while (($line = fgets($handle)) !== false) {        
          if($lineNumber == $num)
          {
            $selectedLine = $line; 
            break;
          }
          $lineNumber++;
        }    
        fclose($handle);
    } 
 
    $output = shell_exec('crontab -l');
    $cronjob = shell_exec('crontab -l | grep -i '.str_replace(" ","+",$selectedLine));
    $newcron = str_replace($cronjob,"",$output);
    
    file_put_contents('crontab.txt', $newcron.PHP_EOL);     
    shell_exec('crontab crontab.txt');     

    $contents = file_get_contents($reminder);
    $contents = str_replace($selectedLine, '', $contents);
    file_put_contents($reminder, $contents);
  
    $answer = 'Canceled reminder about '.$selectedLine;
  } elseif ($command == "crontab") {
    $answer = shell_exec('crontab -l');
  } elseif ($command == "deletereminders") {    
    shell_exec('crontab -r');
    shell_exec('crontab -e');
    file_put_contents($reminder, "");
    $answer = "Remove all reminders.";
  } else{
    $answer = "Command not found.";     
  }
}

if($record == true)
{
	fwrite($historyFile, "\n". $messageText);
	fclose($historyFile);
}


if($response_tweet){
    $response = [
        'recipient' => [ 'id' => $senderId ],
        'message' =>  $attachment
    ];    
}else{
    $response = [
        'recipient' => [ 'id' => $senderId ],
        'message' => [ 'text' => $answer ]
    ];
}

callAPI($accessToken,$response,$input);
curl_close($ch);