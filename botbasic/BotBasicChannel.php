<?php
/**
 * Canal de BotBasic (BotBasic implementa la posibilidad de múltiples canales de comunicación para cada usuario/rol)
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase BotBasicChannel
 *
 * Implementa un canal de BotBasic, que a su vez está reflejado en un bot distinto para cada ChatMedium. Si una BotBasic app tiene 3 bots
 * en cada uno de 2 ChatMedia, entonces hay un máximo de 6 ChatMediumChannels y 3 BotBasicChannels.
 *
 * @package botbasic
 */
class BotBasicChannel implements Initializable, Closable
{



    /** @var null|int ID del BotBasicChannel según la tabla bbchannel de la BD */
    private $id                  = null;

    /** @var array túneles vigentes apĺicables al canal, en la forma:
     *             [ InteractionResource::TYPE_... => [ tgtBBchannel1, tgtBBchannel2, ... ], ... ] */
    private $tunnels             = [];

    // "state" attributes

    /** @var array Mapa de variables de BotBasic específicas del canal, como:
     *             [ name => [ bool-tainted, value-or-null-for-deletion ], ... ] */
    private $vars                = [];

    /** @var array|null Stack del runtime (siempre asociado a cada canal) usado por GOSUB y RETURN */
    private $callStack           = null;

    /** @var array|null Cola de rutas de ejecución; contiene todos los INPUT y MENU que deben ser ejecutados por el canal (en secuencia);
     *                  si está vacía el routing se hace de acuerdo al match de hooks según el programa de BotBasic */
    private $routeQueue          = null;

    /** @var bool Indica si el canal está marcado para borrado, como producto de un CHANNEL DELETE */
    private $deleted             = false;

    /** @var array Contiene todos los BizModelAdapter indexados por BotBasicChannel::id */
    static private $storeForBMAs = [ -1 => [] ];

    /** @var BotBasicRuntime Instancia de BotBasicRuntime */
    private $rt                  = null;

    /** @var BotBasicChannel[] Store para todas las instancias de esta clase */
    static private $store        = [];

    /** @var bool Indica el estado usado en tainting() de la interfaz Closable */
    private $taintingState       = false;



    /**
     * Retorna el ID de la instancia tal como está en BD, o null si no está almacenada aún
     *
     * @return int|null
     */
    public function getId ()
    {
        return $this->id;
    }



    /**
     * Retorna el arreglo asociativo que contiene las variables del programa BotBasic asociadas específicamente al canal del programa BotBasic
     *
     * @return array
     */
    public function getVars ()
    {
        return $this->vars;
    }



    /**
     * Retorna el arreglo que contiene la pila de contextos de ejecución de GOSUB's
     *
     * @return array
     */
    public function getCallStack ()
    {
        return $this->callStack;
    }



    /**
     * Asigna temporalmente un stack vacío para los contextos de ejecución de GOSUB's
     *
     * Este método se debe usar sólo para el mecanismo de preservación del stack al ejecutar hooks (entry, menú, ...). A diferencia de otros
     * métodos, aquí no es necesario aplicar tainting(true) pues se trata de restituir un stack recién obtenido con getCallStack().
     */
    public function toogleCallStack ()
    {
        static $stack = null;
        if ($stack === null) { $stack = $this->callStack; $this->callStack = []; }
        else                 { $this->callStack = $stack; $stack = null;         }
    }



    /**
     * Retorna el arreglo que contiene la cola de contextos de ejecución de BotBasic para route()
     *
     * @return array
     */
    public function getRouteQueue ()
    {
        return $this->routeQueue;
    }



    /**
     * Obtiene la instancia de BotBasicRuntime asociada a esta instancia
     *
     * @return BotBasicRuntime
     */
    public function getBBruntime ()
    {
        return $this->rt;
    }



    /**
     * Obtiene la estructura de datos que almacena los túneles a los que este canal envía contenido
     *
     * @return array
     */
    public function getTunnels ()
    {
        return $this->tunnels;
    }



    /**
     * Obtiene la instancia de ChatMediumChannel a la cual está asociado este canal del programa BotBasic
     *
     * @return ChatMediumChannel|null
     */
    public function getCMchannel ()
    {
        return ChatMediumChannel::getFromStoreByBBchannelId($this->id);   // TODO should filter by CMtype? (2nd arg) (next method makes 2x CMC's attached to the same BBC)
    }



    /**
     * Obtiene la instancia de ChatMediumChannel asociada a este canal del programa BotBasic, para el ChatMedium especificado, si hubiese
     *
     * @param  int                      $cmType     Una de las constantes ChatMedium::TYPE_...
     * @return ChatMediumChannel|null               Instancia de ChatMediumChannel; o null si no estuviese definida para el BotBasicChannel
     */
    public function getCMchannelByCMtype ($cmType)
    {
        $thisCmc = $this->getCMchannel();
        if ($thisCmc === null)                { return null;     }
        if ($thisCmc->getCMtype() == $cmType) { return $thisCmc; }
        $data = DBbroker::readCMchannelDataForBBchannel($this, $cmType);
        if ($data === null) {
            Log::register(Log::TYPE_DATABASE, "BBC178 Error de BD");
            return null;
        }
        elseif ($data === false) {
            return null;
        }
        list ($cmUserId, $cmBotName) = $data;
        $cmc = ChatMediumChannel::createFromBBC($cmType, $cmUserId, $cmBotName, $this);
        return $cmc;
    }



    /**
     * Obtiene el bbCodeId del programa BotBasic, el cual tiene sentido a la luz de las estructuras ChatMedium::$bbBots y ChatMediumXXX::$cmBots
     *
     * @return int|null
     */
    public function getBBcodeId ()
    {
        return $this->rt->getBBbotIdx();
    }



    /**
     * Indica si este canal del programa BotBasic es un "default channel" sobre el cual se deben emitir Splashes de proveniencia foránea
     *
     * @return bool
     */
    public function isDefaultBBchannel ()
    {
        return $this->getCMchannel()->isAdefaultCMchannel();
    }



    /**
     * Indica si el canal del programa BotBasic ha sido eliminado por medio de una directiva CHANNEL DELETE
     *
     * @return bool
     */
    public function isDeleted ()
    {
        return $this->deleted;
    }



    /**
     * Fija el canal del programa BotBasic como eliminado, a fin de poder implementar la directiva CHANNEL DELETE
     */
    public function setAsDeleted ()
    {
        $this->deleted = true;
        $this->tainting(true);
    }



    /**
     * Constructor
     *
     * @param int|null          $id             ID de la instancia
     * @param BotBasicRuntime   $runtime        Instancia asociada de BotBasicRuntime
     * @param array|null        $callStack      Estructura que contiene al call stack de ejecución del runtime; o null para inicializar una
     * @param array|null        $routeQueue     Estructura que contiene la cola de interacciones pendientes del canal; o null para inicializar una
     * @param array|null        $vars           Estructura que contiene las variables del programa BotBasic específicamente asociadas al canal; o null para inicializar una
     * @param array|null        $tunnels        Estructura que contiene los túneles en los que este canal es origen; o null para inicializar una
     */
    private function __construct ($id, $runtime, $callStack = [], $routeQueue = [], $vars = [], $tunnels = [])
    {
        $this->id         = $id;
        $this->rt         = $runtime;
        $this->tunnels    = $tunnels;
        $this->vars       = $vars;
        $this->callStack  = $callStack;
        $this->routeQueue = $routeQueue;
        // route[]: elems: [ type, content... ] ; can be:
        //                 [ 'input',      bot, lineno, dataType, title, word, fromValue                                       ]
        //                 [ 'stdmenu',    bot, lineno, options, pager                                                         ]
        //                 [ 'predefmenu', bot, lineno, options, pager, object-used-for-context-can-be-anything-initially-null ]
        self::$store[] = $this;
    }



    public function getDefauls ()
    {
        return [ 'bbchannel', [
            'call_stack' => [],
            'route'      => [],
            'runtime_id' => -1,
        ] ];
    }



    /**
     * Factory method invocado desde ChatMediumChannel
     *
     * @param  int                  $bbCodeId       "bbCodeId" que refleja la identidad del bot de BotBasic en su asociación a bots de chatapps
     * @param  ChatMediumChannel    $cmChannel      Instancia de ChatMediumChannel asociada
     * @return BotBasicChannel|null                 Nueva instancia; o null si no se pudo crear la instancia de BotBasicRuntime o hubo error de BD
     */
    static public function createFromCMC ($bbCodeId, $cmChannel)
    {
        $rt = BotBasicRuntime::create($bbCodeId, $cmChannel);
        if ($rt === null) {
            Log::register(Log::TYPE_RUNTIME, "BBC220 No se puede crear RT from CMC con bbCode $bbCodeId", $cmChannel);
            return null;
        }
        $bbc = new BotBasicChannel(null, $rt);
        $id = DBbroker::writeBotBasicChannel($bbc);   // because id is null, then dbbroker will do an insert and return the id; no need to call DBbroker::makeId()
        if ($id === null) {
            Log::register(Log::TYPE_DATABASE, "BBC226 Error de BD");
            return null;
        }
        $bbc->id = $id;
        return $bbc;
    }



    /**
     * Factory method invocado desde BotBasicRuntime
     *
     * @param  BotBasicRuntime      $runtime        Instancia del runtime asociada
     * @param  int                  $cmType         Una de las constantes ChatMedium::TYPE_...
     * @param  int                  $cmUserId       User ID del usuario en la chatapp
     * @param  string               $cmBotName      Nombre del bot de la chatapp
     * @return BotBasicChannel|null                 Nueva instancia; o null en caso de error de BD o de creación de la instancia de ChatMediumChannel
     */
    static public function createFromBBRT ($runtime, $cmType, $cmUserId, $cmBotName)
    {
        // try to get from the CMC store
        $cmc = ChatMediumChannel::createFromBBC($cmType, $cmUserId, $cmBotName, null, true);   // 4th arg can be null because 5th is true
        if ($cmc !== null) {   // BBC should be already loaded for the matched CMC
            $bbc = $cmc->getBBchannel();
            if ($bbc->getBBruntime() === $runtime) { return $bbc; }
        }
        // not found? try to read from the DB; if not found, then create a new one
        $bbcId = DBbroker::readBBchannelIdByCMchannelData($cmType, $cmUserId, $cmBotName);
        if ($bbcId === null) {
            Log::register(Log::TYPE_DATABASE, "BBC319 Error de BD");
            return null;
        }
        elseif ($bbcId !== false) {
            $bbc = self::load($bbcId, $runtime);
        }
        else {
            // create a new BBC instance
            $bbc = new BotBasicChannel(null, $runtime);
            $id  = DBbroker::writeBotBasicChannel($bbc);   // because id is null, then dbbroker will do an insert and return the id; no need to call DBbroker::makeId()
            if ($id === null) {
                Log::register(Log::TYPE_DATABASE, "BBC253 Error de BD");
                return null;
            }
            $bbc->id = $id;
        }
        // cascade creation of CMC
        $cmc = ChatMediumChannel::createFromBBC($cmType, $cmUserId, $cmBotName, $bbc);
        if ($cmc === null) {
            Log::register(Log::TYPE_RUNTIME, "BBC260 No se puede crear CMC from BBRT ($cmType, $cmUserId, $cmBotName)", $bbc);
            return null;
        }
        // ready
        return $bbc;
    }



    /**
     * Carga una instancia de esta clase desde la BD a partir de su ID, incluyendo la carga subsecuente de variables y túneles
     *
     * @param  int                      $id                         ID de la instancia
     * @param  BotBasicRuntime|null     $runtime                    Instancia de BotBasicRuntime a la que asociar esta; o null para cargar la correspondiente
     * @param  bool                     $logIfNotFound              Si es false, no se registrará en bitácora el evento de que no se consiga la entrada en BD por su ID
     * @param  ChatMediumChannel|null   $updateThisCmcReference     Instancia de ChatMediumChannel opcional a la que actualizar la referencia al BotBasicChannel cargado
     * @return BotBasicChannel|null                                 La instancia; o null en caso de error de carga
     */
    static public function load ($id, $runtime = null, $logIfNotFound = true, $updateThisCmcReference = null)   // pass $rt to avoid BBRT load but assignment
    {
        // check if in store
        foreach (self::$store as $bbc) {
            if ($bbc->id == $id) { return $bbc; }
        }
        // if not, read from DB
        // $data = DBbroker::readBotBasicChannel($id, $runtime === null ? null : $runtime->getId());
        $data = DBbroker::readBotBasicChannel($id);   // why filter by runtime?
        if ($data === null) {
            Log::register(Log::TYPE_DATABASE, "BBC286 Error de BD");
            return null;
        }
        elseif ($data === false) {
            if ($logIfNotFound) {
                Log::register(Log::TYPE_RUNTIME, "BBC291 No se puede cargar BBC con ID $id");
            }
            return null;
        }
        list ($callStack, $route, $rtId) = $data;
        // create the object and store
        $bbc = new BotBasicChannel($id, $runtime, $callStack, $route);
        // if should update passed CMC's BBC reference...
        if ($updateThisCmcReference !== null) {
            $updateThisCmcReference->setBBchannel($bbc);
        }
        // load BBRT if not passed
        if ($runtime === null) {
            $runtime = BotBasicRuntime::loadById($rtId, true, $bbc);
            if ($runtime === null) {
                Log::register(Log::TYPE_RUNTIME, "BBC300 No se puede cargar el runtime $rtId");
                return null;
            }
        }
        // load vars
        $bbc->vars = [];
        $vars      = DBbroker::readVars(null, $bbc);
        if ($vars === null) {
            Log::register(Log::TYPE_DATABASE, "BBC310 Error de BD");
            return null;
        }
        foreach ($vars as $varData) {
            list ($name, $value) = $varData;
            $bbc->vars[$name] = [ false, $value ];
        }
        // tunnels loading
        $data = DBbroker::readBotBasicTunnels($id);
        if ($data === null) {
            Log::register(Log::TYPE_DATABASE, "BBC320 Error de BD");
            return null;
        }
        foreach ($data as $targetSpec) {
            list ($resourceType, $targetBbcId) = $targetSpec;
            $targetBbc = self::load($targetBbcId, $runtime);
            // TODO para TUNNEL: load() no esta cargando el CMC correspondiente al canal (ver metodo de arriba para copiar forma)
            //                   lo cual es el comportamiento deseado para el caso de la llamada desde BBRT::loadById()
            if ($targetBbc === null) {
                Log::register(Log::TYPE_RUNTIME, "BBC327 No se puede cargar el BBC $targetBbcId");
                return null;
            }
            $bbc->addTunnel($resourceType, $targetBbc);
        }
        // ready
        return $bbc;
    }



    /**
     * Fija la referencia al runtime
     *
     * @param  BotBasicRuntime      $runtime
     */
    public function setRuntime ($runtime)
    {
        $this->rt = $runtime;
    }



    /**
     * Ubica las instancias que estén almacenada en el store a partir de un ID del runtime asociado
     *
     * @param  int                  $runtimeId      ID de la instancia a ubicar
     * @return BotBasicChannel[]                    Colección de instancias ubicadas
     */
    static public function getFromStoreByRuntimeId ($runtimeId)
    {
        $res = [];
        foreach (self::$store as $bbc) {
            if ($bbc->rt->getId() == $runtimeId) { $res[$bbc->id] = $bbc; }
        }
        return $res;
    }



    /**
     * Fija esta instancia como el "current channel" del runtime respectivo
     */
    public function setAsCurrent ()
    {
        $oldStore    = self::$store;
        self::$store = [ $this ];
        foreach ($oldStore as $bbc) {
            if ($bbc !== $this) { self::$store[] = $bbc; }
        }
    }



    /**
     * Agrega un túnel a la estructura de túneles entre instancias de BotBasicChannel, en la que esta instancia actúa como canal origen
     *
     * @param int               $resourceType   Una de las constantes InteractionResource::TYPE_...
     * @param BotBasicChannel   $targetBbc      Canal destino
     */
    public function addTunnel ($resourceType, $targetBbc)
    {
        $this->removeTunnels($resourceType, $targetBbc);
        $this->tunnels[$resourceType][] = $targetBbc;
        $this->tainting(true);
    }



    /**
     * Elimina túneles en los que esta instancia actúa como canal origen
     *
     * @param null|int                                  $resourceType   Una de las constantes InteractionResource::TYPE_...;
     *                                                                  o null para borrar los túneles de todos los tipos de resource
     * @param null|BotBasicChannel|BotBasicChannel[] $targetBbcs        Uno o más instancias de BotBasicChannels que indican los destinos de los túneles
     *                                                                  que deben ser borrados; o null para borrar todos los túneles del tipo de resource indicado
     */
    public function removeTunnels ($resourceType = null, $targetBbcs = null)
    {
        // if no resource type passed, delete all tunnels
        if ($resourceType === null) {
            $this->tunnels = [];
            return;
        }
        // if $targetBbcs is not array (but not null), convert it
        if ($targetBbcs !== null && ! is_array($targetBbcs)) { $targetBbcs = [ $targetBbcs ]; }
        // iterate thru tunnels
        foreach ($this->tunnels as $thisResourceType => $theseTargetBbcs) {
            if ($resourceType != $thisResourceType) { continue; }
            // if no targetBbcs passed, delete all targets
            if ($targetBbcs === null) {
                $this->tunnels[$thisResourceType] = [];
                continue;
            }
            // else, delete specific targets
            foreach ($theseTargetBbcs as $thisTargetBbc) {
                if (in_array($thisTargetBbc, $targetBbcs)) {
                    $this->tunnels[$thisResourceType] = array_filter($this->tunnels[$thisResourceType],
                        function ($bbc) use ($thisTargetBbc) { return $bbc != $thisTargetBbc; }
                    );
                }
            }
        }
        $this->tainting(true);
    }



    /**
     * Obtiene los nombres de todas las variables del programa BotBasic registradas en esta instancia
     *
     * @return string[]
     */
    public function getAllVarNames ()
    {
        $res = [];
        foreach ($this->vars as $name => $pair) {
            if ($pair[1] !== null) { $res[] = $name; }
        }
        return $res;
    }



    /**
     * Indica si un nombre de variable de programa BotBasic está asignado en esta instancia específica
     *
     * @param  string   $name       Nombre de la variable
     * @return bool
     */
    public function isSetVar ($name)
    {
        return isset($this->vars[$name]) && $this->vars[$name][1] !== null;
    }



    /**
     * Fija el valor de una variable de forma que esté asociada específicamente a esta instancia de canal de programa BotBasic y no al runtime
     *
     * Cuando la variable sea una variable mágica, se invoca a su manejador en el runtime.
     *
     * @param string    $name       Nombre de la variable
     * @param string    $value      Valor de la variable
     * @param int       $lineno     Número de línea del programa BotBasic desde el que se ejecuta la asignación
     * @param string    $bot        Nombre del bot del programa BotBasic que ejecuta la asignación
     */
    public function setVar ($name, $value, $lineno, $bot)
    {
        if ($this->getBBruntime()->isMagicVar($name)) { $this->getBBruntime()->setMagicVar($name, $value, $lineno, $bot); }
        else                                          { $this->setCommonVar($name, $value, $this->getBBruntime());        }   // isCommonVar OR overwritting a message name
    }



    /**
     * Obtiene el valor de una variable común del programa BotBasic que esté registrada en esta instancia
     *
     * @param  string           $name       Nombre de la variable
     * @param  BotBasicRuntime  $runtime    Instancia del runtime a partir de la cual se buscará el valor si no se consigue en esta instancia
     * @return string|null                  Valor de la variable; o null si no está fijada ni en esta instancia ni en el runtime
     */
    public function getCommonVar ($name, $runtime)
    {
        $res = isset($this->vars[$name]) ? $this->vars[$name][1] : null;
        if ($res === null && $runtime !== null) { return $runtime->getCommonVar($name, false); }
        else                                    { return $res;                                 }
    }



    /**
     * Asigna a una variable un nuevo valor dentro del estado de este canal, a menos que no esté previamente inicializada con un RESET ... CHANNEL,
     * en cuyo caso la asignación se deriva al runtime pasado (a menos que se pase null)
     *
     * @param  string          $name        Nombre de la variable
     * @param  string          $value       Nuevo valor de la variable
     * @param  BotBasicRuntime $runtime     Runtime en el que se asignará el valor de no estar presente previamente en este canal
     */
    public function setCommonVar ($name, $value, $runtime)
    {
        if ($this->isSetVar($name)) {
            $this->vars[$name] = [ true, $value ];
        }
        elseif ($runtime !== null) {
            $runtime->setCommonVar($name, $value, false);
        }
        else {
            Log::register(Log::TYPE_DEBUG, "BBC516 Combinacion invalida de parametros", $this);
        }
    }



    /**
     * Resetea el valor de una variable inicializándola en null o self::NOTHING
     *
     * Inicializarla a null equivale efectivamente a su eliminación; la fijación a NOTHING equivale a incluir la variable dentro del
     * mapa de variables del canal, de modo que cada SET/GET posterior afectará el estado del canal y no el del runtime.
     *
     * @param  string          $name                    Nombre de la variable
     * @param  bool            $deleteNotInitialize     Fija el valor en null en vez de self::NOTHING
     */
    public function resetCommonVar ($name, $deleteNotInitialize)
    {
        if ($deleteNotInitialize) {
            if ($this->isSetVar($name)) { $this->vars[$name] = [ true, null ]; }
        }
        else {
            $this->vars[$name] = [ true, BotBasicRuntime::NOTHING ];
        }
    }



    /**
     * Ejecuta la semántica de un RESET ALL CHANNEL
     *
     * Se resetean las variables y el stack del canal, pero las rutas quedan intactas
     */
    public function resetAllChannelHelper ()
    {
        $names = $this->getAllVarNames();
        foreach ($names as $name) {
            $this->resetCommonVar($name, true);
        }
        $this->callStackReset();
    }



    /**
     * Apila las referencias de un contexto de ejecución de una subrutina del programa BotBasic (que se crea con un GOSUB)
     *
     * @param int               $fromLineno     Número de línea del calling context
     * @param string[]          $args           Valores de los argumentos del GOSUB
     * @param string[]|null     $toVars         Nombres de variables a las que serán asignadas los valores del subsecuente RETURN, o null si no hay
     */
    public function callStackPush ($fromLineno, $args, $toVars)
    {
        if (count($this->callStack) >= BOTBASIC_CALLSTACK_LIMIT) {
            Log::register(Log::TYPE_BBCODE, "BBC605 Stack overflow: se ha agotado la capacidad de la pila de llamadas para GOSUB", $this, $fromLineno);
            return;
        }
        $this->callStack[] = [ $fromLineno, $args === null ? [] : $args, $toVars === null ? [] : $toVars];
        $this->tainting(true);
    }



    /**
     * Desapila un contexto de ejecución de una subrutina del programa BotBasic, a momento de un RETURN
     *
     * @return null|array   Calling context más reciente en forma: [ fromLineno, args, toVars ]; o null si no hay contextos apilados
     */
    public function callStackPop ()
    {
        if (count($this->callStack) == 0) { return null; }
        $last = array_pop($this->callStack);
        $this->tainting(true);
        return $last;
    }



    /**
     * Retorna el tope de la pila de los contextos de ejecución de subrutinas del programa BotBasic
     *
     * @return null|array   Calling context más reciente en forma: [ fromLineno, args, toVars ]; o null si no hay contextos apilados
     */
    public function callStackTop ()
    {
        if (count($this->callStack) == 0) { return null; }
        return $this->callStack[count($this->callStack) - 1];
    }



    /**
     * Limpia la cola del call stack; este método es llamado cuando se encuentra un END en la ejecución del programa BotBasic
     */
    public function callStackReset ()
    {
        if (count($this->callStack) == 0) { return; }
        $this->callStack = [];
        $this->tainting(true);
    }



    /**
     * Encola en este canal una ruta de contexto de operación pendiente proveniente de la ejecución de un INPUT
     *
     * @param string        $bot                Nombre del bot que convoca la operación
     * @param int           $lineno             Número de línea del programa BotBasic que convoca la operación
     * @param string        $dataType           Tipo de dato; uno de: 'date', 'positiveInteger', 'positiveDecimal', 'string'
     * @param string[]|null $titles             Títulos asociados al INPUT, que son desplegados como PRINTs; o null si no hay
     * @param string|null   $word               Palabra que acepta el valor por defecto $fromValue; o null si no aplica
     * @param int           $targetBbcId        ID del BotBasicChannel sobre el que debe efectuarse la afectación de $toVar
     * @param string        $toVars             Nombre de las variables del programa BotBasic en las que se almacenarás los resultados del INPUT
     * @param string|null   $fromValue          Valor por defecto a ser usado en caso de introducción de $word; o null si no aplica
     * @param bool          $opened             Indica si la ruta se encolará como "abierta", en cuyo caso se manifestará una interacción
     * @param bool          $dontEnqueue        Si es false, no se encolará la ruta de contexto de ejecución (para steps 2 si no se ha hecho popRoute())
     * @param bool          $displayRepeatEntry Si es true, se mostrará antes de los demás un texto que indica al usuario que debe repetir la entrada del dato
     */
    public function enqueueRouteInput ($bot, $lineno, $dataType, $titles, $word, $targetBbcId, $toVars, $fromValue, $opened, $dontEnqueue = false, $displayRepeatEntry = false)
    {
        // verificar capacidad de la cola de operaciones pendientes del canal
        if (! $dontEnqueue && count($this->routeQueue) >= BOTBASIC_ROUTEQUEUE_LIMIT) {
            Log::register(Log::TYPE_BBCODE, "BBC672 Se ha agotado la capacidad de la cola de operaciones pendientes del canal", $this, $lineno);
            return;
        }
        // enqueue
        $opened = $this->calcRouteOpenedFlag($opened, $dontEnqueue);
        if (! $dontEnqueue) {
            $data = [ 'input', $bot, $lineno, $dataType, $titles, $word, $targetBbcId, $toVars, $fromValue, $opened ];
            // array_unshift($this->routeQueue, $data);
            $this->routeQueue[] = $data;
            $this->tainting(true);
        }
        // retornar si no debe manifestarse ninguna salida
        if (! $opened) {
            return;
        }
        // optionally show a "repeat entry" text
        $rt = $this->getBBruntime();
        if ($displayRepeatEntry) {
            $msg = BotConfig::botMessage($this->getBBruntime()->getBBbotIdx(), $this->getBBruntime()->getLocale(), BotConfig::MSG_PLEASE_REPEAT_ENTRY);
            $this->rt->splashHelperPrint($msg, $bot, $this->getBBruntime()->getBMuserId(), $this->getId());
        }
        // process titles as prints
        if ($titles !== null) {
            foreach ($titles as $value) {
                $this->rt->splashHelperPrint($value, $bot, $this->getBBruntime()->getBMuserId(), $this->getId());
            }
        }
        // show a helper text according to the dataType
        $prompt = BotConfig::buildInputHelperForDatatype($rt->getLocale(), $dataType, $rt->getBBbotIdx(), ! $displayRepeatEntry);
        if ($prompt !== null) {
            $this->rt->splashHelperPrint($prompt, $bot, $this->getBBruntime()->getBMuserId(), $this->getId());
        }
        // when there is a default value and a word, show the info before prompting for input
        if ($word !== null && $word !== '' && $fromValue !== null) {
            $prompt = BotConfig::buildInputPromptForDefaultValue($rt->getLocale(), $word, $fromValue, $this->getBBruntime()->getBBbotIdx());
            if ($prompt !== null) {
                $this->rt->splashHelperPrint($prompt, $bot, $this->getBBruntime()->getBMuserId(), $this->getId());
            }
        }
    }



    /**
     * Encola en este canal una ruta de contexto de operación pendiente proveniente de la ejecución de un MENU predefinido
     *
     * TODO: AUN NO PROBADO; ELIMINAR FUNCIONALIDAD DE MENUS PREDEFINIDOS PARA EL PRIMER LANZAMIENTO DE BB
     *
     * @param string        $bot            Nombre del bot que convoca la operación
     * @param int           $lineno         Número de línea del programa BotBasic que convoca la operación
     * @param string        $menuName       Nombre del menú predefinido, que se corresponde con un método de BizModelAdapter
     * @param string[]      $menuArgs       Argumentos del menu predefinido
     * @param string[]|null $titles         Títulos asociados al menú; o null si no están definidos
     * @param array         $options        Opciones del menú, codificadas con Interaction::encodeMenuhook()
     * @param array|null    $pager          Especificación del paginador del menú, de forma: [ pagerSpec, pagerArgs ]
     * @param int           $targetBbcId    ID del BotBasicChannel sobre el que debe efectuarse la afectación de $toVar
     * @param string[]      $toVars         Nombre de las variables del programa BotBasic en las que se almacenarán los resultados del INPUT
     * @param bool          $opened         Indica si la ruta se encolará como "abierta", en cuyo caso se manifestará una interacción
     * @param mixed|null    $contextObject  Objeto opcional que sirve de contexto a la rutina de BizModelAdapter
     */
    public function enqueueRoutePredefMenu ($bot, $lineno, $menuName, $menuArgs, $titles, $options, $pager, $targetBbcId, $toVars, $opened, $contextObject = null)
    {
        // verificar capacidad de la cola de operaciones pendientes del canal
        if (count($this->routeQueue) >= BOTBASIC_ROUTEQUEUE_LIMIT) {
            Log::register(Log::TYPE_BBCODE, "BBC879 Se ha agotado la capacidad de la cola de operaciones pendientes del canal", $this, $lineno);
            return;
        }
        // enqueue
        $opened = $this->calcRouteOpenedFlag($opened, false);
        if ($menuArgs === null) { $menuArgs = []; }
        if ($titles   === null) { $titles   = []; }
        $data = [ 'predefmenu', $bot, $lineno, $menuName, $menuArgs, $titles, $options, $pager, $targetBbcId, $toVars, $contextObject, $opened ];
        $this->routeQueue[] = $data;
        $this->tainting(true);
        // retornar si no debe manifestarse ninguna salida
        if (! $opened) {
            return;
        }
        // invoca a la rutina PHP del BMA asociada al menu
        $bbc = BotBasicChannel::load($targetBbcId);
        if ($bbc === null) {
            Log::register(Log::TYPE_DEBUG, "BBC711 El BBC destino $targetBbcId ya fue borrado pues no se puede cargar", $this, $bot, $lineno);
        }
        else {
            $this->getBBruntime()->callMenu($menuName, $menuArgs, $titles, $options, $pager, $lineno, $bot, $bbc, $contextObject);
        }
    }



    /**
     * Encola en este canal una ruta de contexto de operación pendiente proveniente de la ejecución de un MENU estándar
     *
     * @param string        $bot                Nombre del bot que convoca la operación
     * @param int           $lineno             Número de línea del programa BotBasic que convoca la operación
     * @param string[]|null $titles             Títulos asociados al menú; o null si no están definidos
     * @param array         $options            Opciones del menú, codificadas con Interaction::encodeMenuhook()
     * @param array|null    $pager              Especificación del paginador del menú, de forma: [ pagerSpec, pagerArgs ]
     * @param int           $targetBbcId        ID del BotBasicChannel sobre el que debe efectuarse la afectación de $toVar
     * @param string        $toVar              Nombre de la variable del programa BotBasic en la que se almacenará el resultado del INPUT
     * @param bool          $opened             Indica si la ruta se encolará como "abierta", en cuyo caso se manifestará una interacción
     * @param null|string   $pagerAction        Si se ha presionado un botón del paginador, aquí se recibe el valor del $key del menuhook asociado;
     *                                          en este caso se considera que la ruta ya está encolada y se ignoran todos los argumentos anteriores
     * @param null|int      $pagerActionArg     Cuando $pagerAction indica un tag de número de página, aquí se pasa el número de página seleccionada
     */
    public function enqueueRouteStdMenu ($bot, $lineno, $titles, $options, $pager, $targetBbcId, $toVar, $opened, $pagerAction = null, $pagerActionArg = null)
    {
        $limit = function (&$what, $min, $max)
        {
            if     ($what < $min) { $what = $min; }
            elseif ($what > $max) { $what = $max; }
        };

        // verificar capacidad de la cola de operaciones pendientes del canal
        if (count($this->routeQueue) >= BOTBASIC_ROUTEQUEUE_LIMIT) {
            Log::register(Log::TYPE_BBCODE, "BBC734 Se ha agotado la capacidad de la cola de operaciones pendientes del canal", $this, $lineno);
            return;
        }
        // if this call corresponds to a pager action, load all args from the current route
        if ($pagerAction !== null) {
            list ($bot, $lineno, $titles, $options, $pager, $targetBbcId, $toVar) = $this->getRouteContent(0);
        }
        // calc pager settings
        list ($pagerSpec, $pagerArg) = $pager;   // an optional third element could be $lastPagerPos
        $lastUsedStartPos   = ! isset($pager[2]) ? 0 : $pager[2];
        $startPosOfLastPage = $pagerArg === null ? 0 : $pagerArg * floor((count($options) - 1) / $pagerArg);
        if ($pagerSpec !== null && (! is_numeric($pagerArg) || intval($pagerArg) <= 0)) {
            $pagerSpec = null;
        }
        if ($pagerSpec === null) {
            $numOptionsToShow = count($options);
            $limit($numOptionsToShow, 1, Interaction::MENU_MAX_OPTIONS_TO_SHOW);
            $startPos = 0;
        }
        else {
            $numOptionsToShow = intval($pagerArg);
            $limit($numOptionsToShow, 1, Interaction::MENU_MAX_OPTIONS_TO_SHOW);
            if ($pagerAction === null) {
                $startPos = 0;
            }
            else {
                switch ($pagerAction) {
                    case Interaction::TAG_MENU_PAGER_FIRST : $startPos = 0;                                                                                              break;
                    case Interaction::TAG_MENU_PAGER_LAST  : $startPos = $startPosOfLastPage;                                                                            break;
                    case Interaction::TAG_MENU_PAGER_PREV  : $startPos = $lastUsedStartPos == 0 ? 0 : $lastUsedStartPos - $pagerArg;                                     break;
                    case Interaction::TAG_MENU_PAGER_NEXT  : $startPos = $lastUsedStartPos == $startPosOfLastPage ? $startPosOfLastPage : $lastUsedStartPos + $pagerArg; break;
                    case Interaction::TAG_MENU_PAGER_PAGE  :
                        $startPos = ($pagerActionArg - 1) * $numOptionsToShow;
                        $limit($startPos, 0, $startPosOfLastPage);
                        break;
                    default :
                        Log::register(Log::TYPE_DEBUG, "BBC744 pagerAction invalido [$pagerAction]", $this);
                        $startPos = 0;
                }
                // check for redundant pager actions (press on: first/prev when showing first; next/last when showing last; same page shown)
                if ($lastUsedStartPos == $startPos) {
                    return;
                }
            }
        }
        // enqueue or update route
        $routeIsNew = $pagerAction === null;
        $opened     = $this->calcRouteOpenedFlag($opened, ! $routeIsNew);
        if ($pagerAction === null) {
            $pager[2] = $startPos;
            $data = [ 'stdmenu', $bot, $lineno, $titles, $options, $pager, $targetBbcId, $toVar, $opened ];
            $this->routeQueue[] = $data;   // instead of: array_unshift($this->routeQueue, $data);
        }
        else {
            $this->routeQueue[0][5][2] = $startPos;
        }
        $this->tainting(true);
        // retornar si no debe manifestarse ninguna salida
        if (! $opened) {
            return;
        }
        // process titles as prints (all but the last one)
        $lastTitle = null;
        if ($titles === null) { $titles = []; }
        if (count($titles) > 0) {
            for ($i = 0; $i < count($titles) - 1; $i++) {
                $splash = Splash::createWithText($titles[$i]);
                $this->orderEnqueueing($splash);
            }
            $lastTitle = $titles[count($titles) - 1];
        }
        // make menu options; transform to custom keyboard keys
        $codifiedOptions = [];
        for ($pos = $startPos; $pos < $startPos + $numOptionsToShow; $pos++) {
            if (! isset($options[$pos])) { continue; }
            list ($key, $value, $gotoOrGosub, $gotoGosubTargetLineno) = $options[$pos];
            $codifiedOptions[] = Interaction::encodeMenuhook($key, $value, $gotoOrGosub, $gotoGosubTargetLineno, $this, $lineno);
        }
        // build the pager; short: prev, next; long: first, 5x page numbers, last
        if ($pagerSpec !== null) {
            // TODO probar un menu cuyo key sea igual al valor de uno de los dos tags siguientes y ver como se comporta
            $codifiedOptions[] = Interaction::encodeMenuhook(Interaction::TAG_MENU_NEW_ROW     , null, null, null, $this, $lineno, true);   // returns the key (the tag)
            $codifiedOptions[] = Interaction::encodeMenuhook(Interaction::TAG_MENU_PAGER_STARTS, null, null, null, $this, $lineno, true);   // returns the key (the tag)
            if ($pagerSpec == 'pagerLong' ) {
                $charTip = json_decode('"\u00B7"'); $charFirst = json_decode('"\u25C1"'); $charLast = json_decode('"\u25B7"');
                $codifiedOptions[] = Interaction::encodeMenuhook(Interaction::TAG_MENU_PAGER_FIRST, $charFirst, null, null, $this, $lineno, true);
                $lastPage = $startPosOfLastPage / $numOptionsToShow + 1;   // $firstPage==1
                $curPage  = $startPos           / $numOptionsToShow + 1;
                $limit($curPage, 1, $lastPage);
                if     ($curPage <= 2            ) { $fromPage = 1;             $toPage = 5;            }
                elseif ($curPage >= $lastPage - 1) { $fromPage = $lastPage - 4; $toPage = $lastPage;    }
                else                               { $fromPage = $curPage  - 2; $toPage = $curPage + 2; }
                $limit($fromPage, 1, $lastPage);
                $limit($toPage,   1, $lastPage);
                for ($page = $fromPage; $page <= $toPage; $page++) {
                    if ($page != $curPage) { $label = $page;                       }
                    else                   { $label = $charTip . $page . $charTip; }
                    $codifiedOptions[] = Interaction::encodeMenuhook(Interaction::TAG_MENU_PAGER_PAGE, $label, null, null, $this, $lineno, true, $page);
                }
                $codifiedOptions[] = Interaction::encodeMenuhook(Interaction::TAG_MENU_PAGER_LAST, $charLast, null, null, $this, $lineno, true);
            }
            elseif ($pagerSpec == 'pagerShort') {
                // $charPrev = json_decode('"\u23EA"'); $charNext = json_decode('"\u23E9"');
                $charPrev = json_decode('"\u25C1\u25C1"'); $charNext = json_decode('"\u25B7\u25B7"');
                if ($startPos > 0                  ) { $codifiedOptions[] = Interaction::encodeMenuhook(Interaction::TAG_MENU_PAGER_PREV, $charPrev, null, null, $this, $lineno, true); }
                if ($startPos < $startPosOfLastPage) { $codifiedOptions[] = Interaction::encodeMenuhook(Interaction::TAG_MENU_PAGER_NEXT, $charNext, null, null, $this, $lineno, true); }
            }
            else {
                Log::register(Log::TYPE_RUNTIME, "BBC782 Tipo de paginador de menu $pagerSpec no implementado", $this);
            }
        }
        // register menu options in DB (create menuhooks there) and submit the splash
        Interaction::registerEncodedMenuhooks($codifiedOptions);
        $splash = Splash::createWithMenu($codifiedOptions, $lastTitle);
        $this->orderEnqueueing($splash);
    }



    /**
     * Actualiza en este canal la ruta de contexto de operación pendiente prioritaria a partir de la activación de una opción de un paginador
     * de un MENU estándar
     *
     * @param string    $pagerAction        Una de las constantes Interaction::TAG_MENU_{FIRST,LAST,PREV,NEXT,PAGE}
     * @param int|null  $pagerActionArg     Número de página a mostrar, cuando la acción es TAG_MENU_PAGE
     */
    public function updateRouteStdMenu ($pagerAction, $pagerActionArg = null)
    {
        $this->enqueueRouteStdMenu(null, null, null, null, null, null, null, true, $pagerAction, $pagerActionArg);
    }



    /**
     * Calcula el parámetro que indica si la ruta de contexto de operación pendiente debe estar abierta (mostrar salida a chatapp al ordenarla)
     *
     * @param  bool     $openedAsShouldBe           El indicador deseado, a ser modificado (o no) por este cálculo
     * @param  bool     $routeIsAlreadyEnqueued     Indica si la ruta ya ha sido encolada en el canal
     * @return bool                                 Indicador calculado
     */
    private function calcRouteOpenedFlag ($openedAsShouldBe, $routeIsAlreadyEnqueued)
    {
        if ($openedAsShouldBe === false) { return false; }
        return count($this->routeQueue) == ($routeIsAlreadyEnqueued ? 1 : 0) ? true : false;
    }



    /**
     * Obtiene índices que representan a todos las rutas de contexto de operación pendiente de esta instancia
     *
     * @return int[]
     */
    public function getRouteQueueIndexes ()
    {
        return array_keys($this->routeQueue);
    }



    /**
     * Fija el atributo opened/closed de una ruta de contexto de operación pendiente de esta instancia
     *
     * @param bool      $opened         Atributo de ruta abierta
     * @param int|null  $routeIndex     Indice dentro del rango obtenido por getRouteQueueIndexes(); o null para la ruta más recientemente encolada
     */
    public function setRouteOpenedFlag ($opened, $routeIndex = null)
    {
        if ($routeIndex === null) { $routeIndex = count($this->routeQueue) - 1; }
        if (! isset($this->routeQueue[$routeIndex])) {
            Log::register(Log::TYPE_DEBUG, "BBC955 Route de indice inexistente ($routeIndex)", $this);
            return;
        }
        $this->routeQueue[$routeIndex][ count($this->routeQueue[$routeIndex])-1 ] = $opened;
        $this->tainting(true);
    }



    /**
     * Obtiene el atributo opened/closed de una ruta de contexto de operación pendiente de esta instancia
     *
     * @param  int|null     $routeIndex     Indice dentro del rango obtenido por getRouteQueueIndexes(); o null para la ruta más recientemente encolada
     * @return bool|null                    Atributo de ruta abierta; o null si el índice es inválido
     */
    public function getRouteOpenedFlag ($routeIndex = null)
    {
        if ($routeIndex === null) { $routeIndex = count($this->routeQueue) - 1; }
        if (! isset($this->routeQueue[$routeIndex])) {
            //Log::register(Log::TYPE_DEBUG, "BBC974 Route de indice inexistente ($routeIndex)", $this);
            return null;
        }
        return $this->routeQueue[$routeIndex][ count($this->routeQueue[$routeIndex])-1 ];
    }



    /**
     * Fija el lineno de una ruta de contexto de operación pendiente de esta instancia
     *
     * @param int       $lineno         Número de línea del programa BotBasic
     * @param int|null  $routeIndex     Indice dentro del rango obtenido por getRouteQueueIndexes(); o null para la ruta más recientemente encolada
     */
    public function setRouteLineno ($lineno, $routeIndex = null)
    {
        if ($routeIndex === null) { $routeIndex = count($this->routeQueue) - 1; }
        if (! isset($this->routeQueue[$routeIndex])) {
            Log::register(Log::TYPE_DEBUG, "BBC742 Route de indice inexistente ($routeIndex)", $this, $lineno);
            return;
        }
        $this->routeQueue[$routeIndex][2] = $lineno;
        $this->tainting(true);
    }



    /**
     * Obtiene el tipo de una ruta de contexto de operación pendiente de esta instancia
     *
     * @param  int|null     $routeIndex     Indice dentro del rango obtenido por getRouteQueueIndexes(); o null para la ruta más recientemente encolada
     * @return string|null                  Uno de: 'input', 'stdmenu', 'predefmenu'; o null si no hay ruta asociada al índice o no hay ninguna ruta
     */
    public function getRouteType ($routeIndex = null)
    {
        if ($routeIndex === null) { $routeIndex = count($this->routeQueue) - 1; }
        if (! isset($this->routeQueue[$routeIndex])) {
            //Log::register(Log::TYPE_DEBUG, "BBC761 routeIndex no valido", $this);
            //return null;
            return 'default';
        }
        return $this->routeQueue[$routeIndex][0];
    }



    /**
     * Obtiene el contenido de una ruta de contexto de operación pendiente de esta instancia
     *
     * @param  int|null     $routeIndex     Indice dentro del rango obtenido por getRouteQueueIndexes(); o null para la ruta más recientemente encolada
     * @return string|null                  Según el tipo de ruta es:
     *                                      input:      [ bot, lineno, dataType, title, word, targetBbcId, toVar, fromValue ],
     *                                      stdmenu:    [ bot, lineno, options, pager, targetBbcId, toVar ],
     *                                      predefmenu: [ bot, lineno, menuName, options, pager, targetBbcId, toVars, contextObject ];
     *                                      o null si no hay ruta asociada al índice o no hay ninguna ruta
     */
    public function getRouteContent ($routeIndex = null)
    {
        if ($routeIndex === null) { $routeIndex = count($this->routeQueue) - 1; }
        if (! isset($this->routeQueue[$routeIndex])) {
            //Log::register(Log::TYPE_DEBUG, "BBC783 routeIndex no valido", $this);
            return null;
        }
        return array_slice($this->routeQueue[$routeIndex], 1);
    }



    /**
     * Indica si una ruta de contexto de ejecución es foránea (ON sobre un BotBasicChannel distinto al de quien remitió la ruta) o no
     *
     * @param  BotBasicChannel  $bbc            Si no se pasa la ruta, se extraerá de la instancia de esta clase pasada
     * @param  null|int         $routeIndex     Indice de la ruta en el rango de getRouteQueueIndexes(); o null para la ruta más recientemente encolada
     * @param  null|array       $fullRoute      Ruta, en su representación completa: array_merge( [ getRouteType() ], getRouteContent() );
     *                                          si se pasa null se tomará la ruta más recientemente encolada
     * @return bool|null                        null si los primeros dos argumentos son null; indicador de si la ruta es foránea si no
     */
    static public function isForeignRoute ($bbc, $routeIndex = null, $fullRoute = null)
    {
        if ($bbc === null) {
            Log::register(Log::TYPE_DEBUG, "BBC800 argumento BBC no puede ser null");
            return null;
        }
        if ($fullRoute === null) {
            if ($routeIndex === null) { $routeIndex = count($bbc->routeQueue) - 1; }
            $fullRoute = $bbc->routeQueue[$routeIndex];
        }
        switch ($fullRoute[0]) {
            case 'input'      : $bbcId = $fullRoute[6]; break;
            case 'stdmenu'    : $bbcId = $fullRoute[6]; break;
            case 'predefmenu' : $bbcId = $fullRoute[7]; break;
            default           : $bbcId = null;   // can't happen
        }
        return ! ($bbcId === null || $bbcId == $bbc->getId());
    }



    /**
     * Obtiene el lineno de una ruta de contexto de ejecución
     *
     * @param  array    $fullRoute      Ruta, en su representación indicada en isForeignRoute()
     * @return null                     Lineno asociado a la ejecución del encolado
     */
    static public function getRouteLineno ($fullRoute)
    {
        return $fullRoute[0] == 'default' ? null : $fullRoute[2];
    }



    /**
     * Elimina la ruta de contexto de operación que tenga mayor antiguedad en la cola de rutas
     */
    public function popRoute ()
    {
        array_shift($this->routeQueue);
        $this->tainting(true);
    }



    /**
     * Ordena al runtime la ejecución del programa BotBasic a partir de un Update genérico, como respuesta a un mensaje similar desde ChatMediumChannel
     *
     * @param  Update   $update         Insumo para la ejecución
     */
    public function orderExecution ($update)
    {
        $this->rt->execute($this, $update);
    }



    /**
     * Si en la cola de rutas de contextos de ejecución la primera de ellas está cerrada, la abre e inicia su ejecución
     */
    public function followRoutes ()
    {
        $opened = $this->getRouteOpenedFlag(0);
        if ($opened === null || $opened === true) { return; }   // retorna si no hay una ruta que abrir
        $this->setRouteOpenedFlag(true, 0);
        $this->getBBruntime()->execute($this, null);
    }



    /**
     * Envía un Splash a los túneles de este canal del programa BotBasic
     *
     * @param  Update   $update         Update a ser desplegado a través de los túneles, luego de convertido en Splash
     */
    public function sendToTunnels ($update)
    {
        if ($this->deleted) { return; }
        // splash clones will be created and associated to new BBCs; included (optional) resource will be common to all
        foreach ($this->tunnels as $resourceType => $tgtBbbcs) {
            if (! $update->hasResource($resourceType)) { continue; }
            $firstTime = true;
            $splash    = null;                 /** @var Splash $splash          */   // IDE spoof
            foreach ($tgtBbbcs as $tgtBbc) {   /** @var BotBasicChannel $tgtBbc */
                $cmType     = $tgtBbc->getCMchannel()->getCMtype();
                $cmAuthInfo = $tgtBbc->getCMchannel()->getCMbotName();
                if ($firstTime) { $theSplash = $splash = $update->convertToSplash($cmType, $cmAuthInfo); $firstTime = false; }
                else            { $theSplash = $splash->createByCloning($cmType, $cmAuthInfo);                               }
                $tgtBbc->orderEnqueueing($theSplash);
            }
        }
    }



    /**
     * Ordena el encolado de una salida hacia la chatapp generada por el programa BotBasic
     *
     * @param  Splash   $splash     Salida; no se enviará a los túneles sino directamente a la instancia asociada de ChatMediumChannel
     */
    public function orderEnqueueing ($splash)
    {
        if ($this->deleted) { return; }
        $splash->setBizIntelligencyInfo($this->id, $this->rt->getBizModelUserId());
        $splash->save();
        $cmc = $this->getCMchannel();
        $cmc->enqueue($splash);
    }



    /**
     * Una vez encoladas todas las salidas, la invocación a este método dispara su envío efectivo hacia las chatapps a través de un mecanismo
     * asíncrono que evita los tiempos de espera en el acceso a los web services de las chatapps
     */
    public function orderRendering ()
    {
        $cmc = $this->getCMchannel();
        if ($cmc !== null) { $cmc->render(); }
    }



    /**
     * Implementa un store (get) disponible para el BizModelAdapter con valores asociados al BotBasicChannel o a todos los BotBasicChannels en general
     *
     * @param  string       $name           Nombre de la variable de BotBasic
     * @param  bool         $bbChannelId    ID del canal BotBasic que define el store del cual obtener el valor, o false para el store general
     * @return mixed|null                   Valor recuperado, o null si no existe el nombre indicado
     */
    public function getForBizModelAdapter ($name, $bbChannelId = false)   // pass false for global context, true for current bbchannel context, or any bbc id for that context
    {
        $empty = [];
        if ($bbChannelId === true)  { $bbChannelId = $this->id;                                                                                                 }
        if ($bbChannelId === false) { $store =& self::$storeForBMAs[-1];                                                                                        }
        else                        { if (isset(self::$storeForBMAs[$bbChannelId])) { $store =& self::$storeForBMAs[$bbChannelId]; } else { $store =& $empty; } }
        return isset($store[$name]) ? $store[$name] : null;
    }



    /**
     * Implementa un store (set) disponible para el BizModelAdapter con valores asociados al BotBasicChannel o a todos los BotBasicChannels en general
     *
     * @param  string       $name           Nombre de la variable de BotBasic
     * @param  mixed        $value          Valor de la variable
     * @param  bool         $bbChannelId    ID del canal BotBasic que define el store en el cual asignar el valor, o false para el store general
     */
    public function setForBizModelAdapter ($name, $value, $bbChannelId = false)   // idem
    {
        $empty = [];
        if ($bbChannelId === true) { $bbChannelId = $this->id; }
        if ($bbChannelId === null) {
            Log::register(Log::TYPE_BBCODE, "BBC936 BBCID dice ser null y puede que venga asi de BizModelAdapter", $this);
            return;
        }
        if ($bbChannelId === false) { $store =& self::$storeForBMAs[-1];                                                                                        }
        else                        { if (isset(self::$storeForBMAs[$bbChannelId])) { $store =& self::$storeForBMAs[$bbChannelId]; } else { $store =& $empty; } }
        $store[$name] = $value;
    }



    /**
     * Guarda la instancia en BD
     *
     * @param  bool         $saveTunnels        Indica si adicionalmente se deben guardar los túneles en la BD
     * @return bool|null                        null en caso de error de BD; true en caso de éxito
     */
    public function save ($saveTunnels = true)
    {
        if (! ($this->tainting() || $this->id === null || $this->deleted)) { return true; }
        $res = DBbroker::writeBotBasicChannel($this);
        if ($res === null) {
            Log::register(Log::TYPE_DATABASE, "BBC957 Error de BD", $this);
            return null;
        }
        elseif ($res === false) {
            Log::register(Log::TYPE_RUNTIME, "BBC961 Falla la guardada de BBC por ID inexistente", $this);
            return null;
        }
        elseif (is_int($res)) { $this->id = $res; }
        $toUpdate = [];
        foreach ($this->vars as $name => $varData) {
            list ($tainted, $value) = $varData;
            if (! $tainted) { continue; }
            $toUpdate[] = [ $name, $value ];
        }
        $res = DBbroker::updateVars(null, $this, $toUpdate);
        if ($res === null) {
            Log::register(Log::TYPE_DATABASE, "BBC970 Error de BD", $this);
        }
        if ($saveTunnels) {
            $res = DBbroker::writeBotBasicTunnels($this);
            if ($res === null) {
                Log::register(Log::TYPE_DATABASE, "BBC286 Error de BD", $this);
                return null;
            }
        }
        $this->tainting(false);
        return true;
    }



    public function close ($cascade)
    {
        $this->save();
        if ($cascade) {
            $this->getCMchannel()->close(true);
        }
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
