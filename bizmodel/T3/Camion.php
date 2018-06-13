<?php



namespace T3;



/**
 * Class Camion
 *
 * @method string               fabricante      (bool|string            $arg = null)
 * @method string               modelo          (bool|string            $arg = null)
 * @method string               color           (bool|string            $arg = null)
 * @method string               matricula       (bool|string            $arg = null)
 *
 * @package T3
 */
class Camion extends Subsidiario
{



    const SOFT_DELETIONS = false;



    static protected function membersList ()
    {
        return array_merge(
            parent::membersList(),
            [
                'fabricante',
                'modelo',
                'color',
                'matricula',
            ]
        );
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('tipo',        $record, 'tipo',            'camion'    );
        $obj->fillMember('fabricante',  $record, 'fabricante'                   );
        $obj->fillMember('modelo',      $record, 'modelo'                       );
        $obj->fillMember('color',       $record, 'color'                        );
        $obj->fillMember('matricula',   $record, 'matricula'                    );

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
        return self::DBwriteHelper(/** @var Camion $obj */ $obj, [
            'fabricante'         => DBbroker::q($obj->fabricante()),
            'modelo'             => DBbroker::q($obj->modelo()),
            'color'              => DBbroker::q($obj->color()),
            'matricula'          => DBbroker::q($obj->matricula()),
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



    public function sectoresAtendidos ($load = true, $sqlWhereAnd = null)
    {
        if (($id = $this->id()) === null) { return null; }
        return Ruta::query($this, null, $sqlWhereAnd, 'sector', $load);
    }



    // PRIMITIVES METHODS



    static public function ValidarPIN ($pin)
    {
        return self::actorVerificarPIN('camion', $pin);
    }



    static public function DatosDeCamion ($id)
    {
        $res = self::datosDePersonaJuridica($id, true);
        if ($res == []) { return []; }
        list ($camion, $datos) = $res;   /* @var Camion $camion */
        return array_merge($datos, [ $camion->fabricante(), $camion->modelo(), $camion->color(), $camion->matricula() ]);
    }



    static public function FichaCamion ($id)
    {
        $data = self::DatosDeCamion($id);
        if ($data == []) { return []; }
        list (, , , $nombre, $apellido, , $telefonoCelular, , $nacimiento, , $fabricante, $modelo, $color, $matricula) = $data;
        return [$nombre, $apellido, $nacimiento, $telefonoCelular, $fabricante, $modelo, $color, $matricula];
    }



    static public function CamionesPorNombre ($parte)
    {
        return self::personasJuridicasPorNombre($parte, 'camion');
    }



    static public function CamionesPorSector ($idSector)
    {
        return self::personasJuridicasPorSector($idSector, 'camion');
    }



    static public function CamionesPorAcopio ($idAcopio)
    {
        return self::personasPorAfiliacion($idAcopio, 'camion', 'matricula', false);
    }



    static public function CamionIdAcopio ($id)
    {
        if (($camion = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, null); }   /** @var Camion $camion */
        return $camion->afiliadoA(true);
    }



    static public function AgendaCamion ($idCamion)
    {
        $entregas = EntregaColectorAcopio::DBread("camion = $idCamion AND efectuada_en IS NULL");   /** @var EntregaColectorAcopio[] $entregas */
        if (! $entregas && $entregas != []) { return Log::register(__CLASS__.__LINE__.__METHOD__, ''); }

        // indexar entregas segun momento y en segundo lugar por ID de acopio
        $entXmom = [];
        foreach ($entregas as $entrega) {
            $momento = $entrega->programadaPara();
            if (! isset($entXmom[$momento])) { $entXmom[$momento] = []; }
            if (! ($lote = $entrega->lote()))           { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            if (! ($colector = $entrega->originador())) { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            if (! isset($entXmom[$momento][$colector->id()])) { $entXmom[$momento][$colector->id()] = []; }
            $entXmom[$momento][$colector->id()][] = $entrega;
        }

        // preparar textos ordenados por momento de entrega
        $momentos = array_keys($entXmom);
        sort($momentos);
        $eventos = [];
        foreach ($momentos as $momento) {
            foreach ($entXmom[$momento] as $idColector => $entregas) {
                $eveXtipMat = [];
                // indexar las entregas de este momento por tipo de material y acumular peso y numeros de lote involucrados
                foreach ($entregas as $entrega) {
                    $tipoMat = $entrega->lote()->tipoMaterial();
                    if (! isset($eveXtipMat[$tipoMat])) { $eveXtipMat[$tipoMat] = [ [], 0, $entrega->originador()->nombre() . ' ' . $entrega->originador()->apellido(), $entrega->originador()->direccion() ]; }   // [ nrosLotes, peso, ... ]
                    $eveXtipMat[$tipoMat][0][] = $entrega->lote()->numero();
                    $eveXtipMat[$tipoMat][1]  += $entrega->lote()->peso();
                }
                // construir texto del evento y agregar a los eventos
                $evento = "$momento: ";
                foreach ($eveXtipMat as $tipoMat => $pair) {
                    list ($nrosLotes, $peso, $nombreColector, $direccion) = $pair;
                    $evento .= "$peso Kg de $tipoMat (lotes " . implode(', ', $nrosLotes) . "), en $direccion, con $nombreColector";
                }
                $eventos[] = $evento;
            }
        }

        // ready
        return implode('|', $eventos);
    }



    static public function AgendaCamionMenu ($idCamion, $tagCuando)
    {
        $sqlCuando = $tagCuando == 'ayerHoy' ? ' AND programada_para BETWEEN DATE_SUB(NOW(), INTERVAL 2 DAY) AND NOW()' : '';
        $entregas  = EntregaColectorAcopio::DBread("camion = $idCamion AND efectuada_en IS NULL $sqlCuando");   /** @var EntregaColectorAcopio[] $entregas */
        if (! $entregas && $entregas != []) { return Log::register(__CLASS__.__LINE__.__METHOD__, []); }

        // indexar entregas segun momento y en segundo lugar por ID de acopio
        $entXmom = [];
        foreach ($entregas as $entrega) {
            $momento = $entrega->programadaPara();
            if (! isset($entXmom[$momento])) { $entXmom[$momento] = []; }
            if (! ($colector = $entrega->originador())) { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            if (! isset($entXmom[$momento][$colector->id()])) { $entXmom[$momento][$colector->id()] = []; }
            $entXmom[$momento][$colector->id()][] = $entrega;
        }

        // preparar opciones de menu ordenadas por momento de entrega
        $momentos = array_keys($entXmom);
        sort($momentos);
        $eventos = [];
        foreach ($momentos as $momento) {
            foreach ($entXmom[$momento] as $idColector => $entregas) {
                $colector  = Colector::DBread($idColector);   /** @var Colector $colector */
                $evento    = "$momento @ " . $colector->direccion();
                $eventos[] = $evento;
            }
        }

        // ready
        return $eventos;
    }



    static public function UpdateFabricante ($id, $fabricante)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Camion $o */
        $o->fabricante($fabricante);
    }



    static public function UpdateModelo ($id, $modelo)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Camion $o */
        $o->modelo($modelo);
    }



    static public function UpdateColor ($id, $color)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Camion $o */
        $o->color($color);
    }



    static public function UpdateMatricula ($id, $matricula)
    {
        if (($o = self::DBread($id)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Camion $o */
        $o->matricula($matricula);
    }



}
