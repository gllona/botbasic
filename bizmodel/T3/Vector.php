<?php



namespace T3;



/**
 * Class Vector
 *
 * @package T3
 */
abstract class Vector extends Actor
{



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        // (none)

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    // NAVIGATION METHODS



    public function materialesAclamados ($load = true, $sqlWhereAnd = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Aclamacion::query($this, 'material', null, null, null, $sqlWhereAnd, 'material', $load);
    }



    public function entregasOriginadas ($subtipo = null, $sqlWhere = null)
    {
        return $this->transaccionesOriginadas('entrega', ($subtipo === null ? "TRUE" : "subtipo = '$subtipo'") . " AND " . ($sqlWhere === null ? "TRUE" : "($sqlWhere)"));
    }



    public function entregasVerificadas ($subtipo = null, $sqlWhere = null)
    {
        return $this->transaccionesVerificadas('entrega', ($subtipo === null ? "TRUE" : "subtipo = '$subtipo'") . " AND " . ($sqlWhere === null ? "TRUE" : "($sqlWhere)"));
    }



}
