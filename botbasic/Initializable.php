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
 * Interfaz Initializable
 *
 * Contiene los métodos que deben ser implementados por los objetos asociados a BD que deban ser inicializados con un ID real una tabla de la BD.
 *
 * ESTA INTERFAZ ESTÁ IMPLEMENTADA EN TRES CLASES PERO NO SE USA SU FUNCIONALIDAD, ES DECIR, CREAR IDs A MOMENTO DE CREAR INSTANCIAS.
 *
 * @package botbasic
 */
interface Initializable
{



    /**
     * Retorna un arreglo asociativo con los valores por defecto que deben ser insertados en un registro de una tabla asociada en la BD
     *
     * Los atributos se deben retornar en el orden especificado en la definición del esquema de la tabla y no deben contener a los campos:
     * id, updated, deleted.
     *
     * @return array
     */
    public function getDefauls ();



    /**
     * Retorna el ID de un objeto, si está asignado previamente con DBbroker::makeId(), o null si no lo está
     *
     * @return int|null
     */
    public function getId ();



}
