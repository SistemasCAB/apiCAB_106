<?php
namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


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


class ClinicaController
{
    // FECHA Y HORA LOCAL
    public function fechaHoraCab(Request $request, Response $response, $args){
        $sql = 'select convert(varchar,GETDATE(),103) as fecha, convert(varchar,GETDATE(),108) as hora';
        $stmt = getConnection()->query($sql);
        $datos = $stmt->fetch(\PDO::FETCH_OBJ);
        $response->getBody()->write(json_encode($datos));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}