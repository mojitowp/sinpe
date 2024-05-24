<?php

namespace Mojito\ExchangeRate;

class Factory{

    static function create ( ProviderTypes $provider ) {

        switch( $provider ) {
            case ProviderTypes::CR_Hacienda:
                return new Hacienda();

            case ProviderTypes::CR_BCCR:
                return new BCCR();

            case ProviderTypes::CR_Gometa:
                return new Gometa();

            default:
                return null;
        }
    }
}