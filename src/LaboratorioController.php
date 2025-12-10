<?php
namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


// Conexión a BD CAB en 105
function getConnectionCAB() {
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

// Conexión a BD MARKEY
function getConnectionMARKEY() {
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

function verificarToken($token){
    //busco el token de acceso a la API almacenado en la tabla parámetros.
    $con = 'EXEC sp_parametro_ver @nombre = \'tokenAcceso\'';
    try {
        $stmt = getConnectionTC()->query($con);
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


class LaboratorioController
{
    // ENVIAR ORDEN A THARSIS
    public function enviarOrdenTharsis(Request $request, Response $response, $args){
        $json           = json_decode($request->getBody());

        $id_factura         = $json->id_factura;
        $hc                 = $json->hc;
        $tipoDocumento      = $json->tipoDocumento;
        $documento          = $json->documento;
        $apellidoPaciente   = $json->apellidoPaciente;
        $nombrePaciente     = $json->nombrePaciente;
        $sexo               = $json->sexo;
        $fechaNacimiento    = $json->fechaNacimiento;
        $usuario            = $json->usuario;
        $codigoAmbito       = $json->codigoAmbito;
        $ambito             = $json->ambito;
        $id_medico          = $json->id_medico;
        $medico             = $json->medico;
        $fechaProgramada    = $json->fechaProgramada;

        $error = 0;
        $datos = array();

        // busco el dni del paciente en Markey para enviar el dni correcto a Tharsis.
        

        
        $jsonAbbott = '{"datossesion": {
                                "accionelemento": "Autenticar",
                                "elemento": {
                                "usuario": "GENERICO",
                                "clave": "KWepkT3MY8PU6m8crWhJI3y8Cg+iGKuqGy0ofL1qWLOC6ebgeSc="
                                }
                            },
                            "actualizarorden":{
                                "datospoblador":{
                                    "tipodocumento":"[TIPODOCUMENTO]",
                                    "numerodocumento":"[NRODOCUMENTO]",
                                    "primerapellido":"[APELLIDOPACIENTE]",
                                    "segundoapellido":"",
                                    "primernombre":"[NOMBREPACIENTE]",
                                    "segundonombre":"",
                                    "direccion":"",
                                    "telefono":"",
                                    "email":"",
                                    "pacienteidexterno":"[NRODOCUMENTO]",
                                    "genero":"[SEXO]",
                                    "fechanacimiento":"[FECHANACIMIENTO]"
                                },
                                "datosorden":{
                                    "codigosucursal":"1",
                                    "descripcionsucursal":"CLINICA ADVENTISTA BELGRANO",
                                    "idexterno":"B[IDFACTURA]",
                                    "codigoubicacion":"1",
                                    "descripcionubicacion":"CLINICA ADVENTISTA BELGRANO",
                                    "codigoprofesional":"[NROMEDICO]",
                                    "nombreprofesional":"[MEDICO]",
                                    "codigoservicio":"",
                                    "descripcionservicio":"",
                                    "codigoorigenpaciente":"[CODIGOAMBITO]",
                                    "descripcionorigenpaciente":"[AMBITO]",
                                    "codigotipofisiologico":"",
                                    "descripciontipofisiologico":"",
                                    "codigodiagnostico":"",
                                    "descripciondiagnostico":"",
                                    "urgente":"[URGENTE]",
                                    "codigoconvenio":"",
                                    "descripcionconvenio":"",
                                    "observacion":"",
                                    "turno":"",
                                    "usuariocreo":"[USUARIO]",
                                    "archivostreambase64":"",
                                    "fechaalta":"[FECHAALTA]"
                                },
                                "listaestudios":[LISTAESTUDIOS]
                            }
        }';
    
    
        
        // Tipo de documento
        $jsonAbbott = str_replace('[TIPODOCUMENTO]',$tipoDocumento,$jsonAbbott);

        // Número de documento
        $jsonAbbott = str_replace('[NRODOCUMENTO]',$documento,$jsonAbbott);

        // Apellido del paciente
        $jsonAbbott = str_replace('[APELLIDOPACIENTE]',$apellidoPaciente,$jsonAbbott);

        // Nombre del paciente
        $jsonAbbott = str_replace('[NOMBREPACIENTE]',$nombrePaciente,$jsonAbbott);

        // Fecha de nacimiento
        $jsonAbbott = str_replace('[FECHANACIMIENTO]',$fecha,$jsonAbbott);

        // Sexo
        $jsonAbbott = str_replace('[SEXO]',$sexo,$jsonAbbott);

        // ID Factura
        $jsonAbbott = str_replace('[IDFACTURA]',$id_factura,$jsonAbbott);

        // Codigo del Ámbito
        $jsonAbbott = str_replace('[CODIGOAMBITO]',$codigoAmbito,$jsonAbbott);
        
        // Ámbito
        $jsonAbbott = str_replace('[AMBITO]',$ambito,$jsonAbbott);

        // URGENTE
        if($codigoAmbito == '3'){
            $jsonAbbott = str_replace('[URGENTE]','S',$jsonAbbott);
        }else{
            $jsonAbbott = str_replace('[URGENTE]','N',$jsonAbbott);
        }
        
        // Usuario
        $jsonAbbott = str_replace('[USUARIO]',$usuario,$jsonAbbott);

        // NRO Medico
        $jsonAbbott = str_replace('[NROMEDICO]',$id_medico,$jsonAbbott);

        // nombre medico
        $jsonAbbott = str_replace('[MEDICO]',$medico,$jsonAbbott);  

        // fecha programada
        $jsonAbbott = str_replace('[FECHAALTA]',$fechaProgramada,$jsonAbbott);  

        
        try {
            // busco las prácticas de la orden en la tabla temporal
            $sql = 'select * from CAB.dbo.lab_TempOrdenesAbbottDetalle where id_factura = :id_factura';
            $db = getConnectionCAB();
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id_factura', $id_factura);
            $stmt->execute();
            $resu = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $db = null;
            
            $listaPracticas = array();
            if(count($resu) > 0){
                foreach($resu as $pra){
                    $p = new \stdClass();
                    $p->idestudio = $pra->cod_nomenclador;
                    array_push($listaPracticas,$p);
                    unset($p);
                }
            }
    
            // PRACTICAS
            $jsonAbbott = str_replace('[LISTAESTUDIOS]',json_encode($listaPracticas),$jsonAbbott);

            // echo $jsonAbbott;
        
            
            // verifico si la orden que quiero enviar ya fue enviada. Si ya fue enviada verifico las determinaciones que la componen. Se pueden dar dos situaciones:
            // 1) que en el nuevo envío las determinaciones sean iguales a las ya enviadas. No envio nada ya que no hay cambios en la orden.
            // 2) que en el nuevo envío las determinaciones sean distintas (al menos una es diferente). Entonces elimino la orden y la reenvio.

            $sqlVerificaion = 'declare @resultado int
                    EXEC @resultado = lab_ValidarOrdenes @id_factura = :id_factura
                    select @resultado as resultado';

            //echo $sqlVerificaion;
                    
            $db = getConnectionCAB();
            $stmt = $db->prepare($sqlVerificaion);
            $stmt->bindParam(':id_factura', $id_factura);
            $stmt->execute();
            $resultadoVerificacion = $stmt->fetchAll(\PDO::FETCH_OBJ);
            foreach($resultadoVerificacion as $rv){
                $resultado = $rv->resultado;
            }

            // $resultado = 0: no fue enviada -> Enviar
            // $resultado = 1: fue enviada y no hay diferencias -> No enviar
            // $resultado = 2: fue enviada y hay diferencias -> Eliminar y ReEnviar

            // NO FUE ENVIADA => ENVIAR
            if($resultado == 0){ 
                require_once '../class/Abbott.php';
                $Abbott = new Abbott;
                $ordenEnviada = $Abbott->crearOrden2($jsonAbbott);           

                // respuesta del envío
                $status = $ordenEnviada['status_code'];
                $json_respuesta = json_encode($ordenEnviada['respuesta']);

                // parse de json to get the response message
                $res_0 = json_decode($json_respuesta, true);
                $res_1 = $res_0['result'][0];
                $res_2 = $res_1['resultadoactualizarorden'];
                $res_3 = $res_2['resultado'];


                //guardo en el tabla lab_OrdenesAbbott        
                $sql = 'EXEC lab_OrdenesAbbott_new_v2
                                @id_factura = '.$id_factura.',
                                @nro_documento = \''.$documento.'\',
                                @apellido = \''.$apellidoPaciente.'\',
                                @nombre = \''.$nombrePaciente.'\',
                                @fechaProgramada = \''.$fechaProgramada.'\',
                                @json_enviado = \''.$jsonAbbott.'\',
                                @status_code = '.$status.',
                                @json_respuesta = \''.$json_respuesta.'\',
                                @resultado = \''.$res_3.'\'';
                        
                $db = getConnectionCAB();
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':id_factura', $id_factura);
                $stmt->bindParam(':nro_documento', $documento);
                $stmt->bindParam(':apellido', $apellidoPaciente);
                $stmt->bindParam(':nombre', $nombrePaciente);
                $stmt->bindParam(':fechaProgramada', $fechaProgramada);
                $stmt->bindParam(':json_enviado', $jsonAbbott);
                $stmt->bindParam(':status_code', $status);
                $stmt->bindParam(':json_respuesta', $json_respuesta);
                $stmt->bindParam(':resultado', $res_3);
                $stmt->execute();
                
                $datos = array(
                    'estado' => $status,
                    'mensaje' => $res_3
                );
        
                return $response->withJson($datos,$status);
            }

            // NO ENVIAR PORQUE YA FUE ENVIADA Y NO HAY DIFERENCIAS
            if($resultado == 1){
                $datos = array(
                    'estado' => 200,
                    'mensaje' => 'La orden ya existe en Tharsis y es igual a la que intenta enviar. No se realizaron cambios.');     
                return $response->withJson($datos,200);
            }


            // ELIMINAR Y RE ENVIAR - Porque ya había sido enviada pero hay diferencias
            if($resultado == 2){
                require_once '../class/Abbott.php';

                // Elimino la orden            
                //$jsonOrdenEliminar = '{"anularorden": [{"idexterno":"B'.$id_factura.'","idmotivo":"1","listaestudios":[]}]}';
                $Abbott = new Abbott;
                $ordenEliminada = $Abbott->eliminarOrden($id_factura);           

                $status = $ordenEliminada['status_code'];
                
                if($status == 200){
                    $json_respuesta = json_encode($ordenEliminada['respuesta']);

                    $res_0 = json_decode($json_respuesta, true);
                    $res_1 = $res_0['result'][0];
                    $res_2 = $res_1['resultadoanularorden'];
                    $res_3 = $res_2['resultado'];


                    // Elimino el registro de lab_OrdenesAbbott para que quede como que no se envió y pueda enviarlo. (También elimino el detalle de la orden)
                    $consulta = 'EXEC lab_EliminarOrdenAbbott @id_factura = :id_factura';
                    $db = getConnection2();
                    $stmt = $db->prepare($consulta);
                    $stmt->bindParam("id_factura", $id_factura);
                    $stmt->execute();

                    // vuelvo a enviar la orden a Abbott con las determinaciones correspondientes.
                    $ordenCreada = $Abbott->crearOrden2($jsonAbbott);           
                    $status = $ordenCreada['status_code'];
                    $json_respuesta = json_encode($ordenCreada['respuesta']);

                    $res_0 = json_decode($json_respuesta, true);
                    $res_1 = $res_0['result'][0];
                    $res_2 = $res_1['resultadoactualizarorden'];
                    $res_3 = $res_2['resultado'];

                    // guardo en la tabla lab_OrdenesAbbott la nueva orden con su detalle        
                    $sql = 'EXEC lab_OrdenesAbbott_new_v2
                                    @id_factura = '.$id_factura.',
                                    @nro_documento = \''.$documento.'\',
                                    @apellido = \''.$apellidoPaciente.'\',
                                    @nombre = \''.$nombrePaciente.'\',
                                    @fechaProgramada = \''.$fechaProgramada.'\',
                                    @json_enviado = \''.$jsonAbbott.'\',
                                    @status_code = '.$status.',
                                    @json_respuesta = \''.$json_respuesta.'\',
                                    @resultado = \''.$res_3.'\'';
                            
                    $stmt = getConnection2()->query($sql);
                    
                    $datos = array(
                        'estado' => $status,
                        'mensaje' => $res_3);
            
                    return $response->withJson($datos,$status);
                }else{
                    $datos = array(
                        'estado' => $status,
                        'mensaje' => 'Ha ocurrido un error que impide que la orden sea modificada.');
            
                    return $response->withJson($datos,$status);
                }            
            }
        } catch(PDOException $e) {         
            $datos = array(
                'estado' => 500,
                'mensaje' => $e->getMessage()
            );
    
            return $response->withJson($datos,500);
        }
        
        
    }
}