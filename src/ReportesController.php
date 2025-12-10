<?php
namespace App;

ini_set('memory_limit', '512M');

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

// Conexión a BD Informes en 105
function getConnectionINF() {
    $dbhost = $_ENV['DB_HOSTINF'];
    $dbuser = $_ENV['DB_USERINF'];
    $dbpass = $_ENV['DB_PASSINF'];
    $dbname = $_ENV['DB_NAMEINF'];
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

function getConnectionINF5() {
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



class ReportesController
{
    // LISTADO DE COBERTURAS
    public function coberturas(Request $request, Response $response, $args){
        $sql = 'EXEC coberturas';
        $db = getConnectionINF5();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $db = null;
        if(count($resultado) == 0){
            $datos = array('estado' => 404, 'mensaje' => 'No se encontraron datos');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        // Si se encontraron datos, los devuelvo
        $datos = array();
        foreach($resultado as $row){    
            $datos[] = array(
                'nombreCobertura' => $row->cobeDescripcion,
                'codigoCobertura' => $row->cobeCodigoInterno                
            );
        }
        $nuevoRegistro = array(
            'nombreCobertura' => 'TODAS LAS COBERTURAS',
            'codigoCobertura' => '1'
        );

        array_push($datos, $nuevoRegistro);

        // Ordenar por nombreCobertura
        usort($datos, function($a, $b) {
            return strcmp($a['nombreCobertura'], $b['nombreCobertura']);
        });

        // Devuelvo los datos encontrados
        $response->getBody()->write(json_encode($datos));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);        
    }

    // FACTURACION DE COBERTURAS
    public function facturacionCoberturas(Request $request, Response $response, $args){
        $cobertura      = $request->getQueryParams()['cobertura'] ?? null;
        $desde          = $request->getQueryParams()['desde'] ?? null;
        $hasta          = $request->getQueryParams()['hasta'] ?? null;

        $error = 0;
        if(($cobertura == '') or ($desde == '') or ($hasta == '')){
            $error ++;
        }

        if($error == 0){
            $sql = 'EXEC facturacionCoberturas @cobertura = :cobertura, @desde = :desde, @hasta = :hasta';
            $db = getConnectionINF5();
            $stmt = $db->prepare($sql);
            $stmt->bindParam("cobertura", $cobertura);
            $stmt->bindParam("desde", $desde);
            $stmt->bindParam("hasta", $hasta);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            if(count($resultado) == 0){
                //$datos = array('estado' => 404, 'mensaje' => 'No se encontraron datos');
                //$datos = array();
                $datos[] = array(
                    'codigo' => '',
                    'practica' => '',
                    'cantidad' => 0,
                    'importe' => 0,
                    'tipo' => ''
                    
                );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }
            // Si se encontraron datos, los devuelvo
            $datos = array();
            foreach($resultado as $row){    
                $datos[] = array(
                    'codigo' => $row->codigo,
                    'practica' => $row->practica,
                    'cantidad' => (int)$row->cantidad,
                    //'importe' => $row->total,
                    //'importe' => number_format((float)$row->total, 2, '.', ''),
                    'importe' => round((float)$row->total,2),
                    'tipo' => $row->tipo
                    
                );
            }
            // Devuelvo los datos encontrados
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }else{            
            $datos = array('estado' => 403, 'mensaje' => 'Faltan datos obligatorios');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
    }

    // FACTURACION DE COBERTURAS DETALLE
    public function facturacionCoberturasDetalle(Request $request, Response $response, $args){

        ini_set('memory_limit', '3024M'); // aumento la memoria permitida para este reporte

        $cobertura      = $request->getQueryParams()['cobertura'] ?? null;
        $desde          = $request->getQueryParams()['desde'] ?? null;
        $hasta          = $request->getQueryParams()['hasta'] ?? null;

        $error = 0;
        if(($cobertura == '') or ($desde == '') or ($hasta == '')){
            $error ++;
        }

        if($error == 0){
            $sql = 'EXEC facturacionCoberturasDetalle @cobertura = :cobertura, @desde = :desde, @hasta = :hasta';
            $db = getConnectionINF5();
            $stmt = $db->prepare($sql);
            $stmt->bindParam("cobertura", $cobertura);
            $stmt->bindParam("desde", $desde);
            $stmt->bindParam("hasta", $hasta);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            if(count($resultado) == 0){
                //$datos = array('estado' => 404, 'mensaje' => 'No se encontraron datos');
                //$datos = array();
                $datos[] = array(
                    'fechaAtencion' => '',
                    'fechaFacturacion' => '',
                    'codigo' => 0,
                    'practica' => '',
                    'cantidad' => 0,
                    'precioUnidad' => 0,
                    'total' => 0,
                    'tipo' => ''
                );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }
            // Si se encontraron datos, los devuelvo
            $datos = array();
            foreach($resultado as $row){    
                $datos[] = array(
                    'fechaAtencion' => $row->fechaAtencion ?? '',
                    'fechaFacturacion' => $row->fechaFacturacion ?? '',
                    'codigo' => $row->codigo,
                    'practica' => $row->practica,
                    'cantidad' => (int)$row->cantidad,
                    'precioUnidad' => round((float)$row->total,2),
                    'total' => round((float)$row->total,2),
                    'tipo' => $row->tipo
                );
                // $datos[] = array(
                //     'fechaAtencion' => $row->fechaAtencion,
                //     'fechaFacturacion' => $row->fechaFacturacion,
                //     'codigo' => $row->codigo,
                //     'practica' => $row->practica,
                //     'cantidad' => (int)$row->cantidad,
                //     'precioUnidad' => round((float)$row->total,2),
                //     'total' => round((float)$row->total,2),
                //     'tipo' => $row->tipo
                    
                // );
            }
            // Devuelvo los datos encontrados
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }else{            
            $datos = array('estado' => 403, 'mensaje' => 'Faltan datos obligatorios');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
    }

    // LISTADO DE PROFESIONALES
    public function profesionales(Request $request, Response $response, $args){
        $sql = 'EXEC profesionales';
        $db = getConnectionINF5();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $db = null;
        if(count($resultado) == 0){
            $datos = array('estado' => 404, 'mensaje' => 'No se encontraron datos');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        // Si se encontraron datos, los devuelvo
        $datos = array();

        $nuevoRegistro = array(
            'profesional' => 'TODOS LOS PROFESIONALES',
            'mediCodigo' => '0'
        );

        array_push($datos, $nuevoRegistro);

        foreach($resultado as $row){    
            $datos[] = array(
                'profesional' => $row->persApellido . ' ' . $row->persNombre,
                'mediCodigo' => $row->mediCodigo
            );
        }
                
        // Devuelvo los datos encontrados
        $response->getBody()->write(json_encode($datos));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);        
    }


    // Turnos Indicadores
    public function turnosIndicadores(Request $request, Response $response, $args){
        $desde          = $request->getQueryParams()['desde'] ?? null;
        $hasta          = $request->getQueryParams()['hasta'] ?? null;
        $medico         = $request->getQueryParams()['medico'] ?? null;
        $filtro         = $request->getQueryParams()['filtro'] ?? null;

        if($filtro == ''){
            $filtro = 'medicos';
        }

        $error = 0;
        if(($filtro == '') or ($desde == '') or ($hasta == '')){
            $error ++;
        }

        if($error == 0){
            if(($medico == '') or ($medico == '0') or ($medico == null)){
                $medico = null; // Si no se pasa el médico, se busca para todos los médicos
            }

            $sql = 'EXEC turnosIndicadores @desde = :desde, @hasta = :hasta, @medico = :medico, @filtro = :filtro';
            $db = getConnectionINF5();
            $stmt = $db->prepare($sql);
            $stmt->bindParam("desde", $desde);
            $stmt->bindParam("hasta", $hasta);
            $stmt->bindParam("medico", $medico);
            $stmt->bindParam("filtro", $filtro);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            if(count($resultado) == 0){
                $datos[] = array(
                    'Oferta' => 0,
                    'NoAtenciones' => 0,
                    'OfertaReal'=> 0,
                    'Asignados' => 0,
                    'porcentageAsignados'=> 0,
                    'Sobreturnos' => 0,
                    'porcentageSobreTurnos'=> 0,
                    'Espontaneos' => 0,
                    'porcentageEspontaneos'=> 0,
                    'Presentes' => 0,
                    'porcentagePresente'=> 0,
                    'Ausentes' => 0,
                    'porcentageAusentes'=> 0,

                );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }
            // Si se encontraron datos, los devuelvo
            $datos = array();
            foreach($resultado as $row){    
                $datos[] = array(
                    'Oferta' => (int)$row->oferta ?? '',
                    'NoAtenciones' => (int)$row->noAtenciones ?? '',
                    'OfertaReal' => (int)$row->ofertaReal ?? '',
                    'Asignados' => (int)$row->asignados,
                    //'porcentageAsignados'=> (float)$row->porcentageAsignados,
                    'NoAsignados' => (int)$row->noAsignados,
                    //'porcentageNoAsignados'=> (float)$row->porcentageNoAsignados,
                    'Sobreturnos' => (int)$row->sobreturnos,
                    //'porcentageSobreTurnos'=> (float)$row->porcentageSobreTurnos,
                    'Espontaneos' => (int)$row->espontaneos,
                    //'porcentageEspontaneos'=> (float)$row->porcentageEspontaneos,
                    'Presentes' => (int)$row->presentes,
                    //'porcentagePresentes'=> (float)$row->porcentagePresentes,
                    'Ausentes' => (int)$row->ausentes
                    //'porcentageAusentes'=> (float)$row->porcentageAusentes
                );
            }
            // Devuelvo los datos encontrados
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }else{            
            $datos = array('estado' => 403, 'mensaje' => 'Faltan datos obligatorios');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
    }


    // Estudios Cardiologicos Facturados
    public function estudiosCardiologicosFacturados(Request $request, Response $response, $args){
        $desde          = $request->getQueryParams()['desde'] ?? null;
        $hasta          = $request->getQueryParams()['hasta'] ?? null;

        $error = 0;
        if(($desde == '') or ($hasta == '')){
            $error ++;
        }

        if($error == 0){
            $sql = 'EXEC estudiosCardiologicosFacturados @desde = :desde, @hasta = :hasta';
            $db = getConnectionINF5();
            $stmt = $db->prepare($sql);
            $stmt->bindParam("desde", $desde);
            $stmt->bindParam("hasta", $hasta);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            if(count($resultado) == 0){
                $datos[] = array(
                    'Año' => 0,
                    'Mes' => 0,
                    'Código'=> '',
                    'Práctica' => '',
                    'Cantidad'=> 0
                );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }
            // Si se encontraron datos, los devuelvo
            $datos = array();
            foreach($resultado as $row){    
                $datos[] = array(
                    'Año' => (int)$row->anio ?? '',
                    'Mes' => (int)$row->mes ?? '',
                    'Código' => $row->codigo ?? '',
                    'Práctica' => $row->nomeDescripcion ?? '',
                    'Cantidad' => (int)$row->cantidad,
                
                );
            }
            // Devuelvo los datos encontrados
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }else{            
            $datos = array('estado' => 403, 'mensaje' => 'Faltan datos obligatorios');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
    }

    // INDICADORES CONSULTAS
    public function indicadoresConsultas(Request $request, Response $response, $args){
        $desde          = $request->getQueryParams()['desde'] ?? null;
        $hasta          = $request->getQueryParams()['hasta'] ?? null;

        $error = 0;
        if(($desde == '') or ($hasta == '')){
            $error ++;
        }

        if($error == 0){
            $sql = 'EXEC ConsultasIndicadores @desde = :desde, @hasta = :hasta';
            $db = getConnectionINF5();
            $stmt = $db->prepare($sql);
            $stmt->bindParam("desde", $desde);
            $stmt->bindParam("hasta", $hasta);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            if(count($resultado) == 0){
                $datos[] = array(
                    'Asignados' => 0,
                    'Ausentismo' => 0,
                    'Sobreturnos' => 0,
                    'Espontaneos' => 0,
                    'Programados' => 0,
                    'Recetas' => 0
                );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }
            // Si se encontraron datos, los devuelvo
            $datos = array();
            foreach($resultado as $row){    
                $datos[] = array(
                    'Asignados' => (int) $row->Asignados,
                    'Ausentismo' => (float)$row->Ausentismo,
                    'Sobreturnos' => (int)$row->Sobreturnos,
                    'Espontaneos' => (int)$row->Espontaneos,
                    'Programados' => (int)$row->Programados,
                    'Recetas' => (int)$row->Recetas
                );
            }
            // Devuelvo los datos encontrados
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }else{            
            $datos = array('estado' => 403, 'mensaje' => 'Faltan datos obligatorios');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
    }

}