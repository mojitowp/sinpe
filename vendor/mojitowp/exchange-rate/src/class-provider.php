<?php

namespace Mojito\ExchangeRate;

abstract class Provider{

    protected string $url;

    protected array $params;

    public function setParams( array $params ){
        $this->params = $params;
    }

    abstract public function getRates();

}