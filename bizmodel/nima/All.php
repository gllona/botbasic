<?php



namespace nima;



/**
 * Class All
 *
 * @package nima
 */
class All
{



    static public function Motd ($id)
    {
        if (! DBbroker::connect()) { return []; }
        if (! $id) {
            $sql = <<<END
                SELECT id, texto, siguiente
                  FROM motd
                 WHERE id NOT IN (SELECT DISTINCT siguiente FROM motd WHERE siguiente IS NOT NULL) 
                 ORDER BY updated DESC
                 LIMIT 1;
END;
        }
        else {
            $sql = <<<END
                SELECT id, texto, siguiente
                  FROM motd
                 WHERE id = $id;
END;
        }
        $data = DBbroker::query($sql);
        return count($data) > 0 ? array_values($data[0]) : [ null, null, null ];
    }



    static public function PollPregunta ($bmUserId)
    {
        if (! DBbroker::connect()) { return []; }
        $sql = <<<END
            SELECT id, texto, opciones
              FROM pregunta
             WHERE origen = 'nima'
               AND expirada = 0
               AND id NOT IN (SELECT pregunta FROM preg_respondida WHERE usuario = $bmUserId)
             ORDER BY updated DESC
             LIMIT 1;
END;
        $data = DBbroker::query($sql);
        if ($data === false) { return Log::register('NIMA62', 3); }
        return count($data) > 0 ? array_values($data[0]) : [ null, null, null ];
    }



    static public function PollRespuesta ($idPregunta, $respuesta, $bmUserId)
    {
        if (! DBbroker::connect()) { return []; }
        // cancelar si la pregunta ya esta expirada
        $sql = <<<END
            SELECT COUNT(*) AS cuenta
              FROM pregunta
             WHERE id = $idPregunta
               AND expirada = 0;
END;
        $data = DBbroker::query($sql);
        if ($data === false)         { return Log::register('NIMA79'); }
        if ($data[0]['cuenta'] == 0) { return [];                    }
        // insertar la respuesta
        $respuesta = DBbroker::q($respuesta);
        $sql = <<<END
            INSERT INTO respuesta
               SET texto = $respuesta, pregunta = $idPregunta; 
END;
        $res = DBbroker::exec($sql);
        if ($res === false) { return Log::register('NIMA87'); }
        // incrementar contador de valoraciones de la pregunta
        $sql = <<<END
            UPDATE pregunta
               SET valoraciones = valoraciones + 1
             WHERE id = $idPregunta; 
END;
        $res = DBbroker::exec($sql);
        if ($res === false) { return Log::register('NIMA87'); }
        // insertar el registro de que el usuario ya respondio la pregunta
        $sql = <<<END
            INSERT INTO preg_respondida
               SET pregunta = $idPregunta, usuario = $bmUserId;
END;
        $res = DBbroker::exec($sql);
        if ($res === false) { return Log::register('NIMA162'); }
        // ready
        return [];
    }



    static public function RegistrarPregunta ($texto)
    {
        if (! DBbroker::connect()) { return []; }
        $texto = DBbroker::q($texto);
        $sql = <<<END
            INSERT INTO pregunta
               SET origen = 'gente', texto = $texto; 
END;
        $res = DBbroker::exec($sql);
        if ($res === false) { return Log::register('NIMA113'); }
        return [];
    }



    static public function PreguntaGente ($bmUserId)
    {
        if (! DBbroker::connect()) { return [ null, null ]; }
        $sql = <<<END
            SELECT id, texto, cantidad_respuestas
              FROM ((
                SELECT 1 AS sort_col, id, texto, 0 AS cantidad_respuestas, valoraciones, updated
                  FROM pregunta
                 WHERE origen = 'gente'
                   AND id NOT IN (SELECT pregunta FROM respuesta)
              ) UNION (
                SELECT 2, p.id, p.texto, COUNT(*) AS cantidad_respuestas, p.valoraciones, p.updated
                  FROM pregunta AS p
             LEFT JOIN respuesta AS r ON r.pregunta = p.id
                 WHERE p.origen = 'gente'
                   AND p.id NOT IN (SELECT pregunta FROM preg_respondida WHERE usuario = $bmUserId)
                 GROUP BY id, texto
              )) AS q
             ORDER BY sort_col, cantidad_respuestas ASC, valoraciones DESC, updated DESC
             LIMIT 1;
END;
        $data = DBbroker::query($sql);
        if ($data === false)   { return Log::register('NIMA134', 2); }
        if (count($data) == 0) { return [ null, null ];              }
        list ($id, $texto, ) = array_values($data[0]);
        return [ $id, $texto ];
    }




    static public function AgregarRespuesta ($idPregunta, $valor, $respuesta, $bmUserId)
    {
        if (! DBbroker::connect()) { return []; }
        // insertar la respuesta
        if ($respuesta != '') {
            $respuesta = DBbroker::q($respuesta);
            $sql = <<<END
                INSERT INTO respuesta
                   SET texto = $respuesta, pregunta = $idPregunta;
END;
            $res = DBbroker::exec($sql);
            if ($res === false) { return Log::register('NIMA143'); }
        }
        // incrementar contador de valoraciones de la pregunta, y afectar puntuacion
        $sql = <<<END
            UPDATE pregunta
               SET valoraciones = valoraciones + 1, puntos = puntos + $valor
             WHERE id = $idPregunta; 
END;
        $res = DBbroker::exec($sql);
        if ($res === false) { return Log::register('NIMA151'); }
        // insertar el registro de que el usuario ya respondio la pregunta
        $sql = <<<END
            INSERT INTO preg_respondida
               SET pregunta = $idPregunta, usuario = $bmUserId;
END;
        $res = DBbroker::exec($sql);
        if ($res === false) { return Log::register('NIMA162'); }
        // ready
        return [];
    }



    static public function Preguntas ($desde)
    {
        $num                = 10;   // numero de preguntas a retornar, maximo $numBB
        $numBB              = 10;   // numero de preguntas que espera BB
        $importanceOfOlders = 3;    // importancia de respuestas de dias anteriores; 0 para ninguna; incrementar para asignarla
        $max                = $num + 1;
        $desde--;
        // begin
        if (! DBbroker::connect()) { return array_fill(0, 1 + 2 * $numBB, null); }
        $sql = <<<END
            SELECT p.id, p.texto, SUM(r.puntos) AS puntos_respuestas
              FROM pregunta AS p
              JOIN respuesta AS r ON r.pregunta = p.id
             WHERE p.origen = 'gente'
             GROUP BY p.id, p.texto
             ORDER BY SUM(r.puntos) / (1 + $importanceOfOlders + DATEDIFF(NOW(), p.updated)) DESC
             LIMIT $desde, $max;
END;
        $data = DBbroker::query($sql);
        if ($data === false) { return Log::register('NIMA173', 1 + 2 * $numBB); }
        $res = [ $numReal = count($data) ];
        foreach ($data as $row) {
            $res[] = $row['id'];
            $res[] = $row['texto'];
            if ((count($res) - 1) / 2 >= $num) { break; }
        }
        for ($i = 0; $i < 2 * ($numBB - $numReal); $i++) { $res[] = null; }
        return $res;
    }



    static public function Respuestas ($idPregunta)
    {
        $num   = 7;    // numero de respuestas a retornar, maximo $numBB
        $numBB = 10;   // numero de respuestas que espera BB
        // begin
        if (! DBbroker::connect()) { return array_fill(0, 1 + 2 * $numBB, null); }
        $sql = <<<END
            SELECT id, texto
              FROM respuesta
             WHERE pregunta = $idPregunta
             ORDER BY puntos DESC, updated DESC
             LIMIT $num;
END;
        $data = DBbroker::query($sql);
        if ($data === false) { return Log::register('NIMA199', 1 + 2 * $numBB); }
        $res = [ $num = count($data) ];
        foreach ($data as $row) {
            $res[] = $row['id'];
            $res[] = $row['texto'];
        }
        for ($i = 0; $i < 2 * ($numBB - $num); $i++) { $res[] = null; }
        return $res;
    }



    static public function ValorarRespuesta ($idRespuesta, $valor)
    {
        if (! DBbroker::connect()) { return []; }
        $sql = <<<END
            UPDATE respuesta
               SET valoraciones = valoraciones + 1, puntos = puntos + $valor
             WHERE id = $idRespuesta
               AND pregunta IN (SELECT id FROM pregunta WHERE expirada = 0); 
END;
        $res = DBbroker::exec($sql);
        if ($res === false) { return Log::register('NIMA220'); }
        return [];
    }



}
