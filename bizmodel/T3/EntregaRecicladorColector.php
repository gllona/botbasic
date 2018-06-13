<?php



namespace T3;



/**
 * Class EntregaRecicladorColector
 *
 * @package T3
 */
class EntregaRecicladorColector extends Entrega
{



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('subtipo', $record,    'subtipo',  'entrega_reciclador_colector'   );   // o: $obj->fillMember('subtipo', 'entrega_reciclador_colector');

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    static public function DBread ($criterium, $factory = null)
    {
        return self::DBreadHelper(is_string($criterium) ? "subtipo = 'entrega_reciclador_colector' AND ($criterium)" : $criterium, __CLASS__, $factory);
    }



    // PRIMITIVES METHODS



    static public function EntregarMaterial ($idReciclador, $idColector, $idMaterial, $cantidad)
    {
        $dateFormat = function (\DateTime $date)
        {
            return $date->format('Y-m-d H:i:s');
        };

        // calcular ecopuntos
        if (($eps = Material::calcularEcopuntos($idMaterial, $cantidad)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        list ($epReciclador, $epColector) = $eps;

        // cargar objetos
        if (! ($reciclador = Reciclador::DBread($idReciclador))) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Reciclador $reciclador */
        if (! ($colector = Colector::DBread($idColector)))       { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Colector $colector     */

        // crear entrega
        $now = date_create();   // mejora: localizar fecha segun zona horaria del usuario (tambien en siguiente metodo)
        if (! ($entrega = self::factory())) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var EntregaRecicladorColector $entrega */
        $entrega->originador($idReciclador);
        $entrega->verificador($idColector);
        $entrega->material($idMaterial);
        $entrega->cantidad($cantidad);
        $entrega->ecopuntos($epReciclador);
        $entrega->efectuadaEn($dateFormat($now));
        $loteAsignadoYlotesCerrados = $colector->loteParaEntregaMaterial($idMaterial, $cantidad);
        if ($loteAsignadoYlotesCerrados === null) {
            $lote          = null;
            $lotesCerrados = [];
        }
        else {
            list ($lote, $lotesCerrados) = $loteAsignadoYlotesCerrados;   /** @var Lote $lote */   // los $lotesCerrados estan cerrados en BD pero no entregados, por lo que en BD aun no estan asociados a ningun Acopio
            $entrega->lote($lote->id());
        }

        // ajustar ecopuntos de Lote, Reciclador y Colector
        $lote->ecopuntos($lote->ecopuntos() + $epColector);
        $reciclador->ecopuntos($reciclador->ecopuntos() + $epReciclador);
        $colector->ecopuntos($colector->ecopuntos() + $epColector);

        // ready
        $strLotesCerrados = implode('|', $lotesCerrados);
        return [ 1, $lote->numero(), $epReciclador, $epColector, $strLotesCerrados ];
    }



}
