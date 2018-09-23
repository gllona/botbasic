<?php
/**
 * Componente de conexión entre BotBasic y el modelo de negocio, desde el punto de vista del último
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     0.2 - 01.jul.2018
 * @since       0.1 - 01.jul.2016
 */



namespace botbasic;

use PHPMailer,
    Messente;

use T3\Aclamacion,
    T3\Acopio,
    T3\Actor,
    T3\Camion,
    T3\Canje,
    T3\Colector,
    T3\Donacion,
    T3\DonacionDinero,
    T3\DonacionPremio,
    T3\Entrega,
    T3\EntregaColectorAcopio,
    T3\EntregaRecicladorColector,
    T3\Fundacion,
    T3\Geosector,
    T3\Invitacion,
    T3\Lote,
    T3\LoteThreshold,
    T3\Material,
    T3\Notificacion,
    T3\Operador,
    T3\Pago,
    T3\Premiable,
    T3\Premio,
    T3\Procesador,
    T3\Reciclador,
    T3\Ruta,
    T3\SponsorCorp,
    T3\SponsorRetail,
    T3\Subsidiario,
    T3\Transaccion,
    T3\Vector;
use T3\Persistable,
    T3\Log;

use nima\All;



/**
 * Clase BizModelAdapter
 *
 * Provee el placeholder para las rutinas de PHP que implementan variables mágicas, menús predefinidos y primitivas de BotBasic.
 *
 * Las funciones de la implementación deben seguir las convenciones de nombres:
 *
 * * Variables mágicas: mv_nombrevariablemagica_set() y mv_nombrevariablemagica_get()
 * * Primitivas: pr_nombreprimitiva()
 * * Menús predefinidos: mn_nombremenupredefinido()
 *
 * Ver los ejemplos incluidos para explicacion de los parametros y valores de retorno.
 *
 * Así mismo, el modelo de negocios debe implementar métodos abstractos heredados de la superclase que son requeridos por el ambiente de
 * ejecución de BotBasic. Se proveen ejemplos.
 *
 * Esta clase es instanciada por el runtime de BotBasic y cada instancia está asociada a un canal de BotBasic. Si bien esta es una clase
 * abierta (única en BotBasic) abierta a su modificación por parte del desarrollador del modelo de negocio, tanto el parser como el runtime
 * verifican que la clase herede de la superclase apropiada y contenga los métodos que son requeridos.
 *
 * El desarrollador de aplicaciones BotBasic no debe instanciar esta clase, sino colocar en ella los métodos requeridos por el runtime
 * de acuerdo a la especificación del programa elaborado en BotBasic.
 *
 * @package botbasic
 *
 * @method  string getCommonVar ($name)
 */
class BizModelAdapter extends BizModelAdapterTemplate
{



    //////////////////////////////
    // BOTBASIC-PROVIDED UTILITIES
    //////////////////////////////



    /*
    // UTILIDADES HEREDADAS DE LA SUPERCLASE, DISPONIBLES PARA EL DESARROLLADOR

    protected function getBizModelUserId ()       { return parent::getBizModelUserId();       }
    protected function setBizModelUserId ($value) { return parent::setBizModelUserId($value); }

    // pass true in $bbChannelId to get/save the value associated to the current channel; pass false for global (all bbchannels) context; or pass an specific bbc id
    protected function get ($name,         $bbChannelId = false) { return parent::get($name, $bbChannelId); }
    protected function set ($name, $value, $bbChannelId = false) { return parent::set($name, $bbChannelId); }
    protected function getCommonVar ($name)                      { return parent::getCommonVar($name);      }
    protected function loadFromDB ($key)                         { return parent::loadFromDB($key);         }
    protected function saveToDB ($key, $value)                   { return parent::saveToDB($key, $value);   }

    protected function updatedChatMediaChannelBotName ($anOldCMbotName) { return parent::updatedChatMediaChannelBotName($anOldCMbotName); }
    protected function setCMchannelBotNameMagicVar ($name, $value)      { return parent::setCMchannelBotNameMagicVar($name, $value);      }
    protected function getCMchannelBotNameMagicVar ($name)              { return parent::getCMchannelBotNameMagicVar($name);              }

    const CHANNELS_POLICY_ROUNDROBIN = parent::CHANNELS_POLICY_ROUNDROBIN;
    const CHANNELS_POLICY_LEASTUSED  = parent::CHANNELS_POLICY_LEASTUSED;
    const CHANNELS_POLICY_MOSTUSED   = parent::CHANNELS_POLICY_MOSTUSED;
    protected function computeNextCMchannelBotName ($regExpPattern, $policy, $baseChatMediaType, $baseChannelName = null) { return parent::computeNextCMchannelBotName($regExpPattern, $policy, $baseChatMediaType, $baseChannelName); }
    protected function getCMchannelBotNames ($regExpPattern, $baseChatMediaType)                                          { return parent::getCMchannelBotNames($regExpPattern, $baseChatMediaType);                                   }

    protected function bbPrint ($text,                                                                                     $botName = null, $bmUserId = null, $bbChannelId = null) { return parent::bbPrint($text,                                                                                     $botName, $bmUserId, $bbChannelId); }
    protected function bbMenu  ($predefMenuName, $predefMenuArgs, $titles, $options, $pager, $toVars, $srcLineno, $srcBot, $botName = null, $bmUserId = null, $bbChannelId = null) { return parent::bbMenu ($predefMenuName, $predefMenuArgs, $titles, $options, $pager, $toVars, $srcLineno, $srcBot, $botName, $bmUserId, $bbChannelId); }
    protected function bbInput ($dataType, $titles, $word, $toVar, $fromVar, $srcLineno, $srcBot,                          $botName = null, $bmUserId = null, $bbChannelId = null) { return parent::bbInput($dataType, $titles, $word, $toVar, $fromVar, $srcLineno, $srcBot,                          $botName, $bmUserId, $bbChannelId); }
    */

    private function doDummy ($arg) {}



    //////////////////////////////
    // SUPERCLASS ABSTRACT METHODS
    //////////////////////////////



    public function terminate ()
    {
        Persistable::persist();
        return true;   // FIXME mejorar retornando false si falla la persistencia
    }



    ////////////////////
    // CHANNELS HANDLERS
    ////////////////////



    /**
     * Genera un nuevo nombre de bot de chatapp a ser utilizado por el programa BotBasic para su asignación a una variable por medio de CHANNEL new
     *
     * El desarrollador de BizModelAdapter debe implementar este método en esta clase; para ello puede ayudarse con las utilidades provistas por la superclase.
     *
     * ESTE ES UN EJEMPLO BASE PARA LA IMPLEMENTACION DE NEUROPOWER
     *
     * @param  string       $bbBotName      Nombre del bot de BotBasic para el cual se debe generar un nombre de bot de chatapp
     * @return string|null                  Nombre del bot de la chatapp que se va a utilizar por la directiva CHANNEL new
     */
    public function makeAcmBotName ($bbBotName)
    {
        if ($bbBotName === "bot2") { $chEspecialistasREpattern = '/^\@np[0-9]{2,}/'; }   // Telegram names
        else                       { $chEspecialistasREpattern = '/^NONE$/';         }   // bot1 or bot3
        $newCMbotName = $this->computeNextCMchannelBotName($chEspecialistasREpattern, self::CHANNELS_POLICY_LEASTUSED, ChatMedium::TYPE_TELEGRAM);
        if ($newCMbotName === null) { return null; }
        return $newCMbotName;
    }



    //////////////////////
    // MAGIC VARS HANDLERS
    //////////////////////



    /**
     * Implementa el getter de la variable mágica "uId"; se deben conservar el prefijo y el sufijo en el nombre del método
     *
     * @param  array            $metadata   Arreglo indexado por strings con valores que identifican el código y la instancia del runtime
     * @return string|int|bool              Valor de la variable mágica, según fue asignado; o un string vacío si no lo fue antes;
     *                                      o false si el get() se debe aplicar dentro del runtime de BotBasic
     */
    public function mv_uId_get ($metadata)
    {
        //$codename = $metadata['codename'];
        //$codebot  = $metadata['codebot' ];
        //$codeline = $metadata['codeline'];
        return $metadata['bmuserid'];
    }

    /**
     * Implementa el setter de la variable mágica "uId"; se deben conservar el prefijo y el sufijo en el nombre del método
     *
     * @param  string       $value      Valor de la variable mágica, a asignarle
     * @param  array        $metadata   Arreglo indexado por strings con valores que identifican el código y la instancia del runtime
     * @return bool|array               false si el set() se debe aplicar dentro del runtime de BotBasic sin cambiar el valor;
     *                                  [ stringOentero ] para indicar a BotBasic que aplique el set() con el valor indicado en [0] del arreglo retornado;
     *                                  true de otro modo (no se hará set() o equivalente en el core de BotBasic)
     */
    public function mv_uId_set ($value, $metadata)
    {
        $this->doDummy([ $value, $metadata ]);
        return true;   // do nothing; read-only magic var
    }

    public function getterForActorMagicVar ($property, $metadata)
    {
        if (! ($uId = $metadata['bmuserid'])) { return null; }
        if (! ($actor = Actor::DBread($uId))) { return null; }   /** @var Actor $actor */
        return $actor->$property();
    }

    public function setterForActorMagicVar ($property, $value, $metadata)
    {
        if (! ($uId = $metadata['bmuserid'])) { return true; }
        if (! ($actor = Actor::DBread($uId))) { return true; }   /** @var Actor $actor */
        $actor->$property($value);
        return true;
    }

    public function mv_uTipo_get ($metadata)
    {
        return $this->getterForActorMagicVar('tipo', $metadata);
    }

    public function mv_uTipo_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('tipo', $value, $metadata);
    }

    public function mv_uProfileFull_get ($metadata)
    {
        return $this->getterForActorMagicVar('profileFull', $metadata);
    }

    public function mv_uProfileFull_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('profileFull', $value, $metadata);
    }

    public function mv_uNombre_get ($metadata)
    {
        return $this->getterForActorMagicVar('nombre', $metadata);
    }

    public function mv_uNombre_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('nombre', $value, $metadata);
    }

    public function mv_uApellido_get ($metadata)
    {
        return $this->getterForActorMagicVar('apellido', $metadata);
    }

    public function mv_uApellido_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('apellido', $value, $metadata);
    }

    public function mv_uNombreCompleto_get ($metadata)
    {
        if (! ($tipoPersona = $this->getterForActorMagicVar('tipoPersona', $metadata))) { return null; }
        if ($tipoPersona == 'natural') {
            if (! ($nombre = $this->getterForActorMagicVar('nombre', $metadata))) { return null; }
            return $nombre . ' ' . $this->getterForActorMagicVar('apellido', $metadata);
        }
        else {
            return $this->getterForActorMagicVar('figuraLegal', $metadata);
        }
    }

    public function mv_uNombreCompleto_set ($value, $metadata)
    {
        $this->doDummy([ $value, $metadata ]);
        return true;   // do nothing; read-only magic var
    }

    public function mv_uSexo_get ($metadata)
    {
        return $this->getterForActorMagicVar('sexo', $metadata);
    }

    public function mv_uSexo_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('sexo', $value, $metadata);
    }

    public function mv_uFiguraLegal_get ($metadata)
    {
        return $this->getterForActorMagicVar('figuraLegal', $metadata);
    }

    public function mv_uFiguraLegal_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('figuraLegal', $value, $metadata);
    }

    public function mv_uTelefonoFijo_get ($metadata)
    {
        return $this->getterForActorMagicVar('telefonoFijo', $metadata);
    }

    public function mv_uTelefonoFijo_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('telefonoFijo', $value, $metadata);
    }

    public function mv_uTelefonoCelular_get ($metadata)
    {
        return $this->getterForActorMagicVar('telefonoCelular', $metadata);
    }

    public function mv_uTelefonoCelular_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('telefonoCelular', $value, $metadata);
    }

    public function mv_uEmail_get ($metadata)
    {
        return $this->getterForActorMagicVar('email', $metadata);
    }

    public function mv_uEmail_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('email', $value, $metadata);
    }

    public function mv_uMarca_get ($metadata)
    {
        return $this->getterForActorMagicVar('marca', $metadata);
    }

    public function mv_uMarca_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('marca', $value, $metadata);
    }

    public function mv_uNIT_get ($metadata)
    {
        return $this->getterForActorMagicVar('nit', $metadata);
    }

    public function mv_uNIT_set ($value, $metadata)
    {
        $value = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $value));
        return $this->setterForActorMagicVar('nit', $value, $metadata);
    }

    public function mv_uNacimiento_get ($metadata)
    {
        return $this->getterForActorMagicVar('nacimiento', $metadata);
    }

    public function mv_uNacimiento_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('nacimiento', $value, $metadata);
    }

    public function mv_uFundacion_get ($metadata)
    {
        return $this->mv_uNacimiento_get($metadata);
    }

    public function mv_uFundacion_set ($value, $metadata)
    {
        return $this->mv_uNacimiento_set($value, $metadata);
    }

    public function mv_uPais_get ($metadata)
    {
        if (! ($uId = $metadata['bmuserid'])) { return null; }
        if (! ($actor = Actor::DBread($uId))) { return null; }   /** @var Actor $actor */
        if (! ($sector = $actor->ubicacion())  || $sector->nivel() != 'sector') { return null; }
        if (! ($ciudad = $sector->adscritoA()) || $ciudad->nivel() != 'ciudad') { return null; }
        if (! ($pais = $ciudad->adscritoA())   || $pais->nivel()   != 'pais')   { return null; }
        return $pais->nombre();
    }

    public function mv_uPais_set ($value, $metadata)
    {
        if (! ($uId = $metadata['bmuserid'])) { return true; }
        if (! ($actor = Actor::DBread($uId))) { return true; }   /** @var Actor $actor */
        if (! ($sector = $actor->ubicacion())  || $sector->nivel() != 'sector') { return true; }
        if (! ($ciudad = $sector->adscritoA()) || $ciudad->nivel() != 'ciudad') { return true; }
        if (! ($pais = $ciudad->adscritoA())   || $pais->nivel()   != 'pais')   { return true; }
        $pais->nombre($value);
        return true;
    }

    public function mv_uCiudad_get ($metadata)
    {
        if (! ($uId = $metadata['bmuserid'])) { return null; }
        if (! ($actor = Actor::DBread($uId))) { return null; }   /** @var Actor $actor */
        if (! ($sector = $actor->ubicacion())  || $sector->nivel() != 'sector') { return null; }
        if (! ($ciudad = $sector->adscritoA()) || $ciudad->nivel() != 'ciudad') { return null; }
        return $ciudad->nombre();
    }

    public function mv_uCiudad_set ($value, $metadata)
    {
        if (! ($uId = $metadata['bmuserid'])) { return true; }
        if (! ($actor = Actor::DBread($uId))) { return true; }   /** @var Actor $actor */
        if (! ($sector = $actor->ubicacion())  || $sector->nivel() != 'sector') { return true; }
        if (! ($ciudad = $sector->adscritoA()) || $ciudad->nivel() != 'ciudad') { return true; }
        $ciudad->nombre($value);
        return true;
    }

    public function mv_uSector_get ($metadata)
    {
        return $this->getterForActorMagicVar('ubicacion', $metadata);
    }

    public function mv_uSector_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('ubicacion', $value, $metadata);
    }

    public function mv_uDireccion_get ($metadata)
    {
        return $this->getterForActorMagicVar('direccion', $metadata);
    }

    public function mv_uDireccion_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('direccion', $value, $metadata);
    }

    public function mv_uDireccionCompleta_get ($metadata)
    {
        if (! ($uId = $metadata['bmuserid'])) { return null; }
        if (! ($actor = Actor::DBread($uId))) { return null; }   /** @var Actor $actor */
        if (! ($sector = $actor->ubicacion())  || $sector->nivel() != 'sector') { return null; }
        if (! ($ciudad = $sector->adscritoA()) || $ciudad->nivel() != 'ciudad') { return null; }
        if (! ($pais = $ciudad->adscritoA())   || $pais->nivel()   != 'pais')   { return null; }
        return $actor->direccion() . ', ' . $sector->nombre() . ', ' . $ciudad->nombre() . ', ' . $pais->nombre();
    }

    public function mv_uDireccionCompleta_set ($value, $metadata)
    {
        $this->doDummy([ $value, $metadata ]);
        return true;   // do nothing; read-only magic var
    }

    public function mv_uOcupacion_get ($metadata)
    {
        return $this->getterForActorMagicVar('ocupacion', $metadata);
    }

    public function mv_uOcupacion_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('ocupacion', $value, $metadata);
    }

    public function mv_uEcopuntos_get ($metadata)
    {
        return ($ep = $this->getterForActorMagicVar('ecopuntos', $metadata)) === null ? 0 : $ep;
    }

    public function mv_uEcopuntos_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('ecopuntos', $value, $metadata);
    }

    public function mv_uAfiliadoAid_get ($metadata)
    {
        if (! ($uId = $metadata['bmuserid'])) { return null; }
        if (! ($actor = Actor::DBread($uId))) { return null; }   /** @var Actor $actor */
        return $actor->afiliadoA(true);
    }

    public function mv_uAfiliadoAid_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('afiliadoA', $value, $metadata);
    }

    public function mv_uCamionFabricante_get ($metadata)
    {
        return $this->getterForActorMagicVar('fabricante', $metadata);
    }

    public function mv_uCamionFabricante_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('fabricante', $value, $metadata);
    }

    public function mv_uCamionModelo_get ($metadata)
    {
        return $this->getterForActorMagicVar('modelo', $metadata);
    }

    public function mv_uCamionModelo_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('modelo', $value, $metadata);
    }

    public function mv_uCamionColor_get ($metadata)
    {
        return $this->getterForActorMagicVar('color', $metadata);
    }

    public function mv_uCamionColor_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('color', $value, $metadata);
    }

    public function mv_uCamionMatricula_get ($metadata)
    {
        return $this->getterForActorMagicVar('matricula', $metadata);
    }

    public function mv_uCamionMatricula_set ($value, $metadata)
    {
        return $this->setterForActorMagicVar('matricula', $value, $metadata);
    }



    ///////////////////
    // INTERNAL LIBRARY
    ///////////////////



    public function googleMail ($from, $password, $to, $cc, $bcc, $subject, $body)
    {
        if (! is_array($to )) { $to  = $to  === null ? [] : explode('|', $to ); }
        if (! is_array($cc )) { $cc  = $cc  === null ? [] : explode('|', $cc ); }
        if (! is_array($bcc)) { $bcc = $bcc === null ? [] : explode('|', $bcc); }
        $mail = new PHPMailer();    // create a new object
        $mail->IsSMTP();            // enable SMTP
        $mail->SMTPDebug = 1;       // debugging: 1 = errors and messages, 2 = messages only
        $mail->SMTPAuth = true;     // authentication enabled
        $mail->SMTPSecure = 'ssl';  // secure transfer enabled REQUIRED for Gmail
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'utf-8';
        $mail->IsHTML(false);
        $mail->Username = $from;
        $mail->Password = $password;
        $mail->SetFrom($from);
        $mail->Subject = $subject;
        $mail->Body = $body;
        foreach ($to  as $aTo ) { $mail->AddAddress($aTo ); }
        foreach ($cc  as $aCc ) { $mail->AddCC     ($aCc ); }
        foreach ($bcc as $aBcc) { $mail->AddBCC    ($aBcc); }
        if (! $mail->Send()) { Log::register(__CLASS__.__LINE__.__METHOD__ . ' [PHPMailer.error=' . $mail->ErrorInfo . ']'); }
    }



    private function t3sms ($to, $text)
    {
        // basado en messente/examples/example-simple.php
        // convertir caracteres que generan problemas con los carriers de Messente y no se pueden convertir con su dashboard
        $text = str_replace('@', '(a)', $text);
        // Initialize Messente API
        // No E-mail is sent when debug mode is on. Disable debug mode for live release.
        $preferences = array(
            'username'		=> \T3\MESSENTESMS_USERNAME,
            'password'		=> \T3\MESSENTESMS_PASSWORD,
            'debug'			=> false,
            'error_email'	=> \T3\MESSENTESMS_ADMIN_EMAIL	// E-mail that gets mail when something goes wrong
        );
        $messente = new Messente($preferences);
        // Array of messages to send
        $message = array(
            'from'		    => \T3\MESSENTESMS_FROM,
            'to'		    => (substr($to, 0, 1) != '+' ? \T3\MESSENTESMS_DEFAULT_COUNTRYCODE : '') . $to,
            'content'	    => $text,
            'autoconvert'	=> 'on',    // GG-added
        );
        // echo "Sending SMS message:<br/>\n";
        // var_dump($message);
        $result = $messente->send_sms($message);
        // echo "Result:<br/>\n";
        // var_dump($result);
        //
        // custom code
        $error  = ! $result || ! isset($result['error']) || $result['error'] === true;
        if ($error) { Log::register(__CLASS__.__LINE__.__METHOD__ . ' | ' . ($result['error_message'] . (! isset($result['error_code']) ? '' : '[' . $result['error_code']  . ']'))); }
    }



    //////////////////////
    // PRIMITIVES HANDLERS
    //////////////////////



    //////////////
    // -- NO-T3 --
    //////////////



    public function pr_CurrentDateTime ($args, $metadata)
    {
        // list (...) = $args;   --> l|Y|m|d|H|i|s
        $this->doDummy([ $args, $metadata ]);
        $date = date_create();
        return explode('|', $date->format('l|Y|m|d|H|i|s'));
    }



    public function pr_StringArrayChoose ($args, $metadata)
    {
        list ($array, $separator, $index) = $args;   // --> part
        $this->doDummy($metadata);
        $array = explode($separator, $array);
        return isset($array[$index + 1]) ? $array[$index + 1] : '';
    }



    public function pr_StringArrayCount ($args, $metadata)
    {
        list ($array, $separator) = $args;   // --> count
        $this->doDummy($metadata);
        $array = explode($separator, $array);
        return count($array);
    }



    public function pr_Email ($args, $metadata)
    {
        list ($from, $password, $to, $cc, $bcc, $subject, $body) = $args;
        $this->doDummy($metadata);
        $this->googleMail($from, $password, $to, $cc, $bcc, $subject, $body);
        return null;
    }



    public function pr_StrPosOf ($args, $metadata)
    {
        list ($haystack, $needle) = $args;
        $this->doDummy($metadata);
        $pos = strpos($haystack, "$needle");
        return $pos === false ? -1 : $pos;
    }



    public function pr_SubStr ($args, $metadata)
    {
        list ($string, $start, $length) = $args;
        $this->doDummy($metadata);
        $substr = substr($string, $start, $length);
        return $substr === false ? '' : $substr;
    }



    public function pr_StrToLower ($args, $metadata)
    {
        list ($string) = $args;
        $this->doDummy($metadata);
        return strtolower($string);
    }



    public function pr_StrToUpper ($args, $metadata)
    {
        list ($string) = $args;
        $this->doDummy($metadata);
        return strtoupper($string);
    }



    public function pr_UrlEncode ($args, $metadata)
    {
        list ($string) = $args;
        $this->doDummy($metadata);
        return urlencode($string);
    }



    public function pr_Howdoi ($args, $metadata)
    {
        list ($q) = $args;
        $this->doDummy($metadata);

        if (substr($q, 0, 7) != "howdoi ") {
            return '';
        }

        unset($ec, $stdout);
        $cmd = "sudo $q";   // requires to add howdoi in allowed commands in /etc/sudoers
        exec($cmd, $stdout, $ec);

        $a = '';
        if ($ec === 0) {
            $notFound = "Sorry, couldn't find any help with that topic";
            if (substr($stdout, 0, strlen($notFound)) != $notFound) {
                $a = '[[[' . html_entity_decode(preg_replace("/\n\n\n+/", "\n\n", trim(implode("\n", $stdout)))) . ']]]';
            }
        }

        return $a;
    }



    ///////////
    // -- T3 --
    ///////////



    /**
     * Implementa la primitiva de PHP "EmailA"; se debe conservar el prefijo en el nombre del método
     *
     * * Las primitivas pueden retornar string o número
     * * Los valores booleanos retornados serán convertidos a 0|1 y los null y otros serán fijados a BotBasic::NOTHING
     * * La cantidad de valores de retorno debe coincidir con la definición de la primitiva en el programa BotBasic
     * * Para primitivas que retornan un solo valor, se puede obviar su encapsulamiento en un arreglo al retornarlo
     *
     * @param  string[]                 $args           Argumentos del menu predefinido
     * @param  array                    $metadata       Arreglo indexado por strings con valores que identifican el código y la instancia del runtime
     * @return string|string[]|null                     Valor o valores producidos por la primitiva, o null para primitivas que no retornan datos
     */
/**/public function pr_EmailA ($args, $metadata)
    {
        list ($idActor, $mensaje) = $args;
        $this->doDummy($metadata);
        $actor = Actor::DBread($idActor);   /** @var Actor $actor */
        if (! $actor || ! $actor->email()) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        $this->googleMail(\T3\SMTP_FROM, \T3\SMTP_PASSWORD, $actor->email(), null, null, \T3\SMTP_SUBJECT, $mensaje);
        return null;
    }



/**/public function pr_Contacto ($args, $metadata)
    {
        list ($motivo, $participante, $datosParticipante, $operacion, $datosOperacion, $infoAdicional) = $args;
        $userId     = $metadata['bmuserid'];
        $user       = $userId === null ? null : Actor::DBread($userId);   /** @var Actor $user */
        $userNombre = $user   === null ? null : $user->nombre() . '/' . $user->apellido() . '/' . $user->figuraLegal();
        if ($userNombre == '//') { $userNombre = '(nombre aún no definido)'; }
        $userTipo   = $user   === null ? null : $user->tipo();
        if ($datosParticipante != '') { $datosParticipante = "($datosParticipante)"; }
        if ($datosOperacion    != '') { $datosOperacion    = "($datosOperacion)";    }
        $subject    = \T3\SMTP_SUBJECT . ' - Contacto';
        $body       = "Remitente: $userNombre ($userTipo, #$userId) \n"   .
                      "Motivo: $motivo \n"                                .
                      "Participante: $participante $datosParticipante \n" .
                      "Operación: $operacion $datosOperacion \n"          .
                      "Información adicional: $infoAdicional \n"          ;
        $this->googleMail(\T3\SMTP_FROM, \T3\SMTP_PASSWORD, \T3\SMTP_CONTACT_RECIPIENT, null, null, $subject, $body);
        return null;
    }



/**/public function pr_InvitarContacto ($args, $metadata)
    {
        list ($celularOemail) = $args;
        $this->doDummy($metadata);
        // caso email
        if (strpos($celularOemail, '@') !== false) {
            $subject = $this->getCommonVar('bmaInvitarEmailSubject');
            $body    = $this->getCommonVar('bmaInvitarEmailBody');
            if ($celularOemail && $subject && $body) {
                $this->googleMail(\T3\SMTP_FROM, \T3\SMTP_PASSWORD, $celularOemail, null, null, $subject, $body);
            }
            else { Log::register(__CLASS__.__LINE__.__METHOD__); }
        }
        // caso telefono celular Panama
        else {
            $text = $this->getCommonVar('bmaInvitarSmsText');
            if ($celularOemail && $text && (strlen($celularOemail) == 8 || substr($celularOemail, 0, 4) == \T3\MESSENTESMS_DEFAULT_COUNTRYCODE)) {   // cableado a panama por formato de numeros
                $this->t3sms($celularOemail, $text);
            }
            else { Log::register(__CLASS__.__LINE__.__METHOD__); }
        }
        return true;
    }



/**/public function pr_StrReplace ($args, $metadata)
    {
        list ($from, $to, $subject) = $args;
        $this->doDummy($metadata);
        return str_replace($from, $to, $subject);
    }



/**/public function pr_SplitToArray ($args, $metadata)
    {
        list ($sep, $subject) = $args;
        $this->doDummy($metadata);
        return explode($sep, $subject);
    }



/**/public function pr_DiffPipeArrays ($args, $metadata)
    {
        list ($from, $except) = $args;
        $this->doDummy($metadata);
        $from   = explode('|', $from);
        $except = explode('|', $except);
        $diff   = array_diff($from, $except);
        return implode('|', $diff);
    }



/**/public function pr_ValidarPINactor ($args, $metadata)
    {
        list ($rol, $pin) = $args;
        $this->doDummy($metadata);
        switch ($rol) {
            case 'sponsor'      :   $id = SponsorCorp::  ValidarPIN($pin); break;
            case 'exchanger'    :   $id = SponsorRetail::ValidarPIN($pin); break;
            case 'handler'      :   $id = Colector::     ValidarPIN($pin); break;
            case 'truck'        :   $id = Camion::       ValidarPIN($pin); break;
            case 'converter'    :   $id = Acopio::       ValidarPIN($pin); break;
            case 'partner'      :   $id = Fundacion::    ValidarPIN($pin); break;
            case 'admin'        :   $id = Operador::     ValidarPIN($pin); break;
            default             :   $id = false;
        }
        return $id === false ? 0 : $id;
    }



/**/public function pr_NuevoReciclador          ($args, $metadata)  { return Reciclador::NuevoReciclador(); }

/**/public function pr_RecicladorEsInvitado     ($args, $metadata)  { return Reciclador::RecicladorEsInvitado($metadata['bmuserid']); }

/**/public function pr_PaisesPorLetraReino      ($args, $metadata)  { return Geosector::PaisesPorLetraReino($args[0]); }
/**/public function pr_CiudadesPorPais          ($args, $metadata)  { return Geosector::CiudadesPorPais($args[0]);     }
/**/public function pr_SectoresPorCiudad        ($args, $metadata)  { return Geosector::SectoresPorCiudad($args[0]);   }

/**/public function pr_Premios                  ($args, $metadata)  { return Premio::Premios(); }

/**/public function pr_Materiales               ($args, $metadata)  { return Material::Materiales();           }
/**/public function pr_TipoMaterial             ($args, $metadata)  { return Material::TipoMaterial($args[0]); }

/**/public function pr_EntregarMaterial         ($args, $metadata)  { return EntregaRecicladorColector::EntregarMaterial($args[0], $args[1], $args[2], $args[3]); }

/**/public function pr_LotesPorEntregar         ($args, $metadata)  { return EntregaColectorAcopio::LotesPorEntregar($args[0]);                               }
/**/public function pr_DiasCandidatosRecolecta  ($args, $metadata)  { return EntregaColectorAcopio::DiasCandidatosRecolecta($args[0], $args[1], $this);       }
/**/public function pr_HorasCandidatasRecolecta ($args, $metadata)  { return EntregaColectorAcopio::HorasCandidatasRecolecta($args[0], $args[1]);             }
/**/public function pr_PesoDeLotes              ($args, $metadata)  { return EntregaColectorAcopio::PesoDeLotes($args[0]);                                    }
/**/public function pr_AgendarRecolecta         ($args, $metadata)  { return EntregaColectorAcopio::AgendarRecolecta($args[0], $args[1], $args[2], $args[3]); }
/**/public function pr_RecolectasAgendadas      ($args, $metadata)  { return EntregaColectorAcopio::RecolectasAgendadas($args[0]);                            }
/**/public function pr_DatosDeRecolecta         ($args, $metadata)  { return EntregaColectorAcopio::DatosDeRecolecta($args[0], $args[1]);                     }
/**/public function pr_RealizarRecolecta        ($args, $metadata)  { return EntregaColectorAcopio::RealizarRecolecta($args[0], $args[1]);                    }

/**/public function pr_PremioEnRetail           ($args, $metadata)  { return Canje::PremioEnRetail($args[0], $args[1]);                      }
/**/public function pr_MaxPremiosCanjeables     ($args, $metadata)  { return Canje::MaxPremiosCanjeables($args[0], $args[1]);                }
/**/public function pr_GenerarPINcanje          ($args, $metadata)  { return Canje::GenerarPINcanje($args[0], $args[1], $args[2], $args[3]); }
/**/public function pr_PINesNoCanjeados         ($args, $metadata)  { return Canje::PINesNoCanjeados($args[0]);                              }
/**/public function pr_DatosDeCanje             ($args, $metadata)  { return Canje::DatosDeCanje($args[0]);                                  }
/**/public function pr_ValidarPIN               ($args, $metadata)  { return Canje::ValidarPIN($args[0], $args[1]);                          }
/**/public function pr_RollbackPIN              ($args, $metadata)  { return Canje::RollbackPIN($args[0], $args[1], $args[2]);               }
/**/public function pr_RollBackPINnotaPremiable ($args, $metadata)  { return Canje::RollBackPINnotaPremiable($args[0], $args[1], $this);     }
/**/public function pr_EntregaPremioHechaPorPIN ($args, $metadata)  { return Canje::EntregaPremioHechaPorPIN($args[0]);                      }
/**/public function pr_CanjesEnRetail           ($args, $metadata)  { return Canje::CanjesEnRetail($args[0], $args[1], $args[2]);            }

/**/public function pr_DatosDeFundacion         ($args, $metadata)  { return Fundacion::DatosDeFundacion($args[0]);     }
/**/public function pr_FundacionesPorNombre     ($args, $metadata)  { return Fundacion::FundacionesPorNombre($args[0]); }
/**/public function pr_FundacionesPorSector     ($args, $metadata)  { return Fundacion::FundacionesPorSector($args[0]); }

/**/public function pr_DatosDeReciclador        ($args, $metadata)  { return Reciclador::DatosDeReciclador($args[0]);                         }
/**/public function pr_RecicladoresPorNombre    ($args, $metadata)  { return Reciclador::RecicladoresPorNombre($args[0], $args[1], $args[2]); }
/**/public function pr_RecicladoresPorSector    ($args, $metadata)  { return Reciclador::RecicladoresPorSector($args[0]);                     }
/**/public function pr_RecicladoresPorAcopio    ($args, $metadata)  { return Reciclador::RecicladoresPorAcopio($args[0]);                     }
/**/public function pr_RecicladoresPorMetrica   ($args, $metadata)  { return Reciclador::RecicladoresPorMetrica($args[0], $args[1]);          }
/**/public function pr_HuellaVerdeReciclador    ($args, $metadata)  { return Reciclador::HuellaVerdeReciclador($metadata['bmuserid']);          }

/**/public function pr_DatosDeColector          ($args, $metadata)  { return Colector::DatosDeColector($args[0]);                  }
/**/public function pr_ColectoresPorNombre      ($args, $metadata)  { return Colector::ColectoresPorNombre($args[0]);              }
/**/public function pr_ColectoresPorSector      ($args, $metadata)  { return Colector::ColectoresPorSector($args[0]);              }
/**/public function pr_ColectoresPorAcopio      ($args, $metadata)  { return Colector::ColectoresPorAcopio($args[0]);              }
/**/public function pr_ColectoresPorMetrica     ($args, $metadata)  { return Colector::ColectoresPorMetrica($args[0], $args[1]);   }
/**/public function pr_HuellaVerdeColector      ($args, $metadata)  { return Colector::HuellaVerdeColector($metadata['bmuserid']); }
/**/public function pr_AgendaColector           ($args, $metadata)  { return Colector::AgendaColector($args[0]);                   }
/**/public function pr_ColectorIdAcopio         ($args, $metadata)  { return Colector::ColectorIdAcopio($args[0]);                 }

/**/public function pr_DatosDeAcopio            ($args, $metadata)  { return Acopio::DatosDeAcopio($args[0]);               }
/**/public function pr_AcopiosPorNombre         ($args, $metadata)  { return Acopio::AcopiosPorNombre($args[0]);            }
/**/public function pr_AcopiosPorSector         ($args, $metadata)  { return Acopio::AcopiosPorSector($args[0]);            }
/**/public function pr_AcopiosPorMetrica        ($args, $metadata)  { return Acopio::AcopiosPorMetrica($args[0], $args[1]); }
/**/public function pr_AgendaAcopio             ($args, $metadata)  { return Acopio::AgendaAcopio($args[0]);                }

/**/public function pr_DatosDeCamion            ($args, $metadata)  { return Camion::DatosDeCamion($args[0]);              }
/**/public function pr_FichaCamion              ($args, $metadata)  { return Camion::FichaCamion($args[0]);                }
/**/public function pr_CamionesPorNombre        ($args, $metadata)  { return Camion::CamionesPorNombre($args[0]);          }
/**/public function pr_CamionesPorSector        ($args, $metadata)  { return Camion::CamionesPorSector($args[0]);          }
/**/public function pr_CamionesPorAcopio        ($args, $metadata)  { return Camion::CamionesPorAcopio($args[0]);          }
/**/public function pr_AgendaCamion             ($args, $metadata)  { return Camion::AgendaCamion($args[0]);               }
/**/public function pr_AgendaCamionMenu         ($args, $metadata)  { return Camion::AgendaCamionMenu($args[0], $args[1]); }
/**/public function pr_CamionIdAcopio           ($args, $metadata)  { return Camion::CamionIdAcopio($args[0]);             }

/**/public function pr_DatosDeRetail            ($args, $metadata)  { return SponsorRetail::DatosDeRetail($args[0]);               }
/**/public function pr_RetailsPorPremio         ($args, $metadata)  { return SponsorRetail::RetailsPorPremio($args[0]);            }
/**/public function pr_RetailsPorSponsor        ($args, $metadata)  { return SponsorRetail::RetailsPorSponsor($args[0]);           }
/**/public function pr_RetailsPorCiudad         ($args, $metadata)  { return SponsorRetail::RetailsPorCiudad($args[0]);            }
/**/public function pr_RetailsPorMetrica        ($args, $metadata)  { return SponsorRetail::RetailsPorMetrica($args[0], $args[1]); }

/**/public function pr_DatosDeSponsor           ($args, $metadata)  { return SponsorCorp::DatosDeSponsor($args[0]);               }
/**/public function pr_SponsorDePremio          ($args, $metadata)  { return SponsorCorp::SponsorDePremio($args[0]);              }
/**/public function pr_SponsorsPorRetail        ($args, $metadata)  { return SponsorCorp::SponsorsPorRetail($args[0]);            }
/**/public function pr_SponsorsPorCiudad        ($args, $metadata)  { return SponsorCorp::SponsorsPorCiudad($args[0]);            }
/**/public function pr_SponsorsPorMetrica       ($args, $metadata)  { return SponsorCorp::SponsorsPorMetrica($args[0], $args[1]); }

/**/public function pr_UpdateNombreApellido     ($args, $metadata)  { return Actor ::UpdateNombreApellido($args[0], $args[1], $args[2]);   }
/**/public function pr_UpdateSexo               ($args, $metadata)  { return Actor ::UpdateSexo($args[0], $args[1]);                       }
/**/public function pr_UpdateTelefonos          ($args, $metadata)  { return Actor ::UpdateTelefonos($args[0], $args[1], $args[2]);        }
/**/public function pr_UpdateTelefonoCelular    ($args, $metadata)  { return Actor ::UpdateTelefonoCelular($args[0], $args[1]);            }
/**/public function pr_UpdateEmail              ($args, $metadata)  { return Actor ::UpdateEmail($args[0], $args[1]);                      }
/**/public function pr_UpdateMarca              ($args, $metadata)  { return Actor ::UpdateMarca($args[0], $args[1]);                      }
/**/public function pr_UpdateFiguraLegal        ($args, $metadata)  { return Actor ::UpdateFiguraLegal($args[0], $args[1]);                }
/**/public function pr_UpdateNIT                ($args, $metadata)  { return Actor ::UpdateNIT($args[0], $args[1]);                        }
/**/public function pr_UpdateConstitucion       ($args, $metadata)  { return Actor ::UpdateConstitucion($args[0], $args[1]);               }
/**/public function pr_UpdateDireccion          ($args, $metadata)  { return Actor ::UpdateDireccion($args[0], $args[1], $args[2]);        }
/**/public function pr_UpdateFabricante         ($args, $metadata)  { return Camion::UpdateFabricante($args[0], $args[1]);                 }
/**/public function pr_UpdateModelo             ($args, $metadata)  { return Camion::UpdateModelo($args[0], $args[1]);                     }
/**/public function pr_UpdateColor              ($args, $metadata)  { return Camion::UpdateColor($args[0], $args[1]);                      }
/**/public function pr_UpdateMatricula          ($args, $metadata)  { return Camion::UpdateMatricula($args[0], $args[1]);                  }



    /////////////////
    // MENUS HANDLERS
    /////////////////



    /**
     * Implementa el menú predefinido "thismenuname"; se debe conservar el prefijo en el nombre del método
     *
     * * La cantidad de valores de retorno debe coincidir con la definición del menú en el programa BotBasic
     * * La excepción es cuando en la definición, el último argumento indica cantidad variable de argumentos
     *
     * IMPLEMENTACION DE EJEMPLO
     *
     * @param  string[]             $args           Argumentos del menu predefinido
     * @param  string[]             $titles         Títulos del menú predefinido
     * @param  string[]             $options        Textos de las opciones del menú
     * @param  array                $pager          Paginador, en forma: [ pagerSpec, pagerArg ]
     * @param  mixed|null           $contextObject  Objeto opcional que sirve de contexto al menú
     * @param  null|string          $key            null si se invoca la primera vez; la clave de la opción presionada de otro modo
     * @param  array                $metadata       Arreglo indexado por strings con valores que identifican el código y la instancia del runtime
     * @return null|string|string[]                 null si no ha finalizado la ejecución del menú; el o los valores de retorno de otro modo
     */
    public function mn_ThisMenuName ($args, $titles, $options, $pager, $contextObject, $key, $metadata)
    {
        $this->doDummy([ $args, $titles, $options, $pager, $contextObject, $key, $metadata ]);
        return "selected option";
    }



    ///////
    // NIMA
    ///////



    public function pr_NimaMotd              ($args, $metadata) { return All::Motd($args[0]);                                                        }
    public function pr_NimaPollPregunta      ($args, $metadata) { return All::PollPregunta($metadata['bmuserid']);                                   }
    public function pr_NimaPollRespuesta     ($args, $metadata) { return All::PollRespuesta($args[0], $args[1], $metadata['bmuserid']);              }
    public function pr_NimaRegistrarPregunta ($args, $metadata) { return All::RegistrarPregunta($args[0]);                                           }
    public function pr_NimaPreguntaGente     ($args, $metadata) { return All::PreguntaGente($metadata['bmuserid']);                                  }
    public function pr_NimaAgregarRespuesta  ($args, $metadata) { return All::AgregarRespuesta($args[0], $args[1], $args[2], $metadata['bmuserid']); }
    public function pr_NimaPreguntas         ($args, $metadata) { return All::Preguntas($args[0]);                                                   }
    public function pr_NimaRespuestas        ($args, $metadata) { return All::Respuestas($args[0]);                                                  }
    public function pr_NimaValorarRespuesta  ($args, $metadata) { return All::ValorarRespuesta($args[0], $args[1]);                                  }



    ///////////////
    // TESTING AREA (botbasic development)
    ///////////////



    public function pr_TestPrimitiveMixedCall ($args, $metadata)
    {
        list ($numOpciones, $tipoRetorno) = $args;
        $this->doDummy($metadata);
        $items = [ 'cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez' ];
        if ($numOpciones > 10) { $numOpciones = 10; }
        $res = [];
        for ($i = 1; $i <= $numOpciones; $i++) {
            $res[] = $tipoRetorno != 'mixto' ? $i : [ $i, $items[$i] ];
        }
        return $res;
    }

    public function pr_TestOptionsBasicas ($args, $metadata)
    {
        $this->doDummy([ $args, $metadata ]);
        return [ 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59 ];
    }

    public function pr_TestOptionsMixtas ($args, $metadata)
    {
        $this->doDummy([ $args, $metadata ]);
        return [
            [ 70, '7cero'   ], [ 71, '7uno'    ], [ 72, '7dos'    ], [ 73, '7tres'   ], [ 74, '7cuatro' ],
            [ 75, '7cinco'  ], [ 76, '7seis'   ], [ 77, '7siete'  ], [ 78, '7ocho'   ], [ 79, '7nueve'  ],
            [ 80, '8cero'   ], [ 81, '8uno'    ], [ 82, '8dos'    ], [ 83, '8tres'   ], [ 84, '8cuatro' ],
            [ 85, '8cinco'  ], [ 86, '8seis'   ], [ 87, '8siete'  ], [ 88, '8ocho'   ], [ 89, '8nueve'  ],
        ];
    }

    public function pr_TestEmailT3 ($args, $metadata)
    {
        list ($to, $subject, $body) = $args;
        $this->doDummy($metadata);
        $this->googleMail(\T3\SMTP_FROM, \T3\SMTP_PASSWORD, $to, null, null, $subject, $body);
        return [];
    }

    public function mn_SegundoMenu       ($args, $titles, $options, $pager, $lineno, $bot, $contextObject, $key = null) {}
    public function pr_SegundaPrimitiva  ($args, $lineno, $bot) {}
    public function pr_TerceraPrimitiva  ($args, $lineno, $bot) {}
    public function pr_CuartaPrimitiva   ($args, $lineno, $bot) {}
    public function pr_LogicPrimitive    ($args, $lineno, $bot) {}
    public function pr_NonLogicPrimitive ($args, $lineno, $bot) {}
    public function mv_magicVar_get      () {}
    public function mv_magicVar_set      () {}



}
