<?php
namespace App\V2;

require_once __DIR__ . '/../Common/helpers.php'; // Importa las funciones comunes

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;



class TableroCamasController
{
    // VERSIÓN AUTORIZADA
    public function versionAutorizada(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $nro_version    = $request->getQueryParams()['nro_version'] ?? null;

        $error = 0;
        $datos = array();

        if($nro_version == ''){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    $sql = 'EXEC tc_versionAutorizada @nro_version = :nro_version';
                    try {
                        $db = getConeccionCAB(); 
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("nro_version", $nro_version);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        foreach($resultado as $version){
                            $datos = array(
                                'versionActual' => (int)$version->versionActual,
                                'autorizada' => $version->autorizada,
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

    // SERVICIOS - VER UNO
    public function serviciosVerUno(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $id_servicio    = $request->getQueryParams()['id_servicio'] ?? null;

        $error = 0;
        $datos = array();

        if($id_servicio == ''){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    $sql = 'EXEC tc_servicios_verUno @id_servicio = :id_servicio';
                    try {
                        $db = getConeccionCAB(); 
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("id_servicio", $id_servicio);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        foreach($resultado as $servicio){
                            $datos = array(
                                'id_servicio' => (int)$servicio->id_servicio,
                                'nombreServicio' => $servicio->nombreServicio,
                                'tipo_internacion' => (int)$servicio->tipo_internacion,
                                'cambioCama_areaCerrada' => (int)$servicio->cambioCama_areaCerrada,
                                'gestionaCamas' => (int)$servicio->gestionaCamas
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
    public function permisoModuloPaciente_ver(Request $request, Response $response, $args){
        $tokenAcceso    = $request->getHeader('TokenAcceso');
        $id_modulo      = $request->getQueryParams()['id_modulo'] ?? null;
        $id_servicio    = $request->getQueryParams()['id_servicio'] ?? null;

        $error = 0;
        $datos = array();

        if($id_modulo == ''){
            $error ++;
        }

        if($id_servicio == ''){
            $error ++;
        }

        if(isset($tokenAcceso[0])){
            if(verificarToken($tokenAcceso[0]) === true){                
                // acceso permitido
                if($error == 0){
                    $sql = 'EXEC tc_permisosModuloPaciente_ver @id_modulo = :id_modulo, @id_servicio = :id_servicio';
                    try {
                        $db = getConeccionCAB(); 
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam("id_modulo", $id_modulo);
                        $stmt->bindParam("id_servicio", $id_servicio);
                        $stmt->execute();
                        $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
                        $db = null;

                        foreach($resultado as $permiso){
                            $datos = array(
                                'id_permiso' => (int)$permiso->id_permiso,
                                'id_servicio' => (int)$permiso->id_servicio,
                                'nombreServicio' => $permiso->nombreServicio,
                                'id_modulo' => (int)$permiso->id_modulo,
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

    // OBTENER CAMAS
    public function obtenerCamas(Request $request, Response $response, $args){
        $tokenAcceso        = $request->getHeader('TokenAcceso');
        $id_servicio        = $request->getQueryParams()['id_servicio'] ?? null;
        $tareasPendientes   = $request->getQueryParams()['tareasPendientes'] ?? 0;
        $idEstado           = $request->getQueryParams()['idEstado'] ?? 0;

        $error = 0;
        $datos = array();

        if($id_servicio == ''){
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
                    
                    $sql = 'EXEC camasServicio_ver_V2 @idServicio = :idServicio, @tareasPendientes = :tareasPendientes, @idEstado = :idEstado';
                    try {
                        $db = getConnection();
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam('idServicio',$idServicio);
                        $stmt->bindParam('tareasPendientes',$tareasPendientes);
                        $stmt->bindParam('idEstado',$idEstado);
                        $stmt->execute();
                        $resultados = $stmt->fetchAll(PDO::FETCH_OBJ);
                        $db = null;                

                        $datos = array();

                        foreach($resultados as $cama){
                            $c = new stdClass();
                            $c->id_cama                         = (int)$cama->id_cama;
                            $c->cama                            = $cama->cama;
                            $c->id_habitacion                   = (int)$cama->id_habitacion;
                            $c->habitacion                      = $cama->habitacion;
                            $c->piso                            = $cama->piso;
                            $c->id_estado                       = (int)$cama->id_estado;
                            $c->estado                          = $cama->estado;
                            $c->color                           = $cama->color;
                            $c->observaciones                   = $cama->observaciones; // obs del estado. Ej. Reservada para...
                            $c->id_paciente                     = (int)$cama->id_paciente;
                            $c->nombre_paciente                 = $cama->nombre_paciente;
                            $c->apellido_paciente               = $cama->apellido_paciente;
                            $c->dni                             = $cama->dni;
                            $c->sexo                            = $cama->sexo;
                            if($cama->id_estado == 2){
                                if($cama->sexo == 'F'){
                                    $c->sexo_texto = 'MUJER';
                                }else{
                                    $c->sexo_texto = 'HOMBRE';
                                }
                            }else{
                                $c->sexo_texto = '';
                            };
                            
                            $c->fecha_ingreso_institucion       = ($cama->fecha_ingreso_institucion <> '') ? date_format(date_create($cama->fecha_ingreso_institucion), 'd-m-Y H:i:s') : '';
                            $c->fecha_ingreso_cama              = ($cama->fecha_ingreso_cama <> '') ? date_format(date_create($cama->fecha_ingreso_cama), 'd-m-Y H:i:s') : '';
                            $c->cobertura                       = $cama->cobertura;
                            $c->fantasia                        = $cama->fantasia;
                            $c->plan                            = $cama->plan;
                            $c->nro_afiliado                    = $cama->nro_afiliado;
                            $c->id_internacion                  = (int)$cama->id_internacion;
                            $c->fecha_alta_medica               = ($cama->fecha_alta_medica <> '') ? date_format(date_create($cama->fecha_alta_medica), 'd-m-Y H:i:s') : '';
                            $c->profesional_alta                = $cama->profesional_alta;
                            $c->tipo_alta_medica                = $cama->tipo_alta_medica;
                            $c->foto_paciente                   = $cama->foto_paciente;

                            //$c->foto_paciente = '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AAAATGVmdFVudEYjUmx0AAAAAAAAAAAAAAAAVG9wIFVudEYjUmx0AAAAAAAAAAAAAAAAU2NsIFVudEYjUHJjQFkAAAAAAAAAAAAQY3JvcFdoZW5QcmludGluZ2Jvb2wAAAAADmNyb3BSZWN0Qm90dG9tbG9uZwAAAAAAAAAMY3JvcFJlY3RMZWZ0bG9uZwAAAAAAAAANY3JvcFJlY3RSaWdodGxvbmcAAAAAAAAAC2Nyb3BSZWN0VG9wbG9uZwAAAAAAOEJJTQPtAAAAAAAQAEgAAAABAAIASAAAAAEAAjhCSU0EJgAAAAAADgAAAAAAAAAAAAA/gAAAOEJJTQQNAAAAAAAEAAAAHjhCSU0EGQAAAAAABAAAAB44QklNA/MAAAAAAAkAAAAAAAAAAAEAOEJJTScQAAAAAAAKAAEAAAAAAAAAAjhCSU0D9QAAAAAASAAvZmYAAQBsZmYABgAAAAAAAQAvZmYAAQChmZoABgAAAAAAAQAyAAAAAQBaAAAABgAAAAAAAQA1AAAAAQAtAAAABgAAAAAAAThCSU0D+AAAAAAAcAAA/////////////////////////////wPoAAAAAP////////////////////////////8D6AAAAAD/////////////////////////////A+gAAAAA/////////////////////////////wPoAAA4QklNBAAAAAAAAAIAADhCSU0EAgAAAAAAAgAAOEJJTQQwAAAAAAABAQA4QklNBC0AAAAAAAYAAQAAAAI4QklNBAgAAAAAABAAAAABAAACQAAAAkAAAAAAOEJJTQQeAAAAAAAEAAAAADhCSU0EGgAAAAADRQAAAAYAAAAAAAAAAAAAAUAAAADwAAAACABzAGkAbgBfAGYAbwB0AG8AAAABAAAAAAAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAPAAAAFAAAAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAAAAAAAEAAAAAEAAAAAAABudWxsAAAAAgAAAAZib3VuZHNPYmpjAAAAAQAAAAAAAFJjdDEAAAAEAAAAAFRvcCBsb25nAAAAAAAAAABMZWZ0bG9uZwAAAAAAAAAAQnRvbWxvbmcAAAFAAAAAAFJnaHRsb25nAAAA8AAAAAZzbGljZXNWbExzAAAAAU9i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Ym91dD0iIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtbG5zOnN0RXZ0PSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VFdmVudCMiIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIgeG1sbnM6ZGM9Imh0dHA6Ly9wdXJsLm9yZy9kYy9lbGVtZW50cy8xLjEvIiB4bWxuczpwaG90b3Nob3A9Imh0dHA6Ly9ucy5hZG9iZS5jb20vcGhvdG9zaG9wLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0iNEVEOTZFNUUyMUE4QzlFMTM5RjQ5NUNFREE4OTJGQzYiIHhtcE1NOkRvY3VtZW50SUQ9ImFkb2JlOmRvY2lkOnBob3Rvc2hvcDo4MjQ0NWViMy01NDFkLTgwNDktOGExNC1lZjQ0YzU2MWUwY2UiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6MGY1NGQ4NWYtMTRhOC1mNzQxLThkMTctYzI5MDYzZGIwNWMzIiB4bXA6Q3JlYXRvclRvb2w9IkFkb2JlIFBob3Rvc2hvcCBDUzYgKFdpbmRvd3MpIiB4bXA6Q3JlYXRlRGF0ZT0iMjAyMy0wMy0zMFQxNjo0MDoxOC0wMzowMCIgeG1wOk1vZGlmeURhdGU9IjIwMjMtMTAtMjZUMTU6MjE6MjUtMDM6MDAiIHhtcDpNZXRhZGF0YURhdGU9IjIwMjMtMTAtMjZUMTU6MjE6MjUtMDM6MDAiIGRjOmZvcm1hdD0iaW1hZ2UvanBlZyIgcGhvdG9zaG9wOkNvbG9yTW9kZT0iMyIgcGhvdG9zaG9wOklDQ1Byb2ZpbGU9IiI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOkZDRTc5RDYzMTIwREU4MTE4MjBCQzg3NjVGQTNBM0M2IiBzdFJlZjpkb2N1bWVudElEPSI0RUQ5NkU1RTIxQThDOUUxMzlGNDk1Q0VEQTg5MkZDNiIvPiA8eG1wTU06SGlzdG9yeT4gPHJkZjpTZXE+IDxyZGY6bGkgc3RFdnQ6YWN0aW9uPSJzYXZlZCIgc3RFdnQ6aW5zdGFuY2VJRD0ieG1wLmlpZDoxMTQ2MmFhYy0wNjVhLTA0NGMtOWM5NS1lZGMwNGU1OWNiYTQiIHN0RXZ0OndoZW49IjIwMjMtMTAtMjZUMTU6MjE6MjUtMDM6MDAiIHN0RXZ0OnNvZnR3YXJlQWdlbnQ9IkFkb2JlIFBob3Rvc2hvcCAyMS4xIChXaW5kb3dzKSIgc3RFdnQ6Y2hhbmdlZD0iLyIvPiA8cmRmOmxpIHN0RXZ0OmFjdGlvbj0ic2F2ZWQiIHN0RXZ0Omluc3RhbmNlSUQ9InhtcC5paWQ6MGY1NGQ4NWYtMTRhOC1mNzQxLThkMTctYzI5MDYzZGIwNWMzIiBzdEV2dDp3aGVuPSIyMDIzLTEwLTI2VDE1OjIxOjI1LTAzOjAwIiBzdEV2dDpzb2Z0d2FyZUFnZW50PSJBZG9iZSBQaG90b3Nob3AgMjEuMSAoV2luZG93cykiIHN0RXZ0OmNoYW5nZWQ9Ii8iLz4gPC9yZGY6U2VxPiA8L3htcE1NOkhpc3Rvcnk+IDxwaG90b3Nob3A6RG9jdW1lbnRBbmNlc3RvcnM+IDxyZGY6QmFnPiA8cmRmOmxpPjJCOENBMEUyN0JBNDU0ODBGMEZCMTk0M0I3M0VEQTgxPC9yZGY6bGk+IDwvcmRmOkJhZz4gPC9waG90b3Nob3A6RG9jdW1lbnRBbmNlc3RvcnM+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDw/eHBhY2tldCBl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';

                            $c->procedimientos_no_cumplidos     = (int)$cama->procedimientos_no_cumplidos;
                            $c->medicacion_no_programada        = (int)$cama->medicacion_no_programada;
                            $c->medicacion_no_aplicada          = (int)$cama->medicacion_no_aplicada;
                            
                            $c->aislamiento_contacto            = ($cama->aislamiento_contacto <> '') ? date_format(date_create($cama->aislamiento_contacto), 'd-m-Y H:i:s') : '';
                            $c->aislamiento_respiratorio        = ($cama->aislamiento_respiratorio <> '') ? date_format(date_create($cama->aislamiento_respiratorio), 'd-m-Y H:i:s') : '';
                            $c->aislamiento_gota                = ($cama->aislamiento_gota <> '') ? date_format(date_create($cama->aislamiento_gota), 'd-m-Y H:i:s') : '';                        
                            $c->aislamiento_neutropenico        = ($cama->aislamiento_neutropenico <> '') ? date_format(date_create($cama->aislamiento_neutropenico), 'd-m-Y H:i:s') : '';
                            $c->aislamiento_cd                  = ($cama->aislamiento_cd <> '') ? date_format(date_create($cama->aislamiento_cd), 'd-m-Y H:i:s') : '';
                            $c->aislamiento_sc                  = ($cama->aislamiento_sc <> '') ? date_format(date_create($cama->aislamiento_sc), 'd-m-Y H:i:s') : '';
                            $c->cama_en_aislamiento             = (int)$cama->cama_en_aislamiento;

                            $c->acompanante                     = (int)$cama->acompanante;
                            $c->observaciones_acompanante       = $cama->observaciones_acompanante;
                            $c->orden                           = (int)$cama->orden;
                            $c->cambioCamaPendiente             = (int)$cama->cambioCamaPendiente;
                            $c->alertas                         = (int)$cama->alertas;
                            $c->tareasPendientes                = (int)$cama->tareasPendientes;
                            
                            $c->altaProbable_fecha              = ($cama->altaProbable_fecha <> '') ? date_format(date_create($cama->altaProbable_fecha), 'd-m-Y H:i:s') : '';
                            $c->altaProbable_tipo               = ($cama->altaProbable_tipo <> '') ? $cama->altaProbable_tipo : '';
                            $c->altaProbable_dniUsuario         = (int)$cama->altaProbable_dniUsuario;
                            
                            if($cama->id_estado == 6){
                                $c->id_reserva                      = (int)$cama->id_reserva;
                                $c->fecha_reserva                   = ($cama->fecha_reserva <> '') ? date_format(date_create($cama->fecha_reserva), 'd-m-Y H:i:s') : '';
                                $c->reserva_motivo                  = $cama->reserva_motivo;
                                $c->reservada_por_dni               = (int)$cama->reservada_por_dni;
                                $c->reservada_por_nombre            = $cama->reservada_por_nombre;
                                $c->reserva_fecha_cancelada         = ($cama->reserva_fecha_cancelada <> '') ? date_format(date_create($cama->reserva_fecha_cancelada), 'd-m-Y H:i:s') : '';
                                $c->reserva_cancelada_por_dni       = (int)$cama->reserva_cancelada_por_dni;
                                $c->reserva_cancelada_por_nombre    = $cama->reserva_cancelada_por_nombre;
                                $c->reserva_paciente_dni            = $cama->reserva_paciente_dni;
                                $c->reserva_paciente_nombre         = $cama->reserva_paciente_nombre;
                                $c->id_motivo_fin_reserva           = (int)$cama->id_motivo_fin_reserva;
                                $c->reserva_id_solicitudCambio      = (int)$cama->reserva_id_solicitudCambio;
                            }else{
                                $c->reserva_motivo                  = '';
                                $c->fecha_reserva                   = '';
                                $c->reservada_por_dni               = 0;
                                $c->reservada_por_nombre            = '';
                                $c->id_reserva                      = 0;
                                $c->reserva_fecha_cancelada         = '';
                                $c->reserva_cancelada_por_dni       = 0;
                                $c->reserva_cancelada_por_nombre    = '';
                                $c->reserva_paciente_dni            = '';
                                $c->reserva_paciente_nombre         = '';
                                $c->id_motivo_fin_reserva           = 0;
                                $c->reserva_id_solicitudCambio      = 0;
                            }
                            
                            
                            

                            array_push($datos,$c);
                            unset($c);
                        }                  
                        
                        return $response->withStatus(200)
                                        ->withHeader('Content-Type', 'application/json')
                                        ->write(json_encode($datos));
                    }catch(PDOException $e) {
                        $datos =  '{"error": '.$e->getMessage() .'}';
                        return $response->withStatus(503)
                                        ->withHeader('Content-Type', 'application/json')
                                        ->write(json_encode($datos));
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