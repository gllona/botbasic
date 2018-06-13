<?php



namespace T3;



/**
 * Class Donacion
 *
 * @method string               subtipo         (bool|string            $arg = null)
 * @method float                monto           (bool|float             $arg = null)
 * @method int|Premio|null      premio          (bool|Premio|null       $arg = null)
 * @method int                  cantidad        (bool|int               $arg = null)
 *
 * @package T3
 */
abstract class Donacion extends Transaccion
{



    static protected function membersList ()
    {
        return array_merge(
            parent::membersList(),
            [
                'subtipo',
                'monto',
                [ 'premio',     'Premio'     ],
                'cantidad',
            ]
        );
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('tipo',        $record, 'tipo',    'donacion'                                                           );
        $obj->fillMember('subtipo',     $record, 'subtipo', ! isset($values['subtipo']) ? 'donacion_dinero' : $values['subtipo'] );
        $obj->fillMember('monto',       $record, 'monto'                                                                         );
        $obj->fillMember('premio',      $record, 'premio'                                                                        );
        $obj->fillMember('cantidad',    $record, 'cantidad'                                                                      );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    static public function DBread ($criterium, $factory = null)
    {
        return self::DBreadHelper(is_string($criterium) ? "tipo = 'donacion' AND ($criterium)" : $criterium, __CLASS__, $factory);
    }



    static protected function DBwrite ($obj, $innerCall = false)
    {
        return self::DBwriteHelper(/** @var Donacion $obj */ $obj, [
            'subtipo'            => DBbroker::q($obj->subtipo()),
            'monto'              => $obj->monto(),
            'premio'             => $obj->premio(true),
            'cantidad'           => $obj->cantidad(),
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
