<?php
class Markey{
    public $resultadoLogin;  
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
}
?>