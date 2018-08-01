<?php
/**
 * Superclase para todas las interacciones entre sistema y usuario en ambos sentidos
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase Interaction
 *
 * Superclase para Update (interacciones provenientes de la chatapp) y Update (interacciones provenientes del servidor de BotBasic).
 *
 * Las interacciones pueden tener asociados recursos (InteractionResource) que engloban a los contenidos multimedia.
 *
 * @package botbasic
 */
abstract class Interaction implements Initializable
{



    /** @const Tipo de Interaction en sentido chatapp hacia sistema ("update") */
    const TYPE_UPDATE = 101;

    /** @const Tipo de Interaction en sentido sistema hacia chatapp ("splash") */
    const TYPE_SPLASH = 102;

    /** @const Indicador para salto de fila en la definición de filas de opciones de menús */
    const TAG_MENU_NEW_ROW      = 'MNL';

    /** @const Indicador para salto de fila en la definición de filas de opciones de menús */
    const TAG_MENU_PAGER_STARTS = 'MPS';

    /** @const Indicador de botón '<<' o similar en la definición del paginador de un menú */
    const TAG_MENU_PAGER_FIRST  = 'MPf';   // tags can be different than the text shown inside buttons; this will be $key->tag inside menuhook.data

    /** @const Indicador de botón '>>' en la definición del paginador de un menú */
    const TAG_MENU_PAGER_LAST   = 'MPl';

    /** @const Indicador de botón '<' en la definición del paginador de un menú */
    const TAG_MENU_PAGER_PREV   = 'MPp';

    /** @const Indicador de botón '>' en la definición del paginador de un menú */
    const TAG_MENU_PAGER_NEXT   = 'MPn';

    /** @const Indicador de botón de número de página en la definición del paginador de un menú */
    const TAG_MENU_PAGER_PAGE   = 'MPP';

    /** @const Máximo número de opciones de menú a desplegar */
    const MENU_MAX_OPTIONS_TO_SHOW = 32;

    /** @var null|int ID del Interaction, según como está en BD */
    protected $id       = null;

    /** @var int Tipo de Interaction (TYPE_...) */
    protected $type     = null;

    /** @var null|int ID del BotBasicChannel asociado */
    protected $bbcId    = null;

    /** @var null|int ID del BizModelUserId asociado al runtime que está asociado a este Interaction; este valor debe ser llenado a efectos
     *                de poder implementar business intelligence sólo por medio del análisis de la tabla interaction de la BD */
    protected $bmUserId = null;

    /** @var null|string Texto del Interaction (si hubiere); se refleja directamente y no como un Resource por su simplicidad y frecuencia */
    protected $text     = null;

    /** @var InteractionResource[] Recursos multimedia asociados a un Interaction */
    protected $resources = [];

    /** @var array[] Buffer para acumular los INSERT a BD que serán aplicados, cada uno, por la codificación de una opción de menú */
    static private $encodedMenuHooksStore = [];



    /**
     * Constructor; no debe ser llamado directamente sino desde el constructor de las subclases
     *
     * @param int   $type   Una de las constantes TYPE_...
     */
    protected function __construct ($type)
    {
        $this->type = $type;
    }



    public function getDefauls ()
    {
        return [ 'interaction', [
            'type'             => -1,
            'subtype'          => -1,
            'cm_type'          => -1,
            'cm_sequence_id'   => -1,
            'cm_chat_info'     => [],
            'cm_user_id'       => '',
            'cm_user_name'     => '',
            'cm_user_login'    => '',
            'cm_user_lang'     => '',
            'cm_user_phone'    => '',
            'bbchannel_id'     => -1,
            'bizmodel_user_id' => -1,
            'text'             => '',
            'menu_hook'        => '',
            'options'          => [],
            'created'          => 'NOW()',
        ] ];
    }



    /**
     * Factory method para las subclases; las crea con los atributos con sus valores por defecto
     *
     * @param  int                  $type       Una de las constantes TYPE_...
     * @return Splash|Update                    Instancia creada
     */
    static protected function create ($type)
    {
        $i = null;
        if     ($type == self::TYPE_UPDATE) { $i = new Update(); }
        elseif ($type == self::TYPE_SPLASH) { $i = new Splash(); }
        return $i;
    }



    /**
     * Método auxiliar usado por readFromDB() para fijar los atributos de cada instancia que se está creando
     *
     * @param  array    $values     Atributos a fijar, en el orden requerido por la implementación específica en cada subclase
     */
    abstract protected function fillFields ($values);



    /**
     * Lee de la BD una Interaction a partir de su ID
     *
     * @param  int                  $id     ID en BD del Interaction
     * @return Splash|Update|null           Instancia leida de BD, o null en caso de error de BD
     */
    static public function readFromDB ($id)
    {
        $data = DBbroker::readInteraction($id);
        if ($data === null)  {
            Log::register(Log::TYPE_DATABASE, "I128 Error de BD");
            return null;
        }
        elseif ($data === false) {
            Log::register(Log::TYPE_RUNTIME, "I132 ID $id no se encuentra");
            return null;
        }
        $type = $data[0];
        $interaction = Interaction::create($type);
        switch ($type) {
            case Interaction::TYPE_UPDATE :
                list (, $cmType, $cmSeqId, $cmChatInfo, $cmUserId, $cmUserName, $cmUserLogin, $cmUserLang, $cmUserPhone, $bbcId, $bmUserId, $text, $menuhook) = $data;
                $interaction->fillFields([ $id, $cmType, $cmSeqId, $cmChatInfo, $cmUserId, $cmUserName, $cmUserLogin, $cmUserLang, $cmUserPhone, $bbcId, $bmUserId, $text, $menuhook ]);
                break;
            case Interaction::TYPE_SPLASH :
                list (, $subType, $bbcId, $bmUserId, $text, $options) = $data;
                $interaction->fillFields([ $id, $subType, $bbcId, $bmUserId, $text, $options ]);
                break;
        }
        return $interaction;
    }



    /**
     * Guarda en BD una Interaction, creando el registro si no existe previamente
     *
     * @param  BotBasicRuntime|null     $runtime    El runtime de la VM que invoca, que es usado (si se pasa) para saber si el bot es anónimo
     * @return bool|null                            true en caso de éxito; null en caso de error de BD
     */
    public function save ($runtime = null)
    {
        if ($this instanceof Update && $runtime !== null && BotConfig::cmBotIsAnonymous($runtime->getBBbotIdx())) {
            $this->anonymize();
        }
        $res = DBbroker::writeInteraction($this);
        if ($res === null) {
            Log::register(Log::TYPE_DATABASE, "I161 Error de BD");
            return null;
        }
        elseif ($res === false) {
            Log::register(Log::TYPE_RUNTIME, "I165 ID no se encuentra al guardar", $this);
            return null;
        }
        elseif ($res === true) {}                      // update done
        else                   { $this->id = $res; }   // insert done
        return true;
    }



    /**
     * Codifica una opción de menú para un Splash, aportando un elemento de seguridad que evita manipulaciones por parte de clientes de chatapps
     * modificados
     *
     * Las opciones deben ser codificadas para hacer opaco el contenido de lso atributos de las opciones de menús (ver parámetros) a las chatapps.
     * Nótese que, en el caso de Telegram, no sólo el código fuente del cliente para Android está disponible, sino que la especificación es cbierta
     * y Telegram provee un API que permite crear apps que interactúen con sus servidores. Esto quiere decir que si se envía información funcional
     * como parte de las opciones de menús, ellas pueden ser leidas y hasta modificadas por esos clientes. Sumado esto a que para el runtime es
     * imposible determinar qué cliente (app) se está usando para la chatapp (el original, uno modificado, una app con Telegram embebido, ...), se
     * puede inferir que incluir información funcional en las opciones representa riesgo operacional.
     *
     * Los valores sensibles que están asociados a opciones de menús son guardados por medio de este método en BD (tabla "menuhook_signature");
     * cada entrada contendrá los datos, un ID y un signature generado al azar. Los últimos dos viajan hacia la chatapp. Cuando se activa una
     * opción de menú, se verifica el signature contra el ID en la BD, y si coinciden se acepta la interacción y se recuperan los datos sensibles.
     * Los datos sensibles, por tanto, no están disponibles para la chatapp.
     *
     * @param  string|int       $key                        "Clave" asociada a la etiqueta; esta es la que recibe el programa BotBasic al procesar
     *                                                      una opción de menú que es activada desde una chatapp
     * @param  mixed            $value                      Etiqueta a ser mostrada en la chatapp cuando se construye el menú (no se codifica actualmente)
     * @param  null|string      $gotoOrGosub                Modalidad (opcional) de la opción de menú (según OPTION ... GOTO u OPTION ... GOSUB)
     * @param  null|string      $gotoGosubTargetLineno      Cuando está definida el parámetro anterior, aquí se especifica el lineno destino
     * @param  BotBasicChannel  $bbChannel                  BotBasicChannel al cual está asociada la interacción
     * @param  int              $lineno                     Lineno del punto de ejecución en el que se codifica la opción de menú
     * @param  bool             $isTag                      Indica si $key es un tag, es decir, una de las constantes de Interaction::TAG_...
     * @param  null|int|string  $tagAttribute               Atributo del tag: se usa un entero para especificar número de página para botones de paginador
     * @return null|string[]|int                            Arreglo con la etiqueta y una "clave" generada a ser enviada a la chatapp junto con la etiqueta,
     *                                                      que sirve para decodificar; $key si se trata de un TAG de formato; o null en caso de error de BD
     */
    static public function encodeMenuhook ($key, $value, $gotoOrGosub, $gotoGosubTargetLineno, $bbChannel, $lineno, $isTag = false, $tagAttribute = null)
    {
         if ($isTag) {
            switch ($key) {
                case Interaction::TAG_MENU_PAGER_FIRST  :
                case Interaction::TAG_MENU_PAGER_LAST   :
                case Interaction::TAG_MENU_PAGER_PREV   :
                case Interaction::TAG_MENU_PAGER_NEXT   : $key = json_decode('{"tag":"'.$key.'"}');                            break;
                case Interaction::TAG_MENU_PAGER_PAGE   : $key = json_decode('{"tag":"'.$key.'","page":"'.$tagAttribute.'"}'); break;
                case Interaction::TAG_MENU_PAGER_STARTS :
                case Interaction::TAG_MENU_NEW_ROW      : return $key;
                default:
                    Log::register(Log::TYPE_RUNTIME, "I242 encodeMenuhook() recibe un tag invalido (" . json_encode($key) . ")");
            }
        }
        $tmpMenuhookId = count(self::$encodedMenuHooksStore);
        self::$encodedMenuHooksStore[$tmpMenuhookId] = [ $key, $gotoOrGosub, $gotoGosubTargetLineno, $bbChannel->getId(), $lineno ];
        return [ "$value", $tmpMenuhookId ];   // after registerEncodedMenuhooks(), $tmpMenuhookId will be changed to definitive menuhook "id|signature"
    }



    /**
     * Una vez que se han generado todas las opciones de menú codificadas, se debe pasar la colección de ellas por aquí para registralas en BD
     *
     * @param  array[]      $encodedMenuHooks       Arreglo con todas las opciones codificadas con encodeMenuhook(); los elementos que no sean arreglos serán ignorados
     * @return bool|null                            null en caso de error de BD; true en caso de éxito
     */
    static public function registerEncodedMenuhooks (&$encodedMenuHooks)
    {
        if (count($encodedMenuHooks) < count(self::$encodedMenuHooksStore)) {
            Log::register(Log::TYPE_DATABASE, "I224 Error de premisa al registrar menuhooks precodificados");
            return null;
        }
        $hookDatas = [];
        foreach ($encodedMenuHooks as $idx => &$encodedMenuHook) {
            if (! is_array($encodedMenuHook)) { continue; }
            $hookDatas[$idx] = self::$encodedMenuHooksStore[ $encodedMenuHook[1] ];
        }
        $menuhooks = DBbroker::registerMenuhooks($hookDatas);
        if ($menuhooks === null) {
            Log::register(Log::TYPE_DATABASE, "I232 Error de BD");
            return null;
        }
        foreach ($menuhooks as $idx => $menuhook) {
            $encodedMenuHooks[$idx][1] = $menuhook;
        }
        self::$encodedMenuHooksStore = [];
        return true;
    }



    /**
     * Una vez que se recibe un evento menuhook por parte de una chatapp, este método permite decodificar la "clave" recibida, previamente
     * codificada con encodeMenuhook(), y obtener de BD los valores sensibles previamente almacenados
     *
     * @param  string       $encodedHookFromChatClient      "Clave" precodificada y recibida desde la chatapp
     * @return array|null                                   [ key, gotoOrGosub, gotoOrGosubTargetLineno, bbChannelId, lineno ],
     *                                                      o null en caso de error de BD o de haber recibido una opción de menú forjada
     */
    static public function decodeMenuhook ($encodedHookFromChatClient)
    {
        if ($encodedHookFromChatClient === null) { return null; }
        $parts = explode('|', $encodedHookFromChatClient, 3);   // from Telegram it comes with a 3rd part: callback_query_id, needed for resetting the menu button status
        if (count($parts) < 2) { return null; }
        list ($menuhookId, $signature) = $parts;
        if (preg_match('/^[0-9]+$/', $menuhookId) !== 1) { return null; }
        $data = DBbroker::readMenuhookDataByIdAndSignature($menuhookId, $signature);
        if ($data === null) {
            Log::register(Log::TYPE_DATABASE, "I233 Error de BD");
            return null;
        }
        elseif ($data === false) {
            Log::register(Log::TYPE_RUNTIME, "I237 menuhook forjado!");
            return null;
        }
        return $data;
    }



    /**
     * Fija directamente el texto de un Interaction a un valor dado
     *
     * Este método no debería ser usado directamente. Está disponible sólo para fijar información para la activación de eventos de tiempo
     * (por medio de Updates "fake") al estilo de "$eventword arg1 arg2 arg3..."
     *
     * @param $text
     */
    public function setText ($text)      { $this->text = $text;     }



    /**
     * Fija información de business intelligence sobre el Splash o Update, que permitirá luego efectuar análisis utilizando únicamente como
     * fuente a la tabla interaction de la BD
     *
     * @param int       $bbChannelId        ID del BotBasicChannel
     * @param int|null  $bizModelUserId     ID del BizModel user, o null de no estar fijado aún por la lógica del programa BotBasic
     */
    public function setBizIntelligencyInfo ($bbChannelId, $bizModelUserId)
    {
        $this->bbcId    = $bbChannelId;
        $this->bmUserId = $bizModelUserId;
    }



    /**
     * Agrega un InteractionResource a un Interaction
     *
     * @param InteractionResource $resource
     */
    public function addResource ($resource)
    {
        $this->resources[] = $resource;
    }



    /**
     * Determina si un Interaction posee un InteractionResource de un tipo determinado
     *
     * @param  int      $type       Tipo del resource; una de las constantes InteractionResource::TYPE_...
     * @return bool
     */
    public function hasResource ($type)
    {
        if ($type == InteractionResource::TYPE_TEXT && $this->text !== null) { return true; }
        return $this->getResource($type) !== null;
    }



    /**
     * Obtiene el primer Interaction del tipo especificado que esté asociado al Interaction, o null si no se encuentra
     *
     * @param  int                              $type       Tipo del resource; una de las constantes InteractionResource::TYPE_...
     * @return InteractionResource|string|null
     */
    protected function getResource ($type)
    {
        if ($type == InteractionResource::TYPE_TEXT && $this->text !== null) { return $this->text; }
        foreach ($this->resources as $r) {
            if ($r->type == $type) { return $r; }
        }
        return null;
    }



    /**
     * Obtiene el ID de un Interaction, según haya sido fijado durante su inserción en BD
     *
     * @return int|null
     */
    public function getId ()
    {
        return $this->id;
    }



    /**
     * Obtiene el valor del TYPE_... de la instancia, asociado a la subclase de la cual se trate
     * @return int
     */
    public function getType ()
    {
        return $this->type;
    }



    /**
     * Obtiene el ID del BotBasicChannel asociado al Interaction
     *
     * Estos ID se almacenan en BD a efectos de business intelligence
     *
     * @return int|null
     */
    public function getBBchannelId ()
    {
        return $this->bbcId;
    }



    /**
     * Obtiene el ID del BizModel user asociado al Interaction
     *
     * Estos ID se almacenan en BD a efectos de business intelligence
     *
     * @return int|null
     */
    public function getBizModelUserId ()
    {
        return $this->bmUserId;
    }



    /**
     * Obtiene el texto del Interaction, si está definido
     *
     * @return null|string
     */
    public function getText ()
    {
        return $this->text;
    }



    /**
     * Obtiene un arreglo con los resources contenidos en el Interaction, si hubiere, o un arreglo vacío si no hay
     *
     * Cuando el Interaction tiene texto, se debe recuperar con getText().
     *
     * @param  int[]|int|null   $exceptTypes    Una o más constantes InteractionResource::TYPE_... que se omitirán del resultado; o null para no exceptuar
     * @param  int[]|int|null   $onlyTypes      Una o más constantes InteractionResource::TYPE_... que serán las únicas que se incluirán en el resultado; o null para ignorar este funcionamiento
     * @return                                  InteractionResource[]
     */
    public function getResources ($exceptTypes = null, $onlyTypes = null)
    {
        if (! is_array($exceptTypes)) { $exceptTypes = $exceptTypes === null ? []   : [ $exceptTypes ]; }
        if (! is_array($onlyTypes))   { $onlyTypes   = $onlyTypes   === null ? null : [ $onlyTypes   ]; }
        $res = [];
        foreach ($this->resources as $r) {
            if (! in_array($r->type, $exceptTypes) && ($onlyTypes === null || in_array($r->type, $onlyTypes))) { $res[] = $r; }
        }
        return $res;
    }



    /**
     * Indica si un Interaction tiene asociados resources distintos a texto
     *
     * @return bool
     */
    public function hasResources ()
    {
        return count($this->resources) > 0;
    }



    /**
     * Elimina la información de identificación personal (nombre, ...) asociada a un Interaction tipo Update
     */
    abstract protected function anonymize ();



}
