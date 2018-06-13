<?php



namespace T3;



/**
 * Class Actor
 *
 * @method string               tipo                (bool|string            $arg = null)
 * @method int                  profileFull         (bool|int               $arg = null)
 * @method string               tipoPersona         (bool|string            $arg = null)
 * @method string|null          figuraLegal         (bool|string|null       $arg = null)
 * @method string|null          marca               (bool|string|null       $arg = null)
 * @method string|null          nit                 (bool|string|null       $arg = null)
 * @method string|null          nombre              (bool|string|null       $arg = null)
 * @method string|null          apellido            (bool|string|null       $arg = null)
 * @method string|null          telefonoFijo        (bool|string|null       $arg = null)
 * @method string               telefonoCelular     (bool|string            $arg = null)
 * @method string|null          email               (bool|string|null       $arg = null)
 * @method string|null          nacimiento          (bool|string|null       $arg = null)
 * @method string|null          sexo                (bool|string|null       $arg = null)
 * @method string|null          ocupacion           (bool|string|null       $arg = null)
 * @method string|null          direccion           (bool|string|null       $arg = null)
 * @method array|null           geolocalizacion     (bool|array|null        $arg = null)
 * @method float|null           ecopuntos           (bool|float|null        $arg = null)
 * @method int|Geosector|null   ubicacion           (bool|Geosector|null    $arg = null)
 * @method int|Actor|null       enroladoPor         (bool|Actor|null        $arg = null)
 * @method int|Actor|null       afiliadoA           (bool|Actor|null        $arg = null)
 * @method string|null          notas               (bool|string|null       $arg = null)
 * @method string|null          pin                 (bool|string|null       $arg = null)
 *
 * @package T3
 */
abstract class Actor extends Persistable
{



    const SOFT_DELETIONS = true;



    static protected function membersList ()
    {
        return [
            'id',
            'tipo',
            'profileFull',
            'tipoPersona',
            'figuraLegal',
            'marca',
            'nit',
            'nombre',
            'apellido',
            'telefonoFijo',
            'telefonoCelular',
            'email',
            'nacimiento',
            'sexo',
            'ocupacion',
            'direccion',
            'geolocalizacion',
            'ecopuntos',
            [ 'ubicacion',      'Geosector'     ],
            [ 'enroladoPor',    'Actor'         ],
            [ 'afiliadoA',      'Actor'         ],
            'notas',
            'pin',
        ];
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('id',              $record, 'id'                                                                             );
        $obj->fillMember('tipo',            $record, 'tipo',                ! isset($values['tipo']) ? 'reciclador' : $values['tipo'] );
        $obj->fillMember('profileFull',     $record, 'profile_full',        0                                                         );
        $obj->fillMember('tipoPersona',     $record, 'tipo_persona',        'natural'                                                 );
        $obj->fillMember('figuraLegal',     $record, 'figura_legal'                                                                   );
        $obj->fillMember('marca',           $record, 'marca'                                                                          );
        $obj->fillMember('nit',             $record, 'nit'                                                                            );
        $obj->fillMember('nombre',          $record, 'nombre'                                                                         );
        $obj->fillMember('apellido',        $record, 'apellido'                                                                       );
        $obj->fillMember('telefonoFijo',    $record, 'telefono_fijo'                                                                  );
        $obj->fillMember('telefonoCelular', $record, 'telefono_celular',    ''                                                        );
        $obj->fillMember('email',           $record, 'email'                                                                          );
        $obj->fillMember('nacimiento',      $record, 'nacimiento'                                                                     );
        $obj->fillMember('sexo',            $record, 'sexo'                                                                           );
        $obj->fillMember('ocupacion',       $record, 'ocupacion'                                                                      );
        $obj->fillMember('direccion',       $record, 'direccion'                                                                      );
        $obj->fillMember('geolocalizacion', $record, 'geolocalizacion',     null                                           /*,true*/  );   // las coordenadas se guardan tal como se extraen de google maps (string)
        $obj->fillMember('ecopuntos',       $record, 'ecopuntos'                                                                      );
        $obj->fillMember('ubicacion',       $record, 'ubicacion'                                                                      );
        $obj->fillMember('enroladoPor',     $record, 'enrolado_por'                                                                   );
        $obj->fillMember('afiliadoA',       $record, 'afiliado_a'                                                                     );
        $obj->fillMember('notas',           $record, 'notas'                                                                          );
        $obj->fillMember('pin',             $record, 'pin',                 '1234-5678'                                               );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    /**
     * @param  int|int[]|string                     $criterium
     * @param  null|Callable                        $factory
     * @return Persistable|Persistable[]|bool|null
     */
    static public function DBread ($criterium, $factory = null)
    {
        return self::DBreadHelper($criterium, __CLASS__, $factory);
    }



    static protected function DBwrite ($obj, $innerCall = false)
    {
        return self::DBwriteHelper(/** @var Actor $obj */ $obj, [
            'tipo'              => DBbroker::q($obj->tipo()),
            'profileFull'       => DBbroker::q($obj->profileFull()),
            'tipoPersona'       => DBbroker::q($obj->tipoPersona()),
            'figuraLegal'       => DBbroker::q($obj->figuraLegal()),
            'marca'             => DBbroker::q($obj->marca()),
            'nit'               => DBbroker::q($obj->nit()),
            'nombre'            => DBbroker::q($obj->nombre()),
            'apellido'          => DBbroker::q($obj->apellido()),
            'telefonoFijo'      => DBbroker::q($obj->telefonoFijo()),
            'telefonoCelular'   => DBbroker::q($obj->telefonoCelular()),
            'email'             => DBbroker::q($obj->email()),
            'nacimiento'        => DBbroker::q($obj->nacimiento()),
            'sexo'              => DBbroker::q($obj->sexo()),
            'ocupacion'         => DBbroker::q($obj->ocupacion()),
            'direccion'         => DBbroker::q($obj->direccion()),
            'geolocalizacion'   => DBbroker::q($obj->geolocalizacion()),   // json_encode($obj->geolocalizacion())
            'ecopuntos'         => DBbroker::q($obj->ecopuntos()),
            'ubicacion'         => DBbroker::q($obj->ubicacion(true)),
            'enroladoPor'       => DBbroker::q($obj->enroladoPor(true)),
            'afiliadoA'         => DBbroker::q($obj->afiliadoA(true)),
            'notas'             => DBbroker::q($obj->notas()),
            'pin'               => DBbroker::q($obj->pin()),
        ], $innerCall, __CLASS__);
    }



    static protected function DBdelete ($obj, $innerCall = false)
    {
        return self::DBdeleteHelper($obj, $innerCall, __CLASS__);
    }



    static protected function DBsoftDeletions()
    {
        return self::SOFT_DELETIONS;
    }



    // NAVIGATION METHODS



    public function donaciones ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Donacion::DBread("beneficiario = " . $id . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function invitados ($load = false, $sqlWhereAnd = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Invitacion::query($this, null, $sqlWhereAnd, 'destinatario', $load);
    }



    public function notificacionesEnviadas ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Notificacion::DBread("remitente = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function notificacionesRecibidas ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Notificacion::DBread("destinatario = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    protected function transaccionesOriginadas ($tipo = null, $sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Transaccion::DBread(($tipo === null ? '' : "tipo = '$tipo' ") . "originador = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    protected function transaccionesVerificadas ($tipo = null, $sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Transaccion::DBread(($tipo === null ? '' : "tipo = '$tipo' ") . "verificador = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function donacionesOriginadas ($subtipo = null, $sqlWhere = null)
    {
        return $this->transaccionesOriginadas('donacion', ($subtipo === null ? "TRUE" : "subtipo = '$subtipo'") . " AND " . ($sqlWhere === null ? "TRUE" : "($sqlWhere)"));
    }



    // COMPOSITE ATTRIBUTES



    public function direccionCompleta ()
    {
        $res = $this->direccion();   /** @var Geosector $loc */
        for ($loc = $this->ubicacion(); $loc !== null; $loc = $loc->adscritoA()) { $res .= ', ' . $loc->nombre(); }
        return $res;
    }



    // TOOLBOX METHODS



    static protected function sqlLapsoPorMetrica ($metrica)
    {
        switch ($metrica) {
            case 'ecopuntos'    :
            case 'ecopoints'    : $lapso = "100 YEAR"; break;
            case 'mes'          :
            case 'month'        : $lapso = "1 MONTH"; break;
            case 'trimestre'    :
            case 'quarter'      : $lapso = "3 MONTH"; break;
            case 'año'          :
            case 'year'         : $lapso = "1 YEAR"; break;
            case 'trending'     : $lapso = "3 DAY"; break;
            default             : $lapso = "3 DAY"; Log::register(__CLASS__.__LINE__.__METHOD__);
        }
        return $lapso;
    }



    static public function datosDePersonaNatural ($id, $returnObjectInArray = false)
    {
        $edad = function ($sqlDate)
        {
            if ($sqlDate === null) { return "???"; }
            date_diff(date_create($sqlDate), date_create('today'))->y;
        };

        $actor = self::DBread($id);   /** @var Actor $actor */
        if ($actor === null)  { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        if ($actor === false) { return []; }
        $res = [ $actor->nombre(), $actor->apellido(), $actor->telefonoFijo(), $actor->telefonoCelular(), $actor->email(),
                 $actor->sexo(), $actor->nacimiento(), $edad($actor->nacimiento()), $actor->direccionCompleta() ];
        return ! $returnObjectInArray ? $res : [ $actor, $res ];
    }



    static public function datosDePersonaJuridica ($id, $returnObjectInArray = false)
    {
        $actor = self::DBread($id);   /** @var Actor $actor */
        if ($actor === null)  { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        if ($actor === false) { return []; }
        $res = [ $actor->figuraLegal(), $actor->marca(), $actor->nit(), $actor->nombre(), $actor->apellido(),
                 $actor->telefonoFijo(), $actor->telefonoCelular(), $actor->email(), $actor->nacimiento(), $actor->direccionCompleta() ];
        return ! $returnObjectInArray ? $res : [ $actor, $res ];
    }



    static private function personasPorNombre ($tipoPersona, $parte, $tipo, $campoDeFiltro = null, $idParaFiltro = null)
    {
        $sqlApelativo = $tipoPersona == 'natural' ? "CONCAT(nombre, ' ', apellido)" : "figura_legal";
        $sqlfiltro    = $campoDeFiltro === null ? '' : " AND $campoDeFiltro = $idParaFiltro";
        $items        = self::DBread("tipo = '$tipo' $sqlfiltro AND UCASE($sqlApelativo) LIKE " . DBbroker::q(strtoupper("%$parte%")));
        if ($items === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return array_map(function (Actor $o) use ($tipoPersona) {
                             return [ $o->id(), $tipoPersona == 'natural' ? $o->nombre() . ' ' . $o->apellido() : $o->figuraLegal() ];
                         }, $items);
    }



    static public function personasNaturalesPorNombre ($parte, $tipo, $campoDeFiltro = null, $idParaFiltro = null)
    {
        return self::personasPorNombre('natural', $parte, $tipo, $campoDeFiltro, $idParaFiltro);
    }



    static public function personasJuridicasPorNombre ($parte, $tipo, $campoDeFiltro = null, $idParaFiltro = null)
    {
        return self::personasPorNombre('juridica', $parte, $tipo, $campoDeFiltro, $idParaFiltro);
    }



    static private function personasPorSector ($tipoPersona, $idSector, $tipo, $dobleSalto = false)
    {
        $items = self::DBread(! $dobleSalto ?
            "tipo = '$tipo' AND ubicacion                                               = $idSector" :
            "tipo = '$tipo' AND ubicacion IN (SELECT id FROM geosector WHERE adscrito_a = $idSector)"
        );
        if ($items === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return array_map(function (Actor $o) use ($tipoPersona) {
                             return [ $o->id(), $tipoPersona == 'natural' ? $o->nombre() . ' ' . $o->apellido() : $o->figuraLegal() ];
                         }, $items);
    }



    static public function personasNaturalesPorSector ($idSector, $tipo, $dobleSalto = false)
    {
        return self::personasPorSector('natural', $idSector, $tipo, $dobleSalto);
    }



    static public function personasJuridicasPorSector ($idSector, $tipo, $dobleSalto = false)
    {
        return self::personasPorSector('juridica', $idSector, $tipo, $dobleSalto);
    }



    static private function personasPorAfiliacion ($tipoPersona, $idAfiliante, $tipo, $dobleSalto = false, $segundoCampo = null)
    {
        $items = self::DBread(! $dobleSalto ?
                    "tipo = '$tipo' AND afiliado_a                                           = $idAfiliante" :
                    "tipo = '$tipo' AND afiliado_a IN (SELECT id FROM actor WHERE afiliado_a = $idAfiliante)"
                 );
        if ($items === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return array_map(function (Actor $o) use ($tipoPersona, $segundoCampo) {
                             return [ $o->id(), $segundoCampo !== null ? $o->$segundoCampo() : ($tipoPersona == 'natural' ? $o->nombre() . ' ' . $o->apellido() : $o->figuraLegal()) ];
                         }, $items);
    }



    static public function personasNaturalesPorAfiliacion ($idAfiliante, $tipo, $dobleSalto = false, $segundoCampo = null)
    {
        return self::personasPorAfiliacion('natural', $idAfiliante, $tipo, $dobleSalto, $segundoCampo);
    }



    static public function personasJuridicasPorAfiliacion ($idAfiliante, $tipo, $dobleSalto = false, $segundoCampo = null)
    {
        return self::personasPorAfiliacion('juridica', $idAfiliante, $tipo, $dobleSalto, $segundoCampo);
    }



    static private function normalizarPIN ($pin)
    {
        $pin = preg_replace('/[^0-9]/', '', $pin);
        $pin = substr($pin, 0, 8);
        $pin = str_pad($pin, 8, '0', STR_PAD_LEFT);
        $pin = substr($pin, 0, 4) . '-' . substr($pin, 4, 4);
        return $pin;
    }



    static public function actorVerificarPIN ($tipo, $pin)
    {
        $pin   = self::normalizarPIN($pin);
        $items = self::DBread("tipo = '$tipo' AND pin = '$pin' AND profile_full = 0");   /** @var Actor[] $items */
        if     ($items === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        elseif (! $items)        { return false; }
        return $items[0]->id();   // ignorar registros conincidentes adicionales, extras al primero retornado
    }



    // PRIMITIVES METHODS



    static public function UpdateNombreApellido ($id, $nombre, $apellido)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Actor $o */
        $o->nombre($nombre);
        $o->apellido($apellido);
    }



    static public function UpdateSexo ($id, $sexo)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Actor $o */
        $o->sexo($sexo);
    }



    static public function UpdateTelefonos ($id, $telFijo, $telCelular)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Actor $o */
        $o->telefonoFijo($telFijo);
        $o->telefonoCelular($telCelular);
    }



    static public function UpdateTelefonoCelular ($id, $telCelular)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Actor $o */
        $o->telefonoCelular($telCelular);
    }



    static public function UpdateEmail ($id, $email)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Actor $o */
        $o->email($email);
    }



    static public function UpdateMarca ($id, $marca)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Actor $o */
        $o->marca($marca);
    }



    static public function UpdateFiguraLegal ($id, $figuraLegal)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Actor $o */
        $o->figuraLegal($figuraLegal);
    }



    static public function UpdateNIT ($id, $nit)
    {
        $nit = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $nit));
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Actor $o */
        $o->nit($nit);
    }



    static public function UpdateConstitucion ($id, $constitucion)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Actor $o */
        $o->nacimiento($constitucion);
    }



    static public function UpdateDireccion ($id, $idSector, $direccion)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Actor $o */
        $o->ubicacion($idSector);
        $o->direccion($direccion);
    }



}
