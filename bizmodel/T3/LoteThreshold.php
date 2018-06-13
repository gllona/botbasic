<?php



namespace T3;



/**
 * Class LoteThreshold
 *
 * @method string               tipoMaterial    (bool|string            $arg = null)
 * @method int|Geosector        ciudad          (bool|Geosector         $arg = null)
 * @method float                limite          (bool|float             $arg = null)
 *
 * @package T3
 */
abstract class LoteThreshold extends Persistable
{



    const SOFT_DELETIONS = false;



    static protected function membersList ()
    {
        return array_merge(
            parent::membersList(),
            [
                'tipoMaterial',
                [ 'ciudad',     'Geosector' ],
                'limite',
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
        $obj->fillMember('ciudad',       $record, 'ciudad',       ! isset($values['ciudad'])   ? null                  : $values['ciudad']    );
        $obj->fillMember('limite',       $record, 'limite'                                                                                    );

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
        return self::DBwriteHelper(/** @var LoteThreshold $obj */ $obj, [
            'tipoMaterial'       => DBbroker::q($obj->tipoMaterial()),
            'ciudad'             => $obj->ciudad(true),
            'limite'             => $obj->limite(),
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



}
