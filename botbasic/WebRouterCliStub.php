<?php
/**
 * Enrutador de peticiones provenientes de la línea de comandos; permite probar BotBasic sin conectarse con un chatapp
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
 * Adapta una interacción proveniente de línea de comandos a una interacción tipo web service, a efectos de simulación.
 *
 * @package botbasic
 */
class WebRouterCliStub extends WebRouter
{



    public function __construct ($cmBotDigitsCode)
    {
        parent::__construct($cmBotDigitsCode);
    }



    protected function getThisChatMediumType()
    {
        return ChatMedium::TYPE_CLISTUB;
    }



    protected function getRawUpdate ()
    {
        global $_post;
        return $_post;
    }



}
