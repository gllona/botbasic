<?php



namespace T3;



/**
 * Class Notificacion
 *
 * @method string               tipo            (bool|string            $arg = null)
 * @method int|Actor|null       remitente       (bool|Actor|null        $arg = null)
 * @method int|Actor|null       destinatario    (bool|Actor|null        $arg = null)
 * @method int|Transaccion|null transaccion     (bool|Transaccion|null  $arg = null)
 *
 * @package T3
 */
class Notificacion extends Persistable
{



    const SOFT_DELETIONS = false;



    static protected function membersList ()
    {
        return [
            'id',
            [ 'remitente',      'remitente'     ],
            [ 'destinatario',   'destinatario'  ],
            [ 'transaccion',    'transaccion'   ],
        ];
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('id',              $record, 'id'                                                                                   );
        $obj->fillMember('remitente',       $record, 'remitente',       ! isset($values['remitente'])    ? null : $values['remitente']      );
        $obj->fillMember('destinatario',    $record, 'destinatario',    ! isset($values['destinatario']) ? null : $values['destinatario']   );
        $obj->fillMember('transaccion',     $record, 'transaccion',     ! isset($values['transaccion'])  ? null : $values['transaccion']    );

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
        return self::DBwriteHelper(/** @var Notificacion $obj */ $obj, [
            'remitente'          => $obj->remitente(true),
            'destinatario'       => $obj->destinatario(true),
            'transaccion'        => $obj->transaccion(true),
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
