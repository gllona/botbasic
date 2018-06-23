<?php
/**
 * Librería que provee métodos estáticos genéricos para acceso a base de datos y profiling
 *
 * @author      Gorka Llona <gorka@gmail.com>
 * @see         tg://@GrokaBot
 * @version     2.0 - 23.jun.2018
 * @since       0.1 - 01.jul.2016
 */



namespace nima;

use \PDO, \PDOException;



/**
 * Clase DBbroker
 *
 * Implementa como librería (en forma de métodos estáticos) el ORM de BotBasic.
 *
 * @package nima
 */
class DBbroker
{



    const TABLES_HAVE_RAND_ATTRIB = false;

    /** @var PDO */
    static private $dbh = null;   // DB handler



    /**
     * Establece la conexión a la BD en forma de singleton
     *
     * Si la conexión ya está establecida por un connect() previo, será reusada.
     *
     * @param  bool     $disconnectFirst        Indica si se debe desconectar la BD antes de intentar la conexión
     * @return bool                             Si la conexión pudo ser establecida
     */
    static public function connect ($disconnectFirst = false)
    {
        if (! $disconnectFirst && self::$dbh !== null) { return true; }
        try {
            if (self::$dbh !== null) {
                self::disconnect();
            }
            self::$dbh = new PDO(DB_CONN_STR, DB_USER, DB_PASSWORD, [PDO::ATTR_PERSISTENT => true ]);
            return true;
        }
        catch (PDOException $e) {
            Log::register("No es posible conectarse a la BD del bizmodel: " . $e->getMessage());
            return false;
        }
    }



    /**
     * Desconecta la BD
     */
    static private function disconnect ()
    {
        self::$dbh = null;
    }



    /**
     * Retorna un string con el mensaje del último error de BD, si hubiese; si no lo hay, retorna un string apropiado
     *
     * @return string
     */
    static private function dbError ()
    {
        if (self::$dbh === null) {
            return "DB_NOT_CONNECTED";
        }
        $res = "[SQLSTATE=" . (self::$dbh->errorCode() === null ? 'NULL' : self::$dbh->errorCode()) . '|' . self::$dbh->errorInfo()[2] . "]";
        return $res;
    }



    /**
     * Efectúa un select sobre la BD
     *
     * @param  string       $sql                Texto del select de SQL
     * @param  bool         $fieldToExtract     Si no es null, se extrae este campo de cada fila del resultset obtenido y se retorna un arreglo con esos valores
     * @return array|bool                       false en caso de error de SQL/BD; el arreglo de resultados (como en mysql_query_assoc()) en caso de éxito
     */
    static public function query ($sql, $fieldToExtract = null)
    {
        if (self::$dbh === null) {
            self::connect();
            // Log::register("query(): base de datos no conectada al intentar...\n$sql\n");
            // return false;
        }
        $res = self::$dbh->query($sql);
        if ($res === false) {
            $error = self::dbError();
            Log::register("Error de BD: $error; con...\n$sql\n");
            return false;
        }
        $res = $res->fetchAll(PDO::FETCH_ASSOC);
        if ($fieldToExtract !== null) {
            $res = array_map(function ($elem) use ($fieldToExtract) { return $elem[$fieldToExtract]; }, $res);
        }
        return $res;
    }



    /**
     * Efectúa un insert, update, replace o delete sobre la BD
     *
     * Para evitar que en caso de escritura de un registro cuyos valores a escribir sean los mismos que los actuales, el valor de retorno de
     * número de filas afectadas sea cero (como para MySQL), se escribe un valor aleatorio en una columna "rand" que deben tener todas las tablas.
     *
     * @param  string           $sql                    Texto de la sentencia SQL
     * @param  bool             $returnLastInsertId     Si es true, se retorna el ID del insert/replace efectuado; si es false, la cantidad de filas afectadas
     * @param  bool             $generateRandMark       Indica si debe generar la columna 'rand' automáticamente; pasar false para inserts múltiples
     * @return bool|int|null                            false en caso de error de SQL/BD; o lo especificado arriba
     */
    static public function exec ($sql, $returnLastInsertId = false, $generateRandMark = self::TABLES_HAVE_RAND_ATTRIB)
    {
        if (self::$dbh === null) {
            self::connect();
            // Log::register("exec(): base de datos no conectada al intentar...\n$sql\n");
            // return false;
        }
        if ($generateRandMark) {
            $rand      = substr(md5(rand()), 0, 8);
            $statement = strtoupper(explode(' ', trim($sql), 2)[0]);
            switch ($statement) {
                case 'INSERT'  :
                case 'REPLACE' :
                    $parts1 = explode('(', $sql, 2);
                    if (count($parts1) > 1) {
                        $parts2 = explode('VALUES (', $parts1[1], 2);
                        $sql    = $parts1[0] . "(rand, " . $parts2[0] . "VALUES ('$rand', " . $parts2[1];
                    }
                    else {
                        $parts = explode('SET ', $sql, 2);
                        $sql   = $parts[0] . "SET rand = '$rand', " . $parts[1];
                    }
                    break;
                case 'UPDATE'  :
                    $parts = explode('SET ', $sql, 2);
                    $sql   = $parts[0] . "SET rand = '$rand', " . $parts[1];
                    break;
                case 'DELETE'  :
                default        :
            }
        }
        $res = self::$dbh->exec($sql);
        if ($res === false) {
            $error = self::dbError();
            Log::register("Error de BD: $error; con...\n$sql\n");
            return false;
        }
        if ($returnLastInsertId) { return intval(self::$dbh->lastInsertId()); }
        else                     { return $res;                               }   // affected rows count
    }



    /**
     * Futore work.
     */
    static public function beginTransaction ()
    {
        // not needed up to now
    }



    /**
     * Futore work.
     */
    static public function commit ()
    {
        // not needed up to now
    }



    /**
     * Futore work.
     */
    static public function rollback ()
    {
        // not needed up to now
    }



    /**
     * Quote: retorna el valor que se pasa como parámetro en forma sanitizada para una sentencia SQL
     *
     * Los resultados de serialize() deben pasar también por aquí.
     *
     * @param  string   $val        Valor original
     * @param  bool     $isNumeric  Si es true, no se aplicarán comillas sino que sólo se verificará por valores null
     * @return string               String sanitizado; o el mismo string si no hay conexión abierta a BD
     */
    static public function q ($val, $isNumeric = false)
    {
        if (self::$dbh === null) {
            self::connect();
            // Log::register("base de datos no conectada al intentar Quote");
            // return $val;
        }
        return $val === null ? 'NULL' : ($isNumeric ? $val : self::$dbh->quote($val));
    }



}
