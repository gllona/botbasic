<?php
/**
 * Librería de lectura y escritura para las estructuras de BotBasic (no aplica a los modelos de negocio)
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;

use \PDO, \PDOException;



/**
 * Clase DBbroker
 *
 * Implementa como librería (en forma de métodos estáticos) el ORM de BotBasic.
 *
 * No hay acceso a base de datos fuera de esta clase, a lo largo del código del namespace.
 *
 * @package botbasic
 */
class DBbroker
{



    /**
     * @var PDO
     */
    static private $dbh = null;   // DB handler



    ////////////
    // DB LOGGER
    ////////////


    /**
     * Registra un mensaje en una bitácora ubicada en una tabla en la BD
     *
     * NO IMPLEMENTADO POR EL MOMENTO; PODRIA TENER CAMBIO EN SU FIRMA
     *
     * @param $message
     */
    static public function DBlogger ($message)
    {
        // do nothing, for now
    }



    /**
     * Lee el timestamp (como Unix epoch time) de la última ocurrencia de un registro de bitácora efectuado por un demonio del sistema
     *
     * Esta funcionalidad está provista, junto con writeLastlogtimeForDaemon(), para implementar un mecanismo que evite el registro de
     * bursts de mensajes todos asociados al mismo tipo de error, por parte de los demonios; esto es a su vez para impedir el crecimiento
     * en tamaño de los archivos de bitácora en esas situaciones.
     *
     * @param  string       $daemon     Identificación del demonio: "download" (de resources) o "message" (cola de salida de splashes)
     * @param  int          $cmType     ID del ChatMedium asociado a los registros, como una de las constantes ChatMedium::TYPE_...
     * @return null|int                 Unix epoch time del registro, o null si hay error en el SQL
     */
    static private function readLastlogtimeForDaemon ($daemon, $cmType)
    {
        self::connect();
        $sql = <<<END
            SELECT UNIX_TIMESTAMP(stamp) AS ustamp
              FROM daemons_log_stamps
             WHERE cm_type = $cmType
               AND daemon = '$daemon'
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if ($rows === null || count($rows) == 0) {
            return null;
        }
        return $rows[0]['ustamp'];
    }



    /**
     * Actualiza el timestamp asociado a cada uno de los demonios del sistema
     *
     * @param  string       $daemon     Identificación del demonio: "download" (de resources) o "message" (cola de salida de splashes)
     * @param  int          $cmType     ID del ChatMedium asociado a los registros, como una de las constantes ChatMedium::TYPE_...
     * @return bool|null                null en caso de error del SQL; true de otro modo
     */
    static private function writeCurrentLastlogtimeDaemon ($daemon, $cmType)
    {
        $tstamp = time();
        self::connect();
        $sql = <<<END
            UPDATE daemons_log_stamps
               SET stamp = FROM_UNIXTIME($tstamp)  
             WHERE cm_type = $cmType
               AND daemon = '$daemon'
               AND deleted IS NULL;
END;
        $res = self::exec($sql);
        if ($res === false) { return null; }
        return true;
    }



    /**
     * Obtiene el timestamp asociado al demonio de la cola de envío de Splashes hacia las chatapps
     *
     * @param  int          $cmType     ID del ChatMedium asociado a los registros, como una de las constantes ChatMedium::TYPE_...
     * @return null|int                 Unix epoch time del registro, o null si hay error en el SQL
     */
    static public function readLastlogtimeForMessageDaemon ($cmType)
    {
        return self::readLastlogtimeForDaemon('message', $cmType);
    }



    /**
     * Actualiza el timestamp asociado al demonio de la cola de envío de Splashes hacia las chatapps
     *
     * @param  int          $cmType     ID del ChatMedium asociado a los registros, como una de las constantes ChatMedium::TYPE_...
     * @return bool|null                null en caso de error del SQL; true de otro modo
     */
    static public function writeCurrentLastlogtimeForMessageDaemon ($cmType)
    {
        return self::writeCurrentLastlogtimeDaemon('message', $cmType);
    }



    /**
     * Obtiene el timestamp asociado al demonio de la cola de descarga de InteractionResources que pueden venir agregados en los updates
     *
     * @param  int          $cmType     ID del ChatMedium asociado a los registros, como una de las constantes ChatMedium::TYPE_...
     * @return null|int                 Unix epoch time del registro, o null si hay error en el SQL
     */
    static public function readLastlogtimeForDownloadDaemon ($cmType)
    {
        return self::readLastlogtimeForDaemon('download', $cmType);
    }



    /**
     * Actualiza el timestamp asociado al demonio de la cola de descarga de InteractionResources que pueden venir agregados en los updates
     *
     * @param  int          $cmType     ID del ChatMedium asociado a los registros, como una de las constantes ChatMedium::TYPE_...
     * @return bool|null                null en caso de error del SQL; true de otro modo
     */
    static public function writeCurrentLastlogtimeForDownloadDaemon ($cmType)
    {
        return self::writeCurrentLastlogtimeDaemon('download', $cmType);
    }



    //////////////////////////////////////////////
    // BOTBASICPARSER AND BOTBASICRUNTIME (BBCODE)
    //////////////////////////////////////////////



    /**
     * Indica si un "usuario del parser" puede subir código de BotBasic a la BD
     *
     * Esta es una función utilitaria fuera del core de BotBasic que sólo es usada por las herramientas (web/CLI) de subida de código.
     *
     * @param  string       $userId     Username o ID del usuario
     * @param  string       $password   Contraseña del usuario
     * @return bool|null                null en caso de error del SQL; true si el usuario tiene permiso apropiado; false si no
     */
    static public function userCanUploadCode ($userId, $password)
    {
        self::connect();
        // sanitize input
        $userId   = self::q($userId);
        $password = self::q($password);
        // read the new try_count
        $sql = <<<END
            SELECT COUNT(*) AS matches
              FROM parser_user
             WHERE user_id = $userId 
               AND password = PASSWORD($password)
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if ($rows === false) { return null; }
        return $rows[0]['matches'] == 0 ? false : true;
    }



    /**
     * Escribe en BD (tabla "bbcode") el código BotBasic que está almacenado en las estructuras internas de una instancia de BotBasicParser
     *
     * @param  BotBasicParser   $bbparser   Instancia de BotBasicParser
     * @return bool|null                    null en caso de error de SQL; false si se intenta sobreescribir una versión mayor+menor; true en caso de éxito
     */
    static public function writeBBcode ($bbparser)
    {
        self::connect();
        // gather data
        $bbVersion       = $bbparser->getBBversion();
        $codename        = $bbparser->getCodename();
        $majorVersion    = $bbparser->getMajorCodeVersion();
        $minorVersion    = $bbparser->getMinorCodeVersion();
        $subminorVersion = $bbparser->getSubminorCodeVersion();
        $bots            = implode('|', $bbparser->getBBbotNames());
        $messages        = self::q(serialize($bbparser->getMessages()));
        $menus           = self::q(serialize($bbparser->getPredefmenus()));
        $magicvars       = self::q(serialize($bbparser->getMagicvars()));
        $primitives      = self::q(serialize($bbparser->getPrimitives()));
        $program         = self::q(serialize($bbparser->getProgram()));
        // check for codeversion overwritting
        $sql = <<<END
            SELECT COUNT(*) AS matches
              FROM bbcode
             WHERE code_name = '$codename'
               AND code_major_version = '$majorVersion'
               AND code_minor_version = '$minorVersion'
               AND code_subminor_version = '$subminorVersion'
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if ($rows === false)         { return null;  }
        if ($rows[0]['matches'] > 0) { return false; }
        // insert
        $sql = <<<END
            INSERT INTO bbcode 
                   (botbasic_version, code_name, code_major_version, code_minor_version, code_subminor_version, bots, messages, menus, magicvars, primitives, program)
            VALUES ('$bbVersion', '$codename', '$majorVersion', '$minorVersion', '$subminorVersion', '$bots', $messages, $menus, $magicvars, $primitives, $program);
END;
        $res = self::exec($sql);
        if ($res === false) { return null; }
        return true;
    }



    /**
     * Lee de BD (tabla "bbcode") un código BotBasic identificado por un codename, versión mayor y versión menor específicos
     *
     * Dado el esquema de versionamiento semántico implementado en BotBasic, se leerá la versión submenor más alta que esté almacenada en BD
     * (los números de versiones se ordenan alfanuméricamente).
     *
     * @param  string       $codename           Codename del programa BotBasic
     * @param  null|string  $majorCodeVersion   Versión mayor; los ceros a la izquierda son significativos;
     *                                          puede ser null para leer la más reciente versión mayor
     * @param  null|string  $minorCodeVersion   Versión menor; los ceros a la izquierda son significativos;
     *                                          puede ser null para leer la más reciente versión menor asociada a la versión mayor indicada antes
     * @return array|bool|null                  null si hay error de SQL; false si no se consigue un registro que coincida con los criterios;
     *                                          [ id, bbVersion, codename, majorCodeVersion, minorCodeVersion, subminorCodeVersion, messages, menus, magicvars, primitives, program ]
     *                                          de otro modo
     */
    static public function readBBcode ($codename, $majorCodeVersion = null, $minorCodeVersion = null)
    {
        self::connect();
        // if no full codeVersion was passed, then query for the most recent version of the code
        if ($majorCodeVersion === null || $minorCodeVersion === null) {
            $data = DBbroker::readLastBBCodeVersionForCodename($codename);
            if     ($data === null)  { return null;  }
            elseif ($data === false) { return false; }
            list ($majorCodeVersion, $minorCodeVersion, ) = $data;
        }
        // read the code
        if ($minorCodeVersion !== null) {
            $sql = <<<END
                SELECT id, botbasic_version, code_minor_version, code_subminor_version, messages, menus, magicvars, primitives, program 
                  FROM bbcode
                 WHERE code_name = '$codename'
                   AND code_major_version = '$majorCodeVersion'
                   AND code_minor_version = '$minorCodeVersion'
                   AND deleted IS NULL
                 ORDER BY code_subminor_version DESC
                 LIMIT 1;
END;
        }
        else {
            $sql = <<<END
                SELECT id, botbasic_version, code_minor_version, code_subminor_version, messages, menus, magicvars, primitives, program 
                  FROM bbcode
                 WHERE code_name = '$codename'
                   AND code_major_version = '$majorCodeVersion'
                   AND deleted IS NULL
                 ORDER BY code_minor_version DESC, code_subminor_version DESC
                 LIMIT 1;
END;
        }
        $rows = self::query($sql);
        if ($rows === false)    { return null;  }
        if (count($rows) === 0) { return false; }
        list ($id, $bbVersion, $minorCodeVersion, $subminorCodeVersion, $messages, $menus, $magicvars, $primitives, $program) = array_values($rows[0]);
        return [
            $id, $bbVersion, $codename, $majorCodeVersion, $minorCodeVersion, $subminorCodeVersion,
            unserialize($messages), unserialize($menus), unserialize($magicvars), unserialize($primitives), unserialize($program)
        ];
    }



    /**
     * Dado un codename de un programa BotBasic y un número de versión mayor (opcional), lee de BD (tabla "bbcode") y retorna la información
     * completa de versionamiento de la versión del programa más avanzada
     *
     * @param  string           $bbCodeName         Codename del programa BotBasic
     * @param  null|string      $majorCodeVersion   Especificar aquí un número de versión mayor restringe la búsqueda; usar null para no restringir
     * @return array|bool|null                      null en caso de error de SQL; false si no hay coincidencias;
     *                                              [ code_major_version, code_minor_version, code_subminor_version ] de otro modo
     */
    static public function readLastBBCodeVersionForCodename ($bbCodeName, $majorCodeVersion = null)
    {
        self::connect();
        $andClause = $majorCodeVersion !== null ? "AND code_major_version = '$majorCodeVersion'" : '';
        $sql = <<<END
            SELECT code_major_version, code_minor_version, code_subminor_version
              FROM bbcode
             WHERE code_name = '$bbCodeName'
                   $andClause
               AND deleted IS NULL
             ORDER BY code_major_version DESC, code_minor_version DESC, code_subminor_version DESC
             LIMIT 1;
END;
        $rows = self::query($sql);
        if ($rows === false)    { return null;  }
        if (count($rows) === 0) { return false; }
        return array_values($rows[0]);
    }



    /**
     * Lee las rutas actuales de una instancia del runtime y a la vez el mapa de labels del programa BotBasic que está asociado a esa instancia
     *
     * Este es un método dirigido a la implementación optimizada del mecanismo de actualización de la versión del programa BotBasic que se
     * está corriendo en la instancia del runtime, según el esquema de versionamiento semántico de BotBasic.
     *
     * @param  int          $bbRuntimeId                    ID de la instancia
     * @param  string       $bbRuntimeMajorCodeVersion      Versión mayor del código asociado a la instancia
     * @param  string       $bbBotName                      Nombre del bot del programa BotBasic para el cual se extraerán los labels
     * @return array[]|null                                 null en caso de error de SQL; [ [ bbcId, $route, $labels ], ... ] en caso de éxito;
     *                                                      puede retornar un arreglo vacío en caso de que no se consigan aciertos; estos labels
     *                                                      vienen en el formato en el que están almacenados en las instancias de las subclases de BotBasic
     */
    static public function readAllRouteQueuesAndBBlabelsForBBruntime ($bbRuntimeId, $bbRuntimeMajorCodeVersion, $bbBotName)
    {
        self::connect();
        $sql = <<<END
            SELECT ch.id, ch.route, cd.program
              FROM bbchannel AS ch
              JOIN runtime AS rt ON ch.runtime_id = rt.id
              JOIN bbcode AS cd ON rt.code_major_version = cd.code_major_version AND rt.code_minor_version = cd.code_minor_version
             WHERE rt.id = $bbRuntimeId
               AND rt.code_major_version = '$bbRuntimeMajorCodeVersion'
               AND cd.code_subminor_version = (
                   SELECT MAX(cd2.code_subminor_version)
                     FROM bbcode AS cd2
                    WHERE cd2.code_major_version = cd.code_major_version
                      AND cd2.code_minor_version = cd.code_minor_version
                      AND cd2.deleted IS NULL
                   )
               AND ch.deleted IS NULL
               AND rt.deleted IS NULL
               AND cd.deleted IS NULL
             ORDER BY cd.code_minor_version DESC;
END;
        $rows = self::query($sql);
        if ($rows === false) { return null; }
        $res = [];
        foreach ($rows as $row) {
            list ($bbcId, $route, $program) = array_values($row);
            $route   = unserialize($route);
            $program = unserialize($program);
            $labels  = isset($program[$bbBotName]) ? $program[$bbBotName]['labels'] : null;
            $res[]   = [ $bbcId, $route, $labels ];
        }
        return $res;
    }



    /**
     * Lee los labels asociados a las versiones de programas BotBasic identificados por un codename y una versión mayor, siempre que
     * que esas versiones sean más recientes que la versión menor especificada (la cual no se incluye en los resultados)
     *
     * Este es un método dirigido a la implementación optimizada del mecanismo de actualización de la versión del programa BotBasic que se
     * está corriendo en la instancia del runtime, según el esquema de versionamiento semántico de BotBasic.
     *
     * @param  string       $bbCodename             Codename del programa BotBasic
     * @param  string       $bbCodeMajorVersion     Versión mayor del programa BotBasic (debe coincidir)
     * @param  string       $bbCodeMinorVersion     Versión menor del programa BotBasic que sirve como base para la búsqueda
     * @param  string       $bbBotName              Nombre del bot del programa BotBasic para el cual se extraerán los labels
     * @return array[]|null                         null en caso de error de SQL; [ [ majorVersion, minorVersion, labels ], ... ] en caso de éxito;
     *                                              puede retornar un arreglo vacío en caso de que no se consigan aciertos; estos labels
     *                                              vienen en el formato en el que están almacenados en las instancias de las subclases de BotBasic
     */
    static public function readNewerBBcodeVersionsLabels ($bbCodename, $bbCodeMajorVersion, $bbCodeMinorVersion, $bbBotName)
    {
        self::connect();
        $sql = <<<END
            SELECT cd1.code_major_version, cd1.code_minor_version, cd1.code_subminor_version, cd1.program
              FROM bbcode AS cd1
             WHERE cd1.code_name = '$bbCodename'
               AND cd1.code_major_version = '$bbCodeMajorVersion'
               AND cd1.code_minor_version > '$bbCodeMinorVersion'
               AND cd1.code_subminor_version = (
                   SELECT MAX(cd2.code_subminor_version)
                     FROM bbcode AS cd2
                    WHERE cd2.code_name = cd1.code_name
                      AND cd2.code_major_version = cd1.code_major_version
                      AND cd2.code_minor_version = cd1.code_minor_version
                      AND cd2.deleted IS NULL 
                   )
               AND cd1.deleted IS NULL                      
             ORDER BY cd1.code_major_version DESC, cd1.code_minor_version DESC, cd1.code_subminor_version DESC;
END;
        $rows = self::query($sql);
        if ($rows === false) { return null; }
        $res = [];
        foreach ($rows as $row) {
            list ($major, $minor, $subminor, $program) = array_values($row);
            $program = unserialize($program);
            $labels  = isset($program[$bbBotName]) ? $program[$bbBotName]['labels'] : null;
            $res[]   = [ $major, $minor, $subminor, $labels ];
        }
        return $res;
    }



    ///////////////////
    // TELEGRAM LOG BOT
    ///////////////////



    /**
     * Escribe o reemplaza un registro de credenciales de logging sobre bots de Telegram, para un bot determinado definido como bot de logging
     *
     * @param  int      $bbBotId            ID del bot del programa BotBasic, tal como está definido en ChatMedium::$bbBots
     *                                      y ChatMediumTelegram::$cmLogBots
     * @param  string   $cmFullUserName     Concatenación de nombre, espacio y apellido, del usuario de Telegram
     * @param  string   $cmChannelId        ID del ChatMediumChannel que será usado para dirigir las réplicas de los mensajes de log
     * @return int|null                     null en caso de error de SQL; o el ID del registro recién insertado
     */
    static public function writeTelegramLogBotCredentials ($bbBotId, $cmFullUserName, $cmChannelId)
    {
        self::connect();
        $cmFullUserName = self::q($cmFullUserName);
        $sql = <<<END
            REPLACE INTO telegram_logbot (bb_bot_id, cm_full_user_name, cmchannel_id)
            VALUES ($bbBotId, $cmFullUserName, $cmChannelId);
END;
        $res = self::exec($sql);
        if ($res === false) { return null; }
        return true;
    }



    /**
     * Lee un apuntador a las credenciales necesarias para postear splashes sobre un bot de logging en Telegram
     *
     * @param  int                      $bbBotId                        ID del bot del programa BotBasic, tal como está definido en ChatMedium::$bbBots
     *                                                                  y ChatMediumTelegram::$cmLogBots
     * @param  null|string|string[]     $filterByTheseCMfullUserNames   Nombres completos de usuario de Telegram por los que se filtrará la selección (opcional)
     * @return array|bool|null                                          null en caso de error de SQL; false si no hay credenciales para el bot y filtro;
     *                                                                  [ [ cm_full_user_name, cmchannel_id ], ... ] en caso de haberlas
     */
    static public function readTelegramLogBotCredentials ($bbBotId, $filterByTheseCMfullUserNames = null)
    {
        self::connect();
        if     ($filterByTheseCMfullUserNames === null)   { $filterByTheseCMfullUserNames = [];                                }
        elseif (is_string($filterByTheseCMfullUserNames)) { $filterByTheseCMfullUserNames = [ $filterByTheseCMfullUserNames ]; }
        if (count($filterByTheseCMfullUserNames) == 0) {
            $inClause = '';
        }
        else {
            $quoted = [];
            foreach ($filterByTheseCMfullUserNames as $name) { $quoted[] = self::q($name); }
            $inClause = 'AND cm_full_user_name IN (' . implode(', ', $quoted) . ')';
        }
        $sql = <<<END
            SELECT cm_full_user_name, cmchannel_id
              FROM telegram_logbot
             WHERE bb_bot_id = $bbBotId
                   $inClause
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if ($rows === false) { return null; }
        $res = [];
        foreach ($rows as $row) { $res[] = array_values($row); }
        return $res;
    }



    ////////////////////////////////////////
    // CHATMEDIUM : MESSAGE QUEUE - TELEGRAM
    ////////////////////////////////////////



    /**
     * Inserta el contenido de un Splash en la cola de envío de Telegram (tabla "telegram_queue")
     *
     * @param  string|null  $text               Texto del Splash, o null en caso de no haber
     * @param  array|null   $menuOptions        Opciones del menú del Splash, o null en caso de no haber
     * @param  mixed|null   $resource|null      InteractionResource incluido en el Splash, o null de no haber
     * @param  int          $cmcId              ID del ChatMediumChannel al cual está asociado el Splash
     * @param  int|null     $specialOrder       Orden especial dirigida a Telegram, que no es un post regular
     * @param  mixed|null   $specialOrderArg    Argumento de la orden especial dirigida a Telegram
     * @return int|null                         null en caso de error de SQL; o el ID del registro recién insertado
     */
    static public function writeToTelegramMessageQueue ($text, $menuOptions, $resource, $cmcId, $specialOrder = null, $specialOrderArg = null)
    {
        self::connect();
        $text            = self::q($text);
        $menuOptions     = self::q(serialize($menuOptions));
        $resource        = self::q(serialize($resource));
        $specialOrder    = self::q($specialOrder);
        $specialOrderArg = self::q(serialize($specialOrderArg));
        $sql = <<<END
            INSERT INTO telegram_queue (text, menu_options, resource, special_order, special_order_arg, cmchannel_id)
            VALUES ($text, $menuOptions, $resource, $specialOrder, $specialOrderArg, $cmcId);
END;
        $newId = self::exec($sql, true);
        if ($newId === false) { return null; }
        return $newId;
    }



    /**
     * Inicia el desencolado de un Splash registrado en la cola de envío de Telegram, retornando su contenido y marcándolo como "enviando"
     *
     * Este método filtra por un "send attempts try count" especificado y garantiza que se retorna el más antiguo que esté pendiente por enviar
     * que tenga exactamente esa cantidad de intentos de envío. Esto permite implementar una cola de envíos que no se bloqueará por fallas en
     * envíos de un Splash específico atribuibles a causas situadas en los servidores de la chatapp.
     *
     * @param  int  $tryCount   Cantidad de intentos de envío por la que se desea filtrar
     * @return array|bool|null  null en caso de error de SQL; false si no hay Splashes pendientes por enviar;
     *                          [ id, text, menuOptions, interactionResource, cmcBotName, cmcChatInfo ] en caso de haberlos
     */
    static public function readFirstInTelegramMessageQueueAndMarkAsSending ($tryCount)
    {
        self::connect();
        // closures
        $unlock = function () {
            $sql = <<<END
                UNLOCK TABLES;
END;
            self::exec($sql);
        };
        // lock the table
        $sql = <<<END
            LOCK TABLES telegram_queue AS tq1 WRITE, telegram_queue AS tq2 WRITE, telegram_queue AS tq3 WRITE, cmchannel AS cmc READ;
END;
        $res = self::exec($sql);
        if ($res === false) {
            $unlock();
            Log::register(Log::TYPE_DATABASE, "DBB492");
            return null;
        }
        // read the next message to send:
        // select the oldest pending message with the specified tryCount that has no previous pending messages when associated by their common cmChannelId
        // this guarantees that messages are sent in the proper order and allows balancing of the send queue according to the tryCount attribute
        // special case for cmchannel_id = -1 was added in order to allow early submitting of orders to Telegram, before creation of CMC instances
        $sql = <<<END
            (
            SELECT tq1.id, tq1.text, tq1.menu_options, tq1.resource, tq1.special_order, tq1.special_order_arg, cmc.cm_bot_name, cmc.cm_chat_info 
              FROM telegram_queue AS tq1
              JOIN cmchannel AS cmc ON tq1.cmchannel_id = cmc.id  
             WHERE tq1.state = 'pending'
               AND tq1.try_count = $tryCount
               AND tq1.id = (
                    SELECT tq2.id 
                      FROM telegram_queue AS tq2 
                     WHERE tq2.state = 'pending'
                       AND tq2.try_count = $tryCount
                       AND tq2.cmchannel_id = tq1.cmchannel_id 
                       AND tq2.deleted IS NULL
                     ORDER BY tq2.id ASC
                     LIMIT 1 )
               AND tq1.deleted IS NULL
             ORDER BY tq1.id ASC
             LIMIT 1
            ) 
            UNION 
            (
            SELECT tq3.id, tq3.text, tq3.menu_options, tq3.resource, tq3.special_order, tq3.special_order_arg, NULL, NULL
              FROM telegram_queue AS tq3
             WHERE tq3.state = 'pending'
               AND tq3.try_count = $tryCount
               AND tq3.cmchannel_id = -1
             ORDER BY tq3.id ASC
             LIMIT 1
            );
END;
        $rows = self::query($sql);
        if ($rows === false) {
            $unlock();
            Log::register(Log::TYPE_DATABASE, "DBB520");
            return null;
        }
        if (count($rows) == 0) {
            $unlock();
            return false;
        }
        list ($id, $text, $menuOptions, $resource, $specialOrder, $specialOrderArg, $cmBotName, $cmChatInfo) = array_values($rows[0]);
        $menuOptions     = unserialize($menuOptions);
        $resource        = unserialize($resource);
        $specialOrderArg = unserialize($specialOrderArg);
        $cmChatInfo      = $cmChatInfo === null ? null : unserialize($cmChatInfo);
        $data            = [ $id, $text, $menuOptions, $resource, $specialOrder, $specialOrderArg, $cmBotName, $cmChatInfo ];
        // mark as sending
        $sql = <<<END
            UPDATE telegram_queue AS tq1
               SET state = 'sending'
             WHERE id = $id
               AND deleted IS NULL;
END;
        $res = self::exec($sql);
        if ($res === false) {
            $unlock();
            return null;
        }
        // unlock tables and return
        $unlock();
        return $data;
    }



    /**
     * Finaliza el desencolado de un Splash de la cola de Telegram
     *
     * Este método es invocado, a modo de commit, cuando el envío a los servidores de Telegram tiene éxito.
     *
     * @param  int          $id     ID del registro en la cola de envío
     * @return bool|null            null en caso de error de SQL; true en caso de éxito
     */
    static public function markAsSentInTelegramMessageQueue ($id)
    {
        self::connect();
        $sql = <<<END
            UPDATE telegram_queue
               SET state = 'sent', try_count = try_count + 1
             WHERE id = $id
               AND deleted IS NULL;
END;
        $res = self::exec($sql);
        if ($res === false) { return null; }
        return true;
    }



    /**
     * Finaliza (alternativamente) el desencolado de un Splash de la cola de Telegram
     *
     * Este método es invocado, a modo de rollback, cuando el envío a los servidores de Telegram no tiene éxito, e incrementa el
     * "try count" del registro, traspasando su status a "error" cuando se excede el límite máximo especificado.
     *
     * @param  int          $id             ID del registro en la cola de envío
     * @param  null|int     $countLimit     Límite máximo de intentos de envíos; al alcanzarlo se cambia el status del registro
     * @return int|null                     null en caso de error de SQL; nuevo valor del "try count" en caso de éxito
     */
    static public function markAsUnsentInTelegramMessageQueue ($id, $countLimit = null)
    {
        self::connect();
        // mark (reset as pending) and increment try_count
        $sql = <<<END
            UPDATE telegram_queue
               SET state = 'pending', try_count = try_count + 1
             WHERE id = $id
               AND deleted IS NULL;
END;
        $res = self::exec($sql);
        if ($res === false) { return null; }
        // read the new try_count
        $sql = <<<END
            SELECT try_count
              FROM telegram_queue
             WHERE id = $id
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if ($rows === false)   { return null; }
        if (count($rows) == 0) { return false; }
        $count = $rows[0]['try_count'];
        // update state if sent limit is reached
        if ($countLimit !== null && $count >= $countLimit) {
            $sql = <<<END
                UPDATE telegram_queue
                   SET state = 'error'
                 WHERE id = $id
                   AND deleted IS NULL;
END;
            $res = self::exec($sql);
            if ($res === false) { return null; }
        }
        // return new try count
        return $count;
    }



    ///////////////////////////////////////////
    // RESOURCE : DOWNLOAD QUEUE - ALL CHATAPPS
    ///////////////////////////////////////////



    /**
     * Inicia el desencolado de InteractionResources para posibilitar la descarga de los contenidos (archivos) asociados desde los
     * servidores de una chatapp especificada
     *
     * Este método filtra por un "send attempts try count" especificado y garantiza que se retorna el más antiguo que esté pendiente por descargar
     * que tenga exactamente esa cantidad de intentos de descargas. Esto permite implementar una cola de descargas que no se bloqueará por fallas en
     * los protocolos de download relativos a un resource específico atribuibles a causas situadas en los servidores de la chatapp.
     *
     * El uso de la tabla "resource" de la BD para la cola de descargas es complementario a su uso principal, que es el registro de todos
     * los resources asociados a las Interactions que son manejadas por BotBasic. En este sentido, esta tabla es diferente a la tabla "telegram_queue".
     *
     * @param  int              $cmType         Una de las constantes ChatMedium::TYPE_...
     * @param  int              $tryCount       Cantidad de intentos de envío por la que se desea filtrar
     * @return array|bool|null                  null en caso de error de SQL; false de no haber resources que deban ser descargados;
     *                                          [ id, type, fileId ] en caso de haberlos
     */
    static public function readFirstInResourcesQueueAndMarkAsDownloading ($cmType, $tryCount)
    {
        self::connect();
        // closures
        $unlock = function () {
            $sql = <<<END
                UNLOCK TABLES;
END;
            self::exec($sql);
        };
        // lock the table
        $sql = <<<END
            LOCK TABLES resource WRITE;
END;
        $res = self::exec($sql);
        if ($res === false) {
            $unlock();
            Log::register(Log::TYPE_DATABASE, "DBB663");
            return null;
        }
        // read the next resource to download
        $sql = <<<END
            SELECT id, type, metainfo, chatmedium_authinfo, file_id 
              FROM resource
             WHERE chatmedium_type = $cmType 
               AND download_state = 'pending'
               AND file_id IS NOT NULL
               AND try_count = $tryCount
               AND deleted IS NULL
             ORDER BY id ASC
             LIMIT 1;
END;
        $rows = self::query($sql);
        if ($rows === false) {
            $unlock();
            Log::register(Log::TYPE_DATABASE, "DBB681");
            return null;
        }
        if (count($rows) == 0) {
            $unlock();
            return false;
        }
        list ($id, $type, $metainfo, $cmAuthInfo, $fileId) = array_values($rows[0]);
        // mark as downloading
        $sql = <<<END
            UPDATE resource
               SET download_state = 'doing'
             WHERE id = $id
               AND deleted IS NULL;
END;
        $res = self::exec($sql);
        if ($res === false) {
            $unlock();
            return null;
        }
        // unlock tables and return
        $unlock();
        return [ $id, $type, unserialize($metainfo), $cmAuthInfo, $fileId ];
    }



    /**
     * Finaliza el desencolado de un resource sólo a efectos de descarga del archivo que representa su contenido
     *
     * Este método es invocado, a modo de commit, cuando el envío a los servidores de Telegram tiene éxito.
     *
     * @param  int          $id             ID del registro (resource)
     * @param  string       $filename       Nombre de archivo dentro del servidor al que se asociará el resource descargado
     * @return bool|null                    null en caso de error de SQL; true en caso de éxito
     */
    static public function markAsDownloadedInResourcesQueue ($id, $filename)
    {
        self::connect();
        $sql = <<<END
            UPDATE resource
               SET download_state = 'done', try_count = try_count + 1, filename = '$filename'
             WHERE id = $id
               AND deleted IS NULL;
END;
        $res = self::exec($sql);
        if ($res === false) { return null; }
        return true;
    }



    /**
     * Finaliza (alternativamente) el desencolado de un resource sólo a efectos de descarga del archivo que representa su contenido
     *
     * Este método es invocado, a modo de rollback, cuando la descarga del contenido desde los servidores de la chatapp no tiene éxito,
     * e incrementa el "try count" del registro, traspasando su status a "error" cuando se excede el límite máximo especificado.
     *
     * @param  int          $id             ID del registro (resource)
     * @param  null|int     $countLimit     Límite máximo de intentos de envíos; al alcanzarlo se cambia el status del registro
     * @return int|null                     null en caso de error de SQL; nuevo valor del "try count" en caso de éxito
     */
    static public function markAsNonDownloadedInResourcesQueue ($id, $countLimit = null)
    {
        self::connect();
        // mark (reset as pending) and increment try_count
        $sql = <<<END
            UPDATE resource
               SET download_state = 'pending', try_count = try_count + 1
             WHERE id = $id
               AND deleted IS NULL;
END;
        $res = self::exec($sql);
        if ($res === false) { return null; }
        // read the new try_count
        $sql = <<<END
            SELECT try_count
              FROM resource
             WHERE id = $id
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if ($rows === false)   { return null; }
        if (count($rows) == 0) { return false; }
        $count = $rows[0]['try_count'];
        // update state if sent limit is reached
        if ($countLimit !== null && $count >= $countLimit) {
            $sql = <<<END
                UPDATE resource
                   SET download_state = 'error'
                 WHERE id = $id
                   AND deleted IS NULL;
END;
            $res = self::exec($sql);
            if ($res === false) { return null; }
        }
        // return new try count
        return $count;
    }



    ////////////////////
    // CHATMEDIUMCHANNEL
    ////////////////////



    /**
     * Lee el ID del registro de la tabla "cmchannel" correspondiente a un triplete de identificación del ChatMediumChannel
     *
     * @param  int              $cmType     Una de las constantes ChatMedium::TYPE_...
     * @param  string           $cmUserId   ID del usuario de la chatapp, tal como es reportado por ella
     * @param  string           $cmBotName  Nombre del bot de la chatapp
     * @return int|bool|null                ID del registro; o false si no existe para el triplete indicado; o null si hay error de SQL
     */
    static public function readChatMediumChannelId ($cmType, $cmUserId, $cmBotName)
    {
        self::connect();
        $sql = <<<END
            SELECT id
              FROM cmchannel
             WHERE cm_type = $cmType
               AND cm_user_id = '$cmUserId'
               AND cm_bot_name = '$cmBotName'
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        return $rows[0]['id'];
    }



    /**
     * Lee de la BD un registro asociado a un ChatMediumChannel a partir de su ID
     *
     * @param  int              $id     ID del ChatMediumChannel tal como fue producido por writeChatMediumChannel()
     * @return array|bool|null          null en caso de error de SQL; false si el ID no se encuentra registrado en la tabla;
     *                                  [ cmType, cmUserId, cmBotName, cmChatInfo, dbBBchannelId ] en caso de éxito
     */
    static public function readChatMediumChannel ($id)
    {
        self::connect();
        $sql = <<<END
            SELECT cm_type, cm_user_id, cm_bot_name, cm_chat_info, bbchannel_id
              FROM cmchannel
             WHERE id = $id
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        list ($cmType, $cmUserId, $cmBotName, $cmChatInfo, $dbBBchannelId) = array_values($rows[0]);
        $cmChatInfo = unserialize($cmChatInfo);
        return [ $cmType, $cmUserId, $cmBotName, $cmChatInfo, $dbBBchannelId ];
    }



    /**
     * Guarda en BD la información de una instancia de ChatMediumChannel
     *
     * @param  ChatMediumChannel    $cmChannel      Instancia
     * @return bool|null                            null si hay error de SQL; false si el ID no existe en la BD; true en caso de éxito
     */
    static public function writeChatMediumChannel ($cmChannel)
    {
        self::connect();
        if ($cmChannel === null) { return null; }
        $id          = $cmChannel->getId();
        $cmType      = $cmChannel->getCMtype();
        $cmUserId    = self::q($cmChannel->getCMuserId());
        $cmBotName   = self::q($cmChannel->getCMbotName());
        $cmChatInfo  = self::q(serialize($cmChannel->getCMchatInfo()));
        $bbChannelId = $cmChannel->getBBchannel()->getId();
        if ($id === null) {   // new CMC
            $sql = <<<END
                INSERT INTO cmchannel (cm_type, cm_user_id, cm_bot_name, cm_chat_info, bbchannel_id)
                VALUES ($cmType, $cmUserId, $cmBotName, $cmChatInfo, $bbChannelId);
END;
            $id = self::exec($sql, true);
            if ($id === false) { return null; }
            return $id;
        }
        else {   // existing CMC
            $tgtCmChatInfo = $cmChannel->getCMchatInfo() === null ? 'cm_chat_info' : $cmChatInfo;   // don't overwrite is cmc has null authinfo
            $additionalSet = $cmChannel->isDeleted() ? ", deleted = NOW()" : '';
            $sql = <<<END
                UPDATE cmchannel
                   SET cm_type = $cmType, cm_user_id = $cmUserId, cm_bot_name = $cmBotName, cm_chat_info = $tgtCmChatInfo, 
                       bbchannel_id = $bbChannelId $additionalSet
                 WHERE id = $id
                   AND deleted IS NULL;
END;
            $rowCount = self::exec($sql);
            if     ($rowCount === false) { return null;  }
            elseif ($rowCount == 0)      { return false; }
            return true;
        }
    }



    /**
     * Lee el userId de la chatapp que está asociado a cualquier bot de chatapp que esté especificado dentro del argumento
     *
     * @param  array            $cmBots     Arreglo de la forma: [ [ cmType, cmBotName ], ... ], donde cmType es una de ChatMedium::TYPE_...
     * @param  int              $runtimeId  ID del runtime por el que se filtrará el resultado
     * @return array|bool|null              null si hay error de SQL; false si no hay coincidencias;
     *                                      [ cm_type, cm_bot_name, cm_user_id, bool-if-cmc-entry-deleted, bbc_id ] en caso de éxito
     */
    static public function readCMuserIdForLastUsedCMchannel ($cmBots, $runtimeId)
    {
        self::connect();
        $whereClause = "FALSE";
        foreach ($cmBots as $cmBotData) {
            list ($cmType, $cmBotName) = $cmBotData;
            $whereClause .= " OR cmc.cm_type = $cmType AND cmc.cm_bot_name = '$cmBotName'";
        }
        $sql = <<<END
           (SELECT cmc.cm_type, cmc.cm_bot_name, cmc.cm_user_id, cmc.deleted, bbc.id 
              FROM cmchannel AS cmc
              JOIN bbchannel AS bbc ON cmc.bbchannel_id = bbc.id
             WHERE bbc.runtime_id = $runtimeId
               AND ($whereClause)
               AND bbc.deleted IS NULL
               AND cmc.deleted IS NULL
             ORDER BY cmc.updated DESC
           ) UNION (
             SELECT cmc.cm_type, cmc.cm_bot_name, cmc.cm_user_id, cmc.deleted, bbc.id 
              FROM cmchannel AS cmc
              JOIN bbchannel AS bbc ON cmc.bbchannel_id = bbc.id
             WHERE bbc.runtime_id = $runtimeId
               AND ($whereClause)
               AND bbc.deleted IS NULL
               AND cmc.deleted IS NOT NULL
             ORDER BY cmc.updated DESC
           ) LIMIT 1;
END;
        // this is not active:   // AND cmc.deleted IS NULL   // intentionally left out (allowing procedure for re-attaching a CMC to the BBC where it was previously detached) (USERID FROM)
        //
        // TODO importante para funcionamiento correcto de USERID FROM:
        // - al final de cada SELECT poner una columna fantasma: ... , 1 AS sortorder
        //                                    en el otro SELECT: ... , 2 AS sortorder
        // - antes del LIMIT poner ORDER BY sortorder ASC
        // - justo abajo en el list(...) ignorar la columna fantasma
        //
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        list ($cmType, $cmBotName, $cmUserId, $deleted, $bbcId) = array_values($rows[0]);
        return [ $cmType, $cmBotName, $cmUserId, $deleted !== null, $bbcId ];
    }



    /**
     * Lee los nombres de bots de chatapps que han sido utilizados para un userId de chatapp específico dentro de la chatapp específica
     *
     * Los nombres de bots retornados deben aparecer dentro del arreglo pasado como tercer parámetro.
     *
     * @param  int              $cmType                     Una de las constantes ChatMedium::TYPE_...
     * @param  string           $cmUserId                   ID del usuario de la chatapp
     * @param  string[]         $onlyFromTheseCMbotNames    Arreglo con los nombres de los bots de los que un subconjunto será retornado
     * @return string[]|null                                Bots coincidentes, en orden ascendente por timestamp de última modificación;
     *                                                      o null si hay error de SQL
     */
    static public function readUsedCMchannelBotNames ($cmType, $cmUserId, $onlyFromTheseCMbotNames)
    {
        self::connect();
        $onlyFromTheseCMbotNames[] = true;   // hack
        $inClause = $onlyFromTheseCMbotNames === null || count($onlyFromTheseCMbotNames) == 0 ? "" :
                    "AND cm_bot_name IN ('" . implode("', '", $onlyFromTheseCMbotNames) . "')";
        $sql = <<<END
            SELECT cm_bot_name
              FROM cmchannel
             WHERE cm_type = $cmType
               AND cm_user_id = '$cmUserId'
                   $inClause
               AND deleted IS NULL
              SORT BY updated ASC
END;
        $rows = self::query($sql);
        if ($rows === false) { return null;  }
        $res = [];
        foreach ($rows as $row) { $res[] = array_values($row); }
        return $res;
    }



    /**
     * Lee de la BD la información principal de un registro asociado a un ChatMediumChannel que se corresponda, para un ChatMedium específico,
     * al mismo BotBasicChannel que se pasa como argumento
     *
     * @param  BotBasicChannel  $bbChannel  Instancia del canal del programa BotBasic al cual debe estar asociado el ChatMediumChannel leido
     * @param  int              $cmType     Una de las constantes ChatMedium::TYPE_...
     * @return array|bool|null              null en caso de error de SQL; false si el registro no se encuentra registrado en la tabla;
     *                                      [ cmUserId, cmBotName ] en caso de éxito
     */
    static public function readCMchannelDataForBBchannel ($bbChannel, $cmType)
    {
        self::connect();
        $bbcId = $bbChannel->getId();
        $sql   = <<<END
            SELECT cm_user_id, cm_bot_name
              FROM cmchannel
             WHERE bbchannel_id = $bbcId
               AND cm_type = $cmType
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        return array_values($rows[0]);
    }



    //////////////////
    // BOTBASICCHANNEL
    //////////////////



    /**
     * Lee de la BD un registro asociado a la tabla "bbchannel" por su ID, filtrando opcionalmente por un ID de runtime asociado al registro
     *
     * @param  int              $id                         ID del BotBasicChannel
     * @param  null|int         $filterByThisRuntimeId      ID de runtime asociado al BotBasicChannel por el que se filtra, o null para no filtrar
     * @return array|null|bool                              null en caso de error de SQL; false si no hay coincidencia;
     *                                                      [ callStack, route, rtId ] en caso de éxito
     */
    static public function readBotBasicChannel ($id, $filterByThisRuntimeId = null)
    {
        self::connect();
        $and = $filterByThisRuntimeId === null ? '' : "AND runtime_id = $filterByThisRuntimeId";
        $sql = <<<END
            SELECT call_stack, route, runtime_id
              FROM bbchannel
             WHERE id = $id
                   $and
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        list ($callStack, $route, $rtId) = array_values($rows[0]);
        return [ unserialize($callStack), unserialize($route), $rtId ];
    }



    /**
     * Escribe en la BD la información de una instancia de BotBasicChannel
     *
     * @param  BotBasicChannel  $bbChannel      Instancia
     * @return bool|null|int                    null si hay error de SQL; false si el ID no existe en la BD; true en caso de éxito con update, el nuevo ID en caso de éxito con insert
     */
    static public function writeBotBasicChannel ($bbChannel)
    {
        self::connect();
        if ($bbChannel === null) { return null; }
        $id          = $bbChannel->getId();
        $callStack   = self::q(serialize($bbChannel->getCallStack()));
        $routeQueue  = self::q(serialize($bbChannel->getRouteQueue()));
        $bbRuntimeId = $bbChannel->getBBruntime()->getId();
        if ($id === null) {   // new BBC
            $additionalColumn = $bbChannel->isDeleted() ? ", deleted" : '';
            $additionalValue  = $bbChannel->isDeleted() ? ", NOW()"   : '';
            $sql = <<<END
                INSERT INTO bbchannel (call_stack, route, runtime_id $additionalColumn)
                VALUES ($callStack, $routeQueue, $bbRuntimeId $additionalValue);
END;
            $id = self::exec($sql, true);
            if ($id === false) { return null; }
            return $id;
        }
        else {   // existing BBC (tunnels are not updated; see specific method)
            $additionalSet = $bbChannel->isDeleted() ? ", deleted = NOW()" : '';
            $sql = <<<END
                UPDATE bbchannel
                   SET call_stack = $callStack, route = $routeQueue, runtime_id = $bbRuntimeId $additionalSet
                 WHERE id = $id
                   AND deleted IS NULL;
END;
            $rowCount = self::exec($sql);
            if     ($rowCount === false) { return null;  }
            elseif ($rowCount == 0)      { return false; }
            return true;
        }
    }



    /**
     * Lee el ID del registro de la tabla "bbchannel" correspondiente a un triplete de identificación del ChatMediumChannel
     *
     * @param  int              $cmType     Una de las constantes ChatMedium::TYPE_...
     * @param  string           $cmUserId   ID del usuario de la chatapp, tal como es reportado por ella
     * @param  string           $cmBotName  Nombre del bot de la chatapp
     * @return int|bool|null                ID del registro; o false si no existe para el triplete indicado; o null si hay error de SQL
     */
    static public function readBBchannelIdByCMchannelData ($cmType, $cmUserId, $cmBotName)
    {
        self::connect();
        $sql = <<<END
            SELECT bbc.id AS id
              FROM bbchannel AS bbc
              JOIN cmchannel AS cmc ON cmc.bbchannel_id = bbc.id
             WHERE cmc.cm_type = $cmType
               AND cmc.cm_user_id = '$cmUserId'
               AND cmc.cm_bot_name = '$cmBotName'
               AND bbc.deleted IS NULL
               AND cmc.deleted IS NULL;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        return $rows[0]['id'];
    }



    /**
     * Lee de la BD el ID del BotBasicChannel por defecto a partir del ID de un BizModel user
     *
     * TODO optimizar (cada PRINT ON foráneo invoca a esta rutina; en otras palabras, es conveniente implementar un cache, que se puede hacer aquí)
     *
     * @param  int          $bmUserId       ID del BizModel user
     * @param  string[]     $cmBotNames     Nombres de bots de chatapps por el que se filtrará la tabla cmchannel y que corresponden a canales por defecto
     * @return bool|null                    null en caso de error de SQL; false si no hay coincidencia; el ID del runtime en caso de éxito
     */
    static public function readDefaultChannelIdByBizModelUserId ($bmUserId, $cmBotNames)
    {
        self::connect();
        $cmBotsList = "'" . implode("', '", $cmBotNames) . "'";
        $sql = <<<END
            SELECT bbc.id AS id
              FROM cmchannel AS cmc
              JOIN bbchannel AS bbc ON cmc.bbchannel_id = bbc.id
              JOIN runtime AS rt ON bbc.runtime_id = rt.id
             WHERE cmc.cm_bot_name IN ($cmBotsList)
               AND rt.bizmodel_user_id = $bmUserId
             ORDER BY cmc.updated DESC
             LIMIT 1;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        return $rows[0]['id'];
    }



    /////////////////
    // BOTBASICTUNNEL
    /////////////////



    /**
     * Lee los túneles del programa BotBasic asociados a una instancia de BotBasicChannel
     *
     * @param  int          $bbcId      ID de la instancia
     * @return array|null               null en caso de error de SQL; [ [ resource_type, tgt_bbchannel_id ], ... ] en caso de éxito
     */
    static public function readBotBasicTunnels ($bbcId)
    {
        self::connect();
        $sql = <<<END
            SELECT resource_type, tgt_bbchannel_id
              FROM bbtunnel
             WHERE src_bbchannel_id = $bbcId
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if ($rows === false) { return null; }
        $res = [];
        foreach ($rows as $row) { $res[] = array_values($row); }
        return $res;
    }



    /**
     * Escribe en BD los túneles asociados a una instancia de BotBasicChannel
     *
     * La escritura preserva los ID de los túneles (tabla "bbtunnel") ya preexistentes que no hayan tenido cambios.
     *
     * @param  BotBasicChannel  $bbChannel      Instancia de BotBasicChannel
     * @return bool|null                        null en caso de error de BD; true en caso de éxito
     */
    static public function writeBotBasicTunnels ($bbChannel)
    {
        self::connect();
        if ($bbChannel === null) { return null; }
        $srcBbcId = $bbChannel->getId();
        // "rawize" BBC-object tunnels
        $objTunnelsFlat = [];
        foreach ($bbChannel->getTunnels() as $resourceType => $targetBbbcs) {
            foreach ($targetBbbcs as $targetBbc) {   /** @var BotBasicChannel $targetBbc */
                $objTunnelsFlat[] = $resourceType . "|" . $targetBbc->getId();
            }
        }
        // get existing tunnels (at DB level) and "flatize"
        $dbTunnelsRaw  = self::readBotBasicTunnels($srcBbcId);
        $dbTunnelsFlat = [];
        foreach ($dbTunnelsRaw as $pair) { $dbTunnelsFlat[] = $pair[0] . "|" . $pair[1]; }   // resourceType, tgtBbcId
        // calcs for the DB operations
        $split    = function ($pairStr) { return explode('|', $pairStr); };
        $toDelete = array_map($split, array_diff($dbTunnelsFlat, $objTunnelsFlat));
        $toInsert = array_map($split, array_diff($objTunnelsFlat, $dbTunnelsFlat));
        // "delete"
        $wheres = [];
        foreach ($toDelete as $pair) {
            list ($resourceType, $tgtBbcId) = $pair;
            $wheres[] = "resource_type = $resourceType AND src_bbchannel_id = $srcBbcId AND tgt_bbchannel_id = $tgtBbcId AND deleted IS NULL";
        }
        if (count($wheres) > 0) {
            $where = implode(' OR ', $wheres);
            $sql = <<<END
                UPDATE bbtunnel
                   SET deleted = NOW()
                 WHERE $where;
END;
            $rowCount = self::exec($sql, false, false);
            if ($rowCount === false) { return null; }
        }
        // insert
        $valuess = [];
        foreach ($toInsert as $pair) {
            list ($resourceType, $tgtBbcId) = $pair;
            $valuess[] = "($resourceType, $srcBbcId, $tgtBbcId)";
        }
        if (count($valuess) > 0) {
            $values = implode(', ', $valuess);
            $sql = <<<END
                INSERT INTO bbtunnel (resource_type, src_bbchannel_id, tgt_bbchannel_id)
                VALUES $values;
END;
            $rowCount = self::exec($sql, false, false);
            if ($rowCount === false) { return null; }
        }
        // ready
        return true;
    }



    //////////////////
    // BOTBASICRUNTIME
    //////////////////



    /**
     * Lee de la BD el ID de un BotBasicRuntime a partir de uno de sus ChatMediumChannel asociados (indirectamente a través de BotBasicChannel)
     *
     * @param  ChatMediumChannel    $cmChannel      Instancia de ChatMediumChannel
     * @return bool|null|int                        null en caso de error de SQL; false si no hay coincidencias; el ID del runtime en caso de éxito
     */
    static public function readBBruntimeIdByCMC ($cmChannel)
    {
        self::connect();
        $cmType    = $cmChannel->getCMtype();
        $cmUserId  = self::q($cmChannel->getCMuserId());
        $cmBotName = self::q($cmChannel->getCMbotName());
        $sql = <<<END
            SELECT rt.id AS id 
              FROM runtime AS rt 
              JOIN bbchannel AS bbc ON rt.id = bbc.runtime_id
              JOIN cmchannel AS cmc ON bbc.id = cmc.bbchannel_id
             WHERE cmc.cm_type = $cmType
               AND cmc.cm_user_id = $cmUserId
               AND cmc.cm_bot_name = $cmBotName
               AND rt.deleted IS NULL
               AND bbc.deleted IS NULL
               AND cmc.deleted IS NULL
             ORDER BY rt.updated DESC
             LIMIT 1;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        return $rows[0]['id'];
    }



    /**
     * Lee de la BD el ID de un BotBasicRuntime a partir del ID de un BizModel user que esté asociado dentro de la misma tabla "runtime"
     *
     * @param  int          $bbcodeCMid     ID del bot; una de las claves de ChatMedium::$bbBots
     * @param  int          $bmUserId       ID del BizModel user
     * @return bool|null                    null en caso de error de SQL; false si no hay coincidencia; el ID del runtime en caso de éxito
     */
    static public function readBBruntimeIdByBizModelUserId ($bbcodeCMid, $bmUserId)
    {
        self::connect();
        $sql = <<<END
            SELECT id 
              FROM runtime
             WHERE bbcode_cmid = $bbcodeCMid
               AND bizmodel_user_id = $bmUserId
               AND deleted IS NULL
             ORDER BY updated DESC
             LIMIT 1;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        return $rows[0]['id'];
    }



    /**
     * Lee de la BD un registro asociado a un BotBasicRuntime (runtime) a partir de su ID
     *
     * @param  int              $runtimeId      ID del runtime
     * @return array|bool|null                  null en caso de error de SQL; false si no hay coincidencia;
     *                                          [ bbcodeCMid, majorVersionNumber, minorVersionNumber, locale, word, boolTrace, bmUserId ] en caso de éxito
     */
    static public function readBBruntime ($runtimeId)
    {
        self::connect();
        $sql = <<<END
            SELECT bbcode_cmid, code_major_version, code_minor_version, code_minor_version, code_subminor_version, locale, word, trace, bizmodel_user_id 
              FROM runtime
             WHERE id = $runtimeId
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        list ($bbcodeCMid, $major, $minor, $subminor, $locale, $word, $trace, $bmUserId) = array_values($rows[0]);
        return [ $bbcodeCMid, $major, $minor, $subminor, $locale, $word, $trace == 1, $bmUserId ];
    }



    /**
     * Escribe la información de una instancia de BotBasicRuntime en la BD
     *
     * @param  BotBasicRuntime      $bbRuntime      Instancia
     * @return bool|null|int                        null si hay error de SQL; false si el ID no existe en la BD; true en caso de éxito con update, el nuevo ID en caso de éxito con insert
     */
    static public function writeBBruntime ($bbRuntime)
    {
        self::connect();
        if ($bbRuntime === null) { return null; }
        $id                    = $bbRuntime->getId();
        $bbCodeId              = $bbRuntime->getBBbotIdx();
        $bbCodeMajorVersion    = $bbRuntime->getBBcodeMajorVersion();
        $bbCodeMinorVersion    = $bbRuntime->getBBcodeMinorVersion();
        $bbCodeSubminorVersion = $bbRuntime->getBBcodeSubminorVersion();
        $locale                = $bbRuntime->getLocale();
        $word                  = self::q($bbRuntime->getWord());
        $trace                 = $bbRuntime->getTrace() ? 1 : 0;
        $bmUserId              = self::q($bbRuntime->getBMuserId(), true);
        // new runtime; insert
        if ($id === null || $id === -1) {
            $additionalColumn = $bbRuntime->isDeleted() ? ", deleted" : '';
            $additionalValue  = $bbRuntime->isDeleted() ? ", NOW()"   : '';
            $sql = <<<END
                INSERT INTO runtime (bbcode_cmid, code_major_version, code_minor_version, code_subminor_version, locale, word, trace, bizmodel_user_id $additionalColumn)
                VALUES ($bbCodeId, '$bbCodeMajorVersion', '$bbCodeMinorVersion', '$bbCodeSubminorVersion', '$locale', $word, $trace, $bmUserId $additionalValue);
END;
            $id = self::exec($sql, true);
            if ($id === false) { return null; }
            return $id;
        }
        // existing resource; update
        else {
            $additionalSet = $bbRuntime->isDeleted() ? ", deleted = NOW()" : '';
            $sql = <<<END
                UPDATE runtime
                   SET bbcode_cmid = $bbCodeId, code_major_version = '$bbCodeMajorVersion', code_minor_version = '$bbCodeMinorVersion', code_subminor_version = '$bbCodeSubminorVersion',
                       locale = '$locale', word = $word, trace = $trace, bizmodel_user_id = $bmUserId $additionalSet
                 WHERE id = $id
                   AND deleted IS NULL; 
END;
            $rowCount = self::exec($sql);
            if     ($rowCount === false) { return null;  }
            elseif ($rowCount == 0)      { return false; }
            return true;
        }
    }



    /**
     * Borra (soft-delete) de BD los registros correspondientes a una instancia de BotBasicRuntime y sus relacionados
     * BotBasicChannel y ChatMediumChannel
     *
     * @param  int          $bbRuntimeId        ID de la instancia
     * @return bool|null|int                    null si hay error de SQL; false si el ID no existe en la BD; true en caso de éxito con update, el nuevo ID en caso de éxito con insert
     */
    static public function nullifyRuntimeEtAl ($bbRuntimeId)
    {
        self::connect();
        // mark associated CMC's as deleted
        $sql = <<<END
            UPDATE cmchannel
               SET deleted = NOW()
             WHERE bbchannel_id IN (SELECT id FROM bbchannel WHERE runtime_id = $bbRuntimeId AND deleted IS NULL)
               AND deleted IS NULL;
END;
        if (self::exec($sql) === false) { return null; }
        // mark associated BBC's as deleted
        $sql = <<<END
            UPDATE bbchannel
               SET deleted = NOW()
             WHERE runtime_id = $bbRuntimeId
               AND deleted IS NULL;
END;
        if (self::exec($sql) === false) { return null; }
        // mark RT as deleted
        $sql = <<<END
            UPDATE runtime
               SET deleted = NOW()
             WHERE id = $bbRuntimeId
               AND deleted IS NULL;
END;
        if (self::exec($sql) === false) { return null; }
        // ready
        return true;
    }



    /////////
    // BBVARS
    /////////



    /**
     * Lee las variables de un programa BotBasic asociadas a una instancia de BotBasicRuntime o de BotBasicChannel
     *
     * Ambos argumentos son mutuamente excluyentes, y al menos uno de ellos debe ser un objeto de la instancia apropiada.
     *
     * @param  BotBasicRuntime  $bbRuntime      Instancia del runtime, o null para especificar instancia de BotBasicChannel
     * @param  BotBasicChannel  $bbChannel      Instancia de BotBasicChannel, o null para especificar instancia del runtime
     * @return array|null                       null en caso de error de SQL; [ [ varName, varValue ], ... ] en caso de éxito
     */
    static public function readVars ($bbRuntime, $bbChannel)
    {
        self::connect();
        $rtIdPart  = $bbRuntime !== null ? '= ' . $bbRuntime->getId() : 'IS NULL';
        $bbcIdPart = $bbChannel !== null ? '= ' . $bbChannel->getId() : 'IS NULL';
        $sql = <<<END
            SELECT name, value 
              FROM bbvars
             WHERE bbruntime_id $rtIdPart
               AND bbchannel_id $bbcIdPart
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if ($rows === false) { return null; }
        $res = [];
        foreach ($rows as $row) { $res[] = array_values($row); }
        return $res;
    }



    /**
     * Escribe el nuevo valor de una variable (o la borra) asociada a una instancia de BotBasicRuntime o de BotBasicChannel
     *
     * Los dos primeros argumentos son mutuamente excluyentes, y al menos uno de ellos debe ser un objeto de la instancia apropiada.
     *
     * @param  BotBasicRuntime  $bbRuntime      Instancia del runtime, o null para especificar instancia de BotBasicChannel
     * @param  BotBasicChannel  $bbChannel      Instancia de BotBasicChannel, o null para especificar instancia del runtime
     * @param  string           $name           Nombre de la variable
     * @param  string|null      $value          Valor de la variable, o null para borrarla
     * @return null|bool                        null en caso de error de SQL; true en caso de éxito
     */
    static public function updateVar ($bbRuntime, $bbChannel, $name, $value)   // pass NULL to value in order to delete the var
    {
        self::connect();
        $bbrtId    = $bbRuntime === null ? "NULL" : $bbRuntime->getId();
        $bbchId    = $bbChannel === null ? "NULL" : $bbChannel->getId();
        $bbrtWhere = $bbRuntime === null ? "bbruntime_id IS NULL" : "bbruntime_id = $bbrtId";
        $bbchWhere = $bbChannel === null ? "bbchannel_id IS NULL" : "bbchannel_id = $bbchId";
        if ($value === null) {   // delete an entry
            $sql = <<<END
                UPDATE bbvars
                   SET deleted = NOW()
                 WHERE $bbrtWhere
                   AND $bbchWhere
                   AND name = '$name'
                   AND deleted IS NULL;
END;
        }
        else {
            $sql = <<<END
                SELECT COUNT(*) AS amount
                  FROM bbvars
                 WHERE $bbrtWhere
                   AND $bbchWhere
                   AND name = '$name'
                   AND deleted IS NULL;
END;
            $rows = self::query($sql);
            if ($rows === false) { return null; }
            if ($rows[0]['amount'] != 0) {   // update an entry
                $value = self::q($value);
                $sql = <<<END
                    UPDATE bbvars
                       SET value = $value
                     WHERE $bbrtWhere
                       AND $bbchWhere
                       AND name = '$name'
                       AND deleted IS NULL;
END;
            }
            else {   // insert an entry
                $sql = <<<END
                    INSERT INTO bbvars (bbruntime_id, bbchannel_id, name, value)
                    VALUES ($bbrtId, $bbchId, '$name', '$value');
END;
            }
        }
        $rowCount = self::exec($sql);
        if ($rowCount === false) { return null; }
        return true;
    }



    /**
     * Escribe el nuevo valor de las variables (o las borra) asociadas a una instancia de BotBasicRuntime o de BotBasicChannel
     *
     * Los dos primeros argumentos son mutuamente excluyentes, y al menos uno de ellos debe ser un objeto de la instancia apropiada.
     *
     * @param  BotBasicRuntime  $bbRuntime      Instancia del runtime, o null para especificar instancia de BotBasicChannel
     * @param  BotBasicChannel  $bbChannel      Instancia de BotBasicChannel, o null para especificar instancia del runtime
     * @param  array            $pairs          [ [ $name1, $value1 ], ... ]; pasar null como $value para borrar una variable
     * @return null|bool                        null en caso de error de SQL; true en caso de éxito
     */
    static public function updateVars ($bbRuntime, $bbChannel, $pairs)   // pass NULL to $value in $pair==($name,$value) in order to delete the var
    {
        $quoteElems = function ($array) {
            $res = [];
            foreach ($array as $elem) { $res[] = "'$elem'"; }
            return $res;
        };

        self::connect();
        $bbrtId    = $bbRuntime === null ? "NULL" : $bbRuntime->getId();
        $bbchId    = $bbChannel === null ? "NULL" : $bbChannel->getId();
        $bbrtWhere = $bbRuntime === null ? "bbruntime_id IS NULL" : "bbruntime_id = $bbrtId";
        $bbchWhere = $bbChannel === null ? "bbchannel_id IS NULL" : "bbchannel_id = $bbchId";
        $toUpdate = $toDelete = $toCheck = $toUpdateByUpdate = $toUpdateByInsert = [];
        // classify between update and delete
        foreach ($pairs as $pair) {
            list ($name, $value) = $pair;
            if ($value === null) { $toDelete[] = $name;                           }
            else                 { $toUpdate[$name] = $value; $toCheck[] = $name; }
        }
        // classify toUpdate vars between sql insert and update
        if (count($toCheck) > 0) {
            $names = implode(', ', $quoteElems($toCheck));
            $sql = <<<END
                SELECT name
                  FROM bbvars
                 WHERE $bbrtWhere
                   AND $bbchWhere
                   AND name IN ($names)
                   AND deleted IS NULL;
END;
            $rows = self::query($sql);
            if ($rows === false) { return null; }
            foreach ($rows as $row) {
                $toUpdateByUpdate[] = $row['name'];
            }
            $toUpdateByInsert = array_diff($toCheck, $toUpdateByUpdate);
        }
        // "delete"s
        if (count($toDelete) > 0) {
            $names = implode(', ', $quoteElems($toDelete));
            $sql = <<<END
                UPDATE bbvars
                   SET deleted = NOW()
                 WHERE $bbrtWhere
                   AND $bbchWhere
                   AND name IN ($names)
                   AND deleted IS NULL;
END;
            $rowCount = self::exec($sql, false, false);
            if ($rowCount === false) { return null; }
        }
        // inserts
        if (count($toUpdateByInsert) > 0) {
            $valuess = [];
            foreach ($toUpdateByInsert as $name) {
                $value = $toUpdate[$name];
                $value = self::q($value);
                $valuess[] = "($bbrtId, $bbchId, '$name', $value)";
            }
            $values = implode(', ', $valuess);
            $sql = <<<END
                INSERT INTO bbvars (bbruntime_id, bbchannel_id, name, value)
                VALUES $values;
END;
            $rowCount = self::exec($sql, false, false);
            if ($rowCount === false) { return null; }
        }
        // updates
        foreach ($toUpdateByUpdate as $name) {
            $value = $toUpdate[$name];
            $value = self::q($value);
            $sql = <<<END
                UPDATE bbvars
                   SET value = $value
                 WHERE $bbrtWhere
                   AND $bbchWhere
                   AND name = '$name'
                   AND deleted IS NULL;
END;
            $rowCount = self::exec($sql, false, false);
            if ($rowCount === false) { return null; }
        }
        // ready
        return true;
    }



    /////////////
    // DATAHELPER
    /////////////



    /**
     * Lee una entrada de datos del dataHelper de BotBasic
     *
     * Las entradas del dataHelper siemrpe están asociadas a un BizModel user específico.
     *
     * @param  int              $bbcodeCMid     ID del bot; una de las claves de ChatMedium::$bbBots
     * @param  int              $bmUserId       ID del BizModel user
     * @param  string           $key            nombre (clave) de la entrada
     * @return bool|mixed|null                  null en caso de error de SQL; false si no hay coincidencia; el valor de la entrada en caso de éxito
     */
    static public function readDataHelperData ($bbcodeCMid, $bmUserId, $key)
    {
        self::connect();
        $key = self::q($key);
        $sql = <<<END
            SELECT value
              FROM datahelper_data
             WHERE bbcode_cmid = $bbcodeCMid
               AND bmuser_id = $bmUserId
               AND name = $key;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        return unserialize($rows[0]['value']);
    }



    /**
     * Escribe una entrada de datos del dataHelper de BotBasic
     *
     * Las entradas del dataHelper siemrpe están asociadas a un BizModel user específico.
     *
     * Nota: esta rutina no está en uso; ahora se usa la optimizada writeAllDataHelperData().
     *
     * @param  int              $bbcodeCMid     ID del bot; una de las claves de ChatMedium::$bbBots
     * @param  int              $bmUserId       ID del BizModel user
     * @param  string           $key            nombre (clave) de la entrada
     * @param  mixed            $value          valor de la entrada
     * @return bool|null                        null en caso de error de SQL; true en caso de éxito
     */
    static public function writeDataHelperData ($bbcodeCMid, $bmUserId, $key, $value)
    {
        self::connect();
        $key   = self::q($key);
        $value = self::q(serialize($value));
        $sql = <<<END
            REPLACE INTO datahelper_data (bbcode_cmid, bmuser_id, name, value)
            VALUES ($bbcodeCMid, $bmUserId, $key, $value);
END;
        $res = self::exec($sql);
        if ($res === false) { return null; }
        return true;
    }



    /**
     * Escribe todas las entradas de datos del dataHelper de BotBasic
     *
     * Las entradas del dataHelper siemrpe están asociadas a un BizModel user específico.
     *
     * @param  int              $bbcodeCMid     ID del bot; una de las claves de ChatMedium::$bbBots
     * @param  int              $bmUserId       ID del BizModel user
     * @param  array[]          $pairs          Valores a insertar / reemplazar, en forma: [ [ nombre, valor ], ... ]
     * @return bool|null                        null en caso de error de SQL; true en caso de éxito
     */
    static public function writeAllDataHelperData ($bbcodeCMid, $bmUserId, $pairs)
    {
        self::connect();
        if (count($pairs) == 0) { return true; }
        $valuess = [];
        foreach ($pairs as $pair) {
            list ($key, $value) = $pair;
            $key       = self::q($key);
            $value     = self::q(serialize($value));
            $valuess[] = "($bbcodeCMid, $bmUserId, $key, $value)";
        }
        $values = implode(', ', $valuess);
        $sql = <<<END
            REPLACE INTO datahelper_data (bbcode_cmid, bmuser_id, name, value)
            VALUES $values;
END;
        $res = self::exec($sql, false, false);
        if ($res === false) { return null; }
        return true;
    }



    ///////////////
    // INTERACTIONS
    ///////////////



    /**
     * Lee de la BD un registro asociado a un Interaction a partir de su ID
     *
     * No devuelve el campo cm_bot_name.
     *
     * @param  int              $id     ID del Interaction
     * @return array|bool|null          null en caso de error de SQL; false si no hay coincidencias;
     *                                  para Updates:  [ type, cmType, cmSeqId, cmChatInfo, cmUserId, cmUserName, cmUserPhone, bbcId, bmUserId, text, menuhook ];
     *                                  para Spĺashes: [ type, subType, bbcId, bmUserId, text, options ]
     */
    static public function readInteraction ($id)
    {
        self::connect();
        $sql = <<<END
            SELECT type, subtype, cm_type, cm_sequence_id, cm_chat_info, cm_user_id, cm_user_name, cm_user_login, cm_user_language, cm_user_phone,
                   bbchannel_id, bizmodel_user_id, text, menu_hook, options,
              FROM interaction
             WHERE id = $id
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        list ($type, $subType, $cmType, $cmSeqId, $cmChatInfo, $cmUserId, $cmUserName, $cmUserLogin, $cmUserLang, $cmUserPhone, $bbcId, $bmUserId, $text, $menuhook, $options) = array_values($rows[0]);
        switch ($type) {
            case Interaction::TYPE_UPDATE :
                $res = [ $type, $cmType, $cmSeqId, $cmChatInfo, $cmUserId, $cmUserName, $cmUserLogin, $cmUserLang, $cmUserPhone, $bbcId, $bmUserId, $text, $menuhook ];
                break;
            case Interaction::TYPE_SPLASH :
                $res = [ $type, $subType, $bbcId, $bmUserId, $text, $options ];
                break;
            default :
                $res = false;
        }
        return $res;
    }



    /**
     * Escribe la información de una instancia de Interaction en la BD
     *
     * @param  Interaction|Update|Splash    $interaction    Instancia
     * @return bool|int|null                                null si hay error de SQL; false si el Interaction no existe previamente y su ID no es null; true en caso de éxito
     */
    static public function writeInteraction ($interaction)
    {
        self::connect();
        if ($interaction === null) { return null; }
        $sqlInsert = $sqlUpdate = null;
        $type      = $interaction->getType();
        switch ($type) {
            case Interaction::TYPE_UPDATE :
                $id          = $interaction->getId();
                $cmType      = $interaction->getCMtype();
                $cmBotName   = self::q($interaction->getCMbotName());
                $cmSeqId     = self::q($interaction->getCMseqId(), true);
                $cmChatInfo  = self::q($interaction->getCMchatInfo());
                $cmUserId    = self::q($interaction->getCMuserId());
                $cmUserName  = self::q($interaction->getCMuserName());
                $cmUserLogin = self::q($interaction->getCMuserLogin());
                $cmUserLang  = self::q($interaction->getCMuserLang());
                $cmUserPhone = self::q($interaction->getCMuserPhone());
                $text        = self::q($interaction->getText());
                $menuhook    = self::q($interaction->getMenuhook());
                $bbChannelId = self::q($interaction->getBBchannelId(), true);
                $bmUserId    = self::q($interaction->getBizModelUserId(), true);
                if ($id === null) {   // new Interaction
                    $sqlInsert = <<<END
                        INSERT INTO interaction (type, cm_type, cm_bot_name, cm_sequence_id, cm_chat_info, cm_user_id, cm_user_name, cm_user_phone, text, menu_hook, 
                                                 bbchannel_id, bizmodel_user_id, created, cm_user_login, cm_user_language)
                        VALUES ($type, $cmType, $cmBotName, $cmSeqId, $cmChatInfo, $cmUserId, $cmUserName, $cmUserPhone, $text, $menuhook, 
                                $bbChannelId, $bmUserId, NOW(), $cmUserLogin, $cmUserLang);
END;
                }
                else {   // existing Interaction
                    $sqlUpdate = <<<END
                        UPDATE interaction
                           SET type = $type, cm_type = $cmType, cm_bot_name = $cmBotName, cm_sequence_id = $cmSeqId, cm_chat_info = $cmChatInfo, 
                               cm_user_id = $cmUserId, cm_user_name = $cmUserName, cm_user_phone = $cmUserPhone, text = $text, menu_hook = $menuhook,
                               bbchannel_id = $bbChannelId, bizmodel_user_id = $bmUserId, cm_user_login = $cmUserLogin, cm_user_language = $cmUserLang
                         WHERE id = $id
                           AND deleted IS NULL;
END;
                }
                break;
            case Interaction::TYPE_SPLASH :
                $id          = $interaction->getId();
                $subType     = $interaction->getSubType();
                $text        = self::q($interaction->getText());
                $options     = self::q(serialize($interaction->getOptions()));
                $bbChannelId = self::q($interaction->getBBchannelId(), true);
                $bmUserId    = self::q($interaction->getBizModelUserId(), true);
                if ($id === null) {   // new Interaction
                    $sqlInsert = <<<END
                        INSERT INTO interaction (type, subtype, text, options, bbchannel_id, bizmodel_user_id, created)
                        VALUES ($type, $subType, $text, $options, $bbChannelId, $bmUserId, NOW());
END;
                }
                else {   // existing Interaction
                    $sqlUpdate = <<<END
                        UPDATE interaction
                           SET type = $type, subtype = $subType, text = $text, options = $options,
                               bbchannel_id = $bbChannelId, bizmodel_user_id = $bmUserId
                         WHERE id = $id
                           AND deleted IS NULL;
END;
                }
                break;
            default :
        }
        if ($sqlInsert !== null) {
            $id = self::exec($sqlInsert, true);
            if ($id === false) { return null; }
            return $id;
        }
        if ($sqlUpdate !== null) {
            $rowCount = self::exec($sqlUpdate);
            if     ($rowCount === false) { return null;  }
            elseif ($rowCount == 0)      { return false; }
            return true;
        }
        return null;
    }



    /**
     * Obtiene el último ID de un Update registrado en la tabla "interaction" a partir de la identificación provista por su chatInfo (proveniente
     * de la chatapp) y su nombre de bot de chatapp, para una chatapp específica
     *
     * @param  int          $chatMediumType         Una de las constantes ChatMedium::TYPE_...
     * @param  string       $chatMediumBotName      Nombre del bot de la chatapp
     * @param  string       $chatMediumChatInfo     ChatInfo proveniente de la chatapp
     * @param  int          $updateIdToExclude      Un ID del update que se debe excluir del resultado (con el que se está comparando la secuencia)
     * @return bool|null                            null en caso de error de SQL; false si no hay registros sobre los que inspeccionar; el sequenceId en caso de éxito
     */
    static public function getLastUpdateSequenceIdFor ($chatMediumType, $chatMediumBotName, $chatMediumChatInfo, $updateIdToExclude)
    {
        self::connect();
        $type               = Interaction::TYPE_UPDATE;
        $chatMediumBotName  = self::q($chatMediumBotName);
        $chatMediumChatInfo = self::q($chatMediumChatInfo);
        $sql = <<<END
            SELECT MAX(cm_sequence_id) AS maximum
              FROM interaction
             WHERE type = $type
               AND cm_type = $chatMediumType
               AND cm_bot_name = $chatMediumBotName
               AND cm_chat_info = $chatMediumChatInfo
               AND id != $updateIdToExclude
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if ($rows === false) { return null; }
        $max = $rows[0]['maximum'];
        return $max === null ? false : $max;
    }



    /**
     * Elimina un Interaction
     *
     * @param  int          $id     ID del Interaction
     * @return bool|null            true en caso de éxito; null si hay error de BD
     */
    static public function deleteInteraction ($id)
    {
        self::connect();
        $sql = <<<END
            UPDATE interaction
               SET deleted = NOW()
             WHERE id = $id;
END;
        $res = self::exec($sql);
        if ($res === false) { return null; }
        return true;
    }



    /**
     * Crea las "firmas" para una colección de opciones de menú en la BD, por medio de inserts en la tabla menuhook_signature, almacenando allí la data pasada en
     * el parámetro; devuelve los nuevos menuhooks indexados según los mismos índices del argumento
     *
     * @param  mixed[]      $menuhooksDatas     Arreglo contentivo de los datos de cada opción de menú, de la forma:
     *                                          [ mixed DataAserAlmacenadaEindexadaPorMedioDeLaFirmaOpcion1, ... ]
     * @return string[]|bool|null               null en caso de error de SQL; false si no hubo opciones que insertar en BD; menuhooks en caso de éxito
     */
    static public function registerMenuhooks ($menuhooksDatas)
    {
        self::connect();
        $menuhooks = $values = [];
        foreach ($menuhooksDatas as $idx => $menuhookData) {
            $menuhookData    = self::q(serialize($menuhookData));
            $menuhooks[$idx] = $signature = md5(rand());
            $values[]        = "($menuhookData, '$signature') ";
        }
        if (count($values) == 0) { return false; }
        $values = implode(', ', $values);
        $sql = <<<END
            INSERT INTO menuhook_signature (data, signature) 
            VALUES $values;
END;
        $firstId = self::exec($sql, true, false);
        if ($firstId === false) { return null; }
        $menuhooksIdxs = array_keys($menuhooks);
        for ($mhIdxsPos = 0, $mhId = $firstId; $mhIdxsPos < count($menuhooksIdxs); $mhIdxsPos++, $mhId++) {
            $menuhooks[ $menuhooksIdxs[$mhIdxsPos] ] = "$mhId|" . $menuhooks[ $menuhooksIdxs[$mhIdxsPos] ];
        }
        return $menuhooks;
    }



    /**
     * A partir de una firma con clave única (ambos argumentos) lee la data del menuhook tal como fue almacenada con createMenuhookSignature()
     *
     * @param  int              $menuhookId     ID del menuhook
     * @param  string           $signature      Firma de menuhook
     * @return bool|mixed|null                  null en caso de error de SQL; false si no hay coincidencia con la firma; la data almacenada prevuiamente en caso de éxito
     */
    static public function readMenuhookDataByIdAndSignature ($menuhookId, $signature)
    {
        self::connect();
        $menuhookId = self::q($menuhookId);
        $signature  = self::q($signature);
        $sql = <<<END
            SELECT data
              FROM menuhook_signature
             WHERE id = $menuhookId
               AND signature = $signature
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        $menuhookData = unserialize($rows[0]['data']);
        return $menuhookData;
    }



    ////////////
    // RESOURCES
    ////////////



    static public function readResource ($id)
    {
        self::connect();
        $sql = <<<END
            SELECT type, chatmedium_authinfo, chatmedium_type, file_id, filename, metainfo, download_state
              FROM resource
             WHERE id = $id
               AND deleted IS NULL;
END;
        $rows = self::query($sql);
        if     ($rows === false)   { return null;  }
        elseif (count($rows) == 0) { return false; }
        list ($type, $cmType, $cmAuthInfo, $fileId, $filename, $metainfo, $downloadState) = array_values($rows[0]);
        $metainfo = unserialize($metainfo);
        return [ $type, $cmType, $cmAuthInfo, $fileId, $filename, $metainfo, $downloadState ];
    }



    /**
     * Escribe la información de una instancia de InteractionResource en la BD
     *
     * En caso de resources clonados, se maneja una lógica especial de representación en la tabla ("resource") en la que los atributos
     * del resource están representados por el resource original (indirección a través del campo "cloned_from_id").
     *
     * @param  InteractionResource  $resource           Instancia
     * @param  int|null             $interactionId      Instancia/registro de Interaction al cual está asociado el resource, pudiendo ser null para no asociar a un Interaction
     * @param  bool                 $doDownload         Indicación dirigida a la cola de download de contenidos de resources sobre si efectuar la descarga o no
     * @return null|int                                 null si hay error de SQL; el ID del nuevo registro en caso de éxito
     */
    static public function writeResource ($resource, $interactionId = null, $doDownload = false)
    {
        self::connect();
        if ($resource === null) { return null; }
        $id            = $resource->id;
        $type          = $resource->type;
        $cmType        = $resource->cmType;
        $cmcAuthInfo   = self::q($resource->cmcAuthInfo);
        $fileId        = self::q($resource->fileId);
        $filename      = self::q($resource->filename);
        $metainfo      = self::q(serialize($resource->metainfo));
        $downloadState = $doDownload ? 'pending' : ($resource->downloadState === null || $resource->downloadState === 'avoided' ? 'avoided' : $resource->downloadState);
        // new resource; insert
        if ($id === null || $id === -1) {
            $additionalColum = $interactionId === null ? '' : ", interaction_id";
            $additionalValue = $interactionId === null ? '' : ", $interactionId";
            if ($resource->clonedFrom !== null) {
                $type       = InteractionResource::TYPE_CLONED;
                $clonedFrom = $resource->clonedFrom;
                $sql = <<<END
                    INSERT INTO resource (type, cloned_from_id, download_state $additionalColum)
                    VALUES ($type, $clonedFrom, 'nonapplicable' $additionalValue);
END;
            }
            else {
                $sql = <<<END
                    INSERT INTO resource (type, chatmedium_type, chatmedium_authinfo, file_id, filename, metainfo, download_state $additionalColum)
                    VALUES ($type, $cmType, $cmcAuthInfo, $fileId, $filename, $metainfo, '$downloadState' $additionalValue);
END;
            }
            $id = self::exec($sql, true);
            if ($id === false) { return null; }
            return $id;
        }
        // existing resource; update
        else {
            $additionalSet = $interactionId === null ? '' : ", interaction_id = $interactionId";
            if ($resource->clonedFrom !== null) {
                $type       = InteractionResource::TYPE_CLONED;
                $clonedFrom = $resource->clonedFrom;
                $sql = <<<END
                    UPDATE resource
                       SET type = $type, cloned_from_id = $clonedFrom, download_state = '$downloadState' $additionalSet
                     WHERE id = $id
                       AND deleted IS NULL;
END;
            }
            else {
                $sql = <<<END
                    UPDATE resource
                       SET type = $type, chatmedium_type = $cmType, chatmedium_authinfo = $cmcAuthInfo, file_id = $fileId, filename = $filename, 
                           metainfo = $metainfo, download_state = '$downloadState' $additionalSet 
                     WHERE id = $id
                       AND deleted IS NULL; 
END;
            }
            $rowCount = self::exec($sql);
            if     ($rowCount === false) { return null;  }
            elseif ($rowCount == 0)      { return false; }
            return true;
        }
    }



    /**
     * Retorna el nombre definitivo que tendrá el contenido de un resource (que se almacena como archivo y no como BLOB dentro de la BD)
     * cuando es creado a partir de un archivo propio de la BotBasic app (y no proveniente de un Update)
     *
     * Esta operación no es propiamente de BD pero se incluye acá por ser de storage.
     *
     * @param  string       $filename       Nombre del archivo original en el filesystem del servidor; el archivo debe existir y ser movible por www-data
     * @return string|null                  Nombre de la ubicación definitiva del archivo en el espacio del filesystem correspondiente a BotBasic;
     *                                      o null si hubo problemas moviendo o renombrando el archivo
     */
    static public function storeFile ($filename)
    {
        // FIXME a possible policy (NOTE: works as is here): copy file from $filename to a new, generic store for files for all chatmedia; then return the new filename (ver utilidad de la migración en el comentario arriba)
        return $filename;
    }



    ////////////////////////////
    // GENERIC DB ACCESS METHODS
    ////////////////////////////



    /**
     * Establece la conexión a la BD en forma de singleton
     *
     * Si la conexión ya está establecida por un connect() previo, será reusada.
     *
     * @param  bool     $disconnectFirst        Indica si se debe desconectar la BD antes de intentar la conexión
     * @return bool
     */
    static private function connect ($disconnectFirst = false)
    {
        if (! $disconnectFirst && self::$dbh !== null) { return true; }
        try {
            if (self::$dbh !== null) {
                self::disconnect();
            }
            self::$dbh = new PDO(BOTBASIC_DB_CONN_STR, BOTBASIC_DB_USER, BOTBASIC_DB_PASSWORD, [ PDO::ATTR_PERSISTENT => true ]);
            return true;
        }
        catch (PDOException $e) {
            Log::register(Log::TYPE_DATABASE, "No es posible conectarse a la BD de BotBasic", $e);
            return false;
        }
    }



    /**
     * Desconecta la BD
     */
    static private function disconnect ()
    {
        self::$dbh = null;
    }



    /**
     * Retorna un string con el mensaje del último error de BD, si hubiese; si no lo hay, retorna un string apropiado
     *
     * @return string
     */
    static private function dbError ()
    {
        if (self::$dbh === null) {
            return "DB_NOT_CONNECTED";
        }
        $res = "[SQLSTATE=" . (self::$dbh->errorCode() === null ? 'NULL' : self::$dbh->errorCode()) . '|' . self::$dbh->errorInfo()[2] . "]";
        return $res;
    }



    /**
     * Efectúa un select sobre la BD
     *
     * @param  string       $sql        Texto del select de SQL
     * @return array|bool               false en caso de error de SQL/BD; el arreglo de resultados (como en mysql_query_assoc()) en caso de éxito
     */
    static private function query ($sql)
    {
        if (self::$dbh === null) {
            Log::register(Log::TYPE_DATABASE, "query(): base de datos no conectada al intentar...\n$sql\n");
            return false;
        }
        $res = self::$dbh->query($sql);
        if ($res === false) {
            $error = self::dbError();
            Log::register(Log::TYPE_DATABASE, "Error de BD: $error; con...\n$sql\n");
            return false;
        }
        return $res->fetchAll(PDO::FETCH_ASSOC);
    }



    /**
     * Efectúa un insert, update, replace o delete sobre la BD
     *
     * Para evitar que en caso de escritura de un registro cuyos valores a escribir sean los mismos que los actuales, el valor de retorno de
     * número de filas afectadas sea cero (como para MySQL), se escribe un valor aleatorio en una columna "rand" que deben tener todas las tablas.
     *
     * @param  string           $sql                    Texto de la sentencia SQL
     * @param  bool             $returnLastInsertId     Si es true, se retorna el ID del insert/replace efectuado; si es false, la cantidad de filas afectadas
     * @param  bool             $generateRandMark       Indica si debe generar la columna 'rand' automáticamente; pasar false para inserts múltiples
     * @return bool|int|null                            false en caso de error de SQL/BD; o lo especificado arriba
     */
    static private function exec ($sql, $returnLastInsertId = false, $generateRandMark = true)
    {
        if (self::$dbh === null) {
            Log::register(Log::TYPE_DATABASE, "exec(): base de datos no conectada al intentar...\n$sql\n");
            return false;
        }
        if ($generateRandMark) {
            $rand      = substr(md5(rand()), 0, 8);
            $statement = strtoupper(explode(' ', trim($sql), 2)[0]);
            switch ($statement) {
                case 'INSERT'  :
                case 'REPLACE' :
                    $parts1 = explode('(', $sql, 2);
                    $parts2 = explode('VALUES (', $parts1[1], 2);
                    $sql    = $parts1[0] . "(rand, " . $parts2[0] . "VALUES ('$rand', " . $parts2[1];
                    break;
                case 'UPDATE'  :
                    $parts = explode('SET ', $sql, 2);
                    $sql   = $parts[0] . "SET rand = '$rand', " . $parts[1];
                    break;
                case 'DELETE'  :
                default        :
            }
        }
        $res = self::$dbh->exec($sql);
        if ($res === false) {
            $error = self::dbError();
            Log::register(Log::TYPE_DATABASE, "Error de BD: $error; con...\n$sql\n");
            return false;
        }
        if ($returnLastInsertId) { return intval(self::$dbh->lastInsertId()); }
        else                     { return $res;                               }   // affected rows count
    }



    /**
     * Futore work.
     */
    static public function beginTransaction ()
    {
        // not needed up to now
    }



    /**
     * Futore work.
     */
    static public function commit ()
    {
        // not needed up to now
    }



    /**
     * Futore work.
     */
    static public function rollback ()
    {
        // not needed up to now
    }



    /**
     * Quote: retorna el valor que se pasa como parámetro en forma sanitizada para una sentencia SQL
     *
     * Los resultados de serialize() deben pasar también por aquí.
     *
     * @param  string   $val        Valor original
     * @param  bool     $isNumeric  Si es true, no se aplicarán comillas sino que sólo se verificará por valores null
     * @return string               String sanitizado; o el mismo string si no hay conexión abierta a BD
     */
    static public function q ($val, $isNumeric = false)
    {
        if (self::$dbh === null) {
            Log::register(Log::TYPE_DATABASE, "base de datos no conectada al intentar Quote");
            return $val;
        }
        return $val === null ? 'NULL' : ($isNumeric ? $val : self::$dbh->quote($val));
    }



    /**
     * Genera un ID válido (y que se crea en tanto) en la BD para una instancia de una clase que implemente la interfaz Initializable
     *
     * @param  Initializable    $obj    Instancia
     * @return int                      Nuevo ID; los próximos write...() efectuarán un update (esa lógica se activará dentro de ellos)
     */
    static public function makeId ($obj)
    {
        // if ID is already assigned, do nothing and return it
        if ($obj->getId() !== null) { return $obj->getId(); }
        // build the query
        $sqlValues = $sqlColumns = [];
        list ($table, $values) = $obj->getDefauls();
        foreach ($values as $name => $value) {
            if     (is_numeric($value)) { $sqlVal = $value;                       }
            elseif ($value === 'NOW()') { $sqlVal = $value;                       }
            elseif (is_string($value))  { $sqlVal = self::q($value);              }
            elseif (is_bool($value))    { $sqlVal = $value === true ? 1 : 0;      }
            elseif ($value === null)    { $sqlVal = 'NULL';                       }
            else                        { $sqlVal = self::q(serialize($value)); }
            $sqlValues[]  = "$name = $sqlVal";
            $sqlColumns[] = $name;
        }
        $sqlValues  = implode(', ', $sqlValues);
        $sqlColumns = implode(', ', $sqlColumns    );
        $sql = <<<END
            INSERT INTO $table ($sqlColumns) VALUES ($sqlValues);
END;
        // execute the query
        $id = self::exec($sql, true);
        if ($id === false) { return null; }
        // return the new ID
        return $id;
    }



    //////////////////////////////////////////////
    // DAEMON ROUTINES
    // used when this file is invoked from the CLI
    //////////////////////////////////////////////



    /**
     * Registra una entrada de bitácora (usando la clase Log) pero evita registrar aquellas que sean muy inmediatas a la más recientemente registrada
     *
     * @param  int      $type               Una de las constantes Log::TYPE_..., como lo recibirá la clase Log
     * @param  string   $content            Mensaje de bitácora a registrar, como lo recibirá la clase Log
     * @param  int      $cmType             Una de las constantes ChatMedium::TYPE...; cada cola de envío de Splashes maneja tiempos independientes
     * @param  int      $minSecsToRelog     Número de segundos mínimos necesarios antes de la última entrada que deben transcurrir para poder re-registrar
     */
    private function conditionalLog ($type, $content, $cmType, $minSecsToRelog)
    {
        $lastLogTime = DBbroker::readLastlogtimeForDownloadDaemon($cmType);   // unix time
        if ($lastLogTime === null) {
            Log::register(Log::TYPE_DATABASE, "DBB1942");
        }
        $now = time();
        if ($minSecsToRelog === null || $now - $lastLogTime > $minSecsToRelog) {
            Log::register($type, $content);
            $res = DBbroker::writeCurrentLastlogtimeForDownloadDaemon($cmType);
            if ($res === null) {
                Log::register(Log::TYPE_DATABASE, "DBB1950");
            }
        }
    }



    /**
     * Inicia el proceso de desencolado de un InteractionResource con el fin de descargar el contenido del resource en forma de archivo
     *
     * @param  int              $cmType             Una de las constantes ChatMedium::TYPE_...
     * @param  int              $tryCount           El resource desencolado será uno cuyo try_count coincida con este valor
     * @param  int              $minSecsToRelog     Argumento para conditionalLog()
     * @return array|bool|null                      null en caso de error de SQL; false de no haber resources que deban ser descargados;
     *                                              [ id, type, fileId ] en caso de haberlos
     */
    private function unqueueStart ($cmType, $tryCount, $minSecsToRelog)
    {
        $record = DBbroker::readFirstInResourcesQueueAndMarkAsDownloading($cmType, $tryCount);
        if ($record === null) {
            $this->conditionalLog(Log::TYPE_DATABASE, "DBB1970 Falla unqueue start", $cmType, $minSecsToRelog);
            return null;
        }
        return $record;   // can be false if no message must be sent
    }



    /**
     * Finaliza con éxito el proceso de desencolado iniciado de un InteractionResource iniciado por unqueueStart()
     *
     * @param  int      $id                 ID del resource
     * @param  string   $filename           La ruta al archivo ya descargado, que será almacenada en el registro de la tabla
     * @param  int      $cmType             Una de las constantes ChatMedium::TYPE_...
     * @param  int      $minSecsToRelog     Argument para conditionalLog()
     */
    private function unqueueCommit ($id, $filename, $cmType, $minSecsToRelog)
    {
        $res = DBbroker::markAsDownloadedInResourcesQueue($id, $filename);
        if ($res === null) {
            $this->conditionalLog(Log::TYPE_DATABASE, "DBB1990 Falla unqueue commit", $cmType, $minSecsToRelog);
        }
    }



    /**
     * Finaliza sin éxito el proceso de desencolado de un InteractionResource inciado por unqueueStart()
     *
     * @param  int      $id                     ID del resource
     * @param  int      $cmType                 Una de las constantes ChatMedium::TYPE_...
     * @param  int      $maxDownloadAttempts    Límite máximo de intentos de envíos; al alcanzarlo se cambia el status del registro
     * @param  int      $minSecsToRelog         Argument para conditionalLog()
     */
    private function unqueueRollback ($id, $cmType, $maxDownloadAttempts, $minSecsToRelog)
    {
        $attempsCount = DBbroker::markAsNonDownloadedInResourcesQueue($id, $maxDownloadAttempts);
        if ($attempsCount === null) {
            $this->conditionalLog(Log::TYPE_DATABASE, "DBB2008 Falla unqueue rollback", $cmType, $minSecsToRelog);
        }   // db error
        elseif ($attempsCount === false) {
            $this->conditionalLog(Log::TYPE_DAEMON, "DBB2012 No se consigue el ID $id", $cmType, $minSecsToRelog);
        }
        elseif ($attempsCount < $maxDownloadAttempts) {
            $this->conditionalLog(Log::TYPE_DATABASE, "DBB2014 Se alcanzo el maximo numero de intentos para la descarga con ID $id", $cmType, $minSecsToRelog);
        }
    }



    /**
     * Rutina principal del demonio de descarga de contenidos de resources recibidos como Updates desde las chatapps
     *
     * Descarga, secuencialmente, un máximo de $howMany resources, con un algoritmo de priorización de la cola por el cual se favorecen
     * los resources con menos intentos previos de haber sido descargados.
     *
     * @param  int      $cmType                 Una de las constantes ChatMedium::TYPE_...
     * @param  int      $thread                 Número de de thread, de un total de...
     * @param  int      $threads                Número total de threads (procesos web) invocados en paralelo
     * @param  int      $howMany                Cuántos resources serán descargados; si es -1 la ejecución será de ciclo infinito
     * @param  int      $interDelayMsecs        Tiempo para el usleep() que permite evitar el CPU flooding
     * @param  int      $maxDownloadAttempts    Número máximo de intentos de descarga para cada resource
     * @param  int      $minSecsToRelog         Cantidad mínima de segundos entre dos entradas consecutivas de bitácora
     */
    public function attemptToDownload ($cmType, $thread, $threads, $howMany, $interDelayMsecs, $maxDownloadAttempts, $minSecsToRelog)
    {
        $logTimestamps = false;   // set to true for tuning

        $now = function ($secsPrecision = 6) { return date('H:i:s.'.substr(microtime(), 2, $secsPrecision)); };   // usage: list (, , $secs) = explode(':', $now());   // $secs comes with microsecs
        $sleepUntilNextIteration = function ($startMin, $interDelaySecs)
        {
            $endOfCheckTS = microtime(true);
            $willEndAtMin = date('i', $endOfCheckTS + $interDelaySecs);
            if ($willEndAtMin != $startMin) { return false; }
            usleep(1e6 * $interDelaySecs);
            return true;
        };

        $startMin = date('i');
        $count = 0;
        while (true) {
            for ($tryCount = 0; $tryCount < $maxDownloadAttempts; $tryCount++) {
                $record = $this->unqueueStart($cmType, $tryCount, $minSecsToRelog);
                if ($record === null)  { return; }
                if ($record === false) { $nextInterDelayMsecs = max($interDelayMsecs, BOTBASIC_DOWNLOADDAEMON_TELEGRAM_INTERDELAY_MSECS); }
                else {
                    $nextInterDelayMsecs = $interDelayMsecs;
                    list ($id, $type, $metainfo, $cmAuthInfo, $fileId) = $record;
                    $cm  = ChatMedium::create($cmType);
                    $url = $cm->getDownloadUrl($cmAuthInfo, $fileId);
                    if     ($url === null)  {}   // this ChatMedium doesn't allow to download MM content OR error in SQL when getting the cmBotName based on cmType (including no row for the ID)
                    elseif ($url === false) {
                        $this->conditionalLog(Log::TYPE_DAEMON, "DBB2044 Falla el download", $cmType, $minSecsToRelog);
                        $this->unqueueRollback($id, $cmType, $maxDownloadAttempts, $minSecsToRelog);
                    }
                    else {
                        $filename = $this->filenameForResource($id, $type);
                        if ($filename === null) {
                            $this->conditionalLog(Log::TYPE_DAEMON, "DBB2516 No se puede crear un nombre de archivo local para el Resource", $cmType, $minSecsToRelog);
                            $res = null;
                        }
                        else {
                            $res = $this->downloadFile($url, $filename);
                        }
                        if ($res === null) {
                            $this->unqueueRollback($id, $cmType, $tryCount, $minSecsToRelog);
                        }
                        else {
                            $newFilename = $this->postProcessDownload($filename, $type, $metainfo);
                            if ($newFilename === null) { $this->unqueueRollback($id, $cmType, $maxDownloadAttempts, $minSecsToRelog); }
                            else                       { $this->unqueueCommit($id, $newFilename, $cmType, $minSecsToRelog); $count++; }
                        }
                    }
                }
                if ($howMany != -1 && $count >= $howMany)                                       { break 2; }
                if ($sleepUntilNextIteration($startMin, $nextInterDelayMsecs / 1000) === false) { break 2; }
            }
        }

        if ($logTimestamps) { Log::register(Log::TYPE_DAEMON, "DBB Finaliza thread $thread/$threads en " . $now(6) . ' // elapsed = ' . (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"])); }
    }



    /**
     * Genera un nombre de archivo sobre un directorio existente (se creará si no existe) para el almacenamiento del contenido de un resource
     *
     * El directorio señalado por BOTBASIC_DOWNLOADDAEMON_DOWNLOADS_DIR debe ser escribible para www-data.
     *
     * @param  int              $id         ID del resource en BD
     * @param  int              $type       Una de las constantes InteracyionResource::TYPE_...
     * @return null|string                  null en caso de error de creación de directorio; la ruta completa al archivo en caso de éxito
     */
    private function filenameForResource ($id, $type)
    {
        $basedir  = BOTBASIC_DOWNLOADDAEMON_DOWNLOADS_DIR;
        $typedir  = InteractionResource::typeString($type);
        $datedir  = date("Ym");
        $dir      = "$basedir/$typedir/$datedir";
        $filename = "$dir/$id";
        if (! is_dir($dir)) {
            $res = mkdir($dir, 0775, true);
            if ($res === false) { return null; }
        }
        return $filename;
    }



    /**
     * Descarga un archivo desde un URL
     *
     * Utiliza un mecanismo eficiente en memoria para la descarga del archivo según inputStream().
     *
     * @param  string       $url            URL desde el cual descargar
     * @param  string       $filename       Nombre del archivo destino
     * @return bool|null                    null en caso de error de descarga o almacenamiento local; true en caso de éxito
     */
    private function downloadFile ($url, $filename)
    {
        $inputStream  = $this->inputStream($url);
        $outputStream = fopen($filename, "w");
        $res = stream_copy_to_stream($inputStream, $outputStream);
        fclose($inputStream);
        fclose($outputStream);
        return $res === false ? null : true;
    }



    /**
     * Genera un input stream para la lectura del contenido de un URL, utilizando memoria secundaria para la descarga
     *
     * @param  string       $url        URL para el cual se crea el stream
     * @return resource                 input stream equivalente al retornado por un fopen()
     */
    private function inputStream ($url)
    {
        // adapted from WebRouterTelegram
        $rawInput  = fopen($url, 'r');
        $tmpStream = fopen('php://temp', 'r+');
        stream_copy_to_stream($rawInput, $tmpStream);
        rewind($tmpStream);
        return $tmpStream;
    }



    /**
     * Una vez efectuada la descarga de un archivo, efectúa un posprocesamiento por el cual a los archivos de video les son desechados sus
     * componentes de video con resultantes en forma de archivos MP3
     *
     * Puede incluirse aquí cualquier tipo de posprocesamiento para resources de diferentes tipos.
     *
     * @param  string   $filename       Nombre del archivo a procesar
     * @param  string   $type           Uno de InteractionRecource::TYPE_*; argumento no usado por el momento; el formato del archivo se determina por su tipo MIME
     * @param  array    $metainfo       Metainformacion sobre el archivo
     * @return string                   Nombre del nuevo archivo; o el nombre del archivo original si la transformación falló
     */
    private function postProcessDownload ($filename, $type, $metainfo)   // type is not used yet
    {
        $this->doDummy($type);
        $proc = null;
        switch ($type = mime_content_type($filename)) {
            case 'video/mpeg' : if ($proc !== null) { $proc = [ 'mpeg2mp3 -silent -delete', 'mp3' ]; } break;
            case 'video/avi'  : if ($proc !== null) { $proc = [ 'avi2mp3  -silent -delete', 'mp3' ]; } break;
        }
        $newFilename = strrpos($filename, '.') === false && isset($metainfo['format']) ? $filename . '.' . $metainfo['format'] : $filename;
        if ($proc === null && $filename != $newFilename) { rename($filename, $newFilename); }
        elseif ($proc !== null) {
            $oldFilename = $filename;
            $pos = strrpos($oldFilename, ".");
            if ($pos !== false) { $filename = substr($oldFilename, 0, $pos) . "." . $proc[1]; }
            else                { $filename = $oldFilename                  . "." . $proc[1]; }
            $output = [];
            $res    = -1;
            exec(BOTBASIC_DOWNLOADDAEMON_SCRIPTSDIR . "/" . $proc[0] . " $oldFilename $filename", $output, $res);
            if ($res == 0) { $newFilename = $filename; }
        }
        $shortenedFilename = strpos($newFilename, BOTBASIC_DOWNLOADDAEMON_DOWNLOADS_DIR) === 0 ? substr($newFilename, strlen(BOTBASIC_DOWNLOADDAEMON_DOWNLOADS_DIR) + 1) : $newFilename;
        return $shortenedFilename;
    }



    /**
     * IDE spoofing
     *
     * @param $arg
     */
    private function doDummy ($arg) {}



}
