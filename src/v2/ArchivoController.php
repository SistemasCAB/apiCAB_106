<?php
namespace App\V2;

require_once __DIR__ . '/../Common/helpers.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use PDOException;
use stdClass;



/**
 * Class ArchivoController
 * @package App\V2
 */
class ArchivoController
{
    /**
     * Obtiene la última atención de un paciente por HC
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function pacienteUltimaAtencion(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $hc = $args['hc'] ?? null;

        if (!isset($tokenAcceso[0])) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (verificarToken($tokenAcceso[0]) === false) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if ($hc == '') {
            $datos = array('estado' => 0, 'mensaje' => 'El número de HC es un dato obligatorio.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $sql = 'EXEC pacienteUltimaAtencion @hc = :hc';
        try {
            $db = getConeccionCAB05();
            $stmt = $db->prepare($sql);
            $stmt->bindParam("hc", $hc);
            $stmt->execute();
            $resultado = $stmt->fetchAll(PDO::FETCH_OBJ);
            $db = null;

            $datos = array();
            foreach ($resultado as $pac) {
                $c = new stdClass();
                $c->ultimaAtencion = $pac->ultimaAtencion;
                $c->hc = $pac->hc;
                $c->paciente = $pac->paciente;
                $c->tipoDocumento = $pac->tipoDocumento;
                $c->nroDocumento = $pac->nroDocumento;
                $c->sexo = $pac->sexo;
                $c->fechaNacimiento = $pac->fechaNacimiento;
                if (!empty($pac->pacientesMarkey)) {
                    $c->pacientesMarkey = json_decode($pac->pacientesMarkey, true);
                } else {
                    $c->pacientesMarkey = array();
                }
                array_push($datos, $c);
                unset($c);
            }

            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (PDOException $e) {
            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Guarda un documento (historia clínica o legajo)
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function guardarDocumento(Request $request, Response $response, $args)
    {
        try {
            $tokenAcceso = $request->getHeader('TokenAcceso');
            $json = $request->getBody();
            $datos = json_decode($json);

            if (!isset($tokenAcceso[0])) {
                $respuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
                $response->getBody()->write(json_encode($respuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            if (verificarToken($tokenAcceso[0]) === false) {
                $respuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
                $response->getBody()->write(json_encode($respuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            if (!$datos) {
                $respuesta = array('estado' => 0, 'mensaje' => 'Datos inválidos o JSON mal formado');
                $response->getBody()->write(json_encode($respuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Log para debugging
            error_log("=== DATOS RECIBIDOS EN PHP ===");
            error_log("pacienteUltimaAtencion raw: " . ($datos->pacienteUltimaAtencion ?? 'null'));
            error_log("JSON completo: " . $json);

            $errores = array();

            if (empty($datos->pacienteNombre))
                $errores[] = 'pacienteNombre';
            if (empty($datos->pacienteDocumento))
                $errores[] = 'pacienteDocumento';
            if (empty($datos->tipoDocumento))
                $errores[] = 'tipoDocumento';
            if (empty($datos->accion))
                $errores[] = 'accion';
            if (empty($datos->usuarioId))
                $errores[] = 'usuarioId';
            if (empty($datos->usuarioNombre))
                $errores[] = 'usuarioNombre';
            if (empty($datos->usuarioApellido))
                $errores[] = 'usuarioApellido';
            if (empty($datos->usuarioDocumento))
                $errores[] = 'usuarioDocumento';

            if (!empty($datos->tipoDocumento) && !in_array($datos->tipoDocumento, ['historia', 'legajo'])) {
                $errores[] = 'tipoDocumento debe ser "historia" o "legajo"';
            }

            if (!empty($datos->accion) && !in_array($datos->accion, ['destruir', 'guardar'])) {
                $errores[] = 'accion debe ser "destruir" o "guardar"';
            }

            if ($datos->accion == 'guardar' && empty($datos->numeroCaja)) {
                $errores[] = 'numeroCaja es obligatorio cuando accion = "guardar"';
            }

            if (count($errores) > 0) {
                $respuesta = array('estado' => 0, 'mensaje' => 'Faltan campos obligatorios: ' . implode(', ', $errores));
                $response->getBody()->write(json_encode($respuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // === PROCESAMIENTO CORREGIDO DE FECHA ===
            $fechaUltimaAtencion = null;
            if (!empty($datos->pacienteUltimaAtencion) && $datos->pacienteUltimaAtencion !== 'null') {
                $fechaRaw = trim($datos->pacienteUltimaAtencion);
                error_log("Procesando fecha: " . $fechaRaw);

                // Si ya viene en formato YYYYMMDD (8 dígitos)
                if (preg_match('/^\d{8}$/', $fechaRaw)) {
                    $fechaUltimaAtencion = $fechaRaw;
                    error_log("Formato YYYYMMDD detectado: " . $fechaUltimaAtencion);
                }
                // Si viene con guiones YYYY-MM-DD
                elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRaw)) {
                    $fechaUltimaAtencion = str_replace('-', '', $fechaRaw);
                    error_log("Formato YYYY-MM-DD convertido a: " . $fechaUltimaAtencion);
                }
                // Si viene con barras DD/MM/YYYY
                elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fechaRaw)) {
                    $partes = explode('/', $fechaRaw);
                    $fechaUltimaAtencion = $partes[2] . $partes[1] . $partes[0];
                    error_log("Formato DD/MM/YYYY convertido a: " . $fechaUltimaAtencion);
                }
                // Si es un timestamp o cualquier otra cosa
                else {
                    $timestamp = strtotime($fechaRaw);
                    if ($timestamp !== false) {
                        $fechaUltimaAtencion = date('Ymd', $timestamp);
                        error_log("Timestamp convertido a: " . $fechaUltimaAtencion);
                    } else {
                        error_log("No se pudo parsear la fecha: " . $fechaRaw);
                    }
                }
            } else {
                error_log("pacienteUltimaAtencion está vacío o es null");
            }

            error_log("Fecha final a guardar: " . ($fechaUltimaAtencion ?? 'NULL'));

            $sqlVerificar = 'SELECT COUNT(*) as total FROM historiasClinicasProcesadas 
                         WHERE pacienteDocumento = :pacienteDocumento 
                         AND tipoDocumento = :tipoDocumento';

            try {
                $db = getConeccionCAB();

                $stmt = $db->prepare($sqlVerificar);
                $stmt->bindParam(':pacienteDocumento', $datos->pacienteDocumento);
                $stmt->bindParam(':tipoDocumento', $datos->tipoDocumento);
                $stmt->execute();
                $resultado = $stmt->fetch(PDO::FETCH_OBJ);

                if ($resultado && $resultado->total > 0) {
                    $respuesta = array('estado' => 0, 'mensaje' => 'Este paciente ya tiene un ' . $datos->tipoDocumento . ' registrado. No se puede duplicar.');
                    $response->getBody()->write(json_encode($respuesta));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                }

                $sqlInsert = 'INSERT INTO historiasClinicasProcesadas 
                    (pacienteNombre, pacienteDocumento, pacienteHc, pacienteSistema, pacienteUltimaAtencion,
                    tipoDocumento, accion, numeroCaja,
                    usuarioId, usuarioNombre, usuarioApellido, usuarioDocumento,
                    fechaAlmacenamiento)
                    VALUES 
                    (:pacienteNombre, :pacienteDocumento, :pacienteHc, :pacienteSistema, :pacienteUltimaAtencion,
                    :tipoDocumento, :accion, :numeroCaja,
                    :usuarioId, :usuarioNombre, :usuarioApellido, :usuarioDocumento,
                    :fechaAlmacenamiento)';

                $stmt = $db->prepare($sqlInsert);
                $stmt->bindValue(':pacienteNombre', $datos->pacienteNombre, PDO::PARAM_STR);
                $stmt->bindValue(':pacienteDocumento', $datos->pacienteDocumento, PDO::PARAM_STR);
                $stmt->bindValue(':pacienteHc', $datos->pacienteHc, PDO::PARAM_STR);
                $stmt->bindValue(':pacienteSistema', $datos->pacienteSistema, PDO::PARAM_STR);
                $stmt->bindValue(':pacienteUltimaAtencion', $fechaUltimaAtencion, PDO::PARAM_STR);
                $stmt->bindValue(':tipoDocumento', $datos->tipoDocumento, PDO::PARAM_STR);
                $stmt->bindValue(':accion', $datos->accion, PDO::PARAM_STR);
                $numeroCaja = ($datos->accion == 'guardar') ? $datos->numeroCaja : null;
                $stmt->bindValue(':numeroCaja', $numeroCaja, PDO::PARAM_INT);
                $stmt->bindValue(':usuarioId', $datos->usuarioId, PDO::PARAM_INT);
                $stmt->bindValue(':usuarioNombre', $datos->usuarioNombre, PDO::PARAM_STR);
                $stmt->bindValue(':usuarioApellido', $datos->usuarioApellido, PDO::PARAM_STR);
                $stmt->bindValue(':usuarioDocumento', $datos->usuarioDocumento, PDO::PARAM_STR);

                // fechaAlmacenamiento en formato YYYYMMDD
                $fechaAlmacenamiento = ($datos->accion == 'guardar') ? date('Ymd') : null;
                $stmt->bindValue(':fechaAlmacenamiento', $fechaAlmacenamiento, PDO::PARAM_STR);

                $stmt->execute();

                error_log("=== DATOS GUARDADOS CORRECTAMENTE ===");
                error_log("pacienteUltimaAtencion guardado: " . ($fechaUltimaAtencion ?? 'NULL'));

                $respuesta = array('estado' => 1, 'mensaje' => 'Documento guardado correctamente');
                $response->getBody()->write(json_encode($respuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

            } catch (PDOException $e) {
                error_log("Error PDO: " . $e->getMessage());
                $respuesta = array('estado' => 0, 'mensaje' => 'Error al guardar: ' . $e->getMessage());
                $response->getBody()->write(json_encode($respuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            } finally {
                if (isset($db))
                    $db = null;
            }
        } catch (Exception $e) {
            error_log("Error general: " . $e->getMessage());
            $respuesta = array('estado' => 0, 'mensaje' => 'Error inesperado: ' . $e->getMessage());
            $response->getBody()->write(json_encode($respuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Obtiene reporte unificado de documentos procesados
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function reporteUnificado(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $respuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($respuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $params = $request->getQueryParams();

        try {
            $db = getConeccionCAB();

            // Verificar si la tabla existe y tiene datos
            $sqlCheck = "SELECT COUNT(*) as total FROM historiasClinicasProcesadas";
            $stmt = $db->prepare($sqlCheck);
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_OBJ);

            if ($count->total == 0) {
                $response->getBody()->write(json_encode([]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            // Construir la consulta base
            $sql = "SELECT * FROM historiasClinicasProcesadas WHERE 1=1";
            $parametros = [];
            $paramIndex = 0;

            // Filtrar por acción
            if (!empty($params['accion'])) {
                $sql .= " AND accion = :accion";
                $parametros[':accion'] = $params['accion'];
            }

            // Filtrar por número de caja
            if (!empty($params['numeroCaja'])) {
                $sql .= " AND numeroCaja = :numeroCaja";
                $parametros[':numeroCaja'] = $params['numeroCaja'];
            }

            // Filtrar por tipo de documento
            if (!empty($params['tipoDocumento'])) {
                $sql .= " AND tipoDocumento = :tipoDocumento";
                $parametros[':tipoDocumento'] = $params['tipoDocumento'];
            }

            // Filtrar por usuario ID
            if (!empty($params['usuarioId'])) {
                $sql .= " AND usuarioId = :usuarioId";
                $parametros[':usuarioId'] = $params['usuarioId'];
            }

            // Filtrar por fecha desde
            if (!empty($params['fechaDesde'])) {
                $sql .= " AND fechaRegistro >= :fechaDesde";
                $parametros[':fechaDesde'] = $params['fechaDesde'];
            }

            // Filtrar por fecha hasta
            if (!empty($params['fechaHasta'])) {
                $sql .= " AND fechaRegistro <= :fechaHasta";
                $parametros[':fechaHasta'] = $params['fechaHasta'];
            }

            // FILTRO SEARCH CORREGIDO
            if (!empty($params['search'])) {
                $searchTerm = '%' . $params['search'] . '%';
                $sql .= " AND (pacienteNombre LIKE :search 
                  OR pacienteDocumento LIKE :search 
                  OR pacienteHc LIKE :search)";
                $parametros[':search'] = $searchTerm;
            }

            $sql .= " ORDER BY fechaRegistro DESC";

            // Preparar y ejecutar la consulta
            $stmt = $db->prepare($sql);

            // Ejecutar con los parámetros
            foreach ($parametros as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            $resultado = $stmt->fetchAll(PDO::FETCH_OBJ);
            $db = null;

            $response->getBody()->write(json_encode($resultado));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            // Log más detallado para debugging
            error_log("Error en reporteUnificado: " . $e->getMessage());
            error_log("SQL que causó error: " . ($sql ?? 'No SQL disponible'));
            error_log("Parámetros: " . print_r($parametros ?? [], true));

            $respuesta = array(
                'estado' => 0,
                'mensaje' => 'Error al consultar: ' . $e->getMessage()
            );
            $response->getBody()->write(json_encode($respuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    /**
     * Verifica qué tipos de documentos ya fueron procesados para un paciente
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function tiposProcesados(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $params = $request->getQueryParams();
        $pacienteDocumento = $params['pacienteDocumento'] ?? null;

        if (!isset($tokenAcceso[0])) {
            $respuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($respuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (verificarToken($tokenAcceso[0]) === false) {
            $respuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($respuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (empty($pacienteDocumento)) {
            $respuesta = array('estado' => 0, 'mensaje' => 'pacienteDocumento es obligatorio');
            $response->getBody()->write(json_encode($respuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $db = getConeccionCAB();

            $sqlHistoria = "SELECT COUNT(*) as total FROM historiasClinicasProcesadas 
                            WHERE pacienteDocumento = :pacienteDocumento AND tipoDocumento = 'historia'";
            $stmt = $db->prepare($sqlHistoria);
            $stmt->bindParam(':pacienteDocumento', $pacienteDocumento);
            $stmt->execute();
            $tieneHistoria = $stmt->fetch(PDO::FETCH_OBJ)->total > 0;

            $sqlLegajo = "SELECT COUNT(*) as total FROM historiasClinicasProcesadas 
                          WHERE pacienteDocumento = :pacienteDocumento AND tipoDocumento = 'legajo'";
            $stmt = $db->prepare($sqlLegajo);
            $stmt->bindParam(':pacienteDocumento', $pacienteDocumento);
            $stmt->execute();
            $tieneLegajo = $stmt->fetch(PDO::FETCH_OBJ)->total > 0;

            $db = null;

            $respuesta = array(
                'tieneHistoria' => $tieneHistoria,
                'tieneLegajo' => $tieneLegajo
            );

            $response->getBody()->write(json_encode($respuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            $respuesta = array('estado' => 0, 'mensaje' => 'Error al consultar: ' . $e->getMessage());
            $response->getBody()->write(json_encode($respuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}