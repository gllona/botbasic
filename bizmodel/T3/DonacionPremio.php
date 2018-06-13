<?php



namespace T3;



/**
 * Class DonacionPremio
 *
 * @package T3
 */
class DonacionPremio extends Donacion
{



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('subtipo', $record,    'subtipo',  'donacion_premio'   );   // o: $obj->fillMember('subtipo', 'donacion_premio');

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    static public function DBread ($criterium, $factory = null)
    {
        return self::DBreadHelper(is_string($criterium) ? "subtipo = 'donacion_premio' AND ($criterium)" : $criterium, __CLASS__, $factory);
    }



}
