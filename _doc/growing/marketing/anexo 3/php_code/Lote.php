<?php

class Lote
{

    static public function crear ()
    {
        $sql = <<<END
            INSERT INTO lote;
END;
        return DBbroker::exec($sql, null, true);
    }

    static public function solicitudes ($idLote)
    {
        $sql = <<<END
            SELECT id_solicitud
              FROM solicitud 
             WHERE id_lote = $idLote;
END;
        $rows = DBbroker::query($sql);
        $res = [];
        foreach ($rows as $row) { $res[] = $row["id_solicitud"]; }
        return $res;
    }

    static public function recibir ($idLote, $idEmpleado)
    {
        $solicitudes = self::solicitudes($idLote);
        foreach ($solicitudes as $solicitud) {
            Solicitud::recibir($solicitud, $idEmpleado);
        }
    }

    static public function esValido ($idLote)
    {
        $sql = <<<END
            SELECT COUNT(*) AS cuenta
              FROM lote 
             WHERE id = $idLote;
END;
        $rows = DBbroker::query($sql);
        return $rows[0]["cuenta"] != 0;
    }

}

