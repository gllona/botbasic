<?php

class Categoria
{

    static public function listar ()
    {
        $sql = <<<END
            SELECT id, nombre
              FROM categoria
             ORDER BY nombre ASC;
END;
        $rows = DBbroker::query($sql);
        $res = [];
        foreach ($rows as $row) { $res[] = $row['nombre'] . '|' . $row['id']; }
        return $res;
    }

}

