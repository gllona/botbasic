<?php
/**
 * Definición del autoloader de clases y fijación de parámetros de registro de mensajes y errores
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



/*
Another important step is to minimize/stop your Apache leakage of webserver info that makes it identifiable.
Edit /etc/apache2/conf-available/security by adding/modifying two parameters like the following:
ServerTokens Prod
ServerSignature Off
*/
/*
MEMORY FOOTPRINTS
CURL 	rss     8272 kb, vm   229808 kb		but: it's shared lib <-- curl-config --built-shared, curl-config --configure
WGET 	rss     4116 kb, vm    32264 kb
BASH 	rss     3180 kb, vm    13632 kb
how to test: sudo ~gorka/bin/tstime bash -c "echo hola"
gorka@hp-envy ~/tmp $ ls -l /usr/bin/curl /usr/bin/wget
-rwxr-xr-x 1 root root 154328 Aug 31 11:02 /usr/bin/curl
-rwxr-xr-x 1 root root 407696 Jun 14 03:20 /usr/bin/wget
ELECCION: CURL
*/
// CRONTAB:
//  */5 * * * * /home/gorka/telegram/panama_bot/dns_ssl/duck.sh >/dev/null 2>&1
//  */1 * * * * /home/gorka/telegram/panama_bot/httpdocs/scripts/telegramsender/launcher.sh 1000 750 >/dev/null 2>/dev/null
//  #*/1 * * * * /home/gorka/telegram/panama_bot/httpdocs/scripts/downloader/launcher.sh >/dev/null 2>/dev/null


include_once('bbdefines.php');

ini_set('always_populate_raw_post_data', 'On');

ini_set("log_errors", 1);
ini_set("error_log", BOTBASIC_LOGFILE);
error_reporting(E_ALL);

if (BOTBASIC_DEBUG) {
    if (defined('BOTBASIC_LOG_WELCOME_MESSAGE')) { $message = BOTBASIC_LOG_WELCOME_MESSAGE; }
    else                                         { $message = "Starting BotBasic...";       }
    if ($message !== null)                       { error_log($message);                     }
}

/**
 * Classes autoloader. "BizModelAdapter" class will be loaded from a "bizmodel" outer directory
 */
spl_autoload_register(

    function ($class)
    {
        //error_log("[loading $class...]");
        if (($pos = strpos($class, '\\')) == 0 || $pos === false) { return; }
        list ($namespace, $rest) = explode('\\', $class, 2);
        if ($namespace == 'botbasic' && $rest != 'BizModelAdapter') { $dir = '';                        }
        elseif (substr($rest, 0, 15) == 'BizModelAdapter')          { $dir = "/../bizmodel";            }
        else                                                        { $dir = "/../bizmodel/$namespace"; }
        $toLoad = __DIR__ . "$dir/$rest.php";
        /** @noinspection PhpIncludeInspection */
        require_once $toLoad;
    }

);

include_once(__DIR__ . '/../bizmodel/bmdefines.php');
