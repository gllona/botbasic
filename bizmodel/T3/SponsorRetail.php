<?php



namespace T3;



/**
 * Class SponsorRetail
 *
 * @package T3
 */
class SponsorRetail extends Subsidiario
{



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('tipo',        $record, 'tipo',            'sponsor_retail'    );
        $obj->fillMember('tipoPersona', $record, 'tipo_persona',    'juridica'          );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    // NAVIGATION METHODS



    public function canjesVerificados ($sqlWhere = null)
    {
        return $this->transaccionesVerificadas('canje', $sqlWhere);
    }



    static public function retailsCongruentes ($idRetail)
    {
        // obtener el objeto SponsorRetail
        $retail = self::DBread($idRetail);   /** @var SponsorRetail $retail */
        if     ($retail === null)  { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        elseif ($retail === false) { return []; }

        // obtener todos los SponsorRetails registrados que compartan la ubicacion (sector de la ciudad) y tengan el mismo NIT (superset de las redundancias)
        $candidates = self::DBread("tipo = 'sponsor_retail' AND nit = '" . $retail->nit() . "' and ubicacion = " . $retail->ubicacion());   /** @var SponsorRetail[] $candidates */
        if     ($candidates === null)  { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        elseif ($candidates === false) { return []; }

        // filtrar el superset y obtener el subset, por medio de las coordenadas geograficas de los SponsorRetails
        $retails = [];
        foreach ($candidates as $candidate) {
            $diff = Geosector::geodiff($retail->geolocalizacion(), $candidate->geolocalizacion());
            if ($diff === null || $diff > GEODIFF_THRESHOLD_MTS) { continue; }
            $retails[] = $candidate;
        }

        // ready
        return $retails;
    }



    // PRIMITIVES METHODS



    static public function ValidarPIN ($pin)
    {
        return self::actorVerificarPIN('sponsor_retail', $pin);
    }



    static public function DatosDeRetail ($id)
    {
        return self::datosDePersonaJuridica($id);
    }



    static public function RetailsPorPremio ($idPremio)
    {
        $premio = Premio::DBread($idPremio);   /** @var Premio $premio */
        if (! $premio) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return self::RetailsPorSponsor($premio->provistoPor(true));
    }



    static public function RetailsPorSponsor ($idSponsor)
    {
        $sponsor = SponsorCorp::DBread($idSponsor);   /** @var SponsorCorp $sponsor */
        if (! $sponsor) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        $retails = $sponsor->retails();
        if ($retails === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return array_map(function (SponsorRetail $o) { return [ $o->id(), $o->figuraLegal() ]; }, $retails);
    }



    static public function RetailsPorCiudad ($idCiudad)
    {
        return self::personasJuridicasPorSector($idCiudad, 'sponsor_retail', true);
    }



    static public function RetailsPorMetrica ($metrica, $maxEnLista)
    {
        // determinar el lapso de consulta de Entregas segun la metrica especificada
        $lapso = self::sqlLapsoPorMetrica($metrica);

        // obtener datos de SponsorRetails ordenados por ecopuntos involucrados segun lapso definido ariba segun metrica
        $query = <<<END
            SELECT e.id, e.figura_legal, SUM(c.ecopuntos) AS agregado_ecopuntos
              FROM actor AS e
              JOIN transaccion AS t ON t.verificador = e.id
              JOIN canje AS c ON t.id = c.id
             WHERE e.tipo = 'sponsor_retail'
               AND c.updated BETWEEN DATE_SUB(NOW(), INTERVAL $lapso) AND NOW()
             GROUP BY e.id, e.figura_legal 
             ORDER BY agregado_ecopuntos DESC
             LIMIT $maxEnLista;
END;
        $orden = DBbroker::query($query);
        if ($orden === false) { return Log::register(__CLASS__.__LINE__.__METHOD__); }

        // obtener coleccion de objetos SponsorRetail
        $ids     = array_map(function ($elem) { return $elem['id']; }, $orden);
        $retails = self::DBread($ids);   /** @var SponsorRetail[] $retails */
        if ($retails === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        $ids       = array_map(function (SponsorRetail $o) { return $o->id(); }, $retails);
        $retailXid = array_combine($ids, $retails);

        // retornar la data completa de los SponsorRetails y sus ecopuntos, en el orden correcto
        $res = [];
        for ($i = 0; $i < count($orden); $i++) {
            list ($id, , $ecopuntos) = array_values($orden[$i]);
            $retail = $retailXid[$id];   /** @var SponsorRetail $retail */
            $label  = ($i+1) . '. (' . $ecopuntos . 'p) ' . $retail->figuraLegal();
            $res[]  = [ $retail->id(), $label ];
            //$res[]  = [ $retail->figuraLegal(), $retail->marca(), $retail->nit(), $retail->nombre(), $retail->apellido(),
            //            $retail->telefonoFijo(), $retail->telefonoCelular(), $retail->email(), $retail->nacimiento(),
            //            $retail->direccionCompleta(), $label, $ecopuntos ];
        }
        return $res;
    }



}
