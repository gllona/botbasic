<?php
/**
 * Enrutador de peticiones provenientes de un formulario web; permite probar BotBasic sin conectarse con un chatapp
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
