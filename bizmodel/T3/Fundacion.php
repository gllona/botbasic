<?php



namespace T3;



/**
 * Class Fundacion
 *
 * @package T3
 */
class Fundacion extends Actor
{



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('tipo',        $record, 'tipo',            'fundacion' );
        $obj->fillMember('tipoPersona', $record, 'tipo_persona',    'juridica'  );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    // NAVIGATION METHODS



    public function actoresEnrolados ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Actor::DBread("enrolado_por = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function acopiosAfiliados ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Acopio::DBread("tipo = 'acopio' AND afiliado_a = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function colectoresAfiliados ($sqlWhere = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Colector::DBread("tipo = 'colector' AND afiliado_a = $id" . ($sqlWhere === null ? '' : ' AND (' . $sqlWhere . ')'));
    }



    public function donacionesVerificadas ($sqlWhere = null)
    {
        return $this->transaccionesVerificadas('donacion', $sqlWhere);
    }



    // PRIMITIVES METHODS



    static public function ValidarPIN ($pin)
    {
        return self::actorVerificarPIN('fundacion', $pin);
    }



    static public function DatosDeFundacion ($idFundacion)
    {
        return self::datosDePersonaJuridica($idFundacion);
    }



    static public function FundacionesPorNombre ($parte)
    {
        return self::personasJuridicasPorNombre($parte, 'fundacion');
    }



    static public function FundacionesPorSector ($idSector)
    {
        return self::personasJuridicasPorSector($idSector, 'fundacion');
    }



}
