<?php
/**
 * Enrutador de peticiones provenientes de un formulario web; permite probar BotBasic sin conectarse con un chatapp
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
 * Adapta una interacción proveniente de un formulario web a una interacción de tipo web service, a efectos de simulación.
 *
 * @package botbasic
 */
class WebRouterWebStub extends WebRouter
{



    public function __construct ()
    {
        parent::__construct($_SERVER['REQUEST_URI']);
    }



    protected function getThisChatMediumType()
    {
        return ChatMedium::TYPE_WEBSTUB;
    }



    protected function getRawUpdate ()
    {
        return $_POST;
    }



}
