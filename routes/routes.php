<?php
use Slim\App;

return function (App $app) {
    // --------------------------
    // VERSION 1
    // --------------------------

    // NOTIFICACIONES
    $app->group('/push', function ($group) {
        $group->post('/enviarEnfermero', '\App\NotificacionesController:enviarEnfermero');
        $group->post('/enviarEnfermero_v2', '\App\NotificacionesController:enviarEnfermero_v2');
        $group->get('/ver', '\App\NotificacionesController:verNotificacionesEnfermero');
        $group->get('/verDieta', '\App\NotificacionesController:verNotificacionesDieta');
        $group->post('/leidaDieta', '\App\NotificacionesController:notificacionesDietaLeida');
        $group->post('/leida', '\App\NotificacionesController:notificacionesPushEnfermeroLeida');
        $group->post('/enviar', '\App\NotificacionesController:enviarNotificacion');
        $group->post('/enviarDev', '\App\NotificacionesController:enviarNotificacionDev');
        $group->get('/prueba', '\App\NotificacionesController:prueba');
    });


    // DISPOSITIVOS
    $app->group('/dispositivos', function ($group) {
        $group->get('/validar', '\App\DispositivosController:validarDispositivo');
        $group->post('/cerrarSesionDispositivo', '\App\DispositivosController:cerrarSesionDispositivo');
        $group->post('/cerrarSesionDispositivo_new', '\App\DispositivosController:cerrarSesionDispositivo_new');
        $group->post('/registrar', '\App\DispositivosController:registrarDispositivo');
        $group->post('/registrar_v2', '\App\DispositivosController:registrarDispositivo_v2');
        $group->get('/sesionIniciada', '\App\DispositivosController:verificarSesionIniciada');
    });

    // LABORATORIO
    $app->group('/tharsis', function ($group) {
        $group->post('/enviarOrden', '\App\LaboratorioController:enviarOrdenTharsis');
    });

    // PORTAL CAB
    $app->group('/portal', function ($group) {
        $group->get('/login', '\App\PortalController:loginPortal');
    });

    // REPORTES
    $app->group('/reportes', function ($group) {
        $group->get('/facturacionCoberturas', '\App\ReportesController:facturacionCoberturas');
        $group->get('/facturacionCoberturasDetalle', '\App\ReportesController:facturacionCoberturasDetalle');
        $group->get('/coberturas', '\App\ReportesController:coberturas');
        $group->get('/profesionales', '\App\ReportesController:profesionales');
        $group->get('/turnosIndicadores', '\App\ReportesController:turnosIndicadores');
        $group->get('/estudiosCardiologicosFacturados', '\App\ReportesController:estudiosCardiologicosFacturados');
        $group->get('/indicadoresconsultas', '\App\ReportesController:indicadoresConsultas');
    });

    // CLINICA - funciones varias
    $app->group('/clinica', function ($group) {
        $group->get('/fechahora', '\App\ClinicaController:fechaHoraCab');
    });

    // PORTAL EMPLEADOS
    $app->group('/empleados', function ($group) {
        $group->get('/{legajo}', '\App\EmpleadosController:verEmpleadoPorLegajo');
        $group->post('/crear', '\App\EmpleadosController:crearEmpleado');
        $group->post('/editarEmpleado', '\App\EmpleadosController:editarEmpleado');
    });




    // --------------------------
    // VERSION 2
    // --------------------------
    $app->group('/v2', function ($group) {

        // NOTIFICACIONES v2
        $group->group('/notificaciones', function ($g) {
            $g->get('/', '\App\V2\NotificacionesController_v2:verNotificaciones');
            $g->get('/{idNotificacion}', '\App\V2\NotificacionesController_v2:verUnaNotificacion');
            $g->post('/leida', '\App\V2\NotificacionesController_v2:marcarNotificacionLeida');
            $g->post('/enviar', '\App\V2\NotificacionesController_v2:enviarNotificacion');
            $g->get('/{idNotificacion}/logLeida', '\App\V2\NotificacionesController_v2:verLogLeida');

            // $g->post('/enviarEnfermero', '\App\NotificacionesController:enviarEnfermero');
            // $g->post('/enviarEnfermero_v2', '\App\NotificacionesController:enviarEnfermero_v2');
            // $g->get('/ver', '\App\NotificacionesController:verNotificacionesEnfermero');
            // $g->get('/verDieta', '\App\NotificacionesController:verNotificacionesDieta');
            // $g->post('/leidaDieta', '\App\NotificacionesController:notificacionesDietaLeida');
            // $g->post('/leida', '\App\NotificacionesController:notificacionesPushEnfermeroLeida');
            // $g->post('/enviar', '\App\NotificacionesController:enviarNotificacion');
            // $g->post('/enviarDev', '\App\NotificacionesController:enviarNotificacionDev');
        });

        // PRUEBAS v2
        $group->group('/pruebas', function ($g) {
            $g->post('/fechaSQL', '\App\V2\PruebasController:pruebaFechaSQL');
        });


        // DISPOSITIVOS v2
        $group->group('/dispositivos', function ($g) {
            $g->get('/validar', '\App\V2\DispositivosController_v2:validarDispositivo');
            $g->post('/registrar', '\App\V2\DispositivosController_v2:registrarDispositivo');
            $g->post('/cerrarSesionDispositivo', '\App\V2\DispositivosController_v2:cerrarSesionDispositivo');
            $g->get('/sesionIniciada', '\App\V2\DispositivosController_v2:verificarSesionIniciada');
        });

        // TABLERO DE CAMAS
        $group->group('/tablerocamas', function ($g) {
            // VER CAMAS
            $g->get('/actualizarCamasMarkey', '\App\V2\TableroCamasController:actualizarCamasMarkey');
            $g->get('/actualizarCamasMarkeyPrueba', '\App\V2\TableroCamasController:actualizarCamasMarkeyPrueba');
            $g->get('/camas', '\App\V2\TableroCamasController:obtenerCamas');
            $g->get('/verUnaCama', '\App\V2\TableroCamasController:verUnaCama');

            // APLICACION
            $g->get('/version', '\App\V2\TableroCamasController:versionAutorizada');
            $g->get('/horaServidor', '\App\V2\TableroCamasController:horaServidor');
            $g->post('/login', '\App\V2\TableroCamasController:login');
            $g->get('/tiposDocumentos', '\App\V2\TableroCamasController:tiposDocumentos');

            // SERVICIOS
            $g->get('/servicios', '\App\V2\TableroCamasController:serviciosVerTodos');
            $g->get('/serviciosVerUno', '\App\V2\TableroCamasController:serviciosVerUno');
            $g->get('/serviciosUsuario', '\App\V2\TableroCamasController:serviciosUsuario');
            $g->get('/permisoModuloTablero_ver', '\App\V2\TableroCamasController:permisoModuloTablero_ver');
            $g->get('/permisosModulosTableroServicio', '\App\V2\TableroCamasController:permisosModulosTableroServicio');

            // CAMBIOS DE CAMAS
            $g->delete('/cambioCamaEliminarSolicitud', '\App\V2\TableroCamasController:cambioCamaEliminarSolicitud');
            $g->post('/cambioCamaCrearSolicitud', '\App\V2\TableroCamasController:cambioCamaCrearSolicitud');
            $g->get('/camasDisponibles', '\App\V2\TableroCamasController:camasDisponibles');
            $g->post('/autorizarCambioCama', '\App\V2\TableroCamasController:autorizarCambioCama');
            $g->post('/noAutorizarCambioCama', '\App\V2\TableroCamasController:noAutorizarCambioCama');
            $g->post('/camasCambiosRegistrar', '\App\V2\TableroCamasController:camasCambiosRegistrar');
            $g->get('/camasCambiosPendientes', '\App\V2\TableroCamasController:camasCambiosPendientes');
            $g->get('/motivosCambioCama', '\App\V2\TableroCamasController:verMotivosCambioCama');
            $g->get('/buscarSolicitudCambioCama', '\App\V2\TableroCamasController:buscarSolicitudCambioCama');
            $g->get('/pacientesHabitacion', '\App\V2\TableroCamasController:pacientesHabitacion');
            $g->post('/camasCambiosPendientesAutorizar', '\App\V2\TableroCamasController:camasCambiosPendientesAutorizar');
            $g->post('/camasCambiosPendientesNoAutorizar', '\App\V2\TableroCamasController:camasCambiosPendientesNoAutorizar');
            $g->get('/camasDisponiblesAreaCerrada', '\App\V2\TableroCamasController:camasDisponiblesAreaCerrada');
            $g->post('/cambioCamaCrearSolicitudAreaCerrada', '\App\V2\TableroCamasController:cambioCamaCrearSolicitudAreaCerrada');
            $g->post('/ordenarCambioCama', '\App\V2\TableroCamasController:ordenarCambioCama');


            // ALERTAS           
            $g->post('/nuevaAlerta', '\App\V2\TableroCamasController:nuevaAlerta');
            $g->post('/apagarAlertas', '\App\V2\TableroCamasController:apagarAlertas');
            $g->post('/apagarAlertasCamasDisponibles', '\App\V2\TableroCamasController:apagarAlertasCamasDisponibles');

            // AISLAMIENTOS
            $g->get('/aislamientosDisponibles/{idInternacion}', '\App\V2\TableroCamasController:obtenerAislamientosDisponibles');
            $g->post('/agregarAislamiento', '\App\V2\TableroCamasController:agregarAislamiento');
            $g->post('/finalizarAislamiento', '\App\V2\TableroCamasController:finalizarAislamiento');

            // ALTAS
            $g->get('/validarFechaAltaDefinitiva', '\App\V2\TableroCamasController:validarFechaAltaDefinitiva');
            $g->post('/altaDefinitiva', '\App\V2\TableroCamasController:AltaDefinitiva');
            $g->get('/tiposAltasMedicas', '\App\V2\TableroCamasController:tiposAltasMedicas');
            $g->post('/altaProbable', '\App\V2\TableroCamasController:altaProbableCrear');

            // RESERVAS
            $g->get('/reservas', '\App\V2\TableroCamasController:reservasVerUna');

            // TAREAS
            $g->get('/tareas', '\App\V2\TableroCamasController:tareas');
            $g->get('/tareasCama', '\App\V2\TableroCamasController:tareasCama');
            $g->get('/tareasCama/{idTarea}', '\App\V2\TableroCamasController:tareasCama_VerUna');
            $g->post('/tareaIniciarFinalizarCancelar', '\App\V2\TableroCamasController:tareaIniciarFinalizarCancelar');
            $g->post('/tareaReparacionCrear','\App\V2\TableroCamasController:tareaReparacionCrear');
            $g->get('/categoriasReparaciones','\App\V2\TableroCamasController:categoriasReparaciones');
            

            // TICKETS
            $g->get('/tickets/{idTicket}', '\App\V2\TableroCamasController:ticket_ver');

            // PRUEBAS
            $g->get('/debug', '\App\V2\TableroCamasController:debug');
        });

        // ESTERILIZACION
        $group->group('/esterilizacion', function ($g) {
            // LOTES
            $g->post('/lote', '\App\V2\EsterilizacionController:loteCrear');

            // ARTICULOS
            $g->get('/articulos', '\App\V2\EsterilizacionController:articulosVerTodos');
            $g->get('/articulo', '\App\V2\EsterilizacionController:articuloVerUno');
            $g->post('/articulo', '\App\V2\EsterilizacionController:articuloCrear');
            $g->put('/articulo', '\App\V2\EsterilizacionController:articuloActualizar');
            $g->delete('/articulo', '\App\V2\EsterilizacionController:articuloEliminar');

            // PACKS
            $g->get('/packs', '\App\V2\EsterilizacionController:packsVerTodos');
            $g->get('/pack', '\App\V2\EsterilizacionController:packVerUno');
            $g->post('/pack', '\App\V2\EsterilizacionController:packCrear');
            $g->post('/pack/desde-default', '\App\V2\EsterilizacionController:packCrearDesdeDefault');
            $g->post('/pack/clonar', '\App\V2\EsterilizacionController:packClonar');
            $g->put('/pack', '\App\V2\EsterilizacionController:packActualizar');
            $g->delete('/pack', '\App\V2\EsterilizacionController:packEliminar');

            // PACKS DEFAULT
            $g->get('/packs-default', '\App\V2\EsterilizacionController:packsDefaultVerTodos');
            $g->get('/pack-default', '\App\V2\EsterilizacionController:packDefaultVerUno');
            $g->post('/pack-default', '\App\V2\EsterilizacionController:packDefaultCrear');
            $g->put('/pack-default', '\App\V2\EsterilizacionController:packDefaultActualizar');
            $g->delete('/pack-default', '\App\V2\EsterilizacionController:packDefaultEliminar');

            // ARTICULOS DE PACK (relacion)
            $g->post('/pack-articulo', '\App\V2\EsterilizacionController:packArticuloAgregar');
            $g->put('/pack-articulo', '\App\V2\EsterilizacionController:packArticuloActualizar');
            $g->delete('/pack-articulo', '\App\V2\EsterilizacionController:packArticuloEliminar');
        });
    });
};