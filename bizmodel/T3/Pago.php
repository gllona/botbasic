<?php



namespace T3;



/**
 * Class Pago
 *
 * @method float                monto           (bool|float             $arg = null)
 * @method int|Actor|null       beneficiario    (bool|Actor|null        $arg = null)
 *
 * @package T3
 */
class Pago extends Transaccion
{



    const SOFT_DELETIONS = false;



    static protected function membersList ()
    {
        return array_merge(
            parent::membersList(),
            [
                'monto',
                [ 'beneficiario',   'Actor' ],
            ]
        );
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('monto',           $record, 'monto'                                                                                );
        $obj->fillMember('beneficiario',    $record, 'beneficiario',    ! isset($values['beneficiario']) ? null : $values['beneficiario']   );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    static public function DBread ($criterium, $factory = null)
    {
        return self::DBreadHelper(is_string($criterium) ? "tipo = 'pago' AND ($criterium)" : $criterium, __CLASS__, $factory);
    }



    static protected function DBwrite ($obj, $innerCall = false)
    {
        return self::DBwriteHelper(/** @var Pago $obj */ $obj, [
            'monto'              => $obj->monto(),
            'beneficiario'       => $obj->beneficiario(true),
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
