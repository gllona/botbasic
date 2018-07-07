<?php



// Para fijar  el hook: wget -O - http://local.beta.bots.logicos.org/scripts/hooksetter/setwebhook.php
// Para borrar el hook: wget -O - http://local.beta.bots.logicos.org/scripts/hooksetter/setwebhook.php?action=delete_webhook

define('PUBLIC_SERVER', 'dev');
define('PRIVATE_SERVER', 'beta');
define('PORT', '443');
define('BOT', 'grokabot');



// COPY-PASTE FROM BOTCONFIG HERE (delete "static..."):

$cmBots = [

    /////////////////////////////
    // NEUROPOWER (TEST DRIVE #1)
    /////////////////////////////

    // NP - main
    10 => [
        [ 'NeuroPowerBot',  'tgrp_10_00_278347235423590890123454.php',                                                                                                              '171752376:AAGgO5P3_W8Q8KPLCvoQAKHafiQ54w-K6rw' ],
    ],
    // NP - used for monitoring
    12 => [
        [ 'neuropower_bot', 'tgrp_12_00_934967523854879438679845.php',                                                                                                              '227989979:AAG0lpleT4SlriqdeLUv35jhJsRXn2chMoc' ],
    ],

    // Gorka bots
    898 => [
        [ 'AitanaTheBot',               'tgrp_898_00_832569490854734632897368.php',                                                                                                             '601186767:AAEeWKOuRV726V3aUtnF9dq1irFE7Bs1LNI' ],
        [ 'AitanaLlonaBot',             'tgrp_898_99_85367555436565i535247658.php',                                                                                                             '508088357:AAFRrwZxnfOqQFsG7AdesiM8nOnLYmjK8do' ],
    ],
    899 => [
        [ 'gorkathebot',                'tgrp_899_00_523454487872115869145558.php',                                                                                                             '508526373:AAEaIuGL03wJE8W7DKIrvMh4iZN8Uc90mzE' ],
        [ 'grokabot',                   'tgrp_899_99_423809869823579639532654.php',                                                                                                             '476497270:AAFNZbJXgsKUrldupreWgY_CMZE6QbwJj7w' ],
    ],

];

// END OF COPY-PASTE



$hook = $token = null;
foreach ($cmBots as $bbBotCode => $bots) {
    foreach ($bots as $credentials) {
        list ($bot, $script, $aToken) = $credentials;
        if ($bot == BOT) { $hook = $script; $token = $aToken; break 2; }
    }
}
if ($hook === null) { die("Can't locate bot credentials"); }

// old
//                                 https://telegrambots.duckdns.org:8443/scripts/telegramhooks/coolbot.php
//                                'https://telegrambots.duckdns.org:8443/scripts/telegramhooks'
//define('WEBHOOK_BASE_URL'     , 'https://' . SERVER . '.duckdns.org:' . PORT . '/scripts/telegramhooks');   // old-style (insecure)
//define('WEBHOOK_BASE_URL'     , 'https://' . SERVER . '.duckdns.org:' . PORT . '/telegram');
//define('CERTIFICATE_FILENAME' , BASEDIR . '/webhook_certificate/public.pem');
//define('LOGFILE'              , 'php://stdout');

// 2018
define('WEBHOOK_BASE_URL'       , 'https://' . PUBLIC_SERVER . '.bots.logicos.org:' . PORT . '/telegram');
define('WEBHOOK_URL'            , WEBHOOK_BASE_URL . '/' . $hook);
define('API_URL'                , 'https://api.telegram.org/bot' . $token . '/');
define('LOCAL_SERVER'           , 'local.' . PRIVATE_SERVER . '.bots.logicos.org');
define('BASEDIR'                , '/home/gorka/telegram/panama_bot');
define('LOGFILE'                , BASEDIR . '/logs/hooksetter.log');

// END OF CONFIG



function logger($msg, $limit = true, $close = false)
{
    static $log_fh = null;
    $log_line_limit = 80 * 5;
    if ($log_fh === null) {
        $log_fh = fopen(LOGFILE, "a+");
    }
    $prefix = "[" . date("Y-m-d h:i:s") . "] --- ";
    $text = $prefix . $msg;
    if ($limit && strlen($text) > $log_line_limit - 3) {
        $text = substr($text, 0, $log_line_limit - 3) . "...";
    }
    fwrite($log_fh, $text . "\n");
    if ($close && $log_fh !== null) {
        fclose($log_fh);
        $log_fh = null;
    }
    echo $text . " <br/>\n";
}



function apiRequestWebhook($method, $parameters)
{
    if (!is_string($method)) {
        logger("API: Method name must be a string");
        return false;
    }

    if (!$parameters) {
        $parameters = array();
    } else if (!is_array($parameters)) {
        logger("API: Parameters must be an array");
        return false;
    }

    $parameters["method"] = $method;

    header("Content-Type: application/json");
    echo json_encode($parameters);
    return true;
}



function exec_curl_request($handle)
{
    $response = curl_exec($handle);

    if ($response === false) {
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        logger("API: Curl returned error $errno: $error");
        curl_close($handle);
        return false;
    }

    $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
    curl_close($handle);

    if ($http_code >= 500) {
        // do not wat to DDOS server if something goes wrong
        sleep(10);
        return false;
    } else if ($http_code != 200) {
        $response = json_decode($response, true);
        logger("API: Request has failed with error {$response['error_code']}: {$response['description']}");
        if ($http_code == 401) {
            throw new Exception('Invalid access token provided');
        }
        return false;
    } else {
        $response = json_decode($response, true);
        if (isset($response['description'])) {
            logger("API: Request was successfull: {$response['description']}");
        }
        $response = $response['result'];
    }

    return $response;
}



function apiRequest($method, $parameters)
{
    if (!is_string($method)) {
        logger("API: Method name must be a string");
        return false;
    }

    if (!$parameters) {
        $parameters = array();
    } else if (!is_array($parameters)) {
        logger("API: Parameters must be an array");
        return false;
    }

    foreach ($parameters as $key => &$val) {
        // encoding to JSON array parameters, for example reply_markup
        if (!is_numeric($val) && !is_string($val)) {
            $val = json_encode($val);
        }
    }
    $url = API_URL.$method.'?'.http_build_query($parameters);

    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);

    return exec_curl_request($handle);
}



function apiRequestJson($method, $parameters)
{
    if (!is_string($method)) {
        logger("API: Method name must be a string");
        return false;
    }

    if (!$parameters) {
        $parameters = array();
    } else if (!is_array($parameters)) {
        logger("API: Parameters must be an array");
        return false;
    }

    $parameters["method"] = $method;

    $handle = curl_init(API_URL);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
    curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
    curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

    return exec_curl_request($handle);
}



function processMessage($message)
{
    // process incoming message
    //$message_id = $message['message_id'];
    $chat_id = $message['chat']['id'];
    if (isset($message['text'])) {
        // incoming text message
        $text = $message['text'];
        if (strpos($text, "/start") === 0) {
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Hello', 'reply_markup' => array(
                'keyboard' => array(array('Hello', 'Hi')),
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));
        } else if ($text === "Hello" || $text === "Hi") {
            apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Nice to meet you'));
        } else if (strpos($text, "/stop") === 0) {
            // stop now
        } else {
            //apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => 'Cool'));
            apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Cool'));
        }
    } else {
        apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'I understand only text messages'));
    }
}



///////
// MAIN
///////



// SCRIPT ENTRY CHECKING (for testing)

// testing tunnel? show a message and exit
if (false) {
    //echo "Just testing the HTTP(S) tunnel from logicos.org to the development PC...";
    echo "Just testing the script from duckdns.org to the development PC...";
    logger("Just tested script entrance.");
    exit;
}



// WEBHOOK SETTING

// setear o borrar webhook
// el seteo del webhook debe ser efectuado antes de cualquier utilizacion del bot
//
if (php_sapi_name() == 'cli' || $_SERVER['HTTP_HOST'] == LOCAL_SERVER) {
    // if run from console o in a local browser, set or delete webhook
    // se agrego modalidad local browser debido a que en este momento no se puede usar curl desde php_cli
    $delete =    php_sapi_name() == 'cli' && isset($argv[1])        && $argv[1]        == 'delete_webhook'
              || php_sapi_name() != 'cli' && isset($_GET['action']) && $_GET['action'] == 'delete_webhook';
    apiRequest('setWebhook', array('url' => $delete ? '' : WEBHOOK_URL));
    // apiRequest('setWebhook', array('url' => $delete ? '' : WEBHOOK_URL, 'certificate' => CERTIFICATE_FILENAME));
    /*
    para self-signed certificates: NO DEBE SER EL NOMBRE DEL ARCHIVO SINO UN STREAM HTTP POST FILE (ver documentacion del API)
    para hacerlo con cURL:
    curl -F "url=https://example.com/myscript.php" -F "certificate=@/etc/apache2/ssl/apache.crt" https://api.telegram.org/bot<SECRETTOKEN>/setWebhook
    openssl req -newkey rsa:2048 -sha256 -nodes -keyout /your_home/BOTServer/ssl/PRIVATE.key -x509 -days 365 -out /your_home/BOTServer/ssl/PUBLIC.pem -subj "/C=IT/ST=state/L=location/O=description/CN=your_domain.com"
    */
    logger("Como respuesta a solicitud sobre webhook: " . ($delete ? "eliminacion" : "registro"));
    exit;
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    // receive wrong update, must not happen
    logger("Error: no se recibio un update o no se pudo decodificar la entrada");
    exit;
}

if (isset($update["message"])) {
    logger("Por procesar update con message: " . json_encode($update["message"]));
    processMessage($update["message"]);
}
else {
    logger("Se recibio un update sin message; el bot no emite respuesta...");
}

exit;
