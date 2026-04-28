<?php
namespace App\V2;

require_once __DIR__ . '/../Common/helpers.php'; // Importa las funciones comunes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;



class ArchivoController
{
    // VER TIPOS DE DOCUMENTOS
    public function pacienteUltimaAtencion(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $hc = $args['hc'] ?? null;
        
        $erro = 0;
        $datos = array();

        // verifico que haya recibido el tokenAcceso
        if(!isset($tokenAcceso[0])){
            $datos = array('estado' => 0,'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verifico si el token enviado es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if($hc == ''){ 
            $datos = array('estado' => 0, 'mensaje' => 'El número de HC es un dato obligatorio.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // $datos = array('estado' => 200, 'mensaje' => 'Buscando HC: '.$hc.'. Proximamente aquí. Paciencia... ya lo haré.');
        // $response->getBody()->write(json_encode($datos));
        // return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        // obtengo los datos
        $sql = 'EXEC pacienteUltimaAtencion @hc = :hc';
        try {
            $db = getConeccionCAB05();
            $stmt = $db->prepare($sql);
            $stmt->bindParam("hc", $hc);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $pac){
                $c = new \stdClass();
                $c->ultimaAtencion      = $pac->ultimaAtencion;
                $c->hc                  = $pac->hc;
                $c->paciente            = $pac->paciente;
                $c->tipoDocumento       = $pac->tipoDocumento;
                $c->nroDocumento        = $pac->nroDocumento;
                $c->sexo                = $pac->sexo;
                $c->fechaNacimiento     = $pac->fechaNacimiento;
                if (!empty($pac->pacientesMarkey)) {
                    $c->pacientesMarkey = json_decode($pac->pacientesMarkey, true);
                }else{
                    $c->pacientesMarkey = array();
                };

                array_push($datos,$c);
                unset($c);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        
    }
    

}
