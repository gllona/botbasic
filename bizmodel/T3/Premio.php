<?php



namespace T3;



/**
 * Class Premio
 *
 * @method string|null              nombre          (bool|string|null       $arg = null)
 * @method int|SponsorCorp|null     provistoPor     (bool|SponsorCorp|null  $arg = null)
 * @method float                epPorUnidad     (bool|float             $arg = null)
 *
 * @package T3
 */
class Premio extends Persistable
{



    const SOFT_DELETIONS = true;



    static protected function membersList ()
    {
        return [
            'id',
            'nombre',
            [ 'provistoPor',  'SponsorCorp' ],
            'epPorUnidad',
        ];
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('id',              $record, 'id'                           );
        $obj->fillMember('nombre',          $record, 'nombre',          'unnamed'   );
        $obj->fillMember('epPorUnidad',     $record, 'ep_por_unidad',   1           );

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
        return self::DBwriteHelper(/** @var Premio $obj */ $obj, [
            'nombre'        => DBbroker::q($obj->nombre()),
            'epPorUnidad'   => $obj->epPorUnidad(),
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



    public function aclamadoPor ($load = false, $sqlWhereAnd = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Aclamacion::query(null, 'premio', null, $this, null, $sqlWhereAnd, 'aclamante', $load);
    }



    public function canjes ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Canje::DBread("premio = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function donacionesDeEstaEspecie ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return DonacionPremio::DBread("premio = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    // PRIMITIVES METHODS



    static public function Premios ()
    {
        $items = self::DBread(true);
        if     ($items === null)    { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        elseif ($items === false)   { return []; }
        return array_map(function (Premio $item) { return [ $item->id(), $item->nombre() ]; }, $items);
    }



}
