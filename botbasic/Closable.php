<?php
/**
 * Interfaz que aplica a BotBasicRuntime, BotBasicChannel y ChatMediumChannel, para el proceso de cierre y guardado de estado de cada uno
 *
 * @author      Gorka G LLona                               <gorka@gmail.com> <gorka@venicua.com>
 * @license     http://www.venicua.com/botbasic/license     Licencia de BotBasic
 * @see         http://www.venicua.com/botbasic             Referencia de BotBasic
 * @version     1.0 - 01.jan.2017
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Interfaz Closable
 *
 * Contiene los métodos que deben ser implementados por los objetos que se guarden en BD condicionalmente según cambios en su estado.
 *
 * @package botbasic
 */
interface Closable
{



    /**
     * Mecanismo de housekeeping del status de las instancias de clases que implementen esta interfaz (almacenamiento en BD, ...)
     *
     * @param  bool $cascade Indica si se debe invocar close() para los objetos agregados/compuestos por esta instancia
     *                       que implementen esta misma interfaz
     */
    function close ($cascade);



    /**
     * Invoca a close() para todas las instancias creadas de objetos que implementen esta interfaz
     */
    static function closeAll ();



    /**
     * Define o consulta el estado de modificación de una instancia, a fin de facilitar la implementación de close()
     *
     * @param  null|bool    $state      null para consultar, true para definir activación del guardado de la instancia, false para su desactivación
     * @return bool                     Estado del tainting
     */
    function tainting ($state = null);   // no arg --> return state; with bool arg --> set state



}
