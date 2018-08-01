<?php
/**
 * Superclase para todos los medios de chat (Telegram, Whatsapp, ...)
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase ChatMedium
 *
 * Representación funcional, desde el punto de vista de BotBasic, de una chatapp. El WebRouter instancia a una subclase de esta clase,
 * según la chatapp de la cual provenga la petición, y ejecuta su método run(). Las respuestas provenientes de la ejecución de código
 * BotBasic se reflejan normalmente en el renderizado de Splashes (textos, menús y recursos multimedia) que son transmitidos a la chatapp
 * por medio de métodos de esta clase y sus subclases.
 *
 * @package botbasic
 */
abstract class ChatMedium
{



    /** @const Tipo de ChatMedium que consiste en un simulador de interacciones por línea de comandos */
    const TYPE_CLISTUB  = 101;

    /** @const Tipo de ChatMedium que consiste en un simulador de interacciones por formulario web */
    const TYPE_WEBSTUB  = 102;

    /** @const Tipo de ChatMedium principal que implementa la comunicación con Telegram */
    const TYPE_TELEGRAM = 111;

    /** @const Tipo de ChatMedium principal destinado a uso futuro (cuando WhatsApp implemente su bot's API) */
    const TYPE_WHATSAPP = 112;

    /** @var array Nombres de los tipos de ChatMedium; deben corresponder a los sufijos de los nombres de las subclases (ChatMediumXXX) */
    static protected $typeStrings = [
        self::TYPE_CLISTUB  => 'CliStub',
        self::TYPE_WEBSTUB  => 'WebStub',
        self::TYPE_TELEGRAM => 'Telegram',
        //self::TYPE_WHATSAPP => 'Whatsapp',
    ];

    /** @var int Indica el tipo del ChatMedium, como una de las constantes TYPE_... */
    public $type;

    /** @var string Es necesario definir el locale del ChatMedium aquí y no solo en el runtime para posibilitar la emisión de mensajes
     *              antes de la creación del runtime */
    protected $locale = BOTBASIC_DEFAULT_LOCALE;



    /**
     * Retorna la definición completa (credenciales) de los bots de cada ChatMedium (por lo que este es un método abstracto), como un
     * arreglo indexado con las mismas claves que $bbBots
     *
     * Se puede filtrar la lista retornada por un índice de ChatMedium::$bbBots y de ChatMediumXXX::$cmBots específico, y en ese caso
     * se retornará una lista con el único elemento de la lista que haga match correctamente indexado (sólo no existirá resultado[$cmAndBbBotsIdx])
     *
     * REDEFINIR EN CADA SUBCLASE (no es abstracto aquí pues es estático)
     *
     * @param  int|null     $cmAndBbBotsIdx     Indice por el que se quiere filtrar; o null para no filtrar
     * @return null
     */
    static protected function cmBots ($cmAndBbBotsIdx = null)
    {
        self::doDummy($cmAndBbBotsIdx);
        return null;
    }



    /**
     * Retorna todos los nombres de bots de una chatapp específica que satisfacen un patrón definido por una expresión regular
     *
     * @param  int          $cmType     Tipo del ChatMedium, según sus constantes TYPE_...
     * @param  null|string  $pattern    Expresión regular que actúa como filtro, o null para no filtrar
     * @return string[]                 Lista de nombres
     */
    static public function getCMbotNames ($cmType, $pattern = null)
    {
        $className = "ChatMedium" . self::typeString($cmType);   /** @var ChatMedium $className */
        $bots      = $className::cmBots();
        $names     = [];
        foreach ($bots as $idx => $someBots) {
            foreach ($someBots as $bot) {
                if ($pattern !== null) { if (1 === preg_match($pattern, $bot[0])) { $names[] = $bot[0]; } }
                else                   {                                            $names[] = $bot[0];   }
            }
        }
        return $names;
    }



    /**
     * Genera una lista de todos los nombres de bots de chatapps que actúan como "bots por defecto", o de bots asociados a un único bot del programa BotBasic
     *
     * Los nombres de bots por defecto aparecen como ChatMediumSubclass::$cmBots[anyIndex][0][0]
     *
     * @param  string|null  $bbBotName  Nombre del bot de BotBasic por el que se quiere filtrar la lista; o null para no filtrar
     * @param  string|null  $codeName   En caso de que $bbBotName sea especificado, es el nombre del programa BotBasic tal como está en $bbBots[[0]]
     * @return array                    Lista, en forma de: [ [ cmType, aCMdefaultBotNameForTheCMtype ], ... ]
     */
    static public function getDefaultCMbots ($bbBotName = null, $codeName = null)
    {
        if ($bbBotName === null && $codeName !== null || $bbBotName !== null && $codeName === null) {
            Log::register(Log::TYPE_RUNTIME, "CM200 argumentos invalidos en getDefaultCMbots()");
            return [];
        }
        $idx = null;
        if ($bbBotName !== null) {
            $bbBots = BotConfig::bbBots();
            foreach ($bbBots as $aIdx => $data) {
                if ($data[0] == $codeName && $data[2] == $bbBotName) { $idx = $aIdx; break; }
            }
            if ($idx === null) {
                Log::register(Log::TYPE_RUNTIME, "CM209 no se consigue la definicion del bot de BB en CM::bbBots ($bbBotName, $codeName)");
                return [];
            }
        }
        $defaultCMbots = [];
        foreach (self::allChatMediaTypes() as $cmType) {
            $className = '\botbasic\ChatMedium' . self::typeString($cmType);   /** @var ChatMedium $className */
            foreach ($className::cmBots($idx) as $cmBotCode => $cmBotDataArray) { $defaultCMbots[] = [ $cmType, $cmBotDataArray[0][0] ]; }
        }
        return $defaultCMbots;
    }



    /**
     * Retorna los códigos de las subclases de ChatMedium
     *
     * @return int[]                Códigos (TYPE_...)
     */
    static public function allChatMediaTypes ()
    {
        return array_keys(self::$typeStrings);
    }



    /**
     * Obtiene el nombre del tipo según su código, el cual define el nombre de una subclase de esta clase, como sufijo de "ChatMedium"
     *
     * @param  int          $type   Tipo: uno de los valores TYPE_...
     * @return string|null          Nombre del tipo, o null si no se encuentra
     */
    static public function typeString ($type)
    {
        return isset(self::$typeStrings[$type]) ? self::$typeStrings[$type] : null;
    }



    /**
     * Factory method de esta clase; retorna instancias de las subclases según el tipo especificado
     *
     * Actualmente se retorna null para el tipo TYPE_WHATSAPP.
     *
     * @param  int                  $type   Una de las constantes TYPE_...
     * @return ChatMedium|null              Nueva instancia, o null si el tipo especificado es inválido
     */
    static final public function create ($type)
    {
        switch ($type) {
            case self::TYPE_CLISTUB     : $res = new ChatMediumCliStub();  break;
            case self::TYPE_WEBSTUB     : $res = new ChatMediumWebStub();  break;
            case self::TYPE_TELEGRAM    : $res = new ChatMediumTelegram(); break;
            case self::TYPE_WHATSAPP    :
            default                     : $res = null;
        }
        if ($res !== null) { $res->type = $type; }
        return $res;
    }



    /**
     * Obtiene un arreglo que contiene las credenciales de un bot de chatapp a partir del script PHP que sirve de web service a los servidores de
     * las chatapps para procesar sus peticiones
     *
     * @param  string       $scriptName     Nombre del archivo que contiene el script que actúa como web service, o su URL en el web server local
     * @param  null|array   $aCmBots        null para usar ChatMediumSubclass::$cmBots; otra estructura equivalente puede ser especificada
     * @return array                        Credenciales, como: [ bbCodeId, cmBotName, cmScriptName, cmBotTokenOrPassword ]
     */
    abstract public function getCMbotCredentialsByScriptName ($scriptName, $aCmBots = null);



    /**
     * Obtiene un arreglo que contiene las credenciales de todos los bots de chatapps asociados a los criterios especificados por los tres
     * primeros parámetros
     *
     * Del arreglo retornado, el primero de ellos corresponderá a las credenciales de un bot de chatapp "por defecto".
     *
     * Este método utiliza dos conceptos con el mismo nombre: bots del programa BotBasic y bots de chatapps.
     *
     * @param  string       $bbCodename             Codename de BotBasic (ej. "neuropower")
     * @param  int          $bbMajorVersionNumber   Número de versión mayor asociado (coincidencia exacta)
     * @param  string       $bbBotName              Nombre del bot del programa BotBasic (coincidencia exacta)
     * @param  null|array   $aCmBots                null para usar ChatMediumSubclass::$cmBots; otra estructura equivalente puede ser especificada
     * @return string[]                             Credenciales, como: [ cmBotName, cmScriptName, cmBotTokenOrPassword ]
     */
    abstract protected function getCMbotCredentialsByBBinfo ($bbCodename, $bbMajorVersionNumber, $bbBotName, $aCmBots = null);



    /**
     * Retorna un índice compuesto para un nombre de bot de chatapp que después puede ser utilizado por getCMbotNameBySpecialIndex()
     * para obtener el nombre equivalente para otra chatapp (ambas como subclases de esta clase)
     *
     * Este método debe ser redefinido en todas las subclases de esta clase.
     *
     * @param  string   $cmBotName      Nombre del bot de chatapp (para cualquier chatapp, en vista de que el método es estático)
     * @return int[]                    Indice compuesto "especial"
     */
    static public function getCMbotSpecialIndex ($cmBotName)
    {
        return [ -1, $cmBotName == '' ? -1 : -1 ];   // whatever; redefine in subclasses
    }



    /**
     * Dado un índice "especial" obtenido con getCMbotCredentialsByBBinfo(), retorna el nombre de bot equivalente al de ese índice para
     * esta subclase específica de ChatMedium
     *
     * @param  int[]    $idx    Indice "especial"
     * @return string           Nombre del bot indexado para la chatapp representada por la subclase que implementa el método
     */
    static public function getCMbotNameBySpecialIndex ($idx)
    {
        return "_VOID_$idx";   // whatever; redefine in subclasses
    }



    /**
     * Obtiene la información o token de autenticación usado por un bot de chatapp para la activación de acciones dirigidas hacia los servidores
     * de la chatapp
     *
     * @param  string       $scriptName     Nombre del archivo que contiene el script que actúa como web service, o su URL en el web server local
     * @param  array|null   $aCmBots        null para usar ChatMediumSubclass::$cmBots; otra estructura equivalente puede ser especificada
     * @return string                       Información de autenticación
     */
    abstract public function getAuthInfoForDownloadsByScriptName ($scriptName, $aCmBots = null);



    /**
     * Obtiene el codename de un programa BotBasic a partir su índice en $bbBots
     * (común al aplicable a $cmBots de las subclases)
     *
     * @param  int          $bbBotIdx
     * @return string
     */
    static public function getBBcodename ($bbBotIdx)
    {
        $bbBots = BotConfig::bbBots();
        return $bbBots[$bbBotIdx][0];
    }



    /**
     * Obtiene el la última versión mayor permitida de un programa BotBasic a partir su índice en $bbBots
     * (común al aplicable a $cmBots de las subclases)
     *
     * @param  int          $bbBotIdx
     * @return string
     */
    static public function getBBlastestAllowedCodeMajorVersion ($bbBotIdx)
    {
        $bbBots = BotConfig::bbBots();
        return $bbBots[$bbBotIdx][1];
    }



    /**
     * Obtiene el nombre de bot de un programa BotBasic a partir su índice en $bbBots
     * (común al aplicable a $cmBots de las subclases)
     *
     * @param  int          $bbBotIdx
     * @return string
     */
    static public function getBBbotName ($bbBotIdx)
    {
        $bbBots = BotConfig::bbBots();
        return $bbBots[$bbBotIdx][2];
    }



    /**
     * Obtiene el índice en $bbBots del bot que debe ser usado para el logging de ciertas llamadas a Log::register; o null si no está definido
     * para el bot especificado por el argumento índice de $bbBots (normalmente ambos índices son diferentes)
     *
     * @param  int          $bbBotIdx
     * @return int|null
     */
    static public function getBBbotIdxForLoggingBot ($bbBotIdx)
    {
        $bbBots = BotConfig::bbBots();
        return isset($bbBots[$bbBotIdx][3]) ? $bbBots[$bbBotIdx][3] : null;
    }



    /**
     * Obtiene el índice en $bbBots (y en $cmBots de las subclases) a partir de los criterios especificados
     *
     * @param  string       $bbCodename             Codename del programa BotBasic
     * @param  int          $bbMajorVersionNumber   Número mayor de versión
     * @param  string       $bbBotName              Nombre del bot de BotBasic
     * @return int|null                             Indice
     */
    static protected function getBBbotIndexByBBinfo ($bbCodename, $bbMajorVersionNumber, $bbBotName)
    {
        $bbBots = BotConfig::bbBots();
        foreach ($bbBots as $idx => $triple) {
            if ($bbCodename == $triple[0] && $bbMajorVersionNumber == $triple[1] && $bbBotName == $triple[2]) { return $idx; }
        }
        return null;
    }



    /**
     * Obtiene el índice en $bbBots de un bot específico de una BBapp específica, a partir de datos de otro bot de esa BBapp
     *
     * @param  int      $aBBbotIdx      Indice en $bbBots de uno (cualquiera) de los bots de la BBapp
     * @param  string   $bbBotName      Nombre del bot de BotBasic a ubicar
     * @return int|null                 Indice; o null si no se puede ubicar
     */
    static public function getBBbotIndexByOtherBBbotSameBBapp ($aBBbotIdx, $bbBotName)
    {
        $bbBots = BotConfig::bbBots();
        if (! isset($bbBots[$aBBbotIdx])) { return null; }
        $bbCodename = $bbBots[$aBBbotIdx][0];
        foreach ($bbBots as $idx => $triple) {
            if ($triple[0] != $bbCodename) { continue;    }
            if ($triple[2] == $bbBotName)  { return $idx; }
        }
        return null;
    }



    //////////////
    // ENTER PHASE
    //////////////



    abstract public function setupIdeDebugging ($dressedUpdate, $botName, $bbCode);   //TODO doc



    // last two arguments are for ChatMedia TYPE_ fake and dummy
    /**
     * Genera un Update genérico a partir de un "update" particularizado para una chatapp específica que provenga de una petición al web
     * service local desde los servidores de la chatapp
     *
     * @param  mixed            $dressedUpdate      Entrada o update particular de la chatapp tal como es recibida por el web service, normalmente en JSON;
     *                                              algunas subclases pueden procesar estructuras de arreglos asociativos (stubs)
     * @param  string           $botName            Nombre del bot de la chatapp
     * @param  string           $cmAuthInfo         Token de autenticación, necesario para la descarga de contenidos multimedia incluidos en el update
     * @param  null             $textToPut          No usado por ahora (plantilla para las firmas de "raw+fake" updates basados en updates entrantes reales)
     * @param  null             $userIdToPut        No usado por ahora (plantilla para las firmas de "raw+fake" updates basados en updates entrantes reales)
     * @return Update|null|int                      Update genérico; o null en caso de error de guardado en BD; o -1 si no se debe hacer nada con el update
     */
    abstract public function undressUpdate ($dressedUpdate, $botName, $cmAuthInfo, $textToPut = null, $userIdToPut = null);



    /**
     * Este método run() es invocado por WebRouter::run() para activar la ejecución del programa BotBasic a partir de un Update genérico
     * (que previamente ha pasado por undressUpdate())
     *
     * @param string    $scriptName     Nombre del script o URL con que es invocado el web service local por parte de los servidores de la chatapp
     * @param $update   Update          Update genérico de entrada
     */
    final public function run ($scriptName, $update)
    {
        $cmBotCredentials = $this->getCMbotCredentialsByScriptName($scriptName);   // [ bbCodeId, cmBotName, cmScriptName, cmBotTokenOrPassword ]
        $cmType           = $this->type;
        $cmUserId         = $update->getCMuserId();
        $cmBotName        = $cmBotCredentials[1];
        $cmChatInfo       = $update->getCMchatInfo();
        $bbCodeId         = $cmBotCredentials[0];
        $cmc              = ChatMediumChannel::createFromCM($cmType, $cmUserId, $cmBotName, $cmChatInfo, $this, $bbCodeId);
        if ($cmc === null) {
            Log::register(Log::TYPE_RUNTIME, "CM414 No se puede crear CMC from CM ($cmType, $cmUserId, $cmBotName)", $this);
            // show some feedback: condicion anormal del sistema
            $msg    = BotConfig::botMessage($bbCodeId, $this->locale, BotConfig::MSG_EXCEPTION_CANT_CREATE_CMC);
            $toPost = $this->dressForDisplay($msg, null, null, [$cmBotName, $cmUserId, $cmChatInfo]);
            $res    = $this->display($toPost);
            if ($res === false) {
                Log::register(Log::TYPE_RUNTIME, "CM420 Falla la operacion de encolamiento (display)", $this);
            }
        }
        else {
            $cmc->orderExecution($update);
        }
    }



    /**
     * Cuando un Update de entrada contiene recursos multimedia que deben ser descargados desde los servidores de la chatapp, se debe invocar
     * a este método para obtener el URL para la descarga
     *
     * @param  string       $cmAuthInfo     Nombre del bot de la chatapp (NO es el token de autenticación definido en $cmBots de las subclases)
     * @param  int|string   $fileId         ID del recurso a descargar, tal como es reportado por el update de entrada
     * @return null|bool|string             URL para la descarga, o false si no se pudo obtener el URL, o null en caso de error de parámetros
     */
    abstract public function getDownloadUrl ($cmAuthInfo, $fileId);



    /////////////
    // EXIT PHASE
    /////////////



    /**
     * Envía hacia la chatapp un conjunto de salidas (Splashes), dirigidas todas al mismo bot de chatapp
     *
     * @param Splash[]              $splashes       Información a ser mostrada (textos, recursos multimedia o menus)
     * @param ChatMediumChannel     $cmChannel      Identificador de la tripleta (cmType, cmBotName, cmUserId) en donde es mostrada la información
     */
    public function render ($splashes, $cmChannel)
    {
        // Log::profilerStep(0, 'entering ChatMedium::render() with CMCid=' . $cmChannel->getId());
        $textsAndResources = [];
        $options           = [];
        // join all sequential enqueued texts for doing for them only one ChatMedium request
        $currentText       = '';
        foreach ($splashes as $splash) {
            $type = $splash->getSubType();
            switch ($type) {
                case Splash::SUBTYPE_TEXT :
                    $currentText .= ($currentText == '' ? '' : "\n") . $splash->getText();
                    break;
                case Splash::SUBTYPE_RESOURCE :
                    $captionResources = $splash->getResources(null, InteractionResource::TYPE_CAPTION);
                    $caption          = isset($captionResources[0]) ? $captionResources[0]->metainfo : null;
                    $resource         = $splash->getTheResource();
                    if ($caption !== null)  { $resource->metainfo = $caption; $resource->save($splash); }
                    if ($currentText != '') { $textsAndResources[] = $currentText; $currentText = '';   }
                    $textsAndResources[] = $resource;
                    break;
                case Splash::SUBTYPE_MENU :
                    if ($splash->getText() !== null) { $currentText .= ($currentText == '' ? '' : "\n") . $splash->getText(); }
                    $options = array_merge($options, $splash->getOptions());
                    break 2;   // avoid processing of splashes that were enqueued after the menu enqueue operation (shouldn't happen)
            }
        }
        if ($currentText != '' || count($options) > 0) { $textsAndResources[] = $currentText; }
        // post to ChatMedium
        for ($i = 0; $i < count($textsAndResources); $i++) {
            $textOrResource = $textsAndResources[$i];
            if (is_string($textOrResource)) { $text = $textOrResource; $resource = null;            }
            else                            { $text = null;            $resource = $textOrResource; }
            $postOptionsNow = $i == count($textsAndResources) - 1;
            $infoToPost = $this->dressForDisplay($text, $postOptionsNow ? ($options == [] ? null : $options) : null, $resource, $cmChannel);
            $res = $this->display($infoToPost);
            if (! $res) {
                Log::register(Log::TYPE_RUNTIME, "CM420 Falla la operacion de encolamiento (display)", $this, $cmChannel);
            }
        }
    }



    /**
     * Crea una estructura de datos contentiva de los argumentos especificados, a usar por el método display()
     *
     * Esta rutina NO retorna un objeto tipo Splash NI recibe un objeto de ese tipo.
     *
     * @param  string                       $text                       Texto para el Splash, o null si no contiene
     * @param  array                        $menuOptions                Opciones de menú, o null si no aplican
     * @param  InteractionResource          $resource                   Recurso a ser incluido en el Splash
     * @param  ChatMediumChannel|array      $cmChannelOrCmChatInfo      Tripleta identificadora del destino de la información, como objeto o arreglo;
     *                                                                  como arreglo debe ser: [ cmBotName, cmUserId, cmChatInfo ]
     * @return array
     */
    abstract public function dressForDisplay ($text, $menuOptions, $resource, $cmChannelOrCmChatInfo);



    /**
     * Envía a la chatapp (o más específicamente, a la cola de envíos de la chatapp) información de texto, teclados personalizados y recursos
     * empaquetadas con dressForSplash()
     *
     * ACTUALMENTE EL VALOR DE $forceAsync ES IGNORADO.
     *
     * @param  array    $infoToPost     Información empaquetada: [ text, menuOptions, resource, cmcOrChatInfo ]
     * @param  bool     $forceAsync     Indica si el envío se hará siempre asíncronamente (cola de envíos); cuando el 4to componente de $infoToPost
     *                                  es un arreglo con [ cmBotName, cmUserId, cmChatId ] y no una instancia de ChatMediumChannel, por defecto el
     *                                  envío se hace síncronamente a menos que este argumento sea true
     * @return bool                     Resultado de la operación de encolamiento
     */
    abstract public function display ($infoToPost, $forceAsync = true);



    /**
     * Fija el locale de esta instancia, el cual es usado para la recuperación de los mensajes localizados definidos aquí
     *
     * @param string    $locale     Uno de los locales válidos
     */
    public function setLocale ($locale)
    {
        $this->locale = $locale;
    }



    /**
     * IDE spoofer
     *
     * @param mixed     $arg
     */
    protected function doDummy ($arg) {}



}
