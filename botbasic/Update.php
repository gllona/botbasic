<?php
/**
 * Interacción entre el usuario y el sistema, en ese sentido
 *
 * @author      Gorka G LLona                               <gorka@gmail.com> <gorka@venicua.com>
 * @license     http://www.venicua.com/botbasic/license     Licencia de BotBasic
 * @see         http://www.venicua.com/botbasic             Referencia de BotBasic
 * @version     1.0 - 01.jan.2017
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase Update
 *
 * Refleja a cada uno de los mensajes enviados por un usuario a la BotBasic app a través de un bot asociada desde la chatapp.
 *
 * @package botbasic
 */
class Update extends Interaction
{



    /** @var int Tipo del ChatMedium del que proviene el update (ChatMedium::TYPE_...) */
    private $cmType         = null;

    /** @var int Nombre del bot del ChatMedium del que proviene el update (ChatMedium::TYPE_...) */
    private $cmBotName      = null;

    /** @var int ID secuencial entregado por la chatapp, que refleja el orden de recepción de mensajes por sus servidores desde las apps móviles,
     *           y que permite (con un buffer de tiempo en la entrada del pipeline) ordenar los updates tal como han sido emitidos por el usuario,
     *           para facilitar el procesamiento de estas interacciones sin tener que procesar después updates que han sido emitidos antes */
    private $cmSeqId        = -1;
    // FIXME mejora: implementar un buffer de tiempo en la entrada del pipeline (ver hasValidSequence(), DBbroker::readLastlogtimeForDaemon())

    /** @var null|mixed Provee la información de autenticación que debe ser utilizada para descargar recursos multimedia asociados a este update */
    private $cmChatInfo     = null;

    /** @var string ID del usuario de la chatapp, según la chatapp */
    private $cmUserId       = null;

    /** @var string Nombre del usuario de la chatapp, según la chatapp */
    private $cmUserName     = null;

    /** @var string ID (ej. '@abc' para Telegram) del usuario de la chatapp, según la chatapp */
    private $cmUserLogin    = null;

    /** @var string Código de lenguaje de la chatapp cliente tal como es reportado por ella ('IETF language tag' para Telegram) */
    private $cmUserLanguage = null;

    /** @var string Teléfono del usuario de la chatapp, cuando la chatapp lo reporta; no es utilizado por BotBasic */
    private $cmUserPhone    = null;

    /** @var string Cuando el update es el resultado de un click en una opción de menú, el hook asociado se refleja aquí */
    private $menuhook       = null;

    /** @var string|null Permite crear Updates "fake" que son utilizados para disparar eventos (por tiempo) a procesar por los runtimes */
    private $eventCommand   = null;



    /**
     * Constructor
     */
    protected function __construct ()
    {
        parent::__construct(self::TYPE_UPDATE);
    }



    /**
     * Factory method
     *
     * @param  int          $cmType         Una de las constantes ChatMedium::TYPE_...
     * @param  string       $cmBotName      Nombre del bot de la chatapp
     * @param  int          $cmSeqId        Sequence ID proveniente de la chatapp
     * @param  mixed        $cmChatInfo     Atributos utilitarios provenientes del update crudo desde la chatapp, usado normalmente para autenticación
     * @param  string       $cmUserId       ID del usuario de la chatapp, tal como es reportado por ella
     * @param  string       $cmUserName     Nombre del usuario de la chatapp; para el caso de Telegram es la concatenación de nombre y apellido
     * @param  string|null  $cmUserLogin    Identificador textual de usuario; para Telegram es opcional (si el usuario lo ha definido) y de forma '@abc'
     * @param  string|null  $cmUserLanguage IETF language tag de la chatapp cliente; el cliente estándar actualizado de Telegram lo reporta
     * @param  string|null  $cmUserPhone    Teléfono del usuario de la chatapp; Telegram no lo reporta normalmente
     * @param  string|null  $text           Texto del update crudo, tal como es reportado por la chatapp, cuando está disponible
     * @param  string|null  $menuhook       Valor del menuhook, cuando esté disponible, que normalmente ha sido generado con Interaction::encodeMenuhook()
     * @return Update|null                  Instancia creada, o null en caso de error de guardado en BD
     */
    static public function createByAttribs ($cmType, $cmBotName, $cmSeqId, $cmChatInfo, $cmUserId, $cmUserName, $cmUserLogin, $cmUserLanguage, $cmUserPhone, $text, $menuhook)
    {
        $u = self::create(self::TYPE_UPDATE);
        $u->fillFields([ $cmType, $cmBotName, $cmSeqId, $cmChatInfo, $cmUserId, $cmUserName, $cmUserLogin, $cmUserLanguage, $cmUserPhone, $text, $menuhook ]);
        $u->save();
        return $u;
    }



    protected function fillFields ($values)
    {
        list ($cmType, $cmBotName, $cmSeqId, $cmChatInfo, $cmUserId, $cmUserName, $cmUserLogin, $cmUserLanguage, $cmUserPhone, $text, $menuhook) = $values;
        $this->cmType         = $cmType;
        $this->cmBotName      = $cmBotName;
        $this->cmSeqId        = $cmSeqId;
        $this->cmChatInfo     = $cmChatInfo;
        $this->cmUserId       = $cmUserId;
        $this->cmUserName     = $cmUserName;
        $this->cmUserLogin    = $cmUserLogin;
        $this->cmUserLanguage = $cmUserLanguage;
        $this->cmUserPhone    = $cmUserPhone;
        $this->text           = $text;
        $this->menuhook       = $menuhook;
    }



    /**
     * Factory method de Updates "fake" asociados a eventos de tiempo
     *
     * @param  string   $eventCommand
     * @param  string   $eventData
     * @return Update
     */
    static public function createFakeForTimeEvent ($eventCommand, $eventData)
    {
        $u = self::create(self::TYPE_UPDATE);
        $u->eventCommand = $eventCommand;
        $u->text         = $eventData;
        $u->save();
        return $u;
    }
    // FIXME createFakeForTimeEvent(): esto está crudo, refinar cuando se haga al módulo de eventos (además completar comentario)



    /**
     * Crea un Splash con el mismo contenido de este Update
     */
    public function convertToSplash ()
    {
        return Splash::createFromUpdate($this);
    }



    /**
     * Determina si un Update ha llegado al sistema en la secuencia correcta, a partir del atributo sequence_id de los updates crudos que
     * provienen de las chatapps (al menos en el caso de Telegram)
     *
     * Este método está diseñado para efectuar una validación simpĺe por medio de la cual se descarte cualquier udpate crudo que llegue en
     * orden incorrecto (es decir, que teniendo secuencia X, haya sido recibido para su procesamiento después del procesamiento de otro update
     * de secuencia Y, con Y > X).
     *
     * En modo BOTBASIC_DEBUG, se permiten la entrada de Updates cuyo sequence_id sea igual al más alto ya registrado en BD.
     *
     * @return bool
     */
    public function hasValidSequence ()
    {
        if ($this->cmSeqId == -1) { return true; }
        $lastSeqId = DBbroker::getLastUpdateSequenceIdFor($this->cmType, $this->cmBotName, $this->cmChatInfo, $this->id);
        if ($lastSeqId === null) {
            Log::register(Log::TYPE_DATABASE, "U151 Error de BD", $this);
        }
        return $lastSeqId === false ? true : (BOTBASIC_DEBUG ? $this->cmSeqId >= $lastSeqId : $this->cmSeqId > $lastSeqId);
    }



    /**
     * Elimina un Interaction (por ejemplo, para cuando se recibe un Update fuera de secuencia)
     */
    public function delete ()
    {
        $res = DBbroker::deleteInteraction($this->id);
        if (! $res) {
            Log::register(Log::TYPE_DATABASE, "U166 Error de BD");
        }
    }



    /**
     * Obtiene el ID del ChatMedium asociado al Update
     *
     * @return int
     */
    public function getCMtype ()
    {
        return $this->cmType;
    }



    /**
     * Obtiene el nombre de bot del ChatMedium asociado al Update
     *
     * @return int
     */
    public function getCMbotName ()
    {
        return $this->cmBotName;
    }



    /**
     * Obtiene el sequence ID asociado al Update
     *
     * @return int
     */
    public function getCMseqId ()
    {
        return $this->cmSeqId;
    }



    /**
     * Obtiene la cmChatInfo asociada al Update (sea lo que sea y tal como haya sido asignada por el factory method)
     *
     * @return mixed
     */
    public function getCMchatInfo ()
    {
        return $this->cmChatInfo;
    }



    /**
     * Obtiene el ID del usuario de la chatapp asociado al Update
     *
     * @return string
     */
    public function getCMuserId ()
    {
        return $this->cmUserId;
    }



    /**
     * Obtiene el nombre del usuario de la chatapp asociado al Update
     *
     * @return string
     */
    public function getCMuserName ()
    {
        return $this->cmUserName;
    }



    /**
     * Obtiene el username del usuario de la chatapp asociado al Update
     *
     * @return string
     */
    public function getCMuserLogin ()
    {
        return $this->cmUserLogin;
    }



    /**
     * Obtiene el código de idioma de la chatapp asociado al Update
     *
     * @return string
     */
    public function getCMuserLang ()
    {
        return $this->cmUserLanguage;
    }



    /**
     * Obtiene el teléfono del usuario de la chatapp asociado al Update, si hubiese
     *
     * @return string|null
     */
    public function getCMuserPhone ()
    {
        return $this->cmUserPhone;
    }



    /**
     * Obtiene el menuhook asociado al Update, si hubiese
     *
     * @return string|null
     */
    public function getMenuhook ()
    {
        return $this->menuhook;
    }



    /**
     * Obtiene el "time event command" asociado al Update, si hubiese; usado sólo para Updates "fake"
     *
     * @return string|null
     */
    public function getEventCommand ()
    {
        return $this->eventCommand;
    }



    /**
     * Obtiene la imagen asociada al Update, si está definida, o null si no hay
     *
     * @return InteractionResource|null
     */
    public function getImage ()
    {
        return $this->getResource(InteractionResource::TYPE_IMAGE);
    }



    /**
     * Obtiene el clip de audio asociada al Update, si está definido, o null si no hay
     *
     * @return InteractionResource|null
     */
    public function getAudio ()
    {
        return $this->getResource(InteractionResource::TYPE_AUDIO);
    }



    /**
     * Obtiene el clip de voz (grabación directa) asociada al Update, si está definido, o null si no hay
     *
     * @return InteractionResource|null
     */
    public function getVoice ()
    {
        return $this->getResource(InteractionResource::TYPE_VOICE);
    }



    /**
     * Obtiene el clip de video asociada al Update, si está definido, o null si no hay
     *
     * @return InteractionResource|null
     */
    public function getVideo ()
    {
        return $this->getResource(InteractionResource::TYPE_VIDEO);
    }



    /**
     * Obtiene el documento asociado al Update, si está definido, o null si no hay
     *
     * @return InteractionResource|null
     */
    public function getDocument ()
    {
        return $this->getResource(InteractionResource::TYPE_DOCUMENT);
    }



    /**
     * Obtiene el caption asociado al Update, si está definido, o null si no hay
     *
     * @return InteractionResource|null
     */
    public function getCaption ()
    {
        return $this->getResource(InteractionResource::TYPE_CAPTION);
    }



    /**
     * Obtiene el geolocation asociado al Update, si está definido, o null si no hay
     *
     * @return InteractionResource|null
     */
    public function getLocation ()
    {
        return $this->getResource(InteractionResource::TYPE_LOCATION);
    }



    protected function anonymize ()
    {
        $this->cmUserName = $this->cmUserPhone = null;
    }



}
