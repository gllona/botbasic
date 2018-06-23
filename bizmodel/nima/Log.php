<?php
/**
 * Librería de registro de mensajes y errores en archivos de bitácora
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     2.0 - 23.jun.2018
 * @since       0.1 - 01.jul.2016
 */



namespace nima;

use \DateTime, \DateTimeZone;



/**
 * Clase Log
 *
 * Implementa (como métodos estáticos) las herramientas de registro de errores de mensajes en bitácora.
 *
 * @package nima
 */
class Log
{



    /** @var mixed Filehandler del archivo de bitácora, cuando está abierto */
    static private $fh = null;

    /** @var array[] Store para las marcas del profiler; cada marca contiene un arreglo con un historial de timestamps con microsegundos; el primero es un indicador bool de actividad */
    static private $profilerStore = [];



    /**
     * Genera en bitácora una entrada
     *
     * @param  string       $message            Texto de la entrada
     * @param  mixed        $returnValue        Valor de retorno
     * @return null
     */
    static public function register ($message, $returnValue = [])
    {
        if (self::$fh === null) {
            $fh = fopen(LOGFILE, "a");
            if ($fh === false) { error_log("CAN'T OPEN LOGFILE FOR WRITING... $message\n"); exit; }
            else               { self::$fh = $fh;                                                 }
        }
        $message = '[' . self::makeCurrentDatetimeString() . '] ' . $message;
        $res     = fwrite(self::$fh, "$message\n");
        if ($res === false) { error_log("CAN'T WRITE INTO LOGFILE... $message\n"); exit; }
        else                { fflush(self::$fh);                                         }
        if (is_int($returnValue)) { $returnValue = array_fill(0, $returnValue, null); }
        return $returnValue;
    }



    /**
     * Genera una fecha+hora en un formato apropiado para su registro en bitácora y con el timezone correcto
     *
     * @param  bool     $withMiliSeconds        Indica si se debe incluir información de milisegundos
     * @return string                           Fecha y hora en el formato apropiado
     */
    static private function makeCurrentDatetimeString ($withMiliSeconds = true)
    {
        $mt = microtime(true);
        $ms = sprintf("%06d", ($mt - floor($mt)) * 1000000);
        $dt = new DateTime(date('Y-m-d H:i:s.' . $ms, $mt));
        $tz = new DateTimeZone(TIMEZONE);
        $dt->setTimezone($tz);
        $res = $dt->format("Y-m-d@H:i:s" . ($withMiliSeconds ? ".u" : ""));
        $res = substr($res, 0, strlen($res) - 3);
        return $res;
    }



    /**
     * Utility para el profiler; formatea un timestamp (float con microsegundos) a una precision determinada
     *
     * @param  null|float   $time           Timestamp, tal como es generado por microtime() o time()
     * @param  int          $precision      Precision de los microsegundos; por defecto 6 (lectura de microsegundos)
     * @return string                       Lectura de tiempo en formato hh:mm:ss.frac
     */
    static private function formattedTime ($time = null, $precision = 6)
    {
        if ($time === null) { $time = microtime(true); }
        $secs = floor($time);
        $usec = $time - $secs;
        $frac = substr(sprintf("%0.${precision}f", round($usec, $precision)), 2);
        $text = date_format(date_create("@$secs"), 'H:i:s.') . $frac;
        return $text;
    }



    /**
     * Inicia una secuencia de profiling y registra opcionalmente una entrada en bitácora
     *
     * El profiling requiere fijar en true la constante BIZMODEL_PROFILE.
     *
     * @param  string|int   $mark       Marca de profiling a ser usada posteriormente con profilerStep() y profilerStop()
     * @param  string       $tag        Texto descriptivo opcional de toda la secuencia
     * @param  string|null  $text       Texto opcional a mostrar; usar null para no mostrar la entrada
     */
    static public function profilerStart ($mark, $tag = '', $text = '')
    {
        if (! BIZMODEL_PROFILE) { return; }
        $now = microtime(true);
        if (! (is_int($mark) || is_string($mark))) { return; }
        self::$profilerStore[$mark] = [ true, $tag, $now ];
        if ($text !== null) {
            if ($tag !== '') { $tag = "=$tag"; }
            $msg = "PROFILER::[$mark$tag START NOW=" . self::formattedTime($now) . "] " . $text;
            self::register($msg);
        }
    }



    /**
     * Continúa una secuencia de profiling y registra opcionalmente una entrada en bitácora
     *
     * El profiling requiere fijar en true la constante BIZMODEL_PROFILE.
     *
     * @param  string|int   $mark           Marca de profiling usada con profilerStart()
     * @param  string|null  $text           Texto opcional a mostrar; usar null para no mostrar la entrada
     * @param  bool         $comesFromStop  Don't use
     */
    static public function profilerStep ($mark, $text = '', $comesFromStop = false)
    {
        if (! BIZMODEL_PROFILE) { return; }
        $now = microtime(true);
        if (! (is_int($mark) || is_string($mark))) { return; }
        if (! isset(self::$profilerStore[$mark]) || self::$profilerStore[$mark][0] === false) { return; }
        $fullMsecs = sprintf('%0.6f', round($now - self::$profilerStore[$mark][ 2                                    ], 6));
        $stepMsecs = sprintf('%0.6f', round($now - self::$profilerStore[$mark][ count(self::$profilerStore[$mark])-1 ], 6));
        self::$profilerStore[$mark][] = $now;
        if ($text !== null) {
            $tag   = self::$profilerStore[$mark][1];
            if ($tag !== '') { $tag = "=$tag"; }
            $label = $comesFromStop ? "STOP" : "STEP";
            $text  = "PROFILER::[$mark$tag $label  NOW=" . self::formattedTime($now) . " FULL=$fullMsecs STEP=$stepMsecs] " . $text;
            self::register($text);
        }
    }



    /**
     * Termina una secuencia de profiling y registra una entrada en bitácora con el resumen estadístico de la secuencia
     *
     * El profiling requiere fijar en true la constante BIZMODEL_PROFILE.
     *
     * @param  string|int   $mark       Marca de profiling usada con profilerStart() y profilerStep()
     * @param  string|null  $text       Texto opcional a mostrar; usar null para no mostrar la entrada
     * @param  bool         $doStep     Indica si debe hacer una llamada a profilerStep() antes de procesar la detención de la secuencia
     */
    static public function profilerStop ($mark, $text = '', $doStep = true)
    {
        if (! BIZMODEL_PROFILE) { return; }
        if ($doStep) { self::profilerStep($mark, $text, true); }
        if (! (is_int($mark) || is_string($mark))) { return; }
        if (! isset(self::$profilerStore[$mark]) || self::$profilerStore[$mark][0] === false) { return; }
        if (count(self::$profilerStore[$mark]) <= 3) {
            self::register("PROFILER:: No steps registered for MARK=$mark" . (is_string($text) ? " TEXT=$text" : ''));
        }
        $mean = $sigma = 0; $min = +1e6; $max = -1e6; $steps = [];
        for ($i = 3; $i < count(self::$profilerStore[$mark]); $i++) {
            $steps[] = $step = self::$profilerStore[$mark][$i] - self::$profilerStore[$mark][$i-1];
            $mean   += $step;
            if ($step < $min) { $min = round($step, 6); }
            if ($step > $max) { $max = round($step, 6); }
        }
        $mean /= count($steps);
        for ($i = 1; $i < count($steps); $i++) {
            $sigma += pow($steps[$i] - $mean, 2);
        }
        $sigma = sqrt($sigma / count($steps));
        $mean  = round($mean,  6);
        $sigma = round($sigma, 6);
        $tag   = self::$profilerStore[$mark][1];
        if ($tag !== '') { $tag = "=$tag"; }
        $text = "PROFILER::[$mark$tag STOP MEAN=$mean SIGMA=$sigma MIN=$min MAX=$max] " . ($doStep ? "" : $text);
        self::register($text);
        self::$profilerStore[$mark][0] = false;
    }



}
