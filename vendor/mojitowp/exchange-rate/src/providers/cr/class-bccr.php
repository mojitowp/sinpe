<?php

namespace Mojito\ExchangeRate;

class BCCR extends Provider{

    public function __construct(){
        $this->url = 'https://gee.bccr.fi.cr/Indicadores/Suscripciones/WS/wsindicadoreseconomicos.asmx/ObtenerIndicadoresEconomicos';
        $this->params = array();
    }

    public function getRates(){
        if ( ! empty( $this->params ) ) {
            $this->url .= '?';
            foreach ($this->params as $key => $value) {
                $this->url .= $key . '=' . $value . '&';
            }
            $this->url = rtrim( $this->url, '&' );
        }
        
        $data = file_get_contents( $this->url );
        $xml  = new \DOMDocument();
        $xml->loadXML( $data );

        $dolar_venta = $xml->getElementsByTagName('INGC011_CAT_INDICADORECONOMIC')
                            ->item(0)
                            ->getElementsByTagName('NUM_VALOR')
                            ->item(0)
                            ->nodeValue;

        $dolar_venta_fecha = $xml->getElementsByTagName('INGC011_CAT_INDICADORECONOMIC')
                            ->item(0)
                            ->getElementsByTagName('DES_FECHA')
                            ->item(0)
                            ->nodeValue;

        $dolar_compra = $xml->getElementsByTagName('INGC011_CAT_INDICADORECONOMIC')
                            ->item(1)
                            ->getElementsByTagName('NUM_VALOR')
                            ->item(0)
                            ->nodeValue;

        $dolar_compra_fecha = $xml->getElementsByTagName('INGC011_CAT_INDICADORECONOMIC')
                            ->item(1)
                            ->getElementsByTagName('DES_FECHA')
                            ->item(0)
                            ->nodeValue;

        $response = (object) [
            'dolar' => (object) [
                'venta' => (object) [
                    'valor' => $dolar_venta,
                    'fecha' => $dolar_venta_fecha
                ],
                'compra' => (object) [
                    'valor' => $dolar_compra,
                    'fecha' => $dolar_compra_fecha
                ]
            ]
        ];

        return $response;
    }
}