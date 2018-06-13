<?php



namespace T3;



/**
 * Class Acopio
 *
 * @package T3
 */
class Acopio extends Vector
{



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('tipo',        $record, 'tipo',            'acopio'    );
        $obj->fillMember('tipoPersona', $record, 'tipo_persona',    'juridica'  );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    // NAVIGATION METHODS



    public function materialesProcesables ($load = true, $sqlWhereAnd = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Procesador::query($this, null, $sqlWhereAnd, 'material', $load);
    }



    public function camiones ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Camion::DBread("afiliado_a = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function pagosOriginados ($sqlWhere = null)
    {
        return $this->transaccionesOriginadas('pago', $sqlWhere);
    }



    public function entregasOriginadas ($subtipo = null, $sqlWhere = null)
    {
        error_log("Acopio: un Acopio no puede originar entregas");
        return null;
    }



    public function entregasVerificadas ($subtipo = null, $sqlWhere = null)
    {
        return parent::entregasOriginadas('entrega_colector_acopio', $sqlWhere);
    }



    // PRIMITIVES METHODS



    static public function ValidarPIN ($pin)
    {
        return self::actorVerificarPIN('acopio', $pin);
    }



    static public function DatosDeAcopio ($id)
    {
        return self::datosDePersonaJuridica($id);
    }



    static public function AcopiosPorNombre ($parte)
    {
        return self::personasJuridicasPorNombre($parte, 'acopio');
    }



    static public function AcopiosPorSector ($idSector)
    {
        return self::personasJuridicasPorSector($idSector, 'acopio');
    }



    static public function AcopiosPorMetrica ($metrica, $maxEnLista)
    {
        // determinar el lapso de consulta de Entregas segun la metrica especificada
        $lapso = self::sqlLapsoPorMetrica($metrica);

        // obtener datos de Acopios ordenados por ecopuntos involucrados segun lapso definido ariba segun metrica
        $query = <<<END
            SELECT p.acopio_id AS id, p.acopio_figura_legal, SUM(e.ecopuntos) AS agregado_ecopuntos
              FROM v_premiable_con_acopio AS p
              JOIN transaccion AS t ON t.originador = p.id
              JOIN entrega AS e ON t.id = e.id
             WHERE e.updated BETWEEN DATE_SUB(NOW(), INTERVAL $lapso) AND NOW()
             GROUP BY p.acopio_id, p.acopio_figura_legal 
             ORDER BY agregado_ecopuntos DESC
             LIMIT $maxEnLista;
END;
        $orden = DBbroker::query($query);
        if ($orden === false) { return Log::register(__CLASS__.__LINE__.__METHOD__); }

        // obtener coleccion de objetos Acopio
        $ids     = array_map(function ($elem) { return $elem['id']; }, $orden);
        $acopios = self::DBread($ids);   /** @var Acopio[] $acopios */
        if (! $acopios) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        $ids        = array_map(function (Acopio $o) { return $o->id(); }, $acopios);
        $acopiosXid = array_combine($ids, $acopios);

        // retornar la data completa de los acopios y sus ecopuntos, en el orden correcto
        $res = [];
        for ($i = 0; $i < count($orden); $i++) {
            list ($id, , $ecopuntos) = array_values($orden[$i]);
            $acopio = $acopiosXid[$id];   /** @var Acopio $acopio */
            $label  = ($i+1) . '. (' . $ecopuntos . 'p) ' . $acopio->figuraLegal();
            $res[]  = [ $acopio->id(), $label ];
            //$res[]  = [ $acopio->figuraLegal(), $acopio->marca(), $acopio->nit(), $acopio->nombre(), $acopio->apellido(),
            //            $acopio->telefonoFijo(), $acopio->telefonoCelular(), $acopio->email(), $acopio->nacimiento(),
            //            $acopio->direccionCompleta(), $label, $ecopuntos ];
        }
        return $res;
    }



    static public function AgendaAcopio ($idColector)
    {
        $entregas = EntregaColectorAcopio::DBread("originador IN (SELECT id FROM actor WHERE afiliado_a = $idColector) AND efectuada_en IS NULL");   /** @var EntregaColectorAcopio[] $entregas */
        if (! $entregas && $entregas != []) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }

        // indexar entregas segun momento y en segundo lugar por ID de acopio
        $entXmom = [];
        foreach ($entregas as $entrega) {
            $momento = $entrega->programadaPara();
            if (! isset($entXmom[$momento])) { $entXmom[$momento] = []; }
            if (! ($lote = $entrega->lote()))           { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            if (! ($colector = $entrega->originador())) { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            if (! ($camion = $entrega->camion()))       { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            if (! isset($entXmom[$momento][$colector->id()])) { $entXmom[$momento][$colector->id()] = []; }
            $entXmom[$momento][$colector->id()][] = $entrega;
        }

        // preparar textos ordenados momento de entrega
        $momentos = array_keys($entXmom);
        sort($momentos);
        $eventos = [];
        foreach ($momentos as $momento) {
            foreach ($momento as $idColector) {
                $entregas   = $entXmom[$momento][$idColector];
                $eveXtipMat = [];
                // indexar las entregas de este momento por tipo de material y acumular peso y numeros de lote involucrados
                foreach ($entregas as $entrega) {
                    $tipoMat = $entrega->lote()->tipoMaterial();
                    if (! isset($eveXtipMat[$tipoMat])) { $eveXtipMat[$tipoMat] = [ [], 0, $entrega->originador()->nombre() . ' ' . $entrega->originador()->apellido(), $entrega->originador()->direccion(), $entrega->camion()->matricula() ]; }   // [ nrosLotes, peso, ... ]
                    $eveXtipMat[$tipoMat][0][] = $entrega->lote()->numero();
                    $eveXtipMat[$tipoMat][1]  += $entrega->lote()->peso();
                }
                // construir texto del evento y agregar a los eventos
                $evento = "$momento: ";
                foreach ($eveXtipMat as $tipoMat => $pair) {
                    list ($nrosLotes, $peso, $nombreColector, $direccion, $matricula) = $pair;
                    $evento .= "$matricula busca $peso Kg de $tipoMat (lotes " . implode(', ', $nrosLotes) . "), en $direccion, con $nombreColector";
                }
                $eventos[] = $evento;
            }
        }

        // ready
        return implode('|', $eventos);
    }



}
