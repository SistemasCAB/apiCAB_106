<?php
namespace App\V2;

require_once __DIR__ . '/../Common/helpers.php'; // Importa las funciones comunes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


class PruebasController
{    
    
    public function pruebaFechaSQL(Request $request, Response $response, $args){        
        // $json           = $request->getBody();
        // $datosNotif     = json_decode($json); // array con los parámetros recibidos.

        // $fecha = $datosNotif->fecha;
        // $nombre = $datosNotif->nombre;

        $fechaRaw = '2025-01-02 09:30:00';
        $nombre = 'Prueba desde PHP';

        try {
            $dt = new \DateTime($fechaRaw);
            $fecha = $dt->format('Ymd H:i:s');
        } catch (\Exception $e) {
            $datos = ['error' => 'Formato de fecha inválido'];
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $sql = 'DECLARE	@return_value int
                EXEC @return_value = pruebaFecha
                    @fecha = :fecha,
                    @nombre = :nombre
                SELECT	@return_value as idNotificacion';

        $db = getConeccionCAB();
        //$db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        
        try {
            $stmt = $db->prepare($sql);
            // Cambiamos el formato de fecha a uno más estricto
            // $fecha = $dt->format('Ymd H:i:s');
            //$stmt->bindValue(":fecha", $fecha, \PDO::PARAM_STR);
            $stmt->bindValue(":fecha", $fecha);
            $stmt->bindValue(":nombre", $nombre, \PDO::PARAM_STR);
            $stmt->execute();
        } catch (\PDOException $e) {
            $datos = ['error' => $e->getMessage()];
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $a = new \stdClass();
        $a = $resultado[0];
        $idNotificacion = (int)$a->idNotificacion;
        unset($a);
        $db = null;

        $datos = array(
            'nombre' => $nombre, 
            'fecha' => $fecha, 
            'idNotificacion' => $idNotificacion
        );
        $response->getBody()->write(json_encode($datos));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

    }    
}