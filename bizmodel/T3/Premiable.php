<?php



namespace T3;



/**
 * Class Premiable
 *
 * @package T3
 */
abstract class Premiable extends Vector
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



    public function sponsorsAclamados ($load = true, $sqlWhereAnd = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Aclamacion::query($this, 'sponsor', null, null, null, $sqlWhereAnd, 'sponsor', $load);
    }



    public function premiosAclamados ($load = true, $sqlWhereAnd = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Aclamacion::query($this, 'premio', null, null, null, $sqlWhereAnd, 'premio', $load);
    }



    public function canjesOriginados ($sqlWhere = null)
    {
        return $this->transaccionesOriginadas('canje', $sqlWhere);
    }



}
