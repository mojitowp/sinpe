<?php

namespace Mojito\ExchangeRate;

enum ProviderTypes: string {
    case CR_Hacienda = 'CR\Hacienda';
    case CR_BCCR = 'CR\BCCR';
    case CR_Gometa = 'CR\Gometa';
}
