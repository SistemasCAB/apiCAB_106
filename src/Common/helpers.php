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

// Conexión a BD CAB en 10.99.8.5
function getConeccionCAB05() {
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
    // $sql = "Declare @mensaje varchar(255);
    //         EXEC TareaLimpiezaCrear_v2 
    //             @idCama = :idCama, 
    //             @solicitadaPorDni = :dni, 
    //             @solicitadaPorNombre = :nombreUsuario, 
    //             @idServicioSolicita = :idServicio, 
    //             @mensaje = @mensaje OUTPUT";

    $sql = "Declare @mensaje varchar(255);
            EXEC TareaLimpiezaCrear 
                @idCama = :idCama, 
                @solicitadaPorDni = :dni, 
                @solicitadaPorNombre = :nombreUsuario, 
                @idServicioSolicita = :idServicio, 
                @mensaje = @mensaje OUTPUT";
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
            EXEC alertaNueva @idCama = :idCama, @idTipoAlerta = 13, @mensaje = @mensaje OUTPUT';
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


function encriptar($string) {
    // encripita el número de ticket + un número x (x puede ser 0,1,2,3) 0 = enviados abiertos, 1 = enviados cerrados, 2 = recibidos abiertos, 3 = recibidos cerrados.
	$key = '8125';
	$result = '';
	for($i=0; $i<strlen($string); $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($key, ($i % strlen($key))-1, 1);
		$char = chr(ord($char)+ord($keychar));
		$result.=$char;
	}
	return base64_encode($result);
}

function debug_log($message, $variable = null) {
    $logsDir = __DIR__ . '\logs';
    $logFile = $logsDir . '\debug.log';
    
    // Crear el directorio si no existe
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    
    $entry = "[$timestamp] $message";
    
    if ($variable !== null) {
        $entry .= ": " . print_r($variable, true);
    }
    
    $entry .= PHP_EOL;
    
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function debug_log2($message) {
    $logsDir = __DIR__ . '\logs';
    $logFile = $logsDir . '\debug.log';
    
    // Crear el directorio si no existe
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    
    $entry = "[$timestamp] $message";
        
    $entry .= PHP_EOL;
    
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}