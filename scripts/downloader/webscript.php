<?php
/**
 * Disparador del descargador de contenidos multimedia (resources) asociados a interacciones provenientes del usuario desde las chatapps
 *
 * @author      Gorka G LLona                               <gorka@gmail.com> <gorka@venicua.com>
 * @license     http://www.venicua.com/botbasic/license     Licencia de BotBasic
 * @see         http://www.venicua.com/botbasic             Referencia de BotBasic
 * @version     1.0 - 01.jan.2017
 * @since       0.1 - 01.jul.2016
 */



define('BOTBASIC_LOG_WELCOME_MESSAGE', null);
include "../../botbasic/bbautoloader.php";

use \botbasic\ChatMedium;
use \botbasic\DBbroker;

$die = function ($msg) { fwrite(STDERR, $msg); exit(1); };

if (php_sapi_name() === 'cli') { $die("this script can only be invoked from the web server"); }

$params = [
    ChatMedium::TYPE_TELEGRAM => [
        'minSecsToRelog'      => BOTBASIC_DOWNLOADDAEMON_TELEGRAM_MIN_SECS_TO_RELOG,
        'maxDownloadAttempts' => BOTBASIC_DOWNLOADDAEMON_TELEGRAM_MAX_DOWNLOAD_ATTEMPTS,
        'interdelayMsecs'     => BOTBASIC_DOWNLOADDAEMON_TELEGRAM_INTERDELAY_MSECS,
        'howManyToDownload'   => BOTBASIC_DOWNLOADDAEMON_TELEGRAM_HOW_MANY_TO_DOWNLOAD,
    ],
];

if (! isset($_GET['chatmediumid'])) {
    echo "Invoke with http...?chatmediumid=<numeric-chatchannel-type>";
}
else {
    $type   = $_GET['chatmediumid'];
    $params = $params[$type];
    $dbb    = new DBbroker();
    $dbb->attemptToDownload(
        $type,
        $params['howManyToDownload'],
        $params['interdelayMsecs'],
        $params['maxDownloadAttempts'],
        $params['minSecsToRelog']
    );
}
