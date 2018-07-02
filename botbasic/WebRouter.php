<?php
/**
 * Enrutador de la entrada del web service implementado para recibir los updates desde las chatapps hacia el resto del runtime de BotBasic
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase WebRouter
 *
 * Superclase que desde su método run() transfiere al método run() de una subclase de ChatMedium el control del procesamiento del Update
 * recibido desde una chatapp.
 *
 * @package botbasic
 */
abstract class WebRouter
{



    /** @var string Nombre completo del script PHP por el cual se crea el WebRouter, el cual permite identificar el ChatMedium y el bot de BotBasic */
    private $scriptName = null;



    /**
     * Constructor
     *
     * @param string $scriptName Nombre (ruta completa) del script invocado como web service que presta servicios al servidor de la chatapp,
     *                           a partir del cual se determinan las particularidades y comportamiento del WebRouter
     */
    public function __construct ($scriptName)
    {
        $this->scriptName = $scriptName;
    }



    /**
     * Retorna la constante TYPE_... apropiada para esta instancia de WebRouter
     *
     * @return int
     */
    abstract protected function getThisChatMediumType();



    /**
     * Rutina común a todas las variedades de WebRouter que implementa la lógica de procesamiento de las peticiones provenientes de las chatapps;
     * deriva al método run() de la subclase respectiva de ChatMedium
     *
     * @return bool|null    true en caso de haber podido transferir la ejecución a ChatMedium; null si no se puede determinar la variedad de
     *                      ChatMedium a instanciar o si no se consigue en ella la información de autenticación necesaria para generalizar el update
     */
    final public function run ()
    {
        Log::profilerStart(0, 'MAIN_FLOW', 'entering WebRouter::run()');
        $update            = $this->getRawUpdate();
        $cm                = ChatMedium::create($this->getThisChatMediumType());
        $authInfo          = $cm->getAuthInfoForDownloadsByScriptName($this->scriptName);
        if ($authInfo      === null) { return null; }
        $credentials       = $cm->getCMbotCredentialsByScriptName($this->scriptName);
        if ($credentials   === null) { return null; }
        $botName           = $credentials[1];
        $genericUpdate     = $cm->undressUpdate($update, $botName, $authInfo);
        if ($genericUpdate === null) {
            Log::register(Log::TYPE_RUNTIME, "WR75 No se pudo desvestir el update con ($botName, $authInfo)");
        }
        elseif ($genericUpdate === -1) {
            // do nothing: this is an exit condition for a POSSESSED runtime
        }

        // TODO falla porque no toma en cuenta que Telegram asigna secuencias distintas a cada usuario
        //elseif (! $genericUpdate->hasValidSequence()) {
        //    T3log::register(T3log::TYPE_RUNTIME, "WR81 El update generico no tiene secuencia valida", $genericUpdate);
        //    $genericUpdate->delete();
        //}

        else {
            $cm->run($this->scriptName, $genericUpdate);
        }
        Log::profilerStop(0, 'terminating WebRouter::run()');
        return true;
    }



    /**
     * Método implementado por cada subclase para obtener un raw update (por ejemplo, a través del cuerpo de un POST que arriba al web service)

     * @return mixed
     */
    abstract protected function getRawUpdate ();



    /**
     * Utilidad para las subclases que permite obtener el contenido web (raw) que llega en una petición HTTP
     *
     * @return string   raw content
     */
    protected function getWebContent ()
    {
        $maxReadSize = BOTBASIC_MAX_HTTP_REQUEST_SIZE_MB * 1024*1024;
        $is = $this->inputStream();
        $content = fread($is, $maxReadSize);
        return $content;
    }



    /**
     * Retorna un input stream abierto para lectura que utiliza memoria secundaria para garantizar mínimo consumo de memoria RAM
     * cuando se accede a contenidos voluminosos
     *
     * @return resource
     */
    private function inputStream ()
    {
        // http://stackoverflow.com/questions/8945879/how-to-get-body-of-a-post-in-php
        // php://temp allows you to manage memory consumption because it will transparently switch to filesystem storage after a certain amount of data is stored (2M by default). This size can be manipulated in the php.ini file or by appending /maxmemory:NN, where NN is the maximum amount of data to keep in memory before using a temporary file, in bytes.
        $rawInput  = fopen('php://input', 'r');
        $tmpStream = fopen('php://temp', 'r+');
        stream_copy_to_stream($rawInput, $tmpStream);
        rewind($tmpStream);
        return $tmpStream;
    }



}
