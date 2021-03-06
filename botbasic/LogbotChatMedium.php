<?php
/**
 * Interfaz que aplica a ChatMedia en las cuales hay usuarios que reciben réplicas de algunos Log::register()
 *
 * Actualmente se requiere que esta interfaz esté implementada en sólo una chatapp, definida por BOTBASIC_LOGBOT_CHATAPP
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;



/**
 * Interfaz LogbotChatMedium
 *
 * Contiene los métodos que deben ser implementados por las subclases y objetos de ChatMedium que requieran recibir réplicas de Log::register()
 *
 * @package botbasic
 */
interface LogbotChatMedium
{



    /**
     * Indica si un usuario de una chatapp está identificado como usuario sobre el cual se replican ciertos Log::register()
     *
     * @param  int      $bbBotIdx           Indice en ChatMedium::$bbBots del bot de la BotBasic app correspondiente
     * @param  string   $cmFullUserName     Nombre completo del usuario: concatenación de nombre, espacio y apellido, según lo reporta la chatapp
     * @return bool                         Indicador de usuario de logging de BotBasic apps
     */
    static public function cmUserIsLogbotUser ($bbBotIdx, $cmFullUserName);



}
