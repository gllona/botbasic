<?php
/**
 * Recursos asociados a cada interacción entre sistema y usuario
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase InteractionResource
 *
 * Representa un recurso multimedia que es enviado o recibido como parte de un Interaction (Update/Splash).
 *
 * Dentro del código y nombres de BotBasic, "resource" equivale a InteractionResource.
 *
 * @package botbasic
 */
class InteractionResource
{



    /** Tipo de recurso: usado por DBbroker para señalizar Splashes que son "clones" de un Interaction cuando se utilizan túneles de BotBasic;
     *  la clonación de recursos evita la duplicación de los archivos que reflejan sus contenidos, y permite reutilizar el file_id de Telegram */
    const TYPE_CLONED     = 100;

    /** Tipo de recurso: texto; no usado actualmente para recursos (pues los textos van en Interaction) pero sí para la lógica que implementa túneles */
    const TYPE_TEXT       = 101;

    /** Tipo de recurso: imagen; en Telegram se descargará siempre la imagen que tenga la máxima resolución */
    const TYPE_IMAGE      = 102;

    /** Tipo de recurso: clip de audio */
    const TYPE_AUDIO      = 103;

    /** Tipo de recurso: clip de voz */
    const TYPE_VOICE      = 104;

    /** Tipo de recurso: clip de video */
    const TYPE_VIDEO      = 105;

    /** Tipo de recurso: clip de video */
    const TYPE_VIDEONOTE  = 106;

    /** Tipo de recurso: documento */
    const TYPE_DOCUMENT   = 107;

    /** Tipo de recurso: geolocalización; no se guarda como archivo descargado sino en $content */
    const TYPE_LOCATION   = 108;

    /** Tipo de recurso: caption (asociado a algunos updates que vienen de la chatapp); se almacena junto con el recurso pero no se usa en BotBasic */
    const TYPE_CAPTION    = 109;

    /** @var int ID del recurso, tal como aparece en la BD */
    public $id            = -1;

    /** @var int Tipo del recurso (TYPE_...) */
    public $type          = null;

    /** @var null|InteractionResource Resource original; aplica sólo a generados por clonación */
    public $clonedFrom    = null;

    /** @var int Tipo ($type) del ChatMedium del que proviene el recurso; se refleja en BD a efectos de business intelligence */
    public $cmType        = null;

    /** @var mixed Información de autenticación necesaria para descargar el contenido del recurso desde los servidores de la chatapp */
    public $cmcAuthInfo   = null;

    /** @var mixed ID del recurso, necesario para descargar el contenido desde los servidores de la chatapp */
    public $fileId        = null;

    /** @var string|null Nombre del archivo local que refleja el recurso después de que ha sido descargado, o null cuando aún no lo ha sido */
    public $filename      = null;

    /** @var null|mixed Metadata del resource (atributos que deben ser enviados a Telegram, etc.)
     *                  En recursos que no ameritan un archivo para su contenido (como geolocalizaciones), la información se almacena aquí */
    public $metainfo      = null;

    /** @var string Estado de la descarga del archivo del contenido, según campo ENUM de la BD */
    public $downloadState = null;

    /** @var array Nombres de los tipos de recursos */
    private static $typeStrings = [
        self::TYPE_TEXT      => "text",
        self::TYPE_IMAGE     => "image",
        self::TYPE_AUDIO     => "audio",
        self::TYPE_VOICE     => "voice",
        self::TYPE_VIDEO     => "video",
        self::TYPE_VIDEONOTE => "videonote",
        self::TYPE_DOCUMENT  => "document",
        self::TYPE_CAPTION   => "caption",
        self::TYPE_LOCATION  => "location",
    ];



    /**
     * Indica si un tipo es una de las constantes TYPE_...
     *
     * @param  int      $type
     * @return bool
     */
    static public function isValidType ($type)
    {
        return isset(self::$typeStrings[$type]);
    }



    /**
     * Retorna un string correspondiente al tipo de resource
     *
     * @param  int          $type       Una de las constantes TYPE_...
     * @return string|null              Nombre asociado al tipo de resource; o null si el tipo no es válido
     */
    static public function typeString ($type)
    {
        return self::isValidType($type) ? self::$typeStrings[$type] : null;
    }



    /**
     * Retorna el tipo asociado a un typeString
     *
     * @param  string   $typeString     Uno de los posibles typeStrings
     * @return int|null                 Constante TYPE_... asociada al typeString suministrado
     */
    static public function getType ($typeString)
    {
        foreach (self::$typeStrings as $type => $value) {
            if ($value == $typeString) {
                return $type;
            }
        }
        return null;
    }



    /**
     * Constructor
     *
     * @param  int  $type       Una de las constantes TYPE_...
     */
    private function __construct ($type)
    {
        $this->type = $type;
    }



    /**
     * Factory method: crea un resource a partir de un content pasado como parámetro
     *
     * @param  int                          $type           Una de las constantes TYPE_...
     * @param  int                          $cmType         Una de las constantes ChatMedium::TYPE_...
     * @param  mixed                        $content        contenido del resource a ser guardado, según su tipo
     * @return InteractionResource|null                     null en caso de no haber podido generar la entrada en BD; el resource en caso de éxito
     */
    static public function createFromContent ($type, $cmType, $content)
    {
        $r = new InteractionResource($type);
        $r->cmType   = $cmType;
        $r->metainfo = $content;
        $res = $r->save(null, false);
        if ($res === null) {
            Log::register(Log::TYPE_RUNTIME, "R152 No se puede guardar instancia");
            return null;
        }
        return $r;
    }



    /**
     * Factory method: crea un resource a partir de un file_id que sea recibido como parte de un raw update
     *
     * @param  int                          $type           Una de las constantes TYPE_...
     * @param  int                          $cmType         Una de las constantes ChatMedium::TYPE_...
     * @param  mixed                        $cmAuthInfo     Información necesaria para autenticar ante el servidor la petición de download del resource
     * @param  string                       $fileId         file_id
     * @param  array|null                   $attribs        Arreglo con atributos del archivo que representa al recurso, según la estructura de Telegram
     * @param  bool                         $doDownload     Indica si el contenido del resource deberá ser descargado; las descargas de video se transformarán a audio
     * @return InteractionResource|null                     null en caso de no haber podido generar la entrada en BD; el resource en caso de éxito
     */
    static public function createFromFileId ($type, $cmType, $cmAuthInfo, $fileId, $attribs = null, $doDownload = BOTBASIC_DOWNLOAD_MMCONTENT)
    {
        $r = new InteractionResource($type);
        $r->cmType      = $cmType;
        $r->cmcAuthInfo = $cmAuthInfo;
        $r->fileId      = $fileId;
        $r->metainfo    = $attribs;
        $res = $r->save(null, $doDownload);
        if ($res === null) {
            Log::register(Log::TYPE_RUNTIME, "R176 No se puede guardar instancia");
            return null;
        }
        return $r;
    }



    /**
     * Factory method: crea un resource a partir de un archivo en el filesystem del servidor local
     *
     * El contenido del archivo será movido o copiado a una ubicación propia del storage area de BotBasic.
     *
     * @param  int                          $type       Una de las constantes TYPE_...
     * @param  string                       $filename   ruta al archivo fuente
     * @param  int                          $cmType     Una de las constantes ChatMedium::TYPE_...
     * @return InteractionResource|null                 null en caso de no haber podido crear la entrada en BD o no haber podido copiar/mover el archivo;
     *                                                  la instancia del resource en caso de éxito
     */
    static public function createFromFile ($type, $filename, $cmType)
    {
        if (! file_exists($filename)) { return null; }
        $dbFilename = DBbroker::storeFile($filename);
        if ($dbFilename === null) {
            Log::register(Log::TYPE_DATABASE, "R199 Error de BD");
            return null;
        }
        $r = new InteractionResource($type);
        $r->filename = $dbFilename;
        $r->cmType   = $cmType;
        $res = $r->save(null, false);
        if ($res === null) {
            Log::register(Log::TYPE_RUNTIME, "R205 No se puede guardar instancia");
            return null;
        }
        return $r;
    }



    /**
     * Factory method: crea un recurso por medio de clonación a partir de otro
     *
     * La clonación permite evitar la descarga o replicación en filesystem de archivos con el mismo contenido.
     *
     * @param  int|null                 $newCmType  Si es distinto de null se asignará este cmType al nuevo Resource
     * @return InteractionResource|null             null en caso de no haber podido generar la entrada en BD; el resource en caso de éxito
     */
    public function createByCloning ($newCmType = null)
    {
        $clone     = clone $this;
        $clone->id = null;
        if ($newCmType !== null && $clone->cmType != $newCmType) {
            $clone->cmType = $newCmType;
            $clone->fileId = null;
            //TODO esta logica tambien se debe aplicar para cuando el fileId pertenece a otro bot de Telegram, ya que en Telegram los file_id no son compartibles entre bots
        }
        $clone->clonedFrom = $this;
        $res = $clone->save(null, false);
        if ($res === null) {
            Log::register(Log::TYPE_RUNTIME, "R227 No se puede guardar instancia");
            return null;
        }
        return $clone;
    }



    /**
     * Carga un InteractionResource desde la BD
     *
     * @param  int                          $id     ID del resource
     * @return InteractionResource|null             null en caso de error de BD o si no se ha ubicado el resource; el resource en caso de éxito
     */
    static public function load ($id)
    {
        if (! is_integer($id)) { return null; }
        $data = DBbroker::readResource($id);
        if ($data === null)  {
            Log::register(Log::TYPE_DATABASE, "R245 Error de BD");
            return null;
        }
        elseif ($data === false) {
            Log::register(Log::TYPE_RUNTIME, "R249 ID $id no se encuentra");
            return null;
        }
        list ($type, $cmType, $cmAuthInfo, $fileId, $filename, $metainfo, $downloadState) = $data;
        $r = new InteractionResource($type);
        $r->id            = $id;
        $r->cmType        = $cmType;
        $r->cmcAuthInfo   = $cmAuthInfo;
        $r->fileId        = $fileId;
        $r->filename      = $filename;
        $r->metainfo      = $metainfo;
        $r->downloadState = $downloadState;
        return $r;
    }



    /**
     * Guarda un resource en BD, especificando opcionalmente si se deberá descargar el contenido multimedia asociado (positivo por defecto)
     *
     * @param  Interaction  $interaction    Interaction al cual está asociado el resource, o null para no almacenar la relación en BD
     * @param  bool         $doDownload     Indica si se descargará el contenido multimedia asociado
     * @return bool|null                    null en caso de error de BD; true en caso de éxito
     */
    public function save ($interaction, $doDownload = BOTBASIC_DOWNLOAD_MMCONTENT)
    {
        $interactionId = $interaction instanceof Interaction ? $interaction->getId() : null;
        $res = DBbroker::writeResource($this, $interactionId, $doDownload);
        if ($res === null) {
            Log::register(Log::TYPE_DATABASE, "R278 Error de BD");
            return null;
        }
        elseif ($res === false) {
            Log::register(Log::TYPE_RUNTIME, "R282 ID no se encuentra");
            return null;
        }
        elseif ($res === true) {}   // update done; else insert done
        else                   { $this->id = $res; }
        return true;
    }



    /**
     * Retorna una representación compacta del resource en forma de string
     *
     * @return string
     */
    public function serializeBrief ()
    {
        $f = function ($arg) { return $arg === null ? 'NULL' : $arg; };
        $res = '[ID=' . $f($this->id) . '|TYPE=' . InteractionResource::typeString($this->type) . '|CMTYPE=' . $f($this->cmType) .
               '|FILEID=' . $f($this->fileId) . '|DOWNLOAD_STATE=' . $this->downloadState . ']';
        return $res;
    }



}
