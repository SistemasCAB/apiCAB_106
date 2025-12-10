<?php
namespace App\V2;

require_once __DIR__ . '/../Common/helpers.php'; // Importa las funciones comunes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


class DispositivosController_v2
{
    // VER NOTIFICACIONES ENFERMERO
    public function validarDispositivo(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idAndroid      = $request->getQueryParams()['idAndroid'] ?? null;
        $idAplicacion   = $request->getQueryParams()['idAplicacion'] ?? null;

        // //grabo el log
        // $conLog = 'insert into logValidarDispositivo(idAndroid, idAplicacion) values (:idAndroid, :idAplicacion)';  
        // $db = getConeccionCAB();
        // $stmt = $db->prepare($conLog);
        // $stmt->bindParam(':idAndroid', $idAndroid);
        // $stmt->bindParam(':idAplicacion', $idAplicacion);
        // $stmt->execute();
        // $db = null; 


        $error = 0;
        $datos = array();

        if($idAndroid == ''){
            $error ++;
        }

        if($idAplicacion == ''){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    // verifico si el dispositivo está habiltado para usar la aplicación.
                    // registro el dispositivo
                    $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC @return_value = dispositivoHabilitado @idAndroid = :idAndroid, @idAplicacion = :idAplicacion, @mensaje = @mensaje OUTPUT
                            SELECT	@return_value as estado, @mensaje as mensaje';
                    try {
                        $db = getConeccionCAB(); 
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idAndroid", $idAndroid);
                        $stmt->bindParam("idAplicacion", $idAplicacion);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        //var_dump($resultado);

                        $datos = array(
                            'estado' => (int)$resultado[0]->estado,
                            'mensaje' => $resultado[0]->mensaje
                        );

                        
                        if($resultado[0]->estado == 1){
                            // dispositivo habilitado                            
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }else{
                            // dispositivo no habilitado
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                        }
                    } catch(PDOException $e) {
                        $datos = array(
                            'estado' => 0,
                            'mensaje' => $e->getMessage()
                        );
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                    }
                }else{
                    $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }else{
                // acceso denegado
                $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        }else{
            //acceso denegado. No envió el token de acceso
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
    }

    public function registrarDispositivo(Request $request, Response $response, $args){
        $tokenAcceso            = $request->getHeader('TokenAcceso');
        $json                   = $request->getBody();
        $datosDisp              = json_decode($json); // array con los parámetros recibidos.

        $tokenFcm               = $datosDisp->tokenFcm ?? null;
        $loginDni               = $datosDisp->loginDni ?? null;
        $loginNombre            = $datosDisp->loginNombre ?? null;
        $loginApellido          = $datosDisp->loginApellido ?? null;
        $idAndroid              = $datosDisp->idAndroid ?? null;
        $idAplicacion           = $datosDisp->idAplicacion ?? null;

        $error = 0;
        $datos = array();
        $r = new \stdClass();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido

                if(($tokenFcm <>'') and ($idAplicacion <> '') and ($idAndroid <> '') and ($loginDni <> '')){
                    // registro el dispositivo                    
                    
                    $sql = 'DECLARE	@return_value int,@mensaje varchar(255)
                            EXEC @return_value = registrarDispositivo 
                                @tokenFcm = :tokenFcm, 
                                @loginDni = :loginDni, 
                                @loginNombre = :loginNombre, 
                                @loginApellido = :loginApellido, 
                                @idAndroid = :idAndroid,
                                @idAplicacion = :idAplicacion,
                                @mensaje = @mensaje OUTPUT
                            SELECT	@return_value as estado, @mensaje as mensaje';       
                    
                    try {
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("tokenFcm", $tokenFcm);
                        $stmt->bindParam("loginDni", $loginDni);
                        $stmt->bindParam("loginNombre", $loginNombre);
                        $stmt->bindParam("loginApellido", $loginApellido);
                        $stmt->bindParam("idAndroid", $idAndroid);
                        $stmt->bindParam("idAplicacion", $idAplicacion);
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;
                        array_push($datos,$res[0]);

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                    } catch(PDOException $e) {
                        $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                    }
                }else{
                    //acceso denegado. No envió los parámetros obligatorios                
                    $datos = array('estado' => 0, 'mensaje' => 'Error: Faltan parámetros obligatorios.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }else{
                // acceso denegado
                $datos = array('estado' => 0, 'mensaje' => 'Acceso no autorizado.');
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        }else{
            //acceso denegado. No envió el token de acceso
            $datos = array('estado' => 0, 'mensaje' => 'Acceso no autorizado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
    }

    public function cerrarSesionDispositivo(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datos          = json_decode($json); // array con los parámetros recibidos.
                
        $idAndroid      = $datos->idAndroid ?? null;
        $loginDni       = $datos->loginDni ?? null;
        $idAplicacion   = $datos->idAplicacion ?? null;
                
        $error = 0;

        if(empty($idAndroid) || empty($loginDni) || empty($idAplicacion)){ 
            $error ++;
        }
            

        $datos = array();
        $r = new \stdClass();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido

                if($loginDni <> ''){
                    $sql = 'DECLARE	@return_value int,@mensaje varchar(255)
                            EXEC @return_value = cerrarSesionDipositivo
                                @loginDni = :loginDni, 
                                @idAplicacion = :idAplicacion,
                                @idAndroid = :idAndroid,
                                @mensaje = @mensaje OUTPUT
                            SELECT	@return_value as estado, @mensaje as mensaje';
                    
                    try {
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam(":loginDni", $loginDni);
                        $stmt->bindParam(":idAplicacion", $idAplicacion);
                        $stmt->bindParam(":idAndroid", $idAndroid);
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;
                        array_push($datos,$res[0]);

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                    } catch(PDOException $e) {
                        $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                    }
                }else{
                    //acceso denegado. No envió los parámetros obligatorios                
                    $datos = array('estado' => 0, 'mensaje' => 'Error: Faltan parámetros obligatorios.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }else{
                // acceso denegado
                $datos = array('estado' => 0, 'mensaje' => 'Acceso no autorizado.');
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        }else{
            //acceso denegado. No envió el token de acceso
            $datos = array('estado' => 0, 'mensaje' => 'Acceso no autorizado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
    }

    // VERIFICAR SESION INICIADA
    public function verificarSesionIniciada(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idDispositivo  = $request->getQueryParams()['idDispositivo'] ?? null;
        $idAplicacion   = $request->getQueryParams()['idAplicacion'] ?? null;
        $loginDni       = $request->getQueryParams()['loginDni'] ?? null;

        $error = 0;
        $datos = array();

        if (empty($idDispositivo)) $error++;
        if (empty($idAplicacion)) $error++;
        if (empty($loginDni)) $error++;
        

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                if($error == 0){
                    // verifico si el dispositivo está habiltado para usar la aplicación.
                    // registro el dispositivo
                    $sql = 'DECLARE	@return_value int,@mensaje varchar(255)
                            EXEC @return_value = dispositivoVerificarSesionIniciada @loginDni = :loginDni, @idDispositivo = :idDispositivo, @idAplicacion = :idAplicacion, @mensaje = @mensaje OUTPUT
                            SELECT	@return_value as return_value, @mensaje as mensaje';
                    try {
                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("loginDni", $loginDni);
                        $stmt->bindParam("idDispositivo", $idDispositivo);
                        $stmt->bindParam("idAplicacion", $idAplicacion);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        $sesion = new \stdClass();
                        $sesion = $resultado[0];

                        if($sesion->return_value == 1){
                            $statusCode = 200;
                        }else{
                            $statusCode = 403;
                        }
                        $datos = array(
                            'estado' => (int)$sesion->return_value,
                            'mensaje' => $sesion->mensaje
                        );
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);

                    } catch(PDOException $e) {
                        $datos = array(
                            'estado' => 0,
                            'mensaje' => $e->getMessage()
                        );
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                    }
                }else{
                    $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }else{
                // acceso denegado
                $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        }else{
            //acceso denegado. No envió el token de acceso
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
    }

}