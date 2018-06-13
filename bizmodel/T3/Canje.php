<?php



namespace T3;

use botbasic\BizModelAdapter;



/**
 * Class Canje
 *
 * @method float                ecopuntos       (bool|float             $arg = null)
 * @method int|Premio|null      premio          (bool|Premio|null       $arg = null)
 * @method int                  cantidad        (bool|int               $arg = null)
 * @method string               pin             (bool|string            $arg = null)
 *
 * @package T3
 */
class Canje extends Transaccion
{



    const SOFT_DELETIONS = false;



    static protected function membersList ()
    {
        return array_merge(
            parent::membersList(),
            [
                'ecopuntos',
                [ 'premio',     'Premio'    ],
                'cantidad',
                'pin',
            ]
        );
    }



    static public function factory ($record = null, $over = null, $clone = false, $values = null)
    {
        // resolve base object to fill in
        $obj = self::factoryHelper($record, $over, $clone, $values, __CLASS__);   /** @var Persistable $obj */
        if ($obj === null) { return null; }

        // fill base class attributes
        $obj->fillMember('ecopuntos',   $record, 'ecopuntos'                                                                );
        $obj->fillMember('premio',      $record, 'premio',      ! isset($values['premio'])   ? null : $values['premio']     );
        $obj->fillMember('cantidad',    $record, 'cantidad'                                                                 );
        $obj->fillMember('pin',         $record, 'pin'                                                                      );

        // before ready check if should generate the DB id
        if ($record === true) { $obj->DBwrite($obj); }
        return $obj;
    }



    static public function DBread ($criterium, $factory = null)
    {
        return self::DBreadHelper(is_string($criterium) ? "tipo = 'canje' AND ($criterium)" : $criterium, __CLASS__, $factory);
    }



    static protected function DBwrite ($obj, $innerCall = false)
    {
        return self::DBwriteHelper(/** @var Canje $obj */ $obj, [
            'ecopuntos'          => $obj->ecopuntos(),
            'premio'             => $obj->premio(true),
            'cantidad'           => $obj->cantidad(),
            'pin'                => $obj->pin(),
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



    // TOOLBOX METHODS



    private function generarPIN ()
    {
        if ($this->id() === null) { return null; }
        $pin = '' . ($this->id() % 1e8);
        $pin = strrev(str_pad($pin, 8, '0', STR_PAD_LEFT));
        $rnd = rand(1000, 9999);
        $pin = substr($pin, 0, 4) . '-' . substr($pin, 4, 4) . '-' . $rnd;
        return $pin;
    }



    static private function normalizarPIN ($pin)
    {
        $pin = preg_replace('/[^0-9]/', '', $pin);
        $pin = substr($pin, 0, 12);
        $pin = str_pad($pin, 12, '0', STR_PAD_LEFT);
        $pin = substr($pin, 0, 4) . '-' . substr($pin, 4, 4) . '-' . substr($pin, 8, 4);
        return $pin;
    }



    private function bonoPorRollback ()
    {
        return $this->ecopuntos() * CANJE_CANCELING_BONUS_PERCENT;
    }



    // PRIMITIVES METHODS



    static public function PremioEnRetail ($idPremio, $idRetail)
    {
        return true;   // FIXME trivial; cambiar al implementar subsistema de stock de premios en retails
    }



    static public function MaxPremiosCanjeables ($idPremiable, $idPremio)
    {
        if (($actor  = Actor ::DBread($idPremiable)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, 0); }   /** @var Actor  $actor  */
        if (($premio = Premio::DBread($idPremio   )) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, 0); }   /** @var Premio $premio */
        $max = floor($actor->ecopuntos() / ($premio->epPorUnidad() == 0 ? 1 : $premio->epPorUnidad()));
        return $max;
    }



    static public function GenerarPINcanje ($idPremiable, $idPremio, $cantidad, $idRetail)
    {
        $sqlDateTime = function (\DateTime $date)
        {
            return $date->format('Y-m-d H:i:s');
        };

        $cantidad = floor($cantidad);
        $max      = self::MaxPremiosCanjeables($idPremiable, $idPremio);
        if ($cantidad <= 0)   { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        if ($cantidad > $max) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        if (($actor  = Actor        ::DBread($idPremiable)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Actor  $actor         */
        if (($premio = Premio       ::DBread($idPremio   )) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Premio $premio        */
        if (($retail = SponsorRetail::DBread($idRetail   )) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var SponsorRetail $retail */
        if (! ($canje = self::factory(true))) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }   /** @var Canje $canje */
        $epCanje = $premio->epPorUnidad() * $cantidad;
        $canje->originador($idPremiable);
        $canje->verificador($idRetail);
        $canje->programadaPara($sqlDateTime(date_create()));
        $canje->premio($idPremio);
        $canje->cantidad($cantidad);
        $canje->ecopuntos($epCanje);
        $pin = $canje->generarPIN();
        $canje->pin($pin);
        $actor->ecopuntos($actor->ecopuntos() - $epCanje);
        return [ true, $actor->ecopuntos(), $pin ];
    }



    static public function PINesNoCanjeados ($idPremiable)
    {
        $canjes = self::DBread("originador = $idPremiable AND efectuada_en IS NULL");   /** @var Canje[] $canjes */
        if ($canjes === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        $res = [];
        foreach ($canjes as $canje) {
            if (($premio = $canje->premio()) === null) { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            $res[] = [ $canje->id(), $canje->cantidad() . ' x ' . $premio->nombre() ];
        }
        return $res;
    }



    static public function DatosDeCanje ($idCanje)
    {
        if (($canje  = self::DBread($idCanje)) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }   /** @var Canje         $canje  */
        if (($premio = $canje->premio()) === null)       { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        if (($retail = $canje->verificador()) === null)  { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        return [ $premio->nombre(), $canje->cantidad(), $canje->pin(), $retail->figuraLegal(), $retail->direccionCompleta() ];
    }



    static public function ValidarPIN ($pin, $idRetail)
    {
        // obtener el canje a partir del PIN
        $pin    = self::normalizarPIN($pin);
        $canjes = self::DBread("pin = '$pin' AND efectuada_en IS NULL");   /** @var Canje[] $canjes */
        if ($canjes === null)    { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        if (count($canjes) != 1) { return false; }
        $canje = $canjes[0];

        // validar el pin y devolver la informacion del canje
        if (($retail = $canje->verificador()) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        if ($retail->id() != $idRetail) { return [ true, false, $retail->figuraLegal() ]; }
        if (($actor  = $canje->originador()) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        if (($premio = $canje->premio())     === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        $rol = $actor->tipo() == 'reciclador' ? 'main' : 'handler';
        return [ true, true, $retail->figuraLegal(), $premio->id(), $premio->nombre(), $canje->cantidad(), $rol, $actor->id(), ($actor->nombre() . ' ' . $actor->apellido()) ];
    }



    static public function RollbackPIN ($pin, $tagRolOrigenCancelacion, $razonCancelacion)
    {
        $sqlDateTime = function (\DateTime $date)
        {
            return $date->format('Y-m-d H:i:s');
        };

        // obtener el canje a partir del PIN
        $pin    = self::normalizarPIN($pin);
        $canjes = self::DBread("pin = '$pin' AND efectuada_en IS NULL");   /** @var Canje[] $canjes */
        if ($canjes === null)    { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        if (count($canjes) != 1) { return false; }
        $canje = $canjes[0];

        // hacer rollback del canje
        if (($actor = $canje->originador()) === null) { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        $epDevueltos = $canje->ecopuntos();
        $epBono      = $canje->bonoPorRollback();
        $actor->ecopuntos($actor->ecopuntos() + $epDevueltos + $epBono);
        $sqlNow = $sqlDateTime(date_create());
        $canje->efectuadaEn($sqlNow);
        $canje->canceladaEn($sqlNow);
        $nota = "Rol que cancela: " . ($tagRolOrigenCancelacion . ' (' . ($tagRolOrigenCancelacion == 'exchanger' ? 'retail' : 'reciclador') . ')') . " // " .
                "Razón de cancelación por el retail: $razonCancelacion";
        $canje->nota($nota);

        // ready
        return [ $epDevueltos, $epBono, $actor->ecopuntos() ];
    }



    static public function RollBackPINnotaPremiable ($pin, $nota, BizModelAdapter $bma)
    {
        // obtener el canje a partir del PIN
        $pin    = self::normalizarPIN($pin);
        $canjes = self::DBread("pin = '$pin' AND cancelada_en IS NOT NULL");   /** @var Canje[] $canjes */
        if ($canjes === null)    { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        if (count($canjes) != 1) { return false; }
        $canje = $canjes[0];

        // agregar nota al canje cancelado
        $canje->nota($canje->nota() . " // Nota del actor premiable: $nota");

        // enviar email de notificacion
        if (($actor = $canje->originador()) === null)   { Log::register(__CLASS__.__LINE__.__METHOD__); }
        if (($retail = $canje->verificador()) === null) { Log::register(__CLASS__.__LINE__.__METHOD__); }
        $subject = SMTP_SUBJECT . ' - Canje cancelado';
        $body    = "Retail: " . $retail->figuraLegal() . " \n" .
                   "Actor premiable: " . ($actor->nombre() . ' ' . $actor->apellido()) . " \n" .
                   "Notas: " . $canje->nota();
        $bma->googleMail(SMTP_CONTACT_RECIPIENT, $subject, $body);

        // ready
        return [];
    }



    static public function EntregaPremioHechaPorPIN ($pin)
    {
        $sqlDateTime = function (\DateTime $date)
        {
            return $date->format('Y-m-d H:i:s');
        };

        // obtener el canje a partir del PIN
        $pin    = self::normalizarPIN($pin);
        $canjes = self::DBread("pin = '$pin' AND efectuada_en IS NULL");   /** @var Canje[] $canjes */
        if ($canjes === null)    { return Log::register(__CLASS__.__LINE__.__METHOD__, false); }
        if (count($canjes) != 1) { return false; }
        $canje = $canjes[0];

        // completar el canje y retornar
        $canje->efectuadaEn($sqlDateTime(date_create()));
        return true;
    }



    static public function CanjesEnRetail ($idRetail, $fechaCanje, $tipoCanje)
    {
        $sqlTipo = strtolower($tipoCanje) == 'pendiente' ? 'efectuada_en IS NULL' : "cancelada_en IS NULL AND DATE(efectuada_en) = '$fechaCanje'";
        $canjes  = self::DBread("verificador = $idRetail AND $sqlTipo");   /** @var Canje[] $canjes */
        if ($canjes === null) { return Log::register(__CLASS__.__LINE__.__METHOD__); }
        $res = [];
        foreach ($canjes as $canje) {
            if (($premio = $canje->premio())     === null) { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            if (($actor  = $canje->originador()) === null) { Log::register(__CLASS__.__LINE__.__METHOD__); continue; }
            $res[] = $canje->cantidad() . ' x ' . $premio->nombre() . ' --> ' . ($actor->nombre() . ' ' . $actor->apellido());
        }
        return implode('|', $res);
    }



}
