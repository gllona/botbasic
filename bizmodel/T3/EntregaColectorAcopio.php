<?php



namespace T3;

use botbasic\BizModelAdapter;



/**
 * Class EntregaColectorAcopio
 *
 * @package T3
 */
class EntregaColectorAcopio extends Entrega
{



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('subtipo', $record,    'subtipo',  'entrega_colector_acopio'   );   // o: $obj->fillMember('subtipo', 'entrega_colector_acopio');

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    static public function DBread ($criterium, $factory = null)
    {
        return self::DBreadHelper(is_string($criterium) ? "subtipo = 'entrega_colector_acopio' AND ($criterium)" : $criterium, __CLASS__, $factory);
    }



    // PRIMITIVES METHODS



    static public function LotesPorEntregar ($idColector)
    {
        $lotes = Lote::DBread("colector = $idColector AND estado = 'cerrado'");   /** @var Lote[] $lotes */
        if (! $lotes && $lotes != []) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }
        $nrosLotes = [];
        foreach ($lotes as $lote) { $nrosLotes[] = $lote->numero(); }
        return implode('|', $nrosLotes);
    }



    static public function DiasCandidatosRecolecta ($idColector, $tagThis7orNext7, BizModelAdapter $bma)
    {
        $dateFormat = function (\DateTime $date)
        {
            return $date->format('d-m-Y');
        };

        self::doDummy($idColector);   // actualmente esta es una rutina generica que no toma en cuenta la identidad del colector
        $now = date_create();         // mejora: localizar fecha segun zona horaria del usuario (tambien en siguiente metodo)
        if ($tagThis7orNext7 == 'next7') { $now = date_add($now, date_interval_create_from_date_string("7 days")); }
        $days = [];
        for ($offset = 0; $offset <= 7; $offset++) {
            $date = date_add($now, date_interval_create_from_date_string("$offset days"));
            $formatted = $dateFormat($date);
            if     ($offset == 0) { $formatted .= " (" . $bma->getCommonVar('bmaHoy')     . ")"; }
            elseif ($offset == 1) { $formatted .= " (" . $bma->getCommonVar('bmaManhana') . ")"; }
            $days[] = $formatted;
        }
        return $days;
    }



    static public function HorasCandidatasRecolecta ($idColector, $dia)
    {
        $hourFormat = function ($hour)
        {
            return "$hour:00";
        };
        $hourParts = function (\DateTime $date)
        {
            return explode(':', $date->format('H:i:s'));
        };
        $dateParts = function (\DateTime $date)
        {
            return explode('-', $date->format('Y-m-d'));
        };
        $datePartsStr = function ($dateStr)
        {
            if (($pos = strpos($dateStr, ' ')) !== false) { $dateStr = substr($dateStr, 0, $pos); }
            return array_reverse(explode('-', $dateStr));
        };

        self::doDummy($idColector);   // actualmente esta es una rutina generica que no toma en cuenta la identidad del colector
        list ($yyyyD, $mmD, $ddD) = $datePartsStr($dia);
        list ($yyyyN, $mmN, $ddN) = $dateParts(date_create());

        // si no se trata del dia actual entonces la lista de horas se genera a partir de la primera hora habil
        if ("$yyyyD-$mmD-$ddD" != "$yyyyN-$mmN-$ddN") {
            $startAt = DAILY_START_FOR_CAMION_HH;
        }

        // pero si es el dia actual la lista se genera a partir de una hora prudencial marcada por un threshold desde el momento actual
        else {
            $base = date_create();
            $base = date_add($base, date_interval_create_from_date_string(MIN_OFFSET_FROM_NOW_FOR_CAMION_ARRIVAL_MM . " minutes"));
            list ($hourB, , ) = $hourParts($base);
            if ($hourB > DAILY_END_FOR_CAMION_HH) { return [];         }   // no hay horas candidatas
            else                                     { $startAt = $hourB; }
        }

        $endAt = DAILY_END_FOR_CAMION_HH;
        $hours = [];
        for ($hour = $startAt; $hour <= $endAt; $hour++) { $hours[] = $hourFormat($hour); }
        return $hours;
    }



    static public function PesoDeLotes ($nrosLotesPiped)
    {
        $nrosLotes = explode('|', $nrosLotesPiped);
        if (count($nrosLotes) == 0) { return [ 0 ]; }
        $sqlNrosLotes = "'" . implode("', '", $nrosLotes) . "'";
        $query = <<<END
            SELECT SUM(peso) AS sumpeso
              FROM lote
             WHERE numero IN ($sqlNrosLotes);
END;
        $rows = DBbroker::query($query);
        if ($rows === false) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        $peso = $rows[0]['sumpeso'];
        return $peso;
    }



    static public function AgendarRecolecta ($idColector, $nrosLotesPiped, $momento, $idCamion)
    {
        $sqlDateTime = function ($dia, $hora)
        {
            list ($dd, $mm, $yyyy) = explode('-', $dia);
            return "$yyyy-$mm-$dd $hora";
        };

        list ($dia, $hora) = explode(' @ ', $momento);
        $sqlMomento = $sqlDateTime($dia, $hora);

        // cargar colector y lotes
        if (($colector = Colector::DBread($idColector)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, [ 0 ]); }   /** @var Colector $colector */
        $nrosLotes = explode('|', $nrosLotesPiped);
        if (count($nrosLotes) == 0) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        $lotes = Lote::DBread("numero IN (" . implode(', ', $nrosLotes) . ")");   /** @var Lote[] $lotes */
        if (! $lotes && $lotes != []) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }

        // para cada lote que este cerrado (deberian ser todos los recibidos como argumento) crear una entrega indicando momento programado
        foreach ($lotes as $lote) {
            if ($lote->estado() != 'cerrado') { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            if (! ($entrega = self::factory())) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var EntregaColectorAcopio $entrega */
            $entrega->originador($idColector);
            $entrega->verificador($colector->afiliadoA(true));
            $entrega->lote($lote->id());
            $entrega->camion($idCamion);
            $entrega->programadaPara($sqlMomento);
        }
    }



    static public function RecolectasAgendadas ($idColector)
    {
        $entregas = self::DBread("originador = $idColector AND efectuada_en IS NULL");   /** @var EntregaColectorAcopio[] $entregas */
        if (! $entregas && $entregas != []) { return Log::register(__CLASS__.__LINE__.__METHOD__); }

        // generar textos de entregas indexadas por momento; se indican con (el comienzo de la) direccion donde buscar pues esto va dirigido al bot del camion
        $eStrXm = [];
        foreach ($entregas as $entrega) {
            $momento  = $entrega->programadaPara();
            if (! ($colector = $entrega->originador())) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
            $text = "$momento @ " . $colector->direccion();
            if     (isset($eStrXm[$momento]) && $eStrXm[$momento] != $text) { Log::register(__CLASS__.__LINE__.__METHOD__ . ": mas de una recolecta agendada para el mismo momento [$idColector, $text]"); continue; }
            elseif (isset($eStrXm[$momento]))                               { continue; }   // la recolecta ya fue ingresada en $res cuando se proceso otro lote
            $eStrXm[$momento] = $text;
        }

        // convertir a lo esperado para asignar a OPTIONS
        $res = [];
        foreach ($eStrXm as $momento => $text) { $res[] = [ $momento, $text ]; }
        return $res;
    }



    static public function DatosDeRecolecta ($idColector, $momento)
    {
        $entregas = self::DBread("originador = $idColector AND momento = '$momento' AND efectuada_en IS NULL");   /** @var EntregaColectorAcopio[] $entregas */
        if (! $entregas && $entregas != []) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        $nrosLotes = [];
        $kgs       = 0;
        foreach ($entregas as $entrega) {
            if (! ($lote = $entrega->lote())) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
            $nrosLotes[] = $lote->numero();
            $kgs        += $lote->peso();
        }
        return [ implode('|', $nrosLotes), $kgs ];
    }



    static public function RealizarRecolecta ($idColector, $momento)
    {
        $sqlDate = function (\DateTime $date)
        {
            return $date->format('Y-m-d H:i:s');
        };

        // cargar objetos
        if (! ($colector = Colector::DBread($idColector))) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Colector $colector                */
        $entregas = self::DBread("originador = $idColector AND momento = '$momento' AND efectuada_en IS NULL");              /** @var EntregaColectorAcopio[] $entregas */
        if (! $entregas && $entregas != []) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }

        // para cada entrega asignar fecha de ejecucion, ecopuntos (calculados segun los del lote) y ademas asignar el destino del lote
        $now = date_create();
        foreach ($entregas as $entrega) {
            if (! ($lote = $entrega->lote())) { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            $entrega->efectuadaEn($sqlDate($now));
            $entrega->ecopuntos($lote->ecopuntos());
            $lote->acopio($colector->afiliadoA(true));
        }

        // ready
        return true;
    }



    static protected function doDummy ($something) { return null; }

}
