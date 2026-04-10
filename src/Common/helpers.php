<?php
namespace App\V2;

use PDO;
use PDOException;

// Conexión a BD CAB en 10.99.8.8
function getConeccionCAB() {
    $dbhost = $_ENV['DB_HOSTCAB8'];
    $dbuser = $_ENV['DB_USERCAB8'];
    $dbpass = $_ENV['DB_PASSCAB8'];
    $dbname = $_ENV['DB_NAMECAB8'];
    $dbh = new \PDO(
        "sqlsrv:Server=$dbhost;Database=$dbname;Encrypt=yes;TrustServerCertificate=yes",
        $dbuser,
        $dbpass
    );
    $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    return $dbh;
}

function getConneccionMySql() {
    $dbhost="10.99.8.107";
    $dbuser="root";
    $dbpass="HOwQGPLcjS9utPHG";
    $dbname="soporte";
    $dbh = new \PDO(
        "mysql:host=$dbhost;dbname=$dbname", 
        $dbuser, 
        $dbpass
    );
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $acentos = $dbh->query("SET NAMES 'utf8'");
    return $dbh;
}


function verificarToken($token) {
    try {
        $sql = "EXEC verificarTokenAPI @tokenAcceso = :tokenAcceso";
        $db = getConeccionCAB();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(":tokenAcceso", $token, PDO::PARAM_STR);
        $stmt->execute();

        // Salta posibles conjuntos vacíos
        do {
            $resultado = $stmt->fetchAll(PDO::FETCH_OBJ);
        } while ($stmt->nextRowset() && empty($resultado));

        $db = null;

        return !empty($resultado);
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        return false;
    }
}

function fechaHoraServidorSQL(){
    $sql = 'select dbo.fn_FechaHoraServidor() as fechaHora';
    $stmt = getConeccionCAB()->query($sql);
    $resultado = $stmt->fetchAll(PDO::FETCH_OBJ);
    $db = null;
    foreach($resultado as $fec){
         $f = $fec->fechaHora;
    }
	return $f;
}

function crearTareaLimpieza($idCama, $dni, $nombreUsuario, $idServicio){
    $sql = "EXEC TareaLimpieza_crear @idCama = :idCama, @solicitadaPorDni = :dni, @solicitadaPorNombre = :nombreUsuario, @idServicioSolicita = :idServicio";
    $db = getConeccionCAB();
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":idCama", $idCama, PDO::PARAM_INT);
    $stmt->bindParam(":dni", $dni, PDO::PARAM_STR);
    $stmt->bindParam(":nombreUsuario", $nombreUsuario, PDO::PARAM_STR);
    $stmt->bindParam(":idServicio", $idServicio, PDO::PARAM_INT);
    $stmt->execute();
    $db = null;
    return true;
}

function limpiarAlertasHistoriasCama($idCama){
    $sql = 'EXEC alertasMarcarNoVisible @idCama = :idCama';
    $db = getConeccionCAB();
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":idCama", $idCama, PDO::PARAM_INT);
    $stmt->execute();
    $db = null;
    return true;
}

function crearAlertaCamaDisponible($idCama){
    $sql = 'declare @mensaje varchar(255)
            EXEC alertasNueva @idCama = :idCama, @idAlerta = 13, @mensaje = @mensaje OUTPUT';
    $db = getConeccionCAB();
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":idCama", $idCama, PDO::PARAM_INT);
    $stmt->execute();
    $db = null;
    return true;
}

function bitacoraRegistrarCambioEstadoCama($idCama, $idEvento, $dni, $nombreUsuario){
    $sql = 'EXEC BitacoraCama_registrar
                    @idCama = :idCama,
                    @idEvento = :idEvento,
                    @dni = :dni,
                    @nombreUsuario = :nombreUsuario';
    $db = getConeccionCAB();
    $stmt = $db->prepare($sql);
    $stmt->bindParam(":idCama", $idCama, PDO::PARAM_INT);
    $stmt->bindParam(":idEvento", $idEvento, PDO::PARAM_INT);
    $stmt->bindParam(":dni", $dni, PDO::PARAM_STR);
    $stmt->bindParam(":nombreUsuario", $nombreUsuario, PDO::PARAM_STR);
    $stmt->execute();
    $db = null;
    return true;
}
