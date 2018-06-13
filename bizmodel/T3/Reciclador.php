<?php



namespace T3;



/**
 * Class Reciclador
 *
 * @package T3
 */
class Reciclador extends Premiable
{



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('tipo',        $record, 'tipo',            'reciclador');
        $obj->fillMember('ecopuntos',   $record, 'ecopuntos',       0           );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    // NAVIGATION METHODS



    public function entregasOriginadas ($subtipo = null, $sqlWhere = null)
    {
        return parent::entregasOriginadas('entrega_reciclador_colector', $sqlWhere);
    }



    public function entregasVerificadas ($subtipo = null, $sqlWhere = null)
    {
        error_log("Reciclador: un Reciclador no puede verificar entregas");
        return null;
    }



    // PRIMITIVES METHODS



    static public function NuevoReciclador ()
    {
        $o = Reciclador::factory(true);   /** @var Reciclador $o */   // true == tell to make the ID
        if ($o === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return $o->id();
    }



    static public function RecicladorEsInvitado ($id)
    {
        $reciclador = self::DBread($id);   /** @var Reciclador $reciclador */
        if (! $reciclador) { return Log::register(__CLASS__.__LINE__.__METHOD__, true); }
        $res = $reciclador->profileFull() == 0 ? true : false;
        return $res;
        // logica inicial con sus comentarios:
        //$recicladores = self::DBread("tipo = 'reciclador' AND id = $id AND id IN (SELECT destinatario FROM invitacion)");   /** @var Reciclador[] $recicladores */
        //if ($recicladores === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        //return ! $recicladores ? false : true;
        // la logica de BB actual requiere que uInvitado sea 1 a pesar que el usuario candidato-es-decir-no-aun-reciclador este activado por propia cuenta y sin invitaciones de otro
        // esto quiere decir que hay dos tipos de usuarios en @T3_bot: recicladores (inscritos con datos completados) e invitados (incluyendo autoinvitados)
        // se pudiera implementar una discriminacion entre invitados por otros usuarios y autoinvitados
        // (pero esta implementacion (comentada) de RecicladorEsInvitado() arroja false cuando el reciclador es autoinvitado)
    }



    static public function DatosDeReciclador ($id)
    {
        return self::datosDePersonaNatural($id);
    }



    static public function RecicladoresPorNombre ($parte, $campoDeFiltro, $idParaFiltro)
    {
        return self::personasNaturalesPorNombre($parte, 'reciclador', $campoDeFiltro, $idParaFiltro);
    }



    static public function RecicladoresPorSector ($idSector)
    {
        return self::personasNaturalesPorSector($idSector, 'reciclador');
    }



    static public function RecicladoresPorAcopio ($idAcopio)
    {
        return self::personasNaturalesPorAfiliacion($idAcopio, 'reciclador', true);
    }



    static public function RecicladoresPorMetrica ($metrica, $maxEnLista)
    {
        // determinar el lapso de consulta de entregas segun la metrica especificada
        $lapso = self::sqlLapsoPorMetrica($metrica);

        // obtener datos de recicladores ordenados por ecopuntos involucrados segun lapso definido ariba segun metrica
        $query = <<<END
            SELECT r.id AS id, r.figura_legal, SUM(e.ecopuntos) AS agregado_ecopuntos
              FROM actor AS r
              JOIN transaccion AS t ON t.originador = r.id
              JOIN entrega AS e ON t.id = e.id
             WHERE r.tipo = 'reciclador'
               AND e.updated BETWEEN DATE_ADD(NOW(), INTERVAL $lapso) AND NOW()
               AND e.efectuada_en IS NOT NULL
             GROUP BY r.id, r.figura_legal 
             ORDER BY agregado_ecopuntos DESC
             LIMIT $maxEnLista;
END;
        $orden = DBbroker::query($query);
        if ($orden === false) { return Log::register(__CLASS__.__LINE__.__METHOD__); }

        // obtener coleccion de objetos reciclador
        $ids          = array_map(function ($elem) { return $elem['id']; }, $orden);
        $recicladores = self::DBread($ids);   /** @var Reciclador[] $recicladores */
        if ($recicladores === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        $ids             = array_map(function (Reciclador $o) { return $o->id(); }, $recicladores);
        $recicladoresXid = array_combine($ids, $recicladores);

        // retornar la data completa de los recicladores y sus ecopuntos, en el orden correcto
        $res = [];
        for ($i = 0; $i < count($orden); $i++) {
            list ($id, , $ecopuntos) = array_values($orden[$i]);
            $reciclador = $recicladoresXid[$id];   /** @var Reciclador $reciclador */
            $label      = ($i+1) . '. (' . $ecopuntos . 'p) ' . $reciclador->nombre() . ' ' . $reciclador->apellido();
            $res[]      = [ $reciclador->id(), $label ];
            //$res[]      = [ $reciclador->figuraLegal(), $reciclador->marca(), $reciclador->nit(), $reciclador->nombre(), $reciclador->apellido(),
            //                $reciclador->telefonoFijo(), $reciclador->telefonoCelular(), $reciclador->email(), $reciclador->nacimiento(),
            //                $reciclador->direccionCompleta(), $label, $ecopuntos ];
        }

        // ready
        return $res;
    }



    static public function HuellaVerdeReciclador ($id)
    {
        $reciclador = self::DBread($id);   /** @var Reciclador $reciclador */
        if (! $reciclador) { return Log::register(__CLASS__.__LINE__.__METHOD__, ''); }
        $co2kg = $reciclador->ecopuntos() * ECOPOINTS_TO_CO2_KG_FACTOR_RECICLADOR;
        return round($co2kg, 3);
    }



}
