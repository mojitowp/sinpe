<?php

namespace Mojito\ExchangeRate;

class Hacienda extends Provider{

    public function __construct(){
        $this->url = 'https://api.hacienda.go.cr/indicadores/tc';
        $this->params = array();
    }

    public function getRates(){
        
        // Inicializar sesión cURL
        $ch = curl_init($this->url);

        // Configurar opciones de cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Para que curl_exec() devuelva el resultado de la transferencia como string
        curl_setopt($ch, CURLOPT_HEADER, false); // No incluir los headers en el resultado
        curl_setopt($ch, CURLOPT_USERAGENT, 'Wget/1.21.2 (linux-gnu)'); // Simular User-Agent de wget

        // Ejecutar sesión cURL
        $response = curl_exec($ch);

        // Verificar si ocurrió algún error durante la ejecución
        if(curl_errno($ch)){
            error_log('Error en cURL: ' . curl_error($ch));
            return false;
        }

        // Cerrar sesión cURL
        curl_close($ch);

        return json_decode( $response );
    }
}