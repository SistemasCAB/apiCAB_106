<?php
namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Middleware\AuthTokenMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;


// Conexión a BD TableroCamas en 105
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
function getConnection() {
    $dbhost = $_ENV['DB_HOSTTC'];
    $dbuser = $_ENV['DB_USERTC'];
    $dbpass = $_ENV['DB_PASSTC'];
    $dbname = $_ENV['DB_NAMETC'];
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
        //$stmt = getConnection()->query($con);
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

// Obtener el token de acceso a la API v1 de FCM
function getFCMv1AccessToken(string $keyFilePath): ?string {
    try {
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        
        // Usa ServiceAccountCredentials para cargar el archivo JSON
        $credentials = new ServiceAccountCredentials($scopes, $keyFilePath);

        $accessToken = $credentials->fetchAuthToken();

        // La respuesta fetchAuthToken() es un array. El token actual está en la clave 'access_token'.
        if (isset($accessToken['access_token'])) {
            //echo "Token de acceso obtenido exitosamente.\n";
            // Opcional: Puedes imprimir el token y su expiración para depuración
            // echo "Token: " . $accessToken['access_token'] . "<br>";
            // echo "Expira en: " . $accessToken['expires_in'] . " segundos<br>"; // Tiempo hasta expiración en segundos
            // echo "Tipo: " . $accessToken['token_type'] . "<br>";

            return $accessToken['access_token'];

        } else {
            //echo "Error: No se pudo obtener 'access_token' de la respuesta.\n";
            // Imprimir la respuesta completa para depuración
            //print_r($accessToken);
            return null;
        }

    } catch (\Exception $e) {
        // Manejar cualquier excepción que ocurra durante el proceso
        //echo 'Error al obtener el token de acceso: ' . $e->getMessage() . "\n";
        return null;
    }
}

class NotificacionesController
{
    // ENVIAR NOTIFICACIONES A ENFERMEROS

    public function enviarEnfermero(Request $request, Response $response, $args){
        $projectId      = 'notificaciones-push-enfermeria'; // ID del proyecto de Firebase
        
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosNotif     = json_decode($json); // array con los parámetros recibidos.

        //grabo el log
        $conLog = 'declare @return_value int
                exec @return_value = logPushEnfemero_nuevo @datos = :datos
                select @return_value as id';  
        $db = getConnectionTC();
        $stmt = $db->prepare($conLog);
        $stmt->bindParam(':datos', $json);
        $stmt->execute();
        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $db = null; 
        foreach($resultado as $log){
            $idLog = $log->id;
        }

        // valido datos obligatorios

        $error = 0;
        $datos = array();

        if(isset($datosNotif->dniEnfermero) && $datosNotif->dniEnfermero != ''){
            $dniEnfermero = $datosNotif->dniEnfermero;
        }else{
            $error ++;
        }

        if(isset($datosNotif->nombreEnfermero) && $datosNotif->nombreEnfermero != ''){
            $nombreEnfermero = $datosNotif->nombreEnfermero;
        }else{
            $error ++;
        }

        if(isset($datosNotif->cama) && $datosNotif->cama != ''){
            $cama = $datosNotif->cama;
        }else{
            $error ++;
        }

        if(isset($datosNotif->dniPaciente) && $datosNotif->dniPaciente != ''){
            $dniPaciente = $datosNotif->dniPaciente;
        }else{
            $error ++;
        }

        if(isset($datosNotif->nombrePaciente) && $datosNotif->nombrePaciente != ''){
            $nombrePaciente = $datosNotif->nombrePaciente;
        }else{
            $error ++;
        }

        if(isset($datosNotif->apellidoPaciente) && $datosNotif->apellidoPaciente != ''){
            $apellidoPaciente = $datosNotif->apellidoPaciente;
        }else{
            $error ++;
        }

        if(isset($datosNotif->textoNotificacion) && $datosNotif->textoNotificacion != ''){
            $textoNotificacion = $datosNotif->textoNotificacion;
        }else{
            $error ++;
        }

        if(isset($datosNotif->tipoNotificacion) && $datosNotif->tipoNotificacion != ''){
            $tipoNotificacion = $datosNotif->tipoNotificacion; //1=Nueva indicación; 2=Modificación de indicacion; 3=Eliminación de indicación; 4=Suspención de medicación
        }else{
            $error ++;
        }

        if(isset($datosNotif->urgente) && $datosNotif->urgente != ''){
            if ($datosNotif->urgente == 1) {
                $urgente = $datosNotif->urgente; // 1= Urgente; 0= No urgente
            }else{
                $urgente = 2; // indica que no es urgente
            }
        }else{
            $error ++;
        }

        // fin validación de datos obligatorios

        

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                if($error == 0){
                    // obtengo el token correspondiente al dispositivo que está usando el enfermero.
                    $sql = 'EXEC obtenerTokenDispositivo @loginDni = :loginDni';
                    try {
                        



                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("loginDni", $dniEnfermero);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null; 

                        if(count($resultado) == 0){
                            // aunque el enfermero no inició sesión en un dispositivo, grabo la notificación para que la vea cuando inicie sesión.
                            // grabo la notificación en la tabla notificacionesPushEnfermero
                            $paciente = $apellidoPaciente.', '.$nombrePaciente;
                            if($urgente == 1){
                                $sql = 'DECLARE	@return_value int, @fechaEnvio datetime
                                    EXEC @return_value = notificacionPushEnfermero_nueva_v2 @dniEnfermero = '.$dniEnfermero.', @para = \''.$nombreEnfermero.'\', @cama = \''.$cama.'\', @dniPaciente = \''.$dniPaciente.'\', @paciente = \''.$paciente.'\', @tipoNotificacion = '.$tipoNotificacion.', @textoNotificacion = \''.$textoNotificacion.'\', @urgente = 1, @id_log = '.$idLog.', @fechaEnvio = @fechaEnvio OUTPUT
                                    SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';
                            }else{
                                $sql = 'DECLARE	@return_value int, @fechaEnvio datetime
                                    EXEC @return_value = notificacionPushEnfermero_nueva_v2 @dniEnfermero = '.$dniEnfermero.', @para = \''.$nombreEnfermero.'\', @cama = \''.$cama.'\', @dniPaciente = \''.$dniPaciente.'\', @paciente = \''.$paciente.'\', @tipoNotificacion = '.$tipoNotificacion.', @textoNotificacion = \''.$textoNotificacion.'\', @urgente = 0, @id_log = '.$idLog.',@fechaEnvio = @fechaEnvio OUTPUT
                                    SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';
                            }
                            

                            $stmt = getConnectionTC()->query($sql);
                            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                            $a = new \stdClass();
                            $a = $resultado[0];
                            $idNotificacion = (int)$a->estado;
                            $fechaNotificacion = $a->fechaEnvio;
                            unset($a);

                            $datos = array('estado' => 0, 'mensaje' => 'El enfermero '.$nombreEnfermero.' (DNI: '.$dniEnfermero.') no inició sesión en ningún dispositivo. La notificación se grabó para que la vea cuando inicie sesión.');
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                        }else{   
                            foreach($resultado as $dispositivo){
                                $tokenFcm = $dispositivo->tokenFcm;
                            }

                            // grabo la notificación en la tabla notificacionesPushEnfermero
                            $paciente = $apellidoPaciente.', '.$nombrePaciente;
                            if($urgente == 1){
                                $sql = 'DECLARE	@return_value int, @fechaEnvio datetime
                                    EXEC @return_value = notificacionPushEnfermero_nueva_v2 @dniEnfermero = '.$dniEnfermero.', @para = \''.$nombreEnfermero.'\', @cama = \''.$cama.'\', @dniPaciente = \''.$dniPaciente.'\', @paciente = \''.$paciente.'\', @tipoNotificacion = '.$tipoNotificacion.', @textoNotificacion = \''.$textoNotificacion.'\', @urgente = 1, @id_log = '.$idLog.', @fechaEnvio = @fechaEnvio OUTPUT
                                    SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';
                            }else{
                                $sql = 'DECLARE	@return_value int, @fechaEnvio datetime
                                    EXEC @return_value = notificacionPushEnfermero_nueva_v2 @dniEnfermero = '.$dniEnfermero.', @para = \''.$nombreEnfermero.'\', @cama = \''.$cama.'\', @dniPaciente = \''.$dniPaciente.'\', @paciente = \''.$paciente.'\', @tipoNotificacion = '.$tipoNotificacion.', @textoNotificacion = \''.$textoNotificacion.'\', @urgente = 0, @id_log = '.$idLog.',@fechaEnvio = @fechaEnvio OUTPUT
                                    SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';
                            }
                            

                            $stmt = getConnectionTC()->query($sql);
                            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                            $a = new \stdClass();
                            $a = $resultado[0];
                            $idNotificacion = (int)$a->estado;
                            $fechaNotificacion = $a->fechaEnvio;
                            unset($a);
                            
                            //envío la notificación al dispositivo del enfermero

                            // ruta de acceso al archivo de credenciales de servicio de google firebase
                            $pathToServiceAccountKey = 'c:/wamp64/google-firebase/notificaciones-push-enfermeria-5ab1f1a3e003.json';
                            $accessToken = getFCMv1AccessToken($pathToServiceAccountKey); // obtengo el token de acceso a la API v1 de FCM
                            if ($accessToken) {
                                // 1=Nueva indicación; 2=Modificación de indicacion; 3=Eliminación de indicación; 4=Suspención de medicación
                                switch ($tipoNotificacion){
                                    case 1: 
                                        $notificationTitle = 'NUEVA INDICACIÓN MÉDICA';
                                        break;
                                    case 2: 
                                        $notificationTitle = 'MODIFICACIÓN DE INDICACIÓN MÉDICA';
                                        break;
                                    case 3: 
                                        $notificationTitle = 'ELIMINACIÓN DE INDICACIÓN MÉDICA';
                                        break;
                                    case 4: 
                                        $notificationTitle = 'SUSPENCIÓN DE INDICACIÓN MÉDICA';
                                        break;
                                }

                                
                                $notificationBody = $textoNotificacion;

                                if($urgente <> 1){
                                    $urgente = 0;
                                }

                                $extraData = [
                                    'idNotificacion' => (string)$idNotificacion,
                                    'fecha' => $fechaNotificacion,
                                    'cama' => $cama,
                                    'dniPaciente' => $dniPaciente,
                                    'nombrePaciente' => $nombrePaciente,
                                    'apellidoPaciente' => $apellidoPaciente,
                                    'textoNotificacion' => $textoNotificacion,
                                    'tipoNotificacion' => (string)$tipoNotificacion,
                                    'urgente' => (string)$urgente
                                ];

                                
                                $fcmUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

                                // Construir el array que representa el payload JSON
                                $messagePayloadArray = [
                                    'message' => [
                                        'token' => $tokenFcm, // El token del dispositivo
                                        'notification' => [
                                            'title' => $notificationTitle, // Título visible en la notificación
                                            'body' => $notificationBody,   // Cuerpo visible en la notificación
                                        ],
                                        'data' => $extraData, 
                                        // Opcional: Configuración específica para Android
                                        'android' => [
                                            'priority' => 'HIGH'
                                        ]
                                    ]
                                ];

                                // Convertir el array PHP a una cadena JSON
                                $jsonPayload = json_encode($messagePayloadArray);


                                // ------------------------------------------------------------
                                // Parte 3: Enviar la solicitud HTTP POST usando cURL
                                // ------------------------------------------------------------

                                // Inicializar la sesión cURL
                                $ch = curl_init();

                                // Configurar las opciones de cURL
                                curl_setopt($ch, CURLOPT_URL, $fcmUrl);          // La URL del API de FCM
                                curl_setopt($ch, CURLOPT_POST, true);            // Es una solicitud POST
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Queremos que curl_exec() devuelva el resultado como cadena
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Buena práctica de seguridad: verificar certificado SSL
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    // Buena práctica de seguridad: verificar host del certificado

                                // Configurar los encabezados HTTP
                                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                    'Content-Type: application/json', // Le decimos al servidor que el cuerpo es JSON
                                    "Authorization: Bearer {$accessToken}" // ¡Incluimos el token de acceso aquí!
                                ]);

                                // Configurar el cuerpo de la solicitud con el payload JSON
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

                                // Ejecutar la solicitud cURL
                                $respuestaCurl = curl_exec($ch);

                                // ------------------------------------------------------------
                                // Manejo de la respuesta y errores
                                // ------------------------------------------------------------

                                // Verificar si hubo errores de cURL (problemas de red, configuración, etc.)
                                if (curl_errno($ch)) {
                                    $error_msg = curl_error($ch);
                                    //echo "Error de cURL: " . $error_msg . "\n";
                                    
                                    $datos = array(
                                        'estado' => 0, 
                                        'mensaje' => 'Error de cURL: ' . $error_msg . ' No se envío la notificación .'
                                    );

                                    $response->getBody()->write(json_encode($datos));
                                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);

                                } else {
                                    // La solicitud se envió, ahora verifica la respuesta del servidor FCM

                                    // Obtener el status code HTTP de la respuesta
                                    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                                    // Decodificar la respuesta JSON del servidor FCM
                                    //$responseData = json_decode($respuestaCurl, true);  // no lo usaré por ahora

                                    // grabo el resultado del envio
                                    $con = 'exec notificacionPushEnfermero_resultado @id_notificacion = :id_notificacion, @resultado = :resultado';  
                                    $db = getConnectionTC();
                                    $stmt = $db->prepare($con);
                                    $stmt->bindParam("id_notificacion", $idNotificacion);

                                    if ($statusCode === 200) {
                                        $envio = 1;
                                        $stmt->bindParam("resultado",$envio);
                                        $stmt->execute();

                                        // La notificación se envió correctamente
                                        $datos = array(
                                            'estado' => 1, 
                                            'idNotificacion' => $idNotificacion,
                                            'mensaje' => 'Se envió la notificación al enfermero.', 
                                            'resultadoEnvio' => 'Ok'
                                        );

                                        $response->getBody()->write(json_encode($datos));
                                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                                        
                                    } else {
                                        // Hubo un error en el lado del servidor FCM (ej: token inválido, permisos, etc.)
                                        $envio = 0;
                                        $stmt->bindParam("resultado", $envio);
                                        $stmt->execute();
                                        
                                        $datos = array(
                                            'estado' => 0, 
                                            'idNotificacion' => $idNotificacion,
                                            'mensaje' => 'No se envió la notificación al enfermero', 
                                            'resultadoEnvio' => 'Error'
                                        );

                                        $response->getBody()->write(json_encode($datos));
                                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                                    }
                                }

                                // Cerrar la sesión cURL para liberar recursos
                                curl_close($ch);


                                
                                // fin del envio de la notificación
                            } else {
                                //echo "Error al obtener el token de acceso a la API v1 de FCM.\n";
                                $datos = array(
                                    'estado' => 0, 
                                    'mensaje' => 'Error al obtener el token de acceso a la API v1 de FCM'
                                );

                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                            }                            
                        }
                    } catch(\PDOException $e) {
                        $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                    }
                }else{
                    // error en los datos
                    $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos o faltan parámetros obligatorios.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }else{
                //acceso denegado. No envió el token de acceso
                $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado. Token inválido.');            

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

    public function enviarEnfermero_v2(Request $request, Response $response, $args){
        // POST: /notificaciones/enviarEnfermero
        // Recibe un JSON con los datos de la notificación y lo graba en la tabla de log.
        // Devuelve un JSON con el estado de la operación.
        // El JSON de entrada debe tener la siguiente estructura:
        // {
        //     "dniEnfermero": "12345678",
        //     "nombreEnfermero": "Juan Perez",
        //     "cama": "Cama 1",
        //     "dniPaciente": "87654321",
        //     "nombrePaciente": "Maria Gomez",
        //     "apellidoPaciente": "Lopez",
        //     "textoNotificacion": "Nueva indicación",
        //     "tipoNotificacion": 1,
        //     "urgente": 1,
        //     "fecha": "2025-05-28 10:12:15",
        //     "usuario": "ROMERO, LETICIA",
        //     "idNotif": 19898754
        // }
        // El JSON de salida tendrá la siguiente estructura:
        // {
        //     "estado": 1,
        //     "mensaje": "Notificación enviada correctamente"
        // }

        $projectId      = 'notificaciones-push-enfermeria'; // ID del proyecto de Firebase
        
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosNotif     = json_decode($json); // array con los parámetros recibidos.

        //grabo el log
        $conLog = 'declare @return_value int
                exec @return_value = logPushEnfemero_nuevo @datos = :datos
                select @return_value as id';  
        $db = getConnectionTC();
        $stmt = $db->prepare($conLog);
        $stmt->bindParam(':datos', $json);
        $stmt->execute();
        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $db = null; 
        foreach($resultado as $log){
            $idLog = $log->id;
        }

        // valido datos obligatorios

        $error = 0;
        $datos = array();

        if(isset($datosNotif->dniEnfermero) && $datosNotif->dniEnfermero != ''){
            $dniEnfermero = $datosNotif->dniEnfermero;
        }else{
            $error ++;
        }

        if(isset($datosNotif->nombreEnfermero) && $datosNotif->nombreEnfermero != ''){
            $nombreEnfermero = $datosNotif->nombreEnfermero;
        }else{
            $error ++;
        }

        if(isset($datosNotif->cama) && $datosNotif->cama != ''){
            $cama = $datosNotif->cama;
        }else{
            $error ++;
        }

        if(isset($datosNotif->dniPaciente) && $datosNotif->dniPaciente != ''){
            $dniPaciente = $datosNotif->dniPaciente;
        }else{
            $error ++;
        }

        if(isset($datosNotif->nombrePaciente) && $datosNotif->nombrePaciente != ''){
            $nombrePaciente = $datosNotif->nombrePaciente;
        }else{
            $error ++;
        }

        if(isset($datosNotif->apellidoPaciente) && $datosNotif->apellidoPaciente != ''){
            $apellidoPaciente = $datosNotif->apellidoPaciente;
        }else{
            $error ++;
        }

        if(isset($datosNotif->textoNotificacion) && $datosNotif->textoNotificacion != ''){
            $textoNotificacion = $datosNotif->textoNotificacion;
        }else{
            $error ++;
        }

        if(isset($datosNotif->tipoNotificacion) && $datosNotif->tipoNotificacion != ''){
            $tipoNotificacion = $datosNotif->tipoNotificacion; //1=Nueva indicación; 2=Modificación de indicacion; 3=Eliminación de indicación; 4=Suspención de medicación; 5=Dieta
        }else{
            $error ++;
        }

        if(isset($datosNotif->urgente) && $datosNotif->urgente != ''){
            if ($datosNotif->urgente == 1) {
                $urgente = $datosNotif->urgente; // 1= Urgente; 0= No urgente
            }else{
                $urgente = 2; // indica que no es urgente
            }
        }else{
            $error ++;
        }

        // fin validación de datos obligatorios

        

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                if($error == 0){
                    // obtengo el token correspondiente al dispositivo que está usando el enfermero.
                    $sql = 'EXEC obtenerTokenDispositivo @loginDni = :loginDni';
                    try {
                        



                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("loginDni", $dniEnfermero);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null; 

                        if(count($resultado) == 0){
                            // aunque el enfermero no inició sesión en un dispositivo, grabo la notificación para que la vea cuando inicie sesión.
                            // grabo la notificación en la tabla notificacionesPushEnfermero
                            $paciente = $apellidoPaciente.', '.$nombrePaciente;
                            if($urgente == 1){
                                $sql = 'DECLARE	@return_value int, @fechaEnvio datetime
                                    EXEC @return_value = notificacionPushEnfermero_nueva_v2 @dniEnfermero = '.$dniEnfermero.', @para = \''.$nombreEnfermero.'\', @cama = \''.$cama.'\', @dniPaciente = \''.$dniPaciente.'\', @paciente = \''.$paciente.'\', @tipoNotificacion = '.$tipoNotificacion.', @textoNotificacion = \''.$textoNotificacion.'\', @urgente = 1, @id_log = '.$idLog.', @fechaEnvio = @fechaEnvio OUTPUT
                                    SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';
                            }else{
                                $sql = 'DECLARE	@return_value int, @fechaEnvio datetime
                                    EXEC @return_value = notificacionPushEnfermero_nueva_v2 @dniEnfermero = '.$dniEnfermero.', @para = \''.$nombreEnfermero.'\', @cama = \''.$cama.'\', @dniPaciente = \''.$dniPaciente.'\', @paciente = \''.$paciente.'\', @tipoNotificacion = '.$tipoNotificacion.', @textoNotificacion = \''.$textoNotificacion.'\', @urgente = 0, @id_log = '.$idLog.',@fechaEnvio = @fechaEnvio OUTPUT
                                    SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';
                            }
                            

                            $stmt = getConnectionTC()->query($sql);
                            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                            $a = new \stdClass();
                            $a = $resultado[0];
                            $idNotificacion = (int)$a->estado;
                            $fechaNotificacion = $a->fechaEnvio;
                            unset($a);

                            $datos = array('estado' => 0, 'mensaje' => 'El enfermero '.$nombreEnfermero.' (DNI: '.$dniEnfermero.') no inició sesión en ningún dispositivo. La notificación se grabó para que la vea cuando inicie sesión.');
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                        }else{   
                            foreach($resultado as $dispositivo){
                                $tokenFcm = $dispositivo->tokenFcm;
                            }

                            // grabo la notificación en la tabla notificacionesPushEnfermero
                            $paciente = $apellidoPaciente.', '.$nombrePaciente;
                            if($urgente == 1){
                                $sql = 'DECLARE	@return_value int, @fechaEnvio datetime
                                    EXEC @return_value = notificacionPushEnfermero_nueva_v2 @dniEnfermero = '.$dniEnfermero.', @para = \''.$nombreEnfermero.'\', @cama = \''.$cama.'\', @dniPaciente = \''.$dniPaciente.'\', @paciente = \''.$paciente.'\', @tipoNotificacion = '.$tipoNotificacion.', @textoNotificacion = \''.$textoNotificacion.'\', @urgente = 1, @id_log = '.$idLog.', @fechaEnvio = @fechaEnvio OUTPUT
                                    SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';
                            }else{
                                $sql = 'DECLARE	@return_value int, @fechaEnvio datetime
                                    EXEC @return_value = notificacionPushEnfermero_nueva_v2 @dniEnfermero = '.$dniEnfermero.', @para = \''.$nombreEnfermero.'\', @cama = \''.$cama.'\', @dniPaciente = \''.$dniPaciente.'\', @paciente = \''.$paciente.'\', @tipoNotificacion = '.$tipoNotificacion.', @textoNotificacion = \''.$textoNotificacion.'\', @urgente = 0, @id_log = '.$idLog.',@fechaEnvio = @fechaEnvio OUTPUT
                                    SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';
                            }
                            

                            $stmt = getConnectionTC()->query($sql);
                            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                            $a = new \stdClass();
                            $a = $resultado[0];
                            $idNotificacion = (int)$a->estado;
                            $fechaNotificacion = $a->fechaEnvio;
                            unset($a);
                            
                            //envío la notificación al dispositivo del enfermero

                            // ruta de acceso al archivo de credenciales de servicio de google firebase
                            $pathToServiceAccountKey = 'c:/wamp64/google-firebase/notificaciones-push-enfermeria-5ab1f1a3e003.json';
                            $accessToken = getFCMv1AccessToken($pathToServiceAccountKey); // obtengo el token de acceso a la API v1 de FCM
                            if ($accessToken) {
                                // 1=Nueva indicación; 2=Modificación de indicacion; 3=Eliminación de indicación; 4=Suspención de medicación
                                switch ($tipoNotificacion){
                                    case 1: 
                                        $notificationTitle = 'NUEVA INDICACIÓN MÉDICA';
                                        break;
                                    case 2: 
                                        $notificationTitle = 'MODIFICACIÓN DE INDICACIÓN MÉDICA';
                                        break;
                                    case 3: 
                                        $notificationTitle = 'ELIMINACIÓN DE INDICACIÓN MÉDICA';
                                        break;
                                    case 4: 
                                        $notificationTitle = 'SUSPENCIÓN DE INDICACIÓN MÉDICA';
                                        break;
                                    case 5: 
                                        $notificationTitle = 'CAMBIOS EN LA DIETA DEL PACIENTE';
                                        break;
                                }

                                
                                $notificationBody = $textoNotificacion;

                                if($urgente <> 1){
                                    $urgente = 0;
                                }

                                $extraData = [
                                    'idNotificacion' => (string)$idNotificacion,
                                    'fecha' => $fechaNotificacion,
                                    'cama' => $cama,
                                    'dniPaciente' => $dniPaciente,
                                    'nombrePaciente' => $nombrePaciente,
                                    'apellidoPaciente' => $apellidoPaciente,
                                    'textoNotificacion' => $textoNotificacion,
                                    'tipoNotificacion' => (string)$tipoNotificacion,
                                    'urgente' => (string)$urgente,
                                    'tituloNotificacion' => $notificationTitle
                                ];

                                
                                $fcmUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

                                // Construir el array que representa el payload JSON
                                $messagePayloadArray = [
                                    'message' => [
                                        'token' => $tokenFcm, // El token del dispositivo
                                        'notification' => [
                                            'title' => $notificationTitle, // Título visible en la notificación
                                            'body' => $notificationBody,   // Cuerpo visible en la notificación
                                        ],
                                        'data' => $extraData, 
                                        // Opcional: Configuración específica para Android
                                        'android' => [
                                            'priority' => 'HIGH'
                                        ]
                                    ]
                                ];

                                // Convertir el array PHP a una cadena JSON
                                $jsonPayload = json_encode($messagePayloadArray);


                                // ------------------------------------------------------------
                                // Parte 3: Enviar la solicitud HTTP POST usando cURL
                                // ------------------------------------------------------------

                                // Inicializar la sesión cURL
                                $ch = curl_init();

                                // Configurar las opciones de cURL
                                curl_setopt($ch, CURLOPT_URL, $fcmUrl);          // La URL del API de FCM
                                curl_setopt($ch, CURLOPT_POST, true);            // Es una solicitud POST
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Queremos que curl_exec() devuelva el resultado como cadena
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Buena práctica de seguridad: verificar certificado SSL
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    // Buena práctica de seguridad: verificar host del certificado

                                // Configurar los encabezados HTTP
                                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                    'Content-Type: application/json', // Le decimos al servidor que el cuerpo es JSON
                                    "Authorization: Bearer {$accessToken}" // ¡Incluimos el token de acceso aquí!
                                ]);

                                // Configurar el cuerpo de la solicitud con el payload JSON
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

                                // Ejecutar la solicitud cURL
                                $respuestaCurl = curl_exec($ch);

                                // ------------------------------------------------------------
                                // Manejo de la respuesta y errores
                                // ------------------------------------------------------------

                                // Verificar si hubo errores de cURL (problemas de red, configuración, etc.)
                                if (curl_errno($ch)) {
                                    $error_msg = curl_error($ch);
                                    //echo "Error de cURL: " . $error_msg . "\n";
                                    
                                    $datos = array(
                                        'estado' => 0, 
                                        'mensaje' => 'Error de cURL: ' . $error_msg . ' No se envío la notificación .'
                                    );

                                    $response->getBody()->write(json_encode($datos));
                                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);

                                } else {
                                    // La solicitud se envió, ahora verifica la respuesta del servidor FCM

                                    // Obtener el status code HTTP de la respuesta
                                    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                                    // Decodificar la respuesta JSON del servidor FCM
                                    //$responseData = json_decode($respuestaCurl, true);  // no lo usaré por ahora

                                    // grabo el resultado del envio
                                    $con = 'exec notificacionPushEnfermero_resultado @id_notificacion = :id_notificacion, @resultado = :resultado';  
                                    $db = getConnectionTC();
                                    $stmt = $db->prepare($con);
                                    $stmt->bindParam("id_notificacion", $idNotificacion);

                                    if ($statusCode === 200) {
                                        $envio = 1;
                                        $stmt->bindParam("resultado",$envio);
                                        $stmt->execute();

                                        // La notificación se envió correctamente
                                        $datos = array(
                                            'estado' => 1, 
                                            'idNotificacion' => $idNotificacion,
                                            'mensaje' => 'Se envió la notificación al enfermero.', 
                                            'resultadoEnvio' => 'Ok'
                                        );

                                        $response->getBody()->write(json_encode($datos));
                                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                                        
                                    } else {
                                        // Hubo un error en el lado del servidor FCM (ej: token inválido, permisos, etc.)
                                        $envio = 0;
                                        $stmt->bindParam("resultado", $envio);
                                        $stmt->execute();
                                        
                                        $datos = array(
                                            'estado' => 0, 
                                            'idNotificacion' => $idNotificacion,
                                            'mensaje' => 'No se envió la notificación al enfermero', 
                                            'resultadoEnvio' => 'Error'
                                        );

                                        $response->getBody()->write(json_encode($datos));
                                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                                    }
                                }

                                // Cerrar la sesión cURL para liberar recursos
                                curl_close($ch);


                                
                                // fin del envio de la notificación
                            } else {
                                //echo "Error al obtener el token de acceso a la API v1 de FCM.\n";
                                $datos = array(
                                    'estado' => 0, 
                                    'mensaje' => 'Error al obtener el token de acceso a la API v1 de FCM'
                                );

                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                            }                            
                        }
                    } catch(\PDOException $e) {
                        $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                    }
                }else{
                    // error en los datos
                    $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos o faltan parámetros obligatorios.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }else{
                //acceso denegado. No envió el token de acceso
                $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado. Token inválido.');            

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


    // MARCAR NOTIFICACION PUSH COMO LEIDA
    public function notificacionesPushEnfermeroLeida(Request $request, Response $response, $args){
        $tokenAcceso        = $request->getHeader('TokenAcceso');
        $bodyParams         = $request->getParsedBody();
        $idNotificacion     = $bodyParams['idNotificacion'] ?? null;
        $dni                = $bodyParams['dni'] ?? null;
        $leida_por          = $bodyParams['leida_por'] ?? null;
        $dispositivo        = $bodyParams['dispositivo'] ?? null;

        $error = 0;
        $datos = array();

        // verifico que los datos no se reciban vacios
        if($idNotificacion == ''){ $error ++; }
        if($dni == ''){ $error ++; }
        if($leida_por == ''){ $error ++; }
        if($dispositivo == ''){ $error ++; }
        

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                if($error == 0){
                    // grabo el log de notificación leida en TableroCamas.dbo.notificacionesPushLeidas
                    $sql = 'declare @return_value int, @mensaje varchar(255)
                            EXEC @return_value = notificacionPushEnfermeroLeida
                                @idNotificacion = :idNotificacion,
                                @dni = :dni,
                                @leida_por = :leida_por,
                                @dispositivo = :dispositivo,
                                @mensaje = @mensaje OUTPUT
                            select @return_value as return_value, @mensaje as mensaje';
                    try {
                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idNotificacion", $idNotificacion);
                        $stmt->bindParam("dni", $dni);
                        $stmt->bindParam("leida_por", $leida_por);
                        $stmt->bindParam("dispositivo", $dispositivo);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null; 
                        $notif = new \stdClass();
                        $notif = $resultado[0];
                        $datos = array();
                        $datos = array('estado' => $notif->return_value, 'mensaje' => $notif->mensaje);

                        
                        if($notif->return_value == 1){
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }else{
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                        }
                    } catch(PDOException $e) {
                        $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
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

    public function enviarNotificacion(Request $request, Response $response, $args){
        /*
        POST: /notificaciones/enviar
        El JSON de entrada debe tener la siguiente estructura:
        La estructura del campo "datos" depende del tipo de notificación:
        
        ESTRUCTURA PARA INDICACIÓN MÉDICA

        {
            "tipoNotificacion":"indicacionMedica",            
            "datos": {
                "enfermero": {
                "dniEnfermero": "11111111",
                "nombreEnfermero": "ENFERMERO ENFERMERO"
                },
                "internacion": {
                    "idInternacion": 123456,
                    "cama": "400",
                    "dniPaciente": "99988877",
                    "nombrePaciente": "PRUEBA",
                    "apellidoPaciente": "SISTEMAS"
                },
                "indicacionMedica": {
                    "fecha": "2025-06-10 09:05:00",
                    "tipoIndicacion": 1,
                    "profesional": "MESA, GABRIEL",
                    "urgente": 0,
                    "medicacion": {
                        "generico": "SOLUCION FISIOLOGICA 0.9%",
                        "dosis":"300 ml",
                        "via":"Intravenoso",
                        "frecuencia":"Cada 12 horas",
                        "compuestos":[
                            {
                                "genericoCompuesto":"POTASIO, CLORURO",
                                "dosis":"3 ampolla"
                            },
                            {
                                "genericoCompuesto":"COMPUESTO 2",
                                "dosis":"dosis 2"
                            }
                        ]
                    }
                }
            },
            "idNotif": 785412
        }

        ESTRUCTURA PARA DIETAS

        {
            "tipoNotificacion":"dieta",
            "datos":{
                "internacion":{
                    "idInternacion": 123456,
                    "cama": "400",
                    "dniPaciente": "99988877",
                    "nombrePaciente": "PRUEBA",
                    "apellidoPaciente": "SISTEMAS"
                },
                "indicacionDieta":{
                    "fecha": "2025-06-10 09:05:00",
                    "tipoIndicacion":1,
                    "profesional":"MESA, GABRIEL",
                    "urgente": 0,
                    "dieta":{
                        "indicacion":"Ayuno",
                        "cantidad":"1",
                        "frecuencia":"",
                        "duracion":"1 día",
                        "observaciones":"En ayuno a partir de las 14:00 hs."
                    }
                }
            },
            "idNotif": 785412
        }

        */

        


        $projectId      = 'notificaciones-push-enfermeria'; // ID del proyecto de Firebase
        
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosNotif     = json_decode($json); // array con los parámetros recibidos.

        //grabo el log
        $conLog = 'declare @return_value int
                exec @return_value = logNotificaciones_nuevo @datos = :datos
                select @return_value as id';  
        $db = getConnectionTC();
        $stmt = $db->prepare($conLog);
        $stmt->bindParam(':datos', $json);
        $stmt->execute();
        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $db = null; 
        foreach($resultado as $log){
            $idLog = $log->id;
        }

        // valido datos obligatorios

        $error = 0;
        $datos = array();

        // depende del tipo de notificación (dieta o indicacionMedica), los datos a validar son diferentes.
        
        // SI ES INDICACIÓN MÉDICA
        if (isset($datosNotif->tipoNotificacion) && $datosNotif->tipoNotificacion == 'indicacionMedica') {
            $datosEnf = $datosNotif->datos->enfermero ?? null;
            $datosInt = $datosNotif->datos->internacion ?? null;
            $datosInd = $datosNotif->datos->indicacionMedica ?? null;
            $datosMed = $datosNotif->datos->indicacionMedica->medicacion ?? null;

            if (empty($datosEnf->dniEnfermero)) $error++;
            if (empty($datosEnf->nombreEnfermero)) $error++;

            if (empty($datosInt->idInternacion)) $error++;
            if (empty($datosInt->cama)) $error++;
            if (empty($datosInt->dniPaciente)) $error++;
            if (empty($datosInt->nombrePaciente)) $error++;
            if (empty($datosInt->apellidoPaciente)) $error++;
            
            if (empty($datosInd->fecha)) $error++;
            if (!isset($datosInd->tipoIndicacion) || $datosInd->tipoIndicacion === '') $error++;
            if (empty($datosInd->profesional)) $error++;
            if (!isset($datosInd->urgente) || $datosInd->urgente === '') $error++;

            if (isset($datosInd->medicacion) && !empty((array)$datosInd->medicacion)) {
                if (empty($datosMed->generico)) $error++;
                if (empty($datosMed->dosis)) $error++;
                if (empty($datosMed->via)) $error++;
                if (empty($datosMed->frecuencia)) $error++;
            }else{
                $error++;
            }
        }
        
        
        // SI ES DIETA
        if(isset($datosNotif->tipoNotificacion) && $datosNotif->tipoNotificacion == 'dieta'){
            $datosInt = $datosNotif->datos->internacion ?? null;
            $datosInd = $datosNotif->datos->indicacionDieta ?? null;
            $datosDie = $datosNotif->datos->indicacionDieta->dieta ?? null;

            
            if (empty($datosInt->idInternacion)) $error++;
            if (empty($datosInt->cama)) $error++;
            if (empty($datosInt->dniPaciente)) $error++;
            if (empty($datosInt->nombrePaciente)) $error++;
            if (empty($datosInt->apellidoPaciente)) $error++;
            
            if (empty($datosInd->fecha)) $error++;
            if (!isset($datosInd->tipoIndicacion) || $datosInd->tipoIndicacion === '') $error++;
            if (empty($datosInd->profesional)) $error++;
            if (!isset($datosInd->urgente) || $datosInd->urgente === '') $error++;

            if (isset($datosDie) && !empty($datosDie)) {
                if (empty($datosDie->indicacion)) $error++;
                // if (empty($datosDie->cantidad)) $error++;
                // if (empty($datosDie->frecuencia)) $error++;
                // if (empty($datosDie->duracion)) $error++;
                // if (empty($datosDie->observaciones)) $error++;
            }else{
                $error++;
            }
        }

        // fin validación de datos obligatorios

        

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                if($error == 0){

                    $datos = array(
                        'estado' => 1, 
                        'idNotificacion' => 1001,
                        'mensaje' => 'Se envió la notificación', 
                        'resultadoEnvio' => 'Ok'
                    );
                    
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

                    //$tipoNotificacion = $datosNotif->tipoNotificacion;

                    // INDICACIÓN MEDICA
                    // if($tipoNotificacion == 'indicacionMedica'){
                    //     try {
                    //         // grabo la notificación
                    //         $sql = 'DECLARE	@return_value int, @fechaEnvio datetime

                    //                 EXEC @return_value = notificacionesMedicacion_nueva 
                    //                     @dniEnfermero = :dniEnfermero,
                    //                     @nombreEnfermero = :nombreEnfermero,
                    //                     @idInternacion = :idInternacion,
                    //                     @cama = :cama,
                    //                     @dniPaciente = :dniPaciente,
                    //                     @nombrePaciente = :nombrePaciente,
                    //                     @apellidoPaciente = :apellidoPaciente,
                    //                     @fechaIndicacion = :fechaIndicacion,
                    //                     @tipoIndicacion = :tipoIndicacion,
                    //                     @profesional = :profesional,
                    //                     @urgente = :urgente,
                    //                     @medicacionGenerico = :medicacionGenerico,
                    //                     @medicacionDosis = :medicacionDosis,
                    //                     @medicacionVia = :medicacionVia,
                    //                     @medicacionFrecuencia = :medicacionFrecuencia,
                    //                     @idNotificacionMarkey = :idNotificacionMarkey,
                    //                     @idLog = :idLog,
                    //                     @fechaEnvio = @fechaEnvio OUTPUT

                    //                 SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';


                    //         $db = getConnectionTC();
                    //         $stmt = $db->prepare($sql);
                    //         $stmt->bindParam("dniEnfermero", $datosEnf->dniEnfermero);
                    //         $stmt->bindParam("nombreEnfermero", $datosEnf->nombreEnfermero);
                    //         $stmt->bindParam("idInternacion", $datosInt->idInternacion);
                    //         $stmt->bindParam("cama", $datosInt->cama);
                    //         $stmt->bindParam("dniPaciente", $datosInt->dniPaciente);
                    //         $stmt->bindParam("nombrePaciente", $datosInt->nombrePaciente);
                    //         $stmt->bindParam("apellidoPaciente", $datosInt->apellidoPaciente);
                    //         $stmt->bindParam("fechaIndicacion", $datosInd->fecha);
                    //         $stmt->bindParam("tipoIndicacion", $datosInd->tipoIndicacion);
                    //         $stmt->bindParam("profesional", $datosInd->profesional);
                    //         $stmt->bindParam("urgente", $datosInd->urgente);
                    //         $stmt->bindParam("medicacionGenerico", $datosMed->generico);
                    //         $stmt->bindParam("medicacionDosis", $datosMed->dosis);
                    //         $stmt->bindParam("medicacionVia", $datosMed->via);
                    //         $stmt->bindParam("medicacionFrecuencia", $datosMed->frecuencia);
                    //         $stmt->bindParam("idNotificacionMarkey", $datosNotif->idNotif);
                    //         $stmt->bindParam("idLog", $idLog);
                    //         $stmt->execute();
                    //         $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    //         $a = new \stdClass();
                    //         $a = $resultado[0];
                    //         $idNotificacion = (int)$a->estado;
                    //         $fechaNotificacion = $a->fechaEnvio;
                    //         unset($a);
                    //         $db = null;

                    //         // si hay algún compuesto para esta medicación, grabo los compuestos
                    //         if(isset($datosMed->compuestos) && !empty($datosMed->compuestos)){
                    //             $sqlCompuesto = 'EXEC notificacionesMedicacionCompuestos_nuevo 
                    //                 @idNotificacionMedicacion = :idNotificacionMedicacion, 
                    //                 @genericoCompuesto = :genericoCompuesto, 
                    //                 @dosis = :dosis';
                    //             $db = getConnectionTC();
                    //             $stmtCompuesto = $db->prepare($sqlCompuesto);
                    //             foreach($datosMed->compuestos as $compuesto){
                    //                 $stmtCompuesto->bindParam("idNotificacionMedicacion", $idNotificacion);
                    //                 $stmtCompuesto->bindParam("genericoCompuesto", $compuesto->genericoCompuesto);
                    //                 $stmtCompuesto->bindParam("dosis", $compuesto->dosis);
                    //                 $stmtCompuesto->execute();
                    //             }
                    //             unset($stmtCompuesto);
                    //             $db = null;
                    //         }                            

                    //         // obtengo el token correspondiente al dispositivo que está usando el enfermero.
                    //         $sql = 'EXEC obtenerTokenDispositivo @loginDni = :loginDni';
                    //         $db = getConnectionTC();
                    //         $stmt = $db->prepare($sql);
                    //         $stmt->bindParam("loginDni", $datosEnf->dniEnfermero);
                    //         $stmt->execute();
                    //         $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    //         $db = null; 


                    //         if(count($resultado) == 0){ 
                    //             // no hay token de fcm para el enfermero
                    //             $datos = array('estado' => 0, 'mensaje' => 'El enfermero '.$datosEnf->nombreEnfermero.' (DNI: '.$datosEnf->dniEnfermero.') no inició sesión en ningún dispositivo. La notificación se grabó para que la vea cuando inicie sesión.');
                    //             $response->getBody()->write(json_encode($datos));
                    //             return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                    //         }else{   
                    //             // tengo el token del dispositivo del enfermero, ahora envío la notificación al dispositivo del enfermero
                    //             foreach($resultado as $dispositivo){
                    //                 $tokenFcm = $dispositivo->tokenFcm;
                    //             }

                    //             // ruta de acceso al archivo de credenciales de servicio de google firebase
                    //             $pathToServiceAccountKey = 'c:/wamp64/google-firebase/notificaciones-push-enfermeria-5ab1f1a3e003.json';
                    //             $accessToken = getFCMv1AccessToken($pathToServiceAccountKey); // obtengo el token de acceso a la API v1 de FCM
                    //             if ($accessToken) {
                    //                 // 1=Nueva indicación; 2=Modificación de indicacion; 3=Eliminación de indicación; 4=Suspención de medicación
                    //                 switch ($datosInd->tipoIndicacion){
                    //                     case 1: 
                    //                         $notificationTitle = 'NUEVA INDICACIÓN MÉDICA';
                    //                         break;
                    //                     case 2: 
                    //                         $notificationTitle = 'MODIFICACIÓN DE INDICACIÓN MÉDICA';
                    //                         break;
                    //                     case 3: 
                    //                         $notificationTitle = 'ELIMINACIÓN DE INDICACIÓN MÉDICA';
                    //                         break;
                    //                     case 4: 
                    //                         $notificationTitle = 'SUSPENCIÓN DE INDICACIÓN MÉDICA';
                    //                         break;
                    //                 }

                                    
                    //                 $notificationBody = 'Cama: '.$datosInt->cama.' - Paciente: '.$datosInt->nombrePaciente.' '.$datosInt->apellidoPaciente;

                    //                 if($datosInd->urgente <> 1){
                    //                     $urgente = 0;
                    //                 }

                    //                 // datos que se enviaran como datos personalizados en la notificación
                    //                 $extraData = [
                    //                     'idNotificacion' => (string)$idNotificacion,
                    //                     'fecha' => $fechaNotificacion,
                    //                     'idInternacion' => (string)$datosInt->idInternacion,
                    //                     'cama' => $datosInt->cama,
                    //                     'dniPaciente' => $datosInt->dniPaciente,
                    //                     'nombrePaciente' => $datosInt->nombrePaciente,
                    //                     'apellidoPaciente' => $datosInt->apellidoPaciente,
                    //                     'tipoNotificacion' => (string)$tipoNotificacion,
                    //                     'urgente' => (string)$datosInd->urgente,
                    //                     'profesional' => $datosInd->profesional,
                    //                     'medicamentoGenerico' => $datosMed->generico,
                    //                     'medicamentoDosis' => $datosMed->dosis,
                    //                     'medicamentoVia' => $datosMed->via,
                    //                     'medicamentoFrecuencia' => $datosMed->frecuencia,
                    //                     'medicacionCompuestos' => json_encode($datosMed->compuestos)
                    //                 ];

                                    
                    //                 $fcmUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

                    //                 // Construir el array que representa el payload JSON
                    //                 $messagePayloadArray = [
                    //                     'message' => [
                    //                         'token' => $tokenFcm, // El token del dispositivo
                    //                         'notification' => [
                    //                             'title' => $notificationTitle, // Título visible en la notificación
                    //                             'body' => $notificationBody,   // Cuerpo visible en la notificación
                    //                         ],
                    //                         'data' => $extraData, 
                    //                         // Opcional: Configuración específica para Android
                    //                         'android' => [
                    //                             'priority' => 'HIGH'
                    //                         ]
                    //                     ]
                    //                 ];

                    //                 // Convertir el array PHP a una cadena JSON
                    //                 $jsonPayload = json_encode($messagePayloadArray);


                    //                 // ------------------------------------------------------------
                    //                 // Parte 3: Enviar la solicitud HTTP POST usando cURL
                    //                 // ------------------------------------------------------------

                    //                 // Inicializar la sesión cURL
                    //                 $ch = curl_init();

                    //                 // Configurar las opciones de cURL
                    //                 curl_setopt($ch, CURLOPT_URL, $fcmUrl);          // La URL del API de FCM
                    //                 curl_setopt($ch, CURLOPT_POST, true);            // Es una solicitud POST
                    //                 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Queremos que curl_exec() devuelva el resultado como cadena
                    //                 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Buena práctica de seguridad: verificar certificado SSL
                    //                 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    // Buena práctica de seguridad: verificar host del certificado

                    //                 // Configurar los encabezados HTTP
                    //                 curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    //                     'Content-Type: application/json', // Le decimos al servidor que el cuerpo es JSON
                    //                     "Authorization: Bearer {$accessToken}" // ¡Incluimos el token de acceso aquí!
                    //                 ]);

                    //                 // Configurar el cuerpo de la solicitud con el payload JSON
                    //                 curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

                    //                 // Ejecutar la solicitud cURL
                    //                 $respuestaCurl = curl_exec($ch);

                    //                 // ------------------------------------------------------------
                    //                 // Manejo de la respuesta y errores
                    //                 // ------------------------------------------------------------

                    //                 // Verificar si hubo errores de cURL (problemas de red, configuración, etc.)
                    //                 if (curl_errno($ch)) {
                    //                     $error_msg = curl_error($ch);
                    //                     //echo "Error de cURL: " . $error_msg . "\n";
                                        
                    //                     $datos = array(
                    //                         'estado' => 0, 
                    //                         'mensaje' => 'Error de cURL: ' . $error_msg . ' No se envío la notificación .'
                    //                     );

                    //                     $response->getBody()->write(json_encode($datos));
                    //                     return $response->withHeader('Content-Type', 'application/json')->withStatus(500);

                    //                 } else {
                    //                     // La solicitud se envió, ahora verifica la respuesta del servidor FCM

                    //                     // Obtener el status code HTTP de la respuesta
                    //                     $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    //                     // Decodificar la respuesta JSON del servidor FCM
                    //                     //$responseData = json_decode($respuestaCurl, true);  // no lo usaré por ahora

                    //                     // grabo el resultado del envio
                    //                     $con = 'exec notificacionesMedicacion_respuesta 
                    //                                 @idNotificacionMedicacion = :idNotificacionMedicacion, 
                    //                                 @status = :status,
                    //                                 @respuesta = :respuesta';  
                    //                     $db = getConnectionTC();
                    //                     $stmt = $db->prepare($con);
                    //                     $stmt->bindParam("idNotificacionMedicacion", $idNotificacion);
                    //                     $stmt->bindParam("status", $statusCode);
                    //                     $stmt->bindParam("respuesta", $respuestaCurl);
                    //                     $stmt->execute();

                    //                     if ($statusCode == 200) {
                    //                         // La notificación se envió correctamente
                    //                         $datos = array(
                    //                             'estado' => 1, 
                    //                             'idNotificacion' => $idNotificacion,
                    //                             'mensaje' => 'Se envió la notificación al enfermero.', 
                    //                             'resultadoEnvio' => 'Ok'
                    //                         );

                    //                         $response->getBody()->write(json_encode($datos));
                    //                         return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                                            
                    //                     } else {
                    //                         // Hubo un error en el lado del servidor FCM (ej: token inválido, permisos, etc.)
                    //                         $datos = array(
                    //                             'estado' => 0, 
                    //                             'idNotificacion' => $idNotificacion,
                    //                             'mensaje' => 'No se envió la notificación al enfermero', 
                    //                             'resultadoEnvio' => 'Error'
                    //                         );

                    //                         $response->getBody()->write(json_encode($datos));
                    //                         return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                    //                     }
                    //                 }

                    //                 // Cerrar la sesión cURL para liberar recursos
                    //                 curl_close($ch);


                                    
                    //                 // fin del envio de la notificación
                    //             } else {
                    //                 //echo "Error al obtener el token de acceso a la API v1 de FCM.\n";
                    //                 $datos = array(
                    //                     'estado' => 0, 
                    //                     'mensaje' => 'Error al obtener el token de acceso a la API v1 de FCM'
                    //                 );

                    //                 $response->getBody()->write(json_encode($datos));
                    //                 return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                    //             }                            
                    //         }
                    //     } catch(\PDOException $e) {
                    //         $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
                    //         $response->getBody()->write(json_encode($datos));
                    //         return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                    //     }
                    //     //--------------------
                    // }
                    // FIN INDICACIÓN MEDICA



                    // // DIETA
                    // if($tipoNotificacion == 'dieta'){
                    //     // grabo la notificación    
                    //     $sql = 'DECLARE	@return_value int, @fechaEnvio datetime

                    //             EXEC @return_value = notificacionesDieta_nueva 
                    //                 @idInternacion = :idInternacion,
                    //                 @cama = :cama,
                    //                 @dniPaciente = :dniPaciente,
                    //                 @nombrePaciente = :nombrePaciente,
                    //                 @apellidoPaciente = :apellidoPaciente,
                    //                 @fechaIndicacion = :fechaIndicacion,
                    //                 @tipoIndicacion = :tipoIndicacion,
                    //                 @profesional = :profesional,
                    //                 @urgente = :urgente,
                    //                 @dietaIndicacion = :dietaIndicacion,
                    //                 @dietaCantidad = :dietaCantidad,
                    //                 @dietaFrecuencia = :dietaFrecuencia,
                    //                 @dietaDuracion = :dietaDuracion,
                    //                 @dietaObservaciones = :dietaObservaciones,
                    //                 @idNotificacionMarkey = :idNotificacionMarkey,
                    //                 @idLog = :idLog,
                    //                 @fechaEnvio = @fechaEnvio OUTPUT

                    //             SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';


                    //     $db = getConnectionTC();
                    //     $stmt = $db->prepare($sql);
                    //     $stmt->bindParam("idInternacion", $datosInt->idInternacion);
                    //     $stmt->bindParam("cama", $datosInt->cama);
                    //     $stmt->bindParam("dniPaciente", $datosInt->dniPaciente);
                    //     $stmt->bindParam("nombrePaciente", $datosInt->nombrePaciente);
                    //     $stmt->bindParam("apellidoPaciente", $datosInt->apellidoPaciente);
                    //     $stmt->bindParam("fechaIndicacion", $datosInd->fecha);
                    //     $stmt->bindParam("tipoIndicacion", $datosInd->tipoIndicacion);
                    //     $stmt->bindParam("profesional", $datosInd->profesional);
                    //     $stmt->bindParam("urgente", $datosInd->urgente);
                    //     $stmt->bindParam("dietaIndicacion", $datosDie->indicacion);
                    //     $stmt->bindParam("dietaCantidad", $datosDie->cantidad);
                    //     $stmt->bindParam("dietaFrecuencia", $datosDie->frecuencia);
                    //     $stmt->bindParam("dietaDuracion", $datosDie->duracion);
                    //     $stmt->bindParam("dietaObservaciones", $datosDie->observaciones);
                    //     $stmt->bindParam("idNotificacionMarkey", $datosNotif->idNotif);
                    //     $stmt->bindParam("idLog", $idLog);
                    //     $stmt->execute();
                    //     $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    //     $a = new \stdClass();
                    //     $a = $resultado[0];
                    //     $idNotificacion = (int)$a->estado;
                    //     $fechaNotificacion = $a->fechaEnvio;
                    //     unset($a);
                    //     $db = null;

                    //     // probado hasta acá


                    //     // // obtengo el token correspondiente al dispositivo que está usando el personal de la cocina.
                    //     // $sql = 'EXEC obtenerTokenDispositivo @loginDni = :loginDni';
                    //     // $db = getConnectionTC();
                    //     // $stmt = $db->prepare($sql);
                    //     // $stmt->bindParam("loginDni", $datosEnf->dniEnfermero);
                    //     // $stmt->execute();
                    //     // $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    //     // $db = null;
                        

                    // }
                    // // FIN DIETA

                    
                }else{
                    // error en los datos
                    $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos o faltan parámetros obligatorios.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }else{
                //acceso denegado. No envió el token de acceso
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

    public function enviarNotificacionDev1(Request $request, Response $response, $args){

        $projectId      = 'notificaciones-push-enfermeria'; // ID del proyecto de Firebase        
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosNotif     = json_decode($json); // array con los parámetros recibidos.

        //grabo el log en la tabla logNotificaciones
        $conLog = 'declare @return_value int
                exec @return_value = logNotificaciones_nuevo @datos = :datos
                select @return_value as id';  
        $db = getConnectionTC();
        $stmt = $db->prepare($conLog);
        $stmt->bindParam(':datos', $json);
        $stmt->execute();
        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $db = null; 
        foreach($resultado as $log){
            $idLog = $log->id;
        }

        // valido datos obligatorios

        $error = 0;
        $datos = array();

        // depende del tipo de notificación (dieta o indicacionMedica), los datos a validar son diferentes.
        
        // SI ES INDICACIÓN MÉDICA
        if (isset($datosNotif->tipoNotificacion) && $datosNotif->tipoNotificacion == 'indicacionMedica') {
            $datosEnf = $datosNotif->datos->enfermero ?? null;
            $datosInt = $datosNotif->datos->internacion ?? null;
            $datosInd = $datosNotif->datos->indicacionMedica ?? null;
            $datosMed = $datosNotif->datos->indicacionMedica->medicacion ?? null;

            
            if (empty($datosEnf->dniEnfermero)) $error++;
            if (empty($datosEnf->nombreEnfermero)) $error++;

            if (empty($datosInt->idInternacion)) $error++;
            if (empty($datosInt->cama)) $error++;
            if (empty($datosInt->dniPaciente)) $error++;
            if (empty($datosInt->nombrePaciente)) $error++;
            if (empty($datosInt->apellidoPaciente)) $error++;
            
            if (empty($datosInd->fecha)) $error++;
            if (!isset($datosInd->tipoIndicacion) || $datosInd->tipoIndicacion === '') $error++;
            if (empty($datosInd->profesional)) $error++;
            if (!isset($datosInd->urgente) || $datosInd->urgente === '') $error++;

            if (isset($datosInd->medicacion) && !empty((array)$datosInd->medicacion)) {
                if (empty($datosMed->generico)) $error++;
                if (empty($datosMed->dosis)) $error++;
                if (empty($datosMed->via)) $error++;
                if (empty($datosMed->frecuencia)) $error++;
            }else{
                $error++;
            }
        }
        
        
        // SI ES DIETA
        if(isset($datosNotif->tipoNotificacion) && $datosNotif->tipoNotificacion == 'dieta'){
            $datosEnf = $datosNotif->datos->enfermero ?? null;
            $datosInt = $datosNotif->datos->internacion ?? null;
            $datosInd = $datosNotif->datos->indicacionDieta ?? null;
            $datosDie = $datosNotif->datos->indicacionDieta->dieta ?? null;

            // no serán datos obligatorios, porque podría darse el caso de que el paciente no tenga asignado un enfermero.
            //if (empty($datosEnf->dniEnfermero)) $error++;
            //if (empty($datosEnf->nombreEnfermero)) $error++;
            
            if (empty($datosInt->idInternacion)) $error++;
            if (empty($datosInt->cama)) $error++;
            if (empty($datosInt->dniPaciente)) $error++;
            if (empty($datosInt->nombrePaciente)) $error++;
            if (empty($datosInt->apellidoPaciente)) $error++;
            
            if (empty($datosInd->fecha)) $error++;
            if (!isset($datosInd->tipoIndicacion) || $datosInd->tipoIndicacion === '') $error++;
            if (empty($datosInd->profesional)) $error++;
            if (!isset($datosInd->urgente) || $datosInd->urgente === '') $error++;

            if (isset($datosDie) && !empty($datosDie)) {
                if (empty($datosDie->indicacion)) $error++;
                // if (empty($datosDie->cantidad)) $error++;
                // if (empty($datosDie->frecuencia)) $error++;
                // if (empty($datosDie->duracion)) $error++;
                // if (empty($datosDie->observaciones)) $error++;
            }else{
                $error++;
            }
        }

        // fin validación de datos obligatorios

        

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                if($error == 0){

                    $tipoNotificacion = $datosNotif->tipoNotificacion;

                    // INDICACIÓN MEDICA
                    if($tipoNotificacion == 'indicacionMedica'){
                        try {
                            // grabo la notificación en la tabla TableroCamas.dbo.notificacionesMedicacion
                            $sql = 'DECLARE	@return_value int, @fechaEnvio datetime

                                    EXEC @return_value = notificacionesMedicacion_nueva 
                                        @dniEnfermero = :dniEnfermero,
                                        @nombreEnfermero = :nombreEnfermero,
                                        @idInternacion = :idInternacion,
                                        @cama = :cama,
                                        @dniPaciente = :dniPaciente,
                                        @nombrePaciente = :nombrePaciente,
                                        @apellidoPaciente = :apellidoPaciente,
                                        @fechaIndicacion = :fechaIndicacion,
                                        @tipoIndicacion = :tipoIndicacion,
                                        @profesional = :profesional,
                                        @urgente = :urgente,
                                        @medicacionGenerico = :medicacionGenerico,
                                        @medicacionDosis = :medicacionDosis,
                                        @medicacionVia = :medicacionVia,
                                        @medicacionFrecuencia = :medicacionFrecuencia,
                                        @idNotificacionMarkey = :idNotificacionMarkey,
                                        @idLog = :idLog,
                                        @fechaEnvio = @fechaEnvio OUTPUT

                                    SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';


                            $db = getConnectionTC();
                            $stmt = $db->prepare($sql);
                            $stmt->bindParam("dniEnfermero", $datosEnf->dniEnfermero);
                            $stmt->bindParam("nombreEnfermero", $datosEnf->nombreEnfermero);
                            $stmt->bindParam("idInternacion", $datosInt->idInternacion);
                            $stmt->bindParam("cama", $datosInt->cama);
                            $stmt->bindParam("dniPaciente", $datosInt->dniPaciente);
                            $stmt->bindParam("nombrePaciente", $datosInt->nombrePaciente);
                            $stmt->bindParam("apellidoPaciente", $datosInt->apellidoPaciente);
                            $stmt->bindParam("fechaIndicacion", $datosInd->fecha);
                            $stmt->bindParam("tipoIndicacion", $datosInd->tipoIndicacion);
                            $stmt->bindParam("profesional", $datosInd->profesional);
                            $stmt->bindParam("urgente", $datosInd->urgente);
                            $stmt->bindParam("medicacionGenerico", $datosMed->generico);
                            $stmt->bindParam("medicacionDosis", $datosMed->dosis);
                            $stmt->bindParam("medicacionVia", $datosMed->via);
                            $stmt->bindParam("medicacionFrecuencia", $datosMed->frecuencia);
                            $stmt->bindParam("idNotificacionMarkey", $datosNotif->idNotif);
                            $stmt->bindParam("idLog", $idLog);
                            $stmt->execute();
                            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                            $a = new \stdClass();
                            $a = $resultado[0];
                            $idNotificacion = (int)$a->estado;
                            $fechaNotificacion = $a->fechaEnvio;
                            unset($a);
                            $db = null;

                            // si hay algún compuesto para esta medicación, grabo los compuestos
                            if(isset($datosMed->compuestos) && !empty($datosMed->compuestos)){
                                $sqlCompuesto = 'EXEC notificacionesMedicacionCompuestos_nuevo 
                                    @idNotificacionMedicacion = :idNotificacionMedicacion, 
                                    @genericoCompuesto = :genericoCompuesto, 
                                    @dosis = :dosis';
                                $db = getConnectionTC();
                                $stmtCompuesto = $db->prepare($sqlCompuesto);
                                foreach($datosMed->compuestos as $compuesto){
                                    $stmtCompuesto->bindParam("idNotificacionMedicacion", $idNotificacion);
                                    $stmtCompuesto->bindParam("genericoCompuesto", $compuesto->genericoCompuesto);
                                    $stmtCompuesto->bindParam("dosis", $compuesto->dosis);
                                    $stmtCompuesto->execute();
                                }
                                unset($stmtCompuesto);
                                $db = null;
                            }                            

                            // obtengo el token correspondiente al dispositivo que está usando el enfermero.
                            $sql = 'EXEC obtenerTokenDispositivoAplicacion @loginDni = :loginDni, @idAplicacion = :idAplicacion';
                            $db = getConnectionTC();
                            $stmt = $db->prepare($sql);
                            $stmt->bindParam("loginDni", $datosEnf->dniEnfermero);
                            $idApp = 1;
                            $stmt->bindParam("idAplicacion", $idApp); // 1=App Notificaciones Enfermeria
                            $stmt->execute();
                            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                            $db = null; 


                            if(count($resultado) == 0){ 
                                // no hay token de fcm para el enfermero
                                $datos = array('estado' => 0, 'mensaje' => 'El enfermero '.$datosEnf->nombreEnfermero.' (DNI: '.$datosEnf->dniEnfermero.') no inició sesión en ningún dispositivo. La notificación se grabó para que la vea cuando inicie sesión.');
                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                            }else{   
                                // tengo el token del dispositivo del enfermero, ahora envío la notificación al dispositivo del enfermero
                                foreach($resultado as $dispositivo){
                                    $tokenFcm = $dispositivo->tokenFcm;
                                }

                                // ruta de acceso al archivo de credenciales de servicio de google firebase
                                $pathToServiceAccountKey = 'c:/wamp64/google-firebase/notificaciones-push-enfermeria-5ab1f1a3e003.json';
                                $accessToken = getFCMv1AccessToken($pathToServiceAccountKey); // obtengo el token de acceso a la API v1 de FCM
                                if ($accessToken) {
                                    // 1=Nueva indicación; 2=Modificación de indicacion; 3=Eliminación de indicación; 4=Suspención de medicación
                                    switch ($datosInd->tipoIndicacion){
                                        case 1: 
                                            $notificationTitle = 'NUEVA INDICACIÓN MÉDICA';
                                            break;
                                        case 2: 
                                            $notificationTitle = 'MODIFICACIÓN DE INDICACIÓN MÉDICA';
                                            break;
                                        case 3: 
                                            $notificationTitle = 'ELIMINACIÓN DE INDICACIÓN MÉDICA';
                                            break;
                                        case 4: 
                                            $notificationTitle = 'SUSPENCIÓN DE INDICACIÓN MÉDICA';
                                            break;
                                    }

                                    
                                    $notificationBody = 'Cama: '.$datosInt->cama.' - Paciente: '.$datosInt->nombrePaciente.' '.$datosInt->apellidoPaciente;

                                    if($datosInd->urgente <> 1){
                                        $urgente = 0;
                                    }

                                    // datos que se enviaran como datos personalizados en la notificación
                                    $extraData = [
                                        'idNotificacion' => (string)$idNotificacion,
                                        'fecha' => $fechaNotificacion,
                                        'idInternacion' => (string)$datosInt->idInternacion,
                                        'cama' => $datosInt->cama,
                                        'dniPaciente' => $datosInt->dniPaciente,
                                        'nombrePaciente' => $datosInt->nombrePaciente,
                                        'apellidoPaciente' => $datosInt->apellidoPaciente,
                                        'tipoNotificacion' => (string)$tipoNotificacion,
                                        'urgente' => (string)$datosInd->urgente,
                                        'profesional' => $datosInd->profesional,
                                        'medicamentoGenerico' => $datosMed->generico,
                                        'medicamentoDosis' => $datosMed->dosis,
                                        'medicamentoVia' => $datosMed->via,
                                        'medicamentoFrecuencia' => $datosMed->frecuencia,
                                        'medicacionCompuestos' => json_encode($datosMed->compuestos)
                                    ];

                                    
                                    $fcmUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

                                    // Construir el array que representa el payload JSON
                                    $messagePayloadArray = [
                                        'message' => [
                                            'token' => $tokenFcm, // El token del dispositivo
                                            'notification' => [
                                                'title' => $notificationTitle, // Título visible en la notificación
                                                'body' => $notificationBody,   // Cuerpo visible en la notificación
                                            ],
                                            'data' => $extraData, 
                                            // Opcional: Configuración específica para Android
                                            'android' => [
                                                'priority' => 'HIGH'
                                            ]
                                        ]
                                    ];

                                    // Convertir el array PHP a una cadena JSON
                                    $jsonPayload = json_encode($messagePayloadArray);


                                    // ------------------------------------------------------------
                                    // Parte 3: Enviar la solicitud HTTP POST usando cURL
                                    // ------------------------------------------------------------

                                    // Inicializar la sesión cURL
                                    $ch = curl_init();

                                    // Configurar las opciones de cURL
                                    curl_setopt($ch, CURLOPT_URL, $fcmUrl);          // La URL del API de FCM
                                    curl_setopt($ch, CURLOPT_POST, true);            // Es una solicitud POST
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Queremos que curl_exec() devuelva el resultado como cadena
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Buena práctica de seguridad: verificar certificado SSL
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    // Buena práctica de seguridad: verificar host del certificado

                                    // Configurar los encabezados HTTP
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                        'Content-Type: application/json', // Le decimos al servidor que el cuerpo es JSON
                                        "Authorization: Bearer {$accessToken}" // ¡Incluimos el token de acceso aquí!
                                    ]);

                                    // Configurar el cuerpo de la solicitud con el payload JSON
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

                                    // Ejecutar la solicitud cURL
                                    $respuestaCurl = curl_exec($ch);

                                    // ------------------------------------------------------------
                                    // Manejo de la respuesta y errores
                                    // ------------------------------------------------------------

                                    // Verificar si hubo errores de cURL (problemas de red, configuración, etc.)
                                    if (curl_errno($ch)) {
                                        $error_msg = curl_error($ch);
                                        //echo "Error de cURL: " . $error_msg . "\n";
                                        
                                        $datos = array(
                                            'estado' => 0, 
                                            'mensaje' => 'Error de cURL: ' . $error_msg . ' No se envío la notificación .'
                                        );

                                        $response->getBody()->write(json_encode($datos));
                                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);

                                    } else {
                                        // La solicitud se envió, ahora verifica la respuesta del servidor FCM

                                        // Obtener el status code HTTP de la respuesta
                                        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                                        // Decodificar la respuesta JSON del servidor FCM
                                        //$responseData = json_decode($respuestaCurl, true);  // no lo usaré por ahora

                                        // grabo el resultado del envio
                                        $con = 'exec notificacionesMedicacion_respuesta 
                                                    @idNotificacionMedicacion = :idNotificacionMedicacion, 
                                                    @status = :status,
                                                    @respuesta = :respuesta';  
                                        $db = getConnectionTC();
                                        $stmt = $db->prepare($con);
                                        $stmt->bindParam("idNotificacionMedicacion", $idNotificacion);
                                        $stmt->bindParam("status", $statusCode);
                                        $stmt->bindParam("respuesta", $respuestaCurl);
                                        $stmt->execute();

                                        if ($statusCode == 200) {
                                            // La notificación se envió correctamente
                                            $datos = array(
                                                'estado' => 1, 
                                                'idNotificacion' => $idNotificacion,
                                                'mensaje' => 'Se envió la notificación al enfermero.', 
                                                'resultadoEnvio' => 'Ok'
                                            );

                                            $response->getBody()->write(json_encode($datos));
                                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                                            
                                        } else {
                                            // Hubo un error en el lado del servidor FCM (ej: token inválido, permisos, etc.)
                                            $datos = array(
                                                'estado' => 0, 
                                                'idNotificacion' => $idNotificacion,
                                                'mensaje' => 'No se envió la notificación al enfermero', 
                                                'resultadoEnvio' => 'Error'
                                            );

                                            $response->getBody()->write(json_encode($datos));
                                            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                                        }
                                    }

                                    // Cerrar la sesión cURL para liberar recursos
                                    curl_close($ch);


                                    
                                    // fin del envio de la notificación
                                } else {
                                    //echo "Error al obtener el token de acceso a la API v1 de FCM.\n";
                                    $datos = array(
                                        'estado' => 0, 
                                        'mensaje' => 'Error al obtener el token de acceso a la API v1 de FCM'
                                    );

                                    $response->getBody()->write(json_encode($datos));
                                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                                }                            
                            }
                        } catch(\PDOException $e) {
                            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                        }
                        //--------------------
                    }
                    // FIN INDICACIÓN MEDICA



                    // DIETA


                    if($tipoNotificacion == 'dieta'){
                        // grabo la notificación    
                        $sql = 'DECLARE	@return_value int, @fechaEnvio datetime

                                EXEC @return_value = notificacionesDieta_nueva
                                    @dniEnfermero = :dniEnfermero,
                                    @nombreEnfermero = :nombreEnfermero, 
                                    @idInternacion = :idInternacion,
                                    @cama = :cama,
                                    @dniPaciente = :dniPaciente,
                                    @nombrePaciente = :nombrePaciente,
                                    @apellidoPaciente = :apellidoPaciente,
                                    @fechaIndicacion = :fechaIndicacion,
                                    @tipoIndicacion = :tipoIndicacion,
                                    @profesional = :profesional,
                                    @urgente = :urgente,
                                    @dietaIndicacion = :dietaIndicacion,
                                    @dietaCantidad = :dietaCantidad,
                                    @dietaFrecuencia = :dietaFrecuencia,
                                    @dietaDuracion = :dietaDuracion,
                                    @dietaObservaciones = :dietaObservaciones,
                                    @idNotificacionMarkey = :idNotificacionMarkey,
                                    @idLog = :idLog,
                                    @fechaEnvio = @fechaEnvio OUTPUT

                                SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';


                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("dniEnfermero", $datosEnf->dniEnfermero);
                        $stmt->bindParam("nombreEnfermero", $datosEnf->nombreEnfermero);
                        $stmt->bindParam("idInternacion", $datosInt->idInternacion);
                        $stmt->bindParam("cama", $datosInt->cama);
                        $stmt->bindParam("dniPaciente", $datosInt->dniPaciente);
                        $stmt->bindParam("nombrePaciente", $datosInt->nombrePaciente);
                        $stmt->bindParam("apellidoPaciente", $datosInt->apellidoPaciente);
                        $stmt->bindParam("fechaIndicacion", $datosInd->fecha);
                        $stmt->bindParam("tipoIndicacion", $datosInd->tipoIndicacion);
                        $stmt->bindParam("profesional", $datosInd->profesional);
                        $stmt->bindParam("urgente", $datosInd->urgente);
                        $stmt->bindParam("dietaIndicacion", $datosDie->indicacion);
                        $stmt->bindParam("dietaCantidad", $datosDie->cantidad);
                        $stmt->bindParam("dietaFrecuencia", $datosDie->frecuencia);
                        $stmt->bindParam("dietaDuracion", $datosDie->duracion);
                        $stmt->bindParam("dietaObservaciones", $datosDie->observaciones);
                        $stmt->bindParam("idNotificacionMarkey", $datosNotif->idNotif);
                        $stmt->bindParam("idLog", $idLog);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $a = new \stdClass();
                        $a = $resultado[0];
                        $idNotificacion = (int)$a->estado;
                        $fechaNotificacion = $a->fechaEnvio;
                        unset($a);
                        $db = null;


                        // obtengo el token correspondiente al dispositivo que está usando el personal de la cocina.
                        $sql = 'EXEC obtenerTokenDispositivoCocina';
                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        if(count($resultado) == 0){ 
                            // no hay token de fcm para el dispositivo
                            $datos = array('estado' => 0, 'mensaje' => 'No se encontró ningún dispositivo de Cocina al que se pueda enviar esta notificación.');
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                        }else{   
                            // tengo el token del dispositivo, ahora envío la notificación al dispositivo de cocina
                            foreach($resultado as $dispositivo){
                                $tokenFcm = $dispositivo->tokenFcm;
                            }

                            // ruta de acceso al archivo de credenciales de servicio de google firebase
                            //$pathToServiceAccountKey = 'c:/wamp64/google-firebase/notificaciones-push-enfermeria-5ab1f1a3e003.json';
                            $pathToServiceAccountKey = 'c:/wamp64/google-firebase/notificaciones-push-enfermeria-firebase-adminsdk-m8k0w-e1882fced6.json';
                            $accessToken = getFCMv1AccessToken($pathToServiceAccountKey); // obtengo el token de acceso a la API v1 de FCM
                            if ($accessToken) {
                                // 1=Nueva indicación; 2=Modificación de indicacion; 3=Eliminación de indicación; 4=Suspención de medicación
                                switch ($datosInd->tipoIndicacion){
                                    case 1: 
                                        $notificationTitle = 'NUEVA INDICACIÓN DE DIETA';
                                        break;
                                    case 2: 
                                        $notificationTitle = 'MODIFICACIÓN DE INDICACIÓN DE DIETA';
                                        break;
                                    case 3: 
                                        $notificationTitle = 'ELIMINACIÓN DE INDICACIÓN DE DIETA';
                                        break;
                                    case 4: 
                                        $notificationTitle = 'SUSPENCIÓN DE INDICACIÓN DE DIETA';
                                        break;
                                }

                                
                                $notificationBody = 'Cama: '.$datosInt->cama.' - Paciente: '.$datosInt->nombrePaciente.' '.$datosInt->apellidoPaciente;

                                if($datosInd->urgente <> 1){
                                    $urgente = 0;
                                }

                                // datos que se enviaran como datos personalizados en la notificación
                                $extraData = [
                                    'idNotificacion' => (string)$idNotificacion,
                                    'fecha' => $fechaNotificacion,
                                    'idInternacion' => (string)$datosInt->idInternacion,
                                    'cama' => $datosInt->cama,
                                    'dniPaciente' => $datosInt->dniPaciente,
                                    'nombrePaciente' => $datosInt->nombrePaciente,
                                    'apellidoPaciente' => $datosInt->apellidoPaciente,
                                    'tipoNotificacion' => (string)$tipoNotificacion,
                                    'urgente' => (string)$datosInd->urgente,
                                    'profesional' => $datosInd->profesional,
                                    'dietaIndicacion' => $datosDie->indicacion,
                                    'dietaCantidad' => $datosDie->cantidad,
                                    'dietaFrecuencia' => $datosDie->frecuencia,
                                    'dietaDuracion' => $datosDie->duracion,
                                    'dietaObservacion' => $datosDie->observaciones
                                ];

                                
                                $fcmUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

                                // Construir el array que representa el payload JSON
                                $messagePayloadArray = [
                                    'message' => [
                                        'token' => $tokenFcm, // El token del dispositivo
                                        'notification' => [
                                            'title' => $notificationTitle, // Título visible en la notificación
                                            'body' => $notificationBody,   // Cuerpo visible en la notificación
                                        ],
                                        'data' => $extraData, 
                                        // Opcional: Configuración específica para Android
                                        'android' => [
                                            'priority' => 'HIGH'
                                        ]
                                    ]
                                ];

                                // Convertir el array PHP a una cadena JSON
                                $jsonPayload = json_encode($messagePayloadArray);


                                // ------------------------------------------------------------
                                // Parte 3: Enviar la solicitud HTTP POST usando cURL
                                // ------------------------------------------------------------

                                // Inicializar la sesión cURL
                                $ch = curl_init();

                                // Configurar las opciones de cURL
                                curl_setopt($ch, CURLOPT_URL, $fcmUrl);          // La URL del API de FCM
                                curl_setopt($ch, CURLOPT_POST, true);            // Es una solicitud POST
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Queremos que curl_exec() devuelva el resultado como cadena
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Buena práctica de seguridad: verificar certificado SSL
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    // Buena práctica de seguridad: verificar host del certificado

                                // Configurar los encabezados HTTP
                                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                    'Content-Type: application/json', // Le decimos al servidor que el cuerpo es JSON
                                    "Authorization: Bearer {$accessToken}" // ¡Incluimos el token de acceso aquí!
                                ]);

                                // Configurar el cuerpo de la solicitud con el payload JSON
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

                                // Ejecutar la solicitud cURL
                                $respuestaCurl = curl_exec($ch);

                                // ------------------------------------------------------------
                                // Manejo de la respuesta y errores
                                // ------------------------------------------------------------

                                // Verificar si hubo errores de cURL (problemas de red, configuración, etc.)
                                if (curl_errno($ch)) {
                                    $error_msg = curl_error($ch);
                                    //echo "Error de cURL: " . $error_msg . "\n";
                                    
                                    $datos = array(
                                        'estado' => 0, 
                                        'mensaje' => 'Error de cURL: ' . $error_msg . ' No se envío la notificación .'
                                    );

                                    $response->getBody()->write(json_encode($datos));
                                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);

                                } else {
                                    // La solicitud se envió, ahora verifica la respuesta del servidor FCM

                                    // Obtener el status code HTTP de la respuesta
                                    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                                    // Decodificar la respuesta JSON del servidor FCM
                                    //$responseData = json_decode($respuestaCurl, true);  // no lo usaré por ahora

                                    // grabo el resultado del envio
                                    $con = 'exec notificacionesDieta_respuesta 
                                                @idNotificacionDieta = :idNotificacionDieta, 
                                                @status = :status,
                                                @respuesta = :respuesta';  
                                    $db = getConnectionTC();
                                    $stmt = $db->prepare($con);
                                    $stmt->bindParam("idNotificacionDieta", $idNotificacion);
                                    $stmt->bindParam("status", $statusCode);
                                    $stmt->bindParam("respuesta", $respuestaCurl);
                                    $stmt->execute();

                                    if ($statusCode == 200) {
                                        // La notificación se envió correctamente
                                        $datos = array(
                                            'estado' => 1, 
                                            'idNotificacion' => $idNotificacion,
                                            'mensaje' => 'Se envió la notificación a cocina.', 
                                            'resultadoEnvio' => 'Ok'
                                        );

                                        $response->getBody()->write(json_encode($datos));
                                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                                        
                                    } else {
                                        // Hubo un error en el lado del servidor FCM (ej: token inválido, permisos, etc.)
                                        $datos = array(
                                            'estado' => 0, 
                                            'idNotificacion' => $idNotificacion,
                                            'mensaje' => 'No se envió la notificación al enfermero', 
                                            'resultadoEnvio' => 'Error'
                                        );

                                        $response->getBody()->write(json_encode($datos));
                                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                                    }
                                }

                                // Cerrar la sesión cURL para liberar recursos
                                curl_close($ch);


                                
                                // fin del envio de la notificación
                            } else {
                                //echo "Error al obtener el token de acceso a la API v1 de FCM.\n";
                                $datos = array(
                                    'estado' => 0, 
                                    'mensaje' => 'Error al obtener el token de acceso a la API v1 de FCM'
                                );

                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                            }                            
                        }
                        

                        

                    }
                    // FIN DIETA

                    
                }else{
                    // error en los datos
                    $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos o faltan parámetros obligatorios.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }else{
                //acceso denegado. No envió el token de acceso
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

    public function enviarNotificacionDev(Request $request, Response $response, $args){

        $projectId      = 'notificaciones-push-enfermeria'; // ID del proyecto de Firebase        
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosNotif     = json_decode($json); // array con los parámetros recibidos.

        //grabo el log en la tabla logNotificaciones
        $conLog = 'declare @return_value int
                exec @return_value = logNotificaciones_nuevo @datos = :datos
                select @return_value as id';  
        $db = getConnectionTC();
        $stmt = $db->prepare($conLog);
        $stmt->bindParam(':datos', $json);
        $stmt->execute();
        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $db = null; 
        foreach($resultado as $log){
            $idLog = $log->id;
        }

        // valido datos obligatorios

        $error = 0;
        $datos = array();

        // depende del tipo de notificación (dieta o indicacionMedica), los datos a validar son diferentes.
        
        // SI ES INDICACIÓN MÉDICA
        if (isset($datosNotif->tipoNotificacion) && $datosNotif->tipoNotificacion == 'indicacionMedica') {
            $datosEnf = $datosNotif->datos->enfermero ?? null;
            $datosInt = $datosNotif->datos->internacion ?? null;
            $datosInd = $datosNotif->datos->indicacionMedica ?? null;
            $datosMed = $datosNotif->datos->indicacionMedica->medicacion ?? null;

            
            if (empty($datosEnf->dniEnfermero)) $error++;
            if (empty($datosEnf->nombreEnfermero)) $error++;

            if (empty($datosInt->idInternacion)) $error++;
            if (empty($datosInt->cama)) $error++;
            if (empty($datosInt->dniPaciente)) $error++;
            if (empty($datosInt->nombrePaciente)) $error++;
            if (empty($datosInt->apellidoPaciente)) $error++;
            
            if (empty($datosInd->fecha)) $error++;
            if (!isset($datosInd->tipoIndicacion) || $datosInd->tipoIndicacion === '') $error++;
            if (empty($datosInd->profesional)) $error++;
            if (!isset($datosInd->urgente) || $datosInd->urgente === '') $error++;

            if (isset($datosInd->medicacion) && !empty((array)$datosInd->medicacion)) {
                if (empty($datosMed->generico)) $error++;
                if (empty($datosMed->dosis)) $error++;
                if (empty($datosMed->via)) $error++;
                if (empty($datosMed->frecuencia)) $error++;
            }else{
                $error++;
            }
        }
        
        
        // SI ES DIETA
        if(isset($datosNotif->tipoNotificacion) && $datosNotif->tipoNotificacion == 'dieta'){
            $datosEnf = $datosNotif->datos->enfermero ?? null;
            $datosInt = $datosNotif->datos->internacion ?? null;
            $datosInd = $datosNotif->datos->indicacionDieta ?? null;
            $datosDie = $datosNotif->datos->indicacionDieta->dieta ?? null;

            // no serán datos obligatorios, porque podría darse el caso de que el paciente no tenga asignado un enfermero.
            //if (empty($datosEnf->dniEnfermero)) $error++;
            //if (empty($datosEnf->nombreEnfermero)) $error++;
            
            if (empty($datosInt->idInternacion)) $error++;
            if (empty($datosInt->cama)) $error++;
            if (empty($datosInt->dniPaciente)) $error++;
            if (empty($datosInt->nombrePaciente)) $error++;
            if (empty($datosInt->apellidoPaciente)) $error++;
            
            if (empty($datosInd->fecha)) $error++;
            if (!isset($datosInd->tipoIndicacion) || $datosInd->tipoIndicacion === '') $error++;
            if (empty($datosInd->profesional)) $error++;
            if (!isset($datosInd->urgente) || $datosInd->urgente === '') $error++;

            if (isset($datosDie) && !empty($datosDie)) {
                if (empty($datosDie->indicacion)) $error++;
                // if (empty($datosDie->cantidad)) $error++;
                // if (empty($datosDie->frecuencia)) $error++;
                // if (empty($datosDie->duracion)) $error++;
                // if (empty($datosDie->observaciones)) $error++;
            }else{
                $error++;
            }
        }

        // fin validación de datos obligatorios

        

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                if($error == 0){

                    // GRABO LA NOTIFICACIÓN
                    $tipoNotificacion = $datosNotif->tipoNotificacion;
                    $sql = 'DECLARE	@return_value int

                            EXEC @return_value = notificaciones_nueva 
                                @tipoIndicacion = :tipoIndicacion,
                                @idInternacion = :idInternacion,
                                @cama = :cama,
                                @dniPaciente = :dniPaciente,
                                @nombrePaciente = :nombrePaciente,
                                @apellidoPaciente = :apellidoPaciente,
                                @fechaIndicacion = :fechaIndicacion,
                                @profesional = :profesional,
                                @urgente = :urgente,
                                @idNotificacionMarkey = :idNotificacionMarkey,
                                @idLog = :idLog,
                                @medicacionGenerico = :medicacionGenerico,
                                @medicacionDosis = :medicacionDosis,
                                @medicacionVia = :medicacionVia,
                                @medicacionFrecuencia = :medicacionFrecuencia,
                                @dietaIndicacion = :dietaIndicacion,
                                @dietaCantidad = :dietaCantidad,
                                @dietaFrecuencia = :dietaFrecuencia,
                                @dietaDuracion = :dietaDuracion,
                                @dietaObservaciones = :dietaObservaciones

                            SELECT	@return_value as idNotificacion,';


                    $db = getConnectionTC();
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam("tipoIndicacion", $datosInd->tipoIndicacion);
                    
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $a = new \stdClass();
                    $a = $resultado[0];
                    $idNotificacion = (int)$a->estado;
                    $fechaNotificacion = $a->fechaEnvio;
                    unset($a);
                    $db = null;
                    
                    
                    
                    
                    // fin grabación de notificación


                    // INDICACIÓN MEDICA
                    if($tipoNotificacion == 'indicacionMedica'){
                        try {
                            // grabo la notificación en la tabla TableroCamas.dbo.notificacionesMedicacion
                            $sql = 'DECLARE	@return_value int, @fechaEnvio datetime

                                    EXEC @return_value = notificacionesMedicacion_nueva 
                                        @dniEnfermero = :dniEnfermero,
                                        @nombreEnfermero = :nombreEnfermero,
                                        @idInternacion = :idInternacion,
                                        @cama = :cama,
                                        @dniPaciente = :dniPaciente,
                                        @nombrePaciente = :nombrePaciente,
                                        @apellidoPaciente = :apellidoPaciente,
                                        @fechaIndicacion = :fechaIndicacion,
                                        @tipoIndicacion = :tipoIndicacion,
                                        @profesional = :profesional,
                                        @urgente = :urgente,
                                        @medicacionGenerico = :medicacionGenerico,
                                        @medicacionDosis = :medicacionDosis,
                                        @medicacionVia = :medicacionVia,
                                        @medicacionFrecuencia = :medicacionFrecuencia,
                                        @idNotificacionMarkey = :idNotificacionMarkey,
                                        @idLog = :idLog,
                                        @fechaEnvio = @fechaEnvio OUTPUT

                                    SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';


                            $db = getConnectionTC();
                            $stmt = $db->prepare($sql);
                            $stmt->bindParam("dniEnfermero", $datosEnf->dniEnfermero);
                            $stmt->bindParam("nombreEnfermero", $datosEnf->nombreEnfermero);
                            $stmt->bindParam("idInternacion", $datosInt->idInternacion);
                            $stmt->bindParam("cama", $datosInt->cama);
                            $stmt->bindParam("dniPaciente", $datosInt->dniPaciente);
                            $stmt->bindParam("nombrePaciente", $datosInt->nombrePaciente);
                            $stmt->bindParam("apellidoPaciente", $datosInt->apellidoPaciente);
                            $stmt->bindParam("fechaIndicacion", $datosInd->fecha);
                            $stmt->bindParam("tipoIndicacion", $datosInd->tipoIndicacion);
                            $stmt->bindParam("profesional", $datosInd->profesional);
                            $stmt->bindParam("urgente", $datosInd->urgente);
                            $stmt->bindParam("medicacionGenerico", $datosMed->generico);
                            $stmt->bindParam("medicacionDosis", $datosMed->dosis);
                            $stmt->bindParam("medicacionVia", $datosMed->via);
                            $stmt->bindParam("medicacionFrecuencia", $datosMed->frecuencia);
                            $stmt->bindParam("idNotificacionMarkey", $datosNotif->idNotif);
                            $stmt->bindParam("idLog", $idLog);
                            $stmt->execute();
                            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                            $a = new \stdClass();
                            $a = $resultado[0];
                            $idNotificacion = (int)$a->estado;
                            $fechaNotificacion = $a->fechaEnvio;
                            unset($a);
                            $db = null;

                            // si hay algún compuesto para esta medicación, grabo los compuestos
                            if(isset($datosMed->compuestos) && !empty($datosMed->compuestos)){
                                $sqlCompuesto = 'EXEC notificacionesMedicacionCompuestos_nuevo 
                                    @idNotificacionMedicacion = :idNotificacionMedicacion, 
                                    @genericoCompuesto = :genericoCompuesto, 
                                    @dosis = :dosis';
                                $db = getConnectionTC();
                                $stmtCompuesto = $db->prepare($sqlCompuesto);
                                foreach($datosMed->compuestos as $compuesto){
                                    $stmtCompuesto->bindParam("idNotificacionMedicacion", $idNotificacion);
                                    $stmtCompuesto->bindParam("genericoCompuesto", $compuesto->genericoCompuesto);
                                    $stmtCompuesto->bindParam("dosis", $compuesto->dosis);
                                    $stmtCompuesto->execute();
                                }
                                unset($stmtCompuesto);
                                $db = null;
                            }                            

                            
                        } catch(\PDOException $e) {
                            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                        }
                    }                    

                    // DIETA

                    if($tipoNotificacion == 'dieta'){
                        // grabo la notificación    
                        $sql = 'DECLARE	@return_value int, @fechaEnvio datetime

                                EXEC @return_value = notificacionesDieta_nueva
                                    @dniEnfermero = :dniEnfermero,
                                    @nombreEnfermero = :nombreEnfermero, 
                                    @idInternacion = :idInternacion,
                                    @cama = :cama,
                                    @dniPaciente = :dniPaciente,
                                    @nombrePaciente = :nombrePaciente,
                                    @apellidoPaciente = :apellidoPaciente,
                                    @fechaIndicacion = :fechaIndicacion,
                                    @tipoIndicacion = :tipoIndicacion,
                                    @profesional = :profesional,
                                    @urgente = :urgente,
                                    @dietaIndicacion = :dietaIndicacion,
                                    @dietaCantidad = :dietaCantidad,
                                    @dietaFrecuencia = :dietaFrecuencia,
                                    @dietaDuracion = :dietaDuracion,
                                    @dietaObservaciones = :dietaObservaciones,
                                    @idNotificacionMarkey = :idNotificacionMarkey,
                                    @idLog = :idLog,
                                    @fechaEnvio = @fechaEnvio OUTPUT

                                SELECT	@return_value as estado, @fechaEnvio as fechaEnvio';


                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("dniEnfermero", $datosEnf->dniEnfermero);
                        $stmt->bindParam("nombreEnfermero", $datosEnf->nombreEnfermero);
                        $stmt->bindParam("idInternacion", $datosInt->idInternacion);
                        $stmt->bindParam("cama", $datosInt->cama);
                        $stmt->bindParam("dniPaciente", $datosInt->dniPaciente);
                        $stmt->bindParam("nombrePaciente", $datosInt->nombrePaciente);
                        $stmt->bindParam("apellidoPaciente", $datosInt->apellidoPaciente);
                        $stmt->bindParam("fechaIndicacion", $datosInd->fecha);
                        $stmt->bindParam("tipoIndicacion", $datosInd->tipoIndicacion);
                        $stmt->bindParam("profesional", $datosInd->profesional);
                        $stmt->bindParam("urgente", $datosInd->urgente);
                        $stmt->bindParam("dietaIndicacion", $datosDie->indicacion);
                        $stmt->bindParam("dietaCantidad", $datosDie->cantidad);
                        $stmt->bindParam("dietaFrecuencia", $datosDie->frecuencia);
                        $stmt->bindParam("dietaDuracion", $datosDie->duracion);
                        $stmt->bindParam("dietaObservaciones", $datosDie->observaciones);
                        $stmt->bindParam("idNotificacionMarkey", $datosNotif->idNotif);
                        $stmt->bindParam("idLog", $idLog);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $a = new \stdClass();
                        $a = $resultado[0];
                        $idNotificacion = (int)$a->estado;
                        $fechaNotificacion = $a->fechaEnvio;
                        unset($a);
                        $db = null;
                    }

                    // ENVÍO LA NOTIFICACIÓN A QUIEN CORRESPONDA: si es indicación médica envio al Enfermero. Si es dieta envío al enfermero y a cocina.

                    // busco el tokenFcm de los dispositivo a los cuales debo enviar la notificacion.
                    if($tipoNotificacion == 'indicacionMédica'){

                    }
                    



                    
                }else{
                    // error en los datos
                    $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos o faltan parámetros obligatorios.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }else{
                //acceso denegado. No envió el token de acceso
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



    // ************************
    // VER NOTIFICACIONES
    // ************************

    // VER NOTIFICACIONES ENFERMERO
    public function verNotificacionesEnfermero(Request $request, Response $response, $args){
        // GET: /notificaciones/verNotificaciones
        // Devuelve un JSON con las notificaciones para un enfermero.
        // recibe como parámetros el token de acceso y el dni del enfermero.
        // El JSON de salida tendrá la siguiente estructura:
        // [
        //     {
        //         "idNotificacion": 13946,
        //         "dniEnfemero": 11111111,
        //         "para": "ENFERMERO ENFERMERO",
        //         "fecha": "19-05-2025 17:02:18",
        //         "cama": "400",
        //         "paciente": "ALONSO, PEPONE",
        //         "tipoNotificacion": 4,
        //         "textoNotificacion": "PARACETAMOL 500mg",
        //         "resultadoEnvio": "1",
        //         "leida": 0,
        //         "urgente": 1
        //     }
        // ]


        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $dniEnfermero   = $request->getQueryParams()['dniEnfermero'] ?? null;

        $error = 0;
        $datos = array();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                if($error == 0){
                    // busco las notificaciones de este servicio
                    $con = 'EXEC notificacionesPushEnfermero_ver @dni = :dni';
                    try {
                        $db = getConnectionTC();
                        $stmt = $db->prepare($con);
                        $stmt->bindParam("dni", $dniEnfermero);
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        foreach($res as $notificacion){
                            $n = new \stdClass();
                            $n->idNotificacion      = (int)$notificacion->idNotificacion;
                            $n->dniEnfemero         = (int)$notificacion->dniEnfermero;
                            $n->para                = $notificacion->para;
                            $n->fecha               = date_format(date_create($notificacion->fecha), 'd-m-Y H:i:s');
                            $n->cama                = $notificacion->cama;
                            $n->paciente            = $notificacion->paciente;
                            $n->tipoNotificacion    = (int)$notificacion->tipoNotificacion;
                            switch ($n->tipoNotificacion) {
                                case 1: $n->tituloNotificacion = 'NUEVA INDICACIÓN'; break;
                                case 2: $n->tituloNotificacion = 'MODIFICACIÓN EN INDICACIÓN'; break;
                                case 3: $n->tituloNotificacion = 'ELIMINACIÓN DE INDICACIÓN'; break;
                                case 4: $n->tituloNotificacion = 'SUSPENCIÓN DE INDICACIÓN'; break;
                                case 5: $n->tituloNotificacion = 'CAMBIOS EN LA DIETA DEL PACIENTE'; break;
                                default: $n->tituloNotificacion = 'NOTIFICACIÓN';
                            }
                            $n->textoNotificacion   = $notificacion->textoNotificacion;
                            $n->resultadoEnvio      = $notificacion->resultadoEnvio;
                            $n->leida               = (int)$notificacion->leida;                        
                            $n->urgente             = (int)$notificacion->urgente; 
                            
                            array_push($datos,$n);
                            unset($n);
                        }

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);                        
                    } catch(PDOException $e) {
                        $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                    }
                }else{
                    $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400); 
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

    // VER NOTIFICACIONES COCINA
    public function verNotificacionesDieta(Request $request, Response $response, $args){
        // GET: /push/verCocina
        // Devuelve un JSON con las notificaciones para cocina.
        // recibe como parámetros el token de acceso y el dni del usuario logueado en el celular.
        // El JSON de salida tendrá la siguiente estructura:
        // [
        //     {
                //     "idNotificacion": 22095,
                //     "idInternacion": 542121,
                //     "fecha": "28-07-2025 16:35:15",
                //     "cama": "209",
                //     "paciente": "MAKUC, MARIA",
                //     "tipoNotificacion": 1,
                //     "tituloNotificacion": "NUEVA INDICACIÓN",
                //     "profesional":"APELLIDO, NOMBRE",
                //     "urgente": 0,
                //     "indicacion": "Dieta: LÍQUIDA",
                //     "cantidad": "1", 
                //     "frecuencia": "1",
                //     "duracion": "",
                //     "idNotificacionMarkey": 121545,
                //     "idLog": 4542,
                //     "fechaEnvio": "2025-07-28 16:35:15",
                //     "statusCodeEnvio": 200,
                //     "observaciones": "No consumir alimentos sólidos.",
                //     "resultadoEnvio": "1",
                //     "leida": 2
                // }
        // ]


        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $dni            = $request->getQueryParams()['dni'] ?? null;

        $error = 0;
        $datos = array();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                if($error == 0){
                    // busco las notificaciones de este servicio
                    $con = 'EXEC notificacionesPushCocina_ver @dni = :dni';
                    try {
                        $db = getConnectionTC();
                        $stmt = $db->prepare($con);
                        $stmt->bindParam("dni", $dni);
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        foreach($res as $notificacion){
                            $n = new \stdClass();
                            $n->idNotificacion      = (int)$notificacion->idNotificacion;
                            $n->idInternacion       = (int)$notificacion->idInternacion;
                            $n->fecha               = date_format(date_create($notificacion->fechaIndicacion), 'd-m-Y H:i:s');
                            $n->cama                = $notificacion->cama;
                            $n->paciente            = $notificacion->apellidoPaciente . ', '.$notificacion->nombrePaciente;
                            $n->tipoNotificacion    = (int)$notificacion->tipoIndicacion;
                            switch ($n->tipoNotificacion) {
                                case 1: $n->tituloNotificacion = 'NUEVA INDICACIÓN'; break;
                                case 2: $n->tituloNotificacion = 'MODIFICACIÓN EN INDICACIÓN'; break;
                                case 3: $n->tituloNotificacion = 'ELIMINACIÓN DE INDICACIÓN'; break;
                                case 4: $n->tituloNotificacion = 'SUSPENCIÓN DE INDICACIÓN'; break;
                                default: $n->tituloNotificacion = 'NOTIFICACIÓN DIETA';
                            }
                            $n->profesional         = $notificacion->profesional;
                            $n->urgente             = (int)$notificacion->urgente;
                            $n->indicacion          = $notificacion->dietaIndicacion;
                            $n->cantidad            = $notificacion->dietaCantidad;
                            $n->frecuencia          = $notificacion->dietaFrecuencia;
                            $n->duracion            = $notificacion->dietaDuracion;
                            $n->observaciones       = $notificacion->dietaObservaciones;
                            $n->idNotificacionMarkey   = (int)$notificacion->idNotificacionMarkey;
                            $n->idLog               = (int)$notificacion->idLog;
                            $n->fechaEnvio          = $notificacion->fechaEnvio;
                            $n->statusCodeEnvio     = (int)$notificacion->statusCodeEnvio;
                            $n->leida               = (int)$notificacion->leida;
                            
                            array_push($datos,$n);
                            unset($n);
                        }

                        

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);                        
                    } catch(PDOException $e) {
                        $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                    }
                }else{
                    $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400); 
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

    // MARCAR COMO LEIDA
    public function notificacionesDietaLeida(Request $request, Response $response, $args){
        $tokenAcceso        = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosJson      = json_decode($json); 

        $idNotificacion     = $datosJson->idNotificacion ?? '';
        $dni                = $datosJson->dni ?? '';
        $leida_por          = $datosJson->leida_por ?? '';
        $dispositivo        = $datosJson->dispositivo ?? '';

        $error = 0;
        $datos = array();

        // verifico que los datos no se reciban vacios
        if($idNotificacion == ''){ $error ++; }
        if($dni == ''){ $error ++; }
        if($leida_por == ''){ $error ++; }
        if($dispositivo == ''){ $error ++; }
        

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                if($error == 0){
                    // grabo el log de notificación leida en TableroCamas.dbo.notificacionesPushLeidas
                    $sql = 'declare @return_value int, @mensaje varchar(255)
                            EXEC @return_value = notificacionDietaLeida
                                @idNotificacion = :idNotificacion,
                                @dni = :dni,
                                @leida_por = :leida_por,
                                @dispositivo = :dispositivo,
                                @mensaje = @mensaje OUTPUT
                            select @return_value as return_value, @mensaje as mensaje';
                    try {
                        $db = getConnectionTC();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idNotificacion", $idNotificacion);
                        $stmt->bindParam("dni", $dni);
                        $stmt->bindParam("leida_por", $leida_por);
                        $stmt->bindParam("dispositivo", $dispositivo);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null; 
                        $notif = new \stdClass();
                        $notif = $resultado[0];
                        $datos = array();
                        $datos = array('estado' => $notif->return_value, 'mensaje' => $notif->mensaje);

                        
                        if($notif->return_value == 1){
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }else{
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                        }
                    } catch(PDOException $e) {
                        $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
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

    // PRUEBA
    public function prueba(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
                

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                $sql = 'select * from camas where id_cama = 2';
                try {
                    $db = getConnection();
                    $stmt = $db->prepare($sql);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;                     
                    
                    $datos = $resultado;
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                } catch(PDOException $e) {
                    $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
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