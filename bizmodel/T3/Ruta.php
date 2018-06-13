<?php



namespace T3;



/**
 * Class Ruta
 *
 * @method string               tipo            (bool|string            $arg = null)
 * @method int|Actor|null       camion          (bool|Actor|null        $arg = null)
 * @method int|Geosector|null   sector          (bool|Geosector|null    $arg = null)
 *
 * @package T3
 */
class Ruta extends Persistable
{



    const SOFT_DELETIONS = false;



    static protected function membersList ()
    {
        return [
            'id',
            [ 'camion',         'camion'        ],
            [ 'sector',         'sector'        ],
        ];
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('id',     $record, 'id'                                                                );
        $obj->fillMember('camion', $record, 'camion',   ! isset($values['camion']) ? null : $values['camion']   );
        $obj->fillMember('sector', $record, 'sector',   ! isset($values['sector']) ? null : $values['sector']   );

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
        return self::DBwriteHelper(/** @var Ruta $obj */ $obj, [
            'camion'         => $obj->camion(true),
            'sector'         => $obj->sector(true),
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



    static public function query ($camiones, $sectores, $sqlWhereAnd = null, $fieldToDerive = null, $loadWhenDeriving = true)
    {
        return self::DBquery(__CLASS__, [
                'camion'        => $camiones,
                'sector'        => $sectores,
            ], "TRUE" . ($sqlWhereAnd === null ? '' : " AND ($sqlWhereAnd)"), $fieldToDerive, $loadWhenDeriving
        );
    }



}
