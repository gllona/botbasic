<?php
/**
 * Disparador de la cola de envío de mensajes hacia las chatapps
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



define('BOTBASIC_LOG_WELCOME_MESSAGE', null);
include "../../botbasic/bbautoloader.php";

use botbasic\ChatMedium, botbasic\ChatMediumTelegram, botbasic\Log;

$die = function ($msg) { Log::register(Log::TYPE_DAEMON, $msg); exit(1); };

if (php_sapi_name() === 'cli') { $die("this script can only be invoked from the web server"); }

if (! isset($_GET['thread']) || ! isset($_GET['threads']))               { $die("TGSNDR25 Invoke with http...?thread=<thread-number>&threads=<number-of-threads>");             }
if (isset($_GET['requestmsecs']) && ! is_numeric($_GET['requestmsecs'])) { $die("TGSNDR26 Numeric value expected with http...?requestmsecs=<time-to-spent-per-request-msecs>"); }
if (isset($_GET['maxtosend'])    && ! is_numeric($_GET['maxtosend']))    { $die("TGSNDR27 Numeric value expected with http...?maxtosend=<max-splashes-to-send>");               }

$cm = ChatMedium::create(ChatMedium::TYPE_TELEGRAM);   /** @var ChatMediumTelegram $cm */
$cm->attemptToSend(
    $_GET['thread'],
    $_GET['threads'],
    isset($_GET['maxtosend']) ? $_GET['maxtosend'] : -1,
    $_GET['requestmsecs']
);
