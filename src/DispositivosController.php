<?php
namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


// Conexión a BD CAB en 105
function getConnectionTC() {
    $dbhost = $_ENV['DB_HOSTTABLEROCAMAS'];
    $dbuser = $_ENV['DB_USERTABLEROCAMAS'];
    $dbpass = $_ENV['DB_PASSTABLEROCAMAS'];
    $dbname = $_ENV['DB_NAMETABLEROCAMAS'];
    $dbh = new \PDO(
        "sqlsrv:Server=$dbhost;Database=$dbname;Encrypt=yes;TrustServerCertificate=yes",
        $dbuser,
        $dbpass
    );
    $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    return $dbh;
}

// Conexión a BD TableroCamas en 10.99.8.8
// function getConnection() {
//     $dbhost = $_ENV['DB_HOSTTC'];
//     $dbuser = $_ENV['DB_USERTC'];
//     $dbpass = $_ENV['DB_PASSTC'];
//     $dbname = $_ENV['DB_NAMETC'];
//     $dbh = new \PDO(
//         "sqlsrv:Server=$dbhost;Database=$dbname;Encrypt=yes;TrustServerCertificate=yes",
//         $dbuser,
//         $dbpass
//     );
//     $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
//     return $dbh;
// }

function verificarToken($token){
    //busco el token de acceso a la API almacenado en la tabla parámetros.
    $con = 'EXEC sp_parametro_ver @nombre = \'tokenAcceso\'';
    try {
        $stmt = getConnectionTC()->query($con);
        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
        if($token === $res[0]->valor ){
            // acceso permitido
            return true;
        }else{
            // acceso denegado
            return false;
        }
        $stmt = null;
    } catch(\PDOException $e) {
        return false;
    }
}


class DispositivosController
{
    // VER NOTIFICACIONES ENFERMERO
    public function validarDispositivo(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idDispositivo  = $request->getQueryParams()['idDispositivo'] ?? null;
        $idAplicacion   = $request->getQueryParams()['idAplicacion'] ?? null;

        $error = 0;
        $datos = array();

        if($idDispositivo == ''){
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
                    $sql = 'DECLARE	@return_value int,@mensaje varchar(255)
                            EXEC @return_value = dispositivoHabilitado @idDispositivo = :idDispositivo, @idAplicacion = :idAplicacion, @mensaje = @mensaje OUTPUT
                            SELECT	@return_value as return_value, @mensaje as mensaje';
                    try {
                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idDispositivo", $idDispositivo);
                        $stmt->bindParam("idAplicacion", $idAplicacion);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        $dispositivo = new \stdClass();
                        $dispositivo = $resultado[0];

                        $datos = array(
                            'estado' => $dispositivo->return_value,
                            'mensaje' => $dispositivo->mensaje
                        );
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
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

    // CERRAR SESION DISPOSITIVO
    public function cerrarSesionDispositivo_original(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $bodyParams = $request->getParsedBody();
        $loginDni = $bodyParams['loginDni'] ?? null;

        $error = 0;

        $datos = array();
        $r = new \stdClass();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido

                if($loginDni <> ''){
                    // registro el dispositivo
                    $sql = 'DECLARE	@return_value int,@mensaje varchar(255)
                            EXEC @return_value = cerrarSesionDipositivo @loginDni = :loginDni, @mensaje = @mensaje OUTPUT
                            SELECT	@return_value as estado, @mensaje as mensaje';    
                    
                    try {
                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("loginDni", $loginDni);
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
        $bodyParams = $request->getParsedBody();
        $loginDni = $bodyParams['loginDni'] ?? null;
        $idAplicacion = $bodyParams['idAplicacion'] ?? null;

        $sql = 'insert into logSesion (fecha,dni,idAplicacion) values (getdate(),:loginDni,:idAplicacion)';
                                
        $db = getConnectionTC();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("loginDni", $loginDni);
        $stmt->bindParam("idAplicacion", $idAplicacion);
        $stmt->execute();




        $error = 0;

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
                                @mensaje = @mensaje OUTPUT
                            SELECT	@return_value as estado, @mensaje as mensaje';    
                    
                    try {
                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("loginDni", $loginDni);
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
                    $datos = array('estado' => 0, 'mensaje' => 'Error: Faltan parámetros obligatorios. DNI');
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

    public function cerrarSesionDispositivo_new(Request $request, Response $response, $args){
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
                            EXEC @return_value = cerrarSesionDipositivo_v2
                                @loginDni = :loginDni, 
                                @idAplicacion = :idAplicacion,
                                @idAndroid = :idAndroid,
                                @mensaje = @mensaje OUTPUT
                            SELECT	@return_value as estado, @mensaje as mensaje';
                    
                    try {
                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("loginDni", $loginDni);
                        $stmt->bindParam("idAplicacion", $idAplicacion);
                        $stmt->bindParam("idAndroid", $idAndroid);
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

    // REGISTRAR UN DISPOSITIVO
    public function registrarDispositivo(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $bodyParams = $request->getParsedBody();
        $tokenFcm   = $bodyParams['tokenFcm'] ?? null;
        $loginDni   = $bodyParams['loginDni'] ?? null;
        $loginNombre   = $bodyParams['loginNombre'] ?? null;
        $loginApellido   = $bodyParams['loginApellido'] ?? null;
        $nombreDispositivo   = $bodyParams['nombreDispositivo'] ?? null;

        $error = 0;
        $datos = array();
        $r = new \stdClass();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido

                if(($tokenFcm <>'') and ($loginDni <> '') and ($loginNombre <> '') and ($loginApellido <> '') and ($nombreDispositivo <> '')){
                    // registro el dispositivo
                    // $sql = 'DECLARE	@return_value int,@mensaje varchar(255)
                    //         EXEC @return_value = registrarDispositivo @tokenFcm = \''.$tokenFcm.'\', @loginDni = \''.$loginDni.'\', @loginNombre = \''.$loginNombre.'\', @loginApellido = \''.$loginApellido.'\', @nombreDispositivo = \''.$nombreDispositivo.'\', @mensaje = @mensaje OUTPUT
                    //         SELECT	@return_value as estado, @mensaje as mensaje';
                    
                    $sql = 'DECLARE	@return_value int,@mensaje varchar(255)
                            EXEC @return_value = registrarDispositivo @tokenFcm = :tokenFcm, @loginDni = :loginDni, @loginNombre = :loginNombre, @loginApellido = :loginApellido, @nombreDispositivo = :nombreDispositivo, @mensaje = @mensaje OUTPUT
                            SELECT	@return_value as estado, @mensaje as mensaje';       
                    
                    try {
                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("tokenFcm", $tokenFcm);
                        $stmt->bindParam("loginDni", $loginDni);
                        $stmt->bindParam("loginNombre", $loginNombre);
                        $stmt->bindParam("loginApellido", $loginApellido);
                        $stmt->bindParam("nombreDispositivo", $nombreDispositivo);
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

    public function registrarDispositivo_v2(Request $request, Response $response, $args){
        $tokenAcceso            = $request->getHeader('TokenAcceso');
        $bodyParams = $request->getParsedBody();
        $tokenFcm   = $bodyParams['tokenFcm'] ?? null;
        $loginDni   = $bodyParams['loginDni'] ?? null;
        $loginNombre   = $bodyParams['loginNombre'] ?? null;
        $loginApellido = $bodyParams['loginApellido'] ?? null;
        $nombreDispositivo      = $bodyParams['nombreDispositivo'] ?? null;
        $idAndroid              = $bodyParams['idDispositivo'] ?? null;
        $idAplicacion           = $bodyParams['idAplicacion'] ?? null;

        $error = 0;
        $datos = array();
        $r = new \stdClass();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido

                if(($tokenFcm <>'') and ($idAplicacion <> '') and ($idAndroid <> '')){
                    // registro el dispositivo                    
                    
                    $sql = 'DECLARE	@return_value int,@mensaje varchar(255)
                            EXEC @return_value = registrarDispositivo_v2 
                                @tokenFcm = :tokenFcm, 
                                @loginDni = :loginDni, 
                                @loginNombre = :loginNombre, 
                                @loginApellido = :loginApellido, 
                                @nombreDispositivo = :nombreDispositivo, 
                                @idAndroid = :idAndroid,
                                @idAplicacion = :idAplicacion,
                                @mensaje = @mensaje OUTPUT
                            SELECT	@return_value as estado, @mensaje as mensaje';       
                    
                    try {
                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("tokenFcm", $tokenFcm);
                        $stmt->bindParam("loginDni", $loginDni);
                        $stmt->bindParam("loginNombre", $loginNombre);
                        $stmt->bindParam("loginApellido", $loginApellido);
                        $stmt->bindParam("nombreDispositivo", $nombreDispositivo);
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