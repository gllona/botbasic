<?php
/**
 * Medio de chat que implementa la comunicación con la chatapp Telegram
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace {
    /** @noinspection PhpIncludeInspection */
    require_once BOTBASIC_BASEDIR . '/httpdocs/unirest/src/Unirest.php';
}



namespace botbasic {

    use \Unirest\Request;
    use \Unirest\Request\Body;
    use \Unirest\Exception;



    /**
     * Clase ChatMediumTelegram
     *
     * Subclase de ChatMedium que implementa la comunicación con la chatapp Telegram, de la cual surgen las bases para el resto,
     * en términos del diseño inicial (BB v1.0) de la arquitectura de chatapps "pluggables".
     *
     * @package botbasic
     */
    class ChatMediumTelegram extends ChatMedium implements LogbotChatMedium
    {



        /** @var string Espacio Unicode destinado a garantizar ancho mínimo de splashes sorbe la chatapp (ver constructor) */
        static private $digitSpace = null;



        static public function cmUserIsLogbotUser ($bbBotIdx, $cmFullUserName)
        {
            $cmLogBots = BotConfig::cmLogBots(self::TYPE_TELEGRAM);
            if (! isset($cmLogBots[$bbBotIdx])) { return false; }
            foreach ($cmLogBots[$bbBotIdx] as $username) {
                if ($username == $cmFullUserName) { return true; }
            }
            return false;
        }



        /** @const Indica una orden hacia la chatapp de reseteo del status de un botón de menú */
        const ORDER_RESET_PRESSED_BUTTON = 101;



        static protected function cmBots ($cmAndBbBotsIdx = null)
        {
            $cmBots = BotConfig::cmBots(self::TYPE_TELEGRAM);
            if ($cmAndBbBotsIdx === null) {
                return $cmBots;
            }
            foreach ($cmBots as $idx => $data) {
                if ($idx == $cmAndBbBotsIdx) { return [ $idx => $data ]; }
            }
            return [];
        }



        public function getCMbotCredentialsByScriptName ($scriptName, $aCmBots = null)
        {
            $cmBots = BotConfig::cmBots(self::TYPE_TELEGRAM);
            if ($aCmBots === null) { $aCmBots = $cmBots; }
            if (($pos = strrpos($scriptName, '/')) !== false) { $scriptName = substr($scriptName, $pos + 1); }   // erase protocol://server:path/
            if (($pos = strpos( $scriptName, '?')) !== false) { $scriptName = substr($scriptName, 0, $pos);  }   // erase query string after the '?'
            foreach ($aCmBots as $idx => $cmBotsPerBBbot) {
                foreach ($cmBotsPerBBbot as $cmBotCredentials) {
                    if ($cmBotCredentials[1] == $scriptName) { return array_merge([ $idx ], $cmBotCredentials); }
                }
            }
            return null;
        }



        public function getCMbotCredentialsByBBinfo ($bbCodename, $bbMajorVersionNumber, $bbBotName, $aCmBots = null)
        {
            $cmBots = BotConfig::cmBots(self::TYPE_TELEGRAM);
            if ($aCmBots === null) { $aCmBots = $cmBots; }
            $idx = self::getBBbotIndexByBBinfo($bbCodename, $bbMajorVersionNumber, $bbBotName);
            return $aCmBots[$idx];   // indexes in ChatMedium:$bbBots[] and in self:$cmBots[] are the same
        }



        static public function getCMbotSpecialIndex ($cmBotName)
        {
            $cmBots = BotConfig::cmBots(self::TYPE_TELEGRAM);
            foreach ($cmBots as $j => $credentials) {
                for ($i = 0; $i < count($credentials); $i++) { if ($credentials[$i][0] == $cmBotName) { return [ $j, $i ]; } }
            }
            return null;
        }



        static public function getCMbotNameBySpecialIndex ($idx)
        {
            $cmBots = BotConfig::cmBots(self::TYPE_TELEGRAM);
            return $cmBots[$idx[0]][$idx[1]][0];
        }



        public function getAuthInfoForDownloadsByScriptName ($scriptName, $aCmBots = null)
        {
            $credentials = $this->getCMbotCredentialsByScriptName($scriptName, $aCmBots);
            if ($credentials === null) { return null; }
            return $credentials[1];
        }



        /**
         * ChatMediumTelegram constructor
         *
         * Genera estructuras de datos.
         */
        public function __construct ()
        {
            self::$digitSpace = json_decode('"\u2007"');
        }



        //////////////
        // ENTER PHASE
        //////////////



        public function setupIdeDebugging ($dressedUpdate, $botName, $bbCode) {
            $message = isset($dressedUpdate->callback_query) ? $dressedUpdate->callback_query :
                (isset($dressedUpdate->edited_message) ? $dressedUpdate->edited_message :
                (isset($dressedUpdate->message) ? $dressedUpdate->message : null));
            $username = $message === null ? null : trim($message->from->first_name . ' ' . $message->from->last_name);
            $GLOBALS['botbasic_ide_debug'] = isset(BotConfig::$ideDebugBots[$bbCode]) && in_array($username, BotConfig::$ideDebugBots[$bbCode]);
        }



        public function undressUpdate ($dressedUpdate, $botName, $cmAuthInfo, $textToPut = null, $userIdToPut = null)
        {
            $extract = function ($obj, $members) {
                $res = [];
                foreach (explode(',', $members) as $member) {
                    if     (strpos($member, '=') !== false) { list ($member, $value) = explode('=', $member); $res[$member] = $value; }
                    elseif (isset($obj->$member))           { $res[$member] = $obj->$member;                                          }
                }
                return $res;
            };
            $valid     = true;
            $resources = [];   /** @var InteractionResource[] $resources */
            $update    = $dressedUpdate;
            if (! is_object($update)) {
                Log::register(Log::TYPE_RUNTIME, "CMTG135 Se recibio un update vacio");
                return -1;   // do nothing after this action
            }
            $seqId = $update->update_id;
            $text = $menuhook = $fullname = $login = $language = $userphone = null;
            if     (isset($update->callback_query)) { $from = $update->callback_query->from; $chatId = null;                              }
            elseif (isset($update->message))        { $from = $update->message->from;        $chatId = $update->message->chat->id;        }
            elseif (isset($update->edited_message)) { $from = $update->edited_message->from; $chatId = $update->edited_message->chat->id; }
            else                                    { $from = null;                          $chatId = null;                              }
            $userid = $from === null ? null : $from->id;
            list ($botIdx, ) = self::getCMbotSpecialIndex($botName);
            // special case: botbasic is possessed! (use for test chatapps connections)
            if (BOTBASIC_BOT_IS_POSSESSED) {
                $msg    = BotConfig::botMessage($botIdx, $this->locale, BotConfig::MSG_BOT_IS_POSSESSED);
                $toPost = $this->dressForDisplay($msg, null, null, [$botName, $userid, $chatId]);
                $res    = $this->display($toPost);
                if ($res === false) {
                    Log::register(Log::TYPE_RUNTIME, "CMTG146 Falla display de mensaje generico (isPossessed)");
                }
                else {
                    Log::register(Log::TYPE_RUNTIME, "CMTG150 SE ACABA DE DEVOLVER UN UPDATE GENERICO POR ISPOSSESSED! input=\n" . json_encode($update));
                }
                return -1;   // do nothing with the update after these actions
            }
            // menuhooks
            elseif (isset($update->callback_query)) {
                $fullname        = trim($update->callback_query->from->first_name . (! isset($update->callback_query->from->last_name) ? '' : ' ' . $update->callback_query->from->last_name));
                $login           = isset($update->callback_query->from->username) ? trim($update->callback_query->from->username) : null;
                $language        = isset($update->callback_query->from->language_code) ? trim($update->callback_query->from->language_code) : null;
                $menuhook        = $update->callback_query->data;
                // enqueue a special order for resetting the appearance of the pressed button
                $callbackQueryId = $update->callback_query->id;
                $menuhook        = "$menuhook|$callbackQueryId";   // id|signature|callbackqueryid (callbackqueryid not more used after here)
                $res = DBbroker::writeToTelegramMessageQueue(null, null, null, -1, self::ORDER_RESET_PRESSED_BUTTON, [ $botName, $callbackQueryId ]);
                if ($res === null) {
                    Log::register(Log::TYPE_DATABASE, "CMTG176 Error de BD");
                }
            }
            // texts and resources
            elseif (isset($update->message)) {
                $fullname      = trim($update->message->from->first_name . (! isset($update->message->from->last_name) ? '' : ' ' . $update->message->from->last_name));
                $login         = isset($update->message->from->username) ? trim($update->message->from->username) : null;
                $language      = isset($update->message->from->language_code) ? trim($update->message->from->language_code) : null;
                $add2resources = function ($resource) use (&$resources) { if ($resource !== null) { $resources[] = $resource; } };
                $resource = null;
                if (isset($update->message->text)) { $text = $update->message->text; }
                if (isset($update->message->photo)) {
                    // choose the biggest image
                    $chosen = null; $chosenWidth = -1;
                    foreach ($update->message->photo as $photoSize) {
                        if ($chosen === null || $chosenWidth < $photoSize->width) { $chosen = $photoSize; $chosenWidth = $chosen->width; }
                    }
                    $add2resources(                                       InteractionResource::createFromFileId(InteractionResource::TYPE_IMAGE,      $this->type, $cmAuthInfo, $chosen->file_id,                      $extract($chosen,                      'width,height,format=jpg') ));
                }
                if (isset($update->message->audio))      { $add2resources(InteractionResource::createFromFileId( InteractionResource::TYPE_AUDIO,     $this->type, $cmAuthInfo, $update->message->audio->file_id,      $extract($update->message->audio,      'duration')                )); }
                if (isset($update->message->voice))      { $add2resources(InteractionResource::createFromFileId( InteractionResource::TYPE_VOICE,     $this->type, $cmAuthInfo, $update->message->voice->file_id,      $extract($update->message->voice,      'duration')                )); }
                if (isset($update->message->video))      { $add2resources(InteractionResource::createFromFileId( InteractionResource::TYPE_VIDEO,     $this->type, $cmAuthInfo, $update->message->video->file_id,      $extract($update->message->video,      'width,height,duration')   )); }
                if (isset($update->message->video_note)) { $add2resources(InteractionResource::createFromFileId( InteractionResource::TYPE_VIDEONOTE, $this->type, $cmAuthInfo, $update->message->video_note->file_id, $extract($update->message->video_note, 'length,duration')         )); }
                if (isset($update->message->document))   { $add2resources(InteractionResource::createFromFileId( InteractionResource::TYPE_DOCUMENT,  $this->type, $cmAuthInfo, $update->message->document->file_id                                                                      )); }
                if (isset($update->message->caption))    { $add2resources(InteractionResource::createFromContent(InteractionResource::TYPE_CAPTION,   $this->type, $update->message->caption ));                                                                                             }
                if (isset($update->message->location))   { $add2resources(InteractionResource::createFromContent(InteractionResource::TYPE_LOCATION,  $this->type, $update->message->location));                                                                                             }
            }
            // invalid updates
            elseif (isset($update->edited_message)) {
                $msg    = BotConfig::botMessage($botIdx, $this->locale, BotConfig::MSG_CANT_EDIT_PREVIUOS_UPDATES);
                $toPost = $this->dressForDisplay($msg, null, null, [$botName, $userid, $chatId]);
                $res    = $this->display($toPost);
                if ($res === false) {
                    Log::register(Log::TYPE_RUNTIME, "CMTG179 Falla display de que no se puede editar updates previos");
                }
                $valid = false;
            }
            // any other
            else {
                $msg    = BotConfig::botMessage($botIdx, $this->locale, BotConfig::MSG_CANT_DO_THAT);
                $toPost = $this->dressForDisplay($msg, null, null, [$botName, $userid, $chatId]);   // FIXME $userid will be null here; display won't work?
                $res    = $this->display($toPost);
                if ($res === false) {
                    Log::register(Log::TYPE_DEBUG, "CMTG188 Falla display de operacion no permitida");
                }
                $valid = false;
            }
            // make, save all in DB and return
            if (! $valid) { return null; }
            $u = Update::createByAttribs(ChatMedium::TYPE_TELEGRAM, $botName, $seqId, $chatId, $userid, $fullname, $login, $language, $userphone, $text, $menuhook);
            foreach ($resources as $resource) { $u->addResource($resource);             }
            foreach ($resources as $resource) { $valid &= $resource->save($u) !== null; }
            if (! $valid) { return null; }
            return $u;
        }



        public function getDownloadUrl ($cmAuthInfo, $fileId)
        {
            // obtain the bot name
            $cmBotName = $cmAuthInfo;
            if (! is_string($cmBotName)) { return null; }
            // obtain the bot token
            $cmBotToken = $this->getCMbotToken($cmBotName);
            if ($cmBotToken === null) {
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG211 No se puede obtener el bot token para $cmBotName");
                return false;
            }
            // assemble the request, post to Telegram and decode the answer
            $request = [ $cmBotName, json_decode('{ "file_id" : "' . $fileId . '" }'), "getFile" ];
            $info = $this->postToTelegram($request);
            if (! isset($info->file_path)) { return false; }
            else                           { return "https://api.telegram.org/file/bot" . $cmBotToken . "/" . $info->file_path; }
        }



        /////////////
        // EXIT PHASE
        /////////////



        public function dressForDisplay ($text, $menuOptions, $resource, $cmChannelOrCmChatInfo)
        {
            // if this is a menu, should guarantee that menu title has a minimum width so menu options' texts don't get cut
            if ($text !== null && $menuOptions !== null) {
                $lines           = explode("\n", $text);
                $maxLineLength   = -1;
                $lineOfMaxLength = null;
                foreach ($lines as &$line) {
                    $thisLen = $this->normalizedTextLength($line);
                    if ($thisLen > $maxLineLength)                               { $maxLineLength = $thisLen; $lineOfMaxLength =& $line; }
                    if ($maxLineLength >= BOTBASIC_TELEGRAM_MIN_MENUTITLE_CHARS) { break;                                                }
                }
                if ($maxLineLength < BOTBASIC_TELEGRAM_MIN_MENUTITLE_CHARS) {
                    $lineOfMaxLength .= str_repeat(self::$digitSpace, BOTBASIC_TELEGRAM_MIN_MENUTITLE_CHARS - $maxLineLength);
                }
                $text = implode("\n", $lines);
            }
            // pack and return
            $res = [ $text, $menuOptions, $resource, $cmChannelOrCmChatInfo ];
            return $res;
        }



        public function display ($infoToPost, $forceAsync = true)
        {
            $this->doDummy($forceAsync);
            list ($text, $menuOptions, $resource, $cmcOrChatInfo) = $infoToPost;   /** @var $cmcOrChatInfo ChatMediumChannel|array */
            // in normal cases the display will be done asynchronously
            if ($cmcOrChatInfo instanceof ChatMediumChannel || is_int($cmcOrChatInfo)) {
                $cmcId = is_int($cmcOrChatInfo) ? $cmcOrChatInfo : $cmcOrChatInfo->getId();
                $res = DBbroker::writeToTelegramMessageQueue($text, $menuOptions, $resource, $cmcId);
                if ($res === null) {
                    Log::register(Log::TYPE_DATABASE, "CMTG301 Error de BD");
                    return false;
                }
            }
            // if $cmcOrChatInfo is not a cmChannel, this display is an error message when entering and display will be done synchronously
            else {
                list ($cmBotName, , $cmChatId) = $cmcOrChatInfo;   // $cmUserId is unused because Telegram uses chat_id for binding
                $request = $this->makeContentForPost($text, null, null, $cmChatId, $cmBotName);
                if ($request === null) {
                    Log::register(Log::TYPE_GENERIC, "CMTG287 No se paso ni texto ni resource");
                }
                else {
                    $res = $this->postToTelegram($request);
                    if (! $res) {
                        Log::register(Log::TYPE_GENERIC, "CMTG292 Falla postToTelegram");
                        return false;
                    }
                }
            }
            // ready
            return true;
        }



        ////////////////////////////////////////////////
        // DAEMON ROUTINES
        // used for sending splashes to Telegram servers
        ////////////////////////////////////////////////



        /**
         * Registra una entrada de bitácora (usando la clase Log) pero evita registrar aquellas que sean muy inmediatas a la más recientemente registrada
         *
         * @param  int      $type       Una de las constantes Log::TYPE_...
         * @param  string   $content    Mensaje a registrar, como lo recibirá la clase Log
         */
        private function conditionalLog ($type, $content)
        {
            $lastLogTime = DBbroker::readLastlogtimeForMessageDaemon(ChatMedium::TYPE_TELEGRAM);   // unix time
            if ($lastLogTime === null) {
                Log::register(Log::TYPE_DATABASE, "CMTG285 Error de BD", $this);
            }
            $now = time();
            if ($now - $lastLogTime > BOTBASIC_SENDERDAEMON_TELEGRAM_MIN_SECS_TO_RELOG) {
                Log::register($type, $content);
                DBbroker::writeCurrentLastlogtimeForMessageDaemon(ChatMedium::TYPE_TELEGRAM);
                if ($lastLogTime === null) {
                    Log::register(Log::TYPE_DATABASE, "CMTG292 Error de BD");
                }
            }
        }



        /**
         * Inicia el desencolado de un Splash registrado en la cola de envío de Telegram, retornando su contenido y marcándolo como "enviando"
         *
         * Este método filtra por un "send attempts try count" especificado y garantiza que se retorna el más antiguo que esté pendiente por enviar
         * que tenga exactamente esa cantidad de intentos de envío. Esto permite implementar una cola de envíos que no se bloqueará por fallas en
         * envíos de un Splash específico atribuibles a causas situadas en los servidores de la chatapp.
         *
         * @param  int  $tryCount   Cantidad de intentos de envío por la que se desea filtrar
         * @return array|bool|null  null en caso de error de BD; false si no hay Splashes pendientes por enviar;
         *                          [ id, text, menuOptions, interactionResource, cmcId ] en caso de haberlos
         */
        private function unqueueStart ($tryCount)
        {
            $record = DBbroker::readFirstInTelegramMessageQueueAndMarkAsSending($tryCount);
            if ($record === null) {
                $this->conditionalLog(Log::TYPE_DATABASE, "CMTG314 Error de BD");
                return null;
            }
            return $record;   // can be false if no message must be sent
        }



        /**
         * Finaliza el desencolado de un Splash de la cola de Telegram
         *
         * Este método es invocado, a modo de commit, cuando el envío a los servidores de Telegram tiene éxito.
         *
         * @param  int          $id     ID del registro en la cola de envío
         */
        private function unqueueCommit ($id)
        {
            $res = DBbroker::markAsSentInTelegramMessageQueue($id);
            if ($res === null) {
                $this->conditionalLog(Log::TYPE_DATABASE, "CMTG334 Error de BD");
            }
        }



        /**
         * Finaliza (alternativamente) el desencolado de un Splash de la cola de Telegram
         *
         * Este método es invocado, a modo de rollback, cuando el envío a los servidores de Telegram no tiene éxito, e incrementa el
         * "try count" del registro, traspasando su status a "error" cuando se excede el límite máximo especificado.
         *
         * @param  int          $id             ID del registro en la cola de envío
         */
        private function unqueueRollback ($id)
        {
            $attempsCount = DBbroker::markAsUnsentInTelegramMessageQueue($id, BOTBASIC_SENDERDAEMON_TELEGRAM_MAX_SEND_ATTEMPTS);
            if ($attempsCount === null) {
                $this->conditionalLog(Log::TYPE_DATABASE, "CMTG352 Error de BD");
            }
            elseif ($attempsCount === false) {
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG355 Update $id no se encuentra ");
            }
            elseif ($attempsCount < BOTBASIC_SENDERDAEMON_TELEGRAM_MAX_SEND_ATTEMPTS) {
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG358 Se alcanzo el maximo numero de intentos de envio para el splash $id");
            }
        }



        /**
         * Rutina principal del demonio de envío de splashes a los servidores de Telegram
         *
         * Envía splashes durante un minuto, a razón de uno cada BOTBASIC_SENDERDAEMON_TELEGRAM_ITERATION_MSECS milisegundos. Cuando el tiempo de
         * acceso al web service de Telegram excede esta medida, se espera hasta el siguiente "tic" que coincida con el paso de la medida. Esto
         * garantiza que el sistema envíe, entre todos los threads, las peticiones a Telegram de manera igualmente espaciada.
         *
         * @param  int      $thread         Número de de thread, de un total de...
         * @param  int      $threads        Número total de threads (procesos web) invocados en paralelo
         * @param  int      $howMany        Número máximo de Splashes a enviar; si es -1 no se usará este límite
         * @param  int      $requestMsecs   Duración de un "tick"; puede ser especificado como la duración máxima de un request hacia Telegram sumando
         *                                  una holgura que permita garantizar un tick fijo y adicionalmente un tiempo muerto para hacer confiar a los
         *                                  servidores de Telegram de que no se trata de un flood (~350 msecs es el tiempo real desde El Cangrejo)
         */
        public function attemptToSend ($thread, $threads, $howMany, $requestMsecs)
        {
            $logTimestamps = false;   // set to true for tuning

            $now = function ($secsPrecision = 6) { return date('H:i:s.'.substr(microtime(), 2, $secsPrecision)); };   // usage: list (, , $secs) = explode(':', $now());   // $secs comes with microsecs
            $sleepUntilNextTick = function ($startOfTickTS, $startMin, $iterationFixedSecs)
            {
                $endOfRequestTS = microtime(true);
                $secsElapsed    = $endOfRequestTS - $startOfTickTS;
                $toSleepSecs    = $iterationFixedSecs - $secsElapsed + (BOTBASIC_DEBUG ? BOTBASIC_SENDERDAEMON_TELEGRAM_WAIT_UNTIL_RETRY_SECS : 0);
                while ($toSleepSecs < 0) { $toSleepSecs += $iterationFixedSecs; }
                $willEndAtMin = date('i', $endOfRequestTS + $toSleepSecs - BOTBASIC_SENDERDAEMON_CRON_DELAY_SECS);
                if ($willEndAtMin != $startMin) { return false; }
                usleep(1e6 * $toSleepSecs);
                return true;
            };

            // wait until the right start time for this thread
            if ($logTimestamps) { Log::register(Log::TYPE_DAEMON, "CMTG419 Comienza thread $thread/$threads en " . $now(6) . ' // elapsed = ' . (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"])); }
            $startOfMinuteTS    = date_format(date_create(date('H:i')), 'U');
            $secsSinceSOM       = microtime(true) - $startOfMinuteTS;
            $toSleepSecs        = ($requestMsecs / 1000) * ($thread / $threads); $this->doDummy($secsSinceSOM);   // - $secsSinceSOM;
            if ($toSleepSecs > 0) { usleep(1e6 * $toSleepSecs); }
            if ($logTimestamps) { Log::register(Log::TYPE_DAEMON, "CMTG425 Com/efec thread $thread/$threads en " . $now(6) . ' // elapsed = ' . (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"])); }

            // start send cycle
            $startMin = date('i');
            $count = 0;
            while (true) {
                $startOfRequestTS = microtime(true);
                $record           = false;
                for ($tryCount = 0; $tryCount < BOTBASIC_SENDERDAEMON_TELEGRAM_MAX_SEND_ATTEMPTS; $tryCount++) {
                    $record = $this->unqueueStart($tryCount);
                    if ($record === null) {
                        $this->conditionalLog(Log::TYPE_DATABASE, "CMTG434 Error de BD");
                        break 2;
                    }
                    if ($record !== false) {
                        break;
                    }
                }
                if ($record !== false) {
                    list ($id, $text, $menuOptions, $resource, $specialOrder, $specialOrderArg, $cmBotName, $cmChatId) = $record;
                    if ($specialOrder === null) {
                        $post = $this->makeContentForPost($text, $menuOptions, $resource, $cmChatId, $cmBotName);
                        if ($post === null) {
                            $this->conditionalLog(Log::TYPE_DAEMON, "CMTG441 No se paso ni texto ni resource");
                            $this->unqueueRollback($id);
                            $res = null;
                        }
                        else {
                            $res = $this->postToTelegram($post);
                        }
                    }
                    else {
                        $res = $this->orderToTelegram($cmBotName, $cmChatId, $specialOrder, $specialOrderArg);
                    }
                    if ($res === null) { $this->unqueueRollback($id);           }
                    else               { $this->unqueueCommit(  $id); $count++; }
                }
                if ($howMany != -1 && $count >= $howMany)                                              { break; }
                if ($sleepUntilNextTick($startOfRequestTS, $startMin, $requestMsecs / 1000) === false) { break; }
            }

            if ($logTimestamps) { Log::register(Log::TYPE_DAEMON, "CMTG461 Finaliza thread $thread/$threads en " . $now(6) . ' // elapsed = ' . (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"])); }
        }



        /**
         * Construye una estructura de datos que contiene la información final necesaria a enviar a los servidores de Telegram, a partir del
         * contenido de un Splash y su tipo
         *
         * @param  string               $textOrCaption      Texto del Splash o caption de imagen/video, o null si no los hay; en este último caso debe especificarse un resource
         * @param  array                $menuOptions        Arreglo de opciones de menú que serán renderizadas en forma de un custom keyboard
         * @param  InteractionResource  $resource           Resource del Splash, o null si no lo hay; en este último caso debe especificarse un texto
         * @param  string               $chatId             ID del chat de Telegram al cual será enviado el contenido
         * @param  string               $cmBotName          Nombre del bot de Telegram
         * @return array|null                               Estructura de datos, en forma: [ cmBotName, jsonContent, telegramWebServiceMethod ],
         *                                                  o null en caso de que no se haya pasado ni texto ni resource
         */
        private function makeContentForPost ($textOrCaption, $menuOptions, $resource, $chatId, $cmBotName)
        {
            if ($chatId === null) {
                Log::register(Log::TYPE_DEBUG, "CMTG425 Intentando un post a Telegram con un chatId nulo");
                return null;
            }
            if ($resource !== null) {
                switch ($resource) {
                    case InteractionResource::TYPE_IMAGE     : $method = "sendPhoto";     $content = $this->makePhotoContentBase(    $resource, $this->limitCaption($textOrCaption)); break;
                    case InteractionResource::TYPE_AUDIO     : $method = "sendAudio";     $content = $this->makeAudioContentBase(    $resource, $this->limitCaption($textOrCaption)); break;
                    case InteractionResource::TYPE_VOICE     : $method = "sendVoice";     $content = $this->makeVoiceContentBase(    $resource, $this->limitCaption($textOrCaption)); break;
                    case InteractionResource::TYPE_DOCUMENT  : $method = "sendDocument";  $content = $this->makeDocumentContentBase( $resource, $this->limitCaption($textOrCaption)); break;
                    case InteractionResource::TYPE_VIDEO     : $method = "sendVideo";     $content = $this->makeVideoContentBase(    $resource, $this->limitCaption($textOrCaption)); break;
                    case InteractionResource::TYPE_VIDEONOTE : $method = "sendVideoNote"; $content = $this->makeVideoNoteContentBase($resource, $this->limitCaption($textOrCaption)); break;
                    case InteractionResource::TYPE_LOCATION  : $method = "sendLocation";  $content = $this->makeLocationContentBase($resource);                                       break;
                    default                                  : $method = "sendMessage";   $content = $this->makeTextContentBase("CMTG433 invalid resource type in ChatMediumTelegram::makeJsonForPost()" . ($textOrCaption === null ? "" : " / $textOrCaption"));
                }
            }
            elseif ($textOrCaption === null && ($menuOptions === null || count($menuOptions) == 0)) {
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG444 Tratando de enviar un splash vacio");
                return null;
            }
            else {
                $method = "sendMessage";
                if (  $textOrCaption === null ||   // TODO identificar todas las formas de "salida vacia" para las que Telegram niega un display
                      1 === preg_match('/^[ \t\n]*$/', $textOrCaption) ||
                      0 === strpos($textOrCaption, self::$digitSpace)
                    ) {
                    list ($botIdx, ) = self::getCMbotSpecialIndex($cmBotName);
                    $textOrCaption = BotConfig::botMessage($botIdx, $this->locale, BotConfig::MSG_DEFAULT_TITLE_FOR_MENU);
                }
                $content = $this->makeTextContentBase($textOrCaption, $menuOptions);
            }
            $content['chat_id'] = $chatId;
            return [ $cmBotName, $content, $method ];
        }



        /**
         * Limita la longitud del texto de un caption a una cantidad determinada de caracteres
         *
         * @param  string   $text   Texto del caption original
         * @return string           Texto del caption acortado
         */
        private function limitCaption ($text)
        {
            $maxCaptionLength = BOTBASIC_TELEGRAM_CAPTION_MAXLENGTH;
            if (($len = strlen($text)) > $maxCaptionLength) {
                return substr($text, 0, $maxCaptionLength - 3) . "...";
            }
            return $text;
        }



        /**
         * Transforma metacaracteres de un texto en las respectivas entidades HTML para su visualización efectiva en Telegram
         *
         * Reglas de transformaciones:
         * * http://my.url/dot/com                      :: genera el link respectivo
         * * http:my-display-label://my.url/dot/com     :: genera el link respectivo con un texto visual distinto al link
         * * ***bold-text***                            :: negritas
         * * ___italic-text___                          :: itálicas
         * * [[[preformatted-fixed-block-text]]]        :: texto a ser renderizado en font monoespaciada
         *
         * @param  string   $text   Texto original
         * @return mixed            HTML a renderizar
         */
        private function encodeTextForJsonHtml ($text)
        {
            // fake an empty string so telegram doesn't complain (this doesn't work)
            //   if ($text === null || 1 === preg_match('/^[ \t\n]*$/', $text)) { return '<pre></pre>'; }
            // <>&
            $replacements = [ "<" => "&lt;", ">" => "&gt;", "&" => "&amp;" ];
            foreach ($replacements as $what => $for) { $text = str_replace($what, $for, $text); }
            // http://my.url/dot/com
            $text = preg_replace('/(https?):\/\/([^ ,.;\t\n]+)/', '<a href="$1://$2">$2</a>', $text);
            if ($text === null) {
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG475 Error de regexp");
            }
            // http:my-display-label://my.url/dot/com
            $text = preg_replace('/(https?):([^ \t\n:]+):\/\/([^ ,.;\t\n]+)/', '<a href="$1://$3">$2</a>', $text);
            if ($text === null) {
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG480 Error de regexp");
            }
            // ***bold-text***
            $text = preg_replace('/\*\*\*([^*\t\n])([^\t\n]*)\*\*\*/', '<bold>$1$2</bold>', $text);
            if ($text === null) {
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG485 Error de regexp");
            }
            // ___italic-text___
            $text = preg_replace('/___([^_\t\n])([^\t\n]*)___/', '<i>$1$2</i>', $text);
            if ($text === null) {
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG490 Error de regexp");
            }
            // [[[preformatted-fixed-block-text]]] and newlines (avoids nesting of <pre>'s)
            $magicMarker = '!#"$#%$&%___!#"$#%$&%';
            $count = null;
            while ($count !== 0) {
                $text = preg_replace('/\[\[\[(.*)\n(.*)\]\]\]/', '[[[$1' . $magicMarker . '$2]]]', $text, 1, $count);
                if ($text === null) {
                    $this->conditionalLog(Log::TYPE_DAEMON, "CMTG498 Error de regexp");
                    break;
                }
            }
            $text = preg_replace('/\[\[\[(.+)\]\]\]/', '<pre>$1</pre>', $text);
            if ($text === null) {
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG504 Error de regexp");
            }
            $text = str_replace("\n", "<pre>\n</pre>", $text);
            $text = str_replace($magicMarker, "\n", $text);
            // ready
            return $text;
        }



        /**
         * Extrae los miembros especificados del componente metainfo de un Resource
         *
         * Genera un error en bitácora si uno o más miembros no existen en la metainfo del Resource.
         *
         * @param  InteractionResource  $resource   Resource asociado o asociable a un Interaction
         * @param  string               $members    Lista de miembros, separados por coma (',')
         * @return array                            Arreglo con los miembros extraidos
         */
        private function buildMediaAttributes ($resource, $members)
        {
            $notFoundMembers = $setMembers = [];
            foreach (explode(',', $members) as $member) {
                if (! isset($resource->metainfo[$member])) { $notFoundMembers[]   = $member;                      }
                else                                       { $setMembers[$member] = $resource->metainfo[$member]; }
            }
            if (count($notFoundMembers) > 0) {
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG656 Resource " . $resource->id . " tipo " . $resource->type . " carece de miembros (" . implode(',', $notFoundMembers) . ")");
            }
            return $setMembers;
        }



        /**
         * Genera la estructura de datos (que será transferida como JSON en el raw content de la petición al web service) que representa el
         * contenido esperado por los servidores de Telegram cuando se envía texto con, opcionalmente, un custom keyboard
         *
         * @param  string       $text           Texto a ser renderizado
         * @param  array|null   $menuOptions    Opciones del menu / custom keyboard que estén presentes en el Splash, o null si no las hay
         * @return array                        Arreglo con los parámetros de contenido que debe recibir Telegram, en su formato final
         */
        private function makeTextContentBase ($text, $menuOptions = null)
        {
            $parameters = [
                'text'       => $this->encodeTextForJsonHtml($text),
                'parse_mode' => 'HTML',
            ];
            if ($menuOptions !== null) {
                $keyboard = [];
                $layout = $this->layoutMenuOptions($menuOptions);
                foreach ($layout as $layoutRow) {
                    $keybRow = [];
                    foreach ($layoutRow as $option) {
                        list ($text, $menuhook) = $option;   // from Interaction::encodeMenuhook()
                        $button = [
                            'text'          => $text,
                            'callback_data' => $menuhook,
                        ];
                        $keybRow[] = $button;
                    }
                    $keyboard[] = $keybRow;
                }
                $parameters['reply_markup'] = [
                    'inline_keyboard' => $keyboard,
                ];
            }
            return $parameters;
        }



        /**
         * Genera la estructura de datos (que será transferida como JSON en el raw content de la petición al web service) que representa el
         * contenido esperado por los servidores de Telegram cuando se envía una imagen
         *
         * @param  InteractionResource  $resource   Resource que representa la imagen
         * @param  string|null          $caption    Caption descriptiva de la imagen, o null si no la hay
         * @return array                            Arreglo con los parámetros de contenido que debe recibir Telegram, en su formato final
         */
        private function makePhotoContentBase ($resource, $caption = null)
        {
            $parameters = [
                'photo' => $resource->fileId,
            ];
            if ($caption !== null) {
                $parameters['caption'] = $caption;
            }
            return $parameters;
        }



        /**
         * Genera la estructura de datos (que será transferida como JSON en el raw content de la petición al web service) que representa el
         * contenido esperado por los servidores de Telegram cuando se envía un audio
         *
         * @param  InteractionResource  $resource   Resource que representa el audio
         * @param  string|null          $caption    Caption descriptivo del sonido, o null si no la hay
         * @return array                            Arreglo con los parámetros de contenido que debe recibir Telegram, en su formato final
         */
        private function makeAudioContentBase ($resource, $caption = null)
        {
            $parameters = [
                'audio'  => $resource->fileId,
            ];
            if ($caption !== null) {
                $parameters['caption'] = $caption;
            }
            return $parameters;
        }



        /**
         * Genera la estructura de datos (que será transferida como JSON en el raw content de la petición al web service) que representa el
         * contenido esperado por los servidores de Telegram cuando se envía un clip de voz
         *
         * @param  InteractionResource  $resource   Resource que representa el clip de voz
         * @param  string|null          $caption    Caption descriptivo del sonido, o null si no la hay
         * @return array                            Arreglo con los parámetros de contenido que debe recibir Telegram, en su formato final
         */
        private function makeVoiceContentBase ($resource, $caption = null)
        {
            $parameters = [
                'voice'  => $resource->fileId,
            ];
            if ($caption !== null) {
                $parameters['caption'] = $caption;
            }
            return $parameters;
        }



        /**
         * Genera la estructura de datos (que será transferida como JSON en el raw content de la petición al web service) que representa el
         * contenido esperado por los servidores de Telegram cuando se envía un documento
         *
         * @param  InteractionResource  $resource   Resource que representa el documento
         * @param  string|null          $caption    Caption descriptivo del documento, o null si no la hay
         * @return array                            Arreglo con los parámetros de contenido que debe recibir Telegram, en su formato final
         */
        private function makeDocumentContentBase ($resource, $caption = null)
        {
            $parameters = [
                'document' => $resource->fileId,
            ];
            if ($caption !== null) {
                $parameters['caption'] = $caption;
            }
            return $parameters;
        }



        /**
         * Genera la estructura de datos (que será transferida como JSON en el raw content de la petición al web service) que representa el
         * contenido esperado por los servidores de Telegram cuando se envía un video
         *
         * @param  InteractionResource  $resource   Resource que representa el video
         * @param  string|null          $caption    Caption descriptivo del video, o null si no la hay
         * @return array                            Arreglo con los parámetros de contenido que debe recibir Telegram, en su formato final
         */
        private function makeVideoContentBase ($resource, $caption = null)
        {
            $parameters = [
                'video' => $resource->fileId,
            ];
            if ($caption !== null) {
                $parameters['caption'] = $caption;
            }
            return $parameters;
        }



        /**
         * Genera la estructura de datos (que será transferida como JSON en el raw content de la petición al web service) que representa el
         * contenido esperado por los servidores de Telegram cuando se envía una nota de video
         *
         * @param  InteractionResource  $resource   Resource que representa el video
         * @param  string|null          $caption    Caption descriptivo ed la videonota, o null si no la hay
         * @return array                            Arreglo con los parámetros de contenido que debe recibir Telegram, en su formato final
         */
        private function makeVideoNoteContentBase ($resource, $caption = null)
        {
            $parameters = [
                'video_note' => $resource->fileId,
            ];
            if ($caption !== null) {
                $parameters['caption'] = $caption;
            }
            return $parameters;
        }



        /**
         * Genera la estructura de datos (que será transferida como JSON en el raw content de la petición al web service) que representa el
         * contenido esperado por los servidores de Telegram cuando se envía un par de coordenadas de geolocalización
         *
         * @param  InteractionResource  $resource   Resource que representa las coordenadas
         * @return array                            Arreglo con los parámetros de contenido que debe recibir Telegram, en su formato final
         */
        private function makeLocationContentBase ($resource)
        {
            $parameters = [
                'longitude' => $resource->metainfo->longitude,
                'latitude'  => $resource->metainfo->latitude,
            ];
            return $parameters;
        }



        /**
         * Dada una lista de opciones de menú, este método genera un arreglo bidimensional con la estructura óptima de distribución de las opciones
         * (botones de custom keyboard) en la pantalla de la chatapp Telegram (con optimización para pantallas de teléfonos de 4")
         *
         * @param  array    $menuOptions    Opciones de menú de un Splash
         * @return array                    Distribución, en forma: [ [ row1_option1, row1_option2, ..., row1_optionN ], [ row2_... ... ], ... ]
         */
        private function layoutMenuOptions ($menuOptions)
        {
            // calculate max normalized text length among all menu options
            $maxTextLength = 0;
            foreach ($menuOptions as $option) {
                if ($option == Interaction::TAG_MENU_PAGER_STARTS) { break;    }   // don't take into account pager button labels for optimal layout calculation
                if (! is_array($option))                           { continue; }
                $len = $this->normalizedTextLength($option[0]);   // from Interaction::encodeMenuhook()
                if ($len > $maxTextLength) { $maxTextLength = $len; }
            }
            // calculate max number of menu options per menu row
            $maxButtonsPerRow = floor(
                ( BOTBASIC_TELEGRAM_MAXPOSTWIDTH_PXS + BOTBASIC_TELEGRAM_EXTERNALPADDING_PXS )
                /
                ( 2*BOTBASIC_TELEGRAM_INTERNALPADDING_PXS + $maxTextLength*BOTBASIC_TELEGRAM_PXS_PER_CHAR + BOTBASIC_TELEGRAM_EXTERNALPADDING_PXS )
            );
            if ($maxButtonsPerRow == 0) { $maxButtonsPerRow = 1; }
            // layout the menu options into a 2-dimensional array
            $layout  = $row = [];
            $inPager = false;
            for ($i = 0, $j = 0; $i < count($menuOptions); $i++, $j++) {
                if ($menuOptions[$i] === Interaction::TAG_MENU_PAGER_STARTS) {
                    $inPager = true; $j--;
                }
                elseif ($menuOptions[$i] === Interaction::TAG_MENU_NEW_ROW) {   // TODO implementar llenado de este tipo de opciones a traves de codigo BB
                    if (count($row) != 0) { $layout[] = $row; $row = []; }
                    $j = -1;
                }
                else {
                    $row[] = $menuOptions[$i];
                    if (! $inPager && (($j + 1) % $maxButtonsPerRow == 0)) { $layout[] = $row; $row = []; }
                }
            }
            if (count($row) != 0) { $layout[] = $row; }
            // done
            return $layout;
        }



        /**
         * Heurística que retorna el "ancho normalizado" de un texto; expresado como el ancho (aproximado) del texto en caracteres tipo "dígito"
         *
         * @param  string   $text   Texto a calcular su longitud normalizada
         * @return int              Longitud normalizada, redondeada hacia arriba
         */
        private function normalizedTextLength ($text)
        {
            $textLength              = mb_strlen($text, 'UTF-8');
            $nonNormalWidthCharCount = 0;
            $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);   // http://stackoverflow.com/questions/3666306/how-to-iterate-utf-8-string-in-php
            for ($i = 0; $i < count($chars); $i++) {
                if (strpos(BOTBASIC_TELEGRAM_NORMAL_WIDTH_CHARS, $chars[$i]) === false) { $nonNormalWidthCharCount++; }
            }
            $normalizedLen = intval(ceil(($nonNormalWidthCharCount * BOTBASIC_TELEGRAM_CHARS_WIDTH_RATIO) + ($textLength - $nonNormalWidthCharCount)));
            return $normalizedLen;
        }



        /**
         * Realiza un POST a los servidores de Telegram con el objetivo de enviar una orden especial especifica, diferente a un Splash
         *
         * @param  string     $cmBotName Nombre del bot de la chatapp
         * @param  string     $cmChatId  Información de autenticación (no usada)
         * @param  int        $order     Orden; uno de los valores ORDER_...
         * @param  mixed|null $orderArg  Argumento de la orden
         * @return bool
         */
        private function orderToTelegram ($cmBotName, $cmChatId, $order, $orderArg = null)
        {
            $res = true;
            $this->doDummy([ $cmBotName, $cmChatId ]);
            switch ($order) {
                case self::ORDER_RESET_PRESSED_BUTTON :
                    if ($orderArg === null) { $res = null; }
                    else {
                        list ($cmBotName, $callbackQueryId) = $orderArg;   // discard $cmBotName as passed to the method, because in this case it's null
                        $request = [ $cmBotName, [ "callback_query_id" => $callbackQueryId ], "answerCallbackQuery" ];
                        $res = $this->postToTelegram($request);
                    }
                    break;
            }
            return $res;
        }



        /**
         * Realiza un POST a los servidores de Telegram con el objetivo de enviar el contenido de un Splash
         *
         * Usa los métodos provistos por la librería UniRest.
         *
         * @param  array    $request    El resultado de una invocación a makeContentForPost()
         * @return bool                 Indica si la operación fue exitosa
         */
        private function postToTelegram ($request)
        {
            list ($cmBotname, $content, $method) = $request;
            // obtain the bot token
            $token = $this->getCMbotToken($cmBotname);
            if ($token === null) {
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG708 No se puede obtener el token del bot $cmBotname");
                return false;
            }
            // assemble the url and determine the http header for the POST
            $url     = "https://api.telegram.org/bot$token/$method";
            $headers = [];
            if ($method == "sendMessage" || $method == "getFile" || $method = "answerCallbackQuery") {
                $headers['Content-Type'] = "application/json";
                $accept  = "application/json";
                $content = Body::json($content);
            }
            else {
                $accept  = "application/json";
                $content = Body::multipart($content);
            }
            $headers['Accept'] = $accept;
            // prepare and send
            try {
                Request::timeout(BOTBASIC_SENDERDAEMON_TELEGRAM_POST_TIMEOUT_SECS);
                $response = Request::post($url, $headers, $content);
                // process HTTP response
                $isOk = substr($response->code, 0, 1) == "2";
                if (! $isOk) {
                    $msg  = "Response:";
                    $msg .= isset($response->code) ? " code=[" . $response->code . "]" : "";
                    if (isset($response->body)) {
                        $msg .= isset($response->body->ok)          ? " ok=[" . ($response->body->ok ? "true" : "false") . "]" : "";
                        $msg .= isset($response->body->error_code)  ? " error_code=[" . $response->body->error_code . "]" : "";
                        $msg .= isset($response->body->description) ? " description=[" . $response->body->description . "]" : "";
                    }
                    $this->conditionalLog(Log::TYPE_DAEMON, "CMTG926 $msg // METHOD=$method // JSON=$content");
                    return false;
                }
            }
            catch (Exception $e) {
                $msg = "Exception arrojada por Unirest // " . $e->getMessage();
                $this->conditionalLog(Log::TYPE_DAEMON, "CMTG932 $msg // METHOD=$method // JSON=$content");
                return false;
            }
            // process telegram response
            $telegramResponse = $response->body->result;
            if ($method == "getFile") {
                if (! isset($telegramResponse->file_path)) { return false;             }
                else                                       { return $telegramResponse; }
            }
            else {
                if (! isset($response->body->ok)) {
                    $this->conditionalLog(Log::TYPE_DAEMON, "CMTG765 Response::body no se encuentra");
                    // see https://core.telegram.org/bots/api#making-requests
                    return false;
                }
                if ($response->body->ok !== true) {
                    $this->conditionalLog(Log::TYPE_DAEMON, "CMTG770 Algo estuvo mal: " . $telegramResponse->description);
                    return false;
                }
                // all was ok
                return true;
            }
        }



        /**
         * Obtiene el token de autenticación de un bot de Telegram, a partir de su nombre, el cual se requiere para invocar a los webservices provistos
         * por sus servidores
         *
         * @param  string       $cmBotname      Nombre del bot de Telegram
         * @return string|null                  Token de autenticación, o null si el bot no está registrado en self::$cmBots
         */
        private function getCMbotToken ($cmBotname)
        {
            $cmBots = BotConfig::cmBots(self::TYPE_TELEGRAM);
            foreach ($cmBots as $id => $bots) {
                foreach ($bots as $bot) {
                    if ($bot[0] == $cmBotname) { return $bot[2]; }
                }
            }
            return null;
        }



    }



    ////////////////////////////////////////////////
    // DAEMON MAIN CODE (Apache-invoked)
    // see it in .../httpdocs/scripts/telegramsender
    ////////////////////////////////////////////////



}   // end of botbasic namespace
