<?php



namespace T3;



/**
 * Class Entrega
 *
 * @method string               subtipo         (bool|string            $arg = null)
 * @method float                ecopuntos       (bool|float             $arg = null)
 * @method int|Material|null    material        (bool|Material|null     $arg = null)
 * @method int|null             cantidad        (bool|int|null          $arg = null)
 * @method int|Lote|null        lote            (bool|Lote|null         $arg = null)
 * @method int|Camion|null      camion          (bool|Camion|null       $arg = null)
 *
 * @package T3
 */
abstract class Entrega extends Transaccion
{



    const SOFT_DELETIONS = false;



    static protected function membersList ()
    {
        return array_merge(
            parent::membersList(),
            [
                'subtipo',
                'ecopuntos',
                [ 'material',   'Material'  ],
                'cantidad',
                [ 'lote',       'Lote'      ],
                [ 'camion',     'Camion'    ],
            ]
        );
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('tipo',        $record, 'tipo',        'entrega'                                                                           );
        $obj->fillMember('subtipo',     $record, 'subtipo',     ! isset($values['subtipo'])  ? 'entrega_reciclador_colector' : $values['subtipo']   );
        $obj->fillMember('ecopuntos',   $record, 'ecopuntos'                                                                                        );
        $obj->fillMember('material',    $record, 'material',    ! isset($values['material']) ? null                          : $values['material']  );
        $obj->fillMember('cantidad',    $record, 'cantidad'                                                                                         );
        $obj->fillMember('lote',        $record, 'lote',        ! isset($values['lote'])     ? null                          : $values['lote']      );
        $obj->fillMember('camion',      $record, 'camion',      ! isset($values['camion'])   ? null                          : $values['camion']    );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    static public function DBread ($criterium, $factory = null)
    {
        return self::DBreadHelper(is_string($criterium) ? "tipo = 'entrega' AND ($criterium)" : $criterium, __CLASS__, $factory);
    }



    static protected function DBwrite ($obj, $innerCall = false)
    {
        return self::DBwriteHelper(/** @var Entrega $obj */ $obj, [
            'subtipo'            => $obj->subtipo(),
            'ecopuntos'          => $obj->ecopuntos(),
            'material'           => $obj->material(true),
            'cantidad'           => $obj->cantidad(),
            'lote'               => $obj->lote(true),
            'camion'             => $obj->camion(true),
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
