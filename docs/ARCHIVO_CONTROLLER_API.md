# ArchivoController - Método pacienteUltimaAtencion

## Descripción General
Obtiene la información del paciente junto con datos de su última atención médica. Requiere autenticación mediante token y un número de historia clínica (HC).

---

## Información Técnica

**Clase:** `App\V2\ArchivoController`  
**Método:** `pacienteUltimaAtencion(Request $request, Response $response, $args)`  
**Tipo de Solicitud:** GET  
**Base de Datos:** SQL Server (CAB05)  
**Stored Procedure:** `pacienteUltimaAtencion`

---

## Parámetros de Entrada

### Headers Requeridos
| Header | Tipo | Obligatorio | Descripción |
|--------|------|-------------|-------------|
| `TokenAcceso` | string | ✅ Sí | Token de autenticación del usuario |

### Parámetros de Ruta
| Parámetro | Tipo | Obligatorio | Descripción |
|-----------|------|-------------|-------------|
| `hc` | string/integer | ✅ Sí | Número de historia clínica del paciente |

---

## Validaciones Realizadas

1. **Token de Acceso**
   - Verifica que el header `TokenAcceso` esté presente
   - Valida que el token sea correcto usando `verificarToken()`

2. **Parámetro HC**
   - Valida que el parámetro `hc` no esté vacío

---

## Response - Casos de Éxito (HTTP 200)

```json
[
  {
    "ultimaAtencion": "2024-04-15",
    "hc": "12345",
    "paciente": "Juan Pérez García",
    "tipoDocumento": "DNI",
    "nroDocumento": "12345678",
    "sexo": "M",
    "fechaNacimiento": "1980-05-20",
    "pacientesMarkey": [
      {
        "deviceId": "MAC123",
        "deviceName": "Dispositivo 1"
      }
    ]
  }
]
```

### Campos Retornados
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `ultimaAtencion` | date | Fecha de la última atención del paciente |
| `hc` | string | Número de historia clínica |
| `paciente` | string | Nombre completo del paciente |
| `tipoDocumento` | string | Tipo de documento (DNI, Pasaporte, etc.) |
| `nroDocumento` | string | Número de documento |
| `sexo` | string | Sexo del paciente (M/F) |
| `fechaNacimiento` | date | Fecha de nacimiento del paciente |
| `pacientesMarkey` | array | Dispositivos Markey asociados (array vacío si no hay) |

---

## Response - Casos de Error

### Error 400 - HC Vacío
```json
{
  "estado": 0,
  "mensaje": "El número de HC es un dato obligatorio."
}
```

### Error 403 - Token No Proporcionado
```json
{
  "estado": 0,
  "mensaje": "Acceso denegado."
}
```

### Error 403 - Token Inválido
```json
{
  "estado": 0,
  "mensaje": "Acceso denegado."
}
```

### Error 500 - Error en Base de Datos
```json
{
  "estado": 0,
  "mensaje": "[Mensaje de error específico de PDO]"
}
```

---

## Códigos HTTP Retornados

| Código | Descripción |
|--------|-------------|
| **200** | Operación exitosa, datos retornados |
| **400** | Parámetro obligatorio faltante o vacío |
| **403** | Token no proporcionado o inválido |
| **500** | Error en la base de datos o en el procedimiento almacenado |

---

## Ejemplo de Uso

### Solicitud cURL
```bash
curl -X GET "http://api.dominio.com/v2/paciente/12345/ultimaAtencion" \
  -H "TokenAcceso: abc123def456" \
  -H "Content-Type: application/json"
```

### Solicitud en JavaScript/Fetch
```javascript
const hc = "12345";
const tokenAcceso = "abc123def456";

fetch(`/v2/paciente/${hc}/ultimaAtencion`, {
  method: 'GET',
  headers: {
    'TokenAcceso': tokenAcceso,
    'Content-Type': 'application/json'
  }
})
.then(response => response.json())
.then(data => {
  if (data.estado === 0) {
    console.error('Error:', data.mensaje);
  } else {
    console.log('Datos del paciente:', data);
  }
})
.catch(error => console.error('Error en la solicitud:', error));
```

### Solicitud en PHP
```php
$hc = "12345";
$tokenAcceso = "abc123def456";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://api.dominio.com/v2/paciente/{$hc}/ultimaAtencion");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'TokenAcceso: ' . $tokenAcceso,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (!empty($data)) {
    echo "Última atención: " . $data[0]['ultimaAtencion'];
}
```

---

## Notas Importantes

### 📌 Dependencias
- La función requiere la función `verificarToken()` del archivo `Common/helpers.php`
- La función requiere la función `getConeccionCAB05()` para conectarse a SQL Server

### 📌 Gestión de Errores
- Los errores de PDO son capturados y retornados al cliente
- No se registran específicamente en logs (considera añadirlo)

### 📌 Seguridad
- El acceso está protegido por token de autenticación
- El token se valida en cada solicitud
- Los parámetros se vinculan con prepared statements (previene SQL injection)

### 📌 Dispositivos Markey
- El campo `pacientesMarkey` es JSON en la BD y se decodifica a array
- Si no hay dispositivos asociados, se retorna un array vacío
- Cada dispositivo contiene información del equipo Markey del paciente

### ⚠️ Consideraciones de Rendimiento
- La consulta es directa al SP, sin cachés
- Si se espera alto volumen, considerar implementar cachés
- El SP en BD es responsable del rendimiento de la consulta

### 🔄 Respuesta del SP
- El SP `pacienteUltimaAtencion` debe retornar los campos mencionados
- Si el SP retorna múltiples registros, todos se incluyen en el array JSON

---

## Flujo de Ejecución

```
1. Cliente envía solicitud con TokenAcceso en header y hc en parámetro
   ↓
2. Sistema valida que TokenAcceso esté presente
   ↓
3. Sistema valida el token mediante verificarToken()
   ↓
4. Si token inválido → HTTP 403
   ↓
5. Sistema valida que hc no esté vacío
   ↓
6. Si hc vacío → HTTP 400
   ↓
7. Sistema ejecuta SP 'pacienteUltimaAtencion' con parámetro hc
   ↓
8. Datos se transforman a objetos stdClass
   ↓
9. pacientesMarkey se decodifica de JSON a array
   ↓
10. Response se retorna como JSON → HTTP 200
    ↓
11. En caso de error PDO → HTTP 500
```

---

## Preguntas Frecuentes

**P: ¿Qué pasa si el paciente no tiene últimas atenciones?**  
R: El SP retorna datos vacíos o la consulta puede no retornar registros. Valida la respuesta antes de acceder a los datos.

**P: ¿Puedo usar otro tipo de documento que no sea DNI?**  
R: Sí, el sistema soporta múltiples tipos de documentos según lo configurado en la BD.

**P: ¿Cómo genero un TokenAcceso?**  
R: Consulta con el equipo de autenticación. La generación del token está en la función `verificarToken()`.

**P: ¿El método soporta múltiples pacientes en una solicitud?**  
R: No, acepta un solo HC por solicitud.

---

## Última Actualización
Documento creado: 2024  
Versión del Método: v2 (API CAB v2)
