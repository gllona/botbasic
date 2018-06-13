<?php



namespace T3;



/**
 * Class Material
 *
 * @method string               tipo            (bool|string            $arg = null)
 * @method string|null          nombre          (bool|string|null       $arg = null)
 * @method float                epPorUnidad     (bool|float             $arg = null)
 * @method float                pesoPorUnidad   (bool|float             $arg = null)
 *
 * @package T3
 */
class Material extends Persistable
{



    const SOFT_DELETIONS = true;



    static protected function membersList ()
    {
        return [
            'id',
            'tipo',
            'nombre',
            'epPorUnidad',
            'pesoPorUnidad',
        ];
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('id',              $record, 'id'                           );
        $obj->fillMember('tipo',            $record, 'tipo',            'extras'    );
        $obj->fillMember('nombre',          $record, 'nombre',          'unnamed'   );
        $obj->fillMember('epPorUnidad',     $record, 'ep_por_unidad',   1           );
        $obj->fillMember('pesoPorUnidad',   $record, 'ep_por_unidad'                );

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
        return self::DBwriteHelper(/** @var Material $obj */ $obj, [
            'tipo'          => DBbroker::q($obj->tipo()),
            'nombre'        => DBbroker::q($obj->nombre()),
            'epPorUnidad'   => $obj->epPorUnidad(),
            'pesoPorUnidad' => $obj->pesoPorUnidad(),
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



    public function acopiosQueProcesan ($load = false, $sqlWhereAnd = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Procesador::query(null, $this, $sqlWhereAnd, 'acopio', $load);
    }



    public function aclamadoPor ($load = false, $sqlWhereAnd = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Aclamacion::query(null, 'material', null, null, $this, $sqlWhereAnd, 'aclamante', $load);
    }



    public function entregas ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Entrega::DBread("material = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    // TOOLBOX METHODS



    static public function calcularEcopuntos ($idMaterial, $cantidad)
    {
        $m = Material::DBread($idMaterial);   /** @var Material $m */
        if (! $m) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }
        $epBasePorUnidad           = $m->epPorUnidad();
        $multiplicador             = 1;   // podria calcularse los ecopuntos por tipo y subtipo de material tomando quiza en cuenta un factor a partir de la ciudad del reciclador/colector
        $epDeColectorXdeReciclador = EP_DE_COLECTOR_POR_DE_RECICLADOR;
        $epReciclador              = $epBasePorUnidad * $cantidad * $multiplicador;
        $epColector                = $epReciclador * $epDeColectorXdeReciclador;
        return [ $epReciclador, $epColector ];
    }



    // PRIMITIVES METHODS



    static public function Materiales ()
    {
        $items = self::DBread(true);
        if     ($items === null)    { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        elseif ($items === false)   { return []; }
        elseif (! is_array($items)) { $items = [ $items ]; }
        return array_map(function (Material $item) { return [ $item->id(), $item->nombre() ]; }, $items);
    }



    static public function TipoMaterial ($id)
    {
        $o = self::DBread(true);   /* @var Material $o */
        if     ($o === null)  { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        elseif ($o === false) { return []; }
        return $o->tipo();
    }



}
