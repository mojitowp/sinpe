<?php

namespace Mojito\ExchangeRate;

class Factory{

    static function create ( ProviderTypes $provider ) {

        switch( $provider ) {
            case ProviderTypes::CR_Hacienda:
                return new Hacienda();

            case ProviderTypes::CR_BCCR:
                return new BCCR();

            default:
                return null;
        }
    }
}