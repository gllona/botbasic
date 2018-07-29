<?php

class Solicitud
{

    static public function generar ($idEmpleado, $idInsumo, $cantidad, $plazo, $observaciones)
    {
        $sql = <<<END
            INSERT INTO solicitud (id_insumo, cantidad, fecha, plazo, observaciones, id_generador, estado)
            VALUES ($idInsumo, $cantidad, NOW(), '$plazo', '$observaciones', $idEmpleado, 'solicitado');
END;
        return DBbroker::exec($sql, null, true);
    }

    static public function aprobar ($idSolicitud, $idEmpleado)
    {
        $sql = <<<END
            UPDATE solicitud
               SET id_aprobador = $idEmpleado, estado = 'aprobado'
             WHERE id_solicitud = $idSolicitud;
END;
        DBbroker::exec($sql);
    }

    static public function negar ($idSolicitud, $idEmpleado)
    {
        $sql = <<<END
            UPDATE solicitud
               SET id_negador = $idEmpleado, estado = 'negado'
             WHERE id_solicitud = $idSolicitud;
END;
        DBbroker::exec($sql);
    }

    static public function despachar ($idSolicitud)
    {
        $sql = <<<END
            UPDATE solicitud
               SET estado = 'despachado'
             WHERE id_solicitud = $idSolicitud;
END;
        DBbroker::exec($sql);
    }

    static public function recibir ($idSolicitud, $idEmpleado)
    {
        $sql = <<<END
            UPDATE solicitud
               SET id_recibidor = $idEmpleado, estado = 'recibido'
             WHERE id_solicitud = $idSolicitud;
END;
        DBbroker::exec($sql);
    }

    static public function incluirEnLote ($idSolicitud, $idLote)
    {
        $sql = <<<END
            UPDATE solicitud
               SET id_lote = $idLote
             WHERE id_solicitud = $idSolicitud;
END;
        DBbroker::exec($sql);
    }

    static public function enRango ($diasMinimos, $diasMaximos)
    {
        $sql = <<<END
            SELECT id_solicitud
              FROM solicitud
             WHERE DATE_DIFF(NOW(), DATE_ADD(fecha, INTERVAL CASE WHEN plazo = '3 dias' THEN 3
                                                                  WHEN plazo = '7 dias' THEN 7
                                                                  WHEN plazo = '15 dias' THEN 15
                                                                  WHEN plazo = '1 mes' THEN 30
                                                                  WHEN plazo = '3 meses' THEN 90 END DAY) 
                   BETWEEN $diasMinimos AND $diasMaximos;
END;
        $rows = DBbroker::query($sql);
        $res = [];
        foreach ($rows as $row) { $res[] = $row["id_solicitud"]; }
        return $res;
    }

    static public function datos ($idSolicitud)
    {
        $sql = <<<END
            SELECT i.nombre, c.nombre, s.cantidad, s.estado, s.fecha, s.plazo, 
                   s.observaciones, es.nombre, ea.nombre, en.nombre, er.nombre, u.nombre
              FROM solicitud AS s
              JOIN insumo AS i ON s.id_insumo = i.id
              JOIN categoria AS c ON i.id_categoria = c.id
              JOIN empleado AS es ON s.id_solicitante = es.id
              JOIN unidad AS u ON es.id_unidad = u.id
              LEFT JOIN empleado AS ea ON s.id_aprobador = ea.id
              LEFT JOIN empleado AS en ON s.id_negador = en.id
              LEFT JOIN empleado AS er ON s.id_recibidor = er.id
             WHERE s.id = $idSolicitud;
END;
        $rows = DBbroker::query($sql);
        return count($rows) > 0 ? array_values($rows[1]) : array_fill(0, 12, '');
    }

    static public function plazos ()
    {
        return [ '3 dias', '7 dias', '15 dias', '1 mes', '3 meses' ];
    }

    static public function estado ($idSolicitud)
    {
        $datos = self::datos($idSolicitud);
        return $datos[3];
    }

}

