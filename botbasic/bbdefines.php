<?php
/**
 * defines() de PHP para BotBasic
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



// Main definitions
define('BOTBASIC_BASEDIR',                      '/home/botbasic');
define('BOTBASIC_LOGFILE',                      BOTBASIC_BASEDIR . '/logs/runtime.log');
define("BOTBASIC_LANG_NAME",                    "BotBasic");
define("BOTBASIC_LANG_VERSION",                 "01.00");
define("BOTBASIC_DEFAULT_LOCALE",               "es");
define("BOTBASIC_COMMAND_START",                "/start");
define("BOTBASIC_MAX_PARSE_TIME_SECONDS",       1.0);
define("BOTBASIC_MAX_EXEC_TIME_SECONDS",        1.0);
define('BOTBASIC_TIMEZONE',                     'Etc/UTC');   // 'America/Panama');
define('BOTBASIC_DOWNLOAD_MMCONTENT',           false);
define('BOTBASIC_BOT_IS_POSSESSED',             false);
define('BOTBASIC_DOWNLOADDAEMON_DOWNLOADS_DIR', BOTBASIC_BASEDIR . '/downloads');
define('BOTBASIC_DOWNLOADDAEMON_SCRIPTSDIR',    BOTBASIC_BASEDIR . '/httpdocs/scripts/media');
define('BOTBASIC_WEBSTUB_OUTPUT_DIR',           BOTBASIC_BASEDIR . '/logs/webstub');
define('BOTBASIC_WEBSTUB_OUTPUT_COMMON_FILE',   'ALL.log');
define('BOTBASIC_LOGBOT_CHATAPP',               111);   // one of ChatMedium::TYPE_..., which implements LogbotChatMedium interface

// Interactions
define('BOTBASIC_INPUT_HINTS_WITH_EXAMPLES', false);

// Debugging and profiling
define('BOTBASIC_PROFILE',         false);

define('BOTBASIC_DEV_MACHINE',     'klock');
define('BOTBASIC_DEBUG',           gethostname() == BOTBASIC_DEV_MACHINE);
define('BOTBASIC_LOG_ALSO_TO_DB',  false);
define('BOTBASIC_LOG_ALSO_TO_BOT', true);

// PDO definitions (database access)
define('BOTBASIC_DB_DRIVER',   'mysql');
define('BOTBASIC_DB_HOST',     'localhost');
define('BOTBASIC_DB_NAME',     'botbasic');
define('BOTBASIC_DB_USER',     'botbasic');
define('BOTBASIC_DB_PASSWORD', 'candela');
define('BOTBASIC_DB_CONN_STR', BOTBASIC_DB_DRIVER . ":host=" . BOTBASIC_DB_HOST . ";dbname=" . BOTBASIC_DB_NAME . ";charset=utf8mb4");

// Tuning: memory usage
define('BOTBASIC_CALLSTACK_LIMIT',          100);
define('BOTBASIC_ROUTEQUEUE_LIMIT',         100);
define('BOTBASIC_MAX_HTTP_REQUEST_SIZE_MB',  20);

// Tuning: sending splashes to chatapp servers
define('BOTBASIC_SENDERDAEMON_CRON_DELAY_SECS',              1.5);   // conservative lag from cron job start to start of webrouter script
define('BOTBASIC_SENDERDAEMON_TELEGRAM_POST_TIMEOUT_SECS',    10);
define('BOTBASIC_SENDERDAEMON_TELEGRAM_MIN_SECS_TO_RELOG',     5);
define('BOTBASIC_SENDERDAEMON_TELEGRAM_MAX_SEND_ATTEMPTS',     3);

// Tuning: splashs display on chatapps
// see https://core.telegram.org/bots#inline-keyboards-and-on-the-fly-updating
define('BOTBASIC_TELEGRAM_DEVICE_SCREENWIDTH_PXS', 480);   // not used (this is for Samsung J1mini)
define('BOTBASIC_TELEGRAM_MAXPOSTWIDTH_PXS',       420);   // maxpx
define('BOTBASIC_TELEGRAM_EXTERNALPADDING_PXS',     13);   // a
define('BOTBASIC_TELEGRAM_INTERNALPADDING_PXS',      4);   // b
define('BOTBASIC_TELEGRAM_PXS_PER_CHAR',            15);   // pxxch
define('BOTBASIC_TELEGRAM_CHARS_WIDTH_RATIO',     0.85);   // "non-normal" to "normal" chars' pixel-width ratio // tune up if needed
define('BOTBASIC_TELEGRAM_NORMAL_WIDTH_CHARS',     "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ#%&=?<>@_¿€ÑÁÉÍÓÚÜ");
define('BOTBASIC_TELEGRAM_MIN_MENUTITLE_CHARS',     24);
define('BOTBASIC_TELEGRAM_CAPTION_MAXLENGTH',      200);

// Tuning: downloading updated content
define('BOTBASIC_DOWNLOADDAEMON_TELEGRAM_MIN_SECS_TO_RELOG',        5);
define('BOTBASIC_DOWNLOADDAEMON_TELEGRAM_MAX_DOWNLOAD_ATTEMPTS',    3);
define('BOTBASIC_DOWNLOADDAEMON_TELEGRAM_INTERDELAY_MSECS',      1050);   // tune this if the next one != 1
define('BOTBASIC_DOWNLOADDAEMON_TELEGRAM_HOW_MANY_TO_DOWNLOAD',    15);   // don't touch at this time
