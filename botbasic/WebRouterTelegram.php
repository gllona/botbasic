<?php
/**
 * Enrutador de peticiones provenientes de la chatapp Telegram
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     2.0 - 23.jun.2018
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
