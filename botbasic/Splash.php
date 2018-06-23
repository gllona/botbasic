<?php
/**
 * Interacción entre el sistema y el usuario, en ese sentido
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     2.0 - 23.jun.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase Splash
 *
 * Refleja un texto, menú o contenido multimedia que es enviado a una chatapp desde el servidor de BotBasic.
 *
 * @package botbasic
 */
class Splash extends Interaction
{



    /** @const Subtipo de Interaction para el tipo Splash: texto */
    const SUBTYPE_TEXT     = 101;

    /** @const Subtipo de Interaction para el tipo Splash: menú (texto + opciones) */
    const SUBTYPE_MENU     = 102;

    /** @const Subtipo de Interaction para el tipo Splash: recurso multimedia */
    const SUBTYPE_RESOURCE = 103;

    /** @var int Subtipo (SUBTYPE_...) */
    private $subType  = null;

    /** @var array|null opciones del menú; se trata de un arreglo de resultados de Interaction::encodeMenuhook() */
    private $options  = null;



    /**
     * Obtiene el subtipo del Splash, una de las constantes SUBTYPE_...
     *
     * @return int
     */
    public function getSubType ()
    {
        return $this->subType;
    }



    /**
     * Obtiene las opciones de menú del Splash, si están definidas
     *
     * @return array|null
     */
    public function getOptions ()
    {
        return $this->options;
    }



    /**
     * Obtiene el primer recurso incluido en el Splash, si es que existen
     *
     * @return InteractionResource|null
     */
    public function getTheResource ()
    {
        return $this->resources === null || count($this->resources) == 0 ? null : $this->resources[0];
    }



    protected function __construct ()
    {
        parent::__construct(self::TYPE_SPLASH);
    }



    protected function fillFields ($values)
    {
        list ($id, $subType, $bbcId, $bmUserId, $text, $options) = $values;
        $this->subType  = $subType;
        $this->bbcId    = $bbcId;
        $this->bmUserId = $bmUserId;
        $this->id       = $id;
        $this->text     = $text;
        $this->options  = $options;
    }



    /**
     * Factory method; crea un Splash con subtipo texto y el contenido especificado
     *
     * @param  string       $text   Texto
     * @return Splash|null          null es retornado si el argumento no es texto
     */
    static public function createWithText ($text)
    {
        if (! is_string($text) && ! is_int($text)) {
            Log::register(Log::TYPE_RUNTIME, "S112 Argumento no es string");
            return null;
        }
        $s          = new Splash();
        $s->subType = self::SUBTYPE_TEXT;
        $s->text    = "$text";
        $s->save();
        return $s;
    }



    /**
     * Factory method; crea un Splash con subtipo recurso y el contenido especificado
     *
     * @param  InteractionResource  $resource       Recurso
     * @return Splash|null                          null es retornado si el argumento no es una instancia de InteractionResource
     */
    static public function createWithResource ($resource)
    {
        if (! $resource instanceof InteractionResource) {
            Log::register(Log::TYPE_RUNTIME, "S133 Argumento no es InteractionResource");
            return null;
        }
        $s            = new Splash();
        $s->subType   = self::SUBTYPE_RESOURCE;
        $s->resources = [ $resource ];
        $s->save();
        return $s;
    }



    /**
     * Factory method; crea un Splash con subtipo menú y el contenido especificado
     *
     * @param  array[]      $options    Opciones del menú, cada una codificada con Interaction::encodeMenuhook()
     * @param  null|string  $title      Texto del menú mostrado previo a las opciones
     * @return Splash|null              null es retornado si el argumento no es una instancia de InteractionResource
     */
    static public function createWithMenu ($options, $title = null)
    {
        if (! is_array($options)) {
            Log::register(Log::TYPE_RUNTIME, "S155 Argumento no es arreglo");
            return null;
        }
        $s          = new Splash();
        $s->subType = self::SUBTYPE_MENU;
        $s->text    = $title;
        $s->options = $options;
        $s->save();
        return $s;
    }



    /**
     * Factory method; crea un Splash como un clon del especificado
     *
     * La clonación clona a su vez los InteractionResources agregados, y cada uno de ellos quedará como un clon para el cual el contenido/archivo
     * multimedia correspondiente no será descargado más de una vez en el sistema, a fin de evitar ineficiencias en storage.
     *
     * @return Splash   Nueva instancia clonada
     */
    public function createByCloning ()
    {
        $clone     = clone $this;
        $clone->id = null;
        $clone->resources = [];
        foreach ($this->resources as $resource) {
            $clone->resources[] = $resource->createByCloning();
        }
        $clone->save();
        return $clone;
    }



    /**
     * Factory method; crea un Splash como un espejo de la información contenida en un Update
     *
     * @param  Update|null      $update     Update que sirve de espejo isomorfo para el nuevo Splash
     * @return Splash                       Nueva instancia
     */
    static public function createFromUpdate ($update)
    {
        $built = true;
        $s     = new Splash();
        if ($s->hasResources()) {
            $caption = $update->getResource(InteractionResource::TYPE_CAPTION)->metainfo;   // FIXME el undressing de captions no esta implementado
            $r       = $update->getResources(InteractionResource::TYPE_CAPTION)[0]->createByCloning();
            if ($r !== null) {
                $s->fillFields([ null, self::SUBTYPE_RESOURCE, null, null, $caption, null ]);   // ($id, $subType, $bbcId, $bmUserId, $text, $options)
                // FIXME si viene una imagen con un caption, entonces no se mostrara como caption sino como texto
                $s->addResource($r);
            }
            else {
                $built = false;
            }
        }
        else {
            $s->fillFields([ null, self::SUBTYPE_TEXT, null, null, $update->getText(), null ]);
        }
        if ($built) {
            if ($s->save() === null) { $s = null; }
        }
        return $s;
    }



    /**
     * Agrega a un Splash de tipo menú las opciones especificadas
     *
     * Si hay opciones previamente agregadas en este Splash, las nuevas se concatenan a las anteriores.
     *
     * @param  array[]  $options    Opciones (posiblemente adicionales) codificadas con Interaction::encodeMenuhook()
     * @return null|bool            null si el argumento no es un arreglo o si el Splash no es de tipo menú; true de otro modo
     */
    public function addMenuOptions ($options)
    {
        if (! is_array($options)) {
            Log::register(Log::TYPE_RUNTIME, "S234 Argumento no es arreglo");
            return null;
        }
        if ($this->subType != self::SUBTYPE_MENU) {
            Log::register(Log::TYPE_RUNTIME, "S238 Subtipo no es menu", $this);
            return null;
        }
        $this->options = array_merge($this->options, $options);
        return true;
    }



    protected function anonymize ()
    {
        // do nothing
    }



}
