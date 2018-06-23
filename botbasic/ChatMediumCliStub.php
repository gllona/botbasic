<?php
/**
 * Medio de chat basado en el de Telegram donde la entrada viene de stdin y la salida va a stdout. Se ejecuta desde la línea de comandos
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     2.0 - 23.jun.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase ChatMediumCliStub
 *
 * Subclase de ChatMedium que implementa la comunicación con la herramienta CLI de simulación de interacciones.
 *
 * @package botbasic
 */
class ChatMediumCliStub extends ChatMedium
{



    /** @var string Archvo de salida para los updates generados como respuesta a los usos del CLI tool */
    static private $outFile = "/dev/stdout";



    /** @var ChatMediumTelegram Instancia delegada de ChatMediumTelegram a través de la cual se gestionan los métodos abstractos de la superclase
     *                          y otros métodos de menor importancia para las particularidades de este stub */
    private $cmt = null;



    static protected function cmBots ($cmAndBbBotsIdx = null)
    {
        $cmBots = BotConfig::cmBots(self::TYPE_CLISTUB);
        if ($cmAndBbBotsIdx === null) {
            return $cmBots;
        }
        foreach ($cmBots as $idx => $data) {
            if ($idx == $cmAndBbBotsIdx) { return [ $idx => $data ]; }
        }
        return [];
    }



    /**
     * Constructor
     */
    public function __construct ()
    {
        $this->cmt = new ChatMediumTelegram();
    }



    public function getCMbotCredentialsByScriptName ($scriptName, $aCmBots = null)
    {
        $cmBots = BotConfig::cmBots(self::TYPE_CLISTUB);
        return $this->cmt->getCMbotCredentialsByScriptName($scriptName, $cmBots);
    }



    protected function getCMbotCredentialsByBBinfo ($bbCodename, $bbMajorVersionNumber, $bbBotName, $aCmBots = null)
    {
        $cmBots = BotConfig::cmBots(self::TYPE_CLISTUB);
        return $this->cmt->getCMbotCredentialsByBBinfo($bbCodename, $bbMajorVersionNumber, $bbBotName, $cmBots);
    }



    static public function getCMbotSpecialIndex ($cmBotName)
    {
        $cmBots = BotConfig::cmBots(self::TYPE_CLISTUB);
        foreach ($cmBots as $j => $credentials) {
            for ($i = 0; $i < count($credentials); $i++) { if ($credentials[$i][0] == $cmBotName) { return [ $j, $i ]; } }
        }
        return null;
    }



    static public function getCMbotNameBySpecialIndex ($idx)
    {
        $cmBots = BotConfig::cmBots(self::TYPE_CLISTUB);
        return $cmBots[$idx[0]][$idx[1]][0];
    }



    public function getAuthInfoForDownloadsByScriptName ($scriptName, $aCmBots = null)
    {
        $cmBots = BotConfig::cmBots(self::TYPE_CLISTUB);
        return $this->cmt->getAuthInfoForDownloadsByScriptName($scriptName, $cmBots);
    }



    public function undressUpdate ($dressedUpdate, $botName, $cmAuthInfo, $textToPut = null, $userIdToPut = null)
    {
        $update    = json_decode(json_encode($dressedUpdate));
        $seqId     = $update->id;
        $chatId    = $update->chatId;
        $userid    = $update->userId;
        $fullname  = null;
        $login     = null;
        $language  = null;
        $userphone = null;
        $menuhook  = $update->menuhook == '' ? null : $update->menuhook;
        $text      = $menuhook === null ? $update->text : null;
        $u         = Update::createByAttribs(ChatMedium::TYPE_CLISTUB, $botName, $seqId, $chatId, $userid, $fullname, $login, $language, $userphone, $text, $menuhook);
        return $u;
    }



    public function getDownloadUrl ($cmAuthInfo, $fileId)
    {
        return false;
    }



    public function dressForDisplay ($text, $menuOptions, $resource, $cmChannelOrCmChatInfo)
    {
        $res = [ $text, $menuOptions, $cmChannelOrCmChatInfo ];   // $resource is not used
        return $res;
    }



    public function display ($infoToPost, $forceAsync = true)
    {
        $this->doDummy($forceAsync);
        list ($text, $menuOptions, $cmcOrChatInfo) = $infoToPost;   /** @var $cmcOrChatInfo ChatMediumChannel|array */
        $retval = true;
        // if $cmcOrChatInfo is not a cmChannel, this display is an error message when entering and display will be done synchronously
        if (! $cmcOrChatInfo instanceof ChatMediumChannel) {
            list ($cmBotName, $cmUserId, $cmChatId) = $cmcOrChatInfo;
            $request = $this->makeContentForPost($text, $menuOptions, $cmUserId, $cmChatId, $cmBotName);
        }
        // in normal cases the display will be done synchronously (output to standard output)
        else {
            $cmUserId  = $cmcOrChatInfo->getCMuserId();
            $cmChatId  = $cmcOrChatInfo->getCMchatInfo();
            $cmBotName = $cmcOrChatInfo->getCMbotName();
            $request   = $this->makeContentForPost($text, $menuOptions, $cmUserId, $cmChatId, $cmBotName);
        }
        // write output to standard output
        $res = $this->postToFile($request, self::$outFile);
        if (! $res) {
            Log::register(Log::TYPE_GENERIC, "CMCS164 Falla postToFile");
            $retval = false;
        }
        // ready
        return $retval;
    }



    /**
     * Construye un string con la información de los argumentos, a ser enviada en forma compacta hacia la salida del stub
     *
     * @param  string       $text               Texto del Splash, o null si no la hay
     * @param  array        $menuOptions        Arreglo de opciones de menú que serán renderizadas en forma de un custom keyboard
     * @param  string       $cmUserId           Equivalente al user ID de la chatapp
     * @param  string       $cmChatId           Equivalente al chat ID de la chatapp
     * @param  string       $cmBotName          Equivalente al nombre del bot de la chatapp; usado en ChatMediumXXX::$cmBots
     * @return string                           Representación compacta
     */
    private function makeContentForPost ($text, $menuOptions, $cmUserId, $cmChatId, $cmBotName)
    {
        $msg  = "USER=" . ($cmUserId  === null ? "NULL" : $cmUserId ) . "\n";
        $msg .= "CHAT=" . ($cmChatId  === null ? "NULL" : $cmChatId ) . "\n";
        $msg .= "BOTN=" . ($cmBotName === null ? "NULL" : $cmBotName) . "\n";
        $msg .= "[" . ($text === null ? '' : $text) . "]";
        if ($menuOptions !== null) {
            $buttons = [];
            foreach ($menuOptions as $option) {
                list ($text, $menuhook) = $option;   // from Interaction::encodeMenuhook()
                $buttons[] = "[$text/$menuhook]";
            }
            $msg .= "\nMENU=" . join('', $buttons);
        }
        return $msg;
    }



    /**
     * Agrega un string al archivo de salida del stub
     *
     * @param  string   $request    String a agregar, como salida de makeContentForPost
     * @param  string   $filename   Nombre del archivo de salida del stub
     * @return bool                 Indicador de éxito de la operación
     */
    private function postToFile ($request, $filename)
    {
        if ($filename == '/dev/stdout') {
            echo "$request\n";
            return true;
        }
        $fh = fopen($filename, "a");
        if ($fh === false) {
            Log::register(Log::TYPE_GENERIC, "CMCS214 en postToFile ($request, $filename)");
            return false;
        }
        $res = fwrite($fh, "$request\n");
        if ($res === false) {
            Log::register(Log::TYPE_GENERIC, "CMCS219 en postToFile ($request, $filename)");
            return false;
        }
        fclose($fh);
        return true;
    }



}
