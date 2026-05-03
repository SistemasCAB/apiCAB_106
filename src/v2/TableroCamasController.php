<?php
namespace App\V2;

require_once __DIR__ . '/../Common/helpers.php'; // Importa las funciones comunes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;



class TableroCamasController
{

//______________ APLICACION ______________________________________________________________________

    // VERSIÓN AUTORIZADA
    public function versionAutorizada(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idAplicacion   = $request->getQueryParams()['idAplicacion'] ?? null;
        $nroVersion     = $request->getQueryParams()['nroVersion'] ?? null;

        $error = 0;
        $datos = array();

        if($nroVersion == '' || $idAplicacion == ''){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    $sql = 'EXEC tc_versionAutorizada @idAplicacion = :idAplicacion, @nroVersion = :nroVersion';
                    try {
                        $db = getConeccionCAB(); 
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idAplicacion", $idAplicacion);
                        $stmt->bindParam("nroVersion", $nroVersion);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        foreach($resultado as $version){
                            $datos = array(
                                'versionActual' => (int)$version->versionActual,
                                'autorizada' => $version->autorizada,
                                'idAplicacion' => (int)$version->idAplicacion,
                                'aplicacion' => $version->aplicacion,
                                'versionAutorizada' => (int)$version->versionAutorizada,
                                'fecha' => $version->fecha,
                                'mensaje' => $version->mensaje
                            );
                        }

                        $response->getBody()->write(json_encode($datos));

                        if($version->autorizada == 'Si'){
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }else{
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                        }  
                    } catch(\PDOException $e) {
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

    // HORA DEL SERVIDOR
    public function horaServidor(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        
        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                $fechaServidor = fechaHoraServidorSQL();
                $datos = array('hora' => $fechaServidor);

                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
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

    // LOGIN
    public function login(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosLogin     = json_decode($json);
   
        $tdocCodigo     = $datosLogin->tdocCodigo ?? null;
        $nroDocumento   = $datosLogin->nroDocumento ?? null;
        $clave          = $datosLogin->clave ?? null;
        $idAplicacion   = $datosLogin->idAplicacion ?? null;

        $error = 0;
        $datos = array();

        if($tdocCodigo == ''){ $error ++; }
        if($nroDocumento == ''){ $error ++; } 
        if($clave == ''){ $error ++; }
        if($idAplicacion == ''){ $error ++;}

        // si no envió el tokenAcceso
        if(!isset($tokenAcceso[0])){            
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Si el token enviado no es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // si los parámetros recibos no son válidos
        if ($error > 0) {
            $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        
        // Inicio sesión en Markey
        require_once '../class/Markey.php';
        $datosLogin = new \Markey;
        $resultadoLogin = $datosLogin->loginMarkey($nroDocumento, $clave);

        // si el login falló.
        if ($resultadoLogin->estado <> 1){
            $datos = array('estado' => 403, 'mensaje' => 'Acceso denegado. Usuario y/o contraseña incorrectos.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);    
        }
                        
        $sql = 'EXEC accesoAplicacion
                    @tdocCodigo = :tdocCodigo,
                    @nroDocumento = :nroDocumento,
                    @idAplicacion = :idAplicacion';

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare($sql);
            $stmt->bindParam("tdocCodigo", $tdocCodigo);
            $stmt->bindParam("nroDocumento", $nroDocumento);
            $stmt->bindParam("idAplicacion", $idAplicacion);
            $stmt->execute();
            $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            // si no tiene acceso a la aplicación.
            if($res[0]->estado == 0){
                $datos = array('estado' => 403, 'mensaje' => $res[0]->mensaje);
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);    
            }
            

            foreach($res as $login){
                $u = new \stdClass();
                $u->estado = (int)$login->estado;
                $u->apellido = $login->apellido;
                $u->nombre = $login->nombre;
                $u->tdocCodigo = (int)$login->tdocCodigo;
                $u->tdocDescripcion = $login->tdocDescripcion;
                $u->nroDocumento = $login->nroDocumento;
                $u->idUsuario = (int)$login->idUsuario;
                $u->idAplicacion = (int)$login->idAplicacion;
                $u->aplicacion = $login->aplicacion;
                if (!empty($login->servicios)) {
                    $u->servicios = json_decode($login->servicios, true);
                }else{
                    $u->servicios = array();
                };
                array_push($datos,$u);
                unset($u);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            
        } catch(\PDOException $e) {
            $datos = array('estado' => 500, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }    

                
            
    }



//______________ CAMAS ___________________________________________________________________________

    //ACTUALIZAR CAMAS DESDE MARKEY
    public function actualizarCamasMarkey(Request $request, Response $response, $args){
        /* Obtiene los datos de las camas de Markey desde el servicio web de Markey ObtenerCamas.
        Los datos son guardados en la tabla camasMarkey en la base de datos CAB.
        */

        $tokenAcceso    = $request->getHeader('TokenAcceso');

        if(!isset($tokenAcceso[0])){
            //acceso denegado. No envió el token de acceso
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if(verificarToken($tokenAcceso[0]) === false){
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        // acceso permitido
        // 1) Obtengo las camas desde Markey
        require_once '../class/Markey.php';
        $markey = new \Markey; 
        $camas = $markey->getCamasMarkey(); 
        $cantCamasMarkey = count($camas);

        $datos = array();

        $sql = 'EXEC Markey_VerCamas';
        $stmt = getConeccionCAB()->query($sql);
        $camasCAB = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $cantCamasCab = count($camasCAB);

        //var_dump($camasCAB);

        $borrarTodo = 0;

        // verifico si la cantidad de camas es la misma
        if($cantCamasMarkey == $cantCamasCab){
            $arrayCM    = array();
            $arrayCAB   = array();

            foreach($camas as $cm){
                array_push($arrayCM,(int)$cm->id_cama);
            }

            foreach($camasCAB as $cab){
                array_push($arrayCAB,(int)$cab->idCama);
            }

            $diferencias = 0;

            // la cantidad de camas es la misma pero verifico si son las mismas camas (verifico el idCama). Si hay diferencias, borro todo y cargo de nuevo
            $resultado = array_diff($arrayCM, $arrayCAB);
            if (count($resultado) > 0){
                $diferencias ++;
            }

            if($diferencias <> 0){
                $borrarTodo ++;
            }
        }else{
            // borro todas las camas y las cargo de nuevo
            $borrarTodo ++;
        }

        if($borrarTodo > 0){
            // borrar todos los registros
            $sqlBorrar = 'EXEC Markey_CamasBorrarTodas';
            $db = getConeccionCAB();
            $stmt = $db->prepare($sqlBorrar);
            $stmt->execute();
        }

        
        // Actualizo cada cama
        try {
            foreach($camas as $camaMarkey){ //estas son las camas obtenidas desde Markey
                $sql = 'EXEC Markey_ActualizarCamas
                            @idCama = :idCama,
                            @cama = :cama,
                            @idHabitacion = :idHabitacion,
                            @habitacion = :habitacion,
                            @piso = :piso,
                            @pisoTexto = :pisoTexto,
                            @tipoCama = :tipoCama,
                            @idEstado = :idEstado,
                            @estado = :estado,
                            @color = :color,
                            @paciCodigo = :paciCodigo,
                            @nombrePaciente = :nombrePaciente,
                            @apellidoPaciente = :apellidoPaciente,
                            @tdocCodigo = :tdocCodigo,
                            @nroDocumento = :nroDocumento,
                            @sexo = :sexo,
                            @idInternacion = :idInternacion,
                            @fechaIngresoInstitucion = :fechaIngresoInstitucion,
                            @fechaIngresoCama = :fechaIngresoCama,
                            @cobertura = :cobertura,
                            @fantasia = :fantasia,
                            @plan = :plan,
                            @nroAfiliado = :nroAfiliado,
                            @fechaAltaMedica = :fechaAltaMedica,
                            @profesionalAlta = :profesionalAlta,
                            @observaciones = :observaciones,
                            @fotoPaciente = :fotoPaciente,
                            @procedimientosNoCumplidos = :procedimientosNoCumplidos,
                            @medicacionNoProgramada = :medicacionNoProgramada,
                            @medicacionNoAplicada  = :medicacionNoAplicada,
                            @tipoAltaMedica = :tipoAltaMedica,
                            @acompanante = :acompanante,
                            @observacionesAcompanante = :observacionesAcompanante';
                $db = getConeccionCAB();
                $stmt = $db->prepare($sql);
                $stmt->bindValue('idCama', (int)$camaMarkey->id_cama);
                $stmt->bindValue('cama', $camaMarkey->cama);
                $stmt->bindValue('idHabitacion', (int)$camaMarkey->id_habitacion);
                $stmt->bindValue('habitacion', $camaMarkey->habitacion);
                $stmt->bindValue('piso', $camaMarkey->piso);
                $stmt->bindValue('pisoTexto', $camaMarkey->piso_texto);
                $stmt->bindValue('tipoCama', $camaMarkey->tipocama);
                $stmt->bindValue('idEstado', (int)$camaMarkey->id_estado);
                $stmt->bindValue('estado', $camaMarkey->estado);
                $stmt->bindValue('color', $camaMarkey->color);
                $stmt->bindValue('paciCodigo', (int)$camaMarkey->Id_Paciente);
                $stmt->bindValue('nombrePaciente', $camaMarkey->nombre_paciente);
                $stmt->bindValue('apellidoPaciente', $camaMarkey->apellido_paciente);
                $stmt->bindValue('tdocCodigo', 1); // valor fijo
                $stmt->bindValue('nroDocumento', $camaMarkey->dni);
                $stmt->bindValue('sexo', $camaMarkey->sexo);
                $stmt->bindValue('idInternacion', (int)$camaMarkey->id_internacion);

                // Validar y bindear fecha_ingreso_institucion
                $fechaIngresoInstitucion = (isset($camaMarkey->fecha_ingreso_institucion) && trim($camaMarkey->fecha_ingreso_institucion) !== '') ? trim($camaMarkey->fecha_ingreso_institucion) : null;
                if ($fechaIngresoInstitucion === null) {
                    $stmt->bindValue('fechaIngresoInstitucion', null, \PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue('fechaIngresoInstitucion', $fechaIngresoInstitucion);
                }

                // Validar y bindear fecha_ingreso_cama
                $fechaIngresoCama = (isset($camaMarkey->fecha_ingreso_cama) && trim($camaMarkey->fecha_ingreso_cama) !== '') ? trim($camaMarkey->fecha_ingreso_cama) : null;
                if ($fechaIngresoCama === null) {
                    $stmt->bindValue('fechaIngresoCama', null, \PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue('fechaIngresoCama', $fechaIngresoCama);
                }

                $stmt->bindValue('cobertura', $camaMarkey->cobertura);
                $stmt->bindValue('fantasia', $camaMarkey->fantasia);
                $stmt->bindValue('plan', $camaMarkey->plan);
                $stmt->bindValue('nroAfiliado', $camaMarkey->nro_afiliado);

                // Validar y bindear fecha_alta_medica
                $fechaAltaMedica = (isset($camaMarkey->fecha_alta_medica) && trim($camaMarkey->fecha_alta_medica) !== '') ? trim($camaMarkey->fecha_alta_medica) : null;
                
                $valorFecha = trim($camaMarkey->fecha_alta_medica ?? '', " \t\n\r\0\x0B\xA0");
                $fechaAltaMedica = ($valorFecha !== '') ? $valorFecha : null;

                if ($fechaAltaMedica === null) {
                    $stmt->bindValue('fechaAltaMedica', null, \PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue('fechaAltaMedica', $fechaAltaMedica);
                }

                $stmt->bindValue('profesionalAlta', $camaMarkey->profesional_alta);
                $stmt->bindValue('observaciones', $camaMarkey->observaciones);
                $stmt->bindValue('fotoPaciente', $camaMarkey->foto_paciente);
                $stmt->bindValue('procedimientosNoCumplidos', (int)$camaMarkey->procedimientos_no_cumplidos);
                $stmt->bindValue('medicacionNoProgramada', (int)$camaMarkey->medicacion_no_programada);
                $stmt->bindValue('medicacionNoAplicada', (int)$camaMarkey->medicacion_no_aplicada);
                $stmt->bindValue('tipoAltaMedica', $camaMarkey->tipo_alta_medica);
                $stmt->bindValue('acompanante', $camaMarkey->acompanante);
                $stmt->bindValue('observacionesAcompanante', $camaMarkey->observaciones_acompanante);
                $stmt->execute();
            }

            $datos = array('estado' => 1, 'mensaje' => 'Camas actualizadas.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => 'Error al actualizar camas: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        
        
    }

    //ACTUALIZAR CAMAS DESDE MARKEY
    public function actualizarCamasMarkeyPrueba(Request $request, Response $response, $args){
        /* Obtiene los datos de las camas de Markey desde el servicio web de Markey ObtenerCamas.
        Los datos son guardados en la tabla camasMarkey en la base de datos CAB.
        */

        $tokenAcceso    = $request->getHeader('TokenAcceso');

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                require_once '../class/Markey.php';
                $markey = new \Markey; // la barra inicial es para indicar que es del espacio global y no del namespace actual App\V2
                $camas = $markey->getCamasMarkey(); 
                $cantCamasMarkey = count($camas);

                $datos = array();

                $sql = 'EXEC Markey_VerCamas';
                $stmt = getConeccionCAB()->query($sql);
                $camasCAB = $stmt->fetchAll(\PDO::FETCH_OBJ);

                $cantCamasCab = count($camasCAB);

                //var_dump($camasCAB);

                $borrarTodo = 0;

                // verifico si la cantidad de camas es la misma
                if($cantCamasMarkey == $cantCamasCab){
                    $arrayCM    = array();
                    $arrayCAB   = array();

                    foreach($camas as $cm){
                        array_push($arrayCM,(int)$cm->id_cama);
                    }

                    foreach($camasCAB as $cab){
                        array_push($arrayCAB,(int)$cab->idCama);
                    }

                    $diferencias = 0;

                    $resultado = array_diff($arrayCM, $arrayCAB);
                    if (count($resultado) > 0){
                        $diferencias ++;
                    }

                    if($diferencias <> 0){
                        $borrarTodo ++;
                    }

                }else{
                    // borro todas las camas y las cargo de nuevo
                    $borrarTodo ++;
                }

                if($borrarTodo > 0){
                    // borrar todos los registros
                    $sqlBorrar = 'EXEC Markey_CamasBorrarTodas';
                    $db = getConeccionCAB();
                    $stmt = $db->prepare($sqlBorrar);
                    $stmt->execute();
                }

                
                // Actualizo cada cama
                try {
                    foreach($camas as $cama){
                        $c = new \stdClass();
                        $c->idCama                          = (int)$cama->id_cama;
                        $c->cama                            = $cama->cama;
                        $c->idHabitacion                    = (int)$cama->id_habitacion;
                        $c->habitacion                      = $cama->habitacion;
                        $c->piso                            = $cama->piso;
                        $c->pisoTexto                       = $cama->piso_texto;
                        $c->tipoCama                        = $cama->tipocama;
                        $c->idEstado                        = (int)$cama->id_estado;
                        $c->estado                          = $cama->estado;
                        $c->color                           = $cama->color;
                        $c->paciCodigo                      = (int)$cama->Id_Paciente;
                        $c->nombrePaciente                  = $cama->nombre_paciente;
                        $c->apellidoPaciente                = $cama->apellido_paciente;

                        // modificar esto cuando markey envíe el tdocCodigo
                        //$c->tdocCodigo                      = $cama->tdocCodigo;
                        $c->tdocCodigo                      = 1;

                        $c->nroDocumento                    = $cama->dni;
                        $c->sexo                            = $cama->sexo;
                        $c->idInternacion                   = (int)$cama->id_internacion;
                        $c->fechaIngresoInstitucion         = (isset($cama->fecha_ingreso_institucion) && trim($cama->fecha_ingreso_institucion) !== '') ? trim($cama->fecha_ingreso_institucion) : null;
                        $c->fechaIngresoCama                = (isset($cama->fecha_ingreso_cama) && trim($cama->fecha_ingreso_cama) !== '') ? trim($cama->fecha_ingreso_cama) : null;
                        $c->cobertura                       = $cama->cobertura;
                        $c->fantasia                        = $cama->fantasia;
                        $c->plan                            = $cama->plan;
                        $c->nroAfiliado                     = $cama->nro_afiliado;
                        $c->fechaAltaMedica                 = (isset($cama->fecha_alta_medica) && trim($cama->fecha_alta_medica) !== '') ? trim($cama->fecha_alta_medica) : null;
                        $c->profesionalAlta                 = $cama->profesional_alta;
                        $c->observaciones                   = $cama->observaciones;
                        $c->fotoPaciente                    = $cama->foto_paciente;
                        $c->procedimientosNoCumplidos       = (int)$cama->procedimientos_no_cumplidos;
                        $c->medicacionNoProgramada          = (int)$cama->medicacion_no_programada;
                        $c->medicacionNoAplicada            = (int)$cama->medicacion_no_aplicada;
                        $c->tipoAltaMedica                  = $cama->tipo_alta_medica;
                        $c->acompanante                     = $cama->acompanante;
                        $c->observacionesAcompanante        = $cama->observaciones_acompanante;                

                        $sql = 'EXEC Markey_ActualizarCamas
                                    @idCama = :idCama,
                                    @cama = :cama,
                                    @idHabitacion = :idHabitacion,
                                    @habitacion = :habitacion,
                                    @piso = :piso,
                                    @pisoTexto = :pisoTexto,
                                    @tipoCama = :tipoCama,
                                    @idEstado = :idEstado,
                                    @estado = :estado,
                                    @color = :color,
                                    @paciCodigo = :paciCodigo,
                                    @nombrePaciente = :nombrePaciente,
                                    @apellidoPaciente = :apellidoPaciente,
                                    @tdocCodigo = :tdocCodigo,
                                    @nroDocumento = :nroDocumento,
                                    @sexo = :sexo,
                                    @idInternacion = :idInternacion,
                                    @fechaIngresoInstitucion = :fechaIngresoInstitucion,
                                    @fechaIngresoCama = :fechaIngresoCama,
                                    @cobertura = :cobertura,
                                    @fantasia = :fantasia,
                                    @plan = :plan,
                                    @nroAfiliado = :nroAfiliado,
                                    @fechaAltaMedica = :fechaAltaMedica,
                                    @profesionalAlta = :profesionalAlta,
                                    @observaciones = :observaciones,
                                    @fotoPaciente = :fotoPaciente,
                                    @procedimientosNoCumplidos = :procedimientosNoCumplidos,
                                    @medicacionNoProgramada = :medicacionNoProgramada,
                                    @medicacionNoAplicada  = :medicacionNoAplicada,
                                    @tipoAltaMedica = :tipoAltaMedica,
                                    @acompanante = :acompanante,
                                    @observacionesAcompanante = :observacionesAcompanante';
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam('idCama',$c->idCama);
                        $stmt->bindParam('cama',$c->cama);
                        $stmt->bindParam('idHabitacion',$c->idHabitacion);
                        $stmt->bindParam('habitacion',$c->habitacion);
                        $stmt->bindParam('piso',$c->piso);
                        $stmt->bindParam('pisoTexto',$c->pisoTexto);
                        $stmt->bindParam('tipoCama',$c->tipoCama);
                        $stmt->bindParam('idEstado',$c->idEstado);
                        $stmt->bindParam('estado',$c->estado);
                        $stmt->bindParam('color',$c->color);
                        $stmt->bindParam('paciCodigo',$c->paciCodigo);
                        $stmt->bindParam('nombrePaciente',$c->nombrePaciente);
                        $stmt->bindParam('apellidoPaciente',$c->apellidoPaciente);
                        $stmt->bindParam('tdocCodigo',$c->tdocCodigo);
                        $stmt->bindParam('nroDocumento',$c->nroDocumento);
                        $stmt->bindParam('sexo',$c->sexo);

                        $stmt->bindParam('idInternacion',$c->idInternacion);

                        // Validar y bindear fecha_ingreso_institucion
                        if ($c->fechaIngresoInstitucion === null) {
                            $stmt->bindValue('fechaIngresoInstitucion', null, \PDO::PARAM_NULL);
                        } else {
                            $stmt->bindParam('fechaIngresoInstitucion',$c->fechaIngresoInstitucion);
                        }

                        // Validar y bindear fecha_ingreso_cama
                        if($c->fechaIngresoCama === null){
                            $stmt->bindValue('fechaIngresoCama', null, \PDO::PARAM_NULL);
                        } else {
                            $stmt->bindParam('fechaIngresoCama',$c->fechaIngresoCama);
                        }

                        $stmt->bindParam('cobertura',$c->cobertura);
                        $stmt->bindParam('fantasia',$c->fantasia);
                        $stmt->bindParam('plan',$c->plan);
                        $stmt->bindParam('nroAfiliado',$c->nroAfiliado);
                        
                        
                        // Validar y bindear fecha_alta_medica
                        if($c->fechaAltaMedica === null){
                            $stmt->bindValue('fechaAltaMedica', null, \PDO::PARAM_NULL);
                        } else {
                            $stmt->bindParam('fechaAltaMedica',$c->fechaAltaMedica);
                        }

                        $stmt->bindParam('profesionalAlta',$c->profesionalAlta);
                        $stmt->bindParam('observaciones',$c->observaciones);
                        $stmt->bindParam('fotoPaciente',$c->fotoPaciente);
                        $stmt->bindParam('procedimientosNoCumplidos',$c->procedimientosNoCumplidos);
                        $stmt->bindParam('medicacionNoProgramada',$c->medicacionNoProgramada);
                        $stmt->bindParam('medicacionNoAplicada',$c->medicacionNoAplicada);
                        $stmt->bindParam('tipoAltaMedica',$c->tipoAltaMedica);
                        $stmt->bindParam('acompanante',$c->acompanante);
                        $stmt->bindParam('observacionesAcompanante',$c->observacionesAcompanante);
                        $stmt->execute();
                        
                        unset($c);
                    }
                    
                    $datos = array('estado' => 1, 'mensaje' => 'Camas actualizadas.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                } catch(\PDOException $e) {
                    $datos = array(
                        'estado' => 0,
                        'mensaje' => 'Error al actualizar camas: ' . $e->getMessage(),
                        'error_code' => $e->getCode()
                    );
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

    // OBTENER CAMAS
    public function obtenerCamas(Request $request, Response $response, $args){
        $tokenAcceso        = $request->getHeader('TokenAcceso');
        $idServicio         = $request->getQueryParams()['idServicio'] ?? null;
        $tareasPendientes   = $request->getQueryParams()['tareasPendientes'] ?? 0;
        $idEstado           = $request->getQueryParams()['idEstado'] ?? 0;

        $error = 0;
        $datos = array();

        if($idServicio == ''){
            $error ++;
        }

        if ($tareasPendientes < 0 || $tareasPendientes > 1) { 
            $error++;
        }

        if ($idEstado < 0 || $idEstado > 6) { // el estado debe estar entre 0 y 6
            $error++;
        }


        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    
                    $sql = 'EXEC camasServicio_ver @idServicio = :idServicio, @tareasPendientes = :tareasPendientes, @idEstado = :idEstado';
                    try {
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam('idServicio',$idServicio);
                        $stmt->bindParam('tareasPendientes',$tareasPendientes);
                        $stmt->bindParam('idEstado',$idEstado);
                        $stmt->execute();
                        $resultados = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;                

                        $datos = array();

                        foreach($resultados as $cama){
                            $c = new \stdClass();
                            $c->idCama                         = (int)$cama->idCama;
                            $c->cama                           = $cama->cama;
                            $c->idHabitacion                   = (int)$cama->idHabitacion;
                            $c->habitacion                     = $cama->habitacion;
                            $c->piso                           = $cama->piso;
                            $c->idEstado                       = (int)$cama->idEstado;
                            $c->estado                         = $cama->estado;
                            $c->color                          = $cama->color;
                            $c->observaciones                  = $cama->observaciones; // obs del estado. Ej. Reservada para...
                            $c->paciCodigo                     = (int)$cama->paciCodigo;
                            $c->nombrePaciente                 = $cama->nombrePaciente;
                            $c->apellidoPaciente               = $cama->apellidoPaciente;
                            $c->tdocCodigo                     = (int)$cama->tdocCodigo;
                            $c->tdocDescripcion                = $cama->tdocDescripcion;
                            $c->nroDocumento                   = $cama->nroDocumento;
                            $c->sexo                           = $cama->sexo;
                            if($cama->idEstado == 2){
                                if($cama->sexo == 'F'){
                                    $c->sexoTexto = 'MUJER';
                                }else{
                                    $c->sexoTexto = 'HOMBRE';
                                }
                            }else{
                                $c->sexoTexto = '';
                            };
                            
                            $c->fechaIngresoInstitucion       = ($cama->fechaIngresoInstitucion <> '') ? date_format(date_create($cama->fechaIngresoInstitucion), 'd-m-Y H:i:s') : '';
                            $c->fechaIingresoCama             = ($cama->fechaIngresoCama <> '') ? date_format(date_create($cama->fechaIngresoCama), 'd-m-Y H:i:s') : '';
                            $c->cobertura                     = $cama->cobertura;
                            $c->fantasia                      = $cama->fantasia;
                            $c->plan                          = $cama->plan;
                            $c->nroAfiliado                   = $cama->nroAfiliado;
                            $c->idInternacion                 = (int)$cama->idInternacion;
                            
                            $c->fechaAltaMedica               = ($cama->fechaAltaMedica <> '') ? date_format(date_create($cama->fechaAltaMedica), 'd-m-Y H:i:s') : '';                            
                            $c->profesionalAlta               = $cama->profesionalAlta;
                            $c->tipoAltaMedica                = $cama->tipoAltaMedica;
                            
                            $c->fotoPaciente                  = $cama->fotoPaciente;

                            

                            $c->procedimientosNoCumplidos     = (int)$cama->procedimientosNoCumplidos;
                            $c->medicacionNoProgramada        = (int)$cama->medicacionNoProgramada;
                            $c->medicacionNoAplicada          = (int)$cama->medicacionNoAplicada;
                            
                            $c->aislamiento_contacto          = ($cama->aislamiento_contacto <> '') ? date_format(date_create($cama->aislamiento_contacto), 'd-m-Y H:i:s') : '';
                            $c->kpc                           = (int)$cama->kpc;
                            $c->aislamiento_respiratorio      = ($cama->aislamiento_respiratorio <> '') ? date_format(date_create($cama->aislamiento_respiratorio), 'd-m-Y H:i:s') : '';
                            $c->aislamiento_gota              = ($cama->aislamiento_gota <> '') ? date_format(date_create($cama->aislamiento_gota), 'd-m-Y H:i:s') : '';                        
                            $c->aislamiento_neutropenico      = ($cama->aislamiento_neutropenico <> '') ? date_format(date_create($cama->aislamiento_neutropenico), 'd-m-Y H:i:s') : '';
                            $c->aislamiento_cd                = ($cama->aislamiento_cd <> '') ? date_format(date_create($cama->aislamiento_cd), 'd-m-Y H:i:s') : '';
                            $c->aislamiento_sc                = ($cama->aislamiento_sc <> '') ? date_format(date_create($cama->aislamiento_sc), 'd-m-Y H:i:s') : '';
                            $c->camaEnAislamiento             = (int)$cama->camaEnAislamiento;

                            $c->acompanante                   = (int)$cama->acompanante;
                            $c->observacionesAcompanante      = $cama->observacionesAcompanante;
                            $c->orden                         = (int)$cama->orden;
                            $c->cambioCamaPendiente           = (int)$cama->cambioCamaPendiente;
                            $c->alertas                       = (int)$cama->alertas;
                            $c->tareasPendientes              = (int)$cama->tareasPendientes;
                            
                            $c->altaProbableFecha             = ($cama->altaProbableFecha <> '') ? date_format(date_create($cama->altaProbableFecha), 'd-m-Y H:i:s') : '';
                            $c->altaProbableTipo              = ($cama->altaProbableTipo <> '') ? $cama->altaProbableTipo : '';
                            $c->altaProbableDniUsuario        = (int)$cama->altaProbableDniUsuario;
                            $c->altaProbableNombreUsuario     = $cama->nombreUsuarioAltaProbable;
                            
                            if($cama->idEstado == 6){
                                $c->idReserva                 = (int)$cama->idReserva;
                                $c->reservaFecha              = ($cama->fechaReserva <> '') ? date_format(date_create($cama->fechaReserva), 'd-m-Y H:i:s') : '';
                                $c->reservaMotivo             = $cama->reservaMotivo;
                                $c->reservadaPorDni           = (int)$cama->reservadaPorDni;
                                $c->reservadaPorNombre        = $cama->reservadaPorNombre;
                                $c->reservaFechaCancelada     = ($cama->reservaFechaCancelada <> '') ? date_format(date_create($cama->reservaFechaCancelada), 'd-m-Y H:i:s') : '';
                                $c->reservaCanceladaPorDni    = (int)$cama->reservaCanceladaPorDni;
                                $c->reservaCanceladaPorNombre = $cama->reservaCanceladaPorNombre;
                                $c->reservaPacienteDni        = $cama->reservaPacienteDni;
                                $c->reservaPacienteNombre     = $cama->reservaNombrePaciente;
                                $c->idMotivoFinReserva        = (int)$cama->reservaidMotivoFinReserva;
                                $c->reservaIdSolicitudCambio  = (int)$cama->reservaIdSolicitudCambio;
                            }else{
                                $c->reservaMotivo             = '';
                                $c->reservaFecha              = '';
                                $c->reservadaPorDni           = 0;
                                $c->reservadaPorNombre        = '';
                                $c->idReserva                 = 0;
                                $c->reservaFechaCancelada     = '';
                                $c->reservaCanceladaPorDni    = 0;
                                $c->reservaCanceladaPorNombre = '';
                                $c->reservaPacienteDni        = '';
                                $c->reservaPacienteNombre     = '';
                                $c->reservaidMotivoFinReserva = 0;
                                $c->reservaIdSolicitudCambio  = 0;
                            }
                            
                            array_push($datos,$c);
                            unset($c);
                        }                  
                        
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

                    }catch(\PDOException $e) {
                        $datos =  '{"error": '.$e->getMessage() .'}';
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(503);
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

    // VER UNA CAMA
    function verUnaCama($request, $response) {
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $parametros     = $request->getQueryParams();
        $idCama        = $parametros['idCama'];
        $idServicio    = $parametros['idServicio'];
        
        $error = 0;

        // verifico que los datos obtenidos sean válidos.
        if($idCama == ''){
            $error ++;
        }
        if($idServicio == ''){
            $error ++;
        }

        
        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){
                // acceso permitido
                if($error == 0){
                    // obtengo la cama solicitada
                    $sql = 'EXEC camasVerUna_v2 @idCama = :idCama, @idServicio = :idServicio';
                    $db = getConeccionCAB();
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam('idCama',$idCama);
                    $stmt->bindParam('idServicio',$idServicio);
                    $stmt->execute();
                    $resultados = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;         
                    $datos = array();
                    
                    foreach($resultados as $cama){
                        $c = new \stdClass();
                        $c->idCama                          = (int)$cama->idCama;
                        $c->cama                            = $cama->cama;
                        $c->idHabitacion                    = (int)$cama->idHabitacion;
                        $c->habitacion                      = $cama->habitacion;
                        $c->piso                            = $cama->piso;
                        $c->pisoTexto                       = $cama->pisoTexto;
                        $c->tipoCama                        = $cama->tipoCama;
                        $c->idEstado                        = (int)$cama->idEstado;
                        $c->estado                          = $cama->estado;
                        $c->color                           = $cama->color;
                        $c->paciCodigo                      = (int)$cama->paciCodigo;
                        $c->nombrePaciente                  = $cama->nombrePaciente;
                        $c->apellidoPaciente                = $cama->apellidoPaciente;
                        $c->tdocCodigo                      = (int)$cama->tdocCodigo;
                        $c->tdocDescripcion                 = $cama->tdocDescripcion;
                        $c->nroDocumento                    = $cama->nroDocumento;
                        $c->sexo                            = $cama->sexo;
                        $c->idInternacion                   = (int)$cama->idInternacion;
                        $c->fechaIngresoInstitucion         = ($cama->fechaIngresoInstitucion <> '') ? date_format(date_create($cama->fechaIngresoInstitucion), 'd-m-Y H:i:s') : '';
                        $c->fechaIngresoCama                = ($cama->fechaIngresoCama <> '') ? date_format(date_create($cama->fechaIngresoCama), 'd-m-Y H:i:s') : '';
                        $c->cobertura                       = $cama->cobertura;
                        $c->fantasia                        = $cama->fantasia;
                        $c->plan                            = $cama->plan;
                        $c->nroAfiliado                     = $cama->nroAfiliado;
                        $c->fechaAltaMedica                 = ($cama->fechaAltaMedica <> '') ? date_format(date_create($cama->fechaAltaMedica), 'd-m-Y H:i:s') : '';
                        $c->profesionalAlta                 = $cama->profesionalAlta;
                        $c->tipoAltaMedica                  = $cama->tipoAltaMedica;
                        $c->idTipoAltaMedica                = (int)$cama->idTipoAltaMedica;
                        $c->camaEnAislamiento               = (int)$cama->camaEnAislamiento;
                        $c->observaciones                   = $cama->observaciones;
                        $c->fotoPaciente                    = $cama->fotoPaciente;

                        
                        $c->procedimientosNoCumplidos       = (int)$cama->procedimientosNoCumplidos;
                        $c->medicacionNoProgramada          = (int)$cama->medicacionNoProgramada;
                        $c->medicacionNoAplicada            = (int)$cama->medicacionNoAplicada;

                        
                        $c->acompanante                     = (int)$cama->acompanante;
                        $c->observacionesAcompanante        = $cama->observacionesAcompanante;
                        
                        $c->cambioCamaPendiente             = (int)$cama->cambioCamaPendiente;
                        $c->cambioCamaIdSolicitud           = (int)$cama->cambioCamaIdSolicitud;
                        $c->tareasPendientes                = (int)$cama->tareasPendientes;
                        $c->cantidadAlertas                 = (int)$cama->cantidadAlertas;
                        $c->cambioCamaAutorizacion          = (int)$cama->cambioCamaAutorizacion;
                        $c->cambioCamaAutorizadoPorNombre   = $cama->cambioCamaAutorizadoPorNombre;
                        $c->cambioCamaAutorizadoFecha       = $cama->cambioCamaAutorizadoFecha;
                        $c->cambioCamaIdCamaDestino         = $cama->cambioCamaIdCamaDestino;
                        $c->cambioCamaCamaDestino           = $cama->cambioCamaCamaDestino;
                        
                        
                        $c->altaProbableFecha               = ($cama->altaProbableFecha <> '') ? date_format(date_create($cama->altaProbableFecha), 'd-m-Y H:i:s') : '';
                        $c->altaProbableIdTipoAlta          = (int)($cama->altaProbableIdTipoAlta <> '') ? $cama->altaProbableIdTipoAlta : null;
                        $c->altaProbableTipoAltaProbable    = ($cama->altaProbableTipoAltaProbable <> '') ? $cama->altaProbableTipoAltaProbable : '';
                        $c->altaProbableDniUsuario          = (int)($cama->altaProbableDniUsuario <> '') ? $cama->altaProbableDniUsuario : null;
                        $c->altaProbableNombreUsuario       = ($cama->altaProbableNombreUsuario <> '') ? $cama->altaProbableNombreUsuario : '';
                        $c->soloAltaMedica                  = (int)$cama->soloAltaMedica;

                        // json con los aislamientos del paciente.
                        if (!empty($cama->aislamientos)) {
                            $c->aislamientos = json_decode($cama->aislamientos, true);
                        }else{
                            $c->aislamientos = array();
                        };

                        $c->tareasBloqueanHabitacion = (int)$cama->tareasBloqueanHabitacion;
                        $c->tareasBloqueanCama = (int)$cama->tareasBloqueanCama;

                        $c->limpia = (int)$cama->limpia;

                        $c->requiereAutorizacionEnfermeria = (int)$cama->requiereAutorizacionEnfermeria;
                        $c->autEnfermeriaEstado = $cama->autEnfermeriaEstado;
                        $c->autEnfermeriaFecha = ($cama->autEnfermeriaFecha <> '') ? date_format(date_create($cama->autEnfermeriaFecha), 'd-m-Y H:i:s') : '';
                        // if(is_null($solicitud->autEnfermeriaFecha)){
                        //         $s->autEnfermeriaFecha = null;
                        //     }else{
                        //         $s->autEnfermeriaFecha = date_format(date_create($solicitud->autEnfermeriaFecha), 'd-m-Y H:i:s'); 
                        //     }
                        $c->autEnfermeriaPorDni = $cama->autEnfermeriaPorDni;
                        $c->autEnfermeriaPorNombre = $cama->autEnfermeriaPorNombre;

                        if($cama->idEstado == 6){ // si está reservada
                            $c->idReserva =$cama->idReserva;
                            $c->reservaFecha = ($cama->fechaReserva <> '') ? date_format(date_create($cama->fechaReserva), 'd-m-Y H:i:s') : '';
                            $c->reservaMotivo =$cama->reservaMotivo;
                            $c->reservadaPorDni =$cama->reservadaPorDni;
                            $c->reservadaPorNombre =$cama->reservadaPorNombre;
                            $c->reservaFechaCancelada = ($cama->reservaFechaCancelada <> '') ? date_format(date_create($cama->reservaFechaCancelada), 'd-m-Y H:i:s') : '';
                            $c->reservaCanceladaPorDni =$cama->reservaCanceladaPorDni;
                            $c->reservaCanceladaPorNombre =$cama->reservaCanceladaPorNombre;
                            $c->reservaPacienteDni =$cama->reservaPacienteDni;
                            $c->reservaNombrePaciente =$cama->reservaNombrePaciente;
                            $c->reservaidMotivoFinReserva =$cama->reservaidMotivoFinReserva;
                            $c->reservaIdSolicitudCambio =$cama->reservaIdSolicitudCambio;
                        }else{
                            $c->idReserva = 0;
                            $c->reservaFecha = '';
                            $c->reservaMotivo = '';
                            $c->reservadaPorDni = '';
                            $c->reservadaPorNombre = '';
                            $c->reservaFechaCancelada = '';
                            $c->reservaCanceladaPorDni = '';
                            $c->reservaCanceladaPorNombre = '';
                            $c->reservaPacienteDni = '';
                            $c->reservaNombrePaciente = '';
                            $c->reservaidMotivoFinReserva = 0;
                            $c->reservaIdSolicitudCambio = 0;
                        }
                    

                        
                        array_push($datos,$c);
                        unset($c);
                    }

                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);                    
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

    // CAMAS ACTUALIZAR ESTADOS
    private function camasActualizarEstados($idHabitacion, $dni, $nombreUsuario, $idServicio){
        // Esta función actualiza los estados de las camas de esta habitación y devuelve 1 si el proceso fue exitoso o 0 si hubo algún error.

        // 1- Obtengo de Markey los datos de las camas de esta habitación.
        require_once '../class/Markey.php';
        $datosCamas = new \Markey;
        $camas = $datosCamas->getCamasHabitacion($idHabitacion);
        if(is_null($camas)){
            //debug_log2('Error en funcion camasActualizarEstados: la función getCamasHabitacion devolvió null. idHabitacion: '.$idHabitacion);
            return 0; // no existe la habitación o no hay camas en la habitación indicada.
        }else{
            // Recorro cada cama de esta habitación y actualizo el estado de cada cama.

            foreach($camas as $cama){
                // guardo el estado actual de la cama
                $estadoActual = $cama->id_estado;
                //debug_log2('Cama: ' . $cama->id_cama . ' - Estado actual: ' . $estadoActual);
                
                // 3- Verifico que la cama esté disponible, en reparación o en limpieza. Si la cama tiene otro estado no lo cambio
                if(($cama->id_estado == 1) or ($cama->id_estado == 3) or ($cama->id_estado == 4)){
                    

                    // verifico si la cama está
                    //  - en aislamiento
                    //  - si hay tareas de reparación que bloquean la habitación
                    //  - si hay tareas de reparación que bloquean la cama.
                    //  - si está limpia o no.                            
                    
                    // busco el dato de aislamiento en la tabla local
                    $sql = 'EXEC camasVerUna @idCama = '.$cama->id_cama.', @idServicio = '.$idServicio;
                    $stmt = getConeccionCAB()->query($sql);
                    $datCam = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    foreach ($datCam as $cam) {
                        $camaEnAislamiento          = $cam->camaEnAislamiento;
                        $tareasBloqueanHabitacion   = $cam->tareasBloqueanHabitacion;
                        $tareasBloqueanCama         = $cam->tareasBloqueanCama;
                        $limpia                     = $cam->limpia;
                    }
                    
                    //debug_log2('Cama: ' . $cama->id_cama . ' - CEA: ' . $camaEnAislamiento.' - TBH: ' . $tareasBloqueanHabitacion . ' - TBC: ' . $tareasBloqueanCama . ' - Limpia: ' . $limpia);

                    if($camaEnAislamiento == 1){  // si la cama está en aislamiento
                        
                        $nuevoEstado = 4; // Limpieza
                        if($estadoActual <> $nuevoEstado){
                            if($cama->tipocama == 'F'){ // es una cama física
                                // CREO LA TAREA DE LIMPIEZA, ENVIO UNA ALERTA y registro el evento en la bitácora de la cama.
                                // si en la cama ya existe una tarea de limpieza sin finalizar, no hace nada.
                                crearTareaLimpieza($cama->id_cama, $dni, $nombreUsuario, $idServicio); // función definida en src/Common/helpers.php
                            }
                        }
                    }else{  // la cama no está en aislamiento
                        // verifico si en esta cama hay tareas de reparación que bloquean la habitación.
                        if($tareasBloqueanHabitacion == 1){
                            $nuevoEstado = 3; // Reparación.
                        }else{
                            // verifico si hay tareas de reparación que bloqueen esa cama.
                            if($tareasBloqueanCama == 1){
                                $nuevoEstado = 3; //Reparación.
                            }else{
                                if($limpia == 1){
                                    $nuevoEstado = 1; // Disponible.
                                    
                                    if($estadoActual <> $nuevoEstado){
                                        // crear alerta de cama disponible (se muestra en el tablero de camas.)
                                        // la alerta se creará solo si hay algún servicio que atienda ese tipo de alertas.
                                        $sql = 'declare @mensaje varchar(255)
                                                EXEC alertaNueva '.$cama->id_cama.', 13, @mensaje = @mensaje OUTPUT';
                                        $stmt = getConeccionCAB()->query($sql);                                                                                                        
                                    }
                                }else{
                                    $nuevoEstado = 4; // Limpieza
                                    if($estadoActual <> $nuevoEstado){
                                        if($cama->tipocama == 'F'){
                                            // Creo la tarea de limpieza y envio la alerta
                                            crearTareaLimpieza($cama->id_cama, $dni, $nombreUsuario, $idServicio); // función definida en src/Common/helpers.php
                                            // $datos = array('estado' => 200, 'mensaje' => 'crearTareaLimpieza. TipoCama: '. $cama->tipocama.'id_cama: '.$cama->id_cama.' dni: '.$dni.' nombreUsuario: '.$nombreUsuario.' idServicio: '.$idServicio);
                                            // $response->getBody()->write(json_encode($datos));
                                            // return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                                            
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    //debug_log2('Cama: ' . $cama->id_cama . ' - Nuevo Estado: ' . $nuevoEstado);

                    // ACTUALIZO EL ESTADO DE LA CAMA EN MARKEY SOLO SI EL NUEVO ESTADO ES DISTINTO AL ESTADO ACTUAL.
                    if($estadoActual <> $nuevoEstado){                                    
                        // si la cama es virtual solo puedo asignarle el estado disponible
                        if($cama->tipocama == 'V'){
                            //debug_log2('Cama: ' . $cama->id_cama . ' - Cama Virtual  Poner disponible en Markey');
                            // llamo al servicio de Markey para cambiar el estado de la cama a disponible
                            if($datosCamas->cambiarEstado($cama->id_cama, 1) == 1){
                                //debug_log2('Cama: ' . $cama->id_cama . ' - limpiar alertas históricas');

                                // limpio las alertas históricas de la cama
                                limpiarAlertasHistoriasCama($cama->id_cama); // función definida en src/Common/helpers.php
                            }
                        }else{
                            //debug_log2('Cama: ' . $cama->id_cama . ' - Cama Física - cambiar estado en Markey a: ' . $nuevoEstado);
                            // llamo al servicio de Markey para cambiar el estado de la cama a $nuevo_estado
                            if($datosCamas->cambiarEstado($cama->id_cama, $nuevoEstado) == 1){

                                // si la cama pasa a disponible, limpio el historial de alertas.
                                if($nuevoEstado == 1){
                                    debug_log2('Cama: ' . $cama->id_cama . ' - limpiar alertas históricas y crear alerta de cama disponible');
                                    // limpio el historial de alertas
                                    limpiarAlertasHistoriasCama($cama->id_cama); // función definida en src/Common/helpers.php
                                    
                                    // crear alerta de cama disponible para admisión
                                    crearAlertaCamaDisponible($cama->id_cama); // función definida en src/Common/helpers.php
                                }
                            }
                        }

                        //debug_log2('Cama: ' . $cama->id_cama . ' - registrar cambio de estado en bitácora. Evento: 10, DNI: '.$dni.', Usuario: '.$nombreUsuario);
                        // registro el cambio de estado en la bitácora de la cama.
                        bitacoraRegistrarCambioEstadoCama($cama->id_cama, 10, $dni, $nombreUsuario);
                    }
                }
            }

            // actualizo las camas en las tablas locales camasMarkey y camas para que queden sincronizadas con los datos de Markey.
            // para esto vuelvo a llamar al servicio de Markey para obtener las camas de esta habitación con los datos actualizados.
            
            //debug_log2('Sincronizar tablas locales: idHabitacion = ' . $idHabitacion );
            require_once '../class/Markey.php';
            $datosCamas = new \Markey;
            $camas = $datosCamas->getCamasHabitacion($idHabitacion);
            foreach($camas as $cama){
                // actualizo las tablas locales con los datos de Markey.
                $sql = 'EXEC camaActualizarDatos 
                            @idCama = :idCama,
                            @cama = :cama,
                            @idHabitacion = :idHabitacion,
                            @habitacion = :habitacion,
                            @piso = :piso,
                            @pisoTexto = :pisoTexto,
                            @tipoCama = :tipoCama,
                            @idEstado = :idEstado,
                            @estado = :estado,
                            @paciCodigo = :paciCodigo,
                            @nombrePaciente = :nombrePaciente,
                            @apellidoPaciente = :apellidoPaciente,
                            @tdocCodigo = :tdocCodigo,
                            @nroDocumento = :nroDocumento,
                            @sexo = :sexo,
                            @idInternacion = :idInternacion,
                            @fechaIngresoInstitucion = :fechaIngresoInstitucion,
                            @fechaIngresoCama = :fechaIngresoCama,
                            @cobertura = :cobertura,
                            @fantasia = :fantasia,
                            @plan = :plan,
                            @nroAfiliado = :nroAfiliado,
                            @fechaAltaMedica = :fechaAltaMedica,
                            @profesionalAlta = :profesionalAlta,
                            @camaEnAislamiento = :camaEnAislamiento,
                            @observaciones = :observaciones,
                            @fotoPaciente = :fotoPaciente,
                            @procedimientosNoCumplidos = :procedimientosNoCumplidos,
                            @medicacionNoProgramada = :medicacionNoProgramada,
                            @medicacionNoAplicada = :medicacionNoAplicada,
                            @tipoAltaMedica = :tipoAltaMedica,
                            @acompanante = :acompanante,
                            @observacionesAcompanante = :observacionesAcompanante';
                
                
                $fechaAltaMedica = !empty($cama->fecha_alta_medica) 
                    ? date('Y-m-d H:i:s', strtotime($cama->fecha_alta_medica)) 
                    : null;
                $db = getConeccionCAB();
                $stmt = $db->prepare($sql);
                $stmt->bindParam('idCama',$cama->id_cama);
                $stmt->bindParam('cama',$cama->cama);
                $stmt->bindParam('idHabitacion',$cama->id_habitacion);
                $stmt->bindParam('habitacion',$cama->habitacion);
                $stmt->bindParam('piso',$cama->piso);
                $stmt->bindParam('pisoTexto',$cama->piso_texto);
                $stmt->bindParam('tipoCama',$cama->tipo_cama);
                $stmt->bindParam('idEstado',$cama->id_estado);
                $stmt->bindParam('estado',$cama->estado);
                $stmt->bindParam('paciCodigo',$cama->paci_codigo);
                $stmt->bindParam('nombrePaciente',$cama->nombre_paciente);
                $stmt->bindParam('apellidoPaciente',$cama->apellido_paciente);
                $stmt->bindParam('tdocCodigo',$cama->tdoc_codigo);
                $stmt->bindParam('nroDocumento',$cama->nro_documento);
                $stmt->bindParam('sexo',$cama->sexo);
                $stmt->bindParam('idInternacion',$cama->id_internacion);
                // $stmt->bindParam('fechaIngresoInstitucion',$cama->fecha_ingreso_institucion, \PDO::PARAM_STR);
                // $stmt->bindParam('fechaIngresoCama',$cama->fecha_ingreso_cama, \PDO::PARAM_STR);
                //$stmt->bindParam('fechaIngresoInstitucion',$fechaIngresoInstitucion, \PDO::PARAM_STR);
                //$stmt->bindParam('fechaIngresoCama',$fechaIngresoCama, \PDO::PARAM_STR);

                // Validar y bindear fecha_ingreso_institucion
                $fechaIngresoInstitucion = (isset($cama->fecha_ingreso_institucion) && trim($cama->fecha_ingreso_institucion) !== '') ? trim($cama->fecha_ingreso_institucion) : null;
                if ($fechaIngresoInstitucion === null) {
                    $stmt->bindValue('fechaIngresoInstitucion', null, \PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue('fechaIngresoInstitucion', $fechaIngresoInstitucion);
                }

                // Validar y bindear fecha_ingreso_cama
                $fechaIngresoCama = (isset($cama->fecha_ingreso_cama) && trim($cama->fecha_ingreso_cama) !== '') ? trim($cama->fecha_ingreso_cama) : null;
                if ($fechaIngresoCama === null) {
                    $stmt->bindValue('fechaIngresoCama', null, \PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue('fechaIngresoCama', $fechaIngresoCama);
                }
                $stmt->bindParam('cobertura',$cama->cobertura);
                $stmt->bindParam('fantasia',$cama->fantasia);
                $stmt->bindParam('plan',$cama->plan);
                $stmt->bindParam('nroAfiliado',$cama->nro_afiliado);
                //$stmt->bindParam('fechaAltaMedica',$fechaAltaMedica, \PDO::PARAM_STR);
                // Validar y bindear fecha_alta_medica
                $fechaAltaMedica = (isset($cama->fecha_alta_medica) && trim($cama->fecha_alta_medica) !== '') ? trim($cama->fecha_alta_medica) : null;
                if ($fechaAltaMedica === null) {
                    $stmt->bindValue('fechaAltaMedica', null, \PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue('fechaAltaMedica', $fechaAltaMedica);
                }

                $stmt->bindParam('profesionalAlta',$cama->profesional_alta);
                $stmt->bindParam('camaEnAislamiento',$cama->cama_en_aislamiento);
                $stmt->bindParam('observaciones',$cama->observaciones);
                $stmt->bindParam('fotoPaciente',$cama->foto_paciente);
                $stmt->bindParam('procedimientosNoCumplidos',$cama->procedimientos_no_cumplidos);
                $stmt->bindParam('medicacionNoProgramada',$cama->medicacion_no_programada);
                $stmt->bindParam('medicacionNoAplicada',$cama->medicacion_no_aplicada);
                $stmt->bindParam('tipoAltaMedica',$cama->tipo_alta_medica);
                $stmt->bindParam('acompanante',$cama->acompanante);
                $stmt->bindParam('observacionesAcompanante',$cama->observaciones_acompanante);
                $stmt->execute();
            }
        }

        //debug_log2('Sincronizar finalizada tablas locales: idHabitacion = ' . $idHabitacion );
        
        return 1; // proceso finalizado exitosamente.  
            
    }


//______________ SERVICIOS________________________________________________________________________

    // SERVICIOS - VER TODOS
    public function serviciosVerTodos(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');        

        $datos = array();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                $sql = 'EXEC servicios_verTodos';
                try {
                    $db = getConeccionCAB(); 
                    $stmt = $db->prepare($sql);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;

                    foreach($resultado as $servicio){
                        $s = new \stdClass();
                        $s->idServicio = (int)$servicio->idServicio;
                        $s->nombreServicio = $servicio->nombreServicio;
                        $s->idTipoInternacion = (int)$servicio->idTipoInternacion;
                        $s->cambioCamaAreaCerrada = (int)$servicio->cambioCamaAreaCerrada;
                        $s->gestionaCamas = (int)$servicio->gestionaCamas;
                        
                        array_push($datos,$s);
                        unset($s);
                    }

                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                } catch(\PDOException $e) {
                    $datos = array(
                        'estado' => 0,
                        'mensaje' => $e->getMessage()
                    );
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
    
    // SERVICIOS - VER UNO
    public function serviciosVerUno(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idServicio    = $request->getQueryParams()['idServicio'] ?? null;

        $error = 0;
        $datos = array();

        if($idServicio == ''){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){                    
                    $sql = 'EXEC servicios_verUno @idServicio = :idServicio';
                    try {
                        $db = getConeccionCAB(); 
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idServicio", $idServicio);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        if(count($resultado) == 1){ 
                            // encontré el servicio, ahora busco los permisos que tiene para agregarlos a los datos que devolveré
                            $sqlPermisos = 'EXEC permisosModulosTableroServicio @idServicio = :idServicio';
                            $db = getConeccionCAB(); 
                            $stmtPermisos = $db->prepare($sqlPermisos);
                            $stmtPermisos->bindParam("idServicio", $idServicio);
                            $stmtPermisos->execute();
                            $resultadoPermisos = $stmtPermisos->fetchAll(\PDO::FETCH_OBJ);
                            $db = null;

                            $permisos = array();

                            foreach($resultadoPermisos as $permiso){
                                array_push($permisos, array(
                                    'idPermiso' => (int)$permiso->idPermiso,
                                    'idModulo' => (int)$permiso->idModulo,
                                    'nombreModulo' => $permiso->nombre,
                                    'descripcionModulo' => $permiso->descripcion,
                                    'controlTotal' => (int)$permiso->controlTotal
                                ));
                            }
                            
                        }

                        foreach($resultado as $servicio){
                            $datos = array(
                                'idServicio' => (int)$servicio->idServicio,
                                'nombreServicio' => $servicio->nombreServicio,
                                'idTipoInternacion' => (int)$servicio->idTipoInternacion,
                                'tipoInternacion' => $servicio->tipoInternacion,
                                'cambioCamaAreaCerrada' => (int)$servicio->cambioCamaAreaCerrada,
                                'gestionaCamas' => (int)$servicio->gestionaCamas,
                                'permisos' => $permisos ?? array() // si se encontraron permisos los agrego, sino devuelvo un array vacío
                            );
                        }

                        if(count($datos) == 0){
                            $datos = array('estado' => 0, 'mensaje' => 'No existe el servicio solicitado.');
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                        }else{
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }                        
                    } catch(\PDOException $e) {
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

    // SERVICIOS - VER TODOS
    public function serviciosUsuario(Request $request, Response $response, $args){
        $tokenAcceso = $request->getHeader('TokenAcceso');    
        $idUsuario   = $request->getQueryParams()['idUsuario'] ?? null;    

        $error = 0;
        $datos = array();

        if($idUsuario == ''){ $error ++; }

        if(!isset($tokenAcceso[0])){
            //acceso denegado. No envió el token de acceso
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
            
        if(verificarToken($tokenAcceso[0]) === false){
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if($error > 0){
            $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos o son insuficientes.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // acceso permitido
        $sql = 'EXEC serviciosUsuario @idUsuario = :idUsuario';
        try {
            $db = getConeccionCAB(); 
            $stmt = $db->prepare($sql);
            $stmt->bindParam("idUsuario", $idUsuario);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $servicio){
                $s = new \stdClass();
                $s->idServicio = (int)$servicio->idServicio;
                $s->nombreServicio = $servicio->nombreServicio;
                $s->idTipoInternacion = (int)$servicio->idTipoInternacion;
                $s->cambioCamaAreaCerrada = (int)$servicio->cambioCamaAreaCerrada;
                $s->gestionaCamas = (int)$servicio->gestionaCamas;
                
                array_push($datos,$s);
                unset($s);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        }

    // VER PERMISOS SOBRE UN MÓDULO DETERMINADO
    public function permisoModuloTablero_ver(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idModulo      = $request->getQueryParams()['idModulo'] ?? null;
        $idServicio    = $request->getQueryParams()['idServicio'] ?? null;

        $error = 0;
        $datos = array();

        if($idModulo == ''){
            $error ++;
        }

        if($idServicio == ''){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    $sql = 'EXEC permisoModuloTablero_ver @idModulo = :idModulo, @idServicio = :idServicio';
                    try {
                        $db = getConeccionCAB(); 
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idModulo", $idModulo);
                        $stmt->bindParam("idServicio", $idServicio);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        foreach($resultado as $permiso){
                            $datos = array(
                                'idPermiso' => (int)$permiso->idPermiso,
                                'idServicio' => (int)$permiso->idServicio,
                                'nombreServicio' => $permiso->nombreServicio,
                                'idModulo' => (int)$permiso->idModulo,
                                'nombreModulo' => $permiso->nombreModulo,
                                'descripcionModulo' => $permiso->descripcionModulo,
                                'controlTotal' => (int)$permiso->controlTotal
                            );
                        }
                        
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

                    } catch(\PDOException $e) {
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

    
//______________ PERMISOS ________________________________________________________________________

    // VER PERMISOS SOBRE UN MÓDULO DETERMINADO
    public function permisosModulosTableroServicio(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idServicio    = $request->getQueryParams()['idServicio'] ?? null;

        $error = 0;
        $datos = array();

        if($idServicio == ''){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    $sql = 'EXEC permisosModulosTableroServicio @idServicio = :idServicio';
                    try {
                        $db = getConeccionCAB(); 
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idServicio", $idServicio);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        foreach($resultado as $permiso){
                            $p = new \stdClass();
                            $p->idPermiso = (int)$permiso->idPermiso;
                            $p->idServicio = (int)$permiso->idServicio;
                            $p->nombreServicio = $permiso->nombreServicio;
                            $p->idModulo = (int)$permiso->idModulo;
                            $p->nombreModulo = $permiso->nombre;
                            $p->descripcionModulo = $permiso->descripcion;
                            $p->controlTotal = (int)$permiso->controlTotal;

                            array_push($datos,$p);
                            unset($p);
                        }
                        
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

                    } catch(\PDOException $e) {
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

    
    
//______________ ALERTAS _________________________________________________________________________
    
    // VER ALERTAS
    public function alertasVer(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $parametros     = $request->getQueryParams();
        
        $idCama         = $parametros['idCama'];
        $idServicio     = $parametros['idServicio'];
        $filtro         = $parametros['filtro']; // todas, leidas, pendientes

        $error = 0;
        $datos = array();

        // verifico que los datos obtenidos sean válidos.
        if($idCama == ''){ $error ++; }
        if($idServicio == ''){ $error ++; }

        // verifico que haya recibido el tokenAcceso
        if(!isset($tokenAcceso[0])){
            $datos = array('estado' => 0,'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verifico si el token enviado es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // obtengo las alertas de esta cama para este servicio
        $sql = 'EXEC alertasServicio_ver
                    @idCama     = :idCama,
                    @idServicio = :idServicio,
                    @filtro     = :filtro';
        try {
            $db = getConeccionCAB(); 
            $stmt = $db->prepare($sql);
            $stmt->bindParam("idCama", $idCama);
            $stmt->bindParam("idServicio", $idServicio);
            $stmt->bindParam("filtro", $filtro);
            
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $al){
                $a = new \stdClass();
                
                $a->idAlerta        = $al->idAlerta;
                $a->idTipoAlerta    = $al->idTipoAlerta;
                $a->tipoAlerta      = $al->tipoAlerta;
                $a->idCama          = $al->idCama;
                $a->fecha           = $al->fecha;
                //$a->leida           = $al->leida;

                array_push($datos,$a);
                unset($t);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        
    }

    // ALERTAS NUEVA
    public function nuevaAlerta(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idCama         = $request->getQueryParams()['idCama'] ?? null;
        $idTipoAlerta   = $request->getQueryParams()['idTipoAlerta'] ?? null;
        $paciCodigo     = $request->getQueryParams()['paciCodigo'] ?? null;
        $idInternacion  = $request->getQueryParams()['idInternacion'] ?? null;

        $error = 0;
        $datos = array();
        $r = new \stdClass();

        // verifico que los datos obtenidos sean válidos.
        if(!isset($idCama) or (!isset($idTipoAlerta))){
            $error ++;
        }else{
            // verifico que si el tipo de alerta es:
            // 1- Ingreso de paciente
            // 2- Alta medica
            // reciba los parámetro paciCodigo e idInternacion
            if(($idTipoAlerta == 1) or ($idTipoAlerta == 2)){
                if((!isset($paciCodigo)) or (!isset($idInternacion))){
                    $error ++;
                }
            }
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    if((isset($paciCodigo)) and (($idTipoAlerta == 1) or ($idTipoAlerta == 2))){
                        $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC	@return_value = alertaNueva
                                    @idCama = '.$idCama.',
                                    @idTipoAlerta = '.$idTipoAlerta.',
                                    @paciCodigo = '.$paciCodigo.',
                                    @idInternacion = '.$idInternacion.',
                                    @mensaje = @mensaje OUTPUT
                            
                            SELECT	@return_value as estado, @mensaje as mensaje';
                    }else{
                        $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC	@return_value = alertaNueva
                                    @idCama = '.$idCama.',
                                    @idTipoAlerta = '.$idTipoAlerta.',
                                    @mensaje = @mensaje OUTPUT
                            
                            SELECT	@return_value as estado, @mensaje as mensaje';
                    }
                    
                    try {
                        $stmt = getConeccionCAB()->query($sql);
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;
                        $a = new \stdClass();
                        $a = $resultado[0];

                        $u = new \stdClass();
                        $u->estado = (int)$a->estado;
                        $u->mensaje = $a->mensaje;
                        $datos = array('estado' => $u->estado, 'mensaje' => $u->mensaje);
                        if($u->estado == 1){
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }else{
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                        }
                        
                        unset($u);
                        unset($a);
                    } catch(\PDOException $e) {
                        $u = new \stdClass();
                        $est = 0;
                        $u->estado = (int)$est;
                        $u->mensaje = $e->getMessage();
                        $datos = array('estado' => $u->estado, 'mensaje' => $u->mensaje);
                        unset($u);
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

    // APAGAR ALERTAS DE CAMAS DISPONIBLES
    public function apagarAlertasCamasDisponibles(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');

        $datos = array();
        $r = new \stdClass();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                $sql = 'EXEC alertasApagarCamasDisponibles';          
                try {
                    $stmt = getConeccionCAB()->query($sql);
                    //$resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;
                    $datos = array('estado' => 1, 'mensaje' => 'Alertas apagadas correctamente.');
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);                                        
                } catch(\PDOException $e) {
                    $u = new \stdClass();
                    $est = 0;
                    $u->estado = (int)$est;
                    $u->mensaje = $e->getMessage();
                    $datos = array('estado' => $u->estado, 'mensaje' => $u->mensaje);
                    unset($u);
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
    
    // APAGAR ALERTAS
    public function apagarAlertas(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idCama         = $request->getQueryParams()['idCama'] ?? null;
        $leidaPorDni    = $request->getQueryParams()['leidaPorDni'] ?? null;
        $leidaPorNombre = $request->getQueryParams()['leidaPorNombre'] ?? null;

        $error = 0;
        $datos = array();
        $r = new \stdClass();

        // verifico que los datos obtenidos sean válidos.
        if(!isset($idCama) or (!isset($leidaPorDni)) or (!isset($leidaPorNombre))){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC	@return_value = alertasApagar
                                    @idCama = '.$idCama.',
                                    @leidaPorDni = '.$leidaPorDni.',
                                    @leidaPorNombre = "'.$leidaPorNombre.'",
                                    @mensaje = @mensaje OUTPUT
                            
                            SELECT	@return_value as estado, @mensaje as mensaje';
                    
                    try {
                        $stmt = getConeccionCAB()->query($sql);
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;
                        $a = new \stdClass();
                        $a = $resultado[0];

                        $u = new \stdClass();
                        $u->estado = (int)$a->estado;
                        $u->mensaje = $a->mensaje;
                        $datos = array('estado' => $u->estado, 'mensaje' => $u->mensaje);
                        if($u->estado == 1){
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }else{
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                        }
                        
                        unset($u);
                        unset($a);
                    } catch(\PDOException $e) {
                        $u = new \stdClass();
                        $est = 0;
                        $u->estado = (int)$est;
                        $u->mensaje = $e->getMessage();
                        $datos = array('estado' => $u->estado, 'mensaje' => $u->mensaje);
                        unset($u);
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

    // APAGAR ALERTAS DE UN SERVICIO
    public function apagarAlertasServicio(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosAlerta    = json_decode($json);
   
        $idCama         = $datosAlerta->idCama ?? null;
        $idUsuario      = $datosAlerta->idUsuario ?? null;
        $idServicio     = $datosAlerta->idServicio ?? null;
        $idAplicacion   = $datosAlerta->idAplicacion ?? null;

        $error = 0;
        $datos = array();

        // verifico que los datos obtenidos sean válidos.
        if($idCama == ''){ $error ++; }
        if($idUsuario == ''){ $error ++; }
        if($idServicio == ''){ $error ++; }
        if($idAplicacion == ''){ $error ++; }

        // si no envió el tokenAcceso
        if(!isset($tokenAcceso[0])){            
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Si el token enviado no es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // si los parámetros recibos no son válidos
        if ($error > 0) {
            $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        try {
            $db = getConeccionCAB();
            
            $db->beginTransaction();

            $sql = 'EXEC alertasServicioApagar
                        @idCama = :idCama,
                        @idUsuario = :idUsuario,
                        @idServicio = :idServicio,
                        @idAplicacion = :idAplicacion';

            $stmt = $db->prepare($sql);
            $stmt->bindParam(":idCama", $idCama);
            $stmt->bindParam(":idUsuario", $idUsuario);
            $stmt->bindParam(":idServicio", $idServicio);
            $stmt->bindParam(":idAplicacion", $idAplicacion);
            $stmt->execute();
            
            $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
            
            // Validar que el procedimiento retornó resultados
            if (empty($res)) {
                throw new \Exception('El procedimiento no retornó resultados');
            }
            
            // guardo los valores devueltos por el procedimiento almacenado tarea_iniciarFinalizarCancelar
            $estado = (int)$res[0]->estado;
            $mensaje = $res[0]->mensaje ?? 'Sin mensaje';
            
            // Si el procedimiento devolvió error (estado = 0)
            if ($estado === 0) {
                // Revierte la transacción
                $db->rollBack();
                $db = null;
                
                $datos = array('estado' => 0, 'mensaje' => $mensaje);
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            $db->commit();
            $db = null;

            $datos = array(
                    'estado' => $estado, 
                    'mensaje' => $mensaje
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {
            // Cualquier otro error
            if ($db && $db->inTransaction()) {
                $db->rollBack();
                $db = null; 
            }
            
            $datos = array(
                    'estado' => 0, 
                    'mensaje' => 'Error: ' . $e->getMessage()
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);                       
        } 
    }


    
//______________ AISLAMIENTOS ____________________________________________________________________
    

    // AISLAMIENTOS DISPONIBLES 
    public function obtenerAislamientosDisponibles(Request $request, Response $response, $args){
        $tokenAcceso        = $request->getHeader('TokenAcceso');
        $idInternacion      = $args['idInternacion'] ?? null;
        
        

        $error = 0;
        $datos = array();

        if($idInternacion == ''){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    $sql = 'EXEC aislamientosDisponibles @idInternacion = :idInternacion';
                    $db = getConeccionCAB();
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam('idInternacion',$idInternacion);
                    $stmt->execute();
                    $resultados = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;         
                    $datos = array();
                    
                    foreach($resultados as $aislamiento){
                        $c = new \stdClass();
                        $c->idAislamiento       = (int)$aislamiento->idAislamiento;
                        $c->nombreAislamiento   = $aislamiento->nombre;
                        $c->breve               = $aislamiento->breve;
                        $c->color               = $aislamiento->color;
                        
                        array_push($datos,$c);
                        unset($c);
                    }

                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
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

    // AISLAMIENTOS - NUEVO
    public function agregarAislamiento(Request $request, Response $response, $args){
        $tokenAcceso     = $request->getHeader('TokenAcceso');
        $json            = $request->getBody();
        $aislamiento     = json_decode($json); // array con los parámetros recibidos.
   

        $idAislamiento   = $aislamiento->idAislamiento ?? null;
        $idInternacion   = $aislamiento->idInternacion ?? null;
        $paciCodigo      = $aislamiento->paciCodigo ?? null;
        $creadoPorDni    = $aislamiento->creadoPorDni ?? null;
        $creadoPorNombre = $aislamiento->creadoPorNombre ?? null;
        $kpc             = $aislamiento->kpc ?? null;

        $error = 0;
        $datos = array();
        $r = new \stdClass();

        // verifico que los datos obtenidos sean válidos.
        if (!isset($idAislamiento) || !isset($idInternacion) || !isset($paciCodigo) || !isset($creadoPorDni) || !isset($creadoPorNombre)) {
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC	@return_value = aislamientoPaciente_nuevo
                                    @idAislamiento = '.$idAislamiento.',
                                    @idInternacion = '.$idInternacion.',
                                    @paciCodigo = '.$paciCodigo.',
                                    @creadoPorDni = '.$creadoPorDni.',
                                    @creadoPorNombre = "'.$creadoPorNombre.'",
                                    @kpc = '.$kpc.',
                                    @mensaje = @mensaje OUTPUT
                            
                            SELECT	@return_value as estado, @mensaje as mensaje';
                    
                    try {
                        $stmt = getConeccionCAB()->query($sql);
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;
                        $a = new \stdClass();
                        $a = $resultado[0];

                        $u = new \stdClass();
                        $u->estado = (int)$a->estado;
                        $u->mensaje = $a->mensaje;
                        $datos = array('estado' => $u->estado, 'mensaje' => $u->mensaje);
                        if($u->estado == 1){
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }else{
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                        }
                        
                        unset($u);
                        unset($a);
                    } catch(\PDOException $e) {
                        $u = new \stdClass();
                        $est = 0;
                        $u->estado = (int)$est;
                        $u->mensaje = $e->getMessage();
                        $datos = array('estado' => $u->estado, 'mensaje' => $u->mensaje);
                        unset($u);
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

    // AISLAMIENTOS - FINALIZAR
    public function finalizarAislamiento(Request $request, Response $response, $args){
        $tokenAcceso     = $request->getHeader('TokenAcceso');
        $json            = $request->getBody();
        $aislamiento     = json_decode($json); // array con los parámetros recibidos.
   

        $idPacienteAislamiento = $aislamiento->idPacienteAislamiento ?? null;        
        $finalizadoPorDni    = $aislamiento->finalizadoPorDni ?? null;
        $finalizadoPorNombre = $aislamiento->finalizadoPorNombre ?? null;

        $error = 0;
        $datos = array();
        $r = new \stdClass();

        // verifico que los datos obtenidos sean válidos.
        if (!isset($idPacienteAislamiento) || !isset($finalizadoPorDni) || !isset($finalizadoPorNombre)) {
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC	@return_value = aislamientoPaciente_finalizar
                                    @idPacienteAislamiento = '.$idPacienteAislamiento.',
                                    @finalizadoPorDni = '.$finalizadoPorDni.',
                                    @finalizadoPorNombre = "'.$finalizadoPorNombre.'",
                                    @mensaje = @mensaje OUTPUT
                                                                
                            SELECT	@return_value as estado, @mensaje as mensaje';
                    
                    try {
                        $stmt = getConeccionCAB()->query($sql);
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;
                        $a = new \stdClass();
                        $a = $resultado[0];

                        $u = new \stdClass();
                        $u->estado = (int)$a->estado;
                        $u->mensaje = $a->mensaje;
                        $datos = array('estado' => $u->estado, 'mensaje' => $u->mensaje);
                        if($u->estado == 1){
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }else{
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                        }
                        
                        unset($u);
                        unset($a);
                    } catch(\PDOException $e) {
                        $u = new \stdClass();
                        $est = 0;
                        $u->estado = (int)$est;
                        $u->mensaje = $e->getMessage();
                        $datos = array('estado' => $u->estado, 'mensaje' => $u->mensaje);
                        unset($u);
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



//______________ ALTAS ___________________________________________________________________________

    // VALIDAR FECHA DE ALTA DEFINITIVA
    public function validarFechaAltaDefinitiva(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $parametros     = $request->getQueryParams();
        $fechaAlta      = $parametros['fechaAlta'];

        $fechaServidor = fechaHoraServidorSQL();
        
        $error = 0;
        $datos = array();

        if($fechaAlta == ''){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    if(strtotime($fechaAlta) > strtotime($fechaServidor)){
                        $resultado = 'Posterior';
                    }else{
                        $resultado = 'Anterior';
                    }

                    $datos = array(
                        'resultado' => $resultado,
                        'fecha_servidor' => date_format(date_create($fechaServidor), 'd-m-Y H:i:s')                        
                    );

                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
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

    // CAMAS ALTA DEFINITIVA
    public function AltaDefinitiva(Request $request, Response $response, $args){
        /*
        Este método se ejecuta cuando el enfermero da el alta definitiva al paciente desde el Tablero de Camas.
        El método hace lo siguiente:
        1- Obtiene la fecha de alta médica y el tipo de alta médica desde la tabla camasMarkey.
        2- Registra el alta en el sistema Markey.
        3- Registra los datos del alta en la tabla CAB.dbo.internacionesAltas
        4- Actualiza el estado de las camas de la habitación en Markey, en la tabla camasMarkey y en la tabla camas, dependiendo de las condiciones de la cama
            (si está en aislamiento, si hay tareas de reparación que bloquean la habitación o la cama, si está limpia o no, etc).

        Este método no actualiza todas las camas del sistema obteniendo los datos de cada cama desde Markey y copiandola a la tabla CAB.dbo.camasMarkey.
        */
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosCama      = json_decode($json); // array con los parámetros recibidos.
   

        $idCama                 = $datosCama->idCama ?? null;
        $idHabitacion           = $datosCama->idHabitacion ?? null;        
        $idInternacion          = $datosCama->idInternacion ?? null;
        $paciCodigo             = $datosCama->paciCodigo ?? null;
        $fechaAltaDefinitiva    = $datosCama->fechaAltaDefinitiva ?? null;        
        $dni                    = $datosCama->dni ?? null;
        $nombreUsuario          = $datosCama->nombreUsuario ?? null;
        $idServicio             = $datosCama->idServicio ?? null;

        

        $error = 0;
        $datos = array();
        $r = new \stdClass();

        // $mensaje = 'idCama = '.$idCama.' idHabitacion ='. $idHabitacion.' idInternacion ='. $idInternacion.' paciCodigo ='. $paciCodigo.' fechaAltaDefinitiva= '. $fechaAltaDefinitiva.'
        //     dni ='. $dni.' nombreUsuario ='. $nombreUsuario.' idServicio = '.$idServicio;

        // $datos = array(
        //     "estado" => 500,
        //     "mensaje" => $mensaje
        // );
        // $response->getBody()->write(json_encode($datos));
        // return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        
        


        // verifico que los datos obtenidos sean válidos.
        if (!isset($idCama) || !isset($idHabitacion) || !isset($idInternacion) || !isset($paciCodigo) || !isset($fechaAltaDefinitiva) || !isset($dni) || !isset($nombreUsuario) || !isset($idServicio)) {
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    // 1- Obtengo la fecha de alta medica y el tipo de alta desde la tabla camasmarkey
                    $sql = 'EXEC camasVerUna @idCama = :idCama, @idServicio = :idServicio';
                    $db = getConeccionCAB();
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam('idCama',$idCama);
                    $stmt->bindParam('idServicio',$idServicio);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);

                    $fechaAltaMedica = date('Y-d-m H:i:s', strtotime($resultado[0]->fechaAltaMedica));
                    $fechaEgreso = $fechaAltaDefinitiva;
                    $fechaAltaDefinitiva = date('Y-d-m H:i:s', strtotime($fechaAltaDefinitiva));

                    $idTipoAltaMedica = $resultado[0]->idTipoAltaMedica;

                   

                    // 2- Registra el alta en el sistema Markey.
                    require_once '../class/Markey.php';
                    $datosCamas = new \Markey;
                    $resultadoAlta = $datosCamas->altaDefinitiva($paciCodigo, $idTipoAltaMedica, $idInternacion, $dni, $fechaEgreso);

                    if($resultadoAlta == 1){
                        //3- Registra los datos del alta en la tabla CAB.dbo.internacionesAltas
                        $sql = 'DECLARE	@return_value int
                                EXEC	@return_value = internacionRegistrarAltaDefinitiva
                                        @inteCodigo = :inteCodigo,
                                        @paciCodigo= :paciCodigo,
                                        @fechaAltaMedica = :fechaAltaMedica,
                                        @idTipoAltaMedica = :idTipoAltaMedica,
                                        @dni = :dni,
                                        @nombreUsuario = :nombreUsuario,
                                        @altaEfectiva = :altaEfectiva
                                
                                SELECT	@return_value as estado';
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam('inteCodigo',$idInternacion);
                        $stmt->bindParam('paciCodigo',$paciCodigo);
                        $stmt->bindParam('fechaAltaMedica',$fechaAltaMedica);
                        $stmt->bindParam('idTipoAltaMedica',$idTipoAltaMedica);
                        $stmt->bindParam('dni',$dni);
                        $stmt->bindParam('nombreUsuario',$nombreUsuario);
                        $stmt->bindParam('altaEfectiva',$fechaAltaDefinitiva);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        if($resultado[0]->estado == 1){
                            // 4- Actualiza el estado de las camas de la habitación en Markey y en la tabla camasMarkey, dependiendo de las condiciones de la cama
                            // (si está en aislamiento, si hay tareas de reparación que bloquean la habitación o la cama, si está limpia o no, etc).
                            // para esto llamo al método camasActualizarEstados que hace exactamente eso.
                            if ($this->camasActualizarEstados($idHabitacion, $dni, $nombreUsuario, $idServicio) == 1) {
                                // el estado de las camas se actualizó correctamente.
                                $datos = array(
                                    "estado" => 200,
                                    "mensaje" => "El alta definitiva fue registrada correctamente."
                                );
                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);                                
                            }else{
                                $datos = array(
                                    "estado" => 500,
                                    "mensaje" => "Error al actualizar el estado de las camas. (camasActualizarEstados() devolvió un error.)"
                                );
                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                            }                            
                        }else{
                            $datos = array(
                                "estado" => 500,
                                "mensaje" => "Error. El alta se registró en Markey pero no se registró en el Tablero de Camas."
                            );
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                        }
                    }else{
                        $datos = array(
                            "estado" => 500,
                            "mensaje" => "Error al registrar la alta en Markey."
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

    // TIPOS DE ALTAS - VER
    public function tiposAltasMedicas(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');        

        $datos = array();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                $sql = 'EXEC tiposAltas_ver';
                try {
                    $db = getConeccionCAB(); 
                    $stmt = $db->prepare($sql);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;

                    foreach($resultado as $alta){
                        $a = new \stdClass();
                        $a->idTipoAltaMedica = (int)$alta->idTipoAltaMedica;
                        $a->tipoAltaMedica = $alta->tipoAltaMedica;

                        array_push($datos,$a);
                        unset($a);
                    }

                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                } catch(\PDOException $e) {
                    $datos = array(
                        'estado' => 0,
                        'mensaje' => $e->getMessage()
                    );
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

    // ALTA PROBABLE - CREAR
    public function altaProbableCrear(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosAlta      = json_decode($json); // array con los parámetros recibidos.
   
        $idInternacion      = $datosAlta->idInternacion ?? null;
        $fechaAltaProbable  = $datosAlta->fechaAltaProbable ?? null;
        $idTipoAltaMedica   = $datosAlta->idTipoAltaMedica ?? null;
        $creadoPorDni       = $datosAlta->creadoPorDni ?? null;
        $creadoPorNombre    = $datosAlta->creadoPorNombre ?? null;

        $error = 0;
        $datos = array();

        if($idInternacion == ''){ $error ++; }
        if($fechaAltaProbable == ''){ $error ++; }
        if($idTipoAltaMedica == ''){ $error ++;}
        if($creadoPorDni == ''){ $error ++; }
        if($creadoPorNombre == ''){ $error ++; }

        
        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if ($error == 0) {
                    $sql = 'DECLARE	@return_value int, @men varchar(255)
                            EXEC @return_value = altaProbable_crear
                                        @idInternacion = :idInternacion,
                                        @fechaAltaProbable = :fechaAltaProbable,
                                        @idTipoAltaMedica = :idTipoAltaMedica,
                                        @creadoPorDni = :creadoPorDni,
                                        @creadoPorNombre = :creadoPorNombre,
                                        @mensaje = @men OUTPUT
                            
                            SELECT	@return_value as estado, @men as mensaje';

                    try {
                        if ($fechaAltaProbable !== null && $fechaAltaProbable !== '') {
                            $fechaAltaProbable = (new \DateTime($fechaAltaProbable))->format('Y-m-d H:i:s');
                        } else {
                            $fechaAltaProbable = null;
                        }
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idInternacion", $idInternacion);
                        $stmt->bindParam("fechaAltaProbable", $fechaAltaProbable);
                        $stmt->bindParam("idTipoAltaMedica", $idTipoAltaMedica);
                        $stmt->bindParam("creadoPorDni", $creadoPorDni);
                        $stmt->bindParam("creadoPorNombre", $creadoPorNombre);
                                                
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        $datos = [
                            'estado' => 1,
                            'mensaje' => $res[0]->mensaje
                        ];

                        if ($res[0]->estado == 1){
                            $httpStatus = 200; // cambio de cama no autorizado exitosamente.    
                        }else{
                            $httpStatus = 500; // ocurrió un error al intentar no autorizar el cambio de cama.
                        }

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus($httpStatus);
                        
                    } catch(\PDOException $e) {
                        $datos = array('estado' => 500, 'mensaje' => $e->getMessage());
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


    
//______________ CAMBIOS DE CAMA _________________________________________________________________
    

    // VER MOTIVOS DE CAMBIO DE CAMA
    public function verMotivosCambioCama(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');        

        $datos = array();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                $sql = 'EXEC motivosCambioCama_ver';
                try {
                    $db = getConeccionCAB(); 
                    $stmt = $db->prepare($sql);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;

                    foreach($resultado as $motivo){
                        $m = new \stdClass();
                        $m->idMotivoCambioCama = (int)$motivo->idMotivoCambioCama;
                        $m->motivo = $motivo->motivo;
                        $m->activo = (int)$motivo->activo;

                        array_push($datos,$m);
                        unset($m);
                    }

                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                } catch(\PDOException $e) {
                    $datos = array(
                        'estado' => 0,
                        'mensaje' => $e->getMessage()
                    );
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

    // BUSCAR SOLICITUD DE CAMBIO DE CAMA
    public function buscarSolicitudCambioCama(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');        
        $idInternacion  = $request->getQueryParams()['idInternacion'] ?? null;
        $idCamaOrigen   = $request->getQueryParams()['idCamaOrigen'] ?? null;

        $error = 0;
        $datos = array();

        if($idInternacion == ''){
            $error ++;
        }

        if($idCamaOrigen == ''){
            $error ++;
        }

        

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if ($error == 0) {
                    $sql = 'EXEC camasCambio_solicitud_buscar @idInternacion = :idInternacion,@idCamaOrigen = :idCamaOrigen';
                    try {
                        $db = getConeccionCAB(); 
                        $stmt = $db->prepare($sql);
                        $stmt->bindValue(':idInternacion', $idInternacion);
                        $stmt->bindValue(':idCamaOrigen', $idCamaOrigen);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        foreach($resultado as $solicitud){
                            $s = new \stdClass();
                            $s->idSolicitud          = (int)$solicitud->idSolicitudCambio;
                            $s->idInternacion        = (int)$solicitud->idInternacion;
                            $s->paciCodigo           = (int)$solicitud->paciCodigo;
                            $s->tdocCodigo           = (int)$solicitud->tdocCodigo;
                            $s->nroDocumento         = $solicitud->nroDocumento;
                            $s->fecha                = date_format(date_create($solicitud->fecha), 'd-m-Y H:i:s');
                            $s->idCamaOrigen         = (int)$solicitud->idCamaOrigen;
                            $s->camaOrigen           = $solicitud->camaOrigen;
                            $s->idCamaDestino        = (int)$solicitud->idCamaDestino;
                            $s->camaDestino          = $solicitud->camaDestino;
                            $s->idMotivo             = (int)$solicitud->idMotivo;
                            $s->motivo               = $solicitud->motivo;
                            $s->idEstadoSolicitud    = (int)$solicitud->idEstadoSolicitud;
                            $s->estado               = $solicitud->estado;
                            $s->solicitadoPorDni     = $solicitud->solicitadoPorDni;
                            $s->solicitadoPorNombre  = $solicitud->solicitadoPorNombre;
                            
                            if(is_null($solicitud->autorizadoFecha)){
                                $s->autorizadoFecha = null;
                            } else {
                                $s->autorizadoFecha = date_format(date_create($solicitud->autorizadoFecha), 'd-m-Y H:i:s');
                            }
                            
                            $s->autorizadoPorDni    = $solicitud->autorizadoPorDni;
                            $s->autorizadoPorNombre = $solicitud->autorizadoPorNombre;

                            if(is_null($solicitud->realizadoFecha)){
                                $s->realizadoFecha = $solicitud->realizadoFecha;
                            } else {
                                $s->realizadoFecha = date_format(date_create($solicitud->realizadoFecha), 'd-m-Y H:i:s');
                            }
                            
                            $s->realizadoPorDni    = $solicitud->realizadoPorDni;
                            $s->realizadoPorNombre = $solicitud->realizadoPorNombre;

                            if(is_null($solicitud->canceladoFecha)){
                                $s->canceladoFecha = $solicitud->canceladoFecha;
                            } else {
                                $s->canceladoFecha = date_format(date_create($solicitud->canceladoFecha), 'd-m-Y H:i:s');
                            }
                            
                            $s->canceladoPorDni    = $solicitud->canceladoPorDni;
                            $s->canceladoPorNombre = $solicitud->canceladoPorNombre;

                            $s->requiereAutorizacionEnfermeria = $solicitud->requiereAutorizacionEnfermeria;
                            $s->autEnfermeriaEstado = $solicitud->autEnfermeriaEstado;
                            $s->autEnfermeriaEstadoTexto = $solicitud->autEnfermeriaEstadoTexto;
                            if(is_null($solicitud->autEnfermeriaFecha)){
                                $s->autEnfermeriaFecha = null;
                            }else{
                                $s->autEnfermeriaFecha = date_format(date_create($solicitud->autEnfermeriaFecha), 'd-m-Y H:i:s'); 
                            }
                            $s->autEnfermeriaPorDni = $solicitud->autEnfermeriaPorDni;
                            $s->autEnfermeriaPorNombre = $solicitud->autEnfermeriaPorNombre;

                            array_push($datos, $s);
                            unset($s);
                        }

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                    } catch(\PDOException $e) {
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

    // ELIMINAR SOLICITUD DE CAMBIO DE CAMA
    public function cambioCamaEliminarSolicitud(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');        
        $idSolicitudCambio  = $request->getQueryParams()['idSolicitudCambio'] ?? null;
        $dni   = $request->getQueryParams()['dni'] ?? null;
        $nombreUsuario   = $request->getQueryParams()['nombreUsuario'] ?? null;

        $error = 0;
        $datos = array();

        if($idSolicitudCambio == ''){
            $error ++;
        }

        if($dni == ''){
            $error ++;
        }

        if($nombreUsuario == ''){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if ($error == 0) {
                    $sql = 'declare @mensaje varchar(255)
                            declare @return_value int
                            EXEC @return_value = camasCambio_solicitud_cancelar
                                    @idSolicitudCambio = :idSolicitudCambio,
                                    @dni = :dni,
                                    @nombreUsuario = :nombreUsuario,
                                    @mensaje = @mensaje OUTPUT
                                
                            select @mensaje as mensaje, @return_value as return_value';
                    try {
                        $db = getConeccionCAB(); 
                        $stmt = $db->prepare($sql);
                        $stmt->bindValue(':idSolicitudCambio', $idSolicitudCambio);
                        $stmt->bindValue(':dni', $dni);
                        $stmt->bindValue(':nombreUsuario', $nombreUsuario);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        foreach($resultado as $solicitud){
                            $s = new \stdClass();
                            $s->estado = $solicitud->return_value;
                            $s->mensaje = $solicitud->mensaje;
                            array_push($datos,$s);
                            unset($s);
                        }

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                    } catch(\PDOException $e) {
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

    // CREAR SOLICITUD DE CAMBIO DE CAMA
    public function cambioCamaCrearSolicitud(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosSolicitud = json_decode($json); // array con los parámetros recibidos.
   
        $idInternacion          = $datosSolicitud->idInternacion ?? null;
        $paciCodigo             = $datosSolicitud->paciCodigo ?? null;
        $tdocCodigo             = $datosSolicitud->tdocCodigo ?? null;
        $nroDocumento           = $datosSolicitud->nroDocumento ?? null;
        $idCamaOrigen           = $datosSolicitud->idCamaOrigen ?? null;
        $idMotivo               = $datosSolicitud->idMotivo ?? null;        
        $solicitadoPorDni       = $datosSolicitud->solicitadoPorDni ?? null;
        $solicitadoPorNombre    = $datosSolicitud->solicitadoPorNombre ?? null;

        $error = 0;
        $datos = array();

        if($idInternacion == ''){ $error ++; }
        if($paciCodigo == ''){ $error ++; } 
        if($nroDocumento == ''){ $error ++; }
        if($idCamaOrigen == ''){ $error ++; }
        if($idMotivo == ''){ $error ++; }
        if($solicitadoPorDni == ''){ $error ++; }
        if($solicitadoPorNombre == ''){ $error ++;}

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if ($error == 0) {
                    $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC @return_value = camasCambio_nuevaSolicitud
                                        @idInternacion = :idInternacion,
                                        @paciCodigo = :paciCodigo,
                                        @tdocCodigo = :tdocCodigo,
                                        @nroDocumento = :nroDocumento,
                                        @idCamaOrigen = :idCamaOrigen,
                                        @idMotivo = :idMotivo,
                                        @solicitadoPorDni = :solicitadoPorDni,
                                        @solicitadoPorNombre = :solicitadoPorNombre,
                                        @mensaje = @mensaje OUTPUT
                            
                            SELECT	@return_value as estado, @mensaje as mensaje';

                    try {
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idInternacion", $idInternacion);
                        $stmt->bindParam("paciCodigo", $paciCodigo);
                        $stmt->bindParam("tdocCodigo", $tdocCodigo);
                        $stmt->bindParam("nroDocumento", $nroDocumento);
                        $stmt->bindParam("idCamaOrigen", $idCamaOrigen);
                        $stmt->bindParam("idMotivo", $idMotivo);
                        $stmt->bindParam("solicitadoPorDni", $solicitadoPorDni);
                        $stmt->bindParam("solicitadoPorNombre", $solicitadoPorNombre);
                        
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;
                        if($res[0]->estado > 0){
                            $datos = array('id_solicitud' => (int)$res[0]->estado, 'mensaje' => $res[0]->mensaje);
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }else{
                            if($res[0]->estado == -1){
                                $datos = array('id_solicitud' => 0, 'mensaje' => $res[0]->mensaje);
                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                            }
                        }
                        
                        
                    } catch(\PDOException $e) {
                        $datos = array('id_solicitud' => 0, 'mensaje' => $e->getMessage());
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

    // OBTENER CAMAS DISPONIBLES
    public function camasDisponibles(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');    
        $paciCodigo     = $request->getQueryParams()['paciCodigo'] ?? null; // código del paciente que voy a ingresar en la cama disponible.
        $datos = array();          

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                $sql = 'EXEC camasDisponibles @paciCodigo = :paciCodigo';
                try {
                    $db = getConeccionCAB(); 
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam("paciCodigo", $paciCodigo);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;

                    foreach($resultado as $cama){
                        $c = new \stdClass();
                        $c->idCama          = (int)$cama->idCama;
                        $c->cama            = $cama->cama;
                        $c->idHabitacion    = (int)$cama->idHabitacion;
                        $c->tipoCama        = $cama->tipoCama;
                        $c->piso            = $cama->piso;
                        $c->aislamiento     = (int)$cama->aislamiento;
                        if($cama->aislamiento == 1){
                            $c->advertencia = 'AISLAMIENTO - Requiere autorizacion de Sup. de Enfermería';
                        }else{
                            $c->advertencia = '';
                        }
                                            
                        array_push($datos, $c);
                        unset($c);
                    }

                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                } catch(\PDOException $e) {
                    $datos = array(
                        'estado' => 0,
                        'mensaje' => $e->getMessage()
                    );
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

    // AUTORIZAR DE CAMBIO DE CAMA
    public function autorizarCambioCama(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosSolicitud = json_decode($json); // array con los parámetros recibidos.
   
        $idSolicitudCambio      = $datosSolicitud->idSolicitudCambio ?? null;
        $idCamaDestino          = $datosSolicitud->idCamaDestino ?? null;
        $autorizadoPorDni       = $datosSolicitud->autorizadoPorDni ?? null;
        $autorizadoPorNombre    = $datosSolicitud->autorizadoPorNombre ?? null;

        $error = 0;
        $datos = array();

        if($idSolicitudCambio == ''){ $error ++; }
        if($idCamaDestino == ''){ $error ++; } 
        if($autorizadoPorDni == ''){ $error ++; }
        if($autorizadoPorNombre == ''){ $error ++;}

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if ($error == 0) {
                    $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC @return_value = cambioCama_autorizar
                                        @idSolicitudCambio = :idSolicitudCambio,
                                        @idCamaDestino = :idCamaDestino,
                                        @autorizadoPorDni = :autorizadoPorDni,
                                        @autorizadoPorNombre = :autorizadoPorNombre,
                                        @mensaje = @mensaje OUTPUT
                            
                            SELECT	@return_value as estado, @mensaje as mensaje';

                    try {
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idSolicitudCambio", $idSolicitudCambio);
                        $stmt->bindParam("idCamaDestino", $idCamaDestino);
                        $stmt->bindParam("autorizadoPorDni", $autorizadoPorDni);
                        $stmt->bindParam("autorizadoPorNombre", $autorizadoPorNombre);
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        $httpStatus = match((string)$res[0]->estado){  // Convierte a string para consistencia
                            '0' => 500, // ocurrió un error al intentar autorizar el cambio de cama.
                            '1' => 200, // cambio de cama autorizado exitosamente.
                            '2' => 202, // cambio de cama aceptado, pero requiere que sea autorizado por Supervisión de Enfermería.
                            default => 400  // Valor inesperado; ajusta según lógica (ej. bad request)
                        };

                        $datos = [
                            'estado' => $httpStatus,
                            'mensaje' => $res[0]->mensaje
                        ];

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus($httpStatus);
                        
                    } catch(\PDOException $e) {
                        $datos = array('estado' => 500, 'mensaje' => $e->getMessage());
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

    // NO AUTORIZAR DE CAMBIO DE CAMA
    public function noAutorizarCambioCama(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosSolicitud = json_decode($json); // array con los parámetros recibidos.
   
        $idSolicitudCambio      = $datosSolicitud->idSolicitudCambio ?? null;
        $autorizadoPorDni       = $datosSolicitud->autorizadoPorDni ?? null;
        $autorizadoPorNombre    = $datosSolicitud->autorizadoPorNombre ?? null;

        $error = 0;
        $datos = array();

        if($idSolicitudCambio == ''){ $error ++; }
        if($autorizadoPorDni == ''){ $error ++; }
        if($autorizadoPorNombre == ''){ $error ++;}

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if ($error == 0) {
                    $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC @return_value = cambioCama_NoAutorizar
                                        @idSolicitudCambio = :idSolicitudCambio,
                                        @autorizadoPorDni = :autorizadoPorDni,
                                        @autorizadoPorNombre = :autorizadoPorNombre,
                                        @mensaje = @mensaje OUTPUT
                            
                            SELECT	@return_value as estado, @mensaje as mensaje';

                    try {
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idSolicitudCambio", $idSolicitudCambio);
                        $stmt->bindParam("autorizadoPorDni", $autorizadoPorDni);
                        $stmt->bindParam("autorizadoPorNombre", $autorizadoPorNombre);
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        $datos = [
                            'estado' => 1,
                            'mensaje' => $res[0]->mensaje
                        ];

                        if ($res[0]->estado == 1){
                            $httpStatus = 200; // cambio de cama no autorizado exitosamente.    
                        }else{
                            $httpStatus = 500; // ocurrió un error al intentar no autorizar el cambio de cama.
                        }

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus($httpStatus);
                        
                    } catch(\PDOException $e) {
                        $datos = array('estado' => 500, 'mensaje' => $e->getMessage());
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

    // CAMBIOS DE CAMAS PENDIENTES - VER
    public function camasCambiosPendientes(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');    
        $datos = array();          

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                $sql = 'EXEC camasCambiosPendientes_ver';
                try {
                    $db = getConeccionCAB(); 
                    $stmt = $db->prepare($sql);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;

                    foreach($resultado as $cc){
                        $c = new \stdClass();
                        $c->idCamaCambiosPendientes = (int)$cc->idCamaCambiosPendientes;
                        $c->idSolicitudCambio       = (int)$cc->idSolicitudCambio;
                        $c->idCamaDestino           = (int)$cc->idCamaDestino;
                        $c->camaDestino             = $cc->camaDestino;
                        $c->idHabitacion            = (int)$cc->idHabitacion;
                        $c->paciCodigo              = (int)$cc->paciCodigo;
                        $c->paciente                = $cc->paciente;
                        $c->tdocCodigo              = (int)$cc->tdocCodigo;
                        $c->tipoDocumento           =$cc->tipoDocumento;
                        $c->nroDocumento            = $cc->nroDocumento;
                        $c->sexo                    = $cc->sexo;
                        $c->idInternacion           = (int)$cc->idInternacion;
                        $c->idCamaOrigen            = (int)$cc->idCamaOrigen;
                        $c->camaOrigen              = $cc->camaOrigen;
                        $c->aislamientos            = json_decode($cc->aislamientos);

                        array_push($datos, $c);
                        unset($c);
                    }

                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                } catch(\PDOException $e) {
                    $datos = array(
                        'estado' => 0,
                        'mensaje' => $e->getMessage()
                    );
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

    // VER PACIENTE QUE ESTÁN EN UNA HABITACIÓN
    public function pacientesHabitacion(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idHabitacion = $request->getQueryParams()['idHabitacion'] ?? null;
        $datos = array();          

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                $sql = 'EXEC pacientesHabitacion_ver @idHabitacion = :idHabitacion';
                try {
                    $db = getConeccionCAB(); 
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam("idHabitacion", $idHabitacion);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;

                    foreach($resultado as $pac){
                        $p = new \stdClass();
                        $p->idCama  = (int)$pac->idCama;
                        $p->cama    = $pac->cama;
                        $p->paciCodigo    = (int)$pac->paciCodigo;
                        $p->paciente      = $pac->paciente;
                        $p->tdocCodigo    = (int)$pac->tdocCodigo;
                        $p->tipoDocumento = $pac->tipoDocumento;
                        $p->nroDocumento  = $pac->nroDocumento;
                        $p->sexo          = $pac->sexo;
                        $p->idInternacion = (int)$pac->idInternacion;
                        $p->aislamientos  = json_decode($pac->aislamientos);

                        array_push($datos, $p);
                        unset($c);
                    }

                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                } catch(\PDOException $e) {
                    $datos = array(
                        'estado' => 0,
                        'mensaje' => $e->getMessage()
                    );
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

    // NO AUTORIZAR DE CAMBIO DE CAMA PENDIENTE (SUPERVISIÓN DE ENFERMERÍA)
    public function camasCambiosPendientesNoAutorizar(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosSolicitud = json_decode($json); // array con los parámetros recibidos.
   
        $idCamasCambiosPendientes = $datosSolicitud->idCamasCambiosPendientes ?? null;
        $autEnfermeriaPorDni        = $datosSolicitud->autEnfermeriaPorDni ?? null;
        $autEnfermeriaPorNombre    = $datosSolicitud->autEnfermeriaPorNombre ?? null;

        $error = 0;
        $datos = array();

        if($idCamasCambiosPendientes == ''){ $error ++; }
        if($autEnfermeriaPorDni == ''){ $error ++; }
        if($autEnfermeriaPorNombre == ''){ $error ++;}

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if ($error == 0) {
                    $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC @return_value = camasCambiosPendientes_NoAutorizar
                                        @idCamasCambiosPendientes = :idCamasCambiosPendientes,
                                        @autEnfermeriaPorDni = :autEnfermeriaPorDni,
                                        @autEnfermeriaPorNombre = :autEnfermeriaPorNombre,
                                        @mensaje = @mensaje OUTPUT
                            
                            SELECT	@return_value as estado, @mensaje as mensaje';

                    try {
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idCamasCambiosPendientes", $idCamasCambiosPendientes);
                        $stmt->bindParam("autEnfermeriaPorDni", $autEnfermeriaPorDni);
                        $stmt->bindParam("autEnfermeriaPorNombre", $autEnfermeriaPorNombre);
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        $datos = [
                            'estado' => 1,
                            'mensaje' => $res[0]->mensaje
                        ];

                        if ($res[0]->estado == 1){
                            $httpStatus = 200; // cambio de cama no autorizado exitosamente.    
                        }else{
                            $httpStatus = 500; // ocurrió un error al intentar no autorizar el cambio de cama.
                        }

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus($httpStatus);
                        
                    } catch(\PDOException $e) {
                        $datos = array('estado' => 500, 'mensaje' => $e->getMessage());
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

    // AUTORIZAR DE CAMBIO DE CAMA PENDIENTE (SUPERVISIÓN DE ENFERMERÍA)
    public function camasCambiosPendientesAutorizar(Request $request, Response $response, $args){
        // 1) registra la autorización en camasCambiosPendientes
        // 2) modifica la solicitud de cambio de cama como autorizada, indicando la cama destino,  para que el enfermero puedo hacer el cambio de cama en el sistema.
        // 3) reserva la cama destino para que no pueda ser asignada a otro paciente mientras se realiza el proceso de cambio de cama.
        // 4) Envia alerta de cambio de cama autorizado a enfermería para que realice el cambio de cama físicamente y en el sistema.
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosSolicitud = json_decode($json); // array con los parámetros recibidos.
   
        $idCamasCambiosPendientes = $datosSolicitud->idCamasCambiosPendientes ?? null;
        $autEnfermeriaPorDni        = $datosSolicitud->autEnfermeriaPorDni ?? null;
        $autEnfermeriaPorNombre    = $datosSolicitud->autEnfermeriaPorNombre ?? null;

        $error = 0;
        $datos = array();

        if($idCamasCambiosPendientes == ''){ $error ++; }
        if($autEnfermeriaPorDni == ''){ $error ++; }
        if($autEnfermeriaPorNombre == ''){ $error ++;}

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if ($error == 0) {
                    $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC @return_value = camasCambiosPendientes_Autorizar
                                        @idCamasCambiosPendientes = :idCamasCambiosPendientes,
                                        @autEnfermeriaPorDni = :autEnfermeriaPorDni,
                                        @autEnfermeriaPorNombre = :autEnfermeriaPorNombre,
                                        @mensaje = @mensaje OUTPUT
                            
                            SELECT	@return_value as estado, @mensaje as mensaje';

                    try {
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idCamasCambiosPendientes", $idCamasCambiosPendientes);
                        $stmt->bindParam("autEnfermeriaPorDni", $autEnfermeriaPorDni);
                        $stmt->bindParam("autEnfermeriaPorNombre", $autEnfermeriaPorNombre);
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        $datos = [
                            'estado' => 1,
                            'mensaje' => $res[0]->mensaje
                        ];

                        if ($res[0]->estado == 1){
                            $httpStatus = 200; // cambio de cama autorizado exitosamente.    
                        }else{
                            $httpStatus = 500; // ocurrió un error al intentar autorizar el cambio de cama.
                        }

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus($httpStatus);
                        
                    } catch(\PDOException $e) {
                        $datos = array('estado' => 500, 'mensaje' => $e->getMessage());
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

    // CAMAS CAMBIOS - REGISTRAR
    public function camasCambiosRegistrar_v1(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosSolicitud = json_decode($json); // array con los parámetros recibidos.
   
        $idSolicitudCambio  = $datosSolicitud->idSolicitudCambio ?? null;
        $realizadoPorDni    = $datosSolicitud->realizadoPorDni ?? null;
        $realizadoPorNombre = $datosSolicitud->realizadoPorNombre ?? null;
        $idServicio         = $datosSolicitud->idServicio ?? null;

        $error = 0;
        $datos = array();

        if($idSolicitudCambio == ''){ $error ++; }
        if($realizadoPorDni == ''){ $error ++; }
        if($realizadoPorNombre == ''){ $error ++; }
        if($idServicio == ''){ $error ++; }

        
        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido                

                if ($error == 0) {
                    $sql = 'DECLARE	@return_value int, @men varchar(255)
                            EXEC @return_value = camasCambios_registrarCambio
                                        @idSolicitudCambio = :idSolicitudCambio,
                                        @realizadoPorDni = :realizadoPorDni,
                                        @realizadoPorNombre = :realizadoPorNombre,
                                        @idServicio = :idServicio,
                                        @mensaje = @men OUTPUT
                            
                            SELECT	@return_value as estado, @men as mensaje';

                    try {                        
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idSolicitudCambio", $idSolicitudCambio);
                        $stmt->bindParam("realizadoPorDni", $realizadoPorDni);
                        $stmt->bindParam("realizadoPorNombre", $realizadoPorNombre);
                        $stmt->bindParam("idServicio", $idServicio);
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        $datos = [
                            'estado' => (int)$res[0]->estado,
                            'mensaje' => $res[0]->mensaje
                        ];

                        if ($res[0]->estado == 1){
                            $httpStatus = 200; 
                        }else{
                            $httpStatus = 500; 
                        }

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus($httpStatus);
                        
                    } catch(\PDOException $e) {
                        $datos = array('estado' => 500, 'mensaje' => $e->getMessage());
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

    public function camasCambiosRegistrar(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosSolicitud = json_decode($json); // array con los parámetros recibidos.
   
        $idSolicitudCambio  = $datosSolicitud->idSolicitudCambio ?? null;
        $realizadoPorDni    = $datosSolicitud->realizadoPorDni ?? null;
        $realizadoPorNombre = $datosSolicitud->realizadoPorNombre ?? null;
        $idServicio         = $datosSolicitud->idServicio ?? null;

        $error = 0;
        $datos = array();

        if($idSolicitudCambio == ''){ $error ++; }
        if($realizadoPorDni == ''){ $error ++; }
        if($realizadoPorNombre == ''){ $error ++; }
        if($idServicio == ''){ $error ++; }

        
        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido                

                if ($error == 0) {

                    try {
                        $db = getConeccionCAB();
                        
                        // Inicia la transacción
                        $db->beginTransaction();
                        
                        $sql = 'DECLARE @return_value int, @men varchar(255), @nuevoEstado int
                                EXEC @return_value = camasCambios_registrarCambio
                                            @idSolicitudCambio = :idSolicitudCambio,
                                            @realizadoPorDni = :realizadoPorDni,
                                            @realizadoPorNombre = :realizadoPorNombre,
                                            @idServicio = :idServicio,
                                            @mensaje = @men OUTPUT,
                                            @nuevoEstadoCamaOrigen = @nuevoEstado OUTPUT;
                                
                                SELECT @return_value as estado, @men as mensaje, @nuevoEstado as nuevoEstado';
                        
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam(":idSolicitudCambio", $idSolicitudCambio);
                        $stmt->bindParam(":realizadoPorDni", $realizadoPorDni);
                        $stmt->bindParam(":realizadoPorNombre", $realizadoPorNombre);
                        $stmt->bindParam(":idServicio", $idServicio);
                        $stmt->execute();
                        
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        
                        // Validar que el procedimiento retornó resultados
                        if (empty($res)) {
                            throw new \Exception('El procedimiento no retornó resultados');
                        }
                        
                        $estado = (int)$res[0]->estado;
                        $mensaje = $res[0]->mensaje ?? 'Sin mensaje';
                        $nuevoEstado = (int)$res[0]->nuevoEstado; // nuevo estado a asignar a la cama de origen luego de ser desocupada. Puede ser limpieza o reparación.
                        
                        // Si el procedimiento devolvió error (estado = 0)
                        if ($estado === 0) {
                            // Revierte la transacción
                            $db->rollBack();
                            
                            $datos = array(
                                    'estado' => 0, 
                                    'mensaje' => $mensaje
                                );
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                            $db = null;
                        }
                        
                        // Si el procedimiento fue exitoso (estado = 1)
                        
                        // PASO 2: Llama a la API de Markey para disponibilizar la cama destino (cambiarEstado)
                        
                        // para esto necesito algunos datos de la solicitud de cambio que debo obtener de la BD
                        // necesito: idCamarOrigen, idCamaDestino, idInternacion, paciCodigo,

                        $sql2 = 'select * from camasCambios where idSolicitudCambio = :idSolicitudCambio';
                        
                        $stmt2 = $db->prepare($sql2);
                        $stmt2->bindParam(":idSolicitudCambio", $idSolicitudCambio);
                        $stmt2->execute();
                        $res2 = $stmt2->fetchAll(\PDO::FETCH_OBJ);
                        
                        $idCamaOrigen   = (int)$res2[0]->idCamaOrigen;
                        $idCamaDestino  = (int)$res2[0]->idCamaDestino;
                        $idInternacion  = (int)$res2[0]->idInternacion;
                        $paciCodigo     = (int)$res2[0]->paciCodigo;
                        
                        //error_log("idCamaOrigen: " . $idCamaOrigen. '- idCamaDestino: '. $idCamaDestino. ' idInternacion: ' . $idInternacion . ' paciCodigo: '. $paciCodigo);

                        require_once '../class/Markey.php';
                        $Markey = new \Markey;
                        $resultadoCambiarEstado = $Markey->cambiarEstado($idCamaDestino, 1);

                        if($resultadoCambiarEstado == 0){
                            // ocurrió algún error.
                            $db->rollBack();
                                
                            $datos = array(
                                'estado' => 0, 
                                'mensaje' => 'Error al intentar disponibilizar la cama destino. Endpoit: cambiarEstado'
                            );
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                            $db = null;
                        }

                        // PASO 3 - Realizo el cambio de cama en Markey
                        $resultadoCambiarPaciente = $Markey->cambiarCama($idCamaOrigen, $idCamaDestino, $idInternacion, $paciCodigo, $realizadoPorDni);
                        if($resultadoCambiarPaciente == 0){
                            // ocurrió algún error.
                            $db->rollBack();
                                
                            $datos = array(
                                'estado' => 0, 
                                'mensaje' => 'Error al intentar disponibilizar la cama destino. Endpoit: cambiarEstado'
                            );
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                            $db = null;
                        }

                        
                        // PASO 4 - Cambio el estado de la cama origen.                       
                        
                        $resultadoCambiarEstado2 = $Markey->cambiarEstado($idCamaOrigen, $nuevoEstado);

                        //error_log("idEstado cama origen: " . $nuevoEstado. '- resultadoCambiarEstado2: '. $resultadoCambiarEstado2);

                        if($resultadoCambiarEstado2 == 0){
                            // ocurrió algún error.
                            $db->rollBack();
                                
                            $datos = array(
                                'estado' => 0, 
                                'mensaje' => 'Error al cambiar el estado de la cama origen en Markey. Endpoit: cambiarEstado'
                            );
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                            $db = null;
                        }
                        
                        
                        // Si todo está bien, confirma la transacción
                        $db->commit();
                        
                        $datos = array(
                                'estado' => 1, 
                                'mensaje' => 'El cambio de cama fue registrado correctamente.'
                            );
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        $db = null;
                        
                    } catch (\Exception $e) {
                        // Cualquier otro error
                        if ($db && $db->inTransaction()) {
                            $db->rollBack();
                        }
                        
                        $datos = array(
                                'estado' => 0, 
                                'mensaje' => 'Error: ' . $e->getMessage()
                            );
                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                        $db = null;                        
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

    // VER CAMAS DISPONIBLES DE UN ÁREA CERRADA
    public function camasDisponiblesAreaCerrada(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');      
        $idServicio     = $request->getQueryParams()['idServicio'] ?? null;  
        $soloAltaMedica = $request->getQueryParams()['soloAltaMedica'] ?? null;  

        $datos = array();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                $sql = 'EXEC camasDisponibleAreaCerrada @idServicio = :idServicio, @soloAltaMedica = :soloAltaMedica';
                try {
                    $db = getConeccionCAB(); 
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':idServicio', $idServicio);
                    $stmt->bindParam(':soloAltaMedica', $soloAltaMedica);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;

                    foreach($resultado as $cama){
                        $c = new \stdClass();
                        $c->idCama          = (int)$cama->idCama;
                        $c->cama            = $cama->cama;
                        $c->idHabitacion    = (int)$cama->idHabitacion;
                        $c->habitacion      = $cama->habitacion;
                        $c->piso            = $cama->piso;
                        $c->tipoCama        = $cama->tipoCama;

                        array_push($datos,$c);
                        unset($c);
                    }

                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                } catch(\PDOException $e) {
                    $datos = array(
                        'estado' => 0,
                        'mensaje' => $e->getMessage()
                    );
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

    // CREAR SOLICITUD DE CAMBIO DE CAMA PARA UN ÁREA CERRADA
    public function cambioCamaCrearSolicitudAreaCerrada(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosSolicitud = json_decode($json); // array con los parámetros recibidos.
   
        $idInternacion          = $datosSolicitud->idInternacion ?? null;
        $paciCodigo             = $datosSolicitud->paciCodigo ?? null;
        $tdocCodigo             = $datosSolicitud->tdocCodigo ?? null;
        $nroDocumento           = $datosSolicitud->nroDocumento ?? null;
        $idCamaOrigen           = $datosSolicitud->idCamaOrigen ?? null;
        $idCamaDestino          = $datosSolicitud->idCamaDestino ?? null;
        $solicitadoPorDni       = $datosSolicitud->solicitadoPorDni ?? null;
        $solicitadoPorNombre    = $datosSolicitud->solicitadoPorNombre ?? null;

        $error = 0;
        $datos = array();

        if($idInternacion == ''){ $error ++; }
        if($paciCodigo == ''){ $error ++; } 
        if($tdocCodigo == ''){ $error ++; } 
        if($nroDocumento == ''){ $error ++; }
        if($idCamaOrigen == ''){ $error ++; }
        if($idCamaDestino == ''){ $error ++; }
        if($solicitadoPorDni == ''){ $error ++; }
        if($solicitadoPorNombre == ''){ $error ++;}

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if ($error == 0) {
                    $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC @return_value = camasCambio_nuevaSolicitudAreaCerrada
                                        @idInternacion = :idInternacion,
                                        @paciCodigo = :paciCodigo,
                                        @tdocCodigo = :tdocCodigo,
                                        @nroDocumento = :nroDocumento,
                                        @idCamaOrigen = :idCamaOrigen,
                                        @idCamaDestino = :idCamaDestino,
                                        @solicitadoPorDni = :solicitadoPorDni,
                                        @solicitadoPorNombre = :solicitadoPorNombre,
                                        @mensaje = @mensaje OUTPUT
                            
                            SELECT	@return_value as estado, @mensaje as mensaje';

                    try {
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idInternacion", $idInternacion);
                        $stmt->bindParam("paciCodigo", $paciCodigo);
                        $stmt->bindParam("tdocCodigo", $tdocCodigo);
                        $stmt->bindParam("nroDocumento", $nroDocumento);
                        $stmt->bindParam("idCamaOrigen", $idCamaOrigen);
                        $stmt->bindParam("idCamaDestino", $idCamaDestino);
                        $stmt->bindParam("solicitadoPorDni", $solicitadoPorDni);
                        $stmt->bindParam("solicitadoPorNombre", $solicitadoPorNombre);
                        
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;
                        if($res[0]->estado > 0){
                            $datos = array('id_solicitud' => (int)$res[0]->estado, 'mensaje' => $res[0]->mensaje);
                            $response->getBody()->write(json_encode($datos));
                            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                        }else{
                            if($res[0]->estado == -1){
                                $datos = array('id_solicitud' => 0, 'mensaje' => $res[0]->mensaje);
                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                            }
                        }
                    } catch(\PDOException $e) {
                        $datos = array('id_solicitud' => 0, 'mensaje' => $e->getMessage());
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

    // AUTORIZAR DE CAMBIO DE CAMA
    public function ordenarCambioCama(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosSolicitud = json_decode($json); // array con los parámetros recibidos.
   
        $idCamaOrigen           = $datosSolicitud->idCamaOrigen ?? null;
        $idCamaDestino          = $datosSolicitud->idCamaDestino ?? null;
        $autorizadoPorDni       = $datosSolicitud->autorizadoPorDni ?? null;
        $autorizadoPorNombre    = $datosSolicitud->autorizadoPorNombre ?? null;

        $error = 0;
        $datos = array();

        if($idCamaOrigen == ''){ $error ++; }
        if($idCamaDestino == ''){ $error ++; } 
        if($autorizadoPorDni == ''){ $error ++; }
        if($autorizadoPorNombre == ''){ $error ++;}

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if ($error == 0) {
                    $sql = 'DECLARE	@return_value int, @mensaje varchar(255)
                            EXEC @return_value = cambioCama_ordenar
                                        @idCamaOrigen = :idCamaOrigen,
                                        @idCamaDestino = :idCamaDestino,
                                        @autorizadoPorDni = :autorizadoPorDni,
                                        @autorizadoPorNombre = :autorizadoPorNombre,
                                        @mensaje = @mensaje OUTPUT
                            
                            SELECT	@return_value as estado, @mensaje as mensaje';

                    try {
                        $db = getConeccionCAB();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("idCamaOrigen", $idCamaOrigen);
                        $stmt->bindParam("idCamaDestino", $idCamaDestino);
                        $stmt->bindParam("autorizadoPorDni", $autorizadoPorDni);
                        $stmt->bindParam("autorizadoPorNombre", $autorizadoPorNombre);
                        $stmt->execute();
                        $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        $httpStatus = match((string)$res[0]->estado){  // Convierte a string para consistencia
                            '0' => 500, // ocurrió un error al intentar ordenar el cambio de cama.
                            '1' => 200, // cambio de cama ordenado exitosamente.
                            '2' => 202, // cambio de cama aceptado, pero requiere que sea autorizado por Supervisión de Enfermería.
                            default => 400  // Valor inesperado
                        };

                        $datos = [
                            'estado' => $httpStatus,
                            'mensaje' => $res[0]->mensaje
                        ];

                        $response->getBody()->write(json_encode($datos));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus($httpStatus);
                        
                    } catch(\PDOException $e) {
                        $datos = array('estado' => 500, 'mensaje' => $e->getMessage());
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

    // ENVIAR UN PACIENTE A QUIRÓFANO
    public function enviarQuirofano(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosQx          = json_decode($json);
   
        $idCamaOrigen           = $datosQx->idCamaOrigen ?? null;
        $idCamaDestino          = $datosQx->idCamaDestino ?? null;
        $idUsuario              = $datosQx->idUsuario ?? null;
        $idServicio             = $datosQx->idServicio ?? null;
        $reservarCama           = $datosQx->reservarCama ?? null;
        $idAplicacion           = $datosQx->idAplicacion ?? null;

        //debug_log2('idCamaOrigen: '.$idCamaOrigen.' - idCamaDestino: '.$idCamaDestino.' - idUsuario'.$idUsuario.' - idServicio:'.$idServicio.' - reservarCama: '.$reservarCama.' - idAplicacion: '.$idAplicacion);

        $error = 0;
        $datos = array();

        if($idCamaOrigen == ''){ $error ++; }
        if($idCamaDestino == ''){ $error ++; } 
        if($idUsuario == ''){ $error ++; }
        if($idServicio == ''){ $error ++;}
        if($reservarCama == ''){ $error ++;}
        if($idAplicacion == ''){ $error ++;}

        if ($error > 0) {
            $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if(!isset($tokenAcceso[0])){
            //acceso denegado. No envió el token de acceso
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
            
        if(verificarToken($tokenAcceso[0]) === false){
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);            
        }

        try {
            $db = getConeccionCAB();
            
            $db->beginTransaction();
            
            $sql = 'EXEC enviarQuirofano 
                        @idCamaOrigen = :idCamaOrigen,
                        @idCamaDestino = :idCamaDestino,
                        @idUsuario = :idUsuario,
                        @idServicio = :idServicio,
                        @reservarCama = :reservarCama,
                        @idAplicacion = :idAplicacion';
                    
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam("idCamaOrigen", $idCamaOrigen);
            $stmt->bindParam("idCamaDestino", $idCamaDestino);
            $stmt->bindParam("idUsuario", $idUsuario);
            $stmt->bindParam("idServicio", $idServicio);
            $stmt->bindParam("reservarCama", $reservarCama);
            $stmt->bindParam("idAplicacion", $idAplicacion);
            $stmt->execute();
            
            $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $stmt = null;  // Cierra el statement explícitamente
            
            // Validar que el procedimiento retornó resultados
            if (empty($res)) {
                throw new \Exception('El procedimiento no retornó resultados');
            }
            
            $estado                 = (int)$res[0]->estado;
            $mensaje                = $res[0]->mensaje ?? 'Sin mensaje';
            $idInternacion          = isset($res[0]->idInternacion) ? (int)$res[0]->idInternacion : null;
            $paciCodigo             = isset($res[0]->paciCodigo) ? (int)$res[0]->paciCodigo : null;
            $dni                    = isset($res[0]->dni) ? $res[0]->dni : null;
            $nombreUsuario          = isset($res[0]->nombreUsuario) ? (int)$res[0]->nombreUsuario : null;
            $idHabitacionOrigen     = isset($res[0]->idHabitacionOrigen) ? (int)$res[0]->idHabitacionOrigen : null;
            $mensajeReserva         = isset($res[0]->mensajeReserva) ? (int)$res[0]->mensajeReserva : null;

            debug_log2('estado: '.$estado .' - Mensaje: '.$mensaje.' - idInternacion: '. $idInternacion.' - paciCodigo: '.$paciCodigo.' - dni: '.$dni.' - nombreUsuario: '.$nombreUsuario.' - idHabitacionOrigen: '.$idHabitacionOrigen);
            
            // Si el procedimiento devolvió error (estado = 0)
            if ($estado === 0) {
                // Revierte la transacción
                $db->rollBack();
                $db = null;
                
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => $mensaje
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }


            
            // debug_log2('idCamaOrigen: '.$idCamaOrigen .' - idCamaDestino: '.$idCamaDestino.' - idInternacion: '. $idInternacion.' - paciCodigo: '.$paciCodigo.' - dni: '.$dni);

            // PASO 2: En Markey cambio de cama al paciente a la cama destino.
            require_once '../class/Markey.php';
            $datosCamas = new \Markey;

            if($datosCamas->cambiarCama($idCamaOrigen, $idCamaDestino, $idInternacion, $paciCodigo, $dni) <> 1){
                $db->rollBack();
                $db = null;
                
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => 'No se pudo cambiar de cama al paciente en Markey. Endpoint: cambiarCama'
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            // PASO 3: En Markey cambio el estado de la cama origen según corresponda.
            if($reservarCama == 1){
                if($datosCamas->reservarCama($idCamaOrigen, $mensajeReserva) <> 1){
                    $db->rollBack();
                    $db = null;
                    
                    $datos = array(
                            'estado' => 0, 
                            'mensaje' => 'No se pudo cambiar el estado de la cama origen en Markey. Endpoint: cambiarEstado'
                        );
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                }
            }else{
                if($datosCamas->cambiarEstado($idCamaOrigen, 4) <> 1){
                    $db->rollBack();
                    $db = null;
                    
                    $datos = array(
                            'estado' => 0, 
                            'mensaje' => 'No se pudo cambiar el estado de la cama origen en Markey. Endpoint: cambiarEstado'
                        );
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                }
            }
            
            


            
            // Si todo está bien, confirma la transacción
            $db->commit();
            $db = null;


            // PASO 4:  Actualizar el estado de las camas de la habitación. Se ejecuta fuera de la transaccion.
            // este paso se ejecuta solo para la cama origen, porque el estado podría cambiar dependiendo de si hay tareas de limpieza o reparación.
            

            if ($this->camasActualizarEstados($idHabitacionOrigen, $dni, $nombreUsuario, $idServicio) <> 1) {       
                //debug_log2('Error en funcion camasActualizarEstados(): idCama: ' . $idCama . ' - idHabitacion: ' . $idHabitacion . ' - dni: ' . $dni . ' - nombreUsuario: ' . $usuario . ' - idServicio: ' . $idServicio);            
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => 'Ocurrió un error al actualizar el estado de las camas de esta habitación. La operación se canceló.'
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            // else{
            //     debug_log2('Función camasActualizarEstados ejecutada correctamente: idCama: ' . $idCama . ' - idHabitacion: ' . $idHabitacion . ' - dni: ' . $dni . ' - nombreUsuario: ' . $nombreUsuario . ' - idServicio: ' . $idServicio);            
            // }
            
            
            $datos = array(
                    'estado' => 1, 
                    'mensaje' => 'El paciente fue enviado a Quirófano correctamente.'
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            // Cualquier otro error
            if ($db && $db->inTransaction()) {
                $db->rollBack();
                $db = null; 
            }
            
            $datos = array(
                    'estado' => 0, 
                    'mensaje' => 'Error: ' . $e->getMessage()
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);                       
        }

        
    }
   
    
//______________ RESERVAS ________________________________________________________________________

    
    // RESERVAS VER UNA
    public function reservasVerUna(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');      
        $idCama = $request->getQueryParams()['idCama'] ?? null;  

        $datos = array();

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                $sql = 'EXEC reservas_ver @idCama = :idCama';
                try {
                    $db = getConeccionCAB(); 
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':idCama', $idCama);
                    $stmt->execute();
                    $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    $db = null;

                    foreach($resultado as $reserva){
                        $r = new \stdClass();
                        $r->idReserva               = (int)$reserva->idReserva;
                        $r->idCama                  = $reserva->idCama;
                        $r->fechaReserva            = $reserva->fechaReserva;
                        $r->tdocCodigo              = $reserva->tdocCodigo;
                        $r->tdocDescripcion         = $reserva->tdocDescripcion;
                        $r->nroDocumento            = $reserva->nroDocumento;
                        $r->nombrePaciente          = $reserva->nombrePaciente;
                        $r->motivo                  = $reserva->motivo;
                        $r->reservadaPorDni         = $reserva->reservadaPorDni;
                        $r->reservadaPorNombre      = $reserva->reservadaPorNombre;
                        $r->fechaCancelada          = $reserva->fechaCancelada;
                        $r->canceladaPorDni         = $reserva->canceladaPorDni;
                        $r->canceladaPorNombre      = $reserva->canceladaPorNombre;
                        $r->idMotivoFinReserva      = $reserva->idMotivoFinReserva;
                        $r->idSolicitudCambio       = $reserva->idSolicitudCambio;
                        

                        array_push($datos,$r);
                        unset($r);
                    }

                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                } catch(\PDOException $e) {
                    $datos = array(
                        'estado' => 0,
                        'mensaje' => $e->getMessage()
                    );
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

    // RESERVAS CREAR
    public function reservaCrear(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosReserva   = json_decode($json);
   
        $idCama             = $datosReserva->idCama ?? null;
        $tdocCodigo         = $datosReserva->tdocCodigo ?? null;
        $nroDocumento       = $datosReserva->nroDocumento ?? null;
        $nombrePaciente     = $datosReserva->nombrePaciente ?? null;
        $motivo             = $datosReserva->motivo ?? null;
        $idUsuario          = $datosReserva->idUsuario ?? null;
        $idAplicacion       = $datosReserva->idAplicacion ?? null;

       // debug_log2('idCama:'.$idCama.' - tdocCodigo:'.$tdocCodigo.' - nroDocumento:'.$nroDocumento.' - nombrePaciente:'.$nombrePaciente.' - motivo:'.$motivo.' - idUsuario:'.$idUsuario.' - idAplicacion:'.$idAplicacion);

        $error = 0;
        $datos = array();

        if($idCama == ''){ $error ++; }
        if($tdocCodigo == ''){ $error ++; }
        if($nroDocumento == ''){ $error ++; }
        if($nombrePaciente == ''){ $error ++; }
        if($motivo == ''){ $error ++; }
        if($idUsuario == ''){ $error ++; }
        if($idAplicacion == ''){ $error ++; }

        if ($error > 0) {
            $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if(!isset($tokenAcceso[0])){
            //acceso denegado. No envió el token de acceso
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
            
        if(verificarToken($tokenAcceso[0]) === false){
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);            
        }

        try {
            $db = getConeccionCAB();
            
            $db->beginTransaction();
            
            $sql = 'EXEC reservas_crear @idCama = :idCama,
                        @tdocCodigo = :tdocCodigo,
                        @nroDocumento = :nroDocumento,
                        @nombrePaciente = :nombrePaciente,
                        @motivo = :motivo,
                        @idUsuario = :idUsuario,
                        @idAplicacion = :idAplicacion';
                    
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam("idCama", $idCama);
            $stmt->bindParam("tdocCodigo", $tdocCodigo);
            $stmt->bindParam("nroDocumento", $nroDocumento);
            $stmt->bindParam("nombrePaciente", $nombrePaciente);
            $stmt->bindParam("motivo", $motivo);
            $stmt->bindParam("idUsuario", $idUsuario);
            $stmt->bindParam("idAplicacion", $idAplicacion);
            $stmt->execute();
            
            $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $stmt = null;  // Cierra el statement explícitamente
            
            // Validar que el procedimiento retornó resultados
            if (empty($res)) {
                throw new \Exception('El procedimiento no retornó resultados');
            }
            
            $estado = (int)$res[0]->estado;
            $mensaje = $res[0]->mensaje ?? 'Sin mensaje';
            
            // Si el procedimiento devolvió error (estado = 0)
            if ($estado === 0) {
                // Revierte la transacción
                $db->rollBack();
                $db = null;
                
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => $mensaje
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            
            // PASO 2: Reservo la cama en Markey
            $observacion = 'Reservada para '.$nombrePaciente;
            require_once '../class/Markey.php';
            $Markey = new \Markey;
            $resultadoReservarCama = $Markey->reservarCama($idCama, $observacion);

            if($resultadoReservarCama == 0){
                // ocurrió algún error.
                $db->rollBack();
                $db = null;
                    
                $datos = array(
                    'estado' => 0, 
                    'mensaje' => 'Error al intentar reservar la cama en Markey. Función: reservarCama. Endpoint: cambiarEstado'
                );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            
            // Si todo está bien, confirma la transacción
            $db->commit();
            $db = null;
            
            $datos = array(
                    'estado' => 1, 
                    'mensaje' => 'La cama fue reservada correctamente.'
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            // Cualquier otro error
            if ($db && $db->inTransaction()) {
                $db->rollBack();
                $db = null; 
            }
            
            $datos = array(
                    'estado' => 0, 
                    'mensaje' => 'Error: ' . $e->getMessage()
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);                       
        }
    }
 
    // RESERVAS CANCELAR
    public function reservaCancelar(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosReserva   = json_decode($json);
   
        $idReserva          = $datosReserva->idReserva ?? null;
        $idMotivo           = $datosReserva->idMotivo ?? null;
        $idUsuario          = $datosReserva->idUsuario ?? null;
        $idAplicacion       = $datosReserva->idAplicacion ?? null;
        $idServicio         = $datosReserva->idServicio ?? null;

       // debug_log2('idReserva:'.$idReserva.' - idMotivo:'.$idMotivo.' - idUsuario:'.$idUsuario.' - idAplicacion:'.$idAplicacion.' - idServicio:'.$idServicio);

        $error = 0;
        $datos = array();

        if($idReserva == ''){ $error ++; }
        if($idMotivo == ''){ $error ++; }
        if($idUsuario == ''){ $error ++; }
        if($idAplicacion == ''){ $error ++; }
        if($idServicio == ''){ $error ++; }

        if ($error > 0) {
            $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if(!isset($tokenAcceso[0])){
            //acceso denegado. No envió el token de acceso
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
            
        if(verificarToken($tokenAcceso[0]) === false){
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);            
        }

        try {
            $db = getConeccionCAB();
            
            $db->beginTransaction();
            
            $sql = 'EXEC reservas_cancelar 
                        @idReserva = :idReserva,                        
                        @idMotivo = :idMotivo,
                        @idUsuario = :idUsuario,
                        @idAplicacion = :idAplicacion';
                    
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam("idReserva", $idReserva);
            $stmt->bindParam("idMotivo", $idMotivo);
            $stmt->bindParam("idUsuario", $idUsuario);
            $stmt->bindParam("idAplicacion", $idAplicacion);
            $stmt->execute();
            
            $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $stmt = null;  // Cierra el statement explícitamente
            
            // Validar que el procedimiento retornó resultados
            if (empty($res)) {
                throw new \Exception('El procedimiento no retornó resultados');
            }
            
            $estado     = (int)$res[0]->estado;
            $mensaje    = $res[0]->mensaje ?? 'Sin mensaje';
            $idCama     = isset($res[0]->idCama) ? (int)$res[0]->idCama : null; // $res[0]->idCama podría no existir o ser null.
            //debug_log2('estado: '.$estado .' - Mensaje: '.$mensaje.' - idCama: '. $idCama );
            
            // Si el procedimiento devolvió error (estado = 0)
            if ($estado === 0) {
                // Revierte la transacción
                $db->rollBack();
                $db = null;
                
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => $mensaje
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }



            // PASO 2: En Markey cambio el estado de la cama a disponible
            require_once '../class/Markey.php';
            $datosCamas = new \Markey;
            if($datosCamas->cambiarEstado($idCama, 1) <> 1){
                $db->rollBack();
                $db = null;
                
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => 'No se pudo cambiar el estado de la cama en Markey. Endpoint: cambiarEstado'
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            
            // Si todo está bien, confirma la transacción
            $db->commit();
            $db = null;


            // PASO 3:  Actualizar el estado de las camas de la habitación. Se ejecuta fuera de la transaccion.
            // este paso se ejecuta porque el estado podría cambiar dependiendo de si hay tareas de limpieza o reparación.
            // obtengo los datos necesarios para ejecutar la función camasActualizarEstados()

            $db3 = getConeccionCAB();                             
            $sql3 = 'EXEC ObtenerDatosUsuarioYHabitacionDesdeReserva @idReserva = :idReserva, @idUsuario = :idUsuario';
            
            $stmt3 = $db3->prepare($sql3);
            $stmt3->bindParam(":idReserva", $idReserva);
            $stmt3->bindParam(":idUsuario", $idUsuario);
            $stmt3->execute();
            $res3 = $stmt3->fetchAll(\PDO::FETCH_OBJ);
            
            // guardo los valores devueltos por el procedimiento almacenado ObtenerDatosUsuarioYHabitacion
            $dni            = $res3[0]->dni;
            $nombreUsuario  = $res3[0]->nombreUsuario;
            $idHabitacion   = $res3[0]->idHabitacion;
            
            //debug_log2('idCama: ' . $idCama . ' - idHabitacion: ' . $idHabitacion . ' - dni: ' . $dni . ' - nombreUsuario: ' . $nombreUsuario . ' - idServicio: ' . $idServicio);            

            if ($this->camasActualizarEstados($idHabitacion, $dni, $nombreUsuario, $idServicio) <> 1) {       
                //debug_log2('Error en funcion camasActualizarEstados(): idCama: ' . $idCama . ' - idHabitacion: ' . $idHabitacion . ' - dni: ' . $dni . ' - nombreUsuario: ' . $usuario . ' - idServicio: ' . $idServicio);            
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => 'Ocurrió un error al actualizar el estado de las camas de esta habitación. La operación se canceló.'
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            // else{
            //     debug_log2('Función camasActualizarEstados ejecutada correctamente: idCama: ' . $idCama . ' - idHabitacion: ' . $idHabitacion . ' - dni: ' . $dni . ' - nombreUsuario: ' . $nombreUsuario . ' - idServicio: ' . $idServicio);            
            // }
            
            
            $datos = array(
                    'estado' => 1, 
                    'mensaje' => 'La reserva fue cancelada correctamente.'
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            // Cualquier otro error
            if ($db && $db->inTransaction()) {
                $db->rollBack();
                $db = null; 
            }
            
            $datos = array(
                    'estado' => 0, 
                    'mensaje' => 'Error: ' . $e->getMessage()
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);                       
        }
    }

    

//______________ TAREAS __________________________________________________________________________

    // VER LISTA DE TAREAS
    public function tareas(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $parametros     = $request->getQueryParams();
        $idTipoTarea    = $parametros['idTipoTarea'];        

        $datos = array();

        // verifico que haya recibido el tokenAcceso
        if(!isset($tokenAcceso[0])){
            $datos = array('estado' => 0,'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verifico si el token enviado es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // verifico que recibí todos los parámetros
        if($idTipoTarea == ''){
            $datos = array('estado' => 0,'mensaje' => 'Faltan parámetros obligatorios.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // obtengo la lista de tareas solicitada
        $sql = 'EXEC tareas_ver @idTipoTarea = :idTipoTarea';
        try {
            $db = getConeccionCAB(); 
            $stmt = $db->prepare($sql);
            $stmt->bindParam("idTipoTarea", $idTipoTarea);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $tarea){
                $t = new \stdClass();
                
                $t->idTarea             = (int)$tarea->idTarea;
                $t->tipoTarea           = $tarea->tipoTarea;
                $t->fecha               = $tarea->fecha;
                $t->idCama              = (int)$tarea->idCama;
                $t->cama                = $tarea->cama;
                $t->habitacion          = $tarea->habitacion;
                $t->piso                = $tarea->piso;
                $t->camaEnAislamiento   = (int)$tarea->camaEnAislamiento;
                $t->estadoCama          = $tarea->estadoCama;
                $t->sexoPaciente        = $tarea->sexoPaciente;
                $t->solicitadaPorDni    = $tarea->solicitadaPorDni;
                $t->solicitadaPorNombre = $tarea->solicitadaPorNombre;
                $t->idServicioSolicita  = (int)$tarea->idServicioSolicita;
                $t->nombreServicio      = $tarea->nombreServicio;
                $t->iniciada            = $tarea->iniciada;
                $t->iniciadaPorDni      = $tarea->iniciadaPorDni;
                $t->iniciadaPorNombre   = $tarea->iniciadaPorNombre;
                $t->cancelada           = $tarea->cancelada;
                $t->canceladaPorDni     = $tarea->canceladaPorDni;
                $t->canceladaPorNombre  = $tarea->canceladaPorNombre;
                $t->idEstadoTarea       = (int)$tarea->idEstadoTarea;
                $t->estado              = $tarea->estado;

                // si es una tarea de reparacion, agrego los campos propios de una tarea de reparación
                if($idTipoTarea == 2){
                    $t->idReparacion        = (int)$tarea->idReparacion;
                    $t->reparacion          = $tarea->reparacion;
                    $t->idCategoria         = (int)$tarea->idCategoria;
                    $t->categoria           = $tarea->categoria;
                    $t->inhabilitaHab       = (int)$tarea->inhabilitaHab;
                    $t->limpiezaPosterior   = (int)$tarea->limpiezaPosterior;
                    $t->bloqueaCama         = (int)$tarea->bloqueaCama;
                    $t->idPrioridad         = (int)$tarea->idPrioridad;
                    $t->prioridad           = $tarea->prioridad;
                    $t->ticket              = $tarea->ticket;

                    // muestro el detalle del ticket.
                    $con = 'select JSON_ARRAYAGG(
                                JSON_OBJECT(
                                    "id_detalle", id_detalle,
                                    "usuario", concat(u.nombre, " " , u.apellido ),
                                    "fecha", fecha,
                                    "detalle", detalle
                                )
                            ) as detalle
                            from tk_detalles td
                                left join usuarios u on td.id_usuario = u.id_usuario 
                            where id_ticket = :idTicket 
                            order by id_detalle';
                    $db2 = getConneccionMySql(); 
                    $stmt2= $db2->prepare($con);
                    $stmt2->bindParam("idTicket", $tarea->ticket);
                    $stmt2->execute();
                    $res = $stmt2->fetchAll(\PDO::FETCH_OBJ);
                    $db2 = null;

                    foreach($res as $tk){
                        $t->detalleTicket = $tk->detalle ? json_decode($tk->detalle) : null;
                    }                    
                }

                array_push($datos,$t);
                unset($t);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        
    }

    // VER LISTA DE TAREAS DE UNA CAMA
    public function tareasCama(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $parametros     = $request->getQueryParams();
        $idTipoTarea    = $parametros['idTipoTarea'];        
        $idCama         = $parametros['idCama'];

        $datos = array();

        // verifico que haya recibido el tokenAcceso
        if(!isset($tokenAcceso[0])){
            $datos = array('estado' => 0,'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verifico si el token enviado es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // verifico que recibí todos los parámetros
        if(($idTipoTarea == '') || ($idCama == '')){
            $datos = array('estado' => 0,'mensaje' => 'Faltan parámetros obligatorios.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // obtengo la lista de tareas de la cama
        $sql = 'EXEC tareasCama_ver @idTipoTarea = :idTipoTarea, @idCama = :idCama';
        try {
            $db = getConeccionCAB(); 
            $stmt = $db->prepare($sql);
            $stmt->bindParam("idTipoTarea", $idTipoTarea);
            $stmt->bindParam("idCama", $idCama);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $tarea){
                $t = new \stdClass();
                
                $t->idTarea             = (int)$tarea->idTarea;
                $t->tipoTarea           = $tarea->tipoTarea;
                $t->fecha               = $tarea->fecha;
                $t->idCama              = (int)$tarea->idCama;
                $t->cama                = $tarea->cama;
                $t->habitacion          = $tarea->habitacion;
                $t->piso                = $tarea->piso;
                $t->camaEnAislamiento   = (int)$tarea->camaEnAislamiento;
                $t->estadoCama          = $tarea->estadoCama;
                $t->sexoPaciente        = $tarea->sexoPaciente;
                $t->solicitadaPorDni    = $tarea->solicitadaPorDni;
                $t->solicitadaPorNombre = $tarea->solicitadaPorNombre;
                $t->idServicioSolicita  = (int)$tarea->idServicioSolicita;
                $t->nombreServicio      = $tarea->nombreServicio;
                $t->iniciada            = $tarea->iniciada;
                $t->iniciadaPorDni      = $tarea->iniciadaPorDni;
                $t->iniciadaPorNombre   = $tarea->iniciadaPorNombre;
                $t->cancelada           = $tarea->cancelada;
                $t->canceladaPorDni     = $tarea->canceladaPorDni;
                $t->canceladaPorNombre  = $tarea->canceladaPorNombre;
                $t->idEstadoTarea       = (int)$tarea->idEstadoTarea;
                $t->estado              = $tarea->estado;

                // si es una tarea de reparacion, agrego los campos propios de una tarea de reparación
                if($idTipoTarea == 2){
                    $t->idReparacion        = (int)$tarea->idReparacion;
                    $t->reparacion          = $tarea->reparacion;
                    $t->idCategoria         = (int)$tarea->idCategoria;
                    $t->categoria           = $tarea->categoria;
                    $t->inhabilitaHab       = (int)$tarea->inhabilitaHab;
                    $t->limpiezaPosterior   = (int)$tarea->limpiezaPosterior;
                    $t->bloqueaCama         = (int)$tarea->bloqueaCama;
                    $t->idPrioridad         = (int)$tarea->idPrioridad;
                    $t->prioridad           = $tarea->prioridad;
                    $t->ticket              = $tarea->ticket;

                    // muestro el detalle del ticket.
                    $con = 'select JSON_ARRAYAGG(
                                JSON_OBJECT(
                                    "id_detalle", id_detalle,
                                    "usuario", concat(u.nombre, " " , u.apellido ),
                                    "fecha", fecha,
                                    "detalle", detalle
                                )
                            ) as detalle
                            from tk_detalles td
                                left join usuarios u on td.id_usuario = u.id_usuario 
                            where id_ticket = :idTicket 
                            order by id_detalle';
                    $db2 = getConneccionMySql(); 
                    $stmt2= $db2->prepare($con);
                    $stmt2->bindParam("idTicket", $tarea->ticket);
                    $stmt2->execute();
                    $res = $stmt2->fetchAll(\PDO::FETCH_OBJ);
                    $db2 = null;

                    foreach($res as $tk){
                        $t->detalleTicket = $tk->detalle ? json_decode($tk->detalle) : null;
                    }                    
                }

                array_push($datos,$t);
                unset($t);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        
    }

    // VER UNA TAREA
    public function tareasCama_VerUna(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idTarea = $args['idTarea'] ?? null;

        $datos = array();

        // verifico que haya recibido el tokenAcceso
        if(!isset($tokenAcceso[0])){
            $datos = array('estado' => 0,'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verifico si el token enviado es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // verifico que recibí todos los parámetros
        if($idTarea == ''){
            $datos = array('estado' => 0,'mensaje' => 'Faltan parámetros obligatorios.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // obtengo la tarea solicitada
        $sql = 'EXEC tareasCama_verUna @idTarea = :idTarea';
        try {
            $db = getConeccionCAB(); 
            $stmt = $db->prepare($sql);
            $stmt->bindParam("idTarea", $idTarea);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $tarea){
                $t = new \stdClass();
                
                $t->idTarea             = (int)$tarea->idTarea;
                $t->tipoTarea           = $tarea->tipoTarea;
                $t->fecha               = $tarea->fecha;
                $t->idCama              = (int)$tarea->idCama;
                $t->cama                = $tarea->cama;
                $t->habitacion          = $tarea->habitacion;
                $t->piso                = $tarea->piso;
                $t->camaEnAislamiento   = (int)$tarea->camaEnAislamiento;
                $t->estadoCama          = $tarea->estadoCama;
                $t->sexoPaciente        = $tarea->sexoPaciente;
                $t->solicitadaPorDni    = $tarea->solicitadaPorDni;
                $t->solicitadaPorNombre = $tarea->solicitadaPorNombre;
                $t->idServicioSolicita  = (int)$tarea->idServicioSolicita;
                $t->nombreServicio      = $tarea->nombreServicio;
                $t->iniciada            = $tarea->iniciada;
                $t->iniciadaPorDni      = $tarea->iniciadaPorDni;
                $t->iniciadaPorNombre   = $tarea->iniciadaPorNombre;
                $t->cancelada           = $tarea->cancelada;
                $t->canceladaPorDni     = $tarea->canceladaPorDni;
                $t->canceladaPorNombre  = $tarea->canceladaPorNombre;
                $t->idEstadoTarea       = (int)$tarea->idEstadoTarea;
                $t->estado              = $tarea->estado;

                // si es una tarea de reparacion, agrego los campos propios de una tarea de reparación
                if($tarea->idTipoTarea == 2){
                    $t->idReparacion        = (int)$tarea->idReparacion;
                    $t->reparacion          = $tarea->reparacion;
                    $t->idCategoria         = (int)$tarea->idCategoria;
                    $t->categoria           = $tarea->categoria;
                    $t->inhabilitaHab       = (int)$tarea->inhabilitaHab;
                    $t->limpiezaPosterior   = (int)$tarea->limpiezaPosterior;
                    $t->bloqueaCama         = (int)$tarea->bloqueaCama;
                    $t->idPrioridad         = (int)$tarea->idPrioridad;
                    $t->prioridad           = $tarea->prioridad;
                    $t->ticket              = $tarea->ticket;
                    $t->urlRecibido         = 'http://10.99.8.107/soporte/index.php?d='.encriptar($tarea->ticket . '2');
                    $t->urlEnviado          = 'http://10.99.8.107/soporte/index.php?d='.encriptar($tarea->ticket . '0');

                    // muestro el detalle del ticket.
                    $con = 'select JSON_ARRAYAGG(
                                JSON_OBJECT(
                                    "id_detalle", id_detalle,
                                    "usuario", concat(u.nombre, " " , u.apellido ),
                                    "fecha", DATE_FORMAT(fecha, "%Y-%m-%d %H:%i:%s"),
                                    "detalle", detalle
                                )
                            ) as detalle
                            from tk_detalles td
                                left join usuarios u on td.id_usuario = u.id_usuario 
                            where id_ticket = :idTicket 
                            order by id_detalle';
                    $db2 = getConneccionMySql(); 
                    $stmt2= $db2->prepare($con);
                    $stmt2->bindParam("idTicket", $tarea->ticket);
                    $stmt2->execute();
                    $res = $stmt2->fetchAll(\PDO::FETCH_OBJ);
                    $db2 = null;

                    foreach($res as $tk){
                        $t->detalleTicket = $tk->detalle ? json_decode($tk->detalle) : null;
                    }                    
                }

                array_push($datos,$t);
                unset($t);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        
    }

    // INICIAR, FINALIZAR O CANCELAR UNA TAREA
    public function tareaIniciarFinalizarCancelar_old(Request $request, Response $response, $args){
        /*
        Esta función ejecuta la acción de iniciar, finalizar o cancelar una tarea de limpieza o de reparación.
        1) modifica los datos en la base de datos CAB (tablas camasMarkey y camas)
        2) modifica el estado del ticket asociado a la tarea en el caso de tareas de reparación
        3) Si la tarea es finalizada o cancelada, actualiza el estado de las camas de la habitación ejecutando la función camasActualizarEstados

        Si una tarea de limpieza (no iniciada) es cancelada, la cama pasa a estado disponible, a menos que haya tareas de reparación pendientes.
        */
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosTarea     = json_decode($json);
   
        $idTarea   = $datosTarea->idTarea ?? null;
        $idUsuario = $datosTarea->idUsuario ?? null;
        $idServicio = $datosTarea->idServicio ?? null;
        $accion    = $datosTarea->accion ?? null;
        
        $error = 0;
        $datos = array();

        if($idTarea == ''){ $error ++; }
        if($idUsuario == ''){ $error ++; } 
        if($idServicio == ''){ $error ++; } 
        if($accion == ''){ $error ++; } 
        if($accion <> 'iniciar' && $accion <> 'finalizar' && $accion <> 'cancelar'){ $error ++; } 
        

        // si no envió el tokenAcceso
        if(!isset($tokenAcceso[0])){            
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Si el token enviado no es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // si los parámetros recibos no son válidos
        if ($error > 0) {
            $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // PASO 1: Ejecuto la acción: 1ro. en la base de datos CAB (tablas camasMarkey y camas)
        try {
            $db = getConeccionCAB();
            
            $db->beginTransaction();
            
            // Actualizo la tarea al estado correspondiente.
            $sql = 'EXEC tarea_iniciarFinalizarCancelar
                        @idTarea = :idTarea,
                        @idUsuario = :idUsuario,
                        @idServicio = :idServicio, 
                        @accion = :accion';
                        // envio el servicio solo para validar que sea un servicio existente y que el usuario está asociado a ese servicio.
                    
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(":idTarea", $idTarea);
            $stmt->bindParam(":idUsuario", $idUsuario);
            $stmt->bindParam(":idServicio", $idServicio);
            $stmt->bindParam(":accion", $accion);
            $stmt->execute();
            
            $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
            
            // Validar que el procedimiento retornó resultados
            if (empty($res)) {
                throw new \Exception('El procedimiento no retornó resultados');
            }
            
            // guardo los valores devueltos por el procedimiento almacenado tarea_iniciarFinalizarCancelar
            $estado = (int)$res[0]->estado;
            $mensaje = $res[0]->mensaje ?? 'Sin mensaje';
            
            // Si el procedimiento devolvió error (estado = 0)
            if ($estado === 0) {
                // Revierte la transacción
                $db->rollBack();
                $db = null;
                
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => $mensaje
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }else{
                $idTicket = $estado; // número de ticket asignado a la tarea. Podría ser 1 en caso de tareas de limpieza.
            }
            
            // Si el procedimiento fue exitoso (estado <> 0)
            
            // PASO 2: Cambio el estado del ticket según corresponda. (campo id_estado. 2= En proceso, 4= Resuelto, 10 = Cerrado)
            
            if($idTicket > 1){ 
                // es una tarea de reparación y debo modificar el ticket correspodiente
                $db2 = getConneccionMySql();                             
                $sql2 = 'CALL actualizarTicket(:id_ticket, :accion)';
                
                $stmt2 = $db2->prepare($sql2);
                $stmt2->bindParam(":id_ticket", $idTicket);
                $stmt2->bindParam(":accion", $accion);
                $stmt2->execute();
                $res2 = $stmt2->fetch(\PDO::FETCH_ASSOC);

                if($res2['estado'] == 0){
                    // Ocurrió un error al actualizar el ticket
                    $db->rollBack();
                    $db = null;
                    $db2 = null;
                    
                    $datos = array(
                            'estado' => 0, 
                            'mensaje' => $res2['mensaje']
                        );
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                }
            } 
            
            // /*  
            //     Si estoy finalizando una tarea de reparación y la reparación requiere limpieza posterior. Debo crear una tarea de limpieza y poner el campo limpia = 0
            //     Si $idTicket > 1 es una tarea de reparación.
            //     Para esto necesito obtener los datos de la tarea y la reparación realizada.
            // */

            // if($idTicket > 1){
            //     // es una tarea de reparación, así que busco si la misma necesita limpieza posterior.
            //     $sql4 = 'EXEC tareasCama_verUna @idTarea = :idTarea';
            //     $stmt4 = $db->prepare($sql4);
            //     $stmt4->bindParam(":idTarea", $idTarea);
            //     $stmt4->execute();
            //     $res4 = $stmt4->fetchAll(\PDO::FETCH_OBJ);
                
            //     $necesitaLimpieza = (int)$res4[0]->limpiezaPosterior;
            //     if($necesitaLimpieza == 1){
            //         // creo la tarea de limpieza y coloco la cama en estado 4 y el campo limpia = 0
            //     }
            // }


            // Hasta acá todo se ejecutó bien, así que confirmo la transacción
            $db->commit();
            $db = null;

            switch ($accion) {
                case 'iniciar':
                    $accionMensaje = 'inició';
                    break;
                case 'finalizar':
                    $accionMensaje = 'finalizó';
                    break;
                case 'cancelar':
                    $accionMensaje = 'canceló';
                    break;                
            }

            
            // DE ACÁ EN ADALENTAO LO HAGO FUERA DE LA TRANSACCIÓN. Si ocurre un error no podré revertir lo hecho anteriormente.

            // PASO 3: si la acción es finalizar o cancelar, actualizo el estado de las camas de esta habitación ejecutando la función camasActualizarEstado()
            if(($accion == 'finalizar') or ($accion == 'cancelar')){
                // obtengo los datos necesarios para ejecutar la función: idHabitacion, dni y nombreUsuario
                $db3 = getConeccionCAB();                             
                $sql3 = 'EXEC ObtenerDatosUsuarioYHabitacion @idTarea = :idTarea, @idUsuario = :idUsuario';
                
                $stmt3 = $db3->prepare($sql3);
                $stmt3->bindParam(":idTarea", $idTarea);
                $stmt3->bindParam(":idUsuario", $idUsuario);
                $stmt3->execute();
                $res3 = $stmt3->fetchAll(\PDO::FETCH_OBJ);
            
                
                // guardo los valores devueltos por el procedimiento almacenado tarea_iniciarFinalizarCancelar
                $dni = $res3[0]->dni;
                $nombreUsuario = $res3[0]->nombreUsuario;
                $idHabitacion = $res3[0]->idHabitacion;
                
                if ($this->camasActualizarEstados($idHabitacion, $dni, $nombreUsuario, $idServicio) <> 1) {                   
                    $datos = array(
                            'estado' => 0, 
                            'mensaje' => 'Ocurrió un error al actualizar el estado de las camas de esta habitación. La operación se canceló.'
                        );
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                } 
            }

            
            $datos = array(
                    'estado' => 1, 
                    'mensaje' => 'La tarea se '.$accionMensaje.' correctamente.'
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
             
            
            
        } catch (\Exception $e) {
            // Cualquier otro error
            if ($db && $db->inTransaction()) {
                $db->rollBack();
                $db = null; 
            }
            
            $datos = array(
                    'estado' => 0, 
                    'mensaje' => 'Error: ' . $e->getMessage()
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);                       
        }      
    }
    
    public function tareaIniciarFinalizarCancelar(Request $request, Response $response, $args){
        /*
        Esta función ejecuta la acción de iniciar, finalizar o cancelar una tarea de limpieza o de reparación.
        1) modifica los datos en la base de datos CAB (tablas camasMarkey y camas)
        2) modifica el estado del ticket asociado a la tarea en el caso de tareas de reparación
        3) Si la tarea es finalizada o cancelada, actualiza el estado de las camas de la habitación ejecutando la función camasActualizarEstados
        
        IMPORTANTE:
        1)  Si una tarea de limpieza (no iniciada) es cancelada, la cama pasa a estado disponible, a menos que haya tareas de reparación pendientes, en tal caso pasa a reparación.
        2)  Si la tarea que estoy finalinzando es una tarea de reparación y la reparación necesita limpieza posterior, 
            entonces debo crear una tarea de limpieza e indicar que la cama está sucia (limpia = 0).
            Esto hará que la cama pase al estado reparación.
            Si además la reparación afecta la habitación (campo inhabilitaHab) debo crear una tarea de limpieza para cada cama de la habitación.
        */
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosTarea     = json_decode($json);
   
        $idTarea   = $datosTarea->idTarea ?? null;
        $idUsuario = $datosTarea->idUsuario ?? null;
        $idServicio = $datosTarea->idServicio ?? null;
        $accion    = $datosTarea->accion ?? null;

        //debug_log2('------------------------------');
        //debug_log2('tareaIniciarFinalizarCancelar - idTarea: ' . $idTarea . ' - idUsuario: ' . $idUsuario . ' - idServicio: ' . $idServicio . ' - accion: ' . $accion);
        
        $error = 0;
        $datos = array();

        if($idTarea == ''){ $error ++; }
        if($idUsuario == ''){ $error ++; } 
        if($idServicio == ''){ $error ++; } 
        if($accion == ''){ $error ++; } 
        if($accion <> 'iniciar' && $accion <> 'finalizar' && $accion <> 'cancelar'){ $error ++; } 
        

        // si no envió el tokenAcceso
        if(!isset($tokenAcceso[0])){            
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Si el token enviado no es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // si los parámetros recibos no son válidos
        if ($error > 0) {
            $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }




        // PASO 1: Ejecuto la acción: 1ro. en la base de datos CAB (tablas camasMarkey y camas)
        try {
            $db = getConeccionCAB();
            
            $db->beginTransaction();
            
            // Actualizo la tarea al estado correspondiente.
            $sql = 'EXEC tarea_iniciarFinalizarCancelar
                        @idTarea = :idTarea,
                        @idUsuario = :idUsuario,
                        @idServicio = :idServicio, 
                        @accion = :accion';
                        // envio el servicio solo para validar que sea un servicio existente y que el usuario está asociado a ese servicio.
                    
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(":idTarea", $idTarea);
            $stmt->bindParam(":idUsuario", $idUsuario);
            $stmt->bindParam(":idServicio", $idServicio);
            $stmt->bindParam(":accion", $accion);
            $stmt->execute();
            
            $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
            
            // Validar que el procedimiento retornó resultados
            if (empty($res)) {
                throw new \Exception('El procedimiento no retornó resultados');
            }
            
            // guardo los valores devueltos por el procedimiento almacenado tarea_iniciarFinalizarCancelar
            $estado             = (int)$res[0]->estado;
            $mensaje            = $res[0]->mensaje ?? 'Sin mensaje';
            $idTicket           = (int)$res[0]->idTicket;
            $idTipoTarea        = (int)$res[0]->idTipoTarea;
            // el sp devuelve estas variables pero no las uso.
            // $limpiezaPosterior  = (int)$res[0]->limpiezaPosterior;
            // $inhabilitaHab      = (int)$res[0]->inhabilitaHab;
            $idCama             = (int)$res[0]->idCama;
            
            //debug_log2('Resultado de SP tarea_iniciarFinalizarCancelar - estado: ' . $estado . ' - mensaje: ' . $mensaje . ' - idTicket: ' . $idTicket . ' - idTipoTarea: ' . $idTipoTarea.' - idCama: ' . $idCama);

            // Si el procedimiento devolvió error (estado = 0)
            if ($estado === 0) {
                // Revierte la transacción
                $db->rollBack();
                $db = null;
                
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => $mensaje
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
                        
            
            // PASO 2: Cambio el estado del ticket según corresponda. (campo id_estado. 2= En proceso, 4= Resuelto, 10 = Cerrado)
            
            if($idTipoTarea == 2){ 
                // es una tarea de reparación y debo modificar el ticket correspodiente
                $db2 = getConneccionMySql();                             
                $sql2 = 'CALL actualizarTicket(:id_ticket, :accion)';
                
                $stmt2 = $db2->prepare($sql2);
                $stmt2->bindParam(":id_ticket", $idTicket);
                $stmt2->bindParam(":accion", $accion);
                $stmt2->execute();
                $res2 = $stmt2->fetch(\PDO::FETCH_ASSOC);

                if($res2['estado'] == 0){
                    // Ocurrió un error al actualizar el ticket
                    $db->rollBack();
                    $db = null;
                    $db2 = null;
                    
                    debug_log2('Error al actualizar el ticket - idTicket: ' . $idTicket . ' - accion: ' . $accion . ' - mensaje: ' . $res2['mensaje']);
                    $datos = array(
                            'estado' => 0, 
                            'mensaje' => $res2['mensaje']
                        );
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                }
            }


            // Hasta acá todo se ejecutó bien, así que confirmo la transacción
           // debug_log2('Confirmando transacción');
            $db->commit();
            $db = null;

            switch ($accion) {
                case 'iniciar':
                    $accionMensaje = 'inició';
                    break;
                case 'finalizar':
                    $accionMensaje = 'finalizó';
                    break;
                case 'cancelar':
                    $accionMensaje = 'canceló';
                    break;                
            }

            
            // DE ACÁ EN ADALENTAO LO HAGO FUERA DE LA TRANSACCIÓN. Si ocurre un error no podré revertir lo hecho anteriormente.

            // PASO 3: si la acción es finalizar o cancelar, actualizo el estado de las camas de esta habitación ejecutando la función camasActualizarEstado()
            if(($accion == 'finalizar') or ($accion == 'cancelar')){
                // obtengo los datos necesarios para ejecutar la función: idHabitacion, dni y nombreUsuario
                $db3 = getConeccionCAB();                             
                $sql3 = 'EXEC ObtenerDatosUsuarioYHabitacion @idTarea = :idTarea, @idUsuario = :idUsuario';
                
                $stmt3 = $db3->prepare($sql3);
                $stmt3->bindParam(":idTarea", $idTarea);
                $stmt3->bindParam(":idUsuario", $idUsuario);
                $stmt3->execute();
                $res3 = $stmt3->fetchAll(\PDO::FETCH_OBJ);
            
                
                // guardo los valores devueltos por el procedimiento almacenado ObtenerDatosUsuarioYHabitacion
                $dni = $res3[0]->dni;
                $nombreUsuario = $res3[0]->nombreUsuario;
                $idHabitacion = $res3[0]->idHabitacion;
                
                if ($this->camasActualizarEstados($idHabitacion, $dni, $nombreUsuario, $idServicio) <> 1) {       
                    //debug_log2('Error en funcion camasActualizarEstados(): idCama: ' . $idCama . ' - idHabitacion: ' . $idHabitacion . ' - dni: ' . $dni . ' - nombreUsuario: ' . $nombreUsuario . ' - idServicio: ' . $idServicio);            
                    $datos = array(
                            'estado' => 0, 
                            'mensaje' => 'Ocurrió un error al actualizar el estado de las camas de esta habitación. La operación se canceló.'
                        );
                    $response->getBody()->write(json_encode($datos));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                }
                //else{
                //    debug_log2('Función camasActualizarEstados ejecutada correctamente: idCama: ' . $idCama . ' - idHabitacion: ' . $idHabitacion . ' - dni: ' . $dni . ' - nombreUsuario: ' . $nombreUsuario . ' - idServicio: ' . $idServicio);            
                //}
            }

            
            $datos = array(
                    'estado' => 1, 
                    'mensaje' => 'La tarea se '.$accionMensaje.' correctamente.'
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
             
            
            
        } catch (\Exception $e) {
            // Cualquier otro error
            if ($db && $db->inTransaction()) {
                $db->rollBack();
                $db = null; 
            }

            //debug_log2('Error en tareaIniciarFinalizarCancelar: ' . $e->getMessage());
            
            $datos = array(
                    'estado' => 0, 
                    'mensaje' => 'Error: Ha ocurrido un error al ejecutar esta acción. Por favor, intentá nuevamente en unos minutos. Si el error persiste, contactá al administrador del sistema.'
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);                       
        }      
    }

    // CREAR UNA TAREA DE REPARACIÓN. 
    public function tareaReparacionCrear(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $json           = $request->getBody();
        $datosTarea     = json_decode($json);
   
        $idCama         = $datosTarea->idCama ?? null;
        $idUsuario      = $datosTarea->idUsuario ?? null;
        $idServicio     = $datosTarea->idServicio ?? null;
        $idReparacion   = $datosTarea->idReparacion ?? null;
        $detalle        = $datosTarea->detalle ?? null; // se usa en el ticket que se crea asociado a esta tarea de reparación.

        // debug_log2('------------------------------');
        // debug_log2('tareaReparacionCrear - idCama: ' . $idCama . ' - idUsuario: ' . $idUsuario . ' - idServicio: ' . $idServicio . ' - idReparacion: ' . $idReparacion);
        // debug_log2('detalle: ' . $detalle);

        $error = 0;
        $datos = array();

        if($idCama == ''){ $error ++; }
        if($idUsuario == ''){ $error ++; } 
        if($idServicio == ''){ $error ++; }
        if($idReparacion == ''){ $error ++; }
        if($detalle == ''){ $error ++; }

        // si no envió el tokenAcceso
        if(!isset($tokenAcceso[0])){            
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Si el token enviado no es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // si los parámetros recibos no son válidos
        if ($error > 0) {
            $datos = array('estado' => 0, 'mensaje' => 'Los parámetros recibidos no son válidos.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // 1) Creo la tarea 2) creo el ticket 3) actualizo la tarea para guardar el número de ticket que se le asignó.
        try {
            $db = getConeccionCAB();
            
            $db->beginTransaction();
            
            $sql = 'EXEC tareaReparacionCrear
                        @idCama = :idCama,
                        @idUsuario = :idUsuario,
                        @idServicio = :idServicio,
                        @idReparacion = :idReparacion';
                    
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(":idCama", $idCama);
            $stmt->bindParam(":idUsuario", $idUsuario);
            $stmt->bindParam(":idServicio", $idServicio);
            $stmt->bindParam(":idReparacion", $idReparacion);
            $stmt->execute();
            
            $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $stmt = null;  // Cierra el statement explícitamente
            
            // Validar que el procedimiento retornó resultados
            if (empty($res)) {
                throw new \Exception('El procedimiento no retornó resultados');
            }
            
            $estado = (int)$res[0]->estado;
            $mensaje = $res[0]->mensaje ?? 'Sin mensaje';
            
            // Si el procedimiento devolvió error (estado = 0)
            if ($estado === 0) {
                // Revierte la transacción
                $db->rollBack();
                $db = null;
                
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => $mensaje
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }else{
                $idTarea    = $estado; // número de tarea creada
                $cama       = $res[0]->cama;
                $servicio   = $res[0]->servicio;
                $usuario    = $res[0]->usuario;
                $dni        = $res[0]->dni;
                $idHabitacion = $res[0]->idHabitacion;
            }
            
            // Si el procedimiento fue exitoso (estado <> 0)
            
            // PASO 2: Creo el ticket
            
            // obtener datos de la reparacion para armar el detalle del ticket.
            $sql = 'EXEC reparaciones_verUna @idReparacion = :idReparacion';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(":idReparacion", $idReparacion);
            $stmt->execute();
            $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $stmt = null;  // Cierra el statement explícitamente
            
            $tituloTicket = 'CAMA ' . $cama. ' - Reparacion solicitada';
            $prioridad = $res[0]->idPrioridad; 
            $detalle .= PHP_EOL . '-- Reparación solicitada por: ' . $usuario . PHP_EOL . '-- Servicio: ' . $servicio;
            $idDeptoDestino = 2; // departamento de mantenimiento
            $creadoPor = 108; // usuario Enfermeria CAB
            $idDeptoOrigen = 17; // depto Enfermería
            $idOrigen = 1; // Mesa de Ayuda
        
            $db2 = getConneccionMySql();

            $sql2 = 'CALL crearTicket(:p_id_depto_destino, :p_creado_por, :p_id_depto_origen, :p_titulo, :p_id_prioridad, :p_id_origen, :p_detalle)';
            
            $stmt2 = $db2->prepare($sql2);
            
            

            $stmt2->bindParam(":p_id_depto_destino",  $idDeptoDestino); // Mantenimiento
            $stmt2->bindParam(":p_creado_por", $creadoPor); // usuario Enfermeria CAB
            $stmt2->bindParam(":p_id_depto_origen", $idDeptoOrigen); // depto Enfermería
            $stmt2->bindParam(":p_titulo", $tituloTicket);
            $stmt2->bindParam(":p_id_prioridad", $prioridad);
            $stmt2->bindParam(":p_id_origen", $idOrigen); // Mesa de Ayuda
            $stmt2->bindParam(":p_detalle", $detalle);

            
            $stmt2->execute();
            $res2 = $stmt2->fetch(\PDO::FETCH_ASSOC);
            $stmt2 = null;  // Cierra el statement explícitamente

            if($res2['estado'] == 0){
                // Ocurrió un error al crear el ticket
                $db->rollBack();
                $db = null;
                $db2 = null;
                
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => $res2['mensaje']
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }else{
                $idTicketCreado = $res2['estado']; // el SP devuelve el id del ticket creado en caso de éxito.
            }

            
            
            // PASO 3: Actualizo la tarea para guardar el número de ticket que se le asignó.
            //debug_log2('Actualizando tarea '. $idTarea .' con idTicket: ' . $idTicketCreado);
            
            $sql3 = 'EXEC tareaReparacionActualizarTicketAsignado @idTarea = :idTarea, @idTicket = :idTicket';
            $stmt3 = $db->prepare($sql3);
            $stmt3->bindParam(":idTarea", $idTarea);
            $stmt3->bindParam(":idTicket", $idTicketCreado);
            $stmt3->execute();
            $res3 = $stmt3->fetchAll(\PDO::FETCH_OBJ);
            $stmt3 = null;  // Cierra el statement explícitamente

            //debug_log('Resultado de SP tareaReparacionActualizarTicketAsignado', $res3);
            
            // Validar que el procedimiento retornó resultados
            if (empty($res3)) {
                throw new \Exception('El procedimiento tareaReparacionActualizarTicketAsignado no retornó resultados');
            }
            
            $estado = (int)$res3[0]->estado;
            $mensaje = $res3[0]->mensaje ?? 'Sin mensaje';
            
            //debug_log2('Resultado de SP tareaReparacionActualizarTicketAsignado - estado: ' . $estado . ' - mensaje: ' . $mensaje);

            // Si el procedimiento devolvió error (estado = 0)
            if ($estado === 0) {
                // Revierte la transacción
                $db->rollBack();
                $db = null;
                
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => $mensaje
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }            
                                   
            // Si todo está bien, confirma la transacción
            //debug_log2('Confirmando transacción');
            $db->commit();
            $db = null;

            // PASO 4: Actualizar Estado de las camas de la habitación.
            if ($this->camasActualizarEstados($idHabitacion, $dni, $usuario, $idServicio) <> 1) {       
                debug_log2('Error en funcion camasActualizarEstados(): idCama: ' . $idCama . ' - idHabitacion: ' . $idHabitacion . ' - dni: ' . $dni . ' - nombreUsuario: ' . $usuario . ' - idServicio: ' . $idServicio);            
                $datos = array(
                        'estado' => 0, 
                        'mensaje' => 'Ocurrió un error al actualizar el estado de las camas de esta habitación. La operación se canceló.'
                    );
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }else{
                debug_log2('Función camasActualizarEstados ejecutada correctamente: idCama: ' . $idCama . ' - idHabitacion: ' . $idHabitacion . ' - dni: ' . $dni . ' - nombreUsuario: ' . $usuario . ' - idServicio: ' . $idServicio);            
            }

            $datos = array(
                    'estado' => 1, 
                    'mensaje' => 'La tarea se creo correctamente.'
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            // Cualquier otro error
            if ($db && $db->inTransaction()) {
                $db->rollBack();
                $db = null; 
            }
            
            $datos = array(
                    'estado' => 0, 
                    'mensaje' => 'Error: ' . $e->getMessage()
                );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);                       
        }      
    }

    // CATEGORIAS DE REPARACIÓN Y REPARACIONES
    public function categoriasReparaciones(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        
        $datos = array();

        // verifico que haya recibido el tokenAcceso
        if(!isset($tokenAcceso[0])){
            $datos = array('estado' => 0,'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verifico si el token enviado es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // obtengo la lista de reparacines y categorías de reparación
        $sql = 'EXEC categoriasReparaciones_ver';
        try {
            $db = getConeccionCAB(); 
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $cat){
                $c = new \stdClass();
                
                $c->idCategoria  = (int)$cat->idCategoria;
                $c->categoria    = $cat->categoria;
                $c->reparaciones = $cat->reparaciones ? json_decode($cat->reparaciones) : null;

                array_push($datos,$c);
                unset($c);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        
    }

    // REPARACIÓN
    public function reparaciones(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        
        $datos = array();

        // verifico que haya recibido el tokenAcceso
        if(!isset($tokenAcceso[0])){
            $datos = array('estado' => 0,'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verifico si el token enviado es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // obtengo la lista de reparacines y categorías de reparación
        $sql = 'EXEC reparaciones_ver';
        try {
            $db = getConeccionCAB(); 
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $cat){
                $c = new \stdClass();
                
                $c->idCategoria  = (int)$cat->idCategoria;
                $c->categoria    = $cat->categoria;
                $c->reparaciones = $cat->reparaciones ? json_decode($cat->reparaciones) : null;

                array_push($datos,$c);
                unset($c);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        
    }



//______________ PACIENTES________________________________________________________________________


    // VER UN PACIENTE DE MARKEY
    public function paciente(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $parametros     = $request->getQueryParams();
        $tdocCodigo     = $parametros['tdocCodigo'] ?? null;        
        $nroDocumento   = $parametros['nroDocumento'] ?? null;
        $error = 0;
        $datos = array();

        // verifico que haya recibido el tokenAcceso
        if(!isset($tokenAcceso[0])){
            $datos = array('estado' => 0,'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verifico si el token enviado es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // verifico que recibí todos los parámetros
        if($tdocCodigo == ''){ $error ++; }
        if($nroDocumento == ''){ $error ++; }

        if($error > 0){
            $datos = array('estado' => 0,'mensaje' => 'Faltan parámetros obligatorios.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // busco el paciente en la base de datos de Markey. Accedo por el servidor 10.99.8.5
        $sql = 'EXEC PacienteBuscar @tdocCodigo = :tdocCodigo, @documentoPaciente = :documentoPaciente';
        try {
            $db = getConeccionCAB05(); 
            $stmt = $db->prepare($sql);
            $stmt->bindParam("tdocCodigo", $tdocCodigo);
            $stmt->bindParam("documentoPaciente", $nroDocumento);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $pac){
                $p = new \stdClass();
                
                $p->paciCodigo          = (int)$pac->persCodigo;
                $p->apellido            = $pac->apellidoPaciente;
                $p->nombre              = $pac->nombrePaciente;
                $p->fechaNacimiento     = $pac->fechaNacimiento;
                $p->persSexo            = $pac->persSexo;
                $p->sexo                = $pac->sexo;
                $p->tdocCodigo          = (int)$pac->tdocCodigo;
                $p->tdocDescripcion     = $pac->tdocDescripcion;
                $p->nroDocumento        = $pac->persNroDocumento;

                array_push($datos,$p);
                unset($p);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }    

    // VER TIPOS DE DOCUMENTOS
    public function tiposDocumentos(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $datos = array();

        // verifico que haya recibido el tokenAcceso
        if(!isset($tokenAcceso[0])){
            $datos = array('estado' => 0,'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verifico si el token enviado es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // obtengo los tipos de documentos
        $sql = 'EXEC tiposDocumentos_ver';
        try {
            $db = getConeccionCAB(); 
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $tdoc){
                $t = new \stdClass();
                $t->tdocCodigo = (int)$tdoc->tdocCodigo;
                $t->tdocDescripcion = $tdoc->tdocDescripcion;
                
                array_push($datos,$t);
                unset($t);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }


//______________ CIRUGÍAS ________________________________________________________________________

    // VER LAS CIRUGÍAS DEL DÍA DESDE MARKEY
    public function cirugiasdeldia(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');        
        $datos = array();

        // verifico que haya recibido el tokenAcceso
        if(!isset($tokenAcceso[0])){
            $datos = array('estado' => 0,'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verifico si el token enviado es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // busco las cirugías del día
        $sql = 'EXEC MKY_ObtenerCirugiasDelDia';
        try {
            $db = getConeccionCAB05(); 
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $cir){
                $c = new \stdClass();
                
                $c->tdocCodigo      = $cir->tdocCodigo;
                $c->nroDocumento    = $cir->nroDocumento;
                $c->paciente        = $cir->paciente;
                $c->cirujano        = $cir->cirujano;

                array_push($datos,$c);
                unset($c);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    } 



    
//______________ TICKETS ________________________________________________________________________

    // VER UN TICKET
    public function ticket_ver(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $idTicket = $args['idTicket'] ?? null;

        $datos = array();

        // verifico que haya recibido el tokenAcceso
        if(!isset($tokenAcceso[0])){
            $datos = array('estado' => 0,'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verifico si el token enviado es correcto
        if(verificarToken($tokenAcceso[0]) === false){                
            // acceso denegado
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // verifico que recibí todos los parámetros
        if($idTicket == ''){
            $datos = array('estado' => 0,'mensaje' => 'Faltan parámetros obligatorios.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // obtengo el ticket
        $sql = 'select * from tickets where id_ticket = :idTicket';
        try {
            $db = getConneccionMySql(); 
            $stmt = $db->prepare($sql);
            $stmt->bindParam("idTicket", $idTicket);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            foreach($resultado as $ti){
                $t = new \stdClass();
                
                $t->idTicket = (int)$ti->id_ticket;
                $t->fecha = $ti->fecha;
                $t->titulo = $ti->titulo; 

                array_push($datos,$t);
                unset($t);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch(\PDOException $e) {
            $datos = array(
                'estado' => 0,
                'mensaje' => $e->getMessage()
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        
    }

//______________ PRUEBAS ________________________________________________________________________

    // DEBUG
    public function debug(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');

        debug_log("Token", $tokenAcceso);
        
        $datos = array();
        $datos = array('estado' => 1,'mensaje' => 'Debug OK');
        $response->getBody()->write(json_encode($datos));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);        
    }

}
