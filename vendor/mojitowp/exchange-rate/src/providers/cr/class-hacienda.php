<?php

namespace Mojito\ExchangeRate;

class Hacienda extends Provider{

    public function __construct(){
        $this->url = 'https://api.hacienda.go.cr/indicadores/tc';
        $this->params = array();
    }

    public function getRates(){
        return json_decode( file_get_contents( $this->url ) );
    }
}