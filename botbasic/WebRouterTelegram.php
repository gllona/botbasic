<?php
/**
 * Enrutador de peticiones provenientes de la chatapp Telegram
 *
 * @author      Gorka G LLona                               <gorka@gmail.com> <gorka@venicua.com>
 * @license     http://www.venicua.com/botbasic/license     Licencia de BotBasic
 * @see         http://www.venicua.com/botbasic             Referencia de BotBasic
 * @version     1.0 - 01.jan.2017
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Clase WebRouterCliStub
 *
 * Genera un objeto update "crudo" que después será "undressed" y estandarizado por el método run() de la superclase antes de transferirse
 * al método run() de la respectiva subclase de ChatMedium.
 *
 * @package botbasic
 */
class WebRouterTelegram extends WebRouter
{



    public function __construct ()
    {
        parent::__construct($_SERVER['REQUEST_URI']);
    }



    protected function getThisChatMediumType()
    {
        return ChatMedium::TYPE_TELEGRAM;
    }



    protected function getRawUpdate ()
    {
        $postContent = $this->getWebContent();
        $tgramUpdate = json_decode($postContent);
        return $tgramUpdate;
    }



}
