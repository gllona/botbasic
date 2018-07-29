<?php

class Empleado
{

    static public function esValido ($celular)
    {
        $sql = <<<END
            SELECT id
              FROM empleado 
             WHERE celular = '$celular';
END;
        $rows = DBbroker::query($sql);
        return count($rows) == 0 ? -1 : $rows[0]["id"];
    }

    static public function puedeAprobarNegar ($idEmpleado)
    {
        $sql = <<<END
            SELECT puede_aprobar
              FROM empleado 
             WHERE id = '$idEmpleado';
END;
        $rows = DBbroker::query($sql);
        return count($rows) == 0 ? 0 : $rows[0]["puede_aprobar"] != 0;
    }

    static public function puedeRecibir ($idEmpleado)
    {
        $sql = <<<END
            SELECT puede_recibir
              FROM empleado 
             WHERE id = '$idEmpleado';
END;
        $rows = DBbroker::query($sql);
        return count($rows) == 0 ? 0 : $rows[0]["puede_recibir"] != 0;
    }

    static public function aprobador ($idEmpleado)
    {
        $sql = <<<END
            SELECT s.id AS id
              FROM empleado AS s
              JOIN empleado AS e ON e.id_unidad = s.id_unidad
             WHERE e.id = '$idEmpleado'
             LIMIT 1;
END;
        $rows = DBbroker::query($sql);
        return count($rows) == 0 ? 0 : $rows[0]["id"];
    }

    static public function puedeRecibirLote ($idEmpleado, $idLote)
    {
        // hallar la unidad del empleado recibidor
        $sql = <<<END
            SELECT id_unidad
              FROM empleado
             WHERE id = $idEmpleado;
END;
        $rows = DBbroker::query($sql);
        if (count($rows) == 0) { return false; }
        $idUnidadEmpleado = $rows["id_unidad"];
        // contrastar con la unidad de cada empleado solicitante de cada solicitud del lote
        $sql = <<<END
            SELECT e.id_unidad AS id_unidad
              FROM empleado AS e
              JOIN solicitud AS s ON s.id_generador = e.id
             WHERE s.id_lote = $idLote;
END;
        $rows = DBbroker::query($sql);
        foreach ($rows as $row) {
            if ($row["id_unidad"] != $idUnidadEmpleado) { return false; }
        }
        return true;
    }

}

