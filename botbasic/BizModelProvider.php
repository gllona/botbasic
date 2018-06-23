<?php
/**
 * Proveedor de servicios para el modelo de negocio a través de BizModelAdapterTemplate
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     2.0 - 23.jun.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase BizModelProvider
 *
 * Implementa un patrón proxy que hace opacos los atributos del runtime al modelo de negocio implementado con el conector BizModelAdapter.
 *
 * @package botbasic
 */
class BizModelProvider
{



    /** @var BotBasicChannel Refleja la asociación con el runtime */
    private $bbc          = null;

    /** @var BizModelProvider[] Store para todas las instancias creadas */
    static private $store = [];



    /**
     * Factory method de la clase; su resultado se transfiere al factory method de BizModelAdapter
     *
     * @param  BotBasicChannel      $bbChannel      Canal de BotBasic asociado
     * @return BizModelProvider                     Instancia creada
     */
    static public function create ($bbChannel)
    {
        if ($bbChannel === null) { return null; }
        $bbcId = $bbChannel->getId();
        if (isset(self::$store[$bbcId])) {
            $bmp = self::$store[$bbcId];
        }
        else {
            $bmp = new BizModelProvider();
            $bmp->bbc = $bbChannel;
            self::$store[$bbcId] = $bmp;
        }
        return $bmp;
    }



    /**
     * Obtiene el ID del BotBasicChannel asociado
     *
     * @return int  ID del canal de BotBasic
     */
    public function getBbcId ()
    {
        return $this->bbc->getId();
    }



    /**
     * Obtiene el ID del BizModel user
     *
     * @return int|null     null es retornado cuando no hay un BizModel user asociado al runtime
     */
    public function getBizModelUserId ()
    {
        return $this->bbc->getBBruntime()->getBizModelUserId();
    }



    /**
     * Fija en el runtime el ID del BizModel user a un número entero especificado, cuyo significado está determinado por la lógica de negocio
     *
     * @param  int          $value      ID del BizModel user
     * @return bool|null                El valor de retorno puede ser ignorado, en términos generales, pues no hay operación de BD involucrada
     */
    public function setBizModelUserId ($value)
    {
        return $this->bbc->getBBruntime()->setBizModelUserId($value);
    }



    /**
     * Implementa el getter de un store de valores asociados al BotBasicChannel y disponibles para el BizModelAdepter
     *
     * A diferencia del manejo de variables propias de BotBasic, este store no implementa fallback desde el contexto del BotBasicChannel
     * hacia el contexto del runtime.
     *
     * @param  string       $name           Nombre de la variable
     * @param  bool         $bbChannelId    false para el contexto global; true para el contexto del BotBasicChannel actual; un ID para un BotBasicChannel específico
     * @return mixed|null                   Valor de la variable, o null si no ha sido definida previamente con el setter
     */
    public function get ($name, $bbChannelId = false)
    {
        return $this->bbc->getForBizModelAdapter($name, $bbChannelId);
    }



    /**
     * Implementa el getter de un store de valores asociados al BotBasicChannel y disponibles para el BizModelAdepter
     *
     * A diferencia del manejo de variables propias de BotBasic, este store no implementa fallback desde el contexto del BotBasicChannel
     * hacia el contexto del runtime.
     *
     * @param  string       $name           Nombre de la variable
     * @param  mixed        $value          Valor de la variable
     * @param  bool         $bbChannelId    false para el contexto global; true para el contexto del BotBasicChannel actual; un ID para un BotBasicChannel específico
     */
    public function set ($name, $value, $bbChannelId = false)
    {
        $this->bbc->setForBizModelAdapter($name, $value, $bbChannelId);
    }



    /**
     * Obtiene el valor de una variable de BotBasic, con fallback de contexto hacia el runtime si no está definida en el BotBasicChannel
     *
     * @param  string       $name       Nombre de la variable de BotBasic
     * @return string|null              Valor de la variable, o null si no está definida
     */
    public function getCommonVar ($name)
    {
        return $this->bbc->getBBruntime()->getCommonVar($name, true, true);
    }



    /**
     * Carga un valor previamente guardado en BD con saveToDB() o con la directiva DATA SET
     *
     * Los valores están asociados al BizModel user; cada uno de ellos tienen un espacio de claves independiente.
     *
     * @param  string       $key    Clave del dato
     * @return null|string          Valor del dato, o null si no fue guardado antes de la consulta o hay error de BD
     */
    public function loadFromDB ($key)
    {
        return $this->bbc->getBBruntime()->dataHelperLoader($key);
    }



    /**
     * Almacena un valor previamente en BD para su consulta con loadFromDB() o con la directiva DATA GET
     *
     * Los valores están asociados al BizModel user; cada uno de ellos tienen un espacio de claves independiente.
     *
     * @param  string       $key    Clave del dato
     * @param  string       $value  Valor del dato
     */
    public function saveToDB ($key, $value)
    {
        $this->bbc->getBBruntime()->dataHelperSaver($key, $value);
    }



    /**
     * Consulta a ChatMediumChannel por el valor actualizado (según ChatMedium más recientemente usado) del nombre de un bot de chatapp
     *
     * @param  string       $anOldCMbotName     Nombre supuestamente vigente del bot de chatapp
     * @return string|null                      Nombre actualizado del bot de chatapp, o null si hubo problemas de ubicación del argumento
     */
    public function updatedChatMediaChannelBotName ($anOldCMbotName)
    {
        return $this->bbc->getCMchannel()->updatedChatMediaChannelBotName($anOldCMbotName);
    }



    /**
     * Implementa el selector de nombres de bot de chatapp a partir de una política de round-robin
     *
     * @param  int          $baseChatMediaType  Tipo del ChatMediumXXX que se utiliza para el segundo argumento y para el valor de retorno (ChatMedium::TYPE_...)
     * @param  string       $baseChannelName    Nombre de bot que sirve de base, es decir, se retornará el siguiente en una lista circular de nombres
     * @param  string       $regExpPattern      Expresión regular que actúa de filtro para la selección de nombres
     * @return string|null                      Nombre computado del bot de chatapp
     */
    public function getNextRoundRobinCMchannel ($baseChatMediaType, $baseChannelName, $regExpPattern)
    {
        $botNames = ChatMedium::getCMbotNames($baseChatMediaType, $regExpPattern);
        $pos = array_search($baseChannelName, $botNames);
        if ($pos === false) { return null; }
        return $botNames[ ($pos + 1) % count($botNames) ];
    }



    /**
     * Implementa el selector de nombres de bot de chatapp a partir de una política de canal más antiguamente usado
     *
     * @param  int          $baseChatMediaType  Tipo del ChatMediumXXX que se utiliza para el segundo argumento y para el valor de retorno (ChatMedium::TYPE_...)
     * @param  string       $regExpPattern      Expresión regular que actúa de filtro para la selección de nombres
     * @return string|null                      Nombre computado del bot de chatapp
     */
    public function getLeastUsedCMchannelBotName ($baseChatMediaType, $regExpPattern = '/.*/')
    {
        return $this->bbc->getCMchannel()->getLeastUsedCMchannelBotName($baseChatMediaType, $regExpPattern);
    }



    /**
     * Implementa el selector de nombres de bot de chatapp a partir de una política de canal más recientemente usado
     *
     * @param  int          $baseChatMediaType  Tipo del ChatMediumXXX que se utiliza para el segundo argumento y para el valor de retorno (ChatMedium::TYPE_...)
     * @param  string       $regExpPattern      Expresión regular que actúa de filtro para la selección de nombres
     * @return string|null                      Nombre computado del bot de chatapp
     */
    public function getMostUsedCMchannelBotName ($baseChatMediaType, $regExpPattern = '/.*/')
    {
        return $this->bbc->getCMchannel()->getMostUsedCMchannelBotName($baseChatMediaType, $regExpPattern);
    }



    /**
     * Obtiene la lista de nombres de bots de chatapp que satisfacen un filtro de nombre
     *
     * @param  int      $baseChatMediaType      Tipo del ChatMediumXXX que se utiliza para el valor de retorno (ChatMedium::TYPE_...)
     * @param  string   $regExpPattern          Expresión regular que actúa como filtro de los valores retornados
     * @return string[]                         Nombres de bots de chatapp
     */
    public function getCMchannelBotNames ($baseChatMediaType, $regExpPattern)
    {
        return ChatMedium::getCMbotNames($baseChatMediaType, $regExpPattern);
    }



    /**
     * Implementa un helper para BizModelAdapter que permite efectuar un Splash tipo PRINT
     *
     * Se puede obviar pasar los últimos tres parámetros, o los últimos dos parámetros.
     *
     * @param string        $text               Texto a mostrar en el canal de BotBasic
     * @param null|string   $botName            Nombre del bot de BotBasic, o null para el bot actual
     * @param null|int      $bizModelUserId     ID del BizModel user, o null para el actual siempre que $botName sea el actual
     * @param null|int      $bbChannelId        ID del BotBasicChannel, o null para el actual siempre que $botName sea el actual
     */
    public function bbPrint ($text, $botName = null, $bizModelUserId = null, $bbChannelId = null)
    {
        $this->bbc->getBBruntime()->splashHelperPrint($text, $botName, $bizModelUserId, $bbChannelId);
    }



    /**
     * Implementa un helper para BizModelAdapter que permite efectuar un Splash tipo MENU
     *
     * Se puede obviar pasar los últimos tres parámetros, o los últimos dos parámetros.
     *
     * @param string|null   $predefMenuName     Nombre del menú predefinido, o null si es un menú estándar
     * @param mixed[]|null  $predefMenuArgs     Argumentos que se pasan al menú predefinido; o null si es un menú estándar
     * @param string[]      $titles             Títulos que serán mostrados en el menú (es posible pasar un único string)
     * @param string[]      $options            Textos de las opciones del menú
     * @param array[]|null  $pager              Pager spec a utilizar: [ pagerSpec, pagerArg ], o null para ningún paginador
     * @param string[]      $toVars             Arreglo con las variables de BotBasic donde se guardan los resultados del menú (en el espacio de nombres de este runtime)
     * @param int           $srcLineno          Número de línea que sale reportado en la bitácora en caso de errores
     * @param string        $srcBot             Nombre del bot de BotBasic que sale reportado en la bitácora en caso de errores
     * @param null|string   $botName            Nombre del bot de BotBasic, o null para el bot actual
     * @param null|int      $bmUserId           ID del BizModel user, o null para el actual siempre que $botName sea el actual
     * @param null|int      $bbChannelId        ID del BotBasicChannel, o null para el actual siempre que $botName sea el actual
     */
    public function bbMenu ($predefMenuName, $predefMenuArgs, $titles, $options, $pager, $toVars, $srcLineno, $srcBot, $botName = null, $bmUserId = null, $bbChannelId = null)
    {
        $this->bbc->getBBruntime()->splashHelperMenu($predefMenuName, $predefMenuArgs, $titles, $options, $pager, $toVars, $srcLineno, $srcBot, $botName, $bmUserId, $bbChannelId);
    }



    /**
     * Implementa un helper para BizModelAdapter que permite efectuar un Splash tipo INPUT
     *
     * Se puede obviar pasar los últimos tres parámetros, o los últimos dos parámetros.
     *
     * @param string            $dataType           Uno de: date | positiveInteger | positiveDecimal | string
     * @param string[]          $titles             Títulos que serán mostrados en el menú (es posible pasar un único string)
     * @param string|null       $word               VALOR que, de ser introducido por el usuario, será sustituido al valor de la variable de BotBasic $fromVar
     * @param string|string[]   $toVars             Variable(s) de BotBasic donde se guardan los resultados del input (en el espacio de nombres de este runtime)
     * @param string|null       $fromVar            Variable de BotBasic de donde se extrae el valor que es asignado a $toVar cuando la entrada es el valor de $word
     * @param int               $srcLineno          Número de línea que sale reportado en la bitácora en caso de errores
     * @param string            $srcBot             Nombre del bot de BotBasic que sale reportado en la bitácora en caso de errores
     * @param null|string       $botName            Nombre del bot de BotBasic, o null para el bot actual
     * @param null|int          $bmUserId           ID del BizModel user, o null para el actual siempre que $botName sea el actual
     * @param null|int          $bbChannelId        ID del BotBasicChannel, o null para el actual siempre que $botName sea el actual
     */
    public function bbInput ($dataType, $titles, $word, $toVars, $fromVar, $srcLineno, $srcBot, $botName = null, $bmUserId = null, $bbChannelId = null)
    {
        if (! is_array($toVars)) { $toVars = [ $toVars ]; }
        $this->bbc->getBBruntime()->splashHelperInput($dataType, $titles, $word, $toVars, $fromVar, $srcLineno, $srcBot, $botName, $bmUserId, $bbChannelId);
    }



}
