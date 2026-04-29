<?php
namespace App\V2;

require_once __DIR__ . '/../Common/helpers.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AbmUsuariosController
{
    // ─── HELPERS ────────────────────────────────────────────────────────────────

    private function jsonError(Response $response, string $mensaje, int $status): Response
    {
        $response->getBody()->write(json_encode(['estado' => 0, 'mensaje' => $mensaje]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function jsonOk(Response $response, $datos, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($datos));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function verificarAccesoSistemas(int $idUsuario): bool
    {
        try {
            $db = getConeccionCAB();
            $sql = "SELECT us.idServicio
                    FROM dbo.usuariosServicios us
                    INNER JOIN dbo.servicios s ON s.idServicio = us.idServicio
                    WHERE us.idUsuario = :idUsuario AND s.nombreServicio = 'Sistemas'";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':idUsuario', $idUsuario, \PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            return count($result) > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    // Valida token + pertenencia al sector Sistemas. Devuelve Response en caso de error, null si ok.
    private function verificarTokenYSistemas(Request $request, Response $response, int &$idUsuario): ?Response
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $idUsuarioHeader = $request->getHeader('X-Id-Usuario');

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            return $this->jsonError($response, 'Acceso denegado.', 403);
        }

        if (!isset($idUsuarioHeader[0]) || !is_numeric($idUsuarioHeader[0])) {
            return $this->jsonError($response, 'Usuario no identificado.', 403);
        }

        $idUsuario = (int) $idUsuarioHeader[0];

        if (!$this->verificarAccesoSistemas($idUsuario)) {
            return $this->jsonError($response, 'No tenés permisos de Sistemas para realizar esta acción.', 403);
        }

        return null;
    }

    // ─── LOGIN ───────────────────────────────────────────────────────────────────

    private function loginSistema(Request $request, Response $response): Response
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $datosLogin = json_decode((string) $request->getBody());

        $tdocCodigo = $datosLogin->tdocCodigo ?? null;
        $nroDocumento = $datosLogin->nroDocumento ?? null;
        $clave = $datosLogin->clave ?? null;
        $idAplicacion = $datosLogin->idAplicacion ?? null;

        $error = 0;
        if ($tdocCodigo == '') {
            $error++;
        }
        if ($nroDocumento == '') {
            $error++;
        }
        if ($clave == '') {
            $error++;
        }
        if ($idAplicacion == '') {
            $error++;
        }

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            return $this->jsonError($response, 'Acceso denegado.', 403);
        }

        if ($error > 0) {
            return $this->jsonError($response, 'Los parámetros recibidos no son válidos.', 403);
        }

        require_once '../class/Markey.php';
        $datosMarkey = new \Markey;
        $resultadoLogin = $datosMarkey->loginMarkey($nroDocumento, $clave);

        if ($resultadoLogin->estado <> 1) {
            return $this->jsonError($response, 'Acceso denegado. Usuario y/o contraseña incorrectos.', 401);
        }

        $sql = 'EXEC accesoAplicacion
                    @tdocCodigo = :tdocCodigo,
                    @nroDocumento = :nroDocumento,
                    @idAplicacion = :idAplicacion';

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare($sql);
            $stmt->bindParam('tdocCodigo', $tdocCodigo);
            $stmt->bindParam('nroDocumento', $nroDocumento);
            $stmt->bindParam('idAplicacion', $idAplicacion);
            $stmt->execute();
            $res = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            if (count($res) === 0) {
                return $this->jsonError($response, 'Acceso denegado.', 403);
            }

            $tieneSistemas = false;
            foreach ($res as $login) {
                $servicios = [];
                if (!empty($login->servicios)) {
                    $servicios = json_decode($login->servicios, true);
                    if (!is_array($servicios)) {
                        $servicios = [];
                    }
                }

                foreach ($servicios as $servicio) {
                    $nombreServicio = strtolower(trim((string) ($servicio['nombre'] ?? $servicio['nombreServicio'] ?? '')));
                    if ($nombreServicio === 'sistemas') {
                        $tieneSistemas = true;
                        break 2;
                    }
                }
            }

            if (!$tieneSistemas) {
                return $this->jsonError($response, 'Tu usuario no tiene acceso a esta aplicación.', 403);
            }

            $datos = [];
            foreach ($res as $login) {
                $u = new \stdClass();
                $u->estado = (int) $login->estado;
                $u->apellido = $login->apellido;
                $u->nombre = $login->nombre;
                $u->tdocCodigo = (int) $login->tdocCodigo;
                $u->tdocDescripcion = $login->tdocDescripcion;
                $u->nroDocumento = $login->nroDocumento;
                $u->idUsuario = (int) $login->idUsuario;
                $u->idAplicacion = (int) $login->idAplicacion;
                $u->aplicacion = $login->aplicacion;
                $u->servicios = !empty($login->servicios) ? json_decode($login->servicios, true) : [];
                $datos[] = $u;
            }

            return $this->jsonOk($response, $datos);
        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    public function login(Request $request, Response $response, $args)
    {
        return $this->loginSistema($request, $response);

        $json = $request->getBody();
        $datos = json_decode($json);
        $usuario = trim($datos->usuario ?? '');
        $pass = trim($datos->pass ?? '');

        if (!$usuario || !$pass) {
            return $this->jsonError($response, 'Usuario y contraseña son requeridos.', 400);
        }

        try {
            $db = getConeccionCAB();

            // Buscar usuario activo por nroDocumento
            $sqlU = "SELECT idUsuario, nombre, apellido
                     FROM dbo.usuarios
                     WHERE nroDocumento = :nroDocumento AND activo = 1";
            $stmtU = $db->prepare($sqlU);
            $stmtU->bindParam(':nroDocumento', $usuario);
            $stmtU->execute();
            $rows = $stmtU->fetchAll(\PDO::FETCH_OBJ);

            if (count($rows) === 0) {
                $db = null;
                return $this->jsonError($response, 'Usuario no encontrado o inactivo.', 401);
            }

            $u = $rows[0];

            // Verificar que pertenece al sector Sistemas
            $sqlSistemas = "SELECT us.idServicio
                            FROM dbo.usuariosServicios us
                            INNER JOIN dbo.servicios s ON s.idServicio = us.idServicio
                            WHERE us.idUsuario = :idUsuario AND s.nombreServicio = 'Sistemas'";
            $stmtS = $db->prepare($sqlSistemas);
            $stmtS->bindParam(':idUsuario', $u->idUsuario, \PDO::PARAM_INT);
            $stmtS->execute();

            if (count($stmtS->fetchAll()) === 0) {
                $db = null;
                return $this->jsonError($response, 'Tu usuario no tiene acceso a esta aplicación.', 403);
            }

            // Traer todos los servicios del usuario
            $sqlSv = "SELECT CAST(s.idServicio as VARCHAR) as idServicio, s.nombreServicio as nombre
                      FROM dbo.servicios s
                      INNER JOIN dbo.usuariosServicios us ON us.idServicio = s.idServicio
                      WHERE us.idUsuario = :idUsuario";
            $stmtSv = $db->prepare($sqlSv);
            $stmtSv->bindParam(':idUsuario', $u->idUsuario, \PDO::PARAM_INT);
            $stmtSv->execute();
            $servicios = $stmtSv->fetchAll(\PDO::FETCH_OBJ);

            $db = null;

            // Generar token — reutiliza la función del sistema si existe
            $token = function_exists('generarToken')
                ? generarToken($u->idUsuario)
                : bin2hex(random_bytes(32));

            return $this->jsonOk($response, [
                'acceso' => true,
                'token' => $token,
                'usuario' => [
                    'idUsuario' => $u->idUsuario,
                    'nombre' => $u->nombre,
                    'apellido' => $u->apellido,
                ],
                'aplicacion' => ['idAplicacion' => 1, 'aplicacion' => 'ABM USUARIOS'],
                'servicios' => $servicios,
            ]);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    // ─── USUARIOS ────────────────────────────────────────────────────────────────

    public function usuariosVerTodos(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            return $this->jsonError($response, 'Acceso denegado.', 403);
        }

        try {
            $db = getConeccionCAB();

            $stmtU = $db->prepare(
                "SELECT CAST(idUsuario as VARCHAR) as id,
                        CAST(nroDocumento as VARCHAR) as dni,
                        nombre, apellido,
                        CAST(activo as INT) as estado
                 FROM dbo.usuarios
                 ORDER BY apellido, nombre"
            );
            $stmtU->execute();
            $usuarios = $stmtU->fetchAll(\PDO::FETCH_OBJ);

            $stmtS = $db->prepare(
                "SELECT CAST(idUsuario as VARCHAR) as idUsuario,
                        CAST(idServicio as VARCHAR) as idServicio
                 FROM dbo.usuariosServicios"
            );
            $stmtS->execute();
            $relSectores = $stmtS->fetchAll(\PDO::FETCH_OBJ);

            $stmtA = $db->prepare(
                "SELECT CAST(idUsuario as VARCHAR) as idUsuario,
                        CAST(idAplicacion as VARCHAR) as idAplicacion
                 FROM dbo.usuariosAplicaciones"
            );
            $stmtA->execute();
            $relApps = $stmtA->fetchAll(\PDO::FETCH_OBJ);

            $db = null;

            $sectorMap = [];
            foreach ($relSectores as $r) {
                $sectorMap[$r->idUsuario][] = $r->idServicio;
            }
            $appMap = [];
            foreach ($relApps as $r) {
                $appMap[$r->idUsuario][] = $r->idAplicacion;
            }

            $resultado = [];
            foreach ($usuarios as $u) {
                $idStr = (string) $u->id;
                $resultado[] = [
                    'id' => $idStr,
                    'dni' => $u->dni,
                    'nombre' => $u->nombre,
                    'apellido' => $u->apellido,
                    'estado' => (int) $u->estado,
                    'sectorIds' => $sectorMap[$idStr] ?? [],
                    'applicationIds' => $appMap[$idStr] ?? [],
                ];
            }

            return $this->jsonOk($response, $resultado);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    public function usuarioCrear(Request $request, Response $response, $args)
    {
        $idUsuarioActor = 0;
        if ($check = $this->verificarTokenYSistemas($request, $response, $idUsuarioActor))
            return $check;

        $datos = json_decode($request->getBody());
        $dni = trim($datos->dni ?? '');
        $nombre = trim($datos->nombre ?? '');
        $apellido = trim($datos->apellido ?? '');
        $estado = isset($datos->estado) ? (int) $datos->estado : 1;
        $sectorIds = $datos->sectorIds ?? [];
        $applicationIds = $datos->applicationIds ?? [];

        if (!$dni || !$nombre || !$apellido) {
            return $this->jsonError($response, 'DNI, nombre y apellido son obligatorios.', 400);
        }

        try {
            $db = getConeccionCAB();

            $stmtCheck = $db->prepare('SELECT idUsuario FROM dbo.usuarios WHERE nroDocumento = :dni');
            $stmtCheck->bindParam(':dni', $dni);
            $stmtCheck->execute();
            if (count($stmtCheck->fetchAll()) > 0) {
                $db = null;
                return $this->jsonError($response, 'Ya existe un usuario con ese DNI.', 409);
            }

            $stmtId = $db->prepare('SELECT ISNULL(MAX(idUsuario), 0) + 1 as nextId FROM dbo.usuarios');
            $stmtId->execute();
            $nextId = (int) $stmtId->fetchAll(\PDO::FETCH_OBJ)[0]->nextId;

            $stmtU = $db->prepare(
                'INSERT INTO dbo.usuarios (idUsuario, tdocCodigo, nroDocumento, nombre, apellido, activo)
                 VALUES (:id, 1, :dni, :nombre, :apellido, :activo)'
            );
            $stmtU->bindParam(':id', $nextId, \PDO::PARAM_INT);
            $stmtU->bindParam(':dni', $dni);
            $stmtU->bindParam(':nombre', $nombre);
            $stmtU->bindParam(':apellido', $apellido);
            $stmtU->bindParam(':activo', $estado, \PDO::PARAM_INT);
            $stmtU->execute();

            foreach ($sectorIds as $idServicio) {
                $s = $db->prepare('INSERT INTO dbo.usuariosServicios (idUsuario, idServicio) VALUES (:u, :s)');
                $s->execute([':u' => $nextId, ':s' => $idServicio]);
            }
            foreach ($applicationIds as $idAplicacion) {
                $a = $db->prepare('INSERT INTO dbo.usuariosAplicaciones (idUsuario, idAplicacion) VALUES (:u, :a)');
                $a->execute([':u' => $nextId, ':a' => $idAplicacion]);
            }

            $db = null;
            return $this->jsonOk($response, [
                'estado' => 1,
                'mensaje' => 'Usuario creado correctamente.',
                'id' => (string) $nextId,
            ]);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    public function usuarioActualizar(Request $request, Response $response, $args)
    {
        $idUsuarioActor = 0;
        if ($check = $this->verificarTokenYSistemas($request, $response, $idUsuarioActor))
            return $check;

        $datos = json_decode($request->getBody());
        $id = $datos->id ?? null;
        $dni = trim($datos->dni ?? '');
        $nombre = trim($datos->nombre ?? '');
        $apellido = trim($datos->apellido ?? '');
        $estado = isset($datos->estado) ? (int) $datos->estado : null;
        $sectorIds = $datos->sectorIds ?? null;
        $applicationIds = $datos->applicationIds ?? null;

        if (!$id || !$dni || !$nombre || !$apellido || $estado === null) {
            return $this->jsonError($response, 'Todos los campos son obligatorios.', 400);
        }

        try {
            $db = getConeccionCAB();

            $stmtU = $db->prepare(
                'UPDATE dbo.usuarios
                 SET nroDocumento = :dni, nombre = :nombre, apellido = :apellido, activo = :activo
                 WHERE idUsuario = :id'
            );
            $stmtU->bindParam(':dni', $dni);
            $stmtU->bindParam(':nombre', $nombre);
            $stmtU->bindParam(':apellido', $apellido);
            $stmtU->bindParam(':activo', $estado, \PDO::PARAM_INT);
            $stmtU->bindParam(':id', $id, \PDO::PARAM_INT);
            $stmtU->execute();

            if ($sectorIds !== null) {
                $db->prepare('DELETE FROM dbo.usuariosServicios WHERE idUsuario = :id')->execute([':id' => $id]);
                foreach ($sectorIds as $idServicio) {
                    $db->prepare('INSERT INTO dbo.usuariosServicios (idUsuario, idServicio) VALUES (:u, :s)')
                        ->execute([':u' => $id, ':s' => $idServicio]);
                }
            }
            if ($applicationIds !== null) {
                $db->prepare('DELETE FROM dbo.usuariosAplicaciones WHERE idUsuario = :id')->execute([':id' => $id]);
                foreach ($applicationIds as $idAplicacion) {
                    $db->prepare('INSERT INTO dbo.usuariosAplicaciones (idUsuario, idAplicacion) VALUES (:u, :a)')
                        ->execute([':u' => $id, ':a' => $idAplicacion]);
                }
            }

            $db = null;
            return $this->jsonOk($response, ['estado' => 1, 'mensaje' => 'Usuario actualizado correctamente.']);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    public function usuarioToggleActivo(Request $request, Response $response, $args)
    {
        $idUsuarioActor = 0;
        if ($check = $this->verificarTokenYSistemas($request, $response, $idUsuarioActor))
            return $check;

        $datos = json_decode($request->getBody());
        $id = $datos->id ?? null;
        $activo = isset($datos->activo) ? (int) $datos->activo : null;

        if (!$id || $activo === null) {
            return $this->jsonError($response, 'ID y estado son requeridos.', 400);
        }

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare('UPDATE dbo.usuarios SET activo = :activo WHERE idUsuario = :id');
            $stmt->bindParam(':activo', $activo, \PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();
            $db = null;

            $msg = $activo === 1 ? 'Usuario activado.' : 'Usuario desactivado.';
            return $this->jsonOk($response, ['estado' => 1, 'mensaje' => $msg]);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    public function usuarioEliminar(Request $request, Response $response, $args)
    {
        $idUsuarioActor = 0;
        if ($check = $this->verificarTokenYSistemas($request, $response, $idUsuarioActor))
            return $check;

        $id = $request->getQueryParams()['id'] ?? null;
        if (!$id)
            return $this->jsonError($response, 'ID de usuario requerido.', 400);

        try {
            $db = getConeccionCAB();
            $db->prepare('DELETE FROM dbo.usuariosServicios    WHERE idUsuario = :id')->execute([':id' => $id]);
            $db->prepare('DELETE FROM dbo.usuariosAplicaciones WHERE idUsuario = :id')->execute([':id' => $id]);
            $db->prepare('DELETE FROM dbo.usuarios             WHERE idUsuario = :id')->execute([':id' => $id]);
            $db = null;
            return $this->jsonOk($response, ['estado' => 1, 'mensaje' => 'Usuario eliminado.']);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    // ─── SERVICIOS (SECTORES) ─────────────────────────────────────────────────────

    public function serviciosVerTodos(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            return $this->jsonError($response, 'Acceso denegado.', 403);
        }

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare(
                'SELECT CAST(idServicio as VARCHAR) as id, nombreServicio as nombre
                 FROM dbo.servicios
                 ORDER BY nombreServicio'
            );
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            return $this->jsonOk($response, $result);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    public function servicioCrear(Request $request, Response $response, $args)
    {
        $idUsuarioActor = 0;
        if ($check = $this->verificarTokenYSistemas($request, $response, $idUsuarioActor))
            return $check;

        $datos = json_decode($request->getBody());
        $nombre = trim($datos->nombre ?? '');
        $idTipoInternacion = $datos->idTipoInternacion ?? null; // puede ser NULL
        $cambioCamaAreaCerrada = $datos->cambioCamaAreaCerrada ?? 0;
        $gestionaCamas = $datos->gestionaCamas ?? 0;

        if (!$nombre) {
            return $this->jsonError($response, 'El nombre es obligatorio.', 400);
        }

        try {
            $db = getConeccionCAB();

            $stmtCheck = $db->prepare('SELECT idServicio FROM dbo.servicios WHERE nombreServicio = :nombre');
            $stmtCheck->bindParam(':nombre', $nombre);
            $stmtCheck->execute();
            if (count($stmtCheck->fetchAll()) > 0) {
                $db = null;
                return $this->jsonError($response, 'Ya existe un sector con ese nombre.', 409);
            }

            // ✅ INSERT sin columna 'activo'
            $stmt = $db->prepare('
            INSERT INTO dbo.servicios (nombreServicio, idTipoInternacion, cambioCamaAreaCerrada, gestionaCamas) 
            VALUES (:nombre, :idTipoInternacion, :cambioCamaAreaCerrada, :gestionaCamas)
        ');
            $stmt->bindParam(':nombre', $nombre);

            // Manejar NULL para idTipoInternacion
            if ($idTipoInternacion === null) {
                $stmt->bindValue(':idTipoInternacion', null, \PDO::PARAM_NULL);
            } else {
                $stmt->bindParam(':idTipoInternacion', $idTipoInternacion);
            }

            $stmt->bindParam(':cambioCamaAreaCerrada', $cambioCamaAreaCerrada);
            $stmt->bindParam(':gestionaCamas', $gestionaCamas);
            $stmt->execute();

            $nextId = $db->lastInsertId();

            $db = null;

            return $this->jsonOk($response, [
                'estado' => 1,
                'mensaje' => 'Sector creado.',
                'id' => (string) $nextId,
            ]);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }
    public function servicioActualizar(Request $request, Response $response, $args)
    {
        $idUsuarioActor = 0;
        if ($check = $this->verificarTokenYSistemas($request, $response, $idUsuarioActor))
            return $check;

        $datos = json_decode($request->getBody());
        $id = $datos->id ?? null;
        $nombre = trim($datos->nombre ?? '');

        if (!$id || !$nombre)
            return $this->jsonError($response, 'ID y nombre son obligatorios.', 400);

        try {
            $db = getConeccionCAB();

            $stmtCheck = $db->prepare(
                'SELECT idServicio FROM dbo.servicios WHERE nombreServicio = :nombre AND idServicio != :id'
            );
            $stmtCheck->bindParam(':nombre', $nombre);
            $stmtCheck->bindParam(':id', $id);
            $stmtCheck->execute();
            if (count($stmtCheck->fetchAll()) > 0) {
                $db = null;
                return $this->jsonError($response, 'Ya existe otro sector con ese nombre.', 409);
            }

            $stmt = $db->prepare('UPDATE dbo.servicios SET nombreServicio = :nombre WHERE idServicio = :id');
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();
            $db = null;

            return $this->jsonOk($response, ['estado' => 1, 'mensaje' => 'Sector actualizado.']);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    public function servicioEliminar(Request $request, Response $response, $args)
    {
        $idUsuarioActor = 0;
        if ($check = $this->verificarTokenYSistemas($request, $response, $idUsuarioActor))
            return $check;

        $id = $request->getQueryParams()['id'] ?? null;
        if (!$id)
            return $this->jsonError($response, 'ID de sector requerido.', 400);

        try {
            $db = getConeccionCAB();

            $stmtCheck = $db->prepare('SELECT idUsuario FROM dbo.usuariosServicios WHERE idServicio = :id');
            $stmtCheck->bindParam(':id', $id);
            $stmtCheck->execute();
            if (count($stmtCheck->fetchAll()) > 0) {
                $db = null;
                return $this->jsonError($response, 'No se puede eliminar un sector con usuarios asignados.', 409);
            }

            $db->prepare('DELETE FROM dbo.servicios WHERE idServicio = :id')->execute([':id' => $id]);
            $db = null;
            return $this->jsonOk($response, ['estado' => 1, 'mensaje' => 'Sector eliminado.']);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    // ─── APLICACIONES ────────────────────────────────────────────────────────────

    public function aplicacionesVerTodos(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            return $this->jsonError($response, 'Acceso denegado.', 403);
        }

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare(
                'SELECT CAST(idAplicacion as VARCHAR) as id, nombre, descripcion
                 FROM dbo.aplicaciones
                 WHERE activo = 1
                 ORDER BY nombre'
            );
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            return $this->jsonOk($response, $result);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    public function aplicacionCrear(Request $request, Response $response, $args)
    {
        $idUsuarioActor = 0;
        if ($check = $this->verificarTokenYSistemas($request, $response, $idUsuarioActor))
            return $check;

        $datos = json_decode($request->getBody());
        $nombre = trim($datos->nombre ?? '');
        $descripcion = trim($datos->descripcion ?? '');
        if (!$nombre)
            return $this->jsonError($response, 'El nombre es obligatorio.', 400);

        try {
            $db = getConeccionCAB();

            $stmtCheck = $db->prepare('SELECT idAplicacion FROM dbo.aplicaciones WHERE nombre = :nombre');
            $stmtCheck->bindParam(':nombre', $nombre);
            $stmtCheck->execute();
            if (count($stmtCheck->fetchAll()) > 0) {
                $db = null;
                return $this->jsonError($response, 'Ya existe una aplicación con ese nombre.', 409);
            }

            $stmt = $db->prepare(
                "INSERT INTO dbo.aplicaciones (nombre, descripcion, activo)
                 OUTPUT INSERTED.idAplicacion
                 VALUES (:nombre, :descripcion, 1)"
            );
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->execute();
            $nextId = (int) $stmt->fetchColumn();
            $db = null;

            return $this->jsonOk($response, [
                'estado' => 1,
                'mensaje' => 'Aplicación creada.',
                'id' => (string) $nextId,
            ]);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    public function aplicacionActualizar(Request $request, Response $response, $args)
    {
        $idUsuarioActor = 0;
        if ($check = $this->verificarTokenYSistemas($request, $response, $idUsuarioActor))
            return $check;

        $datos = json_decode($request->getBody());
        $id = $datos->id ?? null;
        $nombre = trim($datos->nombre ?? '');
        $descripcion = trim($datos->descripcion ?? '');

        if (!$id || !$nombre)
            return $this->jsonError($response, 'ID y nombre son obligatorios.', 400);

        try {
            $db = getConeccionCAB();

            $stmtCheck = $db->prepare(
                'SELECT idAplicacion FROM dbo.aplicaciones WHERE nombre = :nombre AND idAplicacion != :id'
            );
            $stmtCheck->bindParam(':nombre', $nombre);
            $stmtCheck->bindParam(':id', $id);
            $stmtCheck->execute();
            if (count($stmtCheck->fetchAll()) > 0) {
                $db = null;
                return $this->jsonError($response, 'Ya existe otra aplicación con ese nombre.', 409);
            }

            $stmt = $db->prepare('UPDATE dbo.aplicaciones SET nombre = :nombre, descripcion = :descripcion WHERE idAplicacion = :id');
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();
            $db = null;

            return $this->jsonOk($response, ['estado' => 1, 'mensaje' => 'Aplicación actualizada.']);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }

    public function aplicacionEliminar(Request $request, Response $response, $args)
    {
        $idUsuarioActor = 0;
        if ($check = $this->verificarTokenYSistemas($request, $response, $idUsuarioActor))
            return $check;

        $id = $request->getQueryParams()['id'] ?? null;
        if (!$id)
            return $this->jsonError($response, 'ID de aplicación requerido.', 400);

        try {
            $db = getConeccionCAB();

            $stmtCheck = $db->prepare('SELECT idUsuario FROM dbo.usuariosAplicaciones WHERE idAplicacion = :id');
            $stmtCheck->bindParam(':id', $id);
            $stmtCheck->execute();
            if (count($stmtCheck->fetchAll()) > 0) {
                $db = null;
                return $this->jsonError($response, 'No se puede eliminar una aplicación con usuarios asignados.', 409);
            }

            // Soft-delete: marcar como inactiva
            $db->prepare('UPDATE dbo.aplicaciones SET activo = 0 WHERE idAplicacion = :id')->execute([':id' => $id]);
            $db = null;
            return $this->jsonOk($response, ['estado' => 1, 'mensaje' => 'Aplicación eliminada.']);

        } catch (\PDOException $e) {
            return $this->jsonError($response, $e->getMessage(), 500);
        }
    }
}
