<?php



namespace T3;



/**
 * Class Lote
 *
 * @method string               tipoMaterial    (bool|string            $arg = null)
 * @method int|Colector         colector        (bool|Colector          $arg = null)
 * @method int|Acopio|null      acopio          (bool|Acopio|null       $arg = null)
 * @method int                  numero          (bool|int               $arg = null)
 * @method float                peso            (bool|float             $arg = null)
 * @method float                ecopuntos       (bool|float             $arg = null)
 * @method string               estado          (bool|string            $arg = null)
 *
 * @package T3
 */
abstract class Lote extends Persistable
{



    const SOFT_DELETIONS = false;



    static protected function membersList ()
    {
        return array_merge(
            parent::membersList(),
            [
                'tipoMaterial',
                [ 'colector',   'Colector'  ],
                [ 'acopio',     'Acopio'    ],
                'numero',
                'peso',
                'ecopuntos',
                'estado',
            ]
        );
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('tipoMaterial', $record, 'tipo_material'                                                                             );
        $obj->fillMember('colector',     $record, 'colector',     ! isset($values['colector']) ? null                  : $values['colector']  );
        $obj->fillMember('acopio',       $record, 'acopio',       ! isset($values['acopio'])   ? null                  : $values['acopio']    );
        $obj->fillMember('numero',       $record, 'numero',       0                                                                           );
        $obj->fillMember('peso',         $record, 'peso',         0                                                                           );
        $obj->fillMember('ecopuntos',    $record, 'ecopuntos',    0                                                                           );
        $obj->fillMember('estado',       $record, 'estado',       'abierto'                                                                   );

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
        return self::DBwriteHelper(/** @var Lote $obj */ $obj, [
            'tipoMaterial'       => DBbroker::q($obj->tipoMaterial()),
            'colector'           => $obj->colector(true),
            'acopio'             => DBbroker::q($obj->acopio(true)),
            'numero'             => $obj->numero(),
            'peso'               => $obj->peso(),
            'ecopuntos'          => $obj->ecopuntos(),
            'estado'             => DBbroker::q($obj->estado()),
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



    // TOOLBOX METHODS



    public function save ()
    {
        self::DBwrite($this);
    }



    static public function nuevoNumero ($idColector)
    {
        $c = Colector::DBread($idColector);   /** @var Colector $c */
        if (! $c) { return Log::register(__CLASS__.__LINE__.__METHOD__, 0); }
        $query = <<<END
            SELECT MAX(numero) AS currentmax
              FROM lote
             WHERE colector = $idColector;
END;
        $rows = DBbroker::query($query);
        if ($rows === false) { return Log::register(__CLASS__.__LINE__.__METHOD__, 0); }
        $nuevoNumero = $rows[0]['currentmax'] + 1;
        return $nuevoNumero;
    }



    static public function cierreAutomatico ($idColector)
    {
        // cargar objetos
        $c = Colector::DBread($idColector);   /** @var Colector $c */
        if (! $c) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }
        $ciudad = $c->ubicacion();
        if ($ciudad->nivel() == 'sector') { $ciudad = $ciudad->adscritoA(); }
        $idCiudad = $ciudad->id();

        // obtener el limite (threshold minimo) aplicable al par colector-acopio y la suma de kg actualmente acumulada en los lotes abiertos
        $query = <<<END
            SELECT MAX(lt.limite) AS maxlimite, SUM(l.peso) AS sumpeso 
              FROM lote_threshold AS lt
              JOIN lote AS l ON lt.tipo_material = l.tipo_material
             WHERE lt.ciudad = $idCiudad
               AND l.colector = $idColector
               AND l.estado = 'abierto';
END;
        $rows = DBbroker::query($query);
        if (! $rows) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }
        list ($maxLimite, $sumPeso) = array_values($rows[0]);

        // no habra lotes a cerrar si no se excede el threshold obtenido
        if ($sumPeso < $maxLimite) { return []; }

        // buscar los lotes a cerrar: la menor cantidad posible que satisfaga un peso superior al threshold obtenido
        $query = <<<END
            SELECT id, peso
              FROM lote
             WHERE colector = $idColector
               AND estado = 'abierto'
             ORDER BY peso DESC; 
END;
        $rows = DBbroker::query($query);
        if (! $rows && $rows != []) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }
        $idsLotes = [];
        $peso     = 0;
        foreach ($rows as $row) {
            $idsLotes[] = $row['id'];
            $peso      += $row['peso'];
            if ($peso >= $maxLimite) { break; }
        }
        if ($peso < $maxLimite) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }   // guard vs mal funcionamiento del 1er query de este metodo

        // marcar los lotes identicados como cerrados
        foreach ($idsLotes as $idLote) {
            $lote = Lote::DBread($idLote);   /** @var Lote $lote */
            if (! $lote) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }
            $lote->estado('cerrado');
        }

        // ready
        return $idsLotes;
    }



}
