<?php
/**
 * defines() de PHP para bizmodels
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace {

    // SPL autoloaders
    require __DIR__ . '/phpmailer/PHPMailerAutoload.php';
    require __DIR__ . '/messente/MessenteSmsAutoload.php';

}



namespace botbasic {

    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 465;   // 465 or 587

}



namespace T3 {

    // generic definitions - Log, DBbroker
    const TIMEZONE         = 'America/Panama';
    const BIZMODEL_PROFILE = false;

    // main definitions
    const LOGFILE = '/home/gorka/telegram/panama_bot/logs/bizmodels/t3.log';

    // PDO definitions (database access)
    const DB_DRIVER   = 'mysql';
    const DB_HOST     = 'localhost';
    const DB_NAME     = 'T3';
    const DB_USER     = 'root';
    const DB_PASSWORD = 'mosopsql';
    const DB_CONN_STR = DB_DRIVER . ":host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8";

    // email sending
    const SMTP_HOST              = 'smtp.gmail.com';
    const SMTP_AUTH              = true;
    const SMTP_FROM              = 't3contacto@gmail.com';
    const SMTP_PASSWORD          =                                                                                                      'Q9ueLXzHpFnpbGKBcaTBtnf5ZokvbWgd';
    const SMTP_SECURE            = 'tls';
    const SMTP_PORT              = 465;   // 465 or 587
    const SMTP_SUBJECT           = 'T3 - Turn Treasures into Trash';
    const SMTP_CONTACT_RECIPIENT = 't3contacto@gmail.com';

    // sms sending
    const MESSENTESMS_USERNAME            =                                                                                             'e4415252581d0b706c27366970bac1ed';
    const MESSENTESMS_PASSWORD            =                                                                                             'f31a540877a4b0843a3c074d6e853f4b';
    const MESSENTESMS_FROM                = '+50769174440';   // FIXME cambiar al final
    const MESSENTESMS_DEFAULT_COUNTRYCODE = '+507';
    const MESSENTESMS_ADMIN_EMAIL         = SMTP_FROM;

    // geo-related definitions
    const GEODIFF_THRESHOLD_MTS = 200;   // en metros

    // CO2-print calculation (bluff as in here)
    const ECOPOINTS_TO_CO2_KG_FACTOR_RECICLADOR = 1;   // FIXME define
    const ECOPOINTS_TO_CO2_KG_FACTOR_COLECTOR   = 1;   // FIXME define

    // ecopoints-related
    const EP_DE_COLECTOR_POR_DE_RECICLADOR = 0.1;   // FIXME tune

    // logistics and agenda
    const DEFAULT_MATERIAL_THRESHOLD_KG             = 64;   // algo mas de 140 libras
    const DAILY_START_FOR_CAMION_HH                 = 9;
    const DAILY_END_FOR_CAMION_HH                   = 18;
    const MIN_OFFSET_FROM_NOW_FOR_CAMION_ARRIVAL_MM = 120;

    // marketing
    const CANJE_CANCELING_BONUS_PERCENT = .15;

}



namespace nima {

    // generic definitions - Log, DBbroker
    const TIMEZONE         = 'America/Panama';
    const BIZMODEL_PROFILE = false;

    // main definitions
    const LOGFILE = '/home/gorka/telegram/panama_bot/logs/bizmodels/nima.log';

    // PDO definitions (database access)
    const DB_DRIVER   = 'mysql';
    const DB_HOST     = 'localhost';
    const DB_NAME     = 'nima';
    const DB_USER     = 'root';
    const DB_PASSWORD = 'mosopsql';
    const DB_CONN_STR = DB_DRIVER . ":host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8";

}
