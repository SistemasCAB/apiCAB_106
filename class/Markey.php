<?php
class Markey{
    public $resultadoLogin;  
    public $camasMarkey;
    public $camas;
    private $url = 'https://clinicaadventista.markey.com.ar/APIMarkeyCAB/api/login?APIKey=d5e75bde-205b-4468-86ec-67e7160bad2e&';  

    public function loginMarkey($dni,$clave){
        $conexion = curl_init();
        $headers = array(
             'Content-Type: application/json'
         );
        curl_setopt($conexion, CURLOPT_URL,$this->url.'Usuario='.$dni.'&Password='.$clave);
        curl_setopt($conexion, CURLOPT_POST, true);
        curl_setopt($conexion, CURLOPT_HTTPHEADER,$headers);
        curl_setopt($conexion, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($conexion, CURLOPT_POSTFIELDS,'{"usuario":"cab"}');
        curl_setopt($conexion, CURLOPT_SSL_VERIFYPEER, false);
        $respuesta = curl_exec($conexion);
        if($respuesta === FALSE){
            die('Curl failed: '. curl_error($conexion));
        }
        
        $this->resultadoLogin  = json_decode($respuesta);
        curl_close($conexion);
        
        return $this->resultadoLogin;
    }

    public function getCamasMarkey(){           
        $conexion = curl_init();
            
        curl_setopt_array($conexion, array(
        CURLOPT_URL => 'https://clinicaadventista.markey.com.ar/APIMarkeyCAB/api/camas/obtenercamas?APIKey=d5e75bde-205b-4468-86ec-67e7160bad2e',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $respuesta = curl_exec($conexion);
        
        if($respuesta === FALSE){
            die('Curl failed: '. curl_error($conexion));
        }

        $status = curl_getinfo($conexion, CURLINFO_HTTP_CODE);
        
        curl_close($conexion);

        if($status == 200){
            $this->camasMarkey  = json_decode($respuesta);
        }else{
            $json_vacio = array();
            $this->camas  = $json_vacio;
        }

        return $this->camasMarkey;
    }

    public function getCamasHabitacion($idHabitacion){               
        
        $ws = 'https://clinicaadventista.markey.com.ar/APIMarkeyCAB/api/camas/obtenercamas?APIKey=d5e75bde-205b-4468-86ec-67e7160bad2e&id_habitacion='.$idHabitacion;

        $conexion = curl_init();
        curl_setopt_array($conexion, array(
        CURLOPT_URL => $ws,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $respuesta = curl_exec($conexion);
        
        if($respuesta === FALSE){
            die('Curl failed: '. curl_error($conexion));
        }

        $status = curl_getinfo($conexion, CURLINFO_HTTP_CODE);
        
        curl_close($conexion);

        if($status == 200){
            $this->camas  = json_decode($respuesta);
        }else{
            $json_vacio = array();
            $this->camas  = $json_vacio;
        }

        return $this->camas;
    }

    public function cambiarEstado($idCama, $idEstado){
        //cambia  el estado de una cama en Markey. Si la operación es exitosa (status code 200), devuelve 1, sino 0
        $conexion = curl_init();
        $observaciones = '';
        
        if($idEstado == 3){
            $observaciones = 'REPARACIÓN';
        }
        
        if($idEstado == 4){
            $observaciones = 'LIMPIEZA';
        }

        if($idEstado == 1){
            $urlServicio = 'https://clinicaadventista.markey.com.ar/APIMarkeyCAB/api/camas/cambiarEstado?APIKey=d5e75bde-205b-4468-86ec-67e7160bad2e&id_cama='.$idCama.'&id_estado='.$idEstado;
        }else{
            $urlServicio = 'https://clinicaadventista.markey.com.ar/APIMarkeyCAB/api/camas/cambiarEstado?APIKey=d5e75bde-205b-4468-86ec-67e7160bad2e&id_cama='.$idCama.'&id_estado='.$idEstado.'&observaciones='.rawurlencode($observaciones);
        }
        
        $headers = array(
            'Content-Type: application/json',
            'User-Agent: PHP-CURL'
        );

        curl_setopt_array($conexion, array(
        CURLOPT_URL => $urlServicio,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => array_merge($headers, ['Content-Length: 0']),
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        ));

        $respuesta = curl_exec($conexion);
        $status = curl_getinfo($conexion, CURLINFO_HTTP_CODE);
        $curlError = curl_error($conexion);
        
        // Debug: loguear URL, status code y respuesta
        error_log("cambiarEstado - URL: $urlServicio | Status: $status | Respuesta: $respuesta | cURL Error: $curlError");
        
        if($respuesta === FALSE){
            error_log('Curl failed in cambiarEstado: '. $curlError);
            curl_close($conexion);
            return 0;
        }

        curl_close($conexion);

        if($status == 200){
            $resultado  = 1;
        }else{
            $resultado = 0;
        }

        return $resultado;
    }

    public function altaDefinitiva($paciCodigo, $idTipoAlta, $idInternacion, $altaPorDni, $fechaAltaDefinitiva){
        // Registra el alta definitiva en Markey. Si la operación es exitosa (status code 200), devuelve 1, sino 0
        $conexion = curl_init();
                
        $params = array(
            'APIKey' => 'd5e75bde-205b-4468-86ec-67e7160bad2e',
            'Id_Paciente' => $paciCodigo,
            'id_tipoAlta' => $idTipoAlta,
            'id_internacion' => $idInternacion,
            'id_Usuario' => $altaPorDni,
            'fecha_hora_alta' => $fechaAltaDefinitiva
        );
        
        $urlServicio = 'https://clinicaadventista.markey.com.ar/APIMarkeyCAB/api/altaDefinitiva?' . http_build_query($params);
        
        $headers = array(
            'Content-Type: application/json',
            'User-Agent: PHP-CURL'
        );

        curl_setopt_array($conexion, array(
        CURLOPT_URL => $urlServicio,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => array_merge($headers, ['Content-Length: 0']),
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        ));

        $respuesta = curl_exec($conexion);
        $status = curl_getinfo($conexion, CURLINFO_HTTP_CODE);
        $curlError = curl_error($conexion);
        
        // Debug: loguear URL, status code y respuesta
        error_log("altaDefinitiva - URL: $urlServicio | Status: $status | Respuesta: $respuesta | cURL Error: $curlError");
       
        if($respuesta === FALSE){
            error_log('Curl failed in altaDefinitiva: '. $curlError);
            curl_close($conexion);
            return 0;
        }

        curl_close($conexion);

        if($status == 200){
            $resultado  = 1;
        }else{
            $resultado = 0;
        }

        return $resultado;
    }
}
?>