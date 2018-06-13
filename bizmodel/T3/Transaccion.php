<?php



namespace T3;



/**
 * Class Actor
 *
 * @method int|Actor|null       originador      (bool|Actor|null        $arg = null)
 * @method int|Actor|null       verificador     (bool|Actor|null        $arg = null)
 * @method string               tipo            (bool|string            $arg = null)
 * @method string               programadaPara  (bool|string            $arg = null)
 * @method string               efectuadaEn     (bool|string            $arg = null)
 * @method string               canceladaEn     (bool|string            $arg = null)
 * @method string               nota            (bool|string            $arg = null)
 *
 * @package T3
 */
abstract class Transaccion extends Persistable
{



    const SOFT_DELETIONS = false;



    static protected function membersList ()
    {
        return [
            'id',
            [ 'originador',     'Actor'     ],
            [ 'verificador',    'Actor'     ],
            'programadaPara',
            'efectuadaEn',
            'canceladaEn',
            'nota',
            'tipo',
        ];
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('id',              $record, 'id'                                                                               );
        $obj->fillMember('tipo',            $record, 'tipo',        ! isset($values['tipo'])        ? 'otro' : $values['tipo']          );
        $obj->fillMember('originador',      $record, 'originador',  ! isset($values['originador'])  ? null   : $values['originador']    );
        $obj->fillMember('verificador',     $record, 'verificador', ! isset($values['verificador']) ? null   : $values['verificador']   );
        $obj->fillMember('programadaPara',  $record, 'programada_para'                                                                  );
        $obj->fillMember('efectuadaEn',     $record, 'efectuada_en'                                                                     );
        $obj->fillMember('canceladaEn',     $record, 'cancelada_en'                                                                     );
        $obj->fillMember('nota',            $record, 'nota'                                                                             );

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
        return self::DBwriteHelper(/** @var Transaccion $obj */ $obj, [
            'tipo'               => DBbroker::q($obj->tipo()),
            'originador'         => $obj->originador(true),
            'verificador'        => $obj->verificador(true),
            'programada_en'      => DBbroker::q($obj->programadaPara()),
            'efectuada_en'       => DBbroker::q($obj->efectuadaEn()),
            'cancelada_en'       => DBbroker::q($obj->canceladaEn()),
            'nota'               => DBbroker::q($obj->nota()),
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



    public function notificaciones ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Notificacion::DBread("transaccion = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function transaccionesOriginadas ($tipo = null, $sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Entrega::DBread(($tipo === null ? '' : "tipo = '$tipo' AND ") . "originador = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function transaccionesVerificadas ($tipo = null, $sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Entrega::DBread(($tipo === null ? '' : "tipo = '$tipo' AND ") . "verificador = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



}
