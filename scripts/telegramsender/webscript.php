<?php
/**
 * Disparador de la cola de envío de mensajes hacia las chatapps
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     2.0 - 23.jun.2018
 * @since       0.1 - 01.jul.2016
 */



define('BOTBASIC_LOG_WELCOME_MESSAGE', null);
include "../../botbasic/bbautoloader.php";

use botbasic\ChatMedium, botbasic\ChatMediumTelegram, botbasic\Log;

if (php_sapi_name() === 'cli') {
    fwrite(STDERR, "this script can only be invoked from the web server");
    exit(1);
}

if (! isset($_GET['thread'])       || ! is_numeric($_GET['thread'])      ||
    ! isset($_GET['threads'])      || ! is_numeric($_GET['threads'])     ||
    ! isset($_GET['requestmsecs']) || ! is_numeric($_GET['requestmsecs'] )) {
    Log::register(Log::TYPE_DAEMON, "TGSNDR25 No se especifico en _GET ni 'thread' ni 'threads' ni 'requestmsecs' en la invocacion web del script");
}

$cm = ChatMedium::create(ChatMedium::TYPE_TELEGRAM);   /** @var ChatMediumTelegram $cm */
$cm->attemptToSend(
    $_GET['thread'],
    $_GET['threads'],
    $_GET['requestmsecs']
);
