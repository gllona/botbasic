<?php

// use \PDO, \PDOException;
// esta clase usa a la clase Log.php, que maneja la bitacora de mensajes y errores

define('TTB_DB_DRIVER',   'mysql'    );   // uno de los posibles RDBMS a los que se accede via PHP Data Objects (PDO)
define('TTB_DB_HOST',     'localhost');
define('TTB_DB_NAME',     'ttbdb'    );
define('TTB_DB_USER',     'ttbuser'  );
define('TTB_DB_PASSWORD', 'ttbpass'  );
define('TTB_DB_CONN_STR', TTB_DB_DRIVER . ":host=" . TTB_DB_HOST . ";dbname=" . TTB_DB_NAME);

class DBbroker
{

    /** @var PDO */
    static private $dbh = null;

    static private function connect ($disconnectFirst = false)
    {
        if (! $disconnectFirst && self::$dbh !== null) { return true; }
        try {
            self::disconnect();
            self::$dbh = new PDO(TTB_DB_CONN_STR, TTB_DB_USER, TTB_DB_PASSWORD, [ PDO::ATTR_PERSISTENT => true ]);
            return true;
        } catch (PDOException $e) {
            Log::register(Log::TYPE_DATABASE, "No es posible conectarse a la BD de BotBasic",
                          [ Log::ATTRIB_EXCEPTION, $e ]);
            return false;
        }
    }

    static public function disconnect ()
    {
        self::$dbh = null;
    }

    static private function dbError ()
    {
        if (self::$dbh === null) { return "DB_NOT_CONNECTED"; }
        $ei  = self::$dbh->errorInfo();
        $res = $ei[0] . ($ei[1] === null ? '' : '/' . $ei[1]) . ($ei[2] === null ? '/NONE' : '/' . $ei[2]);
        return $res;
    }

    static public function query ($sql, $when = null)
    {
        if (! self::connect() === null) {
            Log::register(Log::TYPE_DATABASE, "base de datos no conectada al intentar Query sobre: $sql", null);
            return [];
        }
        $res = self::$dbh->query($sql);
        if ($res === false) {
            $error = self::dbError() . ($when === null ? '' : " WHEN $when");
            Log::register(Log::TYPE_DATABASE, $error, null);
        }
        return $res;
    }

    static public function exec ($sql, $when = null, $returnLastInsertId = false)
    {
        if (self::connect() === null) {
            Log::register(Log::TYPE_DATABASE, "base de datos no conectada al intentar Exec sobre: $sql", null);
            return $returnLastInsertId ? -1 : 0;
        }
        $res = self::$dbh->exec($sql);
        if ($res === false) {
            $error = self::dbError() . ($when === null ? '' : " WHEN $when");
            Log::register(Log::TYPE_DATABASE, $error, null);
        }
        if ($returnLastInsertId) { return intval(self::$dbh->lastInsertId()); }
        else                     { return $res;                               }
    }

}
