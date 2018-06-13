<?php



namespace T3;



/**
 * Class SponsorCorp
 *
 * @package T3
 */
class SponsorCorp extends Actor
{



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('tipo',        $record, 'tipo',            'sponsor_corp'      );
        $obj->fillMember('tipoPersona', $record, 'tipo_persona',    'juridica'          );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    // NAVIGATION METHODS



    public function retails ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return SponsorRetail::DBread("afiliado_a = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function aclamadoPor ($load = false, $sqlWhereAnd = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Aclamacion::query(null, 'sponsor', $this, null, null, $sqlWhereAnd, 'aclamante', $load);
    }



    // PRIMITIVES METHODS



    static public function ValidarPIN ($pin)
    {
        return self::actorVerificarPIN('sponsor_corp', $pin);
    }



    static public function DatosDeSponsor ($id)
    {
        return self::datosDePersonaJuridica($id);
    }



    static public function SponsorDePremio ($idPremio)
    {
        $premio = Premio::DBread($idPremio);   /** @var Premio $premio */
        if (! $premio)  { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return $premio->provistoPor(true);
    }

    

    static public function SponsorsPorRetail ($idRetail)
    {
        $retails = SponsorRetail::retailsCongruentes($idRetail);
        if ($retails == []) { return []; }
        $idsSponsors = array_map(function (SponsorRetail $o) { return $o->afiliadoA(true); }, $retails);
        $sponsors    = self::DBread($idsSponsors);
        if ($sponsors === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return array_map(function (SponsorCorp $o) { return [ $o->id(), $o->figuraLegal() ]; }, $sponsors);
    }



    static public function SponsorsPorCiudad ($idCiudad)
    {
        return self::personasJuridicasPorSector($idCiudad, 'sponsor_corp', true);
    }



    static public function SponsorsPorMetrica ($metrica, $maxEnLista)
    {
        // determinar el lapso de consulta de Entregas segun la metrica especificada
        $lapso = self::sqlLapsoPorMetrica($metrica);

        // obtener datos de SponsorCorps ordenados por ecopuntos involucrados segun lapso definido ariba segun metrica
        $query = <<<END
            SELECT s.id, s.figura_legal, SUM(c.ecopuntos) AS agregado_ecopuntos
              FROM actor AS e
              JOIN actor AS s ON e.afiliado_a = s.id
              JOIN transaccion AS t ON t.verificador = e.id
              JOIN canje AS c ON t.id = c.id
             WHERE e.tipo = 'sponsor_retail'
               AND c.updated BETWEEN DATE_SUB(NOW(), INTERVAL $lapso) AND NOW()
             GROUP BY s.id, s.figura_legal 
             ORDER BY agregado_ecopuntos DESC
             LIMIT $maxEnLista;
END;
        $orden = DBbroker::query($query);
        if ($orden === false) { return Log::register(__CLASS__.__LINE__.__METHOD__); }

        // obtener coleccion de objetos SponsorCorp
        $ids      = array_map(function ($elem) { return $elem['id']; }, $orden);
        $sponsors = self::DBread($ids);   /** @var SponsorCorp[] $sponsors */
        if (! $sponsors) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        $ids        = array_map(function (SponsorRetail $o) { return $o->id(); }, $sponsors);
        $sponsorXid = array_combine($ids, $sponsors);

        // retornar la data completa de los SponsorCorps y sus ecopuntos, en el orden correcto
        $res = [];
        for ($i = 0; $i < count($orden); $i++) {
            list ($id, , $ecopuntos) = array_values($orden[$i]);
            $sponsor = $sponsorXid[$id];   /** @var SponsorCorp $sponsor */
            $label   = ($i+1) . '. (' . $ecopuntos . 'p) ' . $sponsor->figuraLegal();
            $res[]   = [ $sponsor->id(), $label ];
            //$res[]   = [ $sponsor->figuraLegal(), $sponsor->marca(), $sponsor->nit(), $sponsor->nombre(), $sponsor->apellido(),
            //             $sponsor->telefonoFijo(), $sponsor->telefonoCelular(), $sponsor->email(), $sponsor->nacimiento(),
            //             $sponsor->direccionCompleta(), $label, $ecopuntos ];
        }
        return $res;
    }



}
