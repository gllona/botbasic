<?php
/**
 * Disparador del descargador de contenidos multimedia (resources) asociados a interacciones provenientes del usuario desde las chatapps
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



define('BOTBASIC_LOG_WELCOME_MESSAGE', null);
include "../../botbasic/bbautoloader.php";

use botbasic\ChatMedium, botbasic\DBbroker, botbasic\Log;

$die = function ($msg) { Log::register(Log::TYPE_DAEMON, $msg); exit(1); };   // { fwrite(STDERR, $msg); exit(1); };

if (php_sapi_name() === 'cli') { $die("this script can only be invoked from the web server"); }

if (! isset($_GET['chatmediumid']))                                        { $die("DWNLDR22 Invoke with http...?chatmediumid=<numeric-chatchannel-type>");                   }
if (! isset($_GET['thread']) || ! isset($_GET['threads']))                 { $die("DWNLDR23 Invoke with http...?thread=<thread-number>&threads=<number-of-threads>");        }
if (isset($_GET['waitmsecs']) && ! is_numeric($_GET['waitmsecs']))         { $die("DWNLDR24 Numeric value expected with http...?waitmsecs=<inter-downloads-msecs>");         }
if (isset($_GET['maxtodownload']) && ! is_numeric($_GET['maxtodownload'])) { $die("DWNLDR25 Numeric value expected with http...?maxtodownload=<max-resources-to-download>"); }

$params = [
    ChatMedium::TYPE_TELEGRAM => [
        'minSecsToRelog'      => BOTBASIC_DOWNLOADDAEMON_TELEGRAM_MIN_SECS_TO_RELOG,
        'maxDownloadAttempts' => BOTBASIC_DOWNLOADDAEMON_TELEGRAM_MAX_DOWNLOAD_ATTEMPTS,
        'interdelayMsecs'     => isset($_GET['waitmsecs'])     ? $_GET['waitmsecs']     : BOTBASIC_DOWNLOADDAEMON_TELEGRAM_INTERDELAY_MSECS,
        'howManyToDownload'   => isset($_GET['maxtodownload']) ? $_GET['maxtodownload'] : BOTBASIC_DOWNLOADDAEMON_TELEGRAM_HOW_MANY_TO_DOWNLOAD,
    ],
];

$type   = $_GET['chatmediumid'];
$params = $params[$type];
$dbb    = new DBbroker();
$dbb->attemptToDownload(
    $type,
    $_GET['thread'],
    $_GET['threads'],
    $params['howManyToDownload'],
    $params['interdelayMsecs'],
    $params['maxDownloadAttempts'],
    $params['minSecsToRelog']
);
