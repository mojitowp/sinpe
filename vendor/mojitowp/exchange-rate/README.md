# Mojito Exchange Rate

Paquete de consulta de tipo de cambio, por ahora funciona para el Ministerio de Hacienda y el Banco Central de Costa Rica pero es extendible.


## Instalación
```
composer require mojitowp/exchange-rate
```

## Uso
### Tipo de cambio del Ministerio de Hacienda

```
use Mojito\ExchangeRate\Factory;
use Mojito\ExchangeRate\ProviderTypes;

$rates = Factory::create( ProviderTypes::CR_Hacienda );
$data = $rates->getRates();

echo print_r( $data, true );
```

Esto imprime el siguiente objecto:
```
stdClass Object
(
    [dolar] => stdClass Object
        (
            [venta] => stdClass Object
                (
                    [fecha] => 2023-02-28 00:00:00
                    [valor] => 564.27
                )

            [compra] => stdClass Object
                (
                    [fecha] => 2023-02-28 00:00:00
                    [valor] => 556.4
                )

        )

    [euro] => stdClass Object
        (
            [fecha] => 2023-02-28T00:00:00-06:00
            [dolares] => 1.0634
            [colones] => 600.04
        )

)
```


Acceder a los valores:
```
echo "Dólar venta: " . $data->dolar->venta->valor;
echo PHP_EOL;
echo "Dólar compra: " . $data->dolar->venta->valor;
echo PHP_EOL;
```

El resultado sería:
```
Dólar venta: 564.27
Dólar compra: 564.27
```


### Tipo de cambio del Banco Central de Costa Rica

Para poder obtener tipos de cambio usando el web service del Banco Central de Costa Rica primero deberá registrarse, puede encontrar ayuda en [Esta Guía](https://gee.bccr.fi.cr/indicadoreseconomicos/Documentos/DocumentosMetodologiasNotasTecnicas/Webservices_de_indicadores_economicos.pdf)

```
use Mojito\ExchangeRate\Factory;
use Mojito\ExchangeRate\ProviderTypes;

$rates = Factory::create( ProviderTypes::CR_BCCR );
$rates->setParams( array( 
    'Indicador' => 317,
    'FechaInicio' => '27/02/2023',
    'FechaFinal' => '28/02/2023',
    'Nombre' => 'Su Nombre',
    'SubNiveles' => 'N',
    'CorreoElectronico' => 'account@domain.tld',
    'Token' => 'SU_TOKEN'
 ) );
$data = $rates->getRates();

echo print_r( $data, true );
```


Esto imprime el siguiente objecto:

```
stdClass Object
(
    [dolar] => stdClass Object
        (
            [venta] => stdClass Object
                (
                    [valor] => 558.05000000
                    [fecha] => 2023-02-27T00:00:00-06:00
                )

            [compra] => stdClass Object
                (
                    [valor] => 556.40000000
                    [fecha] => 2023-02-28T00:00:00-06:00
                )

        )

)
```

Acceder a los valores:
```
echo "Dólar venta: " . $data->dolar->venta->valor;
echo PHP_EOL;
echo "Dólar compra: " . $data->dolar->venta->valor;
echo PHP_EOL;
```

El resultado sería:
```
Dólar venta: 558.05000000
Dólar compra: 558.05000000
```

### Tipo de cambio de GoMeta

[https://apis.gometa.org/tdc/](https://apis.gometa.org/tdc/)

```
use Mojito\ExchangeRate\Factory;
use Mojito\ExchangeRate\ProviderTypes;

$rates = Factory::create( ProviderTypes::CR_Gometa );
$data = $rates->getRates();

echo print_r( $data, true );
```

Esto imprime el siguiente objecto:
```
stdClass Object
(
    [venta] => 512.64000000
    [compra_date] => 2024-05-08T00:00:00-06:00
    [licence] => https://en.wikipedia.org/wiki/Beerware
    [compra] => 506.22000000
    [updated] => Wed May  8 00:01:03 2024
    [garantia] => Ninguna
    [venta_date] => 2024-05-08T00:00:00-06:00
)
```


Acceder a los valores:
```
echo "Dólar venta: " . $data->venta;
echo PHP_EOL;
echo "Dólar compra: " . $data->compra;
echo PHP_EOL;
```

El resultado sería:
```
Dólar venta: 512.64000000
Dólar compra: 506.22000000
```

### Pull Request are Welcome | Los Pull Request son bienvenidos
