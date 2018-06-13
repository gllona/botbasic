<?php
/**
 * Medio de chat basado en el de Telegram donde la entrada viene de un formulario web y la salida va a un archivo en el servidor
 *
 * @author      Gorka G LLona                               <gorka@gmail.com> <gorka@venicua.com>
 * @license     http://www.venicua.com/botbasic/license     Licencia de BotBasic
 * @see         http://www.venicua.com/botbasic             Referencia de BotBasic
 * @version     1.0 - 01.jan.2017
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase ChatMediumWebStub
 *
 * Subclase de ChatMedium que implementa la comunicación con la herramienta web de simulación de interacciones.
 *
 * @package botbasic
 */
class ChatMediumWebStub extends ChatMedium
{



    /** @var ChatMediumTelegram Instancia delegada de ChatMediumTelegram a través de la cual se gestionan los métodos abstractos de la superclase
     *                          y otros métodos de menor importancia para las particularidades de este stub */
    private $cmt = null;



    static protected function cmBots ($cmAndBbBotsIdx = null)
    {
        $cmBots = BotConfig::cmBots(self::TYPE_WEBSTUB);
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
        $cmBots = BotConfig::cmBots(self::TYPE_WEBSTUB);
        return $this->cmt->getCMbotCredentialsByScriptName($scriptName, $cmBots);
    }



    protected function getCMbotCredentialsByBBinfo ($bbCodename, $bbMajorVersionNumber, $bbBotName, $aCmBots = null)
    {
        $cmBots = BotConfig::cmBots(self::TYPE_WEBSTUB);
        return $this->cmt->getCMbotCredentialsByBBinfo($bbCodename, $bbMajorVersionNumber, $bbBotName, $cmBots);
    }



    static public function getCMbotSpecialIndex ($cmBotName)
    {
        $cmBots = BotConfig::cmBots(self::TYPE_WEBSTUB);
        foreach ($cmBots as $j => $credentials) {
            for ($i = 0; $i < count($credentials); $i++) { if ($credentials[$i][0] == $cmBotName) { return [ $j, $i ]; } }
        }
        return null;
    }



    static public function getCMbotNameBySpecialIndex ($idx)
    {
        $cmBots = BotConfig::cmBots(self::TYPE_WEBSTUB);
        return $cmBots[$idx[0]][$idx[1]][0];
    }



    public function getAuthInfoForDownloadsByScriptName ($scriptName, $aCmBots = null)
    {
        $cmBots = BotConfig::cmBots(self::TYPE_WEBSTUB);
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
        $u         = Update::createByAttribs(ChatMedium::TYPE_WEBSTUB, $botName, $seqId, $chatId, $userid, $fullname, $userphone, $text, $menuhook);
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
        // in normal cases the display will be done synchronously (output to file)
        else {
            $cmUserId  = $cmcOrChatInfo->getCMuserId();
            $cmChatId  = $cmcOrChatInfo->getCMchatInfo();
            $cmBotName = $cmcOrChatInfo->getCMbotName();
            $request   = $this->makeContentForPost($text, $menuOptions, $cmUserId, $cmChatId, $cmBotName);
        }
        // write output to a local file
        $res = $this->postToFile($request, $cmBotName . "__" . $cmUserId);
        if (! $res) {
            Log::register(Log::TYPE_GENERIC, "CMWS167 Falla postToFile");
            $retval = false;
        }
        // write output to a common, all-bots output file
        if (BOTBASIC_WEBSTUB_OUTPUT_COMMON_FILE !== null) {
            $res = $this->postToFile($request, BOTBASIC_WEBSTUB_OUTPUT_COMMON_FILE);
            if (! $res) {
                Log::register(Log::TYPE_GENERIC, "CMWS174 Falla postToFile");
                $retval = false;
            }
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
        $msg  = "[";
        $msg .= "USER=" . ($cmUserId  === null ? "NULL" : $cmUserId ) . "|";
        $msg .= "CHAT=" . ($cmChatId  === null ? "NULL" : $cmChatId ) . "|";
        $msg .= "BOTN=" . ($cmBotName === null ? "NULL" : $cmBotName);
        $msg .= "] [" . ($text === null ? '' : $text) . "]";
        if ($menuOptions !== null) {
            $buttons = [];
            foreach ($menuOptions as $option) {
                list ($text, $menuhook) = $option;
                $buttons[] = "[$text/$menuhook]";
            }
            $msg .= " [MENU=" . join('', $buttons) . "]";
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
        $fn = BOTBASIC_WEBSTUB_OUTPUT_DIR . '/' . $filename;
        $fh = fopen($fn, "a");
        if ($fh === false) {
            Log::register(Log::TYPE_GENERIC, "CMWS226 en postToFile ($request, $filename)");
            return false;
        }
        $res = fwrite($fh, "$request\n");
        if ($res === false) {
            Log::register(Log::TYPE_GENERIC, "CMWS231 en postToFile ($request, $filename)");
            return false;
        }
        fclose($fh);
        return true;
    }



}
