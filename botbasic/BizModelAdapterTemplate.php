<?php
/**
 * Superclase genérica para todas las diferentes implementaciones de la clase BizModelAdapter
 *
 * @author      Gorka G LLona                               <gorka@gmail.com> <gorka@venicua.com>
 * @license     http://www.venicua.com/botbasic/license     Licencia de BotBasic
 * @see         http://www.venicua.com/botbasic             Referencia de BotBasic
 * @version     1.0 - 01.jan.2017
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase BizModelAdapterTemplate
 *
 * Superclase de BizModelAdapter, que obliga a ella a implementar ciertos métodos declarados aquí abstractos.
 *
 * La clase se instancia con un objeto BizModelProvider, el cual está asociado a un BotBasicChannel específico. Esta clase y el BizModelProvider
 * implementan opacidad hacia BizModelAdapter e impíden que desde la última se puedan explorar los atributos del runtime.
 *
 * @package botbasic
 */
abstract class BizModelAdapterTemplate
{



    /** @const política para computeNextCMchannelBotName que elige un canal de BotBasic por round-robin */
    const CHANNELS_POLICY_ROUNDROBIN = 101;

    /** @const política para computeNextCMchannelBotName que elige el canal menos usado (con updated más antiguo en BD);
     *         se seleccionarán primero los canales disponibles no usados aún antes de seleccionar los ya previamente usados */
    const CHANNELS_POLICY_LEASTUSED  = 102;

    /** @const política para computeNextCMchannelBotName que elige el canal más recientemente usado (con updated más reciente en BD) */
    const CHANNELS_POLICY_MOSTUSED   = 103;

    // const CHANNELS_POLICY_FIRSTCREATED = 104;   // not yet implemented

    /** @var BizModelProvider conector con el runtime */
    private $bmProvider                 = null;

    /** @var string[] store para las variables mágicas que contienen nombres de bots de ChatMedia, cuyos valores pueden ser autoactualizables
     *                si son gestionados a través de los métodos provistos por la librería (ejemplos en BizModelAdapter) */
    static private $cmBotNamesMagicVars = [];

    /** @var BizModelAdapterTemplate[] store para todas las instancias de esta clase */
    static private $instancesStore      = [];



    /**
     * Factory de instancias de esta clase
     *
     * Este método es para su exclusiva utilización por parte del runtime.
     *
     * @param  BizModelProvider $bmProvider
     * @return BizModelAdapter
     */
    static public function create ($bmProvider)
    {
        if ($bmProvider !== null && ! $bmProvider instanceof BizModelProvider) { return null; }
        $bbcId = $bmProvider->getBbcId();
        if (isset(self::$instancesStore[$bbcId])) {
            $bma = self::$instancesStore[$bbcId];
        }
        else {
            $bma = new BizModelAdapter();
            $bma->bmProvider = $bmProvider;
            self::$instancesStore[$bbcId] = $bma;
        }
        return $bma;
    }



    /**
     * Seudo-destructor de instancias de esta clase
     *
     * Este método es para su exclusiva utilización por parte del runtime y debe ser implementado por BizModelAdapter
     * para, por ejemplo, proveer persistencia en los objetos del bizmodel que han sido modificados.
     *
     * @return bool
     */
    abstract public function terminate ();



    /////////////////////////////////////////////
    // UTILITIES FOR SAVING AND RECOVERING VALUES
    /////////////////////////////////////////////



    /**
     * Utilidad provista al programador de BizModelAdapter para retornar el ID del BizModel user, el cual puede ser null si no ha sido definido
     *
     * @return null|int ID del BizModel user
     */
    protected function getBizModelUserId ()
    {
        return $this->bmProvider->getBizModelUserId();
    }



    /**
     * Utilidad provista al programador de BizModelAdapter para ordenar al runtime asignar el ID del BizModel user
     *
     * @param  int          $value  ID del BizModel user
     * @return null|bool            True en caso de éxito, false si no se puede validar el ID contra la BD (?), null si el argumento no es un entero
     */
    protected function setBizModelUserId ($value)
    {
        return $this->bmProvider->setBizModelUserId($value);
    }



    /**
     * Utilidad provista al programador de BizModelAdapter para obtener un valor de variable previamente asignado con set()
     * (ver set() para detalles)
     *
     * Nota: no usar (no está implementada la persistencia).
     *
     * @param  string   $name           Nombre de la variable a recuperar
     * @param  int|bool $bbChannelId    ID del BotBasicChannel al cual estará asociada la variable, o true para el canal actual, o false para un contexto global
     *                                  común para todos los BotBasicChannels
     * @return mixed                    Valor de la variable
     */
    protected function get ($name, $bbChannelId = false)   // pass false for global context, true for current bbchannel context, or any bbc id for that context
    {
        return $this->bmProvider->get($name, $bbChannelId);
    }
    // TODO get(): esta rutina requiere cambios, ver set()


    /**
     * Utilidad provista al programador de BizModelAdapter para fijar una entrada dentro del mapa de variables globales o asociadas
     * a BotBasicChannels y disponibles para el BizModelAdapter; son persistentes
     *
     * Nota: no usar (no está implementada la persistencia).
     *
     * @param  string   $name           Nombre de la variable a asignar
     * @param  mixed    $value          Valor de la variable
     * @param  int|bool $bbChannelId    ID del BotBasicChannel al cual estará asociada la variable, o true para el canal actual, o false para un contexto global
     *                                  común para todos los BotBasicChannels
     */
    protected function set ($name, $value, $bbChannelId = false)   // idem
    {
        $this->bmProvider->set($name, $value, $bbChannelId);
    }
    // TODO get/set(): tal como está implementado ahora las variales son volátiles y no persistentes (no permite implementar magic vars a no ser que se...
    // use los dataHelpers, lo cual es incómodo e inconveniente porque están asociados a los BMuserId:
    // * ver cambio en BD: bbvars.source
    // * implementar para get() y set() una copia adaptada del funcionamiento de las common vars para BBC y RT
    // * al igual que allá, se leerán todas las vars con la instanciación del BMA (deberían estar metidas en el provider)
    // * buscar un mecanismo de eliminación de vars que ya no se vayan a usar, como set() a null
    // * al cambiar, hacerlo tambien en BotBasicChannel::{get|set}ForBizModelAdapter()



    /**
     * Utilidad provista al programador de BizModelAdapter para obtener el valor de una variable común (no mágica) de BotBasic
     * (implementa cierto grado de reflexión de sólo lectura utilizable desde BizModelAdapter)
     *
     * @param  string   $name   Nombre de la variable de BotBasic
     * @return string           Valor de la variable, o null si no ha sido definida por la lógica del programa BotBasic
     */
    protected function getCommonVar ($name)
    {
        return $this->bmProvider->getCommonVar($name);
    }



    /**
     * Utilidad provista al programador de BizModelAdapter para cargar un valor de la tabla datahelper_data de la BD,
     * siempre asociado al BizModel user ID, asignado previamente con saveToDB()
     *
     * @param  string   $key    Clave única del valor, de hasta 255 caracteres
     * @return mixed            Valor recuperado, o null si no se encuentra, si el BizModel user ID no está asignado o si hubo error de BD
     */
    protected function loadFromDB ($key)
    {
        return $this->bmProvider->loadFromDB($key);
    }



    /**
     * Utilidad provista al programador de BizModelAdapter para almacenar un valor de la tabla datahelper_data de la BD,
     * siempre asociado al BizModel user ID
     *
     * @param  string   $key    Clave única del valor, de hasta 255 caracteres
     * @param  mixed    $value  Valor a almacenar; se pueden almacenar valores null pero no su recuperación es dificultosa de evaluar con loadFromDB()
     */
    protected function saveToDB ($key, $value)
    {
        $this->bmProvider->saveToDB($key, $value);
    }



    ////////////////////////////////////////////////
    // UTILITIES FOR UPDATING THE CHATMEDIA BOT NAME
    ////////////////////////////////////////////////



    /**
     * Utilidad provista al programador de BizModelAdapter para obtener un nombre actualizado para un nombre de bot de una chatapp,
     * en función de la chatapp manejada actualmente por el ChatMediumChannel
     *
     * Este método permite al programador BotBasic actualizar valores obtenidos previamente con la directva CHANNEL y que no necesariamente
     * están vigentes para el momento de ejecución, debido a que el usuario puede haberse cambiado de chatapp en forma transparente para el programa.
     *
     * @param  string       $anOldCMbotName     Nombre supuestamente vigente del bot de la chatapp
     * @return null|string                      Nombre vigente garantizado, o null en caso de problemas en su cálculo
     */
    protected function updatedChatMediaChannelBotName ($anOldCMbotName)
    {
        return $this->bmProvider->updatedChatMediaChannelBotName($anOldCMbotName);
    }



    /**
     * Utilidad para el programador del BizModelAdapter que almacena el nombre de un bot de una chatapp que esté siendo usado el programa BotBasic
     *
     * Los nombres se autoactualizan con updatedChatMediaChannelBotName() cuando son recuperados con getCMchannelBotNameMagicVar().
     *
     * @param  string   $name   Nombre de la variable mágica contentiva del nombre de bot de la chatapp
     * @param  string   $value  Valor de la variable mágica
     */
    protected function setCMchannelBotNameMagicVar ($name, $value)
    {
        self::$cmBotNamesMagicVars[$name] = $value;
    }



    /**
     * Utilidad para el programador del BizModelAdapter que recupera el nombre de un bot de una chatapp previamente almacenado con setCMchannelBotNameMagicVar()
     *
     * @param  string       $name   Nombre de la variable mágica contentiva del nombre de bot de la chatapp
     * @return null|string          Valor de la variable mágica, actualizada con updatedChatMediaChannelBotName(), o null si no ha sido guardada antes
     */
    protected function getCMchannelBotNameMagicVar ($name)
    {
        if (! isset(self::$cmBotNamesMagicVars[$name])) { return null; }
        $newValue = $this->updatedChatMediaChannelBotName(self::$cmBotNamesMagicVars[$name]);
        self::$cmBotNamesMagicVars[$name] = $newValue;
        return $newValue;
    }



    /**
     * Utilidad provista al programador del BizModelAdapter para una implementación sencilla de makeAcmBotName()
     *
     * De acuerdo a un CHANNELS_POLICY_..., obtiene un nombre de bot de una chatapp, la cual se especifica en un parámetro,
     * usando para la selección un filtro de nombres especificado como una expresión regular.
     *
     * @param  string       $regExpPattern      Expresión regular a usar como filtro; por ejemplo: '/^np_[0-9]+_.*$/'
     * @param  int          $policy             Una de las constantes CHANNELS_POLICY_... de esta clase
     * @param  int          $baseChatMediaType  Tipo del ChatMedia, según ChatMedia::TYPE_...
     * @param  null|string  $baseChannelName    Canal base de cálculo, para la política de round-robin
     * @return null                             Nombre del nuevo canal (puede ser un canal previamente usado)
     */
    protected function computeNextCMchannelBotName ($regExpPattern, $policy, $baseChatMediaType, $baseChannelName = null)
    {
        if ($policy == self::CHANNELS_POLICY_ROUNDROBIN) { if ($baseChannelName === null) { return null; } }
        else                                             { if ($baseChannelName !== null) { return null; } }
        if ($policy == self::CHANNELS_POLICY_ROUNDROBIN) {
            $nextChannelName = $this->bmProvider->getNextRoundRobinCMchannel($baseChatMediaType, $baseChannelName, $regExpPattern);
        }
        elseif ($policy == self::CHANNELS_POLICY_LEASTUSED) {
            $nextChannelName = $this->bmProvider->getLeastUsedCMchannelBotName($baseChatMediaType, $regExpPattern);
        }
        elseif ($policy == self::CHANNELS_POLICY_MOSTUSED) {
            $nextChannelName = $this->bmProvider->getMostUsedCMchannelBotName($baseChatMediaType, $regExpPattern);
        }
        else {
            $nextChannelName = null;
        }
        return $nextChannelName;
    }



    /**
     * Utilidad provista al desarrollador del BizModelAdapter para la implementación de makeAcmBotName() que devuelve todos los nombres de bot
     * de una chatapp especificada que pasan por un filtro especificado por medio de una expresión regular
     *
     * @param  string   $regExpPattern      Filtro a aplicar sobre los nombres a recuperar
     * @param  int      $baseChatMediaType  Tipo del chatapp según ChatMedium::TYPE_...
     * @return array                        Nombres recuperados
     */
    protected function getCMchannelBotNames ($regExpPattern, $baseChatMediaType)
    {
        return $this->bmProvider->getCMchannelBotNames($baseChatMediaType, $regExpPattern);
    }



    /**
     * Este método debe ser implementado por el programador del BizModelAdapter para proveer un nombre de bot de chatapp al runtime
     * cuando se ejecute la directiva CHANNEL new
     *
     * @param  string   $bbBotName  Nombre del bot del programa BotBasic (no confundir con el nombre de bot de la chatapp)
     * @return mixed                Nombre del canal a ser asignado a una variable por la directiva CHANNEL new
     */
    abstract public function makeAcmBotName ($bbBotName);



    //////////////////////
    // SPLASHES GENERATORS
    //////////////////////



    /**
     * Utilidad provista al programador de BizModelAdapter que le permite emitir un texto hacia un canal de BotBasic
     *
     * @param string        $text           Texto del mensaje
     * @param null|string   $botName        Nombre del bot del programa BotBasic, o null para el actual
     * @param null|int      $bmUserId       ID del BizModel user, o null para el actual (siempre que $botName sea el actual o null)
     * @param null|int      $bbChannelId    ID del BotBasicChannel en el que se mostrará la interacción, o null para el actual en caso de
     *                                      ser null $bmUserId; este argumento puede ser null sólo cuando $bmUserId es null
     */
    protected function bbPrint ($text, $botName = null, $bmUserId = null, $bbChannelId = null)
    {
        $this->bmProvider->bbPrint($text, $botName, $bmUserId, $bbChannelId);
    }



    /**
     * Utilidad provista al programador de BizModelAdapter que le permite mostrar un menu en un canal de BotBasic, creando las rutas correspondientes
     * en el runtime
     *
     * @param string|null   $predefMenuName     Nombre del menú predefinido, o null si es un menú estándar
     * @param mixed[]|null  $predefMenuArgs     Argumentos que se pasan al menú predefinido; o null si es un menú estándar
     * @param string[]      $titles             Títulos que serán mostrados en el menú (es posible pasar un único string)
     * @param string[]      $options            Textos de las opciones del menú
     * @param array[]|null  $pager              Pager spec a utilizar: [ pagerSpec, pagerArg ], o null para ningún paginador
     * @param string[]      $toVars             Arreglo con las variables de BotBasic donde se guardan los resultados del menú (en el espacio de nombres de este runtime)
     * @param int           $srcLineno          Número de línea que sale reportado en la bitácora en caso de errores
     * @param string        $srcBot             Nombre del bot de BotBasic que sale reportado en la bitácora en caso de errores
     * @param null|string   $botName            Nombre del bot del programa BotBasic, o null para el actual
     * @param null|int      $bmUserId           ID del BizModel user, o null para el actual (siempre que $botName sea el actual o null)
     * @param null|int      $bbChannelId        ID del BotBasicChannel en el que se mostrará la interacción, o null para el actual en caso de
     *                                          ser null $bmUserId; este argumento puede ser null sólo cuando $bmUserId es null
     */
    protected function bbMenu ($predefMenuName, $predefMenuArgs, $titles, $options, $pager, $toVars, $srcLineno, $srcBot, $botName = null, $bmUserId = null, $bbChannelId = null)
    {
        $this->bmProvider->bbMenu($predefMenuName, $predefMenuArgs, $titles, $options, $pager, $toVars, $srcLineno, $srcBot, $botName, $bmUserId, $bbChannelId);
    }



    /**
     * Utilidad provista al programador de BizModelAdapter que le permite mostrar un menu en un canal de BotBasic, creando las rutas correspondientes
     * en el runtime
     *
     * @param string        $dataType           Uno de: date | positiveInteger | positiveDecimal | string
     * @param string[]      $titles             Títulos que serán mostrados en el menú (es posible pasar un único string)
     * @param string|null   $word               VALOR que, de ser introducido por el usuario, será sustituido al valor de la variable de BotBasic $fromVar
     * @param string        $toVar              Variables de BotBasic donde se guarda el resultado del input (en el espacio de nombres de este runtime)
     * @param string|null   $fromVar            Variable de BotBasic de donde se extrae el valor que es asignado a $toVar cuando la entrada es el valor de $word
     * @param int           $srcLineno          Número de línea que sale reportado en la bitácora en caso de errores
     * @param string        $srcBot             Nombre del bot de BotBasic que sale reportado en la bitácora en caso de errores
     * @param null|string   $botName            Nombre del bot del programa BotBasic, o null para el actual
     * @param null|int      $bmUserId           ID del BizModel user, o null para el actual (siempre que $botName sea el actual o null)
     * @param null|int      $bbChannelId        ID del BotBasicChannel en el que se mostrará la interacción, o null para el actual en caso de
     *                                          ser null $bmUserId; este argumento puede ser null sólo cuando $bmUserId es null
     */
    protected function bbInput ($dataType, $titles, $word, $toVar, $fromVar, $srcLineno, $srcBot, $botName = null, $bmUserId = null, $bbChannelId = null)
    {
        $this->bmProvider->bbInput($dataType, $titles, $word, $toVar, $fromVar, $srcLineno, $srcBot, $botName, $bmUserId, $bbChannelId);
    }



}
