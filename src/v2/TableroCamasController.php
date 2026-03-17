<?php
namespace App\V2;

require_once __DIR__ . '/../Common/helpers.php'; // Importa las funciones comunes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;



class TableroCamasController
{
    
    //ACTUALIZAR CAMAS DESDE MARKEY
    public function actualizarCamasMarkey(Request $request, Response $response, $args){
        /* Obtiene los datos de las camas de Markey desde el servicio web de Markey ObtenerCamas.
        Los datos son guardados en la tabla camasMarkey en la base de datos CAB.
        */

        $tokenAcceso    = $request->getHeader('TokenAcceso');

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
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

                    // la cantidade camas es la misma pero verifico si son las mismas camas (verifico el idCama). Si hay diferencias, borro todo y cargo de nuevo
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

                // $datos = array('estado' => 0, 'cantidadCAmasMarkey' => $cantCamasMarkey, 'cantidadCamasCAB' => $cantCamasCab, 'borrarTodo' => $borrarTodo, 'diferencias' => $diferencias, 'resultado' => $resultado);
                // $response->getBody()->write(json_encode($datos));
                // return $response->withHeader('Content-Type', 'application/json')->withStatus(200);


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
                            
                            if($cama->idEstado == 6){
                                $c->idReserva                 = (int)$cama->idReserva;
                                $c->reservaFecha              = ($cama->fechaReserva <> '') ? date_format(date_create($cama->fechaReserva), 'd-m-Y H:i:s') : '';
                                $c->reservaMotivo             = $cama->reservaMmotivo;
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

                    }catch(PDOException $e) {
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
                    $sql = 'EXEC camasVerUna @idCama = :idCama, @idServicio = :idServicio';
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
                        $c->soloAltaMedica                  = (int)$cama->soloAltaMedica;

                        // json con los aislamientos del paciente.
                        if (!empty($cama->aislamientos)) {
                            $c->aislamientos = json_decode($cama->aislamientos, true);
                        }else{
                            $c->aislamientos = array();
                        };

                        $c->tareasBloqueanHabitacion = (int)$cama->tareasBloqueanHabitacion;
                        $c->tareasBloqueanCama = (int)$cama->tareasBloqueanCama;
                        
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

    // CAMAS ACTUALIZAR ESTADOS
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

    // CAMAS ACTUALIZAR ESTADOS
    private function camasActualizarEstados($idHabitacion, $dni, $nombreUsuario, $idServicio){
        // Esta función actualiza los estados de las camas de estas habitación y devuelve 1 si el proceso fue exitoso o 0 si hubo algún error.

        // 1- Obtengo los datos de las camas de esta habitación.
        require_once '../class/Markey.php';
        $datosCamas = new \Markey;
        $camas = $datosCamas->getCamasHabitacion($idHabitacion);
        if(is_null($camas)){
            return 0; // no existe la habitación o no hay camas en la habitación indicada.
        }else{
            // Recorro los datos de esta habitación y actualizo el estado de cada cama.

            foreach($camas as $cama){
                // 3- Verifico que la cama esté disponible, en reparación o en limpieza. Si la cama tiene otro estado no lo cambio
                if(($cama->id_estado == 1) or ($cama->id_estado == 3) or ($cama->id_estado == 4)){
                    // guardo el estado actual de la cama
                    $estadoActual = $cama->id_estado;

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
                                                EXEC alertasNueva '.$cama->id_cama.', 13, @mensaje = @mensaje OUTPUT';
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
                    

                    // ACTUALIZO EL ESTADO DE LA CAMA EN MARKEY SOLO SI EL NUEVO ESTADO ES DISTINTO AL ESTADO ACTUAL.
                    if($estadoActual <> $nuevoEstado){                                    
                        // si la cama es virtual solo puedo asignarle el estado disponible
                        if($cama->tipocama == 'V'){
                            // llamo al servicio de Markey para cambiar el estado de la cama a disponible
                            if($datosCamas->cambiarEstado($cama->id_cama, 1) == 1){
                                

                                // limpio las alertas históricas de la cama
                                limpiarAlertasHistoriasCama($cama->id_cama); // función definida en src/Common/helpers.php
                            }
                        }else{
                            // llamo al servicio de Markey para cambiar el estado de la cama a $nuevo_estado
                            if($datosCamas->cambiarEstado($cama->id_cama, $nuevoEstado) == 1){

                                // si la cama pasa a disponible, limpio el historial de alertas.
                                if($nuevoEstado == 1){
                                    // limpio el historial de alertas
                                    limpiarAlertasHistoriasCama($cama->id_cama); // función definida en src/Common/helpers.php
                                    
                                    // crear alerta de cama disponible para admisión
                                    crearAlertaCamaDisponible($cama->id_cama); // función definida en src/Common/helpers.php
                                }
                            }
                        }

                        // registro el cambio de estado en la bitácora de la cama.
                        bitacoraRegistrarCambioEstadoCama($cama->id_cama, 10, $dni, $nombreUsuario);
                    }
                }
            }

            // actualizo las camas en las tablas locales camasMarkey y camas para que queden sincronizadas con los datos de Markey.
            // para esto vuelvo a llamar al servicio de Markey para obtener las camas de esta habitación con los datos actualizados.
            
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
                $stmt->bindParam('fechaIngresoInstitucion',$cama->fecha_ingreso_institucion);
                $stmt->bindParam('fechaIngresoCama',$cama->fecha_ingreso_cama);
                $stmt->bindParam('cobertura',$cama->cobertura);
                $stmt->bindParam('fantasia',$cama->fantasia);
                $stmt->bindParam('plan',$cama->plan);
                $stmt->bindParam('nroAfiliado',$cama->nro_afiliado);
                $stmt->bindParam('fechaAltaMedica',$cama->fecha_alta_medica);
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

        
        return 1; // proceso finalizado exitosamente.  
            
    }

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
                            $s->fecha                 = date_format(date_create($solicitud->fecha), 'd-m-Y H:i:s');
                            $s->idCamaOrigen        = (int)$solicitud->idCamaOrigen;
                            $s->camaOrigen           = $solicitud->camaOrigen;
                            $s->idCamaDestino       = (int)$solicitud->idCamaDestino;
                            $s->camaDestino          = $solicitud->camaDestino;
                            $s->idMotivo             = (int)$solicitud->idMotivo;
                            $s->motivo                = $solicitud->motivo;
                            $s->idEstadoSolicitud   = (int)$solicitud->idEstadoSolicitud;
                            $s->estado                = $solicitud->estado;
                            $s->solicitadoPorDni    = $solicitud->solicitadoPorDni;
                            $s->solicitadoPorNombre = $solicitud->solicitadoPorNombre;
                            
                            if(is_null($solicitud->autorizadoFecha)){
                                $s->autorizadoFecha      = null;
                            }else{
                                $s->autorizadoFecha      = date_format(date_create($solicitud->autorizadoFecha), 'd-m-Y H:i:s');
                            }
                            
                            $s->autorizadoPorDni    = $solicitud->autorizadoPorDni;
                            $s->autorizadoPorNombre = $solicitud->autorizadoPorNombre;

                            if(is_null($solicitud->realizadoFecha)){
                                $s->realizadoFecha   = $solicitud->realizadoFecha;
                            }else{
                                $s->realizadoFecha   = date_format(date_create($solicitud->realizadoFecha), 'd-m-Y H:i:s');
                            }
                            
                            
                            $s->realizadoPorDni     = $solicitud->realizadoPorDni;
                            $s->realizadoPorNombre  = $solicitud->realizadoPorNombre;
                            if(is_null($solicitud->canceladoFecha)){
                                $s->canceladoFecha   = $solicitud->canceladoFecha;
                            }else{
                                $s->canceladoFecha   = date_format(date_create($solicitud->canceladoFecha), 'd-m-Y H:i:s');
                            }
                            
                            $s->canceladoPorDni     = $solicitud->canceladoPorDni;
                            $s->canceladoPorNombre  = $solicitud->canceladoPorNombre;

                            if(isset($s)){
                                array_push($datos,$s); //esto es igual a hacer esto $datos[] = $s;
                                unset($s);
                                
                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                            }else{
                                $datos = array('mensaje' => 'No hay una solicitud de cambio de cama para esta internación, que esté pendiente o autorizada.');
                                $response->getBody()->write(json_encode($datos));
                                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                            }
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
}