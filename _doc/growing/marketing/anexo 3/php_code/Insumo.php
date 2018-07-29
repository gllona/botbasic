<?php

class Insumo
{

    static public function listar ($idCategoria)
    {
        $sql = <<<END
            SELECT id, nombre
              FROM insumo
             WHERE id_categoria = $idCategoria
             ORDER BY nombre ASC;
END;
        $rows = DBbroker::query($sql);
        $res = [];
        foreach ($rows as $row) { $res[] = $row['nombre'] . '|' . $row['id']; }
        return $res;
    }

}

