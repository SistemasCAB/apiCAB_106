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



