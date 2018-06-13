<?php



namespace T3;



/**
 * Class Operador
 *
 * @package T3
 */
class Operador extends Actor
{



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('tipo',        $record, 'tipo',            'operador'  );
        $obj->fillMember('tipoPersona', $record, 'tipo_persona',    'juridica'  );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    // NAVIGATION METHODS



    public function fundacionesAfiliadas ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Fundacion::DBread("tipo = 'fundacion' AND afiliado_a = " . $id . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function pagosVerificados ($sqlWhere = null)
    {
        return $this->transaccionesVerificadas('pago', $sqlWhere);
    }



    public function donacionesVerificadas ($subtipo = null, $sqlWhere = null)
    {
        return $this->transaccionesVerificadas('donacion', ($subtipo === null ? "TRUE" : "subtipo = '$subtipo'") . " AND " . ($sqlWhere === null ? "TRUE" : "($sqlWhere)"));
    }



    // PRIMITIVES METHODS



    static public function ValidarPIN ($pin)
    {
        return self::actorVerificarPIN('operador', $pin);
    }



}
