<?php
// ==================================================
// CONFIG
// ==================================================

$BOT_TOKEN = getenv("BOT_TOKEN");
if(!$BOT_TOKEN){
    file_put_contents("error.log","BOT_TOKEN missing\n",FILE_APPEND);
    exit;
}

$API = "https://api.telegram.org/bot$BOT_TOKEN/";

$REQUEST_GROUP = -1003083386043;

$SOURCE_CHANNELS = [
    -1003251791991,
    -1003181705395,
    -1002964109368,
    -1002831605258
];

$TARGET_CHANNELS = [
    $REQUEST_GROUP,
    -1003181705395
];

define('MAIN_CHANNEL','@EntertainmentTadka786');
define('REQUEST_CHANNEL','@EntertainmentTadka7860');
define('THEATER_CHANNEL','@threater_print_movies');
define('BACKUP_CHANNEL_USERNAME','@ETBackup');

$MOVIES_CSV = "movies.csv";
$USER_REQUESTS_FILE = "user_requests.json";
$WAITING_FILE = "waiting_users.json";

// ==================================================
// UPDATE
// ==================================================
$update = json_decode(file_get_contents("php://input"), true);
if(!$update) exit;

// ==================================================
// BASIC FUNCTIONS
// ==================================================
function bot($method,$data=[]){
    global $API;
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$API.$method);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
    $res = curl_exec($ch);
    if(curl_error($ch)){
        file_put_contents("error.log",curl_error($ch).PHP_EOL,FILE_APPEND);
    }
    curl_close($ch);
    return json_decode($res,true);
}

function sendMessage($chat_id,$text,$keyboard=null){
    $data = [
        'chat_id'=>$chat_id,
        'text'=>$text,
        'parse_mode'=>'HTML'
    ];
    if($keyboard){
        $data['reply_markup']=json_encode($keyboard);
    }
    return bot("sendMessage",$data);
}

// ==================================================
// UTILITIES
// ==================================================
function read_csv($file){
    if(!file_exists($file)) return [];
    $rows = array_map('str_getcsv', file($file));
    if(count($rows)<2) return [];
    $header = array_shift($rows);
    $out=[];
    foreach($rows as $r){
        if(count($r)<3) continue;
        $out[]=[
            'movie_name'=>$r[0],
            'message_id'=>$r[1],
            'channel_id'=>$r[2]
        ];
    }
    return $out;
}

function csv_duplicate_exists($file,$movie,$msg,$channel){
    if(!file_exists($file)) return false;
    $rows = array_map('str_getcsv', file($file));
    array_shift($rows);
    foreach($rows as $r){
        if(
            strtolower(trim($r[0]))==strtolower(trim($movie)) &&
            $r[1]==$msg &&
            $r[2]==$channel
        ){
            return true;
        }
    }
    return false;
}

function realistic_typing($chat_id,$text){
    $delay = min(5,max(1,strlen($text)*0.05));
    bot("sendChatAction",[
        'chat_id'=>$chat_id,
        'action'=>'typing'
    ]);
    sleep($delay);
}

function auto_backup(){
    foreach(['movies.csv','waiting_users.json'] as $f){
        if(file_exists($f)){
            bot("sendDocument",[
                'chat_id'=>BACKUP_CHANNEL_USERNAME,
                'document'=>new CURLFile($f)
            ]);
        }
    }
}

// ==================================================
// 1️⃣ CHANNEL POST HANDLER
// ==================================================
if(isset($update['channel_post'])){

    $chat_id = $update['channel_post']['chat']['id'];
    if(!in_array($chat_id,$SOURCE_CHANNELS)) exit;

    $message_id = $update['channel_post']['message_id'];
    $caption = $update['channel_post']['caption']
        ?? $update['channel_post']['text']
        ?? '';

    $movie_name = trim(explode("\n",$caption)[0]);
    if(strlen($movie_name)<3) exit;

    if(!file_exists($MOVIES_CSV)){
        file_put_contents($MOVIES_CSV,"movie_name,message_id,channel_id\n");
    }

    if(!csv_duplicate_exists($MOVIES_CSV,$movie_name,$message_id,$chat_id)){
        $f=fopen($MOVIES_CSV,"a");
        fputcsv($f,[$movie_name,$message_id,$chat_id]);
        fclose($f);
    }

    foreach($TARGET_CHANNELS as $target){
        bot("forwardMessage",[
            'chat_id'=>$target,
            'from_chat_id'=>$chat_id,
            'message_id'=>$message_id
        ]);
    }

    $waiting = file_exists($WAITING_FILE)
        ? json_decode(file_get_contents($WAITING_FILE),true)
        : [];

    $key=strtolower($movie_name);
    if(!empty($waiting[$key])){
        foreach($waiting[$key] as $u){
            sendMessage(
                $u[0],
                "🎉 <b>$movie_name</b> ab available hai!\n📢 Join: ".MAIN_CHANNEL
            );
        }
        unset($waiting[$key]);
        file_put_contents($WAITING_FILE,json_encode($waiting));
    }

    auto_backup();
    exit;
}

// ==================================================
// 2️⃣ USER MESSAGE HANDLER
// ==================================================
if(isset($update['message'])){

    $chat_id=$update['message']['chat']['id'];
    $user_id=$update['message']['from']['id'];
    $text=trim($update['message']['text']??'');
    if(!$text) exit;

    $req=file_exists($USER_REQUESTS_FILE)
        ? json_decode(file_get_contents($USER_REQUESTS_FILE),true)
        : [];

    if(isset($req[$user_id]) && time()-$req[$user_id]<60){
        sendMessage($chat_id,"⏳ 1 minute ruk jao phir try karo.");
        exit;
    }
    $req[$user_id]=time();
    file_put_contents($USER_REQUESTS_FILE,json_encode($req));

    realistic_typing($chat_id,$text);

    // ---------------- COMMANDS ----------------
    if(strpos($text,'/')===0){

        $p=explode(" ",$text);
        $cmd=strtolower($p[0]);
        $args=array_slice($p,1);

        if($cmd=="/start"){
            $msg="🎬 <b>Entertainment Tadka</b>\n\n".
                 "• Movie ka naam type karo\n".
                 "• Partial name bhi chalega\n".
                 "• Request par auto notify\n\n".
                 "🍿 ".MAIN_CHANNEL."\n".
                 "📥 ".REQUEST_CHANNEL."\n".
                 "🎭 ".THEATER_CHANNEL;

            $kb=[
                'inline_keyboard'=>[
                    [
                        ['text'=>'🔍 Search','switch_inline_query_current_chat'=>''],
                        ['text'=>'🍿 Channel','url'=>'https://t.me/EntertainmentTadka786']
                    ]
                ]
            ];
            sendMessage($chat_id,$msg,$kb);
            exit;
        }

        if($cmd=="/request"){
            $movie=trim(implode(" ",$args));
            if(!$movie){
                sendMessage($chat_id,"❌ Usage: /request Movie Name");
                exit;
            }

            $csv=read_csv($MOVIES_CSV);
            foreach($csv as $r){
                if(stripos($r['movie_name'],$movie)!==false){
                    sendMessage($chat_id,"✅ Movie already available!");
                    exit;
                }
            }

            $waiting=file_exists($WAITING_FILE)
                ? json_decode(file_get_contents($WAITING_FILE),true)
                : [];

            $k=strtolower($movie);
            if(!isset($waiting[$k])) $waiting[$k]=[];
            $waiting[$k][]=[$chat_id,$user_id];

            file_put_contents($WAITING_FILE,json_encode($waiting));

            $m=sendMessage(
                $chat_id,
                "🎉 \"$movie\" request saved!\n🔔 Available hote hi notify milega."
            );
            sleep(15);
            bot("deleteMessage",[
                'chat_id'=>$chat_id,
                'message_id'=>$m['result']['message_id']
            ]);
            exit;
        }
    }

    // ---------------- SEARCH (GROUPED) ----------------
    $csv=read_csv($MOVIES_CSV);
    $grouped=[];

    foreach($csv as $r){
        if(stripos($r['movie_name'],$text)!==false){
            $key=strtolower(trim($r['movie_name']));
            $grouped[$key][]=$r;
        }
    }

    if(empty($grouped)){
        sendMessage($chat_id,"❌ Movie nahi mili.");
        exit;
    }

    foreach($grouped as $movie=>$parts){
        sendMessage(
            $chat_id,
            "🎬 <b>".ucwords($movie)."</b>\n📦 Parts: ".count($parts)
        );

        foreach($parts as $p){
            bot("forwardMessage",[
                'chat_id'=>$chat_id,
                'from_chat_id'=>$p['channel_id'],
                'message_id'=>$p['message_id']
            ]);
        }
    }
}

// ==================================================
// 3️⃣ INLINE SEARCH
// ==================================================
if(isset($update['inline_query'])){

    $q=strtolower($update['inline_query']['query']);
    $id=$update['inline_query']['id'];

    $csv=read_csv($MOVIES_CSV);
    $res=[];

    foreach($csv as $r){
        if(stripos($r['movie_name'],$q)!==false){
            $res[]=[
                'type'=>'article',
                'id'=>md5($r['movie_name']),
                'title'=>$r['movie_name'],
                'input_message_content'=>[
                    'message_text'=>"🎬 ".$r['movie_name']
                ]
            ];
        }
    }

    bot("answerInlineQuery",[
        'inline_query_id'=>$id,
        'results'=>json_encode($res),
        'cache_time'=>0
    ]);
}
?>
