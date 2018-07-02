<?php
/**
 * Librería de registro de mensajes y errores en archivos de bitácora y base de datos
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;

use \DateTime, \DateTimeZone;



/**
 * Clase T3log
 *
 * Implementa (como métodos estáticos) las herramientas de registro de errores de mensajes en bitácora.
 *
 * El registro en BD no está implementado aún.
 *
 * @package botbasic
 */
class Log
{



    /** Lista de los únicos tipos que serán reportados al bot de logging definido en ChatMedium para cada bot; no incluir TYPE_LOGLIB */
    static private $logToBotWithTypes = [
        self::TYPE_BBCODE,
        //self::TYPE_DEBUG,
    ];

    /** Tipo de una entrada en bitácora: mensaje genérico */
    const TYPE_GENERIC    = 101;

    /** Tipo de una entrada en bitácora: error de BD */
    const TYPE_DATABASE   = 102;

    /** Tipo de una entrada en bitácora: mensaje asociado a la lógica del entorno de ejecución */
    const TYPE_RUNTIME    = 103;

    /** Tipo de una entrada en bitácora: mensaje relativo a un ChatMedium */
    const TYPE_CHATMEDIUM = 104;

    /** Tipo de una entrada en bitácora: error o alerta asociado a la lógica de un programa BotBasic */
    const TYPE_BBCODE     = 105;

    /** Tipo de una entrada en bitácora: mensajes de bitacora sólo para debugging del código de BotBasic que son mostradas según el define BOTBASIC_DEBUG */
    const TYPE_DEBUG      = 106;

    /** Tipo de una entrada en bitácora: mensajes de bitacora provenientes de un manejador de colas */
    const TYPE_DAEMON     = 107;

    /** Tipo de una entrada en bitácora: entry del profiler */
    const TYPE_PROFILER   = 108;

    /** Tipo de una entrada originada desde la misma librería de T3log*/
    const TYPE_LOGLIB     = 109;

    /** @var array Etiquetas para los tipos de entradas de bitácora, tal como se reflejarán en ella */
    static private $types = [
        self::TYPE_GENERIC    => "generic",
        self::TYPE_DATABASE   => "database",
        self::TYPE_RUNTIME    => "runtime",
        self::TYPE_CHATMEDIUM => "chatmedium",
        self::TYPE_BBCODE     => "botbasic",
        self::TYPE_DEBUG      => "debug",
        self::TYPE_DAEMON     => "daemon",
        self::TYPE_PROFILER   => "profiler",
        self::TYPE_LOGLIB     => "loglib",
    ];

    /** Atributo de una entrada de bitácora: este en particular es no aplicable (artificio) */
    const ATTRIB_DUMMY              = 201;

    /** Atributo de una entrada de bitácora: excepción */
    const ATTRIB_EXCEPTION          = 202;

    /** Atributo de una entrada de bitácora: ID del BizModelUser */
    const ATTRIB_BIZMODEL_USERID    = 203;

    /** Atributo de una entrada de bitácora: ID del usuario de la chatapp (según es informado por la chatapp) */
    const ATTRIB_CHATMEDIUM_USERID  = 204;

    /** Atributo de una entrada de bitácora: nombre del ChatMedium */
    const ATTRIB_CHATMEDIUM_NAME    = 205;

    /** Atributo de una entrada de bitácora: nombre del bot de la chatapp */
    const ATTRIB_CHATMEDIUM_BOT     = 206;

    /** Atributo de una entrada de bitácora: ID del canal BotBasic */
    const ATTRIB_BBCHANNEL_ID       = 207;

    /** Atributo de una entrada de bitácora: ID del runtime */
    const ATTRIB_RUNTIME_ID         = 208;

    /** Atributo de una entrada de bitácora: recurso (no usado por el momento) */
    const ATTRIB_RESOURCE           = 209;

    /** Atributo de una entrada de bitácora: nombre del bot de BotBasic */
    const ATTRIB_BB_BOT             = 210;

    /** Atributo de una entrada de bitácora: número de línea del programa BotBasic */
    const ATTRIB_BB_LINENO          = 211;

    /** Atributo de una entrada de bitácora: simbolo (nombre de variable, ...) del programa BotBasic */
    const ATTRIB_BB_SYMBOL          = 212;

    /** Atributo de una entrada de bitácora: ID de un Splash */
    const ATTRIB_SPLASH_ID          = 213;

    /** Atributo de una entrada de bitácora: texto de un Splash */
    const ATTRIB_SPLASH_TEXT        = 214;

    /** Atributo de una entrada de bitácora: subtipo de un Splash */
    const ATTRIB_SPLASH_SUBTYPE     = 215;

    /** Atributo de una entrada de bitácora: ID de un Update */
    const ATTRIB_UPDATE_ID          = 216;

    /** Atributo de una entrada de bitácora: texto de un Update */
    const ATTRIB_UPDATE_TEXT        = 217;

    /** @var array Nombres de los tipos de atributos, con indicación de si son registrados antes o después del texto principal de la entrada de bitácora */
    static private $attribNames = [
        self::ATTRIB_CHATMEDIUM_NAME    => "/chatmediumName",       // '/' before name logs it before the message
        self::ATTRIB_CHATMEDIUM_BOT     => "/chatmediumBot",
        self::ATTRIB_CHATMEDIUM_USERID  => "/chatmediumUser",
        self::ATTRIB_BBCHANNEL_ID       => "/bbchannelId",
        self::ATTRIB_RUNTIME_ID         => "/runtimeId",
        self::ATTRIB_BIZMODEL_USERID    => "/bizmodelUser",
        self::ATTRIB_SPLASH_ID          => "splashId/",          // '/' after name logs it after the message
        self::ATTRIB_SPLASH_TEXT        => "splashText/",
        self::ATTRIB_SPLASH_SUBTYPE     => "splashSubtype/",
        self::ATTRIB_UPDATE_ID          => "updateId/",
        self::ATTRIB_UPDATE_TEXT        => "updateText/",
        self::ATTRIB_RESOURCE           => "resource/",
        self::ATTRIB_BB_BOT             => "bbBot/",
        self::ATTRIB_BB_LINENO          => "bbLineno/",
        self::ATTRIB_BB_SYMBOL          => "bbSymbol/",
        self::ATTRIB_EXCEPTION          => "exception/",
        self::ATTRIB_DUMMY              => "anything/",
    ];

    /** @var array Estos tipos de atributos no serán registrados */
    static private $dontLogThese = [ self::ATTRIB_DUMMY ];

    /** @var mixed Filehandler del archivo de bitácora, cuando está abierto */
    static private $fh = null;

    /** @var array[] Store para las marcas del profiler; cada marca contiene un arreglo con un historial de timestamps con microsegundos; el primero es un indicador bool de actividad */
    static private $profilerStore = [];

    /** @var ChatMedium Instancia de ChatMedium que es usada para encolar los mensajes hacia los logbots */
    static private $botloggingCM = null;

    /** @var array Buffer para acumular las salidas hacia los logbots y generar un único Splash hacia cada ChatMediumChannel */
    static private $botloggingBuffer = [];



    /**
     * Genera en bitácora una entrada
     *
     * Un "argumento" fuera de los primeros dos puede ser un conjunto de atributos, de forma: [ [ attribNameConstant, content(string|otherScalar) ], ... ];
     * o una instancia de una de las clases ChatMedium, ChatMediumChannel, BotBasicChannel, BotBasicRuntime, Splash, Update o InteractioNResource,
     * en cuyos casos se extraerán automáticamente atributos significativos para la descripción de la entrada; también puede ser un entero, en cuyo caso
     * se tomará como un número de línea de programa BotBasic, o un string, y se tomará como un símbolo del programa BotBasic
     *
     * @param  int          $type       Una de las constantes TYPE_...
     * @param  string       $message    Texto de la entrada
     * @param  mixed|null   $arg1       "Argumento"
     * @param  mixed|null   $arg2       "Argumento"
     * @param  mixed|null   $arg3       "Argumento"
     * @param  mixed|null   $arg4       "Argumento"
     * @param  mixed|null   $arg5       "Argumento"
     * @param  mixed|null   $arg6       "Argumento"
     * @param  mixed|null   $arg7       "Argumento"
     */
    static public function register ($type, $message, $arg1 = null, $arg2 = null, $arg3 = null, $arg4 = null, $arg5 = null, $arg6 = null, $arg7 = null)
    {
        if ($type == self::TYPE_DEBUG && ! BOTBASIC_DEBUG) { return; }
        $attribs = [];
        $runtime = $lineno = null;

        $t = function ($arg)
        {
            return $arg === null ? "null" : $arg;
        };

        $doForArg = function ($arg) use (&$attribs, $t, &$runtime, &$lineno)
        {
            if ($arg === null) {
                return;
            }
            elseif (is_array($arg)) {
                if (count($arg) > 0 && ! is_array($arg[0])) { $arg = [ $arg ]; }
                $attribs = array_merge($attribs, $arg);
            }
            elseif ($arg instanceof ChatMedium) {
                $attribs[] = [ self::ATTRIB_CHATMEDIUM_NAME,    $t($arg->type) ];
            }
            elseif ($arg instanceof ChatMediumChannel) {
                $attribs[] = [ self::ATTRIB_CHATMEDIUM_NAME,    $t($arg->getCMtype())    ];
                $attribs[] = [ self::ATTRIB_CHATMEDIUM_BOT,     $t($arg->getCMbotName()) ];
                $attribs[] = [ self::ATTRIB_CHATMEDIUM_USERID,  $t($arg->getCMuserId())  ];
            }
            elseif ($arg instanceof BotBasicChannel) {
                $attribs[] = [ self::ATTRIB_BBCHANNEL_ID,       $t($arg->getId()) ];
            }
            elseif ($arg instanceof BotBasicRuntime) {
                // $m = null;  /** @var BotBasicRuntime $m */   // $m->getId();   // IDE tells about the method
                $attribs[] = [ self::ATTRIB_RUNTIME_ID,         $t($arg->getId())        ];
                $attribs[] = [ self::ATTRIB_BIZMODEL_USERID,    $t($arg->getBMuserId())  ];
                $attribs[] = [ self::ATTRIB_BB_BOT,             $t($arg->getBBbotName()) ];
                $runtime   = $arg;
            }
            elseif ($arg instanceof InteractionResource) {
                $attribs[] = [ self::ATTRIB_RESOURCE,           $t($arg->serializeBrief()) ];
            }
            elseif ($arg instanceof Splash) {
                $attribs[] = [ self::ATTRIB_SPLASH_SUBTYPE,     $t($arg->getSubType()) ];
                $attribs[] = [ self::ATTRIB_SPLASH_ID,          $t($arg->getId())      ];
                $attribs[] = [ self::ATTRIB_SPLASH_TEXT,        $t($arg->getText())    ];
                foreach ($arg->getResources() as $r) {
                    $attribs[] = [ self::ATTRIB_RESOURCE,       $t($r->serializeBrief()) ];
                }
            }
            elseif ($arg instanceof Update) {
                $attribs[] = [ self::ATTRIB_UPDATE_ID,          $t($arg->getId())      ];
                $attribs[] = [ self::ATTRIB_UPDATE_TEXT,        $t($arg->getText())    ];
                foreach ($arg->getResources() as $r) {
                    $attribs[] = [ self::ATTRIB_RESOURCE,       $t($r->serializeBrief()) ];
                }
            }
            elseif (is_int($arg)) {
                $attribs[] = [ self::ATTRIB_BB_LINENO,          $arg ];
                $lineno = $arg;
            }
            elseif (is_string($arg)) {
                $attribs[] = [ self::ATTRIB_BB_SYMBOL,          $arg ];
            }
            elseif ($arg instanceof \Exception) {
                $attribs[] = [ self::ATTRIB_EXCEPTION,          $t($arg->getCode()) . '|' . $t($arg->getLine()) . '|' . $t($arg->getMessage()) ];
            }
        };

        // fill attribs
        $doForArg($arg1);
        $doForArg($arg2);
        $doForArg($arg3);
        $doForArg($arg4);
        $doForArg($arg5);
        $doForArg($arg6);
        $doForArg($arg7);
        // build the message
        $fullMessage = self::makeFullMessage($type, $message, $attribs);
        // write in logfile
        if (self::$fh === null) {
            $fh = fopen(BOTBASIC_LOGFILE, "a");
            if ($fh === false) { error_log("CAN'T OPEN LOGFILE FOR WRITING... $fullMessage\n"); exit; }
            else               { self::$fh = $fh;                                                     }
        }
        $res = fwrite(self::$fh, "$fullMessage\n");
        if ($res === false) { error_log("CAN'T WRITE INTO LOGFILE... $fullMessage\n"); exit; }
        else                { fflush(self::$fh);                                             }
        // optionally write in DB
        if (BOTBASIC_LOG_ALSO_TO_DB) {
            DBbroker::DBlogger($fullMessage);
        }
        // optionally write message to a monitoring bot
        if (BOTBASIC_LOG_ALSO_TO_BOT && in_array($type, self::$logToBotWithTypes) && $runtime !== null) {
            self::logToBot($message, $runtime, $lineno);
        }
    }



    /**
     * Construye el texto de una entrada a partir de los componentes pasados
     *
     * Este método es llamado por register().
     *
     * @param  int      $type       Una de las constantes TYPE_...
     * @param  string   $message    Texto de la entrada
     * @param  array    $attribs    Atributos de la entrada, de forma: [ [ attribNameConstant, content(string|resource|exception) ], ... ]
     * @return string               Texto final de la entrada, que comprende el contenido de todos los argumentos pasados
     */
    static private function makeFullMessage ($type, $message, $attribs)
    {
        if (! (is_string($message) || is_int($message))) { $message = "BAD_MESSAGE_CONTENT"; }
        $now     = self::makeCurrentDatetimeString();
        $typeStr = isset(self::$types[$type]) ? strtoupper(self::$types[$type]) : "INVALID_LOG_TYPE=[$type]";
        $text    = "[$now@$typeStr]";
        foreach (self::$attribNames as $ak => $an) {
            $res = self::getAttribs($attribs, $ak);
            if ($res === null) { continue; }
            foreach ($res as $part) {
                list ($subText, $displayType, $putAfterMessage) = $part;
                if (! $putAfterMessage) { $text .= ' [' . $displayType . ': ' . $subText . ']'; }
            }
        }
        $text .= ' ' . $message;
        foreach (self::$attribNames as $ak => $an) {
            $res = self::getAttribs($attribs, $ak);
            if ($res === null) { continue; }
            foreach ($res as $part) {
                list ($subText, $displayType, $putAfterMessage) = $part;
                if ($putAfterMessage) { $text .= ' [' . $displayType . ': ' . $subText . ']'; }
            }
        }
        return $text;
    }



    /**
     * Obtiene todos los atributos de un tipo dado que están en una colección de atributos
     *
     * @param  $attribs     array           Colección de atributos, de forma: [ [ attribNameConstant, content(string|resource|exception) ], ... ]
     * @param  $type        int             Una de las constantes ATTRIB_... que actúa como filtro
     * @return string[]|null                Colección de atributos extraida, en forma [ stringifiedContent, ... ]; o null si el tipo de atributo no debe ser registrado en bitácora
     */
    static private function getAttribs ($attribs, $type)
    {
        if ($attribs === null)                    { return null; }
        if (in_array($type, self::$dontLogThese)) { return null; }
        $pos = strpos(self::$attribNames[$type], '/');
        if ($pos === false)                       { return null; }
        if ($pos == 0) { $displayAfterAndNotBefore = false; $displayType = substr(self::$attribNames[$type], 1);       }
        else           { $displayAfterAndNotBefore = true;  $displayType = substr(self::$attribNames[$type], 0, $pos); }
        $res = [];
        foreach ($attribs as $attrib) {
            list ($t, $content) = $attrib;   /** @var $content \Exception */
            if ($type != $t || $content === null) { continue; }
            if  (is_object($content) && (get_class($content) == "Exception" || is_subclass_of($content, "Exception"))) {
                $content = '[' . $content->getFile() . ':' . $content->getLine() . ':' . $content->getMessage() . ']';
            }
            elseif ($content instanceof InteractionResource) {
                $content = $content->serializeBrief();
            }
            elseif (! is_string($content) && ! is_numeric($content)) {
                $content = "BAD_CONTENT";
            }
            $res[] = [ $content, $displayType, $displayAfterAndNotBefore ];
        }
        return $res;
    }



    /**
     * Genera una fecha+hora en un formato apropiado para su registro en bitácora y con el timezone correcto
     *
     * @param  bool     $withMiliSeconds        Indica si se debe incluir información de milisegundos
     * @return string                           Fecha y hora en el formato apropiado
     */
    static private function makeCurrentDatetimeString ($withMiliSeconds = true)
    {
        $mt = microtime(true);
        $ms = sprintf("%06d", ($mt - floor($mt)) * 1000000);
        $dt = new DateTime(date('Y-m-d H:i:s.' . $ms, $mt));
        $tz = new DateTimeZone(BOTBASIC_TIMEZONE);
        $dt->setTimezone($tz);
        $res = $dt->format("Y-m-d@H:i:s" . ($withMiliSeconds ? ".u" : ""));
        $res = substr($res, 0, strlen($res) - 3);
        return $res;
    }



    /**
     * Replica un mensaje hacia un los bots de ChatMedia definidos como (bots, users) de logging asociados a la instancia especificada de Runtime
     *
     * @param  string           $message    Mensaje a replicar hacia el bot+user de logging
     * @param  BotBasicRuntime  $runtime    Instancia de BotBasicRuntime desde la cual se origina inicialmente el acto de logging
     * @param  int|null         $lineno     Línea en ejecución del programa BotBasic; o null si no aplica
     */
    static private function logToBot ($message, $runtime, $lineno = null)
    {
        // check if this runtime should log to a specific bot
        $loggingBotIdx = ChatMedium::getBBbotIdxForLoggingBot($runtime->getBBbotIdx());
        if ($loggingBotIdx === null) { return; }
        // grab logging credentials
        $credentials = DBbroker::readTelegramLogBotCredentials($loggingBotIdx);
        if ($credentials === null) {
            Log::register(self::TYPE_LOGLIB, "L384 Error de BD", $runtime);
            return;
        }
        if (count($credentials) == 0) { return; }
        // build the instance of the ChatMedium subclass that will be used for enqueuing log messages, if not in store
        if (self::$botloggingCM === null) {
            $cm = ChatMedium::create(BOTBASIC_LOGBOT_CHATAPP);
            if ($cm === null) {
                Log::register(self::TYPE_LOGLIB, "L392 No se puede crear la instancia de ChatMedium que sera usada para replicar mensajes de T3log", $runtime);
                return;
            }
            self::$botloggingCM = $cm;
        }
        // enqueue the messages (in buffer)
        foreach ($credentials as $credential) {
            list (, $cmcId) = $credential;
            if (! isset(self::$botloggingBuffer[$cmcId])) { self::$botloggingBuffer[$cmcId] = []; }
            $message = '[' . $runtime->getBBcodename() .
                       '/' . $runtime->getBBbotName() .
                       ($lineno !== null ? "/L$lineno" : '') .
                       ($runtime->getBizModelUserId() !== null ? '/U' . $runtime->getBizModelUserId() : '') .
                       "]\n" . $message;
            self::$botloggingBuffer[$cmcId][] = $message;
        }
    }



    /**
     * Vacía el buffer de mensajes hacia los logbots, encolando los mensajes en forma de un único splash hacia cada ChatMediumChannel
     */
    static private function flushLogbotBuffer ()
    {
        foreach (self::$botloggingBuffer as $cmcId => $messages) {
            $fullMessage = implode("\n---\n", $messages);
            $toPost      = self::$botloggingCM->dressForDisplay($fullMessage, null, null, $cmcId);   // passing an int as 4th argument (aka cmcOrChatInfo)
            $res         = self::$botloggingCM->display($toPost);
            if ($res === false) {
                Log::register(self::TYPE_LOGLIB, "L421 Falla la operacion de encolamiento (display)");
            }
        }
        self::$botloggingBuffer = [];
    }



    /**
     * Este método debe ser llamado al finalizar la ejecución del ambiente de ejecución de BotBasic
     */
    static public function housekeeping ()
    {
        self::flushLogbotBuffer();
    }



    /**
     * Utility para el profiler; formatea un timestamp (float con microsegundos) a una precision determinada
     *
     * @param  null|float   $time           Timestamp, tal como es generado por microtime() o time()
     * @param  int          $precision      Precision de los microsegundos; por defecto 6 (lectura de microsegundos)
     * @return string                       Lectura de tiempo en formato hh:mm:ss.frac
     */
    static private function formattedTime ($time = null, $precision = 6)
    {
        if ($time === null) { $time = microtime(true); }
        $secs = floor($time);
        $usec = $time - $secs;
        $frac = substr(sprintf("%0.${precision}f", round($usec, $precision)), 2);
        $text = date_format(date_create("@$secs"), 'H:i:s.') . $frac;
        return $text;
    }



    /**
     * Inicia una secuencia de profiling y registra opcionalmente una entrada en bitácora
     *
     * El profiling requiere fijar en true la constante BOTBASIC_PROFILE.
     *
     * @param  string|int   $mark       Marca de profiling a ser usada posteriormente con profilerStep() y profilerStop()
     * @param  string       $tag        Texto descriptivo opcional de toda la secuencia
     * @param  string|null  $text       Texto opcional a mostrar; usar null para no mostrar la entrada
     */
    static public function profilerStart ($mark, $tag = '', $text = '')
    {
        if (! BOTBASIC_PROFILE) { return; }
        $now = microtime(true);
        if (! (is_int($mark) || is_string($mark))) { return; }
        self::$profilerStore[$mark] = [ true, $tag, $now ];
        if ($text !== null) {
            if ($tag !== '') { $tag = "=$tag"; }
            $msg = "[$mark$tag START NOW=" . self::formattedTime($now) . "] " . $text;
            self::register(self::TYPE_PROFILER, $msg);
        }
    }



    /**
     * Continúa una secuencia de profiling y registra opcionalmente una entrada en bitácora
     *
     * El profiling requiere fijar en true la constante BOTBASIC_PROFILE.
     *
     * @param  string|int   $mark           Marca de profiling usada con profilerStart()
     * @param  string|null  $text           Texto opcional a mostrar; usar null para no mostrar la entrada
     * @param  bool         $comesFromStop  Don't use
     */
    static public function profilerStep ($mark, $text = '', $comesFromStop = false)
    {
        if (! BOTBASIC_PROFILE) { return; }
        $now = microtime(true);
        if (! (is_int($mark) || is_string($mark))) { return; }
        if (! isset(self::$profilerStore[$mark]) || self::$profilerStore[$mark][0] === false) { return; }
        $fullMsecs = sprintf('%0.6f', round($now - self::$profilerStore[$mark][ 2                                    ], 6));
        $stepMsecs = sprintf('%0.6f', round($now - self::$profilerStore[$mark][ count(self::$profilerStore[$mark])-1 ], 6));
        self::$profilerStore[$mark][] = $now;
        if ($text !== null) {
            $tag   = self::$profilerStore[$mark][1];
            if ($tag !== '') { $tag = "=$tag"; }
            $label = $comesFromStop ? "STOP" : "STEP";
            $text  = "[$mark$tag $label  NOW=" . self::formattedTime($now) . " FULL=$fullMsecs STEP=$stepMsecs] " . $text;
            self::register(self::TYPE_PROFILER, $text);
        }
    }



    /**
     * Termina una secuencia de profiling y registra una entrada en bitácora con el resumen estadístico de la secuencia
     *
     * El profiling requiere fijar en true la constante BOTBASIC_PROFILE.
     *
     * @param  string|int   $mark       Marca de profiling usada con profilerStart() y profilerStep()
     * @param  string|null  $text       Texto opcional a mostrar; usar null para no mostrar la entrada
     * @param  bool         $doStep     Indica si debe hacer una llamada a profilerStep() antes de procesar la detención de la secuencia
     */
    static public function profilerStop ($mark, $text = '', $doStep = true)
    {
        if (! BOTBASIC_PROFILE) { return; }
        if ($doStep) { self::profilerStep($mark, $text, true); }
        if (! (is_int($mark) || is_string($mark))) { return; }
        if (! isset(self::$profilerStore[$mark]) || self::$profilerStore[$mark][0] === false) { return; }
        if (count(self::$profilerStore[$mark]) <= 3) {
            self::register(self::TYPE_PROFILER, "No steps registered for MARK=$mark" . (is_string($text) ? " TEXT=$text" : ''));
        }
        $mean = $sigma = 0; $min = +1e6; $max = -1e6; $steps = [];
        for ($i = 3; $i < count(self::$profilerStore[$mark]); $i++) {
            $steps[] = $step = self::$profilerStore[$mark][$i] - self::$profilerStore[$mark][$i-1];
            $mean   += $step;
            if ($step < $min) { $min = round($step, 6); }
            if ($step > $max) { $max = round($step, 6); }
        }
        $mean /= count($steps);
        for ($i = 1; $i < count($steps); $i++) {
            $sigma += pow($steps[$i] - $mean, 2);
        }
        $sigma = sqrt($sigma / count($steps));
        $mean  = round($mean,  6);
        $sigma = round($sigma, 6);
        $tag   = self::$profilerStore[$mark][1];
        if ($tag !== '') { $tag = "=$tag"; }
        $text = "[$mark$tag STOP MEAN=$mean SIGMA=$sigma MIN=$min MAX=$max] " . ($doStep ? "" : $text);
        self::register(self::TYPE_PROFILER, $text);
        self::$profilerStore[$mark][0] = false;
    }



}
