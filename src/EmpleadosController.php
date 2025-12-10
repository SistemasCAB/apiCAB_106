<?php

namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Conexión a BD portalEmpleados en 8.5
function getConnection() {
    $dbhost = $_ENV['DB_HOSTPE05'];
    $dbuser = $_ENV['DB_USERPE05'];
    $dbpass = $_ENV['DB_PASSPE05'];
    $dbname = $_ENV['DB_NAMEPE05'];
    $dbh = new \PDO(
        "sqlsrv:Server=$dbhost;Database=$dbname;Encrypt=yes;TrustServerCertificate=yes",
        $dbuser,
        $dbpass
    );
    $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    return $dbh;
}

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

function getConnection2() {
    $dbhost = $_ENV['DB_HOSTINF5'];
    $dbuser = $_ENV['DB_USERINF5'];
    $dbpass = $_ENV['DB_PASSINF5'];
    $dbname = $_ENV['DB_NAMEINF5'];
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

class EmpleadosController
{
    public function verEmpleadoPorLegajo(Request $request, Response $response, $args)
    {
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $legajo = $args['legajo'] ?? null;
        $datos = [];

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // token válido, continuar
                if ($legajo) {
                    $sql = 'SELECT id_usuario, employee_number_AD, id_usuario_AD, name_last, name_first FROM Usuario WHERE employee_number_AD = :legajo';
                    $db = getConnection();
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam('legajo', $legajo);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;

                    if (count($resultado) > 0) {               
                        $row = $resultado[0];

                        $datos = [
                            'id_usuario' => (int)$row->id_usuario,
                            'legajo' => (int)$row->employee_number_AD,
                            'usuario_AD' => $row->id_usuario_AD,
                            'apellido' => $row->name_last,
                            'nombre' => $row->name_first
                        ];
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                    } else {
                        $datos = ['estado' => 404, 'mensaje' => 'Empleado no encontrado'];
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                    }
                } else {
                    $datos = ['estado' => 400, 'mensaje' => 'Legajo no especificado'];
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
            } else {
                $datos = ['estado' => 401, 'mensaje' => 'Token de acceso inválido'];
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
        } else {
            $datos = ['estado' => 401, 'mensaje' => 'Token de acceso no proporcionado'];
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }


    public function crearEmpleado(Request $request, Response $response, $args)
    {
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $emp            = json_decode($json); // array con los parámetros recibidos.

        $error = 0;

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // token válido, continuar
                
                // Legajo
                if($emp->legajo == '' || $emp->legajo == null){
                    $error ++;
                }        

                // cuenta AD
                if($emp->cuenta == '' || $emp->cuenta == null){
                    $error ++;
                }
                // Nombre
                if($emp->nombre == '' || $emp->nombre == null){
                    $error ++;
                }
                // Apellido
                if($emp->apellido == '' || $emp->apellido == null){
                    $error ++;
                }


                
                if ($error == 0) {
                    // verifico que no exista un empleado con ese legajo o con esa cuenta AD
                    $sql = 'SELECT * FROM Usuario WHERE (employee_number_AD = :legajo) or (id_usuario_AD = :cuenta)';
                    $db = getConnection();
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam('legajo', $emp->legajo);
                    $stmt->bindParam('cuenta', $emp->cuenta);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;
                    if (count($resultado) > 0) {
                        $datos = ['estado' => 409, 'mensaje' => 'Ya existe un empleado con legajo '.$emp->legajo.' o con la cuenta '.$emp->cuenta.' de Active Directory'];
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                    }else{
                        // no existe, puedo crearlo
                        $sql = 'insert into Usuario (employee_number_AD, id_usuario_AD, name_last, name_first) values (:legajo, :cuenta, :apellido, :nombre)';
                    
                        $db = getConnection();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam('legajo', $emp->legajo);
                        $stmt->bindParam('cuenta', $emp->cuenta);
                        $stmt->bindParam('apellido', $emp->apellido);
                        $stmt->bindParam('nombre', $emp->nombre);
                        $stmt->execute();
                        
                        $db = null;

                        $datos = [
                                'estado' => 200,
                                'mensaje' => 'Se creo correctamente el empleado',
                                'legajo' => (int)$emp->legajo,
                                'cuenta' => $emp->cuenta,
                                'apellido' => $emp->apellido,
                                'nombre' => $emp->nombre
                            ];
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                    }
                    
                } else {
                    $datos = ['estado' => 400, 'mensaje' => 'Los datos ingresados son incompletos o inválidos'];
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }

            } else {
                $datos = ['estado' => 403, 'mensaje' => 'Token inválido. Acceso no autorizado'];
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
        } else {
            $datos = ['estado' => 403, 'mensaje' => 'Acceso no autorizado'];
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
    }
}