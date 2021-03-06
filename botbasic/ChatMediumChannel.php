<?php
/**
 * Objeto intermedio asociado a un medio de chat y a un canal de BotBasic
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase ChatMediumChannel
 *
 * Representa una "identidad definida" para la interacción entre BotBasic y una chatapp. La identidad deviene de un triplete compuesto por
 * el tipo de la chatapp, el ID del usuario de la chatapp y el nombre del bot de la chatapp que se usa para las interacciones.
 *
 * @package botbasic
 */
class ChatMediumChannel implements Initializable, Closable
{



    // in-DB attributes

    /** @var null|int ID del ChatMediumChannel, según como está en la BD */
    private $id            = null;

    /** @var int Uno de los tres componentes del triplete de identidad del ChatMediumChannel: $type del ChatMedium correspondiente */
    private $cmType        = null;

    /** @var string Uno de los tres componentes del triplete de identidad del ChatMediumChannel: ID de usuario tal como es entregado por la chatapp */
    private $cmUserId      = null;

    /** @var string Uno de los tres componentes del triplete de identidad del ChatMediumChannel: nombre del bot de la chatapp */
    private $cmBotName     = null;

    /** @var mixed Información adicional que provee el ChatMedium con cada interacción y que es necesaria para autenticar las respuestas dirigidas hacia él */
    private $cmChatInfo    = null;   // inmutable

    /** @var int Código del bot de BotBasic tal como aparece como clave en BotConfig::$cmBots y en BotConfig::$bbBots */
    private $bbBotIdx      = null;

    /** @var ChatMedium Instancia del ChatMedium asociado */
    private $cm            = null;

    /** @var BotBasicChannel Instancia del BotBasicChannel asociado */
    private $bbc           = null;

    /** @var Splash[] Cola de Splashes que deben se renderizados hacia las chatapps al final del proceso de corrida del runtime */
    private $splashQueue   = [];

    // volatile attributes

    /** @var bool Indica si el CMchannel está marcado para borrado, como producto de un USERID FROM */
    private $deleted       = false;

    /** @var ChatMediumChannel[] Store para todas las instancias de esta clase */
    static private $store  = [];

    /** @var bool Indica el estado usado en tainting() de la interfaz Closable */
    private $taintingState = false;



    public function getId ()
    {
        return $this->id;
    }



    /**
     * Retorna el tipo de ChatMedium reflejado en esta instancia; una de las constantes TYPE_... de esa clase
     *
     * @return int
     */
    public function getCMtype ()
    {
        return $this->cmType;
    }



    /**
     * Retorna el user ID correspondiente a la chatapp para el usuario reflejado en esta instancia
     *
     * @return string
     */
    public function getCMuserId ()
    {
        return $this->cmUserId;
    }



    /**
     * Retorna el nombre de bot de la chatapp reflejado en esta instancia
     *
     * @return string
     */
    public function getCMbotName ()
    {
        return $this->cmBotName;
    }



    /**
     * Retorna la información "chatinfo" asociada a cada update que permite al runtime efectuar autenticaciones hacia el servidor de la chatapp
     *
     * @return mixed
     */
    public function getCMchatInfo ()
    {
        return $this->cmChatInfo;
    }



    /**
     * Retorna la instancia de ChatMedium asociada a esta instancia
     *
     * @return ChatMedium
     */
    public function getChatMedium ()
    {
        return $this->cm;
    }



    /**
     * Retorna la instancia de BotBasicChannel asociada a esta instancia
     *
     * @return BotBasicChannel
     */
    public function getBBchannel ()
    {
        return $this->bbc;
    }



    /**
     * Retorna el "bbBotIdx" que define tanto la asociación al bot de BotBasic (en ChatMedium::$bbBots) como la colección de bots de chatapps
     * que están asociados a cada uno de esos bots (en ChatMedium::$cmBots); en ambas estructuras se comparte el bbCodeId
     *
     * @return int
     */
    public function getBBbotIdx ()
    {
        return $this->bbBotIdx;
    }



    /**
     * Retorna el codename del bot de BotBasic al cual está asociado esta instancia
     *
     * @return string
     */
    public function getBBcodename ()
    {
        return ChatMedium::getBBcodename($this->bbBotIdx);
    }



    /**
     * Retorna la última versión mayor autorizada del código de un programa BotBasic que puede ser activada por el runtime (aunque en BD
     * esté disponible código parseado con versiones posteriores)
     *
     * @return string
     */
    public function getBBlastestAllowedCodeMajorVersion ()
    {
        return ChatMedium::getBBlastestAllowedCodeMajorVersion($this->bbBotIdx);
    }



    /**
     * Retorna el nombre del programa BotBasic que está asociado a esta instancia
     *
     * @return string
     */
    public function getBBbotname ()
    {
        return ChatMedium::getBBbotName($this->bbBotIdx);
    }



    /**
     * Asigna la instancia asociada de BotBasicChannel a esta instancia de ChatMediumChannel
     *
     * @param BotBasicChannel $bbChannel
     */
    public function setBBchannel ($bbChannel)
    {
        $this->bbc = $bbChannel;
        $this->tainting(true);
    }



    /**
     * Indica si esta instancia, a través del bot de la chatapp asociada, está catalogado como un "default CMchannel"
     *
     * Los default CMchannels aparecen como los primeros bots de chatapps que están listados para cada uno de los bots del programa BotBasic.
     *
     * En los default CMchannels aparecen los mensajes que son desplegados con cláusulas ON foráneas por las directivas MENU, INPUT y PRINT.
     *
     * @return bool
     */
    public function isAdefaultCMchannel ()
    {
        foreach (ChatMedium::getDefaultCMbots() as $pair) { if ($pair[0] == $this->cmType && $pair[1] == $this->cmBotName) { return true; } }
        return false;
    }



    /**
     * Indica si CMchannel ha sido eliminado como producto de una operación USERID FROM
     *
     * @return bool
     */
    public function isDeleted ()
    {
        return $this->deleted;
    }



    /**
     * Fija el canal del programa BotBasic como eliminado, a fin de poder implementar la directiva USERID FROM
     */
    public function setAsDeleted ()
    {
        $this->deleted = true;
        $this->tainting(true);
    }



    /**
     * Constructor; almacena la nueva instancia en el store local
     *
     * @param  int|null                 $id             ID de la instancia en BD, o null para especificarlo después
     * @param  int                      $cmType         Una de las constantes ChatMedium::TYPE_...
     * @param  string                   $cmUserId       User ID del usuario de la chatapp
     * @param  string                   $cmBotName      Nombre del bot de la chatapp
     * @param  mixed                    $cmChatInfo     Información de autenticación de/para la chatapp
     * @param  ChatMedium               $chatMedium     Instancia de una de las subclases de ChatMedium, asociada a la chatapp
     * @param  BotBasicChannel|null     $bbChannel      Instancia de BotBasicChannel asociada, o null para especificarla después
     * @param  int                      $bbCodeId       "bbCodeId" que define la relación bot-de-chatapp con bot-de-programa-botbasic
     */
    private function __construct ($id, $cmType, $cmUserId, $cmBotName, $cmChatInfo, $chatMedium, $bbChannel, $bbCodeId)
    {
        $this->id         = $id;
        $this->cmType     = $cmType;
        $this->cmUserId   = strtoupper($cmUserId);
        $this->cmBotName  = $cmBotName;
        $this->cmChatInfo = $cmChatInfo;
        $this->cm         = $chatMedium;
        $this->bbc        = $bbChannel;
        $this->bbBotIdx   = $bbCodeId;
        self::$store[] = $this;
    }



    public function getDefauls ()
    {
        return [ 'cmchannel', [
            'cm_type'      => -1,
            'cm_user_id'   => '',
            'cm_bot_name'  => '',
            'cm_chat_info' => [],
            'bbchannel_id' => -1,
        ] ];
    }



    /**
     * Factory method que es llamado desde ChatMedium
     *
     * @param  int                      $cmType         Una de las constantes ChatMedium::TYPE_...
     * @param  string                   $cmUserId       User ID del usuario de la chatapp
     * @param  string                   $cmBotName      Nombre del bot de la chatapp
     * @param  mixed                    $cmChatInfo     Información de autenticación de/para la chatapp
     * @param  ChatMedium               $chatMedium     Instancia de una de las subclases de ChatMedium, asociada a la chatapp ($this al invocar)
     * @param  int                      $bbCodeId       "bbCodeId" que define la relación bot-de-chatapp con bot-de-programa-botbasic
     * @return ChatMediumChannel|null                   Nueva instancia; o null en caso de error de BD o si no se puede crear el BotBasicChannel asociado
     */
    static public function createFromCM ($cmType, $cmUserId, $cmBotName, $cmChatInfo, $chatMedium, $bbCodeId)
    {
        // get ID from the passed args
        $id = DBbroker::readChatMediumChannelId($cmType, $cmUserId, $cmBotName);
        if ($id === null) {
            Log::register(Log::TYPE_DATABASE, "CMC267 Error de BD");
            return null;
        }
        if ($id === false) { $id = null; }
        // create the object
        $cmc = new ChatMediumChannel($id, $cmType, $cmUserId, $cmBotName, $cmChatInfo, $chatMedium, null, $bbCodeId);
        // if ID is null, this is a new session from a new triplet
        if ($id === null) {
            $bbc = BotBasicChannel::createFromCMC($bbCodeId, $cmc);
            if ($bbc === null) {
                Log::register(Log::TYPE_RUNTIME, "CM277 No se puede crear BBC $bbCodeId from CMC", $cmc);
                return null;
            }
            $cmc->bbc = $bbc;
            $id = DBbroker::writeChatMediumChannel($cmc);
            // because id is null, then dbbroker will do an insert and return the id
            // we are writing here, so no need to call DBbroker::makeId()
            if ($id === null) {
                Log::register(Log::TYPE_DATABASE, "CMC285 Error de BD");
                return null;
            }
            $cmc->id = $id;
        }
        // else ID was read from DB, it means the user was here before with the same chatmedium and in the same bbchannel
        else {
            $data = DBbroker::readChatMediumChannel($id);
            if ($data === null) {
                Log::register(Log::TYPE_DATABASE, "CMC294 Error de BD");
                return null;
            }
            list (, , , , $bbcId) = $data;
            $bbc = BotBasicChannel::load($bbcId, null, true, $cmc);
            if ($bbc === null) {
                Log::register(Log::TYPE_RUNTIME, "CMC300 No se puede cargar BBC $bbcId");
                return null;
            }
            //$cmc->bbc = $bbc;
        }
        // ready
        return $cmc;
    }



    /**
     * Factory method que es llamado desde BotBasicChannel
     *
     * @param  int              $cmType                     Una de las constantes ChatMedium::TYPE_...
     * @param  int              $cmUserId                   User ID del usuario de la chatapp
     * @param  string           $cmBotName                  Nombre del bot de la chatapp
     * @param  BotBasicChannel  $bbChannel                  Instancia de BotBasicChannel ($this al invocar)
     * @param  bool             $onlyQueryStore             Si es true, no se crean nuevas instancias a partir del triplete identificatorio sino
     *                                                      que sólo se intenta extraer una instancia preexistente en el store de instancias
     * @return                  ChatMediumChannel|null      Nueva instancia; o null en caso de error de BD o en caso de no poder crearse la instancia de ChatMediumChannel
     */
    static public function createFromBBC ($cmType, $cmUserId, $cmBotName, $bbChannel, $onlyQueryStore = false)
    {
        // try to get from the store
        foreach (self::$store as $cmc) {
            if ($cmc->cmType == $cmType && strtoupper($cmc->cmUserId) == strtoupper($cmUserId) && $cmc->cmBotName == $cmBotName &&
                //($bbChannel === null ? true : $cmc->getBBchannel() == $bbChannel)) {
                true) {
                return $cmc;
            }
        }
        if ($onlyQueryStore) { return null; }
        // get ID from the passed args
        $id = DBbroker::readChatMediumChannelId($cmType, $cmUserId, $cmBotName);
        if ($id === null) {
            Log::register(Log::TYPE_DATABASE, "CMC332 Error de BD");
            return null;
        }
        if ($id === false) { $id = null; }
        // create the objects
        $cm  = ChatMedium::create($cmType);
        $cmc = new ChatMediumChannel($id, $cmType, $cmUserId, $cmBotName, null, $cm, $bbChannel, $bbChannel->getBBcodeId());
        // if ID is null, this is a new session from a new triplet; write in DB
        // no need to call here to DBbroker::makeId()
        if ($id === null) {
            $id = DBbroker::writeChatMediumChannel($cmc);   // because id is null, then dbbroker will do an insert and return the id
            if ($id === null) {
                Log::register(Log::TYPE_DATABASE, "CMC344 Error de BD");
                return null;
            }
            $cmc->id = $id;
        }
        // ready
        return $cmc;
    }



    /**
     * Carga desde la BD una instancia de esta clase a partir de su ID
     *
     * @param  int                      $id             ID de la instancia/entrada en BD
     * @param  BotBasicChannel|null     $bbChannel      Si no es null, se asociará el BotBasicChannel pasado a la instancia cargada (típicamente $this de quien invoca);
     *                                                  de otro modo se cargará la instancia asociada de BotBasicChannel desde la BD o desde el su store
     * @return ChatMediumChannel|null                   Nueva instancia; o null si hubo error de BD o de carga del BotBasicChannel
     */
    static public function load ($id, $bbChannel = null)   // pass $bbc to avoid BBC loading but assignment
    {
        // try to get from the store
        foreach (self::$store as $cmc) {
            if ($cmc->id == $id) { return $cmc; }
        }
        // if not, read from DB
        $data = DBbroker::readChatMediumChannel($id);
        if ($data === null) {
            Log::register(Log::TYPE_DATABASE, "CMC372 Error de BD");
            return null;
        }
        list ($cmType, $cmUserId, $cmBotName, $cmChatInfo, $bbcId) = $data;
        // if BBC was not passed, load it
        if ($bbChannel === null) {
            $bbChannel = BotBasicChannel::load($bbcId);
            if ($bbChannel === null) {
                Log::register(Log::TYPE_RUNTIME, "CMC380 No se puede cargar BBC $bbcId");
                return null;
            }
        }
        // create the objects
        $cm = ChatMedium::create($cmType);
        if ($cm === null) {
            Log::register(Log::TYPE_RUNTIME, "CMC387 No se puede crear CM $cmType");
            return null;
        }
        $cmc = new ChatMediumChannel($id, $cmType, $cmUserId, $cmBotName, $cmChatInfo, $cm, $bbChannel, $bbChannel->getBBcodeId());
        // ready
        return $cmc;
    }



    /**
     * Retorna una instancia de esta clase almacenada en el store a partir de su ID
     *
     * @param  int                      $bbChannelId            ID de la instancia
     * @param  int                      $filterByThisCMtype     Filtro, una de las constantes ChatMedium::TYPE_...
     * @return ChatMediumChannel|null                           Instancia; o null si no está en el store
     */
    static public function getFromStoreByBBchannelId ($bbChannelId, $filterByThisCMtype = null)
    {
        foreach (self::$store as $cmc) {
            if ($cmc->bbc->getId() == $bbChannelId) {
                if ($filterByThisCMtype !== null && $filterByThisCMtype !== $cmc->cmType) { continue; }
                return $cmc;
            }
        }
        return null;
    }



    /**
     * Una vez creada la instancia, este método es invocado con la llegada de un Update (ya transformado a genérico) para transferir el
     * control hacia BotBasicChannel y luego al runtime
     *
     * Esta rutina asume que las instancias asociadas de BotBasicChannel y BotBasicRuntime ya están creadas.
     *
     * @param  Update   $update     Update genérico que representa la entrada proveniente de una chatapp
     */
    public function orderExecution ($update)
    {
        $this->bbc->orderExecution($update);
    }



    /**
     * Fija el locale de esta instancia, que afecta las comunicaciones autónomas hacia la chatapp que se emiten como respuesta a eventos
     * excepcionales
     *
     * @param string    $locale     Uno de los locales aceptados
     */
    public function setLocale ($locale)
    {
        $this->cm->setLocale($locale);
    }



    /**
     * Las salidas de contenido efectivas del runtime se concretan con invocaciones a este método por parte del BotBasicChannel asociado
     *
     * @param Splash    $splash     Splash que representa de manera genérica una salida a ser mostrada en la chatapp, con o sin componente interactivo
     */
    public function enqueue ($splash)
    {
        $this->splashQueue[] = $splash;
    }



    /**
     * Después de encolar los Splashes que se quieren mostrar en la chatapp con enqueue(), se debe invocar a este método para enviarlos hacia la chatapp
     */
    public function render ()
    {
        $this->cm->render($this->splashQueue, $this);
        $this->splashQueue = [];
    }



    /**
     * Este método pregunta a las subclases de ChatMedium por nombres de bots de chatapps equivalentes a uno indicado, y sirve para implementar
     * funcionalidad provista al programador del BizModelAdapter
     *
     * @param $anOldCMbotName
     * @return null|string
     */
    public function updatedChatMediaChannelBotName ($anOldCMbotName)
    {
        $idx = null;
        foreach (ChatMedium::allChatMediaTypes() as $type) {
            $classname = "ChatMedium" . ChatMedium::typeString($type);   /** @var ChatMedium $classname */
            $idx = $classname::getCMbotSpecialIndex($anOldCMbotName);
            if ($idx !== null) { break; }
        }
        if ($idx === null) { return null; }
        return $this->cm->getCMbotNameBySpecialIndex($idx);
    }



    /**
     * Busca el nombre de bot de chatapp menos usado, a fin de implementar una de las políticas de generación de nombres de canales de BotBasic
     * que deben ser implementadas en BizModelAdapter
     *
     * @param  int          $baseChatMediaType      Una de las constantes ChatMedium::TYPE_..., que determina de qué ChatMedium se extraerá el nomrbe
     * @param  string       $regExpPattern          Expresión regular que actúa como filtro de los nombres disponibles
     * @return string|null                          El nombre del bot; o null en caso de error de cálculo o no haber disponibles
     */
    public function getLeastUsedCMchannelBotName ($baseChatMediaType, $regExpPattern)
    {
        $allBotNames    = ChatMedium::getCMbotNames($baseChatMediaType, $regExpPattern);
        $usedCMbotNames = DBbroker::readUsedCMchannelBotNames($baseChatMediaType, $this->cmUserId, $allBotNames);
        if ($usedCMbotNames === null) {
            Log::register(Log::TYPE_DATABASE, "CMC497 Error de BD");
        }
        foreach ($allBotNames as $cmBotName) {
            if (in_array($cmBotName, $usedCMbotNames)) { continue; }
            return $cmBotName;
        }
        for ($i = 0; $i < count($usedCMbotNames); $i++) {
            if (1 !== preg_match($regExpPattern, $usedCMbotNames[$i])) { continue; }
            return $usedCMbotNames[$i];   // take the oldest used
        }
        return null;
    }



    /**
     * Busca el nombre de bot de chatapp más usado, a fin de implementar una de las políticas de generación de nombres de canales de BotBasic
     * que deben ser implementadas en BizModelAdapter
     *
     * No se retorna en ningún caso un nombre del bot de chatapp que coincida con el del update que genera la instanciación del runtime, debido a
     * que se trata de implementar la directiva CHANNEL new ...
     *
     * @param  int          $baseChatMediaType      Una de las constantes ChatMedium::TYPE_..., que determina de qué ChatMedium se extraerá el nomrbe
     * @param  string       $regExpPattern          Expresión regular que actúa como filtro de los nombres disponibles
     * @return string|null                          El nombre del bot; o null en caso de error de cálculo o no haber disponibles
     */
    public function getMostUsedCMchannelBotName ($baseChatMediaType, $regExpPattern)
    {
        $except         = $this->cmBotName;
        $allBotNames    = ChatMedium::getCMbotNames($baseChatMediaType, $regExpPattern);
        $usedCMbotNames = DBbroker::readUsedCMchannelBotNames($baseChatMediaType, $this->cmUserId, $allBotNames);
        if ($usedCMbotNames === null) {
            Log::register(Log::TYPE_DATABASE, "CMC529 Error de BD");
            return null;
        }
        for ($i = count($usedCMbotNames) - 1; $i >= 0; $i--) {
            if ($usedCMbotNames[$i] == $except)                        { continue; }
            if (1 !== preg_match($regExpPattern, $usedCMbotNames[$i])) { continue; }
            return $usedCMbotNames[$i];   // take the newest used
        }
        foreach ($allBotNames as $cmBotName) {
            if (in_array($cmBotName, $usedCMbotNames)) { continue; }
            return $cmBotName;
        }
        return null;
    }



    /**
     * Almacena en BD la instancia
     *
     * @return bool|null    null en caso de error de BD; true de otro modo
     */
    public function save ()
    {
        if (! ($this->tainting() || $this->id === null)) { return true; }
        $res = DBbroker::writeChatMediumChannel($this);
        if ($res === null) {
            Log::register(Log::TYPE_DATABASE, "CMC556 Error de BD");
            return null;
        }
        elseif ($res === false) {
            Log::register(Log::TYPE_RUNTIME, "CMC560 El ID ya fue borrado de BD al grabar la instancia", $this);
            return null;
        }
        $this->tainting(false);
        return true;
    }



    public function close ($cascade)
    {
        $this->save();
    }



    static public function closeAll ()
    {
        foreach (self::$store as $obj) {
            $obj->close(false);
        }
    }



    public function tainting ($state = null)
    {
        if ($state === null)   { return $this->taintingState; }
        if (! is_bool($state)) { return null;                 }
        $this->taintingState = $state;
        return null;
    }



}
