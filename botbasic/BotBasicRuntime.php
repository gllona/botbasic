<?php
/**
 * Runtime (entorno de ejecución) de BotBasic
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;

include_once "bbautoloader.php";



/**
 * Clase BotBasicRuntime
 *
 * Clase principal del runtime (entorno de ejecución) de un programa BotBasic, la cual está asociada a varios BotBasicChannels y a su vez,
 * cada uno de estos, a varios ChatMediumChannels, de los cuales sólo uno (el último activo según ChatMedium) está cargado en memoria a la vez.
 *
 * @package botbasic
 */
class BotBasicRuntime extends BotBasic implements Initializable, Closable
{



    //////////////////////////////
    // DEFINITIONS AND CONSTRUCTOR
    //////////////////////////////



    // "state" attributes (see defaults in ::create())

    /** @var null|string Locale actual de la ejecución del programa BotBasic; forma parte del estado y se guarda en BD */
    private $locale                 = null;

    /** @var null|array Mapa de variables de BotBasic asociadas al runtime y que no han pasado por un RESET ... CHANNEL, como:
                        [ name => [ bool-tainting, value-or-nullForDeletion ], ... ] */
    private $vars                   = null;

    /** @var null|string Palabra "mágica" que permite aceptar un valor por defecto en un INPUT; forma parte del estado y se guarda en BD */
    private $word                   = null;

    /** @var null|string Indicador de si se ha activado/desactivado el trace de BotBasic con TRACE/NOTRACE; forma parte del estado y se guarda en BD */
    private $trace                  = null;

    /** @var bool Indica si la instancia se ha terminado o no de construir adecuadamente */
    private $built                  = false;

    /** @var bool Indica si el runtime está marcado para borrado */
    private $deleted                = false;

    // heap attributes

    /** @var null|array Canal target para los siguientes Splashes generados con PRINT, INPUT y MENU; puede fijarse también con ON; está expresado como:
     *                  [ bbBotName, bizModelUserId, bbChannelId ] */
    private $on                     = null;

    /** @var null|array Secuencia de textos que se deben convertir en Splashes cuando se ejecute submitRendering() */
    private $prints                 = [];

    /** @var null|array Secuencia de MENUs que se deben convertir en Splashes cuando se ejecute submitRendering().
     *                  La multiplicidad es mayor que uno porque los targets pueden ser otros BotBasicChannels */
    private $menus                  = [];

    /** @var null|array Secuencia de INPUTs que se deben convertir en Splashes cuando se ejecute submitRendering().
     *                  La multiplicidad es mayor que uno porque los targets pueden ser otros BotBasicChannels */
    private $inputs                 = [];

    /** @var null|array Títulos preacumulados para MENU e INPUT a través de la directiva TITLE */
    private $menuAndInputTitles     = null;

    /** @var null|array Opciones preacumuladas para MENU a través de las directivas OPTION y OPTIONS */
    private $menuOptions            = null;

    /** @var null|array Definición del paginador del menú, según la directiva PAGER, como:
                        [ pagerSpec, pagerArg ] */
    private $menuPager              = null;

    /** @var null|bool Indica si la última operación DATA GET no consiguió un valor con la clave especificada en la BD */
    private $dataGetEmpty           = null;

    /** @var null|int Contiene el código de error que ha generado la última instrucción que puede afectar este valor; actualmente:
     *                DISPLAY, BLOAD, BSAVE, EXTRACT.
     *                Siendo un valor no persistente, la detención de la ejecución (INPUT, MENU, END fuera de hook, END en hook con ABORT)
     *                tiene el efecto de resetear este valor a cero (código de "no hay error") */
    private $lastErr                = 0;

    /** @var bool Indica si se ha ejecutado un ABORT (para evitar la ejecución del código BotBasic de un hook posterior) */
    private $aborted                = false;

    // references

    /** @var BizModelAdapter Implementa la relación */
    private $bmAdapter              = null;

    /** @var Update Contenido de la interacción proveniente de la chatapp; debe ser fijada antes de llamar a route() */
    private $update                 = null;

    // "runtime signature" attributes

    /** @var null|int ID del runtime, según la BD, o null para runtimes nuevos */
    private $id                     = null;

    /** @var null|int Cada bot de un programa en BotBasic tiene un ID expresado como un índice de un mapa en BotConfig */
    private $bbBotIdx               = null;

    /** @var null|string Versión mayor del programa de BotBasic que se ejecuta en este runtime; puede actualizarse automáticamente */
    private $bbCodeMajorVersion     = null;

    /** @var null|string Versión menor del programa de BotBasic que se ejecuta en este runtime; puede actualizarse automáticamente */
    private $bbCodeMinorVersion     = null;

    /** @var null|string Versión submenor del programa de BotBasic que se ejecuta en este runtime; se actualiza automáticamente */
    private $bbCodeSubminorVersion  = null;

    /** @var bool Se asigna a true al actualizar el código de la BB app (sólo major update) y permite así evitar
     *            una actualización redundante posterior al llegar un /start (ej. caso de múltiples RESET ALL en un mismo request) */
    private $bbCodeUpdatedToLastest = false;

    /** @var null|int ID del "usuario del modelo de negocio" al cual está asociado este runtime, o null si no hay asociación previa;
     *                las directivas CHANNEL no tienen efecto si este atributo es null */
    private $bmUserId               = null;

    /** @var array Buffer de lectura/escritura del dataHelper, usado para minimizar el acceso a BD */
    private $dataHelperBuffer       = [];

    /** @var BotBasicRuntime[] Store para todas las instancias de esta clase */
    static private $store           = [];

    /** @var bool Indica el estado usado en tainting() de la interfaz Closable */
    private $taintingState          = false;



    /**
     * Retorna la instancia de BizModelAdapter asociada; si no está creada intenta su creación antes de retornarla
     *
     * @return BizModelAdapter|null     Instancia; o null en caso de error
     */
    private function bma ()
    {
        if ($this->bmAdapter === null) {
            $bmp = BizModelProvider::create($this->getCurrentBBchannel());
            if ($bmp === null) {
                Log::register(Log::TYPE_RUNTIME, "RT174 no se puede crear BizModelProvider", $this);
            }
            $bma = BizModelAdapter::create($bmp);
            if (! is_subclass_of($bma, '\botbasic\BizModelAdapterTemplate')) {
                Log::register(Log::TYPE_RUNTIME, "RT151 BizModelAdapter no es subclase de BizModelAdapterTemplate", $this);
            }
            if ($bmp === null || $bma === null) { return null; }
            $this->bmAdapter = $bma;
        }
        return $this->bmAdapter;
    }



    public function getId ()
    {
        return $this->id;
    }



    /**
     * Obtiene el locale asociado a este runtime
     *
     * @return null|string
     */
    public function getLocale ()
    {
        return $this->locale;
    }



    /**
     * Obtiene el contenido de la última directiva WORD si no ha sido reseteada por otra directiva
     *
     * @return null|string
     */
    public function getWord ()
    {
        return $this->word;
    }



    /**
     * Obtiene el indicador fijado con TRACE y NOTRACE
     *
     * @return null|string
     */
    public function getTrace ()
    {
        return $this->trace;
    }



    /**
     * Obtiene el ID del BizModel user, si está asignado
     *
     * @return int|null
     */
    public function getBMuserId ()
    {
        return $this->bmUserId;
    }



    /**
     * Obtiene la estructura que almacena las variables del programa BotBasic asociadas específicamente al runtime y no a un BotBasicChannel
     *
     * @return array|null
     */
    public function getVars ()
    {
        return $this->vars;
    }



    /**
     * Obtiene el bbBotIdx del programa BotBasic, que es un índice común en estructuras de datos de ChatMedium y sus subclases
     *
     * @return int|null
     */
    public function getBBbotIdx ()
    {
        return $this->bbBotIdx;
    }



    /**
     * Obtiene la versión mayor del programa BotBasic asociado a esta instancia
     *
     * @return null|string
     */
    public function getBBcodeMajorVersion ()
    {
        return $this->bbCodeMajorVersion;
    }



    /**
     * Obtiene la versión menor del programa BotBasic asociado a esta instancia
     *
     * @return null|string
     */
    public function getBBcodeMinorVersion ()
    {
        return $this->bbCodeMinorVersion;
    }



    /**
     * Obtiene la versión submenor del programa BotBasic asociado a esta instancia
     *
     * @return null|string
     */
    public function getBBcodeSubminorVersion ()
    {
        return $this->bbCodeSubminorVersion;
    }



    /**
     * Obtiene el codename del programa BotBasic asociado a esta instancia
     *
     * @return string
     */
    public function getBBcodename ()
    {
        return ChatMedium::getBBcodename($this->bbBotIdx);
    }



    /**
     * Obtiene la última versión autorizada del programa BotBasic asociado a esta instancia
     *
     * @return string
     */
    private function getBBlastestAllowedCodeMajorVersion ()
    {
        return ChatMedium::getBBlastestAllowedCodeMajorVersion($this->bbBotIdx);
    }



    /**
     * Obtiene el nombre de bot asociado a esta instancia del runtime
     *
     * @return string
     */
    public function getBBbotName ()
    {
        return ChatMedium::getBBbotName($this->bbBotIdx);
    }



    /**
     * Obtiene los canales del programa BotBasic asociados a este runtime, a partir de los que estén en el store de BotBasicChannel
     *
     * @return BotBasicChannel[]
     */
    private function getBBchannels ()
    {
        return BotBasicChannel::getFromStoreByRuntimeId($this->id);
    }



    /**
     * Obtiene los canales del programa BotBasic a semezanja de getBBchannels(), pero cada uno indexado por su ID
     *
     * @return array
     */
    private function getBBchannelsIndexed ()
    {
        $res = [];
        foreach ($this->getBBchannels() as $bbc) { $res[$bbc->getId()] = $bbc; }
        return $res;
    }



    /**
     * Obtiene la instancia "actual" de BotBasicChannel asociada a este runtime, es decir, aquella por la que se creó el runtime
     *
     * @return BotBasicChannel
     */
    public function getCurrentBBchannel ()
    {
        $bbcs = $this->getBBchannels();
        return count($bbcs) == 0 ? null : array_values($bbcs)[0];
    }



    /**
     * Indica si el runtime ha sido marcado para borrado en BD
     *
     * @return bool
     */
    public function isDeleted ()
    {
        return $this->deleted;
    }



    /**
     * Fija la marca de borrado en BD para el runtime
     */
    public function setAsDeleted ()
    {
        $this->deleted = true; $this->tainting(true);
    }



    protected function __construct ($bbCodeId, $bbCodeVersion = null)
    {
        parent::__construct();
        $this->built    = false;
        $this->bbBotIdx = $bbCodeId;
        $bbCodeName = $this->getBBcodename();
        // if no BB code version was passed, infer it as the lastest allowed
        if ($bbCodeVersion === null) {
            $bbCodeVersion = DBbroker::readLastBBCodeVersionForCodename($bbCodeName, $this->getBBlastestAllowedCodeMajorVersion());
            if ($bbCodeVersion === null)  {
                Log::register(Log::TYPE_DATABASE, "RT333 Error BD y se cancela la creación del runtime");
                return;
            }
            if ($bbCodeVersion === false) {
                Log::register(Log::TYPE_RUNTIME, "RT337 No se puede leer la version del codigo BB para el codename $bbCodeName y se cancela la creación del runtime");
                return;
            }
            list ($bbMajorCodeversion, $bbMinorCodeversion, $bbSubminorCodeversion) = $bbCodeVersion;
        }
        else {
            $bbMajorCodeversion    = self::getMajorCodeVersionFor(   $bbCodeVersion);
            $bbMinorCodeversion    = self::getMinorCodeVersionFor(   $bbCodeVersion);
            $bbSubminorCodeversion = self::getSubminorCodeVersionFor($bbCodeVersion);
        }
        // load the BB code
        $program = DBbroker::readBBcode($bbCodeName, $bbMajorCodeversion, $bbMinorCodeversion);
        if ($program === null)  {
            Log::register(Log::TYPE_DATABASE, "RT346 Error BD y se cancela la creación del runtime");
            return;
        }
        if ($program === false) {
            Log::register(Log::TYPE_RUNTIME, "RT350 No se puede leer el codigo BB para ($bbCodeName [$bbCodeVersion] [$bbMajorCodeversion.$bbMinorCodeversion.$bbSubminorCodeversion]) y se cancela la creación del runtime");
            return;
        }
        // fill fields
        list (, $bbVersion, , , , , $this->messages, $this->predefmenus, $this->magicvars, $this->primitives, $this->bots) = $program;
        $this->bbCodeMajorVersion    = $bbMajorCodeversion;
        $this->bbCodeMinorVersion    = $bbMinorCodeversion;
        $this->bbCodeSubminorVersion = $bbSubminorCodeversion;
        // a security check
        if ($bbVersion > BOTBASIC_LANG_VERSION) {
            Log::register(Log::TYPE_RUNTIME, "RT360 El codigo del programa BB leido requiere un interprete BB de version superior ($bbVersion) y se cancela la creación del runtime");
            return;
        }
        // flag as ok and store
        $this->built   = true;
        self::$store[] = $this;
    }



    public function getDefauls ()
    {
        return [ 'runtime', [
            'bbcode_cmid'           => -1,
            'code_major_version'    => '0',
            'code_minor_version'    => '0',
            'code_subminor_version' => '0',
            'locale'                => 'es',
            'word'                  => '',
            'trace'                 => 0,
            'bizmodel_user_id'      => -1,
        ] ];
    }



    /**
     * Factory method
     *
     * @param  int                  $bbCodeId           Código "bbCodeId" del programa BotBasic tal como está definido en ChatMedium y sus subclases
     * @param  ChatMediumChannel    $cmChannel          Tripleta identificadora de la fuente de la interacción entrante (Update)
     * @param  bool                 $createBBCandCMC    Indica si se debe crear el BotBasicChannel y el ChatMediumChannel asociados
     * @return BotBasicRuntime|null                     Instancia creada, o null en caso de error
     */
    static public function create ($bbCodeId, $cmChannel, $createBBCandCMC = false)
    {
        // verificar correctitud de BizModelAdapter (la existencia de metodos mv_, pr_ y mn_ fue verificado cuando se compilo el codigo de BB)
        if (! is_subclass_of('\botbasic\BizModelAdapter', '\botbasic\BizModelAdapterTemplate')) {
            Log::register(Log::TYPE_RUNTIME, "RT397 La clase BizModelAdapter debe extender a BizModelAdapterTemplate y se cancela la creación del runtime");
            return null;
        }
        // if it's a new CMC triplet, assign default values
        $rtId = DBbroker::readBBruntimeIdByCMC($cmChannel);
        if ($rtId === null) { return null; }
        if ($rtId === false) {
            $rt = new BotBasicRuntime($bbCodeId);
            if (! $rt->built) { return null; }
            $rt->locale    = BOTBASIC_DEFAULT_LOCALE;
            $rt->word      = null;
            $rt->trace     = false;
            $rt->bmUserId  = null;
            $rt->vars      = [];
            // assign new ID   // TODO si la creacion de un RT queda inconclusa, la siguiente instruccion deja en BD un RT "corrupto"; hay que "borrarlo"
            $rtId = DBbroker::writeBBruntime($rt);   // so no need to call DBbroker::makeId()
            if ($rtId === null) { return null; }
            $rt->id = $rtId;
            // optionally create a BBC associated to the new RT (CMC will be associated automatically when get from the CMC store)
            if ($createBBCandCMC) {
                $bbc = BotBasicChannel::createFromBBRT($rt, $cmChannel->getCMtype(), $cmChannel->getCMuserId(), $cmChannel->getCMbotName());
                if ($bbc === null) { $rt->built = false; $rt->close(false); return null; }
            }
            // finalize building
            $rt->initRunStructs();
        }
        // if it's an existing runtime associated to the CMC triplet, load it
        else {
            $rt = self::loadById($rtId);
            if ($rt === null) { return null; }
            // optionally create a BBC associated to the new RT (CMC will be associated automatically when get from the CMC store)
            if ($createBBCandCMC) {
                $bbc = BotBasicChannel::createFromBBRT($rt, $cmChannel->getCMtype(), $cmChannel->getCMuserId(), $cmChannel->getCMbotName());
                if ($bbc === null) { return null; }
            }
        }
        // try to upgrade the BBcode
        $rt->updateBBcode();
        // ready
        return $rt;
    }



    /**
     * Carga una instancia de esta clase según su ID en BD
     *
     * @param  int                      $runtimeId                  ID del runtime en BD
     * @param  bool                     $loadDefaultBBCandCMC       Indica si se debe cargar el BotBasicChannel por defecto y el respectivo ChatMediumChannel
     * @param  BotBasicChannel|null     $updateThisBbcReference     Si no es null, es un BotBasicChannel al cual se debe actualizar la referencia al Runtime
     *                                                              antes de intentar actualizar la versión del código de BotBasic
     * @return BotBasicRuntime|null                                 Instancia cargada; o null en caso de errores
     */
    static public function loadById ($runtimeId, $loadDefaultBBCandCMC = false, $updateThisBbcReference = null)
    {
        // check if in store
        foreach (self::$store as $rt) {
            if ($rt->id == $runtimeId) { return $rt; }
        }
        // if not, read from DB
        $data = DBbroker::readBBruntime($runtimeId);
        if ($data === null) {
            Log::register(Log::TYPE_DATABASE, "RT450 Error de BD y se cancela la creación del runtime");
            return null;
        }
        elseif ($data === false) {
            Log::register(Log::TYPE_RUNTIME, "RT454 No se puede cargar el runtime por ID ($runtimeId) y se cancela la creación del runtime");
            return null;
        }
        list ($bbCodeId, $bbCodeMajorVersion, $bbCodeMinorVersion, $bbCodeSubminorVersion, $locale, $word, $trace, $bmUserId) = $data;
        if (! is_numeric($bbCodeId) || ! is_numeric($bbCodeMajorVersion) || ! is_numeric($bbCodeMinorVersion) || $bbCodeSubminorVersion === null || $bbCodeSubminorVersion == '') {
            Log::register(Log::TYPE_DATABASE, "RT492 Estructura de datos corrompida en DB para rtId $runtimeId");
            return null;
        }
        // create the object
        $rt = new BotBasicRuntime($bbCodeId, "$bbCodeMajorVersion.$bbCodeMinorVersion.$bbCodeSubminorVersion");
        if (! $rt->built) { return null; }
        $rt->locale    = $locale;
        $rt->word      = $word;
        $rt->trace     = $trace;
        $rt->bmUserId  = $bmUserId;
        $rt->id        = $runtimeId;
        $rt->vars      = [];
        $vars          = DBbroker::readVars($rt, null);
        if ($vars === null) {
            Log::register(Log::TYPE_DATABASE, "RT468 Error de BD y se cancela la creación del runtime");
            return null;
        }
        foreach ($vars as $varData) {
            list ($name, $value) = $varData;
            $rt->vars[$name] = [ false, $value ];
        }
        // if should update passed BBC's RT reference...
        if ($updateThisBbcReference !== null) {
            $updateThisBbcReference->setRuntime($rt);
        }
        // if should load (default) BBC and CMC...
        if ($loadDefaultBBCandCMC) {
            $cmBots = ChatMedium::getDefaultCMbots();   // [ cmType, cmBotName ][]
            $data   = DBbroker::readCMuserIdForLastUsedCMchannel($cmBots, $runtimeId);
            if ($data === null) {
                Log::register(Log::TYPE_DATABASE, "RT480 Error de BD y se cancela la creación del runtime");
                return null;
            }
            if ($data === false) {
                Log::register(Log::TYPE_RUNTIME, "RT484 No se puede leer la info del ultimo CMC usado por el runtime $runtimeId y se cancela la creación del runtime");
                return null;
            }
            list ($cmType, $cmBotName, $cmUserId, $cmcIsDeleted, $bbcId) = $data;
            if (! $cmcIsDeleted) {   // normal case
                $bbc = BotBasicChannel::createFromBBRT($rt, $cmType, $cmUserId, $cmBotName);
            }
            else {   // there is no non-deleted CMC associated to the runtime for defaults cm-bots, so it will be loaded a BBC with no CMC associated
                $bbc = BotBasicChannel::load($bbcId, $rt);
            }
            $rt->doDummy($bbc);
            // if ($bbc === null) { return null; }
        }
        // finalize building
        $rt->initRunStructs();
        // try to upgrade the BBcode
        $rt->updateBBcode();
        // ready
        return $rt;
    }



    /**
     * Carga una instancia de esta clase según el ID asociado de su BizModel user
     *
     * @param  int                          $bbcodeCMid     ID del bot; una de las claves de ChatMedium::$bbBots
     * @param  int                          $bmUserId       ID del BizModel user
     * @return BotBasicRuntime|null|bool                    Instancia cargada; o null en caso de error; o false si no se consigue el bmUserId
     */
    static public function loadByBizModelUserId ($bbcodeCMid, $bmUserId)
    {
        if (! is_numeric($bmUserId)) { return false; }
        // check if in store
        foreach (self::$store as $rt) {
            if ($rt->bmUserId == $bmUserId) { return $rt; }
        }
        // if not, read from DB
        $rtId = DBbroker::readBBruntimeIdByBizModelUserId($bbcodeCMid, $bmUserId);
        if ($rtId === null) {
            Log::register(Log::TYPE_DATABASE, "RT512 Error de BD y se cancela la creación del runtime");
            return null;
        }
        elseif ($rtId === false) {
            return false;
        }
        return self::loadById($rtId, true);
    }



    /**
     * Intenta actualizar el código del programa BotBasic de esta instancia (mismo codename) a una versión más reciente (concretamente mayor versión)
     * que esté disponible en BD
     *
     * Se intenta actualizar sólo a una versión que comparta la versión mayor actual asociada a esta instancia.
     *
     * @param bool  $updateAlsoMajorVersion     Indica si actualizar a una versión mayor que esté disponible, dentro del contexto de un RESET ALL
     */
    private function updateBBcode ($updateAlsoMajorVersion = false)
    {
        // check if should update now
        $bbCodeName              = $this->getBBcodename();
        $allowedMajorCodeVersion = $this->getBBlastestAllowedCodeMajorVersion();
        $majorVersionToReach     = $updateAlsoMajorVersion ? $allowedMajorCodeVersion : $this->bbCodeMajorVersion;
        $bbCodeVersion           = DBbroker::readLastBBCodeVersionForCodename($bbCodeName, $majorVersionToReach);
        if ($bbCodeVersion === null) {
            Log::register(Log::TYPE_DATABASE, "RT539 Error de BD", $this);
            return;
        }
        if ($bbCodeVersion === false) {
            Log::register(Log::TYPE_RUNTIME, "RT543 No se consigue una version de codigo BB para el codename $bbCodeName", $this);
            return;
        }
        list ($bbMajorCodeversion, $bbMinorCodeversion, $bbSubminorCodeversion) = $bbCodeVersion;
        if ($bbMajorCodeversion == $this->bbCodeMajorVersion && $bbMinorCodeversion == $this->bbCodeMinorVersion && $bbSubminorCodeversion == $this->bbCodeSubminorVersion) {
            return;   // already updated
        }

        // if should update major version code, do it and return
        if ($updateAlsoMajorVersion) {
            $program = DBbroker::readBBcode($bbCodeName, $bbMajorCodeversion, $bbMinorCodeversion);
            if ($program === null) {
                Log::register(Log::TYPE_DATABASE, "RT550 Error de BD", $this);
                return;
            }
            if ($program === false) {
                Log::register(Log::TYPE_RUNTIME, "RT554 No se puede leer la version del programa BB para ($bbCodeName, $bbMajorCodeversion, $bbMinorCodeversion)", $this);
                return;
            }
            list ($bbCodeId, $bbVersion, , , , $theNewSubminor, $messages, $menus, $magicvars, $primitives, $bots) = $program;
            if ($bbVersion > BOTBASIC_LANG_VERSION) {
                Log::register(Log::TYPE_RUNTIME, "RT559 Se requiere una version del interprete superior ($bbVersion)", $this);
                return;
            }
            $this->messages               = $messages;
            $this->predefmenus            = $menus;
            $this->magicvars              = $magicvars;
            $this->primitives             = $primitives;
            $this->bots                   = $bots;
            $this->bbBotIdx               = $bbCodeId;
            $this->bbCodeMajorVersion     = $bbMajorCodeversion;
            $this->bbCodeMinorVersion     = $bbMinorCodeversion;
            $this->bbCodeSubminorVersion  = $theNewSubminor;
            $this->bbCodeUpdatedToLastest = true;
            $this->tainting(true);
            return;
        }

        // from here: updating only minor version code
        // get all routes used currently by active bbChannels; at the same time get all the labels for each route stoppoint for each BBC
        $data = DBbroker::readAllRouteQueuesAndBBlabelsForBBruntime($this->id, $this->bbCodeMajorVersion, $this->getBBbotName());
        if ($data === null) {
            Log::register(Log::TYPE_DATABASE, "RT576 Error de BD", $this);
            return;
        }
        $oldRouteLabelsAndLinenos = [];
        $canOnlyUpdateSubminor    = false;
        foreach ($data as $triplet) {
            list ($bbcId, $routeQueue, $labels) = $triplet;
            // get the label for the stoppoint lineno that was stored in the each route
            if (! is_array($labels)) { continue; }   // fix durante el taller de abril 2017
            $labelsNames   = array_keys($labels);
            $labelsLinenos = array_values($labels);
            foreach ($routeQueue as $routeIdx => $route) {
                if (BotBasicChannel::isForeignRoute($this->getCurrentBBchannel(), null, $route)) { continue; }   // ignore input/menu routes set from foreign BBC's
                // the following code will happen only once per BBC (no more than one non-foreign route can be set for a specific BBC)
                $lineno = BotBasicChannel::getRouteLineno($route);
                if ($lineno === null) { continue; }   // a 'default' route
                $pos = array_search($lineno, $labelsLinenos);
                if ($pos === false) {
                    $canOnlyUpdateSubminor = true;
                    break 2;
                }   // if an active stoppoint has no associated label for the current BB code version, can't upgrade
                $matched = [ $labelsNames[$pos], $lineno ];
                // store
                $oldRouteLabelsAndLinenos[$bbcId] = $matched;   // create the key in the array excluding BBCs with route 'default'
                break;                                          // there is only one "real" (non-foreign) stoppoint per BBC
            }
        }

        // try to match labels to the new code version, from the newest to the oldest
        $newRouteLabelsAndLinenos = $newMinorCodeVersion = null;
        if (! $canOnlyUpdateSubminor) {
            $data = DBbroker::readNewerBBcodeVersionsLabels($this->getBBcodename(), $this->bbCodeMajorVersion, $this->bbCodeMinorVersion, $this->getBBbotName());   // got newest to oldest
            if ($data === null) {
                Log::register(Log::TYPE_DATABASE, "RT602 Error de BD", $this);
                return;
            }
            foreach ($data as $triplet) {
                list (, $minor, , $labels) = $triplet;   // first elem is $major
                $newRouteLabelsAndLinenos = [];
                foreach ($oldRouteLabelsAndLinenos as $bbcId => $pair) {
                    list ($label, ) = $pair;
                    // if no match is found for a current label, can't upgrade to that version; try with the previous most inmediate code version
                    if (! isset($labels[$label])) { continue 2; }
                    // store
                    $newRouteLabelsAndLinenos[$bbcId] = [ $label, $labels[$label] ];
                }
                $newMinorCodeVersion = $minor;
                break;
            }
        }

        // if no new code version is available where all active stoppoints labels can be matched, can't upgrade to a new minor version, so only update the subminor version
        if ($canOnlyUpdateSubminor || $newMinorCodeVersion === null) {
            $newMinorCodeVersion = $this->bbCodeMinorVersion;
        }

        // when updating minor and also subminor, must upgrade the routes of each BBC (taint them)
        else {
            $bbcs = [];   /** @var BotBasicChannel[] $bbcs */
            foreach (array_keys($newRouteLabelsAndLinenos) as $bbcId) {
                $bbc = BotBasicChannel::load($bbcId, $this);
                if ($bbc === null) {
                    Log::register(Log::TYPE_RUNTIME, "RT627 No se puede actualizar la version de codigo BB", $this);
                    return;
                }
                if ($bbc === false) {
                    Log::register(Log::TYPE_RUNTIME, "RT631 No se puede actualizar la version de codigo BB", $this);
                    return;
                }
                $bbcs[$bbcId] = $bbc;
            }
            foreach ($newRouteLabelsAndLinenos as $bbcId => $pair) {
                list (, $newLineno) = $pair;
                $bbc                = $bbcs[$bbcId];
                $routeIdxs          = $bbc->getRouteQueueIndexes();
                foreach ($routeIdxs as $routeIdx) {
                    if ($bbc->getRouteType($routeIdx) == 'default' || BotBasicChannel::isForeignRoute($bbc, $routeIdx)) { continue; }   // process only the max-one non-foreign route
                    $bbc->setRouteLineno($newLineno, $routeIdx);
                    break;
                }
            }
        }

        // update the RT
        $program = DBbroker::readBBcode($bbCodeName, $this->getBBcodeMajorVersion(), $newMinorCodeVersion);
        if ($program === null) {
            Log::register(Log::TYPE_DATABASE, "RT650 Error de BD", $this);
            return;
        }
        if ($program === false) {
            Log::register(Log::TYPE_RUNTIME, "RT654 No se puede leer la version de codigo BB", $this);
            return;
        }
        if ($program[1] > BOTBASIC_LANG_VERSION) {
            Log::register(Log::TYPE_RUNTIME, "RT658 Se requiere una version superior del interpretador", $this);
            return;
        }
        list (, , , , , $theNewSubminor, $this->messages, $this->predefmenus, $this->magicvars, $this->primitives, $this->bots) = $program;
        $this->bbCodeMinorVersion    = $newMinorCodeVersion;
        $this->bbCodeSubminorVersion = $theNewSubminor;
        if ($this->bbCodeMajorVersion == $allowedMajorCodeVersion) {
            $this->bbCodeUpdatedToLastest = true;
        }
        $this->tainting(true);
    }



    ////////////
    // UTILITIES
    ////////////



    protected function addError ($lineno, $message, $isBBerror = false)
    {
        Log::register(Log::TYPE_RUNTIME, "Intento de uso de addError() en modo runtime con: [lineno:$lineno] [BBerror:" . ($isBBerror ? "true" : "false") . "] [$message]");
    }



    ///////////////////////////////////////////////////////////////////
    // ROUTING ACTION (ENTERING PROCESS) (INCLUDING RUNWITH() HANDLERS)
    ///////////////////////////////////////////////////////////////////



    /**
     * Dispara la ejecución del programa BotBasic a partir de un lineno a determinar a partir del Update
     * o a partir de la cola de rutas de contextos de ejecución
     *
     * @param  BotBasicChannel  $bbChannel  Canal BotBasic desde el cual se llama a la ejecución
     * @param  Update|null      $update     Update de entrada, si aplica; o null si no aplica, en cuyo caso se interpreta la cola de rutas de contextos
     */
    public function execute ($bbChannel, $update = null)
    {
        // check
        if (! $this->built) {
            Log::register(Log::TYPE_RUNTIME, "RT713 Se esta intentando execute() sobre una version de runtime que no finalizo su construccion", $this, $update);
            return;
        }
        // prepare and run
        Log::profilerStep(0, 'entering BBruntime::execute()');
        $this->update = $update;
        $this->couldForceUpdateBBcode();
        $this->route($bbChannel);
        // render
        $rtsRendered = [];
        do {
            $pendings = false;
            foreach (self::$store as $rt) {
                $pendings |= $rt->submitRendering();
                if (! in_array($rt->id, $rtsRendered)) { $rtsRendered[] = $rt->id; }
            }
        }
        while ($pendings || count($rtsRendered) != count(self::$store));
        // post-run actions and housekeeping
        if ($update !== null) {
            self::closeAll();
            $this->trapLoggingCredentials($update);
            $update->setBizIntelligencyInfo($bbChannel->getId(), $this->bmUserId);
            $update->save($this);
        }
    }



    protected function initRunStructs ()
    {
        // volatile (per-run) state (see proper methods for ->inputs and ->menus to get the final definition af arrays)
        $this->menuAndInputTitles = [];                     // each: value
        $this->menuOptions        = [];                     // each: see Interaction:: helpers for menuhooks
        $this->menuPager          = [ null, null ];         // [ pagerType, pagerTypeArg ]
        $this->dataGetEmpty       = true;                   // if last DATA GET directive got no match by key in DB
        $this->on                 = [ null, null, null ];   // [ bot, domainUserId, bbChannelId ]
        $this->prints             = [];                     // each: [ value, on[] ]
        $this->inputs             = [];                     // each: [ 'input', $dataType, $title, $word, $targetBbcId, $toVar, $fromValue ]
        $this->menus              = [];                     // each: [ 'predefmenu', $bot, $lineno, $menuName, $options, $pager, $targetBbcId, $toVars, $contextObject ]
                                                            //   or: [ 'stdmenu', $bot, $lineno, $options, $pager, $targetBbcId, $toVar ]
    }



    /**
     * Fuerza la actualización del código del programa BotBasic si la entrada es un comando /start
     *
     * Esto evita el deadlocking del mecanismo de actualización inteligente por versionamiento semántico.
     */
    private function couldForceUpdateBBcode ()
    {
        if (! ($this->update !== null && $this->update->getText() == BOTBASIC_COMMAND_START)) {
            return;
        }
        $done = false;
        if (! $this->bbCodeUpdatedToLastest) {
            $this->resetAllHelper(-1, $this->getBBbotName(), true);
            $done = true;
        }
        if (! $done) {
            $this->resetAllHelper(-1, $this->getBBbotName(), true, null, true);
        }
    }



    /**
     * Atrapa, si procede, las credenciales del update asociado a la ejecución y las registra para futuros Log::register()
     * cuando está activdo el registro en bots
     *
     * @param  Update   $update     Update a partir del cual extraer las credenciales
     */
    private function trapLoggingCredentials ($update)
    {
        if ($update === null)                                                            { return; }
        $cmc = $this->getCurrentBBchannel()->getCMchannel();
        if ($cmc === null)                                                               { return; }
        if ($cmc->getCMtype() !== BOTBASIC_LOGBOT_CHATAPP)                               { return; }
        $classname = '\botbasic\ChatMedium' . ChatMedium::typeString(BOTBASIC_LOGBOT_CHATAPP);   /** @var $classname LogbotChatMedium */
        if (! $classname::cmUserIsLogbotUser($this->bbBotIdx, $update->getCMuserName())) { return; }
        $res = DBbroker::writeTelegramLogBotCredentials($this->bbBotIdx, $update->getCMuserName(), $cmc->getId());
        if ($res === null) {
            Log::register(Log::TYPE_DATABASE, "RT806 Error de BD", $this, $update);
        }
    }



    /**
     * Ejecuta efectivamente la secuencia de enrutamiento a partir del Update registrado, determinando primero el lineno a partir del cual
     * ejecutar; previamente puede determinarse que se debe ejecutar la segunda fase del procesador de un INPUT o MENU
     *
     * @param  BotBasicChannel  $bbChannel      Instancia del canal desde el cual se invoca la ejecución
     */
    private function route ($bbChannel)
    {
        Log::profilerStep(0, 'entering BBruntime::route()');
        $text         = $this->getEntryText(true);
        $hook         = $this->getMenuhook();
        $event        = $this->getEventCommand();
        $hasResource  = $this->update !== null && $this->update->hasResources();
        $routeType    = $bbChannel->getRouteType(0);
        $routeContent = $bbChannel->getRouteContent(0);
        $catched      = false;

        // special case for event "fake" updates
        if (! ($event === null || $event == self::NOTHING)) {
            Log::profilerStep(0, 'entering BBruntime::route()::eventCommandHandler');
            $hookLineno = $this->getEventhookStartPoint();
            if ($hookLineno !== null) {
                $this->runPart($hookLineno, true);
            }
            if (! $this->aborted) {
                $lineno = $this->getEventStartPoint();
                if ($lineno !== null) {
                    $this->runPart($lineno);
                }
                else {
                    Log::register(Log::TYPE_BBCODE, "RT758 Fue disparado un evento que no tiene handler en el programa BB", $this);
                }
            }
            return;
        }

        // special case for preemptive text commands
        if ($text == BOTBASIC_COMMAND_START) {
            $realType  = $routeType;
            $routeType = 'default';
        }

        // type-and-content-based routing: inputs
        if ($routeType == 'input' && ($text !== null || $hasResource)) {
            Log::profilerStep(0, 'entering BBruntime::route()::inputHandler');
            $hookLineno = $this->getInputhookStartPoint();
            if ($hookLineno !== null) {
                $this->runPart($hookLineno, true);
            }
            if (! $this->aborted) {
                list ($bot, $lineno, $dataType, $title, $word, $bbcId, $toVars, $fromValue) = $routeContent;
                if (! is_array($toVars)) { $toVars = [ $toVars ]; }
                $caption = $resource = null;
                if ($this->update !== null) {
                    foreach ($this->update->getResources() as $r) {
                        if ($r->type == InteractionResource::TYPE_CAPTION) { $caption = $r->metainfo; }
                        else                                               { $resource = $r;          }
                    }
                    if ($resource !== null && $caption === null && isset($resource->metainfo['caption'])) {
                        $caption = $resource->metainfo['caption'];
                    }
                    if ($caption !== null && $text !== null) {
                        Log::register(Log::TYPE_RUNTIME, "RT960 Se recibio un update con texto y a la vez un resource caption", $this);
                    }
                    if ($text === null && $caption !== null) { $text = $caption; }
                }
                $this->invokeInputStep2($bot, $lineno, $dataType, $title, $word, $bbcId, $toVars, $fromValue, $text, $resource);
                $catched = true;
            }
        }

        // type-and-content-based routing: menus
        elseif (($routeType == 'stdmenu' || $routeType == 'predefmenu') && $hook !== null) {
            Log::profilerStep(0, 'entering BBruntime::route()::menuhookHandler');
            $hook = Interaction::decodeMenuhook($hook);
            if ($hook === null) {
                // do nothing (suspect a "bad client" hack)
                Log::register(Log::TYPE_RUNTIME, "RT781 Se sospecha de un hack de un menuhook desde una chatapp", $this);
                return;
            }
            list ($key, $gotoOrGosub, $gotoOrGosubTargetLineno, $bbChannelId, $menuHookLineno) = $hook;
            $this->doDummy($bbChannelId);
            $menuName = $menuArgs = $contextObject = $toVar = $toVars = null;
            $routeContent[] = null;   // hack for next line
            if ($routeType == 'stdmenu') { list ($bot, $lineno,                       $titles, $options, $pager, $bbcId, $toVar                 ) = $routeContent; }
            else                         { list ($bot, $lineno, $menuName, $menuArgs, $titles, $options, $pager, $bbcId, $toVars, $contextObject) = $routeContent; }
            if ($menuHookLineno != $lineno) {
                // do nothing (pressed an old menu option)
                // Log::register(Log::TYPE_DEBUG, "RT791 Se ha presionado una opcion de menu correspondiente a un menu antiguo", $this);
            }
            else {
                $hookLineno = $this->getMenuhookStartPoint();
                if ($hookLineno !== null) {
                    $this->runPart($hookLineno, true);
                }
                if (! $this->aborted) {
                    if (isset($key->tag)) {   // pager button
                        $action = $key->tag;
                        $page   = isset($key->page) ? $key->page : null;
                        $this->getCurrentBBchannel()->updateRouteStdMenu($action, $page);
                    }
                    else {   // normal menu button
                        $toVarOrToVars = $toVar !== null ? $toVar : $toVars;
                        $this->invokeMenuStep2($bot, $lineno, $menuName, $menuArgs, $titles, $options, $pager, $contextObject, $bbcId, $toVarOrToVars, $key, $gotoOrGosub, $gotoOrGosubTargetLineno);
                    }
                }
            }
            $catched = true;
        }

        // text input when expecting a menuhook
        elseif ($routeType == 'stdmenu' || $routeType == 'predefmenu') {   // $hook === null
            $msg = BotConfig::botMessage($this->bbBotIdx, $this->locale, BotConfig::MSG_MUST_CHOOSE_FROM_MENU);
            $this->splashHelperPrint($msg);
            // si no funciona la continuidad del custom/inline keyboard al mostrar el prompt, se debe replicar el menu que justo acaba de ser mostrado y para el cual el usuario no selecciono una opcion sino que metio texto (OK en Telegram)
        }

        // ignore keyboard button presses when expecting text
        elseif ($routeType == 'default' && $hook !== null) {}

        // normal text input OR running code from a foreigner, succesful INPUT or MENU
        elseif ($routeType == 'default') {
            Log::profilerStep(0, 'entering BBruntime::route()::defaultRouteHandler');
            // special case for preemptive text commands
            if (isset($realType) && $realType != 'default' && $text == BOTBASIC_COMMAND_START) {
                $this->getCurrentBBchannel()->popRoute();
            }
            // standard processing
            $hookLineno = $this->getEntryhookStartPoint();
            $lineno     = $this->getMainCodeStartPoint();
            if ($text !== null && $hookLineno !== null) {   // no correr el entryhook en caso de provenir de un MENU/INPUT foraneo
                $this->runPart($hookLineno, true);
            }
            if (! $this->aborted && $lineno !== null) {
                $this->runPart($lineno);
            }
        }

        // wrong route type
        else {
            $info2log =  "text="         . ($text         === null ? "NULL" : $text                     ) .
                        "|hook="         . ($hook         === null ? "NULL" : $hook                     ) .
                        "|event="        . ($event        === null ? "NULL" : $event                    ) .
                        "|routetype="    . ($routeType    === null ? "NULL" : $routeType                ) .
                        "|routecontent=" . ($routeContent === null ? "NULL" : json_encode($routeContent));
            Log::register(Log::TYPE_RUNTIME, "RT824 routetype incorrecto u otro route con combinacion invalida [$info2log]", $this);
            // TODO por aqui entran (y son logueados) los uploads de contenido multimedia
        }

        // tunnels forwarding
        if ($this->update !== null && ($this->update->hasResources() || $text !== null) && ! $catched) {
            Log::profilerStep(0, 'entering BBruntime::route()::tunnelsForwarding');
            $this->getCurrentBBchannel()->sendToTunnels($this->update);
        }
    }



    /**
     * Ejecuta desde route() una llamada a runWith()
     *
     * @param  int  $lineno     Lineno a partir del cual ejecutar el código del programa BotBasic
     * @param  bool $inHook     Indica si se está ejecutando un hook (entry, menu, ...) a fin de salvaguardar el stack principal
     */
    private function runPart ($lineno, $inHook = false)
    {
        Log::profilerStep(0, "entering BBruntime::runPart() lineno=$lineno");
        $bot      = $this->getBBbotName();
        $this->on = $this->completeOn();
        if ($inHook) { $this->getCurrentBBchannel()->toogleCallStack(); }
        $this->runWith($bot, true, "runner", $lineno);
        if ($inHook) { $this->getCurrentBBchannel()->toogleCallStack(); }
    }



    /**
     * Obtiene el lineno del inicio del ENTRYHOOK
     *
     * @return null|int     lineno asociado; o null si no fue definido el hook
     */
    private function getEntryhookStartPoint ()
    {
        $bot = $this->getBBbotName();
        if (! isset($this->bots[$bot]['specialHooks']['entry'])) { return null; }
        return      $this->bots[$bot]['specialHooks']['entry'];
    }



    /**
     * Obtiene el lineno del inicio del MENUHOOK
     *
     * @return null|int     lineno asociado; o null si no fue definido el hook
     */
    private function getMenuhookStartPoint ()
    {
        $bot = $this->getBBbotName();
        if (! isset($this->bots[$bot]['specialHooks']['menu'])) { return null; }
        return      $this->bots[$bot]['specialHooks']['menu'];
    }



    /**
     * Obtiene el lineno del inicio del INPUTHOOK
     *
     * @return null|int     lineno asociado; o null si no fue definido el hook
     */
    private function getInputhookStartPoint ()
    {
        $bot = $this->getBBbotName();
        if (! isset($this->bots[$bot]['specialHooks']['input'])) { return null; }
        return      $this->bots[$bot]['specialHooks']['input'];
    }



    /**
     * Obtiene el lineno del inicio del EVENTHOOK
     *
     * @return null|int     lineno asociado; o null si no fue definido el hook
     */
    private function getEventhookStartPoint ()
    {
        $bot = $this->getBBbotName();
        if (! isset($this->bots[$bot]['specialHooks']['event'])) { return null; }
        return      $this->bots[$bot]['specialHooks']['event'];
    }



    /**
     * Obtiene el lineno del inicio de un manejador de eventos de tiempo, a partir del contenido del Update "fake" de entrada
     *
     * @return null|int     lineno asociado; o null si no se puede hallar un hook común o por expresión regular
     */
    private function getEventStartPoint ()
    {
        $bot   = $this->getBBbotName();
        $event = $this->getEventCommand();
        foreach ($this->bots[$bot]['eventHooks'] as $hook => $lineno) {
            if ($event == $hook) { return $lineno; }
        }
        return null;
    }



    /**
     * Obtiene el lineno del inicio del código principal del programa BotBasic, a partir del texto del Update de entrada
     *
     * @return null|int     lineno asociado; o null si no se puede hallar un hook común o por expresión regular
     */
    private function getMainCodeStartPoint ()
    {
        $bot  = $this->getBBbotName();
        $text = trim($this->getEntryText());
        foreach ($this->bots[$bot]['commonHooks'] as $hook => $lineno) {
            if ($text == $hook) { return $lineno; }
        }
        foreach ($this->bots[$bot]['regexpHooks'] as $hook => $lineno) {
            if (1 === preg_match($hook, $text)) { return $lineno; }
        }
        return null;
    }



    ///////////////
    // EXIT PROCESS
    ///////////////



    /**
     * Remite hacia las chatapps, a través de los BotBasicChannels asociados a esta instancia, las salidas del la ejecución del código asociado
     * a los hooks activados con el Update de entrada por parte de render(), convirtiéndoles en objetos Splash genéricos en las instancias de
     * BotBasicChannel apropiadas
     *
     * @return bool     Indicador de si uno de los splashes fue foráneo
     */
    private function submitRendering ()
    {
        Log::profilerStep(0, 'entering BBruntime::submitRendering()');
        $bbcPool         = [];   /** @var BotBasicChannel[] $bbcPool */   // not more used
        $foreignSplashes = false;

        $completeOn = function (&$onArray)
        {
            if ($onArray === null) { $onArray = []; }
            $botName  = isset($onArray[0]) ? $onArray[0]   : null;
            $bmUserId = isset($onArray[1]) ? $onArray[1]   : null;
            $bbcId    = isset($onArray[2]) ? $onArray[2]   : null;
            $onArray  = $this->completeOn($botName, $bmUserId, $bbcId);
            if ($onArray[2] === null) { $onArray = null; }
            //old code:
            //if ($onArray === null)    { $onArray = [];                              }
            //if (! isset($onArray[0])) { $onArray[0] = $this->getBBbotName();        }
            //if (! isset($onArray[1])) { $onArray[1] = $this->bmUserId;              }
            //if (! isset($onArray[2])) {
            //    if ($onArray[0] == $this->getBBbotName() && $onArray[1] == $this->bmUserId) {   // myself
            //        $onArray[2] = $this->getCurrentBBchannel();
            //    }
            //    else {
            //        $rt = BotBasicRuntime::loadByBizModelUserId($onArray[1]);
            //        if ($rt === false || $rt === null) { $onArray = null;                                   }
            //        else                               { $onArray[2] = $rt->getCurrentBBchannel()->getId(); }
            //    }
            //}
        };

        $completedOnIsForeign = function ($onArray)
        {
            return $onArray[0] != $this->getBBbotName();
        };

        $getBBchannel = function ($onArray = null) use (&$bbcPool)
        {
            list ($botName, $userId, $bbChannelId) = $onArray;
            $bbcs = $this->getBBchannelsIndexed();
            if ($onArray !== null && $botName ==  $this->getBBbotName() && $userId  ==  $this->bmUserId && isset($bbcs[$bbChannelId])) {
                $bbcPool[$bbChannelId] = $bbcs[$bbChannelId];
            }
            if (! isset($bbcPool[$bbChannelId])) {
                $bbc = BotBasicChannel::load($bbChannelId);
                if ($bbc === null) { return null; }
                $bbcPool[$bbChannelId] = $bbc;
            }
            return $bbcPool[$bbChannelId];
        };

        $doForPrints = function () use ($completeOn, $getBBchannel, $completedOnIsForeign)
        {
            // Log::profilerStep(0, 'in BBruntime::submitRendering() before prints');
            $foreigns = false;
            foreach ($this->prints as $print) {
                list ($value, $on) = $print;
                // ONs y BBCs
                $completeOn($on);
                $foreigns |= $completedOnIsForeign($on);
                if ($on === null) {
                    Log::register(Log::TYPE_DEBUG, "RT1011 No se encontro un default BBC para un RT foraneo", $this);
                    continue;
                }
                $bbc = $getBBchannel($on);   /** @var BotBasicChannel $bbc */
                if ($bbc === null) {
                    Log::register(Log::TYPE_DEBUG, "RT1016 lambda getBBchannel arroja null", $this);
                    continue;
                }
                // build splash
                if (is_string($value)) {
                    $splash = Splash::createWithText($value);
                }
                elseif (is_array($value) && $value['type'] == 'resource') {
                    $cmType      = $this->getCurrentBBchannel()->getCMchannel()->getCMtype();
                    $srcResource = InteractionResource::load($value['id']);
                    if ($srcResource === null) { continue; }
                    $resource = $srcResource->createByCloning($cmType, $bbc->getCMchannel()->getCMbotName());
                    $caption  = isset($value['caption']) ? InteractionResource::createFromContent(InteractionResource::TYPE_CAPTION, $cmType, $value['caption']) : null;
                    $splash   = Splash::createWithResource($resource, $caption);
                }
                else {
                    Log::register(Log::TYPE_DEBUG, "RT1016 lambda getBBchannel arroja null", $this);
                    continue;
                }
                // enqueue
                $bbc->orderEnqueueing($splash);
            }
            $this->prints = [];
            return $foreigns ? true : false;
        };

        // prints - first pass
        $foreignSplashes |= $doForPrints();

        // menus
        // Log::profilerStep(0, 'in BBruntime::submitRendering() before menus');
        foreach ($this->menus as $menu) {
            list ($menuName, $menuArgs, $titles, $options, $pager, $toVars, $lineno, $bot, $on) = $menu;
            if ($toVars !== null) {   // a menu was requested
                $completeOn($on);
                $foreignSplashes |= $completedOnIsForeign($on);
                if ($on === null) {
                    Log::register(Log::TYPE_DEBUG, "RT1029 No se encontro un default BBC para un RT foraneo", $this);
                    continue;
                }
                $bbc = $getBBchannel($on);
                if ($bbc === null) {
                    Log::register(Log::TYPE_DEBUG, "RT1034 lambda getBBchannel arroja null", $this);
                    continue;
                }
                // Log::profilerStep(0, 'in BBruntime::submitRendering() before menu->enqueue...()');
                if ($menuName !== null) {
                    $bbc->enqueueRoutePredefMenu($bot, $lineno, $menuName, $menuArgs, $titles, $options, $pager, $this->getCurrentBBchannel()->getId(), $toVars, true);
                }
                else {
                    $bbc->enqueueRouteStdMenu($bot, $lineno, $titles, $options, $pager, $this->getCurrentBBchannel()->getId(), $toVars[0], true);
                }
                // Log::profilerStep(0, 'in BBruntime::submitRendering() after menu->enqueue...()');
            }
        }

        // inputs (should be exclusive with menu)
        // Log::profilerStep(0, 'in BBruntime::submitRendering() before inputs');
        foreach ($this->inputs as $input) {
            list ($dataType, $titles, $word, $toVars, $fromVarValue, $lineno, $bot, $on) = $input;
            if ($toVars !== null) {   // an input was requested
                $completeOn($on);
                $foreignSplashes |= $completedOnIsForeign($on);
                if ($on === null) {
                    Log::register(Log::TYPE_DEBUG, "RT1052 No se encontro un default BBC para un RT foraneo", $this);
                    continue;
                }
                $bbc = $getBBchannel($on);   /** @var BotBasicChannel $bbc */
                if ($bbc === null) {
                    Log::register(Log::TYPE_DEBUG, "RT1057 lambda getBBchannel arroja null", $this);
                    continue;
                }
                $bbc->enqueueRouteInput($bot, $lineno, $dataType, $titles, $word, $this->getCurrentBBchannel()->getId(), $toVars, $fromVarValue, true);
            }
        }

        // prints - last pass
        $foreignSplashes |= $doForPrints();

        // render at the multiple ChatMedia
        Log::profilerStep(0, 'in BBruntime::submitRendering() before orderRendering()s');
        foreach ($this->getBBchannels() as $bbc) {   // iterar sobre $bbcPool no funcionara pues al invocar a un menu pager button, en BBC::enqueueRouteStdMenu() se encolan splashes directamente sin usar RT::menus[]
            $bbc->orderRendering();
        }

        // reset buffers and return
        $this->menus = $this->inputs = $this->prints = [];
        return $foreignSplashes ? true : false;
    }



    /**
     * Guarda esta instancia en BD, guardando a la vez las variables del programa BotBasic asociadas específicamente al runtime
     *
     * @return bool|null    null en caso de error de BD; true en caso de éxito
     */
    public function save ()
    {
        // vars
        $toUpdate = [];
        foreach ($this->vars as $name => $varData) {
            list ($tainted, $value) = $varData;
            if (! $tainted) { continue; }
            $toUpdate[] = [ $name, $value ];
        }
        $res = DBbroker::updateVars($this, null, $toUpdate);
        if ($res === null) {
            Log::register(Log::TYPE_DATABASE, "RT1098 Error de BD", $this);
        }
        // datahelper
        $this->dataHelperFlusher();
        // runtime table
        if ($this->tainting() || $this->id === null || $this->deleted) {
            $res = DBbroker::writeBBruntime($this);
            if ($res === null) {
                Log::register(Log::TYPE_DATABASE, "RT1085 Error de BD", $this);
                return null;
            }
            elseif ($res === false) {
                Log::register(Log::TYPE_RUNTIME, "RT1089 No se puede grabar el runtime porque su ID no existe en BD", $this);
                return null;
            }
            elseif (is_int($res)) { $this->id = $res; }
            $this->tainting(false);
        }
        // ready
        return true;
    }



    public function close ($cascade)
    {
        if (! $this->built) {
            if (DBbroker::nullifyRuntimeEtAl($this->id) === null) {
                Log::register(Log::TYPE_RUNTIME, "RT1335 Error de BD: no se puede nullificar RT (et al) cuya construccion no ha finalizado", $this);
            }
            return;
        }
        if ($this->bma() !== null) {
            $ok = $this->bma()->terminate();
            if (! $ok) { return; }
        }
        $this->save();
        if ($cascade) {
            foreach ($this->getBBchannels() as $bbc) {
                $bbc->close(true);
            }
        }
    }



    static public function closeAll ()
    {
        foreach (self::$store as $obj) {
            $obj->close(false);
        }
        BotBasicChannel::closeAll();
        ChatMediumChannel::closeAll();
        Log::housekeeping();
        if (BOTBASIC_DEBUG) {
            Log::register(Log::TYPE_DEBUG, "peak_mem = " . (memory_get_peak_usage(true) / (1024*1024)). " MB");
        }
    }



    public function tainting ($state = null)
    {
        if ($state === null)   { return $this->taintingState; }
        if (! is_bool($state)) { return null;                 }
        $this->taintingState = $state;
        return null;
    }



    ////////////////////////////////////////////////////
    // NAMESPACE OBJECTS ACCESSORS INCLUDING VARS ACCESS
    ////////////////////////////////////////////////////



    /**
     * Obtiene el ID del BizModel user, si está registrado
     *
     * @return int|null     ID; o null si no ha sido previamente definido en el runtime
     */
    public function getBizModelUserId ()
    {
        return $this->bmUserId;
    }



    /**
     * Fija el ID del BizModel user
     *
     * @param  int              $value      ID
     * @param  int|null         $lineno     Número de línea del programa BotBasic desde el que se llama a esta función (opcional)
     * @param  string|null      $bot        Bot del programa BotBasic que llama a esta función (opcional)
     * @return bool|null                    null en caso de que no se pase un entero; true en caso de éxito; false en caso de error: si el argumento especificado
     *                                      no permite cargar un runtime, o si la operación es invocada desde un BotBasicChannel que no es un "default channel"
     */
    public function setBizModelUserId ($value, $lineno = null, $bot = null)
    {
        // detener la ejecucion del programa sobre el runtime actual
        $this->running($this->getBBbotName(), false);
        $this->aborted = true;
        // retornar trivialmente si el valor especificado es el mismo que el actual
        if ($value == $this->bmUserId) {
            return true;
        }
        // cancelar la operacion si no se esta ejecutando desde el canal por defecto
        if (! $this->getCurrentBBchannel()->isDefaultBBchannel()) {
            Log::register(Log::TYPE_BBCODE, "RT1158 setBizModelUserId solo se puede hacer en el default channel", $this, $lineno, $bot);
            return false;
        }
        // verificar que el userId especificado tenga un runtime previamente creado y cargarlo
        $newRt = BotBasicRuntime::loadByBizModelUserId($this->bbBotIdx, $value);
        if ($newRt === null) {
            Log::register(Log::TYPE_DATABASE, "RT1164 Error de BD", $this, $lineno, $bot);
            return false;
        }
        // si no hay un runtime previamente disponible para el valor especificado, el valor se asigna a este runtime
        elseif ($newRt === false) {
            $this->bmUserId = $value;
            $this->tainting(true);
            return true;
        }
        // retornar trivialmente si el runtime cargado es el mismo que el actual
        elseif ($newRt->id == $this->id) {
            return true;
        }
        // hacer flush de los splashes acumulados en el runtime actual
        $this->submitRendering();
        // conectar el CMC actual al default BBC del RT cargado, y opcionalmente eliminar el CMC correspondiente que estaba conectado a ese BBC
        $oldBbc     = $this->getCurrentBBchannel();
        $currentCmc = $oldBbc->getCMchannel();
        $newBbc     = $newRt->getCurrentBBchannel();
        $cmc2delete = $newBbc->getCMchannelByCMtype($currentCmc->getCMtype());
        if ($cmc2delete !== null) {
            $cmc2delete->setAsDeleted();
            $cmc2delete->setBBchannel($oldBbc);   // a trick for saving in BD a reference for navigating to an "orphaned" RT's CMC data (see DBbroker::readCMuserIdForLastUsedCMchannel())
        }
        $currentCmc->setBBchannel($newBbc);
        // ready
        return true;
    }



    /**
     * Implementa el dataHelper de BotBasic (get)
     *
     * Los nombres están asociados siempre al ID del BizModel user, por lo que no se debe invocar a DATA cuando no se ha dijado previamente
     *
     * @param  string       $key        Clave del atributo en el storage persistente
     * @return null|mixed               null en caso de error de BD o si no se encuentra la clave; el valor del atributo en caso de éxito
     */
    public function dataHelperLoader ($key)
    {
        if ($this->bmUserId === null) { return null; }
        if (isset($this->dataHelperBuffer[$key])) {
            return $this->dataHelperBuffer[$key][1];
        }
        $res = DBbroker::readDataHelperData($this->bbBotIdx, $this->bmUserId, $key);
        if ($res === null) {
            Log::register(Log::TYPE_DATABASE, "RT1201 Error de BD", $this);
            return null;
        }
        elseif ($res === false) {
            return null;
        }
        $value = $res['value'];
        $this->dataHelperBuffer[$key] = [ false, $value ];   // [tainted, value]
        return $value;
    }



    /**
     * Implementa el dataHelper de BotBasic (set)
     *
     * Los nombres están asociados siempre al ID del BizModel user, por lo que no se debe invocar a DATA cuando no se ha dijado previamente.
     *
     * Nota: el comportamiento ha cambiado; ahora las operaciones de BD se dejan para dataHelperFlusher(), el cual si no se llama con el close()
     * del runtime, no genera la persistencia que se busca con este método.
     *
     * @param  string       $key        Clave del atributo en el storage persistente
     * @param  mixed        $value      Valor del atributo
     */
    public function dataHelperSaver ($key, $value)
    {
        if ($this->bmUserId === null) { return; }
        if (isset($this->dataHelperBuffer[$key]) && $value == $this->dataHelperBuffer[$key]) {
            // do nothing
        }
        else {
            $this->dataHelperBuffer[$key] = [ true, $value ];
        }
    }



    /**
     * Implementa el dataHelper de BotBasic (set flusher)
     */
    private function dataHelperFlusher ()
    {
        if ($this->bmUserId === null) { return null; }
        $pairs = [];
        foreach ($this->dataHelperBuffer as $key => &$pair) {
            list ($tainted, $value) = $pair;
            if (! $tainted) { continue; }
            $toSave  = [ 'value' => $value ];
            $pairs[] = [ $key, $toSave ];
        }
        $res = DBbroker::writeAllDataHelperData($this->bbBotIdx, $this->bmUserId, $pairs);
        if ($res === null) {
            Log::register(Log::TYPE_DATABASE, "RT1425 Error de BD", $this);
            return;
        }
        foreach ($this->dataHelperBuffer as $key => &$pair) {
            $pair[0] = false;   // untaint
        }
    }



    /**
     * Retorna la lista de todos los nombres de variables del programa BotBasic definidas específicamente en el runtime
     *
     * @return string[]
     */
    private function getAllVarNames ()
    {
        $res = [];
        foreach ($this->vars as $name => $pair) {
            if ($pair[1] !== null) { $res[] = $name; }
        }
        // $res = array_merge($res, $this->magicvars);   // descomentar para incluir a las variables magicas en el efecto de un RESET ALL
        return $res;
    }



    /**
     * Indica si una variable del programa BotBasic está registrada en el espacio de variables del runtime o de la cadena BotBasicChannel a runtime
     *
     * La variable puede ser común, predefinida (message) o mágica.
     *
     * @param  string               $name
     * @param  null|BotBasicChannel $bbChannel
     * @return bool
     */
    private function isSetVar ($name, $bbChannel = null)
    {
        if ($this->isMagicVar($name)) { $res = true; }
        else {
            $res = false;
            if ($bbChannel !== null) { $res = $bbChannel->getCommonVar($name, $this) !== null;                                      }
            if ($res === false)      { $res = $this->isMessageName($name) || $this->isCommonVar($name) || $this->isMagicVar($name); }
        }
        return $res;
    }



    /**
     * Obtiene el valor de una variable del programa BotBasic, o NOTHING si no está definida
     *
     * @param  string   $name                       Nombre de la variable
     * @param  int      $lineno                     Línea en ejecución del programa BotBasic
     * @param  string   $bot                        Nombre del bot
     * @param  bool     $processMessageEntities     Indica si se deben procesar los templates dentro de los valores de las variables
     * @return string                               Valor de la variable recuperada, o self::NOTHING
     */
    private function getVar ($name, $lineno, $bot, $processMessageEntities = true)
    {
        if ($this->isMagicVar($name)) {
            $res = $this->getMagicVar($name, $lineno, $bot);
        }
        else {
            if     ($this->isCommonVar($name))   { $spec = $this->getCommonVar($name);                                                                           }
            elseif ($this->isMessageName($name)) { $spec = isset($this->messages[$this->locale][$name]) ? $this->messages[$this->locale][$name] : self::NOTHING; }
            else                                 { $spec = self::NOTHING;                                                                                        }
            $res = $processMessageEntities ? $this->processMessageSpec($spec, $lineno, $bot, $name) : $spec;
        }
        return $res === null ? self::NOTHING : $res;
    }



    /**
     * Fija el valor de una variable del programa BotBasic
     *
     * @param  string   $name               Nombre de la variable
     * @param  string   $value              Valor de la variable
     * @param  int      $lineno             Número de línea del programa BotBasic
     * @param  string   $bot                Nombre del bot
     * @param  bool     $queryBBchannel     Indica si se debe asignar la variable en el BotBasicChannel actual o si, por otro lado, se debe hacer en el runtime
     */
    private function setVar ($name, $value, $lineno, $bot, $queryBBchannel = true)
    {
        if ($this->isMagicVar($name)) { $this->setMagicVar( $name, $value, $lineno, $bot);   }
        else                          { $this->setCommonVar($name, $value, $queryBBchannel); }   // isCommonVar OR overwritting a message name
    }



    /**
     * Obtiene el valor de una variable mágica
     *
     * @param  string       $name       Nombre de la variable
     * @param  int          $lineno     Número de línea del programa BotBasic
     * @param  string       $bot        Nombre del bot
     * @return null|string              Valor de la variable; o null si no se consigue el método correspondiente en BizModelAdapter
     */
    private function getMagicVar ($name, $lineno, $bot)
    {
        $this->doDummy($bot);
        if (! $this->isMagicVar($name)) {
            return null;
        }
        $accessor = self::MAGICVARS_PHPACCESSOR_PREFIX . $name . self::MAGICVARS_PHPACCESSOR_POSTFIX_GET;
        if ($this->bma() === null) {
            Log::register(Log::TYPE_RUNTIME, "RT1597 BizModelAdapter nulo", $this, $lineno);
            $res = self::NOTHING;
        }
        elseif (! method_exists($this->bma(), $accessor)) {
            Log::register(Log::TYPE_BBCODE, "RT1328 No se puede hallar el metodo '$accessor' en el objeto BizModelAdapter", $this, $lineno);
            $res = self::NOTHING;
        }
        else {
            $res = $this->bma()->$accessor($this->buildBMAcallMetadata($lineno));
            if     ($res === null)  { $res = self::NOTHING;                                  }
            elseif ($res === false) { $res = $this->getCommonVar($name, false, false, true); }
        }
        return $res;
    }



    /**
     * Fija el valor de una variable mágica
     *
     * @param  string       $name       Nombre de la variable
     * @param  string       $value      Valor de la variable
     * @param  int          $lineno     Número de línea del programa BotBasic
     * @param  string       $bot        Nombre del bot
     */
    public function setMagicVar ($name, $value, $lineno, $bot)
    {
        $this->doDummy($bot);
        if (! $this->isMagicVar($name)) {
            return;
        }
        $accessor = self::MAGICVARS_PHPACCESSOR_PREFIX . $name . self::MAGICVARS_PHPACCESSOR_POSTFIX_SET;
        if ($this->bma() === null) {
            Log::register(Log::TYPE_RUNTIME, "RT1652 BizModelAdapter nulo", $this, $lineno);
        }
        elseif (! method_exists($this->bma(), $accessor)) {
            Log::register(Log::TYPE_BBCODE, "RT1367 No se puede hallar el metodo '$accessor' en el objeto BizModelAdapter", $this, $lineno);
        }
        else {
            $res = $this->bma()->$accessor($value, $this->buildBMAcallMetadata($lineno));
            if (is_array($res)) { $value = $res[0]; $res = false;                  }
            if ($res === false) { $this->setCommonVar($name, $value, false, true); }
        }
    }



    /**
     * Obtiene el valor de una variable común
     *
     * @param  string       $name                   Nombre de la variable
     * @param  bool         $queryBBchannel         Indica si se debe asignar la variable en el BotBasicChannel actual o si, por otro lado, se debe hacer en el runtime
     * @param  bool         $fromBizModelAdapter    Indica se está invocando el método desde el BizModelAdapter
     * @param  bool         $force                  Indica si se debe evitar el chequeo de si la variable es común
     * @return string|null                          Valor de la variable; o si no está fijada: null si se invoca desde BizModelAdapter o NOTHING de otro modo
     */
    public function getCommonVar ($name, $queryBBchannel = true, $fromBizModelAdapter = false, $force = false)
    {
        if (! $force && ! $this->isCommonVar($name) ) {
            if (! $fromBizModelAdapter) {
                Log::register(Log::TYPE_BBCODE, "RT1377 Intentando acceder al valor de una variable no-comun desde BizModelAdapter", $this);
            }
            return $fromBizModelAdapter ? null : self::NOTHING;
        }
        if     ($queryBBchannel)                              { $res = $this->getCurrentBBchannel()->getCommonVar($name, $this); }
        elseif (isset($this->vars[$name]))                    { $res = $this->vars[$name][1];                                    }
        elseif (isset($this->messages[$this->locale][$name])) { $res = $this->messages[$this->locale][$name];                    }
        else                                                  { $res = self::NOTHING;                                            }
        return $fromBizModelAdapter ? $this->processMessageSpec($res, -1, 'called_from_bma', $name) : $res;   // FIXME parametros
    }



    /**
     * Fija el valor de una variable común
     *
     * @param  string       $name                   Nombre de la variable
     * @param  string       $value                  Valor de la variable
     * @param  bool         $queryBBchannel         Indica si se debe asignar la variable en el BotBasicChannel actual o si, por otro lado, se debe hacer en el runtime
     * @param  bool         $force                  Indica si se debe evitar el chequeo de si la variable es común
     */
    public function setCommonVar ($name, $value, $queryBBchannel = true, $force = false)
    {
        if (! $force && ! $this->isCommonVar($name)) {
            Log::register(Log::TYPE_RUNTIME, "RT1399 Intentando fijar el valor de la variable no-comun $name", $this);
        }
        if ($queryBBchannel) { $this->getCurrentBBchannel()->setCommonVar($name, $value, $this); }
        else                 { $this->vars[$name] = [ true, $value ];                            }
    }



    /**
     * Anula el valor de una variable del programa BotBasic, o anula su registro en el espacio de variables, dentro de la estructura de
     * nombres de variables del runtime
     *
     * @param  string   $name
     * @param  bool     $deleteNotInitialize
     */
    public function resetCommonVar ($name, $deleteNotInitialize)
    {
        if (! $this->isCommonVar($name)) {
            Log::register(Log::TYPE_RUNTIME, "RT1417 Intentando resetear el valor de la variable no-comun $name", $this);
        }
        $this->vars[$name] = [ true, $deleteNotInitialize ? null : self::NOTHING ];
    }



    /**
     * Ejecuta la semántica de un RESET ALL, pudiendo también ejecutar el equivalente a un RESET ALL CHANNEL sobre cada canal
     *
     * Actualmente NO resetea el valor de las variables mágicas (ver getAllVarNames()).
     *
     * Este método actualiza también el código de la BB app cuando hay un label asociado a la línea de código en ejecución.
     *
     * @param  int      $lineno             Número de línea del programa BotBasic; el valor se ignora si $forceUpdate es true
     * @param  string   $bot                Bot del programa BotBasic
     * @param  bool     $forceUpdate        Si es true, se efectuará la actualización del código de la BB app aunque no haya un label asociado al lineno
     * @param  bool     $includeChannels    Indica si se debe ejecutar el RESET ALL CHANNEL sobre cada canal asociado al runtime
     * @param  bool     $dontUpdateBBcode   Si es true se cancelará la actualización semántica del código del programa BotBasic
     */
    private function resetAllHelper ($lineno, $bot, $forceUpdate = false, $includeChannels = true, $dontUpdateBBcode = false)
    {
        // fill default vars' values when null is passed
        if ($includeChannels === null) { $includeChannels = true; }
        // reset main vars
        $names = $this->getAllVarNames();
        foreach ($names as $name) {
            //if ($this->isMagicVar($name)) { $this->setMagicVar($name, self::NOTHING, $lineno, $bot); }
            //else                          { $this->resetCommonVar($name, true);                      }
            // it was defined later that magic vars won't be cleared with a RESET ALL or /start so previous lines were replaced by the next one
            if ($this->isCommonVar($name)) { $this->resetCommonVar($name, true); }
        }
        // optionally reset channels' vars and call stack
        if ($includeChannels) {
            foreach ($this->getBBchannels() as $bbc) { $bbc->resetAllChannelHelper(); }
        }
        // now force major update only if: (a) force; or (b) current lineno has a associated label (any will work)
        if (($forceUpdate || ! $forceUpdate && array_search($lineno, array_values($this->bots[$bot]['labels'])) !== false) && ! $dontUpdateBBcode) {
            $this->updateBBcode(true);
        }

    }



    /**
     * Resuelve los templates encontrados dentro del valor de una variable del programa BotBasic y devuelve el valor resuelto
     *
     * @param  string       $spec           Valor de la variable, con o sin templates
     * @param  int          $lineno         Línea del programa BotBasic en ejecución
     * @param  string       $bot            Nombre del bot
     * @param  string       $lvalName       Nombre de la variable cuyo valor se pasa
     * @return mixed|null                   Valor resuelto; o null si hay errores de resolución
     */
    private function processMessageSpec ($spec, $lineno, $bot, $lvalName)
    {
        // determine the replacements
        $toReplace = [];
        while (($pos1 = strrpos($spec, '{')) !== false) {
            $pos2 = strpos($spec, '}', $pos1 + 1);
            if ($pos2 === false) {
                Log::register(Log::TYPE_BBCODE, "RT1440 Texto en '$lvalName' invalido: '{','}' desbalanceados: [$spec]", $this, $lineno);
                return null;
            }
            if ($pos2 == $pos1 + 1) {
                Log::register(Log::TYPE_BBCODE, "RT1444 Texto en '$lvalName' invalido: template vacio: [$spec]", $this, $lineno);
                return null;
            }
            $name = substr($spec, $pos1 + 1, $pos2 - $pos1 - 1);
            if (! ($this->isPrimitive($name) || $this->isLvalue($name))) {
                Log::register(Log::TYPE_BBCODE, "RT1449 Texto en '$lvalName' invalido: '$name' no es primitiva o variable: [$spec]", $this, $lineno);
                return null;
            }
            if ($this->isPrimitive($name) && ! $this->checkPrimitiveArgcounts($name, 0, 1)) {
                Log::register(Log::TYPE_BBCODE, "RT1453 Texto en '$lvalName' invalido: '$name' debe ser una primitiva con 0 argumentos IN y 1 argument OUT: [$spec]", $this, $lineno);
                return null;
            }
            if ($this->isPrimitive($name)) { $res = $this->callPrimitive($name, [], $lineno, $bot); $res = ! is_array($res) || $res[0] === null ? self::NOTHING : $res[0]; }
            else                           { $res = $this->getVar($name, $lineno, $bot, false);                                                                            }
            $toReplace[] = [ $pos1, $pos2 - $pos1 + 1, $res ];
            $spec = substr_replace($spec, '<', $pos1, 1);   // this and next line: avoid matching of {} in the next iteration
            $spec = substr_replace($spec, '>', $pos2, 1);
        }
        // do the replacements
        foreach ($toReplace as $what) {
            list ($pos, $len, $res) = $what;
            $spec = substr_replace($spec, $res, $pos, $len);
        }
        return $spec;
    }



    /**
     * Invoca a una primitiva del programa BotBasic
     *
     * @param  string               $primitive      Nombre de la primitiva
     * @param  string[]             $args           Argumentos de la invocación
     * @param  int                  $lineno         Línea de código en ejecución del programa BotBasic
     * @param  string               $bot            Nombre del bot
     * @return string[]                             Valores retornados por la primitiva, o NOTHING si no se consigue el método en BizModelAdapter
     */
    private function callPrimitive ($primitive, $args, $lineno, $bot)
    {
        $normalize = function (&$val)
        {
            $val = is_numeric($val) || is_string($val) ? $val : (
                   $val === true  ? 1 : (
                   $val === false ? 0 :
                   self::NOTHING ));
        };

        $this->doDummy($bot);
        if (! $this->isPrimitive($primitive)) {
            return [ self::NOTHING ];
        }
        $accessor = self::PRIMITIVES_PHPACCESSOR_PREFIX . $primitive;
        if ($this->bma() === null) {
            Log::register(Log::TYPE_RUNTIME, "RT1811 BizModelAdapter nulo", $this, $lineno);
            return [ self::NOTHING ];
        }
        if (! method_exists($this->bma(), $accessor)) {
            Log::register(Log::TYPE_BBCODE, "RT1487 No se puede hallar el metodo '$accessor' en el objeto BizModelAdapter", $this, $lineno);
            return [ self::NOTHING ];
        }
        $res = $this->bma()->$accessor($args, $this->buildBMAcallMetadata($lineno));
        if (! is_array($res)) { $res = [ $res ]; }
        for ($i = 0; $i < count($res); $i++) {
            if (! is_array($res[$i])) { $normalize($res[$i]);                                                  }
            else                      { for ($j = 0; $j < count($res[$i]); $j++) { $normalize($res[$i][$j]); } }
        }
        return $res;
    }



    /**
     * Invoca al método de BizModelAdapter correspondiente a un menú predefinido del programa BotBasic
     *
     * @param  string           $menuName       Nombre del menú predefinido
     * @param  string[]         $args           Argumentos del menú predefinido
     * @param  string           $titles         Títulos del menú
     * @param  array            $options        Textos de las opciones del menú; si vienen codificadas se les extraerá el texto antes de invocar al método
     * @param  array            $pager          Especificación del paginador, como: [ pagerSpec, pagerArgs ]
     * @param  int              $lineno         Número de línea del programa BotBasic que se ejecuta
     * @param  string           $bot            Nombre del bot
     * @param  BotBasicChannel  $bbChannel      BotBasicChannel que determina el inicio de la cadena de las variables de BotBasic que serán usadas
     * @param  mixed            $contextObject  Objeto asociado a la rutina que implementa el menú predefinido y que le sirve de contexto
     * @param  string|null      $key            Si no es null, se trata de una invocación "step 2" que viene con la activación de la opción de menú de este valor
     * @return null|string[]                    Valores de retorno producidos por el menú para ser asignados a variables; o null si no la interacción definida
     *                                          por el método del menú predefinido en BizModelAdapter no ha terminado aún
     */
    public function callMenu ($menuName, $args, $titles, $options, $pager, $lineno, $bot, $bbChannel, $contextObject, $key = null)
    {
        $this->doDummy($bot);
        if (! $this->isMenu($menuName)) {
            Log::register(Log::TYPE_RUNTIME, "RT1518 $menuName no es un menu predefinido registrado", $this, $lineno);
            return null;
        }
        if (count($options) > 0 && is_array($options[0])) {
            $theOptions = [];
            foreach ($options as $codifiedOption) { $theOptions[] = $codifiedOption[1]; }   // each is: [ key, value, ... ]
        }
        else {
            $theOptions = $options;
        }
        $accessor = self::MENUS_PHPACCESSOR_PREFIX . $menuName;
        if ($this->bma() === null) {
            Log::register(Log::TYPE_RUNTIME, "RT1889 BizModelAdapter nulo", $this, $lineno);
            return null;
        }
        elseif (! method_exists($this->bma(), $accessor)) {
            Log::register(Log::TYPE_BBCODE, "RT1530 No se puede hallar el metodo '$accessor' en el objeto BizModelAdapter", $this, $lineno);
            return null;
        }
        $res = $bbChannel->getBBruntime()->bma()->$accessor($args, $titles, $theOptions, $pager, $contextObject, $key, $this->buildBMAcallMetadata($lineno));
        if ($res !== null && ! is_array($res)) { $res = [ $res ]; }
        return $res;
    }



    /**
     * Construye un arreglo de metadatos que puede ser pasado a las rutinas del BizModelAdapter
     *
     * @param  int      $lineno     Número de línea en ejecución
     * @return array                Arreglo indexado por claves tipo string, con los índices:
     *                              codename, codeversion, codebot, codeline, runtimeid, channelid, bizmodeluserid
     */
    private function buildBMAcallMetadata ($lineno)
    {
        $res = [
            'codename'          => $this->getBBcodename(),
            'codeversion'       => $this->bbCodeMajorVersion . '.' . $this->bbCodeMinorVersion . '.' . $this->bbCodeSubminorVersion,
            'codebot'           => $this->getBBbotName(),
            'codeline'          => $lineno,
            'runtimeid'         => $this->id,
            'channelid'         => $this->getCurrentBBchannel()->getId(),
            'bmuserid'          => $this->bmUserId,
        ];
        return $res;
    }



    /**
     * Procede a la continuación de la interpretación de una directiva INPUT ya previamente mostrada en una chatapp
     *
     * @param string                    $bot        Nombre del bot
     * @param int                       $lineno     Número de línea en ejecución del programa BotBasic
     * @param string                    $dataType   Tipo de dato, uno de: 'date', 'positiveInteger', 'positiveDecimal', 'string'
     * @param string[]|null             $titles     Títulos del menú; o null si no hay
     * @param string|null               $word       Palabra opcional que al ser introducida indica que se debe usar el valor por defecto
     * @param int                       $bbcId      ID del BotBasicChannel sobre el que debe afectarse el valor de la variable destino y ejecutarse
     *                                              la continuidad de la corrida del código del programa BotBasic luego de aceptar la entrada
     * @param array                     $toVars     Nombres de la variables del programa BotBasic que deben ser afectadas con el valor de la entrada
     *                                              y sus atributos
     * @param string|null               $fromValue  Valor por defecto, opcional
     * @param string                    $text       Texto introducido por el usuario en la chatapp, o caption del resource que se recibió
     * @param InteractionResource|null  $resource   Resource principal del update que se recibió, si aplica
     */
    private function invokeInputStep2 ($bot, $lineno, $dataType, $titles, $word, $bbcId, $toVars, $fromValue, $text, $resource)
    {
        $validateDate = function (&$date)
        {
            $months       = BotConfig::monthsOfYear($this->locale);
            $monthsShort  = array_keys($months);
            $monthsLong   = array_values($months);
            $monthsRegexp = implode('|', array_merge($monthsShort, $monthsLong));
            $matches      = [];
            if (1 === preg_match('/^([0-9]{1,2}) *([\/-]) *([0-9]{1,2}|' . $monthsRegexp . ') *\2 *([0-9]{2}|[0-9]{4})$/', strtolower($date), $matches)) {
                list (, $dd, , $mm, $yy) = $matches;
                if (! is_numeric($mm)) {
                    $pos = array_search($mm, $monthsShort);
                    if ($pos !== false) {                                        $mm = $pos + 1; }
                    else                { $pos = array_search($mm, $monthsLong); $mm = $pos + 1; }
                }
                if (strlen($yy) == 2) {
                    $thisYear = date('y');
                    if ($yy <= $thisYear + 1) { $yy = 2000 + $yy; }   // holgura de 1 año para especificacion de fechas futuras
                    else                      { $yy = 1900 + $yy; }
                }
                if (strlen($mm) == 1) { $mm = "0" . $mm; }
                if (strlen($dd) == 1) { $dd = "0" . $dd; }
                $res = checkdate($mm, $dd, $yy);
                if ($res === true) { $date = "$dd-$mm-$yy"; }
                return $res;
            }
            return false;
        };

        $validateInteger = function (&$number, $nonNegative)
        {
            if ($this->locale == 'en') { $gs = ','; $gsre = ',' ; }
            else                       { $gs = '.'; $gsre = '\.'; }
            $matches = [];
            if (1 === preg_match('/^(' . ($nonNegative ? '' : '-?') . '[0-9]{1,3}(?:' . $gsre . '[0-9]{3}|[0-9]{3})*)$/', $number, $matches)) {
                list (, $int) = $matches;
                $int    = str_replace($gs, '', $int);
                $number = intval($int);
                return true;
            }
            return false;
        };

        $validateDecimal = function (&$number, $nonNegative) use ($validateInteger)
        {
            if ($validateInteger($number, $nonNegative)) { return true; }
            if ($this->locale == 'en') { $gs = ','; $gsre = ',' ; $dsre = '\.'; }
            else                       { $gs = '.'; $gsre = '\.'; $dsre = ',' ; }
            $matches = [];
            if (1 === preg_match('/^(' . ($nonNegative ? '' : '-?') . '[0-9]{1,3}(?:' . $gsre . '[0-9]{3}|[0-9]{3})*)' . $dsre . '([0-9]+)$/', $number, $matches)) {
                list (, $int, $frac) = $matches;
                $int    = str_replace($gs, '', $int);
                $number = floatval($int . '.' . $frac);
                return true;
            }
            return false;
        };

        $validateString = function (&$string)
        {
            $this->doDummy($string);
            return true;
        };

        $validatePhone = function (&$phone)
        {
            $matches = [];
            if (1 === preg_match('/^(\+?)([0-9]{1,3})[ \-]?([0-9]{3,4})[ \-]?([0-9]{3,4})$/', $phone, $matches)) {   // garantiza 7-11 digitos
                list (, , $country, $local1, $local2) = $matches;
                $number = $country . $local1 . $local2;
                $phone  = strlen($number) > 8 ? "+$number" : $number;
                return true;
            }
            return false;
        };

        $validateEmail = function (&$email)
        {
            // taken from http://emailregex.com/
            if (1 === preg_match('/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD', $email)) {
                return true;
            }
            return false;
        };

        $validateArrobaUsername = function (&$username)
        {
            //$forTwitter  = '/^@[A-Za-z0-9_]{1,15}$/';
            //$forTelegram = '/^@[A-Za-z0-9_]{5,32}$/';
            $forBoth     = '/^@[A-Za-z0-9_]{1,32}$/';
            if (1 === preg_match($forBoth, $username)) {
                return true;
            }
            return false;
        };

        $validateResourceType = function (&$resource, $type)
        {
            return $resource === null ? false : $resource->type === $type;
        };

        $validateImage = function (&$resource) use ($validateResourceType)
        {
            return $validateResourceType($resource, InteractionResource::TYPE_IMAGE);
        };

        $validateAudio = function (&$resource) use ($validateResourceType)
        {
            return $validateResourceType($resource, InteractionResource::TYPE_AUDIO);
        };

        $validateVoice = function (&$resource) use ($validateResourceType)
        {
            return $validateResourceType($resource, InteractionResource::TYPE_VOICE);
        };

        $validateDocument = function (&$resource) use ($validateResourceType)
        {
            return $validateResourceType($resource, InteractionResource::TYPE_DOCUMENT);
        };

        $validateVideo = function (&$resource) use ($validateResourceType)
        {
            return $validateResourceType($resource, InteractionResource::TYPE_VIDEO);
        };

        $validateLocation = function (&$resource) use ($validateResourceType)
        {
            if (! $validateResourceType($resource, InteractionResource::TYPE_LOCATION)) {
                return false;
            }
            if (is_string($resource)) {   // this method can re-enter
                $parts = explode(',', $resource);
                if (count($parts) != 2) { return false; }
                $resource = [ 'latitude' => (float)$parts[0], 'longitude' => (float)$parts[1] ];
            }
            /** @var InteractionResource $resource */
            $ok = isset($resource->metainfo['latitude']) && isset($resource->metainfo['longitude']) &&
                  is_float($resource->metainfo['latitude']) && is_float($resource->metainfo['longitude']);
            if (! $ok) { return false; }
            $resource = $resource->metainfo['latitude'] . ',' . $resource->metainfo['longitude'];
            return true;
        };

        // check if input is correct, normalize input data and determine input datatype
        $text = trim($text);
        if ($text === $word && $fromValue !== null) { $text = $fromValue; }
        $type = $dataType;
        switch ($dataType) {
            case 'date'            : $ok = $validateDate($text);                                    break;
            case 'integer'         : $ok = $validateInteger($text, false);                          break;
            case 'decimal'         : $ok = $validateDecimal($text, false);                          break;
            case 'positiveInteger' : $ok = $validateInteger($text, true);                           break;
            case 'positiveDecimal' : $ok = $validateDecimal($text, true);                           break;
            case 'string'          : $ok = $validateString($text);                                  break;
            case 'phone'           : $ok = $validatePhone($text);                                   break;
            case 'email'           : $ok = $validateEmail($text);                                   break;
            case 'arrobaUsername'  : $ok = $validateArrobaUsername($text);                          break;
            case 'image'           : $ok = $validateImage($resource);                               break;
            case 'audio'           : $ok = $validateAudio($resource);                               break;
            case 'voice'           : $ok = $validateVoice($resource);                               break;
            case 'document'        : $ok = $validateDocument($resource);                            break;
            case 'video'           : $ok = $validateVideo($resource);                               break;
            case 'location'        : if ($ok = $validateLocation($resource)) { $text = $resource; } break;
            default                :
                if ($dataType == 'any' && $resource === null) {   // $text !== null
                    $ok   = true;
                    $type = 'text';
                }
                elseif ($resource === null) {
                    $ok   = false;
                    $type = null;
                }
                elseif ($dataType == 'any') {
                    $ok = true;
                }
                elseif ($dataType == 'sound') {
                    $isAudio = $validateAudio($resource);
                    $isVoice = $validateVoice($resource);
                    $ok = $isAudio || $isVoice;
                }
                elseif ($dataType == 'clip') {
                    $isAudio = $validateAudio($resource);
                    $isVoice = $validateVoice($resource);
                    $isVideo = $validateVideo($resource);
                    $ok = $isAudio || $isVoice || $isVideo;
                }
                elseif ($dataType == 'visual') {
                    $isImage = $validateImage($resource);
                    $isVideo = $validateVideo($resource);
                    $ok = $isImage || $isVideo;
                }
                elseif ($dataType == 'media') {
                    $isImage = $validateImage($resource);
                    $isAudio = $validateAudio($resource);
                    $isVoice = $validateVoice($resource);
                    $isDoc   = $validateDocument($resource);
                    $isVideo = $validateVideo($resource);
                    $ok = $isImage || $isAudio || $isVoice || $isDoc || $isVideo;
                }
                else {
                    $ok = false;
                    Log::register(Log::TYPE_RUNTIME, "RT2153 datatype '$dataType' no soportado en INPUT", $this, $lineno);
                }
                if     ($ok && $resource !== null) { $type = InteractionResource::typeString($resource->type); }
                elseif (! $ok)                     { $type = null;                                             }
        }

        // bad entry; must re-prompt the user
        if ($ok !== true) {
            $this->getCurrentBBchannel()->enqueueRouteInput($bot, $lineno, $dataType, $titles, $word, $bbcId, $toVars, $fromValue, true, true, true);
        }

        // entry must be accepted; modify toVars value and then continue program execution
        else {
            // this channel's acions
            $this->getCurrentBBchannel()->popRoute();
            $this->getCurrentBBchannel()->followRoutes();
            // target var channel's actions
            $bbc = BotBasicChannel::load($bbcId);
            if ($bbc === null) {}   // do nothing (a BBC was deleted by program logic before this entry processing moment comes)
            else {
                $rt = $bbc->getBBruntime();
                // assign value
                if ($resource !== null) { $rt->setVar($toVars[0], $resource->id,                                                $lineno, $bot); }
                else                    { $rt->setVar($toVars[0], $text,                                                        $lineno, $bot); }
                // assign datatype
                if (isset($toVars[1]))  { $rt->setVar($toVars[1], $type !== null ? $type : self::NOTHING,                       $lineno, $bot); }
                // assign caption
                if (isset($toVars[2]))  { $rt->setVar($toVars[2], $text !== null && $resource !== null ? $text : self::NOTHING, $lineno, $bot); }
                // continue execution
                $nextLineno = $rt->nextLineno($bot, $lineno);
                if ($nextLineno !== null) {
                    $bbc->setAsCurrent();
                    $rt->runPart($nextLineno);
                }
            }
        }
    }



    /**
     * Procede a la continuación de la interpretación de una directiva MENU ya previamente mostrada en una chatapp
     *
     * @param string            $bot                        Nombre del bot
     * @param int               $lineno                     Número de línea en ejecución del programa BotBasic
     * @param string|null       $menuName                   Nombre del menú predefinido; o null si se trata de un menu estándar
     * @param string[]|null     $menuArgs                   Argumentos del menú predefinido, si fuera aplicable
     * @param string[]          $titles                     Títulos del menú
     * @param array             $options                    Todas las opciones del menú codificadas según splashHelperMenuMakeOption()
     * @param array|null        $pager                      Paginador, en forma: [ pagerSpec, pagerArg ]; o null si no aplica
     * @param mixed|null        $contextObject              Objeto que sirve de contexto al menú predefinido, si aplica
     * @param int               $bbcId                      ID del BotBasicChannel sobre el que debe afectarse los valores de las variables destino y ejecutarse
     *                                                      la continuidad de la corrida del código del programa BotBasic luego de aceptar la entrada
     * @param string|string[]   $toVars                     Nombre de la o las variables del programa BotBasic que deben ser afectadas con el valor de la entrada
     *                                                      (menus estándar) o los valores de las entradas y cálculos (menus predefinidos)
     * @param string            $key                        Clave proveniente del menuhook que permite acceder en BD al contenido de la opción de menú seleccionada
     * @param string|null       $gotoOrGosub                Indicación de flujo de ejecución: 'GOTO', 'GOSUB' o null
     * @param int|null          $gotoOrGosubTargetLineno    Número de línea destino para OPTION GOTO/GOSUB
     */
    private function invokeMenuStep2 ($bot, $lineno, $menuName, $menuArgs, $titles, $options, $pager, $contextObject, $bbcId, $toVars, $key, $gotoOrGosub, $gotoOrGosubTargetLineno)
    {
        // check for an invalid pressed option
        $matchedOption = null;
        foreach ($options as $option) {
            if ($option[0] == $key) { $matchedOption = $option; break; }
        }
        if ($matchedOption === null) {
            Log::register(Log::TYPE_RUNTIME, "RT1678 Opcion de menu presionada ($key) no esta en la definicion de sus opciones", $this, $lineno);
            return;
        }

        // this channel's acions
        $this->getCurrentBBchannel()->popRoute();
        $this->getCurrentBBchannel()->followRoutes();

        // target var channel's actions
        $bbc = BotBasicChannel::load($bbcId);
        if ($bbc === null) {
            // do nothing (a BBC was deleted by program logic before this menu option processing moment comes)
            return;
        }
        $rt = $bbc->getBBruntime();

        // standard menu case
        if ($menuName === null) {
            $toVar = $toVars;
            $rt->setVar($toVar, $key, $lineno, $bot);
            $derive = true;
        }

        // predefined menu case
        else {
            $retVals = $rt->callMenu($menuName, $menuArgs, $titles, $options, $pager, $lineno, $bot, $bbc, $contextObject, $key);
            if ($retVals === null) {
                $derive = false;
            }
            else {
                if (! is_array($retVals)) { $retVals = [ $retVals ]; }
                for ($i = 0; $i < count($toVars); $i++) {
                    $toVar = $toVars[$i];
                    $toVal = isset($retVals[$i]) ? $retVals[$i] : self::NOTHING;
                    $rt->setVar($toVar, $toVal, $lineno, $bot);
                }
                $derive = true;
            }
        }

        // derive program flow execution if menu execution was completed (trivial (true ever) for standard menus)
        if ($derive) {
            if (strtoupper($gotoOrGosub) == 'GOSUB') {
                $bbc->callStackPush($lineno, null, null);
                $nextLineno = $gotoOrGosubTargetLineno;
            }
            elseif (strtoupper($gotoOrGosub) == 'GOTO') {
                $nextLineno = $gotoOrGosubTargetLineno;
            }
            else {   // no OPTION ... GOSUB/GOTO
                $nextLineno = $rt->nextLineno($bot, $lineno);
            }
            if ($nextLineno !== null) {
                $bbc->setAsCurrent();
                $rt->runPart($nextLineno);
            }
        }
    }



    //////////////////////
    // NAMESPACE UTILITIES
    //////////////////////



    /**
     * Convierte un conjunto de rvals del programa BotBasic a sus respectivos valores
     *
     * @param  string[]     $arrayOfRvals       Rvals
     * @param  int          $lineno             Línea del programa BotBasic en ejecución
     * @param  string       $bot                Nombre del bot
     * @return string[]                         Valores obtenidos
     */
    private function rvals2values ($arrayOfRvals, $lineno, $bot)
    {
        $res = [];
        foreach ($arrayOfRvals as $rval) { $res[] = $this->getRvalValue($rval, $lineno, $bot); }
        return $res;
    }



    /**
     * Obtiene el valor de un rval (un rval es una expresión-token que puede aparecer en el lado derecho de una asignación o en el izquierdo)
     *
     * @param  string   $rval       Expresión del rval
     * @param  int      $lineno     Línea del programa BotBasic en ejecución
     * @param  string   $bot        Nombre del bot
     * @return string               Valor del rval
     */
    private function getRvalValue ($rval, $lineno, $bot)
    {
        if     ($this->isExpressionDirective($rval)) { $method = "runner4" . strtolower($rval); $val = $this->$method();                                                             }
        elseif ($this->isNumber($rval))              { $val = $rval;                                                                                                                 }
        elseif ($this->isNoargsPrimitive($rval))     { $res = $this->callPrimitive($rval, [], $lineno, $bot); $val = ! is_array($res) || count($res) == 0 ? self::NOTHING : $res[0]; }
        elseif ($this->isMagicVar($rval))            { $val = $this->getLvalValue($rval, $lineno, $bot);                                                                             }
        elseif ($this->isCommonVar($rval))           { $val = $this->getLvalValue($rval, $lineno, $bot);                                                                             }
        elseif ($this->isMessageName($rval))         { $val = $this->getLvalValue($rval, $lineno, $bot);                                                                             }
        else {
            $val = self::NOTHING;
            Log::register(Log::TYPE_BBCODE, "RT1779 Error evaluando rvalue ['$rval'/TYPE:" . gettype($rval) . "]", $this, $lineno);
        }
        return $val;
    }



    /**
     * Obtiene el valor de un lval (un rval es una expresión-token que sólo puede aparecer en el lado izquierdo de una asignación: una variable)
     *
     * @param  string   $lval       Expresión del lval
     * @param  int      $lineno     Línea del programa BotBasic en ejecución
     * @param  string   $bot        Nombre del bot
     * @return string               Valor del lval
     */
    private function getLvalValue ($lval, $lineno, $bot)
    {
        return $this->getVar($lval, $lineno, $bot);
    }



    /**
     * Obtiene el texto del Update de de entrada del runtime
     *
     * @param  bool     $canReturnNull      Indica si se retornará null en vez de NOTHING en caso de no estar fijado el Update
     * @return string
     */
    private function getEntryText ($canReturnNull = false)
    {
        if ($this->update === null) {
            return $canReturnNull ? null : self::NOTHING;
        }
        elseif ($this->update->getType() != Interaction::TYPE_UPDATE) {
            Log::register(Log::TYPE_RUNTIME, "RT1809 La Interaction de entrada no es de tipo Update", $this);
            return $canReturnNull ? null : self::NOTHING;
        }
        return $this->update->getText();
    }



    /**
     * Obtiene el contenido de un menuhook tal como viene en el Update de entrada del runtime
     *
     * @return null|string      Contenido del menuhook; o null en caso de que no esté asignado
     */
    public function getMenuhook ()
    {
        if ($this->update === null) {
            return self::NOTHING;
        }
        elseif ($this->update->getType() != Interaction::TYPE_UPDATE) {
            Log::register(Log::TYPE_RUNTIME, "RT1825 La Interaction de entrada no es de tipo Update", $this);
            return null;
        }
        return $this->update->getMenuhook();
    }



    /**
     * Obtiene el contenido de un "event command" de un "fake update" asociado a eventos de tiempo del runtime
     *
     * @return null|string      Contenido del event command; o null en caso de que no esté asignado
     */
    private function getEventCommand ()
    {
        if ($this->update === null) {
            return self::NOTHING;
        }
        elseif ($this->update->getType() != Interaction::TYPE_UPDATE) {
            Log::register(Log::TYPE_RUNTIME, "RT1841 La Interaction de entrada no es de tipo Update", $this);
            return null;
        }
        return $this->update->getEventCommand();
    }



    ////////////////////////////////
    // RUNNER AND ITS HELPER METHODS
    ////////////////////////////////



    /**
     * Procesador del runtime para runWith()
     *
     * @param  string   $bot                Nombre del bot
     * @param  int      $lineno             Número de línea en ejecución
     * @param  array    $parsedContent      Contenido del número de línea procesado por el parser
     * @return bool                         true
     */
    public function runner ($bot, $lineno, &$parsedContent)
    {
        if (! is_array($parsedContent)) { $parsedContent = [ $parsedContent ]; }   // caso "IF EQ 1 1 THEN REM" --> "Directiva R invalida"
        $directive = $parsedContent[0];
        $newLineno = -1;
        if (($supertrace = true) && $this->trace && $directive != 'REM') {
            Log::register(Log::TYPE_DEBUG, "TRACE @ $bot:$lineno :: $directive");   // FIXME esto deberia ser lo que haga un SUPERTRACE; no muestra los IF porque runner() no es llamado en caso de IF's por super::descender()
        }
        if (! isset($this->tokensByName[$directive])) {
            Log::register(Log::TYPE_RUNTIME, "RT1874 Directiva $directive invalida", $this, $lineno);
        }
        else {
            $method = $this->tokensByName[$directive][1];
            $method = "runner4" . $method;
            if (! method_exists($this, $method)) {
                Log::register(Log::TYPE_RUNTIME, "RT1880 Metodo $method no existe", $this, $lineno);
            }
            else {
                $newLineno = $this->$method($parsedContent, $lineno, $bot);   // no return value needed
            }
        }
        return $newLineno;
    }



    /**
     * Retorna un triplete de especificación de destino de interacciones (ON ...) a partir de una heurística de completación en caso de
     * argumentos incompletos
     *
     * Cuando uno de los dos últimos argumentos es null, el otro también lo debe ser.
     *
     * @param  null|string  $botName            Nombre del bot; o null para derivar
     * @param  null|int     $bizModelUserId     ID del BizModel user; o null para derivar
     * @param  null|int     $bbChannelId        ID del canal del programa BotBasic; o null para derivar
     * @return array|null                       null si se pasa una combinación errónea de argumentos;
     *                                          [ botName, bizModelUserId, bbChannelId ] en caso de éxito
     */
    public function completeOn ($botName = null, $bizModelUserId = null, $bbChannelId = null)
    {
        $exit = function ($line, $msg = 'Combinacion invalida de parametros de entrada en completeOn', $isBBappError = false) use ($botName, $bizModelUserId, $bbChannelId) {
            Log::register($isBBappError ? Log::TYPE_BBCODE : Log::TYPE_RUNTIME, "$line $msg: " .
                "[bot="       . ($botName        === null ? "NULL" : $botName)        .
                "|bmUid="     . ($bizModelUserId === null ? "NULL" : $bizModelUserId) .
                "|chId="      . ($bbChannelId    === null ? "NULL" : $bbChannelId)    .
                "|thisBmUid=" . ($this->bmUserId === null ? "NULL" : $this->bmUserId) . "]"
                , $this);
            // $e = new \Exception; Log::register(Log::TYPE_DEBUG, "call stack:\n" . $e->getTraceAsString());
            return null;
        };

        if (!(  $botName === null                                      && $bizModelUserId === null    && $bbChannelId === null
             || $botName !== null && $botName == $this->getBBbotName() && $bizModelUserId === null    && $bbChannelId === null
             || $botName !== null && $botName == $this->getBBbotName() && $bizModelUserId === null    && is_numeric($bbChannelId)
             || $botName !== null && $botName == $this->getBBbotName() && is_numeric($bizModelUserId) && $bbChannelId === null
             || $botName !== null && $botName == $this->getBBbotName() && is_numeric($bizModelUserId) && is_numeric($bbChannelId)
             || $botName !== null && $botName != $this->getBBbotName() && is_numeric($bizModelUserId) && $bbChannelId === null
             || $botName !== null && $botName != $this->getBBbotName() && is_numeric($bizModelUserId) && is_numeric($bbChannelId)
           )) {
            return $exit("RT2250");
        }
        if ($botName === null) {
            $botName = $this->getBBbotName();
        }
        if ($bbChannelId === null) {
            if (    $bizModelUserId === null || $bizModelUserId == $this->bmUserId ||
                    $botName == $this->getBBbotName() && $this->getCurrentBBchannel()->isDefaultBBchannel()) {
                $bbChannelId = $this->getCurrentBBchannel()->getId();
            }
            elseif ($bizModelUserId !== null) {
                //new code:
                $bbBotIdx = ChatMedium::getBBbotIndexByOtherBBbotSameBBapp($this->bbBotIdx, $botName);
                if ($bbBotIdx === null) { return $exit("RT2364"); }
                $rt = BotBasicRuntime::loadByBizModelUserId($bbBotIdx, $bizModelUserId);
                if (! ($rt === false || $rt === null)) { $bbChannelId = $rt->getCurrentBBchannel()->getId();                           }
                else                                   { return $exit("RT2282", "No se puede cargar el Runtime segun bmUserId", true); }
                //old code:
                //$cmBotsPairs = ChatMedium::getDefaultCMbots($botName, $this->getBBcodename());
                //$cmBotNames  = [];
                //foreach ($cmBotsPairs as $pair) { $cmBotNames[] = $pair[1]; }
                //$res = DBbroker::readDefaultChannelIdByBizModelUserId($bizModelUserId, $cmBotNames);
                //if     ($res === null ) { Log::register(LOG::TYPE_DATABASE, "RT2256 Error de BD");                                                                 }
                //elseif ($res === false) { Log::register(LOG::TYPE_RUNTIME,  "RT2257 BizModelUserId $bizModelUserId no esta registrado en BD para ningun usuario"); }
                //if ($res) { $bbChannelId = $res; }
            }
            else {
                return $exit("RT2266");
            }
        }
        if ($bizModelUserId === null) {
            $bizModelUserId = $this->bmUserId;
        }
        return [ $botName, $bizModelUserId, $bbChannelId ];
    }



    /**
     * Elimina los valores asignados por TITLE, OPTION, OPTIONS, PAGER y ON como conclusión de la ejecución de un INPUT o MENU
     *
     * @param bool $resetAlsoWordDirective  Elimina adicionalmente el valor asignado por WORD
     */
    public function resetAfterSplash ($resetAlsoWordDirective = false)
    {
        $this->menuAndInputTitles = [];
        $this->menuOptions        = [];
        $this->menuPager          = [ null, null ];
        $this->on                 = $this->completeOn();
        if ($resetAlsoWordDirective) { $this->word = null; }
        $this->tainting(true);
    }



    /**
     * Permite el preencolamiento hacia la chatapp de un PRINT; este método es para ser usado por runner4...() y por el BizModelAdapter
     *
     * @param string        $text               Texto a mostrar
     * @param null|string   $botName            Nombre del bot sobre el cual aplicar el Splash; o null para derivarlo
     * @param null|int      $bizModelUserId     ID del BizModel user sobre el cual aplicar el Splash; o null para derivarlo
     * @param null|int      $bbChannelId        ID del BotBasicChannel sobre el cual aplicar el Splash; o null para derivarlo
     */
    public function splashHelperPrint ($text, $botName = null, $bizModelUserId = null, $bbChannelId = null)
    {
        $on = $this->completeOn($botName, $bizModelUserId, $bbChannelId);
        if ($on === null) { return; }
        $rt = $this;
        //use these lines instead of the previous one for affecting target rt's print[] (works bad with INPUT and MENU)
        //$bbc = BotBasicChannel::load($on[2]);
        //if ($bbc === null) { return; }
        //$rt = $bbc->getBBruntime();
        $rt->prints[] = [ $text, $on ];
    }



    /**
     * Permite el preencolamiento hacia la chatapp de un DISPLAY; este método es para ser usado por runner4...()
     *
     * @param string        $id                 ID del Resource, el cual debe tener asociado un archivo o un Telegram file_id
     * @param null|string   $caption            Caption del contenido multimedia
     * @param null|string   $botName            Nombre del bot sobre el cual aplicar el Splash; o null para derivarlo
     * @param null|int      $bizModelUserId     ID del BizModel user sobre el cual aplicar el Splash; o null para derivarlo
     * @param null|int      $bbChannelId        ID del BotBasicChannel sobre el cual aplicar el Splash; o null para derivarlo
     */
    public function splashHelperDisplay ($id, $caption, $botName = null, $bizModelUserId = null, $bbChannelId = null)
    {
        if (! BotBasic::isPositiveInteger($id)) { return; }
        $on = $this->completeOn($botName, $bizModelUserId, $bbChannelId);
        if ($on === null) { return; }
        $entry = [ 'type' => 'resource', 'id' => $id ];
        if ($caption !== null) { $entry['caption'] = $caption; }
        $this->prints[] = [ $entry, $on ];
    }



    /**
     * Permite el preencolamiento hacia la chatapp de un MENU; este método es para ser usado por runner4...() y por el BizModelAdapter
     *
     * @param string|null       $predefMenuName     Nombre del menu predefinido; o null si se trata de un menú estándar
     * @param string[]|null     $predefMenuArgs     Argumentos del menú predefinido; o null si se trata de un menú estándar
     * @param string[]|string   $titles             Título o títulos del menú
     * @param array             $options            Opciones del menú, construidas con splashHelperMenuMakeOption()
     * @param array|null        $pager              Especificación del paginador, como: [ pagerSpec, pagerArg ]; o null para no incluir paginador
     * @param string[]          $toVars             Nombres de las variables destino, resultado del funcionamiento del menú
     * @param int               $srcLineno          Número de línea en ejecución del programa BotBasic
     * @param string            $srcBot             Nombre del bot en ejecución
     * @param null|string       $botName            Nombre del bot sobre el cual aplicar el Splash; o null para derivarlo
     * @param null|int          $bmUserId           ID del BizModel user sobre el cual aplicar el Splash; o null para derivarlo
     * @param null|int          $bbChannelId        ID del BotBasicChannel sobre el cual aplicar el Splash; o null para derivarlo
     */
    public function splashHelperMenu ($predefMenuName, $predefMenuArgs, $titles, $options, $pager, $toVars, $srcLineno, $srcBot, $botName = null, $bmUserId = null, $bbChannelId = null)
    {
        $on = $this->completeOn($botName, $bmUserId, $bbChannelId);
        if ($on === null) { return; }
        if (is_string($options[0])) {
            $plainOptions = $options;
            $options      = [];
            foreach ($plainOptions as $plainOption) { $options[] = $this->splashHelperMenuMakeOption($plainOption, $plainOption); }
        }
        $this->menus[] = [ $predefMenuName, $predefMenuArgs, is_string($titles) ? [ $titles ] : $titles, $options, $pager, $toVars, $srcLineno, $srcBot, $on ];
    }



    /**
     * Fabrica una estructura que contiene una especificación de opción de menú
     *
     * @param  string       $key                            "Clave" del valor a mostrar (sólo se fija con OPTION ... FOR ...
     * @param  string       $value                          Valor a mostrar en pantalla de la opción del menú
     * @param  null|string  $gosubOrGoto                    'gosub', 'goto' (en mayúsculas o minúsculas), o null si no hay lógica de flujo asociada a la opción
     * @param  null|int     $targetLinenoForGosubOrGoto     Número de línea que especifica el salto del GOSUB o GOTO, si aplica
     * @return array                                        Representación
     */
    public function splashHelperMenuMakeOption ($key, $value, $gosubOrGoto = null, $targetLinenoForGosubOrGoto = null)
    {
        return [ $key, $value, $gosubOrGoto === null ? null : strtoupper($gosubOrGoto), $targetLinenoForGosubOrGoto ];
    }



    /**
     * Permite el preencolamiento hacia la chatapp de un INPUT; este método es para ser usado por runner4...() y por el BizModelAdapter
     *
     * @param string            $dataType           Tipo de dato a captar, uno de: 'date', 'positiveInteger', 'positiveDecimal', 'string'
     * @param string|string[]   $titles             Título o títulos del menú
     * @param string|null       $word               Palabra a ser usada para aceptar el valor contenido en $fromVar
     * @param string[]          $toVars             Nombres de las variables destino para el valor capturado y sus atributos
     * @param string|null       $fromVarValue       Valor a usar en caso de ser introducida la palabra $word
     * @param int               $srcLineno          Número de línea en ejecución del programa BotBasic
     * @param string            $srcBot             Nombre del bot en ejecución
     * @param null|string       $botName            Nombre del bot sobre el cual aplicar el Splash; o null para derivarlo
     * @param null|int          $bmUserId           ID del BizModel user sobre el cual aplicar el Splash; o null para derivarlo
     * @param null|int          $bbChannelId        ID del BotBasicChannel sobre el cual aplicar el Splash; o null para derivarlo
     */
    public function splashHelperInput ($dataType, $titles, $word, $toVars, $fromVarValue, $srcLineno, $srcBot, $botName = null, $bmUserId = null, $bbChannelId = null)
    {
        $on = $this->completeOn($botName, $bmUserId, $bbChannelId);
        if ($on === null) { return; }
        $this->inputs[] = [ $dataType, is_string($titles) ? [ $titles ] : $titles, $word, $toVars, $fromVarValue, $srcLineno, $srcBot, $on ];
    }



    /////////////////////
    // RUNNER4... METHODS
    /////////////////////



    /**
     * Retorna el número de línea siguiente (o no tan siguiente) a una línea determinada de un programa BotBasic
     *
     * @param  string   $bot        Nombre del bot
     * @param  int      $lineno     Número de línea a partir de la cual se hace el cálculo
     * @param  int      $offset     Normalmente 1, o más para saltar más de una línea
     * @return null|int             null si se lleg+ó al final del programa; el número de línea calculado de otro modo
     */
    private function nextLineno ($bot, $lineno, $offset = 1)
    {
        $linenos     = array_keys($this->bots[$bot]['sentences']);
        $finalLineno = $linenos[ count($this->bots[$bot]['sentences']) - 1 - ($offset-1) ];
        if ($lineno >= $finalLineno) { return null;                                                  }
        else                         { return $linenos[ array_search($lineno, $linenos) + $offset ]; }
    }



    protected function evalLogicPredicate ($directive, &$args, $lineno, $bot)
    {
        $arg0 =                                      $this->isReservedWord($args[0]) ? $args[0] : $this->getRvalValue($args[0], $lineno, $bot);
        $arg1 = ! isset($args[1]) ? self::NOTHING : ($this->isReservedWord($args[1]) ? $args[1] : $this->getRvalValue($args[1], $lineno, $bot));
        switch ($directive) {
            case 'NOT'   : $res = ! $this->evalLogicPredicate($args[0], array_slice($args, 1), $lineno, $bot);          break;   // wrong and inactive (not called from descender())
            case 'EQ'    : $res = is_numeric($arg0) && is_numeric($arg1) ? abs($arg0 - $arg1) <  1e-6 : $arg0 == $arg1; break;
            case 'NEQ'   : $res = is_numeric($arg0) && is_numeric($arg1) ? abs($arg0 - $arg1) >= 1e-6 : $arg0 != $arg1; break;
            case 'GT'    : $res = $arg0 >  $arg1;                                                                       break;
            case 'GTE'   : $res = $arg0 >= $arg1;                                                                       break;
            case 'LT'    : $res = $arg0 <  $arg1;                                                                       break;
            case 'LTE'   : $res = $arg0 <= $arg1;                                                                       break;
            case 'EMPTY' :
                $res = $arg0 === 'DATA'    ? $this->dataGetEmpty : (
                       $arg0 === 'OPTIONS' ? count($this->menuOptions) == 0 :
                       $arg0 === self::NOTHING );
                break;
            default                  :
                if (! $this->isNoargsPrimitive($directive)) {
                    Log::register(Log::TYPE_BBCODE, "RT2047 Primitiva '$directive' usada como predicado logico debe tener 0 argumentos IN y 1 argumento OUT", $this, $lineno);
                    $res = false;
                } else {
                    $res = $this->callPrimitive($arg0, $args, $lineno, $bot);
                    if     (is_int($res))    { $res = $res != 0;             }
                    elseif (is_string($res)) { $res = $res != self::NOTHING; }
                    elseif (! is_bool($res)) { $res = false;                 }
                }
        }
        return $res;
    }



    /**
     * Runner4... para APPVERSION
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   Nombre del bot en ejecución del programa BotBasic
     */
    private function runner4appversion ()
    {
        return $this->bbCodeMajorVersion . '.' . $this->bbCodeMinorVersion . '.' . $this->bbCodeSubminorVersion;
    }



    /**
     * Runner4... para RUNTIMEID
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   Nombre del bot en ejecución del programa BotBasic
     */
    private function runner4runtimeid ()
    {
        return $this->id;
    }



    /**
     * Runner4... para BOTNAME
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   Nombre del bot en ejecución del programa BotBasic
     */
    private function runner4botname ()
    {
        return $this->getBBbotName();
    }



    /**
     * Runner4... para CHATAPP
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   Nombre de la chatapp desde la cual se fue invocado este runtime (uno de los sufijos ChatMedium... de esas subclases)
     */
    private function runner4chatapp ()
    {
        $bbc = $this->getCurrentBBchannel();
        if ($bbc === null) { return self::NOTHING; }
        return ChatMedium::typeString($bbc->getCMchannel()->getChatMedium()->type);
    }



    /**
     * Runner4... para USERNAME
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   Nombre y apellido del usuario tal como es reportado por la chatapp
     */
    private function runner4username ()
    {
        return $this->update === null ? self::NOTHING : $this->update->getCMuserName();
    }



    /**
     * Runner4... para USERLOGIN
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   Nombre de la cuenta del usuario en la chatapp (ej. '@abc' en Telegram); string vacío si no está definido
     */
    private function runner4userlogin ()
    {
        return $this->update === null ? self::NOTHING : $this->update->getCMuserLogin();
    }



    /**
     * Runner4... para USERLANG
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   Código de idioma reportado por la chatapp; string vacío si no está definido
     */
    private function runner4userlang ()
    {
        return $this->update === null ? self::NOTHING : $this->update->getCMuserLang();
    }



    /**
     * Runner4... para ENTRYTYPE
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   Tipo del Update: 'empty', 'text' o uno de los tipos de InteractionResource
     */
    private function runner4entrytype ()
    {
        if     ($this->update === null)                                                   { $type = 'empty';                                                                 }
        elseif ($this->update->getText() !== null)                                        { $type = 'text';                                                                  }
        elseif (count($this->update->getResources()) == 0)                                { $type = 'empty';                                                                 }
        elseif (InteractionResource::isValidType($this->update->getResources()[0]->type)) { $type = InteractionResource::typeString($this->update->getResources()[0]->type); }
        else                                                                              { $type = 'unknown';                                                               }
        return $type;
    }



    /**
     * Runner4... para ENTRYTEXT
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   Texto del Update; o NOTHING si el Update no contiene texto
     */
    private function runner4entrytext ()
    {
        if ($this->update === null) { return self::NOTHING; }
        $res = $this->getEntryText();
        return $res === null ? self::NOTHING : $res;
    }



    /**
     * Runner4... para ENTRYID
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   ID del InteractionResource asociado al Update; o NOTHING en caso de que no haya resources asociados
     */
    private function runner4entryid ()
    {
        if ($this->update === null)            { return self::NOTHING;          }
        if ($this->update->getText() !== null) { return $this->update->getId(); }
        $resources = $this->update->getResources();
        if (count($resources) == 0)            { return self::NOTHING;          }
        return $resources[0]->id;
    }



    /**
     * Runner4... para ERR
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   Código del último error que puede ser generado por algunas de las instrucciones de BotBasic
     */
    private function runner4err ()
    {
        return $this->lastErr;
    }



    /**
     * Runner4... para PEEK222 (alias para ERR)
     *
     * No es un runner4...() a ser invocado directamente por runWith().
     *
     * @return string   El mismo valor de retorno que para runner4err()
     */
    private function runner4peek222 ()
    {
        return $this->runner4err();
    }



    /**
     * Runner4... para GOTO
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4goto (&$parsedContent, $lineno, $bot)
    {
        $this->doDummy([ $lineno, $bot ]);
        return $parsedContent[1];
    }



    /**
     * Runner4... para GOSUB
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4gosub (&$parsedContent, $lineno, $bot)
    {
        $values = [];
        foreach ($parsedContent[2] as $rval) {
            $values[] = $this->getRvalValue($rval, $lineno, $bot);
        }
        $toVars = false === ($pos = array_search('TO', $parsedContent)) ? null : $parsedContent[$pos+1];
        $this->getCurrentBBchannel()->callStackPush($lineno, $values, $toVars);
        return $parsedContent[1];
    }



    /**
     * Runner4... para ARGS
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4args (&$parsedContent, $lineno, $bot)
    {
        // get last GOSUB info
        $top = $this->getCurrentBBchannel()->callStackTop();
        if ($top === null) {
            Log::register(Log::TYPE_BBCODE, "RT2177 Call stack vacia (no se ejecuto un GOSUB previo antes de ARGS)", $this, $lineno);
            $gosubArgs = array_fill(0, count($parsedContent) - 1, self::NOTHING);
        } else {
            $gosubArgs = $top[1];
        }
        // check number of arguments in GSOUB+ARGS (should coincide)
        $argsArgs                         = $parsedContent[1];
        $theseMoreValuesInGosubThanInArgs = count($gosubArgs) - count($argsArgs);
        if ($theseMoreValuesInGosubThanInArgs != 0) {
            Log::register(Log::TYPE_BBCODE, "RT2186 La cantidad de argumentos de un ARGS no coincide con la de un GOSUB previo", $this, $lineno);
            if ($theseMoreValuesInGosubThanInArgs < 0) {
                for ($i = 0; $i < -$theseMoreValuesInGosubThanInArgs; $i++) { $gosubArgs[] = self::NOTHING; }
            }
        }
        // assign the args to the named lvalues
        for ($i = 0; $i < count($argsArgs); $i++) {
            $this->setVar($argsArgs[$i], $gosubArgs[$i], $lineno, $bot);
        }
        // done
        return -1;
    }



    /**
     * Runner4... para RETURN
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4return (&$parsedContent, $lineno, $bot)
    {
        $this->doDummy([ $parsedContent ]);
        // get last GOSUB info and pop from the call stack
        $top = $this->getCurrentBBchannel()->callStackPop();
        if ($top === null) {
            Log::register(Log::TYPE_BBCODE, "RT2215 Call stack vacia (no se ejecuto un GOSUB previo antes de RETURN; stopping...)", $this, $lineno);
            $this->running($bot, false);
        }
        // optionally return the values
        // if less returned values than gosub toVars, fill remaining toVars with self::NOTHING
        // if more returned values than gosub toVars, ignore extra values
        $gosubToVarNames = ! isset($top[2])           ? [] : $top[2];
        $returnNames     = ! isset($parsedContent[1]) ? [] : $parsedContent[1];
        $returnValues    = [];
        foreach ($returnNames as $returnName) {
            $returnValues[] = $this->getRvalValue($returnName, $lineno, $bot);
        }
        for ($i = 0; $i < count($gosubToVarNames); $i++) {
            $this->setVar($gosubToVarNames[$i], isset($returnValues[$i]) ? $returnValues[$i] : self::NOTHING, $lineno, $bot);
        }
        // calculate and return last context lineno
        $lineno     = $top[0];
        $nextLineno = $this->nextLineno($bot, $lineno);
        if ($nextLineno === null) {
            $this->running($bot, false);
            return -1;
        } else {
            return $nextLineno;
        }
    }



    /**
     * Runner4... para CALL
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4call (&$parsedContent, $lineno, $bot)
    {
        $primitiveName = $parsedContent[1][0];
        $srcVars       = $parsedContent[1][1];
        $tgtVars       = isset($parsedContent[3]) ? $parsedContent[3] : null;
        $callVals      = [];
        foreach ($srcVars as $rval) {
            $callVals[] = $this->getRvalValue($rval, $lineno, $bot);
        }
        $retVals = $this->callPrimitive($primitiveName, $callVals, $lineno, $bot);
        if ($tgtVars == 'OPTIONS') {
            // $this->menuOptions = [];   // don't erase previous options definitions
            $badRetVals = null;
            foreach ($retVals as $retVal) {
                if (! (is_array($retVal) && count($retVal) == 2 || is_numeric($retVal) || is_string($retVal))) {
                    if ($badRetVals === null) { $badRetVals = []; }
                    $badRetVals[] = json_encode($retVal);
                    continue;
                }
                if (! is_array($retVal)) { $key = $value = $retVal;       }
                else                     { list ($key, $value) = $retVal; }
                $this->menuOptions[] = $this->splashHelperMenuMakeOption($key, $value);
            }
            if ($badRetVals !== null) { Log::register(Log::TYPE_BBCODE, "RT2631 Primitiva $primitiveName retorna valores para OPTIONS con estructura incorrecta: [... " . implode(" ... ", $badRetVals) . " ...]", $this, $lineno); }
        }
        elseif ($tgtVars !== null) {
            if (! is_array($retVals)) {
                Log::register(Log::TYPE_RUNTIME, "RT2275 CallPrimitive no retorno un arreglo", $this, $lineno);
                $retVals = array_fill(0, count($tgtVars), self::NOTHING);
            }
            if (($numRets = count($retVals)) != ($numExps = count($this->primitives[$primitiveName][1]))) {
                Log::register(Log::TYPE_BBCODE, "RT2279 Diferencia entre cantidades de argumentos esperados[$numExps] y retornados[$numRets] por primitiva", $this, $lineno);
                for ($i = count($retVals); $i < count($tgtVars); $i++) { $retVals[$i] = self::NOTHING; }
            }
            for ($i = 0; $i < count($tgtVars); $i++) {
                $this->setVar($tgtVars[$i], $retVals[$i], $lineno, $bot);
            }
        }
        return -1;
    }



    /**
     * Runner4... para ON
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4on (&$parsedContent, $lineno, $bot)
    {
        $onBotName        = $parsedContent[1];
        $onBizModelUserId = isset($parsedContent[2]) ? $parsedContent[2] : null;
        $onBBchannelId    = isset($parsedContent[3]) ? $parsedContent[3] : null;
        if ($onBizModelUserId !== null && ! $this->isNumber($onBizModelUserId)) { $onBizModelUserId = $this->getRvalValue($onBizModelUserId, $lineno, $bot); }
        if ($onBBchannelId    !== null && ! $this->isNumber($onBBchannelId   )) { $onBBchannelId    = $this->getRvalValue($onBBchannelId,    $lineno, $bot); }
        $this->on = $this->completeOn($onBotName, $onBizModelUserId, $onBBchannelId);
        return -1;
    }



    /**
     * Runner4... para PRINT
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4print (&$parsedContent, $lineno, $bot)
    {
        $rvals           =& $parsedContent[1];
        $isOnAllChannels =  isset($parsedContent[3]) && $parsedContent[3] == 'CHANNELS';
        if ($isOnAllChannels) {
            $ons = [];
            foreach ($this->getBBchannels() as $bbc) { $ons[] = [ $this->getBBbotName(), $this->getBizModelUserId(), $bbc->getId() ]; }
        }
        else {
            $ons = [ isset($parsedContent[3]) ? $parsedContent[3] : $this->on ];
        }
        foreach ($ons as $on) {
            for ($pos = 0; $pos < count($rvals); $pos++) {
                $onBotName        = isset($on[0]) ? $on[0] : null;
                $onBizModelUserId = isset($on[1]) ? $on[1] : null;
                $onBBchannelId    = isset($on[2]) ? $on[2] : null;
                if ($onBizModelUserId !== null && ! $this->isNumber($onBizModelUserId)) { $onBizModelUserId = $this->getRvalValue($onBizModelUserId, $lineno, $bot); }
                if ($onBBchannelId    !== null && ! $this->isNumber($onBBchannelId   )) { $onBBchannelId    = $this->getRvalValue($onBBchannelId,    $lineno, $bot); }
                $this->splashHelperPrint($this->getRvalValue($rvals[$pos], $lineno, $bot), $onBotName, $onBizModelUserId, $onBBchannelId);
            }
        }
        // $this->resetAfterSplash();   // PRINT's no deben resetear el contexto de los Splashes
        return -1;
    }



    /**
     * Runner4... para DISPLAY
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4display (&$parsedContent, $lineno, $bot)
    {
        $rvals           =& $parsedContent[1];
        $hasTitle        =  isset($parsedContent[2]) && $parsedContent[2] == 'TITLE';
        $title           =  $hasTitle ? $parsedContent[3] : null;
        $isOnAllChannels =  isset($parsedContent[$hasTitle ? 5 : 3]) && $parsedContent[$hasTitle ? 5 : 3] == 'CHANNELS';
        if ($isOnAllChannels) {
            $ons = [];
            foreach ($this->getBBchannels() as $bbc) { $ons[] = [ $this->getBBbotName(), $this->getBizModelUserId(), $bbc->getId() ]; }
        }
        else {
            $ons = [ isset($parsedContent[$hasTitle ? 5 : 3]) ? $parsedContent[$hasTitle ? 5 : 3] : $this->on ];
        }
        foreach ($ons as $on) {
            for ($pos = 0; $pos < count($rvals); $pos++) {
                $onBotName        = isset($on[0]) ? $on[0] : null;
                $onBizModelUserId = isset($on[1]) ? $on[1] : null;
                $onBBchannelId    = isset($on[2]) ? $on[2] : null;
                if ($onBizModelUserId !== null && ! $this->isNumber($onBizModelUserId)) { $onBizModelUserId = $this->getRvalValue($onBizModelUserId, $lineno, $bot); }
                if ($onBBchannelId    !== null && ! $this->isNumber($onBBchannelId   )) { $onBBchannelId    = $this->getRvalValue($onBBchannelId,    $lineno, $bot); }
                $titleValue = $title === null ? null : $this->getRvalValue($title, $lineno, $bot);
                $this->splashHelperDisplay($this->getRvalValue($rvals[$pos], $lineno, $bot), $titleValue, $onBotName, $onBizModelUserId, $onBBchannelId);
            }
        }
        return -1;
    }



    /**
     * Runner4... para END
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4end (&$parsedContent, $lineno, $bot)
    {
        $this->doDummy([ $parsedContent, $lineno ]);
        $this->getCurrentBBchannel()->callStackReset();
        $this->running($bot, false);
        return -1;
    }



    /**
     * Runner4... para REM
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4rem (&$parsedContent, $lineno, $bot)
    {
        if (BOTBASIC_DEBUG && $this->trace) {
            $comment = join(self::SEP, array_slice($parsedContent, 1));
            Log::register(Log::TYPE_BBCODE, "REM: $comment", $this, $lineno);
            $this->doDummy([ $lineno, $bot ]);
        }
        return -1;
    }



    /**
     * Runner4... para OPTION
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4option (&$parsedContent, $lineno, $bot)
    {
        $asTarget     = $parsedContent[2] == 'AS' ? $parsedContent[3] : null;
        $gosubOrGoto  = $parsedContent[2] == 'GOSUB' || $parsedContent[2] == 'GOTO' ? $parsedContent[2] : (
                        ! isset($parsedContent[4]) ? null : (
                        $parsedContent[4] == 'GOSUB' || $parsedContent[4] == 'GOTO' ? $parsedContent[4] : null ));
        $targetLineno = $parsedContent[2] == 'GOSUB' || $parsedContent[2] == 'GOTO' ? $parsedContent[3] : (
                        ! isset($parsedContent[4]) || ! isset($parsedContent[5]) ? null : (
                        $parsedContent[4] == 'GOSUB' || $parsedContent[4] == 'GOTO' ? $parsedContent[5] : null ));
        $value = $this->getRvalValue($parsedContent[1], $lineno, $bot);
        $key   = $asTarget !== null ? $this->getRvalValue($asTarget, $lineno, $bot) : $value;
        $this->menuOptions[] = $this->splashHelperMenuMakeOption($key, $value, $gosubOrGoto, $targetLineno);
        return -1;
    }



    /**
     * Runner4... para OPTIONS
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4options (&$parsedContent, $lineno, $bot)
    {
        foreach ($parsedContent[1] as $option) {
            $value = $this->getRvalValue($option, $lineno, $bot);
            $this->menuOptions[] = $this->splashHelperMenuMakeOption($value, $value);
        }
        return -1;
    }



    /**
     * Runner4... para TITLE
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4title (&$parsedContent, $lineno, $bot)
    {
        $this->menuAndInputTitles = array_merge($this->menuAndInputTitles, $this->rvals2values(array_slice($parsedContent, 1), $lineno, $bot));
        return -1;
    }



    /**
     * Runner4... para PAGER
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4pager (&$parsedContent, $lineno, $bot)
    {
        $this->doDummy([ $lineno, $bot ]);
        $this->menuPager = $parsedContent[1];
        return -1;
    }



    /**
     * Runner4... para MENU
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4menu (&$parsedContent, $lineno, $bot)
    {
        $predefMenuName   = $parsedContent[1][0];
        $predefMenuArgs   = $this->rvals2values($parsedContent[1][1], $lineno, $bot);
        $titles           = array_merge($this->menuAndInputTitles, $this->rvals2values(($pos = array_search('TITLE', $parsedContent)) === false ? [] : [ $parsedContent[$pos+1] ], $lineno, $bot));
        $theseOptions     = ($pos = array_search('OPTIONS', $parsedContent)) === false ? [] : $parsedContent[$pos+1];
        $theseOptionsFull = [];
        foreach ($theseOptions as $option) {
            $value = $this->getRvalValue($option, $lineno, $bot);
            $theseOptionsFull[] = $this->splashHelperMenuMakeOption($value, $value);
        }
        $options          = array_merge($this->menuOptions, $theseOptionsFull);
        $pager            = ($pos = array_search('PAGER', $parsedContent)) === false ? $this->menuPager : $parsedContent[$pos+1];
        $on               = ($pos = array_search('ON',    $parsedContent)) === false ? $this->on        : $parsedContent[$pos+1];
        $toVars           = ($pos = array_search('TO',    $parsedContent)) === false ? []               : $parsedContent[$pos+1];
        if (count($options) == 0 && $predefMenuName === null) {
            Log::register(Log::TYPE_BBCODE, "RT2517 Un menu estandar se ha intentado desplegar sin opciones", $this, $lineno);
            foreach ($toVars as $toVar) { $this->setVar($toVar, self::NOTHING, $lineno, $bot); }
        }
        else {
            $onBotName        = isset($on[0]) ? $on[0] : null;
            $onBizModelUserId = isset($on[1]) ? $on[1] : null;
            $onBBchannelId    = isset($on[2]) ? $on[2] : null;
            if ($onBizModelUserId !== null && ! $this->isNumber($onBizModelUserId)) { $onBizModelUserId = $this->getRvalValue($onBizModelUserId, $lineno, $bot); }
            if ($onBBchannelId    !== null && ! $this->isNumber($onBBchannelId   )) { $onBBchannelId    = $this->getRvalValue($onBBchannelId,    $lineno, $bot); }
            $this->splashHelperMenu($predefMenuName, $predefMenuArgs, $titles, $options, $pager, $toVars, $lineno, $bot, $onBotName, $onBizModelUserId, $onBBchannelId);
            //if ($on[0] == $this->getBBbotName() && $on[2] == $this->getCurrentBBchannel()->getId()) {
            $this->running($bot, false);
            //}
        }
        $this->resetAfterSplash();
        return -1;
    }



    /**
     * Runner4... para WORD
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4word (&$parsedContent, $lineno, $bot)
    {
        $this->word = $this->getRvalValue($parsedContent[1], $lineno, $bot);
        $this->tainting(true);
        return -1;
    }



    /**
     * Runner4... para INPUT
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4input (&$parsedContent, $lineno, $bot)
    {
        $dataType         = $parsedContent[1];
        $titles           = array_merge($this->menuAndInputTitles, $this->rvals2values(($pos = array_search('TITLE', $parsedContent)) === false ? [] : [ $parsedContent[$pos+1] ], $lineno, $bot));
        $on               = ($pos = array_search('ON',   $parsedContent)) === false ? $this->on   : $parsedContent[$pos+1];
        $word             = ($pos = array_search('WORD', $parsedContent)) === false ? $this->word : $this->getRvalValue($parsedContent[$pos+1], $lineno, $bot);   // "inactive" by BB syntax
        $toVars           = ($pos = array_search('TO',   $parsedContent)) === false ? null : $parsedContent[$pos+1];
        $fromVarValue     = ($pos = array_search('FROM', $parsedContent)) === false ? null : $this->getRvalValue($parsedContent[$pos+1], $lineno, $bot);
        if (! is_array($toVars)) { $toVars = [ $toVars ]; }   // legacy parsed code can have scalar values as $toVars
        $onBotName        = isset($on[0]) ? $on[0] : null;
        $onBizModelUserId = isset($on[1]) ? $on[1] : null;
        $onBBchannelId    = isset($on[2]) ? $on[2] : null;
        if ($onBizModelUserId !== null && ! $this->isNumber($onBizModelUserId)) { $onBizModelUserId = $this->getRvalValue($onBizModelUserId, $lineno, $bot); }
        if ($onBBchannelId    !== null && ! $this->isNumber($onBBchannelId   )) { $onBBchannelId    = $this->getRvalValue($onBBchannelId,    $lineno, $bot); }
        $this->splashHelperInput($dataType, $titles, $word, $toVars, $fromVarValue, $lineno, $bot, $onBotName, $onBizModelUserId, $onBBchannelId);
        $this->resetAfterSplash();
        //if ($on[0] == $this->getBBbotName() && $on[2] == $this->getCurrentBBchannel()->getId()) {
        $this->running($bot, false);
        //}
        return -1;
    }



    /**
     * Runner4... para BLOAD
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4bload (&$parsedContent, $lineno, $bot)
    {
        $buildLocalFilename = function ($filename, $fromCloud)
        {
            if (substr($filename, 0, 1) != '/') { $filename = BOTBASIC_PRIVATE_MEDIA_DIR . '/' . ($fromCloud ? 'cloud/' : '') . $filename; }
            return $filename;
        };
        $buildCloudFilename = function ($filename)
        {
            return $filename;
        };
        $loadFile = function ($filename, $mediaType, $fromCloud) use ($buildLocalFilename)
        {
            if ($filename === null || $filename == '') { return null; }
            $filename = $buildLocalFilename($filename, $fromCloud);
            if (! file_exists($filename)) { return null; }
            $cmType   = $this->getCurrentBBchannel()->getCMchannel()->getCMtype();
            $resource = InteractionResource::createFromFile(InteractionResource::getType($mediaType), $filename, $cmType);
            return $resource;
        };
        $download = function ($filename) use ($buildLocalFilename, $buildCloudFilename, $lineno)
        {
            $localFilename = $buildLocalFilename($filename, true);
            $cloudFilename = $buildCloudFilename($filename);
            $command       = BOTBASIC_DOWNLOADDAEMON_SCRIPTSDIR . "/downloadfromcloud.sh \"$cloudFilename\" \"$localFilename\"";
            $output        = [];
            $res           = -1;
            exec($command, $output, $res);
            if ($res != 0) {
                Log::register(Log::TYPE_RUNTIME, "RT3436 No se puede descargar el resource: [$command] arroja [$res] [" . json_encode($output) . "]", $this, $lineno);
            }
            return $res == 0;
        };
        // try to load locally
        $filename  = $this->getVar($parsedContent[1], $lineno, $bot);
        $mediaType = $parsedContent[3];
        $resource  = $loadFile($filename, $mediaType, false);
        // if not, try to download from cloud and then load locally
        if ($resource === null) {
            $download($filename);
            $resource = $loadFile($filename, $mediaType, true);
        }
        if ($resource === null) {
            Log::register(Log::TYPE_BBCODE, "RT3420 No se puede cargar el resource desde $filename", $this, $lineno);
        }
        // set the var
        $resourceId = $resource === null ? self::NOTHING : $resource->id;
        $this->setVar($parsedContent[5], $resourceId, $lineno, $bot, false);
        return -1;
    }



    /**
     * Runner4... para BSAVE
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4bsave (&$parsedContent, $lineno, $bot)
    {
        $mkdir = function ($dir)
        {
            if (! is_dir($dir)) {
                return mkdir($dir, 0775, true);
            }
            return true;
        };
        $makeTargetDir = function ($mediaType) use ($mkdir)
        {
            $basedir   = BOTBASIC_PRIVATE_MEDIA_DIR;
            $datedir   = date("Ym");
            $mediaType = InteractionResource::typeString($mediaType);
            $subdir    = "$mediaType/$datedir";
            $dir       = "$basedir/$subdir";
            return $dir;
        };
        $return = function ($ok) use (&$parsedContent, $lineno, $bot) {
            $this->setVar($parsedContent[5], $ok, $lineno, $bot);
            return -1;
        };
        // load the resource
        $resourceId = $this->getVar($parsedContent[1], $lineno, $bot);
        $resource   = InteractionResource::load($resourceId);
        if ($resource === null) {
            Log::register(Log::TYPE_BBCODE, "RT3454 No se puede cargar el resource con ID $resourceId", $this, $lineno);
            return $return(self::NOTHING);
        }
        // get and complete filenames
        if ($resource->filename === null) {
            Log::register(Log::TYPE_BBCODE, "RT3459 El resource con ID $resourceId no tiene un archivo asociado", $this, $lineno);
            return $return(self::NOTHING);
        }
        $srcFilename = BOTBASIC_BASEDIR . '/' . $resource->filename;
        $tgtFilename = $this->getVar($parsedContent[3], $lineno, $bot);
        if ($tgtFilename === self::NOTHING) {
            Log::register(Log::TYPE_BBCODE, "RT3466 Se intenta guardar un resource en un nombre de archivo vacio", $this, $lineno);
            return $return(self::NOTHING);
        }
        if (substr($tgtFilename, 0, 1) == '/') { $tgtFilename = substr($tgtFilename, 1); }
        // build target directory
        $dir         = $makeTargetDir($resource->type);
        $tgtFilename = $dir . '/' . $tgtFilename;
        if (! $mkdir(dirname($tgtFilename))) {
            Log::register(Log::TYPE_RUNTIME, "RT3479 No se puede crear el directorio $dir", $this, $lineno);
            return $return(self::NOTHING);
        }
        // copy to target directory and return
        if (! copy($srcFilename, $tgtFilename)) {
            Log::register(Log::TYPE_BBCODE, "RT3467 No se puede copiar desde $srcFilename hacia $tgtFilename", $this, $lineno);
            return $return(self::NOTHING);
        }
        return $return("1");
    }



    /**
     * Runner4... para EXTRACT
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4extract (&$parsedContent, $lineno, $bot)
    {
        // load the resource
        $resourceId = $this->getVar($parsedContent[3], $lineno, $bot);
        $resource   = InteractionResource::load($resourceId);
        if ($resource === null) {
            Log::register(Log::TYPE_BBCODE, "RT3505 No se puede cargar el resource con ID $resourceId", $this, $lineno);
            return -1;
        }
        // get the value
        $attribute = $parsedContent[1];
        $value     = isset($resource->metainfo[$attribute]) ? $resource->metainfo[$attribute] : self::NOTHING;
        // set the value and return
        $this->setVar($parsedContent[5], $value, $lineno, $bot, false);
        return -1;
    }



    /**
     * Runner4... para SET
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4set (&$parsedContent, $lineno, $bot)
    {
        list ($name, $expr) = $parsedContent[1];
        $value = $this->getRvalValue($expr, $lineno, $bot);
        $pos   = array_search('ON', $parsedContent);
        $bbcId = null;
        if ($pos === false) {
            $this->getCurrentBBchannel()->setVar($name, $value, $lineno, $bot);
        }
        else {
            list ($onBotName, $onBizModelUserId) = $parsedContent[$pos+1];
            if ($onBizModelUserId !== null && ! $this->isNumber($onBizModelUserId)) { $onBizModelUserId = $this->getRvalValue($onBizModelUserId, $lineno, $bot); }
            $rt = BotBasicRuntime::loadByBizModelUserId($this->bbBotIdx, $onBizModelUserId);
            if ($rt === null) {
                Log::register(Log::TYPE_RUNTIME, "RT2539 No se puede cargar el runtime por BizModelUserId para un SET...ON", $this, $lineno);
            }
            else {
                if ($rt->getBBbotName() != $onBotName) {
                    Log::register(Log::TYPE_BBCODE, "RT2543 El BizModelUserId $onBizModelUserId no se corresponde al bot $onBotName", $this, $lineno);
                }
                else {
                    $rt->setVar($name, $value, $lineno, $bot, false);
                }
            }
        }
        return -1;
    }



    /**
     * Runner4... para CLEAR
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4clear (&$parsedContent, $lineno, $bot)
    {
        $this->doDummy([ $lineno, $bot ]);
        // reset de arreglos (OPTIONS)
        if (array_search('OPTIONS', $parsedContent) !== false) {
            $this->menuOptions = [];
        }
        // reset de target de interacciones (ON)
        elseif (array_search('ON', $parsedContent) !== false) {
            $this->on = [ null, null, null ];
        }
        // reset de variables escalares
        else {
            $hasWord    = array_search('WORD',    $parsedContent) !== false;
            $hasAll     = array_search('ALL',     $parsedContent) !== false;
            $hasChannel = array_search('CHANNEL', $parsedContent) !== false;
            if ($hasWord) {
                $this->word = null;
                $this->tainting(true);
            }
            elseif (! $hasChannel && $hasAll) {
                $this->resetAllHelper($lineno, $bot, false);
            }
            elseif ($hasChannel && $hasAll) {
                $this->getCurrentBBchannel()->resetAllChannelHelper();
            }
            else {   // ! $hasAll
                $names = $parsedContent[1];
                foreach ($names as $name) {
                    if     ($this->isMagicVar($name)) { $this->setMagicVar($name, self::NOTHING, $lineno, $bot);    }
                    elseif ($hasChannel)              { $this->getCurrentBBchannel()->resetCommonVar($name, false); }
                    else                              { $this->resetCommonVar($name, true);                         }
                }
            }
        }
        // ready
        return -1;
    }



    /**
     * Runner4... para INC, DEC, MUL, DIV y MOD (helper)
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @param  string       $oper           Operador que define la operación aritmética
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4incdecmuldivmodHelper (&$parsedContent, $lineno, $bot, $oper)
    {
        $neutral       = [ '/' => 1, '*' => 1, '%' => 1, '+' => 0, '-' => 0 ];
        $defaultAmount = $neutral[$oper];
        $name          = $parsedContent[1];
        $amount        = isset($parsedContent[2]) ? $this->getRvalValue($parsedContent[2], $lineno, $bot) : 1;   // only +/- can come without default addition/substraction term
        if (! $this->isSetVar($name, $this->getCurrentBBchannel())) { $value = $defaultAmount; $this->setVar($name, $value, $lineno, $bot); }
        else                                                        { $value = $this->getVar($name, $lineno, $bot);                         }
        if (! is_numeric($value )) { $value  = 0; }
        if (! is_numeric($amount)) { $amount = 1; }
        switch ($oper) {
            case '+' :                                         $value += $amount; break;
            case '-' :                                         $value -= $amount; break;
            case '*' :                                         $value *= $amount; break;
            case '/' : if ($amount < 1e-6) { $amount = 1e-6; } $value /= $amount; break;
            case '%' : if ($amount < 1e-6) { $amount = 1e-6; } $value %= $amount; break;
        }
        $this->setVar($name, $value, $lineno, $bot);
        return -1;
    }



    /**
     * Runner4... para INC
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4inc (&$parsedContent, $lineno, $bot)
    {
        return $this->runner4incdecmuldivmodHelper($parsedContent, $lineno, $bot, '+');
    }



    /**
     * Runner4... para DEC
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4dec (&$parsedContent, $lineno, $bot)
    {
        return $this->runner4incdecmuldivmodHelper($parsedContent, $lineno, $bot, '-');
    }



    /**
     * Runner4... para MUL
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4mul (&$parsedContent, $lineno, $bot)
    {
        return $this->runner4incdecmuldivmodHelper($parsedContent, $lineno, $bot, '*');
    }



    /**
     * Runner4... para DIV
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4div (&$parsedContent, $lineno, $bot)
    {
        return $this->runner4incdecmuldivmodHelper($parsedContent, $lineno, $bot, '/');
    }



    /**
     * Runner4... para MOD
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4mod (&$parsedContent, $lineno, $bot)
    {
        return $this->runner4incdecmuldivmodHelper($parsedContent, $lineno, $bot, '%');
    }



    /**
     * Runner4... para CONCAT
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4concat (&$parsedContent, $lineno, $bot)
    {
        $name          = $parsedContent[1][0];
        $toConcatNames = array_slice($parsedContent[1], 1);
        $value         = $this->isSetVar($name, $this->getCurrentBBchannel()) ? $this->getVar($name, $lineno, $bot) : self::NOTHING;
        foreach ($toConcatNames as $toConcatName) {
            $toConcatValue = $this->isSetVar($toConcatName, $this->getCurrentBBchannel()) ? $this->getVar($toConcatName, $lineno, $bot) : self::NOTHING;
            $value .= $toConcatValue;
        }
        $this->setVar($name, $value, $lineno, $bot);
        return -1;

    }



    /**
     * Runner4... para SPLIT
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4split (&$parsedContent, $lineno, $bot)
    {
        $spacesSpec = '/ +/';
        list (, $splits, , $tgtVars) = $parsedContent;
        list ($splitSpecVarName, $toSplitVarName) = $splits;
        if (! $this->isSetVar($splitSpecVarName)) {
            Log::register(Log::TYPE_BBCODE, "RT2767 Variable $splitSpecVarName de splitSpec no ha sido fijada; usando espacios...", $this, $lineno);
            $splitSpec = $spacesSpec;
        }
        else {
            $splitSpec = $this->getRvalValue($splitSpecVarName, $lineno, $bot);
            if ($splitSpec == '') { $splitSpec = $spacesSpec; }
        }
        $isRegExp = 1 === preg_match('/^\/.*\/$/', $splitSpec);
        if ($isRegExp) {
            $valid = (@preg_match($splitSpec, '') !== false);
            if (! $valid) {
                Log::register(Log::TYPE_BBCODE, "RT2776 Expresion regular incorrecta: $splitSpec", $this, $lineno);
                $splitSpec = $spacesSpec;
            }
        }
        if (! $this->isSetVar($toSplitVarName)) {
            Log::register(Log::TYPE_BBCODE, "RT2781 Variable $toSplitVarName a hacerle split no ha sido fijada; usando NOTHING...", $this, $lineno);
            $toSplit = self::NOTHING;
        }
        else {
            $toSplit = $this->getRvalValue($toSplitVarName, $lineno, $bot);
        }
        $splitted = $isRegExp ? preg_split($splitSpec, $toSplit) : explode($splitSpec, $toSplit);
        for ($i = count($splitted); $i < count($tgtVars); $i++) { $splitted[$i] = self::NOTHING;                             }
        for ($i = 0; $i < count($tgtVars); $i++)                { $this->setVar($tgtVars[$i], $splitted[$i], $lineno, $bot); }
        return -1;
    }



    /**
     * Runner4... para COUNT
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4count (&$parsedContent, $lineno, $bot)
    {
        list (, , , $varName) = $parsedContent;
        $value = count($this->menuOptions);
        $this->setVar($varName, $value, $lineno, $bot);
        return -1;
    }



    /**
     * Runner4... para LOG
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4log (&$parsedContent, $lineno, $bot)
    {
        $messages = [];
        foreach ($parsedContent[1] as $varName) {
            $messages[] = $varName . (! $this->isSetVar($varName) ? '' : "[" . $this->getRvalValue($varName, $lineno, $bot) . "]");
        }
        Log::register(Log::TYPE_BBCODE, "LOG: " . join(' ', $messages), $this, $lineno);
        return -1;
    }



    /**
     * Runner4... para LOCALE
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4locale (&$parsedContent, $lineno, $bot)
    {
        $this->doDummy([ $lineno, $bot ]);
        $newLocale = $parsedContent[1];
        if ($this->isLocale($newLocale)) {
            $this->locale = $newLocale;
            $this->tainting(true);
            foreach ($this->getBBchannels() as $bbc) {
                $bbc->getCMchannel()->setLocale($newLocale);
            }
        }
        return -1;
    }



    /**
     * Runner4... para ABORT
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4abort (&$parsedContent, $lineno, $bot)
    {
        $this->doDummy([ $parsedContent, $lineno, $bot ]);
        $this->aborted = true;
        return -1;
    }



    /**
     * Runner4... para DATA
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4data (&$parsedContent, $lineno, $bot)
    {
        list (, $oper, $dbVarName, , $varName) = $parsedContent;
        $dbKey = $this->getVar($dbVarName, $lineno, $bot);
        if ($dbKey === null || $dbKey == self::NOTHING) {
            Log::register(Log::TYPE_BBCODE, "RT2824 DATA requiere un valor concreto como clave para la tabla en BD", $this, $lineno);
        }
        else {
            if ($oper == 'GET') {
                $newDataGetEmpty = false;
                $dbValue         = $this->dataHelperLoader($dbKey);
                if     ($dbValue === false)                              { $dbValue = 0;                                      }
                elseif ($dbValue === true)                               { $dbValue = 1;                                      }
                elseif ($dbValue === null)                               { $dbValue = self::NOTHING; $newDataGetEmpty = true; }
                elseif (! (is_numeric($dbValue) || is_string($dbValue))) { $dbValue = self::NOTHING;                          }
                $this->setVar($varName, $dbValue, $lineno, $bot);
                if ($this->dataGetEmpty != $newDataGetEmpty) { $this->dataGetEmpty = $newDataGetEmpty; }   // $this->tainting(true);   // now this's volatile
            }
            else {   // SET
                $dbValue = $this->getRvalValue($varName, $lineno, $bot);
                $this->dataHelperSaver($dbKey, $dbValue);
            }
        }
        return -1;
    }



    /**
     * Runner4... para CHANNEL
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4channel (&$parsedContent, $lineno, $bot)
    {
        $oper = $parsedContent[1];
        if ($oper == 'DELETE') {
            // determine what to delete
            $toDeleteTmp = [];
            if ($parsedContent[2] == 'ALL') { $toDeleteTmp   = $this->getBBchannels();                                 }
            else                            { $toDeleteTmp[] = BotBasicChannel::load($parsedContent[2], $this, false); }
            $toDelete = [];   /** @var BotBasicChannel[] $toDelete */
            foreach ($toDeleteTmp as $bbc) {
                if ($bbc !== null && $bbc->getId() != $this->getCurrentBBchannel()->getId() && ! $bbc->isDefaultBBchannel()) { $toDelete[] = $bbc; }
            }
            // delete
            foreach ($toDelete as $bbc) {
                $message = BotConfig::botMessage($this->bbBotIdx, $this->locale, BotConfig::MSG_BBCHANNEL_WAS_DELETED);
                $this->splashHelperPrint($message, $this->getBBbotName(), $this->bmUserId, $bbc->getId());
                $bbc->setAsDeleted();
            }
        }
        else {   // current|new
            list ($channelIdVarName, $cmBotNameVarName) = $parsedContent[3];
            if ($oper == 'current') {
                $channelId = $this->getCurrentBBchannel()->getId();
                $cmBotName = $this->getCurrentBBchannel()->getCMchannel()->getCMbotName();
            }
            else {   // new
                if ($this->bma() === null) {
                    Log::register(Log::TYPE_RUNTIME, "RT3454 BizModelAdapter nulo", $this, $lineno);
                    return -1;
                }
                $chPurposeVarName = isset($parsedContent[5]) ? $parsedContent[5][0] : null;
                $chPurpose        = $chPurposeVarName === null ? null : $this->getVar($chPurposeVarName, $lineno, $bot);
                $cmBotName        = $this->bma()->makeAcmBotName($this->getBBbotName());
                if ($cmBotName === null) {
                    $channelId = self::NOTHING;
                    $cmBotName = self::NOTHING;
                }
                else {
                    $previousBbcs      = $this->getBBchannels();
                    $cmType            = $this->getCurrentBBchannel()->getCMchannel()->getCMtype();
                    $cmUserId          = $this->getCurrentBBchannel()->getCMchannel()->getCMuserId();
                    $bbc               = BotBasicChannel::createFromBBRT($this, $cmType, $cmUserId, $cmBotName);
                    $previouslyPresent = false;
                    foreach ($previousBbcs as $aBbc) {   /** @var BotBasicChannel $aBbc */
                        if ($bbc->getId() == $aBbc->getId()) { $previouslyPresent = true; }
                    }
                    if (! $previouslyPresent) {
                        $res = $bbc->save();
                        if ($res === null) { $channelId = $cmBotName = self::NOTHING; }
                        else               { $channelId = $bbc->getId();              }
                    }
                    else {
                        $channelId = $bbc->getId();
                        if ($chPurpose !== null) {   // signal the new channel purpose to the user
                            $prefix  = BotConfig::botMessage($this->bbBotIdx, $this->locale, BotConfig::MSG_BBCHANNEL_WAS_REUSED_PREFIX);
                            $message = $prefix . $chPurpose;
                            $this->splashHelperPrint($message, $this->getBBbotName(), $this->bmUserId, $channelId);
                        }
                    }
                }
            }
            if ($channelId !== self::NOTHING) {
                $this->setVar($channelIdVarName, $channelId, $lineno, $bot);
                $this->setVar($cmBotNameVarName, $cmBotName, $lineno, $bot);
            }
        }
        return -1;
    }



    /**
     * Runner4... para TUNNEL
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4tunnel (&$parsedContent, $lineno, $bot)
    {
        list (, $tunnelSpec, , $srcChannelId, , $tgtChannelSpec) = $parsedContent;
        list ($tgtBotVarName, $tgtUserId, $tgtChannelId)         = $tgtChannelSpec;
        $tgtBotName = $this->getVar($tgtBotVarName, $lineno, $bot);
        $srcBbc     = BotBasicChannel::load($srcChannelId, $this, false);
        if ($srcBbc === null) {
            Log::register(Log::TYPE_BBCODE, "RT2940 BBchannel origen $srcChannelId no encontrado", $this, $lineno);
        }
        else {
            $tgtBbc = BotBasicChannel::load($tgtChannelId, null, false);
            if ($tgtBbc === null) {
                Log::register(Log::TYPE_BBCODE, "RT2945 BBchannel destino $tgtChannelId no encontrado", $this, $lineno);
            }
            elseif ($tgtUserId !== $tgtBbc->getBBruntime()->getBizModelUserId()) {
                Log::register(Log::TYPE_BBCODE, "RT2948 BizModelUser ID's no coinciden ($tgtUserId" . $tgtBbc->getBBruntime()->getBizModelUserId() . ")", $this, $lineno);
            }
            elseif ($tgtBotName !== $tgtBbc->getBBruntime()->getBBbotName()) {
                Log::register(Log::TYPE_BBCODE, "RT2951 Nombres de bots no coinciden ($tgtBotName" . $tgtBbc->getBBruntime()->getBBbotName() . ")", $this, $lineno);
            }
            else {
                $types = [];
                switch ($tunnelSpec) {
                    case 'nothing'    : $srcBbc->removeTunnels(null, $tgtBbc); break;
                    case 'all'        : $types = [ InteractionResource::TYPE_TEXT, InteractionResource::TYPE_AUDIO, InteractionResource::TYPE_DOCUMENT, InteractionResource::TYPE_IMAGE, InteractionResource::TYPE_LOCATION, InteractionResource::TYPE_VIDEO, InteractionResource::TYPE_VOICE ]; break;
                    case 'allButText' : $types = [                                 InteractionResource::TYPE_AUDIO, InteractionResource::TYPE_DOCUMENT, InteractionResource::TYPE_IMAGE, InteractionResource::TYPE_LOCATION, InteractionResource::TYPE_VIDEO, InteractionResource::TYPE_VOICE ]; break;
                    case 'text'       : $types = [ InteractionResource::TYPE_TEXT ];      break;
                    case 'image'      : $types = [ InteractionResource::TYPE_IMAGE ];     break;
                    case 'audio'      : $types = [ InteractionResource::TYPE_AUDIO ];     break;
                    case 'voice'      : $types = [ InteractionResource::TYPE_VOICE ];     break;
                    case 'video'      : $types = [ InteractionResource::TYPE_VIDEO ];     break;
                    case 'videonote'  : $types = [ InteractionResource::TYPE_VIDEONOTE ]; break;
                    case 'document'   : $types = [ InteractionResource::TYPE_DOCUMENT ];  break;
                    case 'location'   : $types = [ InteractionResource::TYPE_LOCATION ];  break;
                }
                foreach ($types as $type) { $srcBbc->addTunnel($type, $tgtBbc); }
            }
        }
        return -1;
    }



    /**
     * Runner4... para USERID
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4userid (&$parsedContent, $lineno, $bot)
    {
        list (, $oper, $varName) = $parsedContent;
        if ($oper == 'TO') {
            $userId = $this->bmUserId === null ? self::NOTHING : $this->bmUserId;
            $this->setVar($varName, $userId, $lineno, $bot);
        }
        else {   // FROM
            $userId = $this->getVar($varName, $lineno, $bot);
            //if (! ($userId == (int)$userId && $userId > 0)) { Log::register(Log::TYPE_BBCODE, "RT2993 BizModelUserID $userId debe ser entero positivo no-cero para USERID", $this, $lineno); }
            if (! ($userId == (int)$userId)) {
                Log::register(Log::TYPE_BBCODE, "RT3596 BizModelUserID $userId debe ser entero para USERID", $this, $lineno);
                $this->running($this->getBBbotName(), false);
            }
            else {
                $this->setBizModelUserId($userId, $lineno, $bot);
            }
        }
        return -1;
    }



    /**
     * Runner4... para TRACE
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4trace (&$parsedContent, $lineno, $bot)
    {
        $this->doDummy([ $parsedContent, $lineno, $bot ]);
        $this->trace = true;
        $this->tainting(true);
        return -1;
    }



    /**
     * Runner4... para NOTRACE
     *
     * @param  array        $parsedContent  Tokens de la línea de código ya transformados por el parser (no serán modificados)
     * @param  int          $lineno         Número de línea del programa BotBasic
     * @param  string       $bot            Bot del programa BotBasic
     * @return bool                         Nuevo número de línea (salto de ejecución); o -1 si se debe seguir la secuencia lineal
     */
    private function runner4notrace (&$parsedContent, $lineno, $bot)
    {
        $this->doDummy([ $parsedContent, $lineno, $bot ]);
        $this->trace = false;
        $this->tainting(true);
        return -1;
    }



    /**
     * IDE spoofer
     */
    private function IDEspoofer ()
    {
        $a = $b = $c = null;
        $this->IDEspoofer           ();
        $this->runner               ($a, $b, $c);
        $this->runner4appversion    ();
        $this->runner4runtimeid     ();
        $this->runner4botname       ();
        $this->runner4chatapp       ();
        $this->runner4username      ();
        $this->runner4userlogin     ();
        $this->runner4userlang      ();
        $this->runner4entrytype     ();
        $this->runner4entrytext     ();
        $this->runner4entryid       ();
        $this->runner4err           ();
        $this->runner4peek222       ();
        $this->runner4goto          ($a, $b, $c);
        $this->runner4on            ($a, $b, $c);
        $this->runner4print         ($a, $b, $c);
        $this->runner4display       ($a, $b, $c);
        $this->runner4end           ($a, $b, $c);
        $this->runner4rem           ($a, $b, $c);
        $this->runner4gosub         ($a, $b, $c);
        $this->runner4args          ($a, $b, $c);
        $this->runner4return        ($a, $b, $c);
        $this->runner4call          ($a, $b, $c);
        $this->runner4menu          ($a, $b, $c);
        $this->runner4option        ($a, $b, $c);
        $this->runner4options       ($a, $b, $c);
        $this->runner4word          ($a, $b, $c);
        $this->runner4title         ($a, $b, $c);
        $this->runner4pager         ($a, $b, $c);
        $this->runner4input         ($a, $b, $c);
        $this->runner4set           ($a, $b, $c);
        $this->runner4clear         ($a, $b, $c);
        $this->runner4inc           ($a, $b, $c);
        $this->runner4dec           ($a, $b, $c);
        $this->runner4mul           ($a, $b, $c);
        $this->runner4div           ($a, $b, $c);
        $this->runner4mod           ($a, $b, $c);
        $this->runner4concat        ($a, $b, $c);
        $this->runner4split         ($a, $b, $c);
        $this->runner4count         ($a, $b, $c);
        $this->runner4log           ($a, $b, $c);
        $this->runner4locale        ($a, $b, $c);
        $this->runner4abort         ($a, $b, $c);
        $this->runner4data          ($a, $b, $c);
        $this->runner4channel       ($a, $b, $c);
        $this->runner4tunnel        ($a, $b, $c);
        $this->runner4userid        ($a, $b, $c);
        $this->runner4trace         ($a, $b, $c);
        $this->runner4notrace       ($a, $b, $c);
        $this->runner4bload         ($a, $b, $c);
        $this->runner4bsave         ($a, $b, $c);
        $this->runner4extract       ($a, $b, $c);
    }



}
