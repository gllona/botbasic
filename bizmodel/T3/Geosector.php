<?php



namespace T3;



/**
 * Class Geosector
 *
 * @method string               nivel           (bool|string            $arg = null)
 * @method string|null          nombre          (bool|string|null       $arg = null)
 * @method int|Geosector|null   adscritoA       (bool|Geosector|null    $arg = null)
 *
 * @package T3
 */
class Geosector extends Persistable
{



    const SOFT_DELETIONS = true;



    static protected function membersList ()
    {
        return [
            'id',
            'nivel',
            'nombre',
            [ 'adscritoA',  'Geosector' ],
        ];
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('id',              $record, 'id'                       );
        $obj->fillMember('nivel',           $record, 'nivel',       'punto'     );
        $obj->fillMember('nombre',          $record, 'nombre',      'unnamed'   );
        $obj->fillMember('adscritoA',       $record, 'adscrito_a'               );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    static public function DBread ($criterium, $factory = null)
    {
        return self::DBreadHelper($criterium, __CLASS__, $factory);
    }



    static protected function DBwrite ($obj, $innerCall = false)
    {
        return self::DBwriteHelper(/** @var Geosector $obj */ $obj, [
            'nivel'              => DBbroker::q($obj->nivel()),
            'nombre'             => DBbroker::q($obj->nombre()),
            'adscritoA'          => DBbroker::q($obj->adscritoA(true)),
        ], $innerCall, __CLASS__);
    }



    static protected function DBdelete ($obj, $innerCall = false)
    {
        return self::DBdeleteHelper($obj, $innerCall, __CLASS__);
    }



    static protected function DBsoftDeletions ()
    {
        return self::SOFT_DELETIONS;
    }



    // NAVIGATION METHODS



    public function subsectores ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Geosector::DBread("adscrito_a = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function actoresUbicadosAqui ($nivel = null, $sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Actor::DBread(($nivel === null ? '' : "nivel = '$nivel' AND ") . "ubicacion = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function camionesQueAtienden ($load = true, $sqlWhereAnd = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Ruta::query(null, $this, $sqlWhereAnd, 'camion', $load);
    }



    // UTILITY METHODS



    static private function normalizePt ($pt)
    {
        if (is_array($pt)) {
            $pts = $pt;
        }
        elseif (preg_replace('/[^0-9.,-]/', '', $pt) == $pt) {
            $pts = explode(',', $pt);
        }
        else {
            $coords = explode('+', $pt);
            $pts    = [];
            foreach ($coords as $coord) {
                $matches = [];
                if (false !== preg_match('/^([0-9]+)°([0-9]+)\'([0-9]+\.[0-9]+)"([NSEW])$/', $coord, $matches)) {
                    list (, $grados, $minutos, $segundos, $cuadrante) = $matches;
                    $signo = ($cuadrante == 'N' || $cuadrante == 'E') ? +1 : -1;
                    $pts[] = $signo * (round($grados + $minutos*(100/60)/100 + $segundos*(100/60)/10000, 8));
                }
            }
        }
        if (count($pts) != 2) { $pts = [ 0, 0 ]; }
        return $pts;
    }



    static public function geodiff ($pt1, $pt2)
    {
        $pt1 = self::normalizePt($pt1);
        $pt2 = self::normalizePt($pt2);
        /*
        // forma simple, teorema de pitagoras (no toma en cuenta la latitud y no convierte a metros)
        list ($y1, $x1) = $pt1;
        list ($y2, $x2) = $pt2;
        $h = sqrt( pow(($y1 - $y2), 2) + pow(($x1 - $x2), 2) );
        return $h;
        */
        // forma final (resultado en metros) tomada de:
        // - http://stackoverflow.com/questions/639695/how-to-convert-latitude-or-longitude-to-meters
        // - https://en.wikipedia.org/wiki/Geographic_coordinate_system
        // - https://en.wikipedia.org/wiki/Universal_Transverse_Mercator_coordinate_system
        $toRads = function (&$a) { $a *= /* PI is here */ 3.14159265359 / 180; };
        if (! is_array($pt1) || ! is_array($pt2)) { return 40e6; }   // circunferencia planetaria
        list ($lat1, $lon1) = $pt1;
        list ($lat2, $lon2) = $pt2;
        $toRads($lon1); $toRads($lon2); $toRads($lat1); $toRads($lat2);
        $latMid        = ($lat1 + $lat2) / 2.0;
        $m_per_deg_lat = 111132.92                - 559.82 * cos(2.0 * $latMid) + 1.175 * cos(4.0 * $latMid) - 0.0023 * cos(6.0 * $latMid);
        $m_per_deg_lon = 111412.84 * cos($latMid) -  93.5  * cos(3.0 * $latMid) + 0.118 * cos(5.0 * $latMid);
        $deltaLat      = abs($lat1 - $lat2);
        $deltaLon      = abs($lon1 - $lon2);
        $dist_m = sqrt( pow($deltaLat * $m_per_deg_lat, 2) + pow($deltaLon * $m_per_deg_lon, 2) );
        return $dist_m;
    }



    // PRIMITIVES METHODS



    static public function PaisesPorLetraReino ($inicialPais)
    {
        $items = self::DBread("nivel = 'pais' AND UCASE(nombre) LIKE " . DBbroker::q(strtoupper("$inicialPais%")));
        if ($items === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return array_map(function (Geosector $item) { return [ $item->id(), $item->nombre() ]; }, $items);
    }



    static public function CiudadesPorPais ($idPais)
    {
        $items = self::DBread("nivel = 'ciudad' AND adscrito_a = $idPais");
        // solo funciona para estructura pais -> ciudad; no para pais -> provincia -> ciudad; para habilitar:
        // $items2 = self::DBread("nivel = 'ciudad' AND adscrito_a IN (SELECT id FROM geosector WHERE nivel = 'provincia' AND adscrito_a = $idPais)");
        // luego: merge, sort, uniq
        if ($items === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return array_map(function (Geosector $item) { return [ $item->id(), $item->nombre() ]; }, $items);
    }



    static public function SectoresPorCiudad ($idCiudad)
    {
        $items = self::DBread("nivel = 'sector' AND adscrito_a = $idCiudad");
        if ($items === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return array_map(function (Geosector $item) { return [ $item->id(), $item->nombre() ]; }, $items);
    }



}
