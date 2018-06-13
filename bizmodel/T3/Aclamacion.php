<?php



namespace T3;



/**
 * Class Aclamacion
 *
 * @method string               tipo            (bool|string            $arg = null)
 * @method int|Actor|null       aclamante       (bool|Actor|null        $arg = null)
 * @method int|Actor|null       acopio          (bool|Actor|null        $arg = null)
 * @method int|Premio|null      premio          (bool|Premio|null       $arg = null)
 * @method int|Material|null    material        (bool|Material|null     $arg = null)
 *
 * @package T3
 */
class Aclamacion extends Persistable
{



    const SOFT_DELETIONS = false;



    static protected function membersList ()
    {
        return [
            [ 'aclamante',  'Actor'     ],
            [ 'sponsor',    'Actor'     ],
            [ 'premio',     'Premio'    ],
            [ 'material',   'Material'  ],
        ];
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('id',              $record, 'id'                                                                       );
        $obj->fillMember('aclamante',       $record, 'aclamante',   ! isset($values['aclamante']) ? null : $values['aclamante'] );
        $obj->fillMember('acopio',          $record, 'acopio',      ! isset($values['acopio'])    ? null : $values['acopio']    );
        $obj->fillMember('premio',          $record, 'premio',      ! isset($values['premio'])    ? null : $values['premio']    );
        $obj->fillMember('material',        $record, 'material',    ! isset($values['material'])  ? null : $values['material']  );

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
        return self::DBwriteHelper(/** @var Aclamacion $obj */ $obj, [
            'aclamante'      => $obj->aclamante(true),
            'acopio'         => $obj->acopio(true),
            'premio'         => $obj->premio(true),
            'material'       => $obj->material(true),
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



    static public function query ($aclamantes, $tipo, $sponsors, $premios, $materiales, $sqlWhereAnd = null, $fieldToDerive = null, $loadWhenDeriving = true)
    {
        return self::DBquery(__CLASS__, [
                'aclamante'     => $aclamantes,
                'sponsor'       => $sponsors,
                'premio'        => $premios,
                'material'      => $materiales,
            ], "tipo = '$tipo'" . ($sqlWhereAnd === null ? '' : " AND ($sqlWhereAnd)"), $fieldToDerive, $loadWhenDeriving
        );
    }



}
