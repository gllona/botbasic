<?php
/**
 * Webhook para Telegram; ver identidad del bot según nombre de este script en la clase ChatMediumTelegram
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



include "../../botbasic/bbautoloader.php";

use \botbasic\WebRouterTelegram;
use \botbasic\Log;

$die = function ($msg) { fwrite(STDERR, $msg); exit(1); };

if (php_sapi_name() === 'cli') { $die("this script can only be invoked from the web server"); }

$wr = new WebRouterTelegram();
$res = $wr->run();
if ($res === null) {
    // TODO log this: can't start because couldn't find cmAuthInfo (cmBotName) based on scriptName
}
