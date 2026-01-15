<?php
// ================= CONFIG =================
$BOT_TOKEN = getenv("BOT_TOKEN"); // Render Environment Variable me set karo
$API = "https://api.telegram.org/bot$BOT_TOKEN/";

$REQUEST_GROUP = -1003083386043; 
$SOURCE_CHANNELS = [-1003251791991,-1003181705395,-1002964109368,-1002831605258];
$TARGET_CHANNELS = [$REQUEST_GROUP, -1003181705395];

define('MAIN_CHANNEL','@EntertainmentTadka786');
define('REQUEST_CHANNEL','@EntertainmentTadka7860');
define('THEATER_CHANNEL','@threater_print_movies');
define('BACKUP_CHANNEL_USERNAME','@ETBackup');

$USER_REQUESTS_FILE = "user_requests.json";
$WAITING_FILE = "waiting_users.json";

$update = json_decode(file_get_contents("php://input"), true);
if(!$update) exit;

// ==========================================
// Bot function
function bot($method,$data=[]){
    global $API;
    $ch=curl_init();
    curl_setopt($ch,CURLOPT_URL,$API.$method);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
    $res=curl_exec($ch);
    if(!$res) file_put_contents("error.log","Curl Error: ".curl_error($ch).PHP_EOL, FILE_APPEND);
    curl_close($ch);
    return json_decode($res,true);
}

// Send message
function sendMessage($chat_id, $text, $keyboard=null, $parse_mode='HTML'){
    $data = ['chat_id'=>$chat_id,'text'=>$text,'parse_mode'=>$parse_mode];
    if($keyboard) $data['reply_markup'] = json_encode($keyboard);
    return bot('sendMessage',$data);
}

// Read CSV
function read_csv($file){
    if(!file_exists($file)) return [];
    $rows = array_map('str_getcsv', file($file));
    $header = array_shift($rows);
    $csv = [];
    foreach($rows as $row) $csv[] = array_combine($header,$row);
    return $csv;
}

// Typing realism
function realistic_typing($chat_id,$text){
    $chars = strlen($text);
    $delay = min(5,max(1,$chars*0.05));
    bot('sendChatAction',['chat_id'=>$chat_id,'action'=>'typing']);
    sleep($delay);
}

// Auto backup
function auto_backup(){
    $files = ['movies.csv','waiting_users.json'];
    foreach($files as $f){
        bot('sendDocument',['chat_id'=>BACKUP_CHANNEL_USERNAME,'document'=>new CURLFile($f)]);
    }
}

// ==========================================
// Handle Channel Post
if(isset($update['channel_post'])){
    $chat_id = $update['channel_post']['chat']['id'];
    if(!in_array($chat_id,$SOURCE_CHANNELS)) exit;

    $message_id = $update['channel_post']['message_id'];
    $caption = $update['channel_post']['caption'] ?? $update['channel_post']['text'] ?? '';
    $movie_name = trim(explode("\n",$caption)[0]);
    if(strlen($movie_name)<3) exit;

    // Append to CSV
    $f = fopen("movies.csv","a");
    fputcsv($f,[$movie_name,$message_id,$chat_id]);
    fclose($f);

    // Forward to targets
    global $TARGET_CHANNELS;
    foreach($TARGET_CHANNELS as $target){
        bot('forwardMessage',['chat_id'=>$target,'from_chat_id'=>$chat_id,'message_id'=>$message_id]);
    }

    // Notify waiting users
    $waiting_users = file_exists($WAITING_FILE) ? json_decode(file_get_contents($WAITING_FILE),true):[];
    $movie_lower = strtolower($movie_name);
    if(!empty($waiting_users[$movie_lower])){
        foreach($waiting_users[$movie_lower] as $u){
            list($uc,$uid)=$u;
            sendMessage($uc,"🎉 Your requested movie <b>$movie_name</b> is now available!\n📢 Join: ".MAIN_CHANNEL);
        }
        unset($waiting_users[$movie_lower]);
        file_put_contents($WAITING_FILE,json_encode($waiting_users));
    }

    auto_backup();
    exit;
}

// ==========================================
// Handle User Message
if(isset($update['message'])){
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
    $text = trim($update['message']['text'] ?? '');
    if(!$text) exit;

    // Spam control
    $user_req = file_exists($USER_REQUESTS_FILE) ? json_decode(file_get_contents($USER_REQUESTS_FILE), true) : [];
    if(isset($user_req[$user_id]) && time()-$user_req[$user_id]<60){
        sendMessage($chat_id,"⏳ Wait 1 minute before next request.");
        exit;
    }
    $user_req[$user_id]=time();
    file_put_contents($USER_REQUESTS_FILE,json_encode($user_req));

    realistic_typing($chat_id,$text);

    // Commands
    if(strpos($text,'/')===0){
        $parts = explode(' ',$text);
        $command = strtolower($parts[0]);
        $params = array_slice($parts,1);

        if($command=='/start'){
            $welcome = "🎬 Welcome to Entertainment Tadka!\n\n📢 Type movie name (partial/full)\n🔔 Request group auto-notification included!\n\nJoin Channels:\n🍿 ".MAIN_CHANNEL."\n📥 ".REQUEST_CHANNEL."\n🎭 ".THEATER_CHANNEL."\n🔒 ".BACKUP_CHANNEL_USERNAME;
            $keyboard = [
                'inline_keyboard'=>[
                    [['text'=>'🔍 Search Movies','switch_inline_query_current_chat'=>''],['text'=>'🍿 Main Channel','url'=>'https://t.me/EntertainmentTadka786']],
                    [['text'=>'📥 Requests','url'=>'https://t.me/EntertainmentTadka7860'],['text'=>'🎭 Theater Prints','url'=>'https://t.me/threater_print_movies']],
                    [['text'=>'🔒 Backup','url'=>'https://t.me/ETBackup']]
                ]
            ];
            sendMessage($chat_id,$welcome,$keyboard,'HTML');
            exit;
        }

        if($command=='/request'){
            $movie_name = trim(implode(' ',$params));
            if(!$movie_name){
                sendMessage($chat_id,"❌ Usage: /request Movie Name");
                exit;
            }

            $csv = read_csv('movies.csv');
            $found = false;
            foreach($csv as $row){
                if(strpos(strtolower($row['movie_name']),strtolower($movie_name))!==false){
                    $found=true; break;
                }
            }

            if($found){
                sendMessage($chat_id,"✅ Movie already available!");
                exit;
            }

            $waiting_users = file_exists($WAITING_FILE) ? json_decode(file_get_contents($WAITING_FILE),true):[];
            $movie_lower = strtolower($movie_name);
            if(!isset($waiting_users[$movie_lower])) $waiting_users[$movie_lower]=[];
            $waiting_users[$movie_lower][] = [$chat_id,$user_id];
            file_put_contents($WAITING_FILE,json_encode($waiting_users));

            $msg = sendMessage($chat_id,"🎉 Your request for \"$movie_name\" received!\n🔔 Notification when added.");
            sleep(15);
            bot("deleteMessage",["chat_id"=>$chat_id,"message_id"=>$msg['result']['message_id']]);
            exit;
        }
    }

    // Partial match
    $csv = read_csv('movies.csv');
    $matches = [];
    foreach($csv as $row){
        if(strpos(strtolower($row['movie_name']),strtolower($text))!==false){
            $matches[]=$row;
        }
    }

    if(!$matches){
        sendMessage($chat_id,"❌ Movie not found!");
        exit;
    }

    foreach($matches as $m){
        bot("forwardMessage",["chat_id"=>$chat_id,"from_chat_id"=>$m['channel_id'],"message_id"=>$m['message_id']]);
    }

    $msg = sendMessage($chat_id,"✅ ".count($matches)." movie(s) forwarded!");
    sleep(10);
    bot("deleteMessage",["chat_id"=>$chat_id,"message_id"=>$msg['result']['message_id']]);
}

// ==========================================
// Inline query suggestions
if(isset($update['inline_query'])){
    $query = strtolower($update['inline_query']['query']);
    $inline_id = $update['inline_query']['id'];
    $csv = read_csv('movies.csv');
    $results = [];
    foreach($csv as $row){
        if(strpos(strtolower($row['movie_name']),$query)!==false){
            $results[] = [
                'type'=>'article',
                'id'=>md5($row['movie_name']),
                'title'=>$row['movie_name'],
                'input_message_content'=>['message_text'=>"🎬 ".$row['movie_name']]
            ];
        }
    }
    bot('answerInlineQuery',['inline_query_id'=>$inline_id,'results'=>json_encode($results),'cache_time'=>0]);
}
?>
