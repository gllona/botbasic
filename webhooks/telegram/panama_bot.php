<?php

// START OF CONFIG

define('BOT', 'panama_bot');

// Para fijar  el hook: navegar a: https://panama_bot.local/scripts/telegramhooks/panama_bot.php
// Para borrar el hook: navegar a: https://panama_bot.local/scripts/telegramhooks/panama_bot.php?action=delete_webhook



// WIRED

$cmBots = [
    10 => [
            [ 'panama_bot',  'panama_bot.php',                                                                                                              '222423662:AAHUmFozDKoNVBy_8gzNCUORNAhNHo35J2o' ],
          ],
];

// END OF WIRED



$hook = $token = null;
foreach ($cmBots as $bbBotCode => $bots) {
    foreach ($bots as $credentials) {
        list ($bot, $script, $aToken) = $credentials;
        if ($bot == BOT) { $hook = $script; $token = $aToken; break 2; }
    }
}
if ($hook === null) { die("Can't locate bot credentials"); }

define('WEBHOOK_BASE_URL'       , 'https://telegrambots.duckdns.org:443/telegram');
define('WEBHOOK_URL'            , WEBHOOK_BASE_URL . '/' . $hook);
define('API_URL'                , 'https://api.telegram.org/bot' . $token . '/');
define('LOCAL_SERVER'           , 'panama_bot.local');
define('BASEDIR'                , '/home/gorka/telegram/panama_bot');
define('CERTIFICATE_FILENAME'   , BASEDIR . '/webhook_certificate/public.pem');
define('LOGFILE'                , BASEDIR . '/logs/panama_bot.log');

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
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => "Hello, I'm Panama_Bot.", 'reply_markup' => array(
                'keyboard' => array(array('Hello', 'Hi')),
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));
        } else if ($text === "Hello" || $text === "Hi") {
            apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "Nice to meet you.\nI'll be launched in 2017."));
        } else if (strpos($text, "/stop") === 0) {
            // stop now
        } else {
            //apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => 'Cool'));
            apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Come and visit us!'));
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
// places where this can be tested:
// SECRET webhook:          https://telegrambots.logicos.org/panama_bot/webhook_tokenizedby_3E64rt1vTsRnC5wIfY81BO7k/forward_bot_cliente.php
// WAN IP (wifi router):    http://181.197.133.221:8088/bot_cliente.php
// development PC by IP:    https://192.168.2.6:8443/bot_cliente.php                    [SSL cert fails because of domain name mismatch]
// development PC by name:  https://panama_bot.local:8443/bot_cliente.php               [SSL cert fails because of domain name mismatch]
// new dev PC by dyn DNS:   https://telegrambots.duckdns.org:8443/bot_cliente.php       [TODO: ADD SECRET/TOKEN PART TO URL]
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
    Duck DNS:   account: gllona@gmail.com
                token: 972f1b81-07ce-4aad-a801-18f7b7a46859
                domains: telegrambots.duckdns.org
    */
    logger("Webhook " . ($delete ? "eliminado" : "registrado"));
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
