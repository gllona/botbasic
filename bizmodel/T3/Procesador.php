<?php



namespace T3;



/**
 * Class Procesador
 *
 * @method string               tipo            (bool|string            $arg = null)
 * @method int|Actor|null       acopio          (bool|Actor|null        $arg = null)
 * @method int|Material|null    material        (bool|Material|null     $arg = null)
 * @method float                capacidadMesKg  (float                  $arg = null)
 *
 * @package T3
 */
class Procesador extends Persistable
{



    const SOFT_DELETIONS = false;



    static protected function membersList ()
    {
        return [
            'id',
            [ 'acopio',         'acopio'        ],
            [ 'material',       'material'      ],
            'capacidadMesKg',
        ];
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('id',              $record, 'id'                                                                                           );
        $obj->fillMember('acopio',          $record, 'acopio',              ! isset($values['acopio'])         ? null : $values['acopio']           );
        $obj->fillMember('material',        $record, 'material',            ! isset($values['material'])       ? null : $values['material']         );
        $obj->fillMember('capacidadMesKg',  $record, 'capacidad_mes_kg',    ! isset($values['capacidadMesKg']) ? 0    : $values['capacidadMesKg']   );

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
        return self::DBwriteHelper(/** @var Procesador $obj */ $obj, [
            'acopio'         => $obj->acopio(true),
            'material'       => $obj->material(true),
            'capacidadMesKg' => $obj->capacidadMesKg(),
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



    static public function query ($acopios, $materiales, $sqlWhereAnd = null, $fieldToDerive = null, $loadWhenDeriving = true)
    {
        return self::DBquery(__CLASS__, [
                'acopio'        => $acopios,
                'material'      => $materiales,
            ], "TRUE" . ($sqlWhereAnd === null ? '' : " AND ($sqlWhereAnd)"), $fieldToDerive, $loadWhenDeriving
        );
    }



}
