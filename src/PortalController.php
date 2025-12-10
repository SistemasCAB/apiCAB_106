<?php
namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Incluir la clase Markey
require_once __DIR__ . '/../class/Markey.php';

// Conexión a BD CAB en 105
function getConnection() {
    $dbhost = $_ENV['DB_HOSTCAB'];
    $dbuser = $_ENV['DB_USERCAB'];
    $dbpass = $_ENV['DB_PASSCAB'];
    $dbname = $_ENV['DB_NAMECAB'];
    $dbh = new \PDO(
        "sqlsrv:Server=$dbhost;Database=$dbname;Encrypt=yes;TrustServerCertificate=yes",
        $dbuser,
        $dbpass
    );
    $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    return $dbh;
}

// Conexión a BD Markey en producción
function getConnectionMarkey() {
    $dbhost = $_ENV['DB_HOSTMARKEY'];
    $dbuser = $_ENV['DB_USERMARKEY'];
    $dbpass = $_ENV['DB_PASSMARKEY'];
    $dbname = $_ENV['DB_NAMEMARKEY'];
    $dbh = new \PDO(
        "sqlsrv:Server=$dbhost;Database=$dbname;Encrypt=yes;TrustServerCertificate=yes",
        $dbuser,
        $dbpass
    );
    $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    return $dbh;
}

function getConnectionCAB05() {
    $dbhost = $_ENV['DB_HOSTCAB05'];
    $dbuser = $_ENV['DB_USERCAB05'];
    $dbpass = $_ENV['DB_PASSCAB05'];
    $dbname = $_ENV['DB_NAMECAB05'];
    $dbh = new \PDO(
        "sqlsrv:Server=$dbhost;Database=$dbname;Encrypt=yes;TrustServerCertificate=yes",
        $dbuser,
        $dbpass
    );
    $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    return $dbh;
}



function verificarToken($token){
    //busco el token de acceso a la API almacenado en la tabla parámetros.
    $con = 'EXEC sp_parametro_ver @nombre = \'tokenAcceso\'';
    try {
        $stmt = getConnection()->query($con);
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

function obtenerUbicacionUsuario($dni){
    //busco en la base de datos de Markey si el usuario tiene asignada una ubicación.
    // devuelve true en caso de que el usuario tenga una ubicación asignada, lo que significa que es personal de la CAB.
    // devuelve false en caso de que el usuario no tenga una ubicación asignada, lo que significa que es un paciente u otro tipo de usuario.
    $sql = 'EXEC MKY_usuarioUbicacion @nroDocumento = :dni';
    $db = getConnectionCAB05();
    $stmt = $db->prepare($sql);
    $stmt->bindParam("dni", $dni);
    $stmt->execute();
    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
    $db = null;
    if(count($resultado) >= 1){
        // el usuario tiene una ubicación asignada
        return true;
    }else{
        // el usuario no tiene una ubicación asignada
        return false;
    }
}

class PortalController
{
    // LOGIN
    public function loginPortal(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $dni            = $request->getQueryParams()['dni'] ?? null;
        $clave          = $request->getQueryParams()['clave'] ?? null;

        $error = 0;
        if(($dni == '') or ($clave == '')){
            $error ++;
        }

        if($error == 0){
            if(isset($tokenAcceso[0])){
                if(verificarToken($tokenAcceso[0]) === true){
                    // acceso permitido - intento loguear con Markey
                    $markey = new \Markey();
                    $loginMarkey = new \Markey;
                    $login = $loginMarkey->loginMarkey($dni, $clave); 

                    if(! is_null($login)){
                        if($login->estado == 1){
                            // login correcto

                            // busco en Markey si el usuario tiene asignada una ubicación. En tal caso significa que es personal de la CAB y no un paciente.
                            if(obtenerUbicacionUsuario($dni) === true){
                                // el usuario es personal de la CAB
                                $datos = array(
                                    'estado' => 200, 
                                    'loginApellido' => $login->apellido,
                                    'loginNombre' => $login->nombre,
                                    'loginDni' => $login->dni
                                );

                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                            }else{
                                // el usuario no es personal de la CAB, es un paciente u otro tipo de usuario.
                                $datos = array(
                                    'estado' => 403, 
                                    'mensaje' => 'Acceso denegado. No estás autorizado a usar esta aplicación.'
                                );
                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                            }
                        }else{
                            $datos = array('estado' => 401, 'mensaje' => 'Acceso denegado. Documento o contraseña incorrectos.');
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
                        }
                    }else{
                        // error en el login - acceso denegado
                        $datos = array('estado' => 401, 'mensaje' => 'Acceso denegado. Documento y/o contraseña incorrectos.');
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
                    }
                }else{
                    // acceso denegado
                    $datos = array('estado' => 401, 'mensaje' => 'Acceso denegado. Token inválido.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
                }
            }else{
                //acceso denegado. No envió el token de acceso
                $datos = array('estado' => 401, 'mensaje' => 'Acceso denegado. Token no recibido.');
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
        }else{
            //acceso denegado. Faltan parámetros obligatorios
            $datos = array('estado' => 401, 'mensaje' => 'Acceso denegado. Usuario y/o contraseña incorrectos.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }
}