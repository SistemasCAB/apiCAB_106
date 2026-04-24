<?php
namespace App\V2;

require_once __DIR__ . '/../Common/helpers.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EsterilizacionController
{
    // ARTICULOS (dbo.esterilizacionArticulos)
    // VER TODOS LOS ARTICULOS
    public function articulosVerTodos(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if ($rate = $this->throttleMutation($request, $response, 'articuloCrear', 6, 60)) {
            return $rate;
        }

        $sql = 'SELECT id, nombre FROM dbo.esterilizacionArticulos ORDER BY nombre';

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $resultados = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            $response->getBody()->write(json_encode($resultados));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // VER UN ARTICULO
    public function articuloVerUno(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $id = $request->getQueryParams()['id'] ?? null;

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if ($rate = $this->throttleMutation($request, $response, 'articuloEliminar', 8, 300)) {
            return $rate;
        }
        if ($confirm = $this->requireDestructiveConfirmation($request, $response)) {
            return $confirm;
        }

        if (!$id) {
            $datos = array('estado' => 0, 'mensaje' => 'ID no proporcionado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $sql = 'SELECT id, nombre FROM dbo.esterilizacionArticulos WHERE id = :id';

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            if (count($resultado) == 0) {
                $datos = array('estado' => 0, 'mensaje' => 'Artículo no encontrado.');
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode($resultado[0]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // CREAR ARTICULO
public function articuloCrear(Request $request, Response $response, $args)
{
    $tokenAcceso = $request->getHeader('TokenAcceso');

    if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
        $datosRespuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
        $response->getBody()->write(json_encode($datosRespuesta));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    $json = $request->getBody();
    $datos = json_decode($json);
    $nombre = trim($datos->nombre ?? '');

    if (!$nombre) {
        $datosRespuesta = array('estado' => 0, 'mensaje' => 'El nombre es obligatorio.');
        $response->getBody()->write(json_encode($datosRespuesta));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    try {
        $db = getConeccionCAB();
        
        // Obtener el próximo ID disponible
        $sqlMaxId = 'SELECT ISNULL(MAX(id), 0) + 1 as nextId FROM dbo.esterilizacionArticulos';
        $stmtMax = $db->prepare($sqlMaxId);
        $stmtMax->execute();
        $resultadoMax = $stmtMax->fetchAll(\PDO::FETCH_OBJ);
        $nextId = $resultadoMax[0]->nextId;
        
        // Verificar si ya existe el nombre
        $sqlCheck = 'SELECT id FROM dbo.esterilizacionArticulos WHERE nombre = :nombre';
        $stmtCheck = $db->prepare($sqlCheck);
        $stmtCheck->bindParam(':nombre', $nombre);
        $stmtCheck->execute();
        $existe = $stmtCheck->fetchAll(\PDO::FETCH_OBJ);
        
        if (count($existe) > 0) {
            $db = null;
            $datosRespuesta = array(
                'estado' => 0, 
                'mensaje' => 'Ya existe un artículo con ese nombre.',
                'id' => $existe[0]->id
            );
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }
        
        // Insertar con ID manual
        $sql = 'INSERT INTO dbo.esterilizacionArticulos (id, nombre) VALUES (:id, :nombre)';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $nextId);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->execute();
        $db = null;

        $datosRespuesta = array(
            'estado' => 1,
            'mensaje' => 'Artículo creado correctamente.',
            'id' => $nextId
        );
        $response->getBody()->write(json_encode($datosRespuesta));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (\PDOException $e) {
        $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
        $response->getBody()->write(json_encode($datosRespuesta));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
}

    // ACTUALIZAR ARTICULO
    public function articuloActualizar(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $json = $request->getBody();
        $datos = json_decode($json);

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if ($rate = $this->throttleMutation($request, $response, 'packCrear', 6, 60)) {
            return $rate;
        }

        $id = $datos->id ?? null;
        $nombre = trim($datos->nombre ?? '');

        if (!$id || !$nombre) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'ID y nombre son obligatorios.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verificar si ya existe otro con el mismo nombre
        $sqlCheck = 'SELECT id FROM dbo.esterilizacionArticulos WHERE nombre = :nombre AND id != :id';
        try {
            $db = getConeccionCAB();
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->bindParam(':nombre', $nombre);
            $stmtCheck->bindParam(':id', $id);
            $stmtCheck->execute();
            $existe = $stmtCheck->fetchAll(\PDO::FETCH_OBJ);
            
            if (count($existe) > 0) {
                $db = null;
                $datosRespuesta = array('estado' => 0, 'mensaje' => 'Ya existe otro artículo con ese nombre.');
                $response->getBody()->write(json_encode($datosRespuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            $sql = 'UPDATE dbo.esterilizacionArticulos SET nombre = :nombre WHERE id = :id';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->execute();
            $db = null;

            $datosRespuesta = array('estado' => 1, 'mensaje' => 'Artículo actualizado correctamente.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ELIMINAR ARTICULO
    public function articuloEliminar(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $id = $request->getQueryParams()['id'] ?? null;

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (!$id) {
            $datos = array('estado' => 0, 'mensaje' => 'ID no proporcionado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verificar si el artículo está siendo usado en algún pack
        $sqlCheck = 'SELECT COUNT(*) as total FROM dbo.esterilizacionPackArticulos WHERE ArticuloId = :id';
        try {
            $db = getConeccionCAB();
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->bindParam(':id', $id);
            $stmtCheck->execute();
            $resultado = $stmtCheck->fetchAll(\PDO::FETCH_OBJ);
            
            if ($resultado[0]->total > 0) {
                $db = null;
                $datos = array('estado' => 0, 'mensaje' => 'No se puede eliminar el artículo porque está siendo usado en uno o más packs.');
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            $sql = 'DELETE FROM dbo.esterilizacionArticulos WHERE id = :id';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $db = null;

            $datos = array('estado' => 1, 'mensaje' => 'Artículo eliminado correctamente.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // PACKS (dbo.esterilizacionPacks)
    // VER TODOS LOS PACKS
    public function packsVerTodos(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $sql = 'SELECT p.Id, p.Nombre, COUNT(pa.Id) as CantidadArticulos
                FROM dbo.esterilizacionPacks p
                LEFT JOIN dbo.esterilizacionPackArticulos pa ON pa.PackId = p.Id
                GROUP BY p.Id, p.Nombre
                ORDER BY p.Nombre';

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $resultados = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            $response->getBody()->write(json_encode($resultados));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // VER UN PACK CON SUS ARTICULOS
    public function packVerUno(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $id = $request->getQueryParams()['id'] ?? null;

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (!$id) {
            $datos = array('estado' => 0, 'mensaje' => 'ID no proporcionado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        try {
            $db = getConeccionCAB();
            
            $sqlPack = 'SELECT Id, Nombre FROM dbo.esterilizacionPacks WHERE Id = :id';
            $stmtPack = $db->prepare($sqlPack);
            $stmtPack->bindParam(':id', $id);
            $stmtPack->execute();
            $pack = $stmtPack->fetchAll(\PDO::FETCH_OBJ);
            
            if (count($pack) == 0) {
                $db = null;
                $datos = array('estado' => 0, 'mensaje' => 'Pack no encontrado.');
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            $sqlArticulos = 'SELECT epa.Id, epa.ArticuloId, epa.Cantidad, a.nombre as ArticuloNombre
                            FROM dbo.esterilizacionPackArticulos epa
                            INNER JOIN dbo.esterilizacionArticulos a ON epa.ArticuloId = a.id
                            WHERE epa.PackId = :id
                            ORDER BY a.nombre';
            $stmtArticulos = $db->prepare($sqlArticulos);
            $stmtArticulos->bindParam(':id', $id);
            $stmtArticulos->execute();
            $articulos = $stmtArticulos->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            
            $resultado = array(
                'id' => $pack[0]->Id,
                'nombre' => $pack[0]->Nombre,
                'articulos' => $articulos
            );
            
            $response->getBody()->write(json_encode($resultado));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // VALIDAR SI UN PACK YA EXISTE (por su composición de artículos)
    private function packExiste($articulos, &$packIdExistente = null)
    {
        $sql = 'SELECT p.Id 
                FROM dbo.esterilizacionPacks p
                WHERE (SELECT COUNT(*) FROM dbo.esterilizacionPackArticulos WHERE PackId = p.Id) = :totalArticulos
                AND NOT EXISTS (
                    SELECT 1 FROM dbo.esterilizacionPackArticulos epa1
                    WHERE epa1.PackId = p.Id
                    AND NOT EXISTS (
                        SELECT 1 FROM (
                            SELECT ArticuloId, Cantidad FROM (
                                VALUES ';
        
        // Construir los VALUES para la comparación
        $values = [];
        $params = ['totalArticulos' => count($articulos)];
        $i = 0;
        foreach ($articulos as $art) {
            $i++;
            $values[] = "(:art_id_$i, :art_cant_$i)";
            $params["art_id_$i"] = $art->articuloId;
            $params["art_cant_$i"] = $art->cantidad;
        }
        $sql .= implode(',', $values);
        $sql .= ') AS v(ArticuloId, Cantidad)
                        WHERE v.ArticuloId = epa1.ArticuloId AND v.Cantidad = epa1.Cantidad
                    )
                )
                AND NOT EXISTS (
                    SELECT 1 FROM (
                        SELECT ArticuloId, Cantidad FROM (
                            VALUES ';
        $sql .= implode(',', $values);
        $sql .= ') AS v(ArticuloId, Cantidad)
                    WHERE NOT EXISTS (
                        SELECT 1 FROM dbo.esterilizacionPackArticulos epa2
                        WHERE epa2.PackId = p.Id AND epa2.ArticuloId = v.ArticuloId AND epa2.Cantidad = v.Cantidad
                    )
                )';
        
        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            
            if (count($resultado) > 0) {
                $packIdExistente = $resultado[0]->Id;
                return true;
            }
            return false;
        } catch (\PDOException $e) {
            error_log("Error en packExiste: " . $e->getMessage());
            return false;
        }
    }

    private function packNombreExiste(string $nombre, ?string $idExcluir = null, ?string &$packIdExistente = null): bool
    {
        try {
            $db = getConeccionCAB();
            $tables = ['dbo.esterilizacionPacks', 'dbo.esterilizacionPacksDefault'];
            foreach ($tables as $table) {
                $sql = "SELECT TOP 1 Id
                        FROM {$table}
                        WHERE LOWER(LTRIM(RTRIM(Nombre))) = LOWER(LTRIM(RTRIM(:nombre)))";

                if ($idExcluir !== null && $idExcluir !== '') {
                    $sql .= ' AND Id <> :idExcluir';
                }

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':nombre', $nombre);
                if ($idExcluir !== null && $idExcluir !== '') {
                    $stmt->bindValue(':idExcluir', $idExcluir);
                }
                $stmt->execute();
                $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);

                if (count($resultado) > 0) {
                    $packIdExistente = $resultado[0]->Id;
                    $db = null;
                    return true;
                }
            }

            $db = null;
            return false;
        } catch (\PDOException $e) {
            error_log("Error en packNombreExiste: " . $e->getMessage());
            return false;
        }
    }

    private function packDefaultExiste($articulos, &$packIdExistente = null): bool
    {
        $sql = 'SELECT pd.Id
                FROM dbo.esterilizacionPacksDefault pd
                WHERE (SELECT COUNT(*) FROM dbo.esterilizacionPackArticulos WHERE PackDefaultId = pd.Id) = :totalArticulos
                AND NOT EXISTS (
                    SELECT 1 FROM dbo.esterilizacionPackArticulos epa1
                    WHERE epa1.PackDefaultId = pd.Id
                    AND NOT EXISTS (
                        SELECT 1 FROM (
                            SELECT ArticuloId, Cantidad FROM (
                                VALUES ';

        $values = [];
        $params = ['totalArticulos' => count($articulos)];
        $i = 0;
        foreach ($articulos as $art) {
            $i++;
            $values[] = "(:art_id_$i, :art_cant_$i)";
            $params["art_id_$i"] = $art->articuloId;
            $params["art_cant_$i"] = $art->cantidad;
        }
        $sql .= implode(',', $values);
        $sql .= ') AS v(ArticuloId, Cantidad)
                        WHERE v.ArticuloId = epa1.ArticuloId AND v.Cantidad = epa1.Cantidad
                    )
                )
                AND NOT EXISTS (
                    SELECT 1 FROM (
                        SELECT ArticuloId, Cantidad FROM (
                            VALUES ';
        $sql .= implode(',', $values);
        $sql .= ') AS v(ArticuloId, Cantidad)
                    WHERE NOT EXISTS (
                        SELECT 1 FROM dbo.esterilizacionPackArticulos epa2
                        WHERE epa2.PackDefaultId = pd.Id AND epa2.ArticuloId = v.ArticuloId AND epa2.Cantidad = v.Cantidad
                    )
                )';

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            if (count($resultado) > 0) {
                $packIdExistente = $resultado[0]->Id;
                return true;
            }
            return false;
        } catch (\PDOException $e) {
            error_log("Error en packDefaultExiste: " . $e->getMessage());
            return false;
        }
    }

    private function getClientThrottleKey(Request $request): string
    {
        $token = $request->getHeaderLine('TokenAcceso');
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $ua = $request->getHeaderLine('User-Agent');
        $fingerprint = $token !== '' ? substr(hash('sha256', $token), 0, 16) : 'no-token';
        return $ip . '|' . $fingerprint . '|' . substr(hash('sha256', $ua), 0, 12);
    }

    private function throttleMutation(Request $request, Response $response, string $bucket, int $limit, int $windowSeconds)
    {
        $key = $this->getClientThrottleKey($request);
        $now = time();
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cab-esterilizacion-throttle';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $file = $dir . DIRECTORY_SEPARATOR . sha1($bucket . '|' . $key) . '.json';
        $state = ['windowStart' => $now, 'count' => 0];

        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = json_decode($raw ?: '', true);
            if (is_array($decoded) && isset($decoded['windowStart'], $decoded['count'])) {
                $state = $decoded;
            }
        }

        if (($now - (int)$state['windowStart']) >= $windowSeconds) {
            $state = ['windowStart' => $now, 'count' => 0];
        }

        $state['count'] = (int)$state['count'] + 1;
        @file_put_contents($file, json_encode($state), LOCK_EX);

        if ($state['count'] > $limit) {
            $retryAfter = max(1, $windowSeconds - ($now - (int)$state['windowStart']));
            $datos = array(
                'estado' => 0,
                'mensaje' => 'Demasiadas operaciones seguidas. Probá de nuevo en unos segundos.',
                'retryAfter' => $retryAfter
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string)$retryAfter)
                ->withStatus(429);
        }

        return null;
    }

    private function requireDestructiveConfirmation(Request $request, Response $response)
    {
        $confirm = strtolower(trim($request->getHeaderLine('X-Destructive-Action')));
        if ($confirm !== 'confirm') {
            $datos = array(
                'estado' => 0,
                'mensaje' => 'Falta confirmación explícita para esta operación.',
            );
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(428);
        }

        return null;
    }

    // CREAR PACK (con validación de duplicados)
    public function packCrear(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $json = $request->getBody();
        $datos = json_decode($json);

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if ($rate = $this->throttleMutation($request, $response, 'packCrearDesdeDefault', 6, 60)) {
            return $rate;
        }

        $nombre = trim($datos->nombre ?? '');
        $articulos = $datos->articulos ?? []; // Array de {articuloId, cantidad}

        if (!$nombre) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'El nombre del pack es obligatorio.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (empty($articulos)) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'El pack debe tener al menos un artículo.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verificar si ya existe un pack con los mismos artículos
        $packIdExistente = null;
        if ($this->packExiste($articulos, $packIdExistente)) {
            // Obtener el pack existente
            $sql = 'SELECT Id, Nombre FROM dbo.esterilizacionPacks WHERE Id = :id';
            try {
                $db = getConeccionCAB();
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':id', $packIdExistente);
                $stmt->execute();
                $packExistente = $stmt->fetchAll(\PDO::FETCH_OBJ);
                $db = null;
                
                $datosRespuesta = array(
                    'estado' => 0,
                    'mensaje' => 'Ya existe un pack con la misma composición.',
                    'packExistente' => array(
                        'id' => $packExistente[0]->Id,
                        'nombre' => $packExistente[0]->Nombre
                    )
                );
                $response->getBody()->write(json_encode($datosRespuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            } catch (\PDOException $e) {
                $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
                $response->getBody()->write(json_encode($datosRespuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        }

        $packNombreExistente = null;
        if ($this->packNombreExiste($nombre, null, $packNombreExistente)) {
            $datosRespuesta = array(
                'estado' => 0,
                'mensaje' => 'Ya existe un pack con ese nombre.',
                'packExistente' => array('id' => $packNombreExistente)
            );
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        // Generar ID del pack (puede ser autoincremental o manual)
        $packId = $datos->id ?? null;
        
        try {
            $db = getConeccionCAB();
            $db->beginTransaction();
            
            // Si no se proporcionó ID, generar uno
            if (!$packId) {
                $sqlGetMaxId = "SELECT MAX(CAST(Id AS INT)) as maxId FROM dbo.esterilizacionPacks WHERE ISNUMERIC(Id) = 1";
                $stmtMax = $db->prepare($sqlGetMaxId);
                $stmtMax->execute();
                $maxId = $stmtMax->fetchAll(\PDO::FETCH_OBJ);
                $nextId = ($maxId[0]->maxId ?? 0) + 1;
                $packId = (string)$nextId;
            }
            
            // Insertar pack usando la fecha del servidor SQL para evitar problemas de formato
            $sql = 'INSERT INTO dbo.esterilizacionPacks (Id, Nombre, FechaCreacion) VALUES (:id, :nombre, GETDATE())';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $packId);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->execute();
            
            // Insertar artículos del pack
            $sqlArticulo = 'INSERT INTO dbo.esterilizacionPackArticulos (PackId, ArticuloId, Cantidad) VALUES (:packId, :articuloId, :cantidad)';
            $stmtArt = $db->prepare($sqlArticulo);
            
            foreach ($articulos as $articulo) {
                $articuloId = $articulo->articuloId;
                $cantidad = $articulo->cantidad ?? 1;
                $stmtArt->bindParam(':packId', $packId);
                $stmtArt->bindParam(':articuloId', $articuloId);
                $stmtArt->bindParam(':cantidad', $cantidad);
                $stmtArt->execute();
            }
            
            $db->commit();
            $db = null;
            
            $datosRespuesta = array(
                'estado' => 1,
                'mensaje' => 'Pack creado correctamente.',
                'id' => $packId
            );
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // CREAR PACK A PARTIR DE UN PACK DEFAULT
    public function packCrearDesdeDefault(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $json = $request->getBody();
        $datos = json_decode($json);

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if ($rate = $this->throttleMutation($request, $response, 'packClonar', 6, 60)) {
            return $rate;
        }

        $packDefaultId = $datos->packDefaultId ?? null;
        $nuevoNombre = trim($datos->nombre ?? '');
        $modificaciones = $datos->modificaciones ?? []; // Artículos a modificar {articuloId, cantidad, accion: 'agregar'|'eliminar'|'modificar'}

        if (!$packDefaultId || !$nuevoNombre) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'PackDefaultId y nombre son obligatorios.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        try {
            $db = getConeccionCAB();
            
            // Obtener artículos del pack default
            $sqlArticulos = 'SELECT epa.ArticuloId, epa.Cantidad 
                            FROM dbo.esterilizacionPackArticulos epa
                            WHERE epa.PackDefaultId = :packDefaultId';
            $stmtArt = $db->prepare($sqlArticulos);
            $stmtArt->bindParam(':packDefaultId', $packDefaultId);
            $stmtArt->execute();
            $articulosBase = $stmtArt->fetchAll(\PDO::FETCH_OBJ);
            
            if (empty($articulosBase)) {
                $db = null;
                $datosRespuesta = array('estado' => 0, 'mensaje' => 'El pack default no tiene artículos configurados.');
                $response->getBody()->write(json_encode($datosRespuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Aplicar modificaciones
            $articulosFinal = [];
            foreach ($articulosBase as $art) {
                $articulosFinal[$art->ArticuloId] = $art->Cantidad;
            }
            
            foreach ($modificaciones as $mod) {
                $articuloId = $mod->articuloId;
                $accion = $mod->accion;
                $cantidad = $mod->cantidad ?? 1;
                
                switch ($accion) {
                    case 'eliminar':
                        unset($articulosFinal[$articuloId]);
                        break;
                    case 'modificar':
                        $articulosFinal[$articuloId] = $cantidad;
                        break;
                    case 'agregar':
                        $articulosFinal[$articuloId] = $cantidad;
                        break;
                }
            }
            
            // Convertir a array para la validación
            $articulosArray = [];
            foreach ($articulosFinal as $artId => $cant) {
                $obj = new \stdClass();
                $obj->articuloId = $artId;
                $obj->cantidad = $cant;
                $articulosArray[] = $obj;
            }
            
            // Validar si ya existe un pack con la misma composición
            $packIdExistente = null;
            if ($this->packExiste($articulosArray, $packIdExistente)) {
                $db = null;
                $datosRespuesta = array(
                    'estado' => 0,
                    'mensaje' => 'Ya existe un pack con la misma composición.',
                    'packExistente' => array('id' => $packIdExistente)
                );
                $response->getBody()->write(json_encode($datosRespuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }
            
            // Crear el nuevo pack
            $db->beginTransaction();
            
            // Generar ID
            $sqlGetMaxId = "SELECT MAX(CAST(Id AS INT)) as maxId FROM dbo.esterilizacionPacks WHERE ISNUMERIC(Id) = 1";
            $stmtMax = $db->prepare($sqlGetMaxId);
            $stmtMax->execute();
            $maxId = $stmtMax->fetchAll(\PDO::FETCH_OBJ);
            $nextId = ($maxId[0]->maxId ?? 0) + 1;
            $nuevoId = (string)$nextId;
            
            $sqlPack = 'INSERT INTO dbo.esterilizacionPacks (Id, Nombre, FechaCreacion) VALUES (:id, :nombre, GETDATE())';
            $stmtPack = $db->prepare($sqlPack);
            $stmtPack->bindParam(':id', $nuevoId);
            $stmtPack->bindParam(':nombre', $nuevoNombre);
            $stmtPack->execute();
            
            // Insertar artículos
            $sqlInsert = 'INSERT INTO dbo.esterilizacionPackArticulos (PackId, ArticuloId, Cantidad) VALUES (:packId, :articuloId, :cantidad)';
            $stmtInsert = $db->prepare($sqlInsert);
            
            foreach ($articulosFinal as $artId => $cant) {
                $stmtInsert->bindParam(':packId', $nuevoId);
                $stmtInsert->bindParam(':articuloId', $artId);
                $stmtInsert->bindParam(':cantidad', $cant);
                $stmtInsert->execute();
            }
            
            $db->commit();
            $db = null;
            
            $datosRespuesta = array(
                'estado' => 1,
                'mensaje' => 'Pack creado a partir del pack default correctamente.',
                'id' => $nuevoId
            );
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // CLONAR PACK (para modificar un artículo y crear uno nuevo)
    public function packClonar(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $json = $request->getBody();
        $datos = json_decode($json);

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if ($rate = $this->throttleMutation($request, $response, 'packActualizar', 10, 60)) {
            return $rate;
        }

        $packOrigenId = $datos->packOrigenId ?? null;
        $nuevoNombre = trim($datos->nombre ?? '');
        $modificaciones = $datos->modificaciones ?? [];

        if (!$packOrigenId || !$nuevoNombre) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'PackOrigenId y nombre son obligatorios.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        try {
            $db = getConeccionCAB();
            
            // Obtener artículos del pack origen
            $sqlArticulos = 'SELECT epa.ArticuloId, epa.Cantidad 
                            FROM dbo.esterilizacionPackArticulos epa
                            WHERE epa.PackId = :packId';
            $stmtArt = $db->prepare($sqlArticulos);
            $stmtArt->bindParam(':packId', $packOrigenId);
            $stmtArt->execute();
            $articulosBase = $stmtArt->fetchAll(\PDO::FETCH_OBJ);
            
            if (empty($articulosBase)) {
                $db = null;
                $datosRespuesta = array('estado' => 0, 'mensaje' => 'El pack origen no tiene artículos.');
                $response->getBody()->write(json_encode($datosRespuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Aplicar modificaciones
            $articulosFinal = [];
            foreach ($articulosBase as $art) {
                $articulosFinal[$art->ArticuloId] = $art->Cantidad;
            }
            
            foreach ($modificaciones as $mod) {
                $articuloId = $mod->articuloId;
                $accion = $mod->accion;
                $cantidad = $mod->cantidad ?? 1;
                
                switch ($accion) {
                    case 'eliminar':
                        unset($articulosFinal[$articuloId]);
                        break;
                    case 'modificar':
                        $articulosFinal[$articuloId] = $cantidad;
                        break;
                    case 'agregar':
                        $articulosFinal[$articuloId] = $cantidad;
                        break;
                }
            }
            
            // Convertir a array para validación
            $articulosArray = [];
            foreach ($articulosFinal as $artId => $cant) {
                $obj = new \stdClass();
                $obj->articuloId = $artId;
                $obj->cantidad = $cant;
                $articulosArray[] = $obj;
            }
            
            // Validar si ya existe un pack con la misma composición
            $packIdExistente = null;
            if ($this->packExiste($articulosArray, $packIdExistente)) {
                $db = null;
                $datosRespuesta = array(
                    'estado' => 0,
                    'mensaje' => 'Ya existe un pack con la misma composición.',
                    'packExistente' => array('id' => $packIdExistente)
                );
                $response->getBody()->write(json_encode($datosRespuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }
            
            // Crear el nuevo pack clonado
            $db->beginTransaction();
            
            $sqlGetMaxId = "SELECT MAX(CAST(Id AS INT)) as maxId FROM dbo.esterilizacionPacks WHERE ISNUMERIC(Id) = 1";
            $stmtMax = $db->prepare($sqlGetMaxId);
            $stmtMax->execute();
            $maxId = $stmtMax->fetchAll(\PDO::FETCH_OBJ);
            $nextId = ($maxId[0]->maxId ?? 0) + 1;
            $nuevoId = (string)$nextId;
            
            $sqlPack = 'INSERT INTO dbo.esterilizacionPacks (Id, Nombre, FechaCreacion) VALUES (:id, :nombre, GETDATE())';
            $stmtPack = $db->prepare($sqlPack);
            $stmtPack->bindParam(':id', $nuevoId);
            $stmtPack->bindParam(':nombre', $nuevoNombre);
            $stmtPack->execute();
            
            $sqlInsert = 'INSERT INTO dbo.esterilizacionPackArticulos (PackId, ArticuloId, Cantidad) VALUES (:packId, :articuloId, :cantidad)';
            $stmtInsert = $db->prepare($sqlInsert);
            
            foreach ($articulosFinal as $artId => $cant) {
                $stmtInsert->bindParam(':packId', $nuevoId);
                $stmtInsert->bindParam(':articuloId', $artId);
                $stmtInsert->bindParam(':cantidad', $cant);
                $stmtInsert->execute();
            }
            
            $db->commit();
            $db = null;
            
            $datosRespuesta = array(
                'estado' => 1,
                'mensaje' => 'Pack clonado correctamente.',
                'id' => $nuevoId
            );
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ACTUALIZAR PACK (nombre y/o artículos)
    public function packActualizar(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $json = $request->getBody();
        $datos = json_decode($json);

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $id = $datos->id ?? null;
        $nombre = trim($datos->nombre ?? '');
        $articulos = $datos->articulos ?? null; // Si se envía, se reemplazan todos

        if (!$id || !$nombre) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'ID y nombre son obligatorios.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $packNombreExistente = null;
        if ($this->packNombreExiste($nombre, $id, $packNombreExistente)) {
            $datosRespuesta = array(
                'estado' => 0,
                'mensaje' => 'Ya existe otro pack con ese nombre.',
                'packExistente' => array('id' => $packNombreExistente)
            );
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        try {
            $db = getConeccionCAB();
            $db->beginTransaction();
            
            // Actualizar nombre
            $sql = 'UPDATE dbo.esterilizacionPacks SET Nombre = :nombre WHERE Id = :id';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->execute();
            
            // Si se enviaron artículos, reemplazar todos
            if ($articulos !== null) {
                // Eliminar artículos existentes
                $sqlDelete = 'DELETE FROM dbo.esterilizacionPackArticulos WHERE PackId = :packId';
                $stmtDelete = $db->prepare($sqlDelete);
                $stmtDelete->bindParam(':packId', $id);
                $stmtDelete->execute();
                
                // Insertar nuevos artículos
            $sqlInsert = 'INSERT INTO dbo.esterilizacionPackArticulos (PackId, ArticuloId, Cantidad) VALUES (:packId, :articuloId, :cantidad)';
                $stmtInsert = $db->prepare($sqlInsert);
                
                foreach ($articulos as $art) {
                    $articuloId = $art->articuloId;
                    $cantidad = $art->cantidad ?? 1;
                    $stmtInsert->bindParam(':packId', $id);
                    $stmtInsert->bindParam(':articuloId', $articuloId);
                    $stmtInsert->bindParam(':cantidad', $cantidad);
                    $stmtInsert->execute();
                }
            }
            
            $db->commit();
            $db = null;
            
            $datosRespuesta = array('estado' => 1, 'mensaje' => 'Pack actualizado correctamente.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ELIMINAR PACK
    public function packEliminar(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $id = $request->getQueryParams()['id'] ?? null;

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if ($rate = $this->throttleMutation($request, $response, 'packEliminar', 5, 300)) {
            return $rate;
        }
        if ($confirm = $this->requireDestructiveConfirmation($request, $response)) {
            return $confirm;
        }

        if (!$id) {
            $datos = array('estado' => 0, 'mensaje' => 'ID no proporcionado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        try {
            $db = getConeccionCAB();
            $db->beginTransaction();
            
            // Eliminar relación con artículos
            $sqlDeleteRel = 'DELETE FROM dbo.esterilizacionPackArticulos WHERE PackId = :packId';
            $stmtRel = $db->prepare($sqlDeleteRel);
            $stmtRel->bindParam(':packId', $id);
            $stmtRel->execute();
            
            // Eliminar pack
            $sql = 'DELETE FROM dbo.esterilizacionPacks WHERE Id = :id';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $db->commit();
            $db = null;
            
            $datos = array('estado' => 1, 'mensaje' => 'Pack eliminado correctamente.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // LOTES
    public function loteCrear(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $json = $request->getBody();
        $datos = json_decode($json);

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $nombre = trim($datos->nombre ?? '');
        $observaciones = trim($datos->observaciones ?? '');
        $packs = $datos->packs ?? [];

        if ($nombre === '' || empty($packs)) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Nombre y packs son obligatorios.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        try {
            $db = getConeccionCAB();
            $db->beginTransaction();

            $sqlLote = 'INSERT INTO dbo.esterilizacionLote (Nombre, Observaciones, FechaCreacion) OUTPUT INSERTED.Id VALUES (:nombre, :observaciones, GETDATE())';
            $stmtLote = $db->prepare($sqlLote);
            $stmtLote->bindParam(':nombre', $nombre);
            $stmtLote->bindParam(':observaciones', $observaciones);
            $stmtLote->execute();
            $loteId = $stmtLote->fetchColumn();

            foreach ($packs as $packEntrada) {
                $packId = strval($packEntrada->packId ?? '');
                $cantidad = intval($packEntrada->quantity ?? 1);
                if ($packId === '' || $cantidad < 1) {
                    continue;
                }

                $sqlPack = 'SELECT p.Id, p.Nombre
                            FROM dbo.esterilizacionPacks p
                            WHERE p.Id = :id';
                $stmtPack = $db->prepare($sqlPack);
                $stmtPack->bindParam(':id', $packId);
                $stmtPack->execute();
                $pack = $stmtPack->fetch(\PDO::FETCH_OBJ);
                if (!$pack) {
                    throw new \Exception('Pack no encontrado: ' . $packId);
                }

                $sqlInsertLotePack = 'INSERT INTO dbo.esterilizacionLotePack (LoteId, PackOriginalId, NombrePackSnapshot, Cantidad)
                                      OUTPUT INSERTED.Id
                                      VALUES (:loteId, :packId, :nombrePack, :cantidad)';
                $stmtInsertLotePack = $db->prepare($sqlInsertLotePack);
                $stmtInsertLotePack->bindParam(':loteId', $loteId);
                $stmtInsertLotePack->bindParam(':packId', $packId);
                $stmtInsertLotePack->bindParam(':nombrePack', $pack->Nombre);
                $stmtInsertLotePack->bindParam(':cantidad', $cantidad);
                $stmtInsertLotePack->execute();
                $lotePackId = $stmtInsertLotePack->fetchColumn();

                $sqlArticulos = 'SELECT pa.ArticuloId, pa.Cantidad, a.nombre as Nombre
                                 FROM dbo.esterilizacionPackArticulos pa
                                 INNER JOIN dbo.esterilizacionArticulos a ON a.Id = pa.ArticuloId
                                 WHERE pa.PackId = :packId';
                $stmtArticulos = $db->prepare($sqlArticulos);
                $stmtArticulos->bindParam(':packId', $packId);
                $stmtArticulos->execute();
                $articulos = $stmtArticulos->fetchAll(\PDO::FETCH_OBJ);

                foreach ($articulos as $articulo) {
                    $sqlInsertArt = 'INSERT INTO dbo.esterilizacionLotePackArticulo (LotePackId, ArticuloOriginalId, NombreArticuloSnapshot, Cantidad)
                                     VALUES (:lotePackId, :articuloId, :nombreArticulo, :cantidad)';
                    $stmtInsertArt = $db->prepare($sqlInsertArt);
                    $stmtInsertArt->bindParam(':lotePackId', $lotePackId);
                    $stmtInsertArt->bindParam(':articuloId', $articulo->ArticuloId);
                    $stmtInsertArt->bindParam(':nombreArticulo', $articulo->Nombre);
                    $stmtInsertArt->bindParam(':cantidad', $articulo->Cantidad);
                    $stmtInsertArt->execute();
                }
            }

            $db->commit();
            $db = null;

            $datosRespuesta = array('estado' => 1, 'mensaje' => 'Lote creado correctamente.', 'id' => $loteId);
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // PACKS DEFAULT (esterilizacionPacksDefault)

    // VER TODOS LOS PACKS DEFAULT
    public function packsDefaultVerTodos(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $sql = 'SELECT pd.Id, pd.Nombre,
                (SELECT COUNT(*) FROM dbo.esterilizacionPackArticulos WHERE PackDefaultId = pd.Id) as CantidadArticulos
                FROM dbo.esterilizacionPacksDefault pd 
                ORDER BY pd.Nombre';

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $resultados = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;

            $response->getBody()->write(json_encode($resultados));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Throwable $e) {
            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // VER UN PACK DEFAULT CON SUS ARTICULOS
    public function packDefaultVerUno(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $id = $request->getQueryParams()['id'] ?? null;

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (!$id) {
            $datos = array('estado' => 0, 'mensaje' => 'ID no proporcionado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        try {
            $db = getConeccionCAB();
            
            $sqlPack = 'SELECT Id, Nombre FROM dbo.esterilizacionPacksDefault WHERE Id = :id';
            $stmtPack = $db->prepare($sqlPack);
            $stmtPack->bindParam(':id', $id);
            $stmtPack->execute();
            $pack = $stmtPack->fetchAll(\PDO::FETCH_OBJ);
            
            if (count($pack) == 0) {
                $db = null;
                $datos = array('estado' => 0, 'mensaje' => 'Pack Default no encontrado.');
                $response->getBody()->write(json_encode($datos));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            $sqlArticulos = 'SELECT epa.Id, epa.ArticuloId, epa.Cantidad, a.nombre as ArticuloNombre
                            FROM dbo.esterilizacionPackArticulos epa
                            INNER JOIN dbo.esterilizacionArticulos a ON epa.ArticuloId = a.id
                            WHERE epa.PackDefaultId = :id
                            ORDER BY a.nombre';
            $stmtArticulos = $db->prepare($sqlArticulos);
            $stmtArticulos->bindParam(':id', $id);
            $stmtArticulos->execute();
            $articulos = $stmtArticulos->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            
            $resultado = array(
                'id' => $pack[0]->Id,
                'nombre' => $pack[0]->Nombre,
                'articulos' => $articulos
            );
            
            $response->getBody()->write(json_encode($resultado));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // CREAR PACK DEFAULT
    public function packDefaultCrear(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $json = $request->getBody();
        $datos = json_decode($json);

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if ($rate = $this->throttleMutation($request, $response, 'packDefaultCrear', 4, 60)) {
            return $rate;
        }

        $id = trim($datos->id ?? '');
        $nombre = trim($datos->nombre ?? '');
        $articulos = $datos->articulos ?? []; // Array de {articuloId, cantidad}

        if (!$id || !$nombre) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'ID y nombre son obligatorios.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $articulosNormalizados = [];
        foreach ($articulos as $art) {
            $obj = new \stdClass();
            $obj->articuloId = $art->articuloId ?? null;
            $obj->cantidad = $art->cantidad ?? 1;
            if ($obj->articuloId) {
                $articulosNormalizados[] = $obj;
            }
        }

        $packNombreExistente = null;
        if ($this->packNombreExiste($nombre, null, $packNombreExistente)) {
            $datosRespuesta = array(
                'estado' => 0,
                'mensaje' => 'Ya existe otro pack o plantilla base con ese nombre.',
                'packExistente' => array('id' => $packNombreExistente)
            );
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        $packExistente = null;
        if (!empty($articulosNormalizados) && $this->packDefaultExiste($articulosNormalizados, $packExistente)) {
            $datosRespuesta = array(
                'estado' => 0,
                'mensaje' => 'Ya existe una plantilla base con la misma composición.',
                'packExistente' => array('id' => $packExistente)
            );
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        try {
            $db = getConeccionCAB();
            $db->beginTransaction();
            
            // Verificar si ya existe
            $sqlCheck = 'SELECT Id FROM dbo.esterilizacionPacksDefault WHERE Id = :id';
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->bindParam(':id', $id);
            $stmtCheck->execute();
            $existe = $stmtCheck->fetchAll(\PDO::FETCH_OBJ);
            
            if (count($existe) > 0) {
                $db = null;
                $datosRespuesta = array('estado' => 0, 'mensaje' => 'Ya existe un pack default con ese ID.');
                $response->getBody()->write(json_encode($datosRespuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }
            
            // Insertar pack default
            $sql = 'INSERT INTO dbo.esterilizacionPacksDefault (Id, Nombre) VALUES (:id, :nombre)';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->execute();
            
            // Insertar artículos si los hay
            if (!empty($articulosNormalizados)) {
                $sqlArt = 'INSERT INTO dbo.esterilizacionPackArticulos (PackDefaultId, ArticuloId, Cantidad) VALUES (:packId, :articuloId, :cantidad)';
                $stmtArt = $db->prepare($sqlArt);
                
                foreach ($articulosNormalizados as $art) {
                    $articuloId = $art->articuloId;
                    $cantidad = $art->cantidad ?? 1;
                    $stmtArt->bindParam(':packId', $id);
                    $stmtArt->bindParam(':articuloId', $articuloId);
                    $stmtArt->bindParam(':cantidad', $cantidad);
                    $stmtArt->execute();
                }
            }
            
            $db->commit();
            $db = null;
            
            $datosRespuesta = array(
                'estado' => 1,
                'mensaje' => 'Pack Default creado correctamente.',
                'id' => $id
            );
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ACTUALIZAR PACK DEFAULT
    public function packDefaultActualizar(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $json = $request->getBody();
        $datos = json_decode($json);

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if ($rate = $this->throttleMutation($request, $response, 'packDefaultActualizar', 8, 60)) {
            return $rate;
        }

        $id = $datos->id ?? null;
        $nombre = trim($datos->nombre ?? '');
        $articulos = $datos->articulos ?? null;

        if (!$id || !$nombre) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'ID y nombre son obligatorios.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        try {
            $db = getConeccionCAB();
            $db->beginTransaction();
            
            // Actualizar nombre
            $sql = 'UPDATE dbo.esterilizacionPacksDefault SET Nombre = :nombre WHERE Id = :id';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->execute();
            
            // Si se enviaron artículos, reemplazar todos
            if ($articulos !== null) {
                // Eliminar artículos existentes
                $sqlDelete = 'DELETE FROM dbo.esterilizacionPackArticulos WHERE PackDefaultId = :packId';
                $stmtDelete = $db->prepare($sqlDelete);
                $stmtDelete->bindParam(':packId', $id);
                $stmtDelete->execute();
                
                // Insertar nuevos artículos
                $sqlInsert = 'INSERT INTO dbo.esterilizacionPackArticulos (PackDefaultId, ArticuloId, Cantidad) VALUES (:packId, :articuloId, :cantidad)';
                $stmtInsert = $db->prepare($sqlInsert);
                
                foreach ($articulos as $art) {
                    $articuloId = $art->articuloId;
                    $cantidad = $art->cantidad ?? 1;
                    $stmtInsert->bindParam(':packId', $id);
                    $stmtInsert->bindParam(':articuloId', $articuloId);
                    $stmtInsert->bindParam(':cantidad', $cantidad);
                    $stmtInsert->execute();
                }
            }
            
            $db->commit();
            $db = null;
            
            $datosRespuesta = array('estado' => 1, 'mensaje' => 'Pack Default actualizado correctamente.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ELIMINAR PACK DEFAULT
    public function packDefaultEliminar(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $id = $request->getQueryParams()['id'] ?? null;

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if ($rate = $this->throttleMutation($request, $response, 'packDefaultEliminar', 4, 300)) {
            return $rate;
        }
        if ($confirm = $this->requireDestructiveConfirmation($request, $response)) {
            return $confirm;
        }

        if (!$id) {
            $datos = array('estado' => 0, 'mensaje' => 'ID no proporcionado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        try {
            $db = getConeccionCAB();
            $db->beginTransaction();
            
            // Eliminar relación con artículos
            $sqlDeleteRel = 'DELETE FROM dbo.esterilizacionPackArticulos WHERE PackDefaultId = :packId';
            $stmtRel = $db->prepare($sqlDeleteRel);
            $stmtRel->bindParam(':packId', $id);
            $stmtRel->execute();
            
            // Eliminar pack default
            $sql = 'DELETE FROM dbo.esterilizacionPacksDefault WHERE Id = :id';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $db->commit();
            $db = null;
            
            $datos = array('estado' => 1, 'mensaje' => 'Pack Default eliminado correctamente.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ARTICULOS DE PACK (operaciones directas)

    // AGREGAR ARTICULO A UN PACK EXISTENTE
    public function packArticuloAgregar(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $json = $request->getBody();
        $datos = json_decode($json);

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $packId = $datos->packId ?? null;
        $packDefaultId = $datos->packDefaultId ?? null;
        $articuloId = $datos->articuloId ?? null;
        $cantidad = $datos->cantidad ?? 1;

        if ((!$packId && !$packDefaultId) || !$articuloId) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Se requiere packId o packDefaultId, y articuloId.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        try {
            $db = getConeccionCAB();
            
            // Verificar si ya existe
            $sqlCheck = 'SELECT Id FROM dbo.esterilizacionPackArticulos 
                        WHERE ((PackId = :packId AND :packId IS NOT NULL) OR (PackDefaultId = :packDefaultId AND :packDefaultId IS NOT NULL))
                        AND ArticuloId = :articuloId';
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->bindParam(':packId', $packId);
            $stmtCheck->bindParam(':packDefaultId', $packDefaultId);
            $stmtCheck->bindParam(':articuloId', $articuloId);
            $stmtCheck->execute();
            $existe = $stmtCheck->fetchAll(\PDO::FETCH_OBJ);
            
            if (count($existe) > 0) {
                $db = null;
                $datosRespuesta = array('estado' => 0, 'mensaje' => 'El artículo ya existe en este pack.');
                $response->getBody()->write(json_encode($datosRespuesta));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }
            
            $sql = 'INSERT INTO dbo.esterilizacionPackArticulos (PackId, PackDefaultId, ArticuloId, Cantidad) 
                    VALUES (:packId, :packDefaultId, :articuloId, :cantidad);
                    SELECT SCOPE_IDENTITY() as Id';
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':packId', $packId);
            $stmt->bindParam(':packDefaultId', $packDefaultId);
            $stmt->bindParam(':articuloId', $articuloId);
            $stmt->bindParam(':cantidad', $cantidad);
            $stmt->execute();
            $resultado = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            
            $datosRespuesta = array(
                'estado' => 1,
                'mensaje' => 'Artículo agregado correctamente.',
                'id' => $resultado[0]->Id
            );
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ACTUALIZAR CANTIDAD DE ARTICULO EN UN PACK
    public function packArticuloActualizar(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $json = $request->getBody();
        $datos = json_decode($json);

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $id = $datos->id ?? null;
        $cantidad = $datos->cantidad ?? null;

        if (!$id || !$cantidad) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => 'ID y cantidad son obligatorios.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $sql = 'UPDATE dbo.esterilizacionPackArticulos SET Cantidad = :cantidad WHERE Id = :id';

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':cantidad', $cantidad);
            $stmt->execute();
            $db = null;

            $datosRespuesta = array('estado' => 1, 'mensaje' => 'Cantidad actualizada correctamente.');
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            $datosRespuesta = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datosRespuesta));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // ELIMINAR ARTICULO DE UN PACK
    public function packArticuloEliminar(Request $request, Response $response, $args)
    {
        $tokenAcceso = $request->getHeader('TokenAcceso');
        $id = $request->getQueryParams()['id'] ?? null;

        if (!isset($tokenAcceso[0]) || verificarToken($tokenAcceso[0]) === false) {
            $datos = array('estado' => 0, 'mensaje' => 'Acceso denegado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (!$id) {
            $datos = array('estado' => 0, 'mensaje' => 'ID no proporcionado.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        $sql = 'DELETE FROM dbo.esterilizacionPackArticulos WHERE Id = :id';

        try {
            $db = getConeccionCAB();
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $db = null;

            $datos = array('estado' => 1, 'mensaje' => 'Artículo eliminado del pack correctamente.');
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\PDOException $e) {
            $datos = array('estado' => 0, 'mensaje' => $e->getMessage());
            $response->getBody()->write(json_encode($datos));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
