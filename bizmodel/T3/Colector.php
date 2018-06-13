<?php



namespace T3;



/**
 * Class Colector
 *
 * @package T3
 */
class Colector extends Premiable
{



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('tipo',        $record, 'tipo',            'colector'  );
        $obj->fillMember('ecopuntos',   $record, 'ecopuntos',       0           );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    // NAVIGATION METHODS



    public function recicladoresAfiliados ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Reciclador::DBread("afiliado_a = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function entregasOriginadas ($subtipo = null, $sqlWhere = null)
    {
        return parent::entregasOriginadas('entrega_colector_acopio', $sqlWhere);
    }



    public function entregasVerificadas ($subtipo = null, $sqlWhere = null)
    {
        return parent::entregasVerificadas('entrega_reciclador_colector', $sqlWhere);
    }



    // TOOLBOX METHODS



    public function loteParaEntregaMaterial ($idMaterial, $cantidad)
    {
        // cargar objetos
        $m = Material::DBread($idMaterial);   /** @var Material $m */
        if (! $m) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }
        $ciudad = $this->ubicacion();
        if ($ciudad->nivel() == 'sector') { $ciudad = $ciudad->adscritoA(); }

        // obtener el Lote abierto para el Colector y tipo de Material (o crear uno si no existe)
        $items = Lote::DBread("estado = 'abierto' AND tipo_material = '" . $m->tipo() . "' AND colector = " . $this->id());
        if (! $items && $items != []) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }
        if (count($items) > 0) {
            $lote = $items[0];
        }
        else {
            $lote = Lote::factory();   /** @var Lote $lote */
            $lote->tipoMaterial($m->tipo());
            $lote->colector($this);
            //$lote->acopio(null);
            $lote->numero(Lote::nuevoNumero($this->id()));
        }

        // sumar al Lote el peso equivalente de la Entrega que se registra y verificar si este lote debe ser cerrado por haber alcanzado el peso minimo de transportacion
        $kg = $m->pesoPorUnidad() * $cantidad;
        $lote->peso($lote->peso() + $kg);
        $items = LoteThreshold::DBread("tipo_material = '" . $m->tipo() . "' AND ubicacion = " . $ciudad->id());   /** @var LoteThreshold[] $items */
        if (! $items) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }
        $limite  = $items[0]->limite();
        $doClose = $lote->peso() >= $limite;

        // cerrar el lote, si es procedente, o...
        if ($doClose) {
            $lote->estado('cerrado');
            $lotesCerrados = [ $lote->id() ];
        }

        // ... alternativamente activar cierre automatico de algun(os) Lote(s) para invocar una posible recogida de materiales con el Acopio respectivo
        else {
            // $lote->save();   // ID en BD debe ser generado para que la siguiente operacion funcione (pero: optimizadamente no es necesario considerar un lote recien creado)
            $lotesCerrados = Lote::cierreAutomatico($this->id());
            if ($lotesCerrados === null) { return null; }
        }

        // ready
        $nrosLotesCerrados = array_map(function (Lote $o) { return $o->numero(); }, $lotesCerrados);
        return [ $lote, $nrosLotesCerrados ];
    }



    // PRIMITIVES METHODS



    static public function ValidarPIN ($pin)
    {
        return self::actorVerificarPIN('colector', $pin);
    }



    static public function DatosDeColector ($id)
    {
        return self::datosDePersonaNatural($id);
    }



    static public function ColectoresPorNombre ($parte)
    {
        return self::personasNaturalesPorNombre($parte, 'colector');
    }



    static public function ColectoresPorSector ($idSector)
    {
        return self::personasNaturalesPorSector($idSector, 'colector');
    }



    static public function ColectoresPorAcopio ($idAcopio)
    {
        return self::personasNaturalesPorAfiliacion($idAcopio, 'colector');
    }



    static public function ColectoresPorMetrica ($metrica, $maxEnLista)
    {
        // determinar el lapso de consulta de Entregas segun la metrica especificada
        $lapso = self::sqlLapsoPorMetrica($metrica);

        // obtener datos de Colectores ordenados por ecopuntos involucrados segun lapso definido ariba segun metrica
        $query = <<<END
            SELECT r.colector_id AS id, r.colector_figura_legal, SUM(e.ecopuntos) AS agregado_ecopuntos
              FROM v_reciclador_con_colector AS r
              JOIN transaccion AS t ON t.originador = r.id
              JOIN entrega AS e ON t.id = e.id
             WHERE e.updated BETWEEN DATE_SUB(NOW(), INTERVAL $lapso) AND NOW()
             GROUP BY r.colector_id, r.colector_figura_legal 
             ORDER BY agregado_ecopuntos DESC
             LIMIT $maxEnLista;
END;
        $orden = DBbroker::query($query);
        if ($orden === false) { return Log::register(__CLASS__.__LINE__.__METHOD__); }

        // obtener coleccion de objetos Colector
        $ids        = array_map(function ($elem) { return $elem['id']; }, $orden);
        $colectores = self::DBread($ids);   /** @var Colector[] $colectores */
        if ($colectores === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        $ids           = array_map(function (Colector $o) { return $o->id(); }, $colectores);
        $colectoresXid = array_combine($ids, $colectores);

        // retornar la data completa de los Colectores y sus ecopuntos, en el orden correcto
        $res = [];
        for ($i = 0; $i < count($orden); $i++) {
            list ($id, , $ecopuntos) = array_values($orden[$i]);
            $colector = $colectoresXid[$id];   /** @var Colector $colector */
            $label    = ($i+1) . '. (' . $ecopuntos . 'p) ' . $colector->nombre() . ' ' . $colector->apellido();
            $res[]    = [ $colector->id(), $label ];
            //$res[]    = [ $colector->figuraLegal(), $colector->marca(), $colector->nit(), $colector->nombre(), $colector->apellido(),
            //              $colector->telefonoFijo(), $colector->telefonoCelular(), $colector->email(), $colector->nacimiento(),
            //              $colector->direccionCompleta(), $label, $ecopuntos ];
        }
        return $res;
    }



    static public function HuellaVerdeColector ($id)
    {
        $colector = self::DBread($id);   /** @var Colector $colector */
        if (! $colector) { return Log::register(__CLASS__.__LINE__.__METHOD__, ''); }
        $co2kg = $colector->ecopuntos() * ECOPOINTS_TO_CO2_KG_FACTOR_COLECTOR;
        return round($co2kg, 3);
    }



    static public function ColectorIdAcopio ($id)
    {
        if (($colector = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }   /** @var Colector $colector */
        return $colector->afiliadoA(true);
    }



    static public function AgendaColector ($idColector)
    {
        $entregas = EntregaColectorAcopio::DBread("originador = $idColector AND efectuada_en IS NULL");   /** @var EntregaColectorAcopio[] $entregas */
        if (! $entregas && $entregas != []) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }

        // indexar entregas segun momento
        $entXmom = [];
        foreach ($entregas as $entrega) {
            $momento = $entrega->programadaPara();
            if (! isset($entXmom[$momento])) { $entXmom[$momento] = []; }
            if (! ($lote = $entrega->lote())) { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            $entXmom[$momento][] = $entrega;
        }

        // preparar textos ordenados momento de entrega
        $momentos = array_keys($entXmom);
        sort($momentos);
        $eventos = [];
        foreach ($momentos as $momento) {
            $entregas   = $entXmom[$momento];
            $eveXtipMat = [];
            // indexar las entregas de este momento por tipo de material y acumular peso y numeros de lote involucrados
            foreach ($entregas as $entrega) {
                $tipoMat = $entrega->lote()->tipoMaterial();
                if (! isset($eveXtipMat[$tipoMat])) { $eveXtipMat[$tipoMat] = [ [], 0 ]; }   // [ nrosLotes, peso ]
                $eveXtipMat[$tipoMat][0][] = $entrega->lote()->numero();
                $eveXtipMat[$tipoMat][1]  += $entrega->lote()->peso();
            }
            // construir texto del evento y agregar a los eventos
            $evento = "$momento: ";
            foreach ($eveXtipMat as $tipoMat => $pair) {
                list ($nrosLotes, $peso) = $pair;
                $evento .= "$peso Kg de $tipoMat (lotes " . implode(', ', $nrosLotes) . ")";
            }
            $eventos[] = $evento;
        }

        // ready
        return implode('|', $eventos);
    }



}
