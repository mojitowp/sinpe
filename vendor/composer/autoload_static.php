<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitd1d6564a8efa80ad9206e62689a7a86a
{
    public static $files = array (
        '04c6c5c2f7095ccf6c481d3e53e1776f' => __DIR__ . '/..' . '/mustangostang/spyc/Spyc.php',
    );

    public static $prefixLengthsPsr4 = array (
        'h' => 
        array (
            'hisorange\\BrowserDetect\\' => 24,
        ),
        'U' => 
        array (
            'UAParser\\' => 9,
        ),
        'L' => 
        array (
            'League\\Pipeline\\' => 16,
        ),
        'J' => 
        array (
            'Jaybizzle\\CrawlerDetect\\' => 24,
        ),
        'D' => 
        array (
            'DeviceDetector\\' => 15,
        ),
        'C' => 
        array (
            'Composer\\CaBundle\\' => 18,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'hisorange\\BrowserDetect\\' => 
        array (
            0 => __DIR__ . '/..' . '/hisorange/browser-detect/src',
        ),
        'UAParser\\' => 
        array (
            0 => __DIR__ . '/..' . '/ua-parser/uap-php/src',
        ),
        'League\\Pipeline\\' => 
        array (
            0 => __DIR__ . '/..' . '/league/pipeline/src',
        ),
        'Jaybizzle\\CrawlerDetect\\' => 
        array (
            0 => __DIR__ . '/..' . '/jaybizzle/crawler-detect/src',
        ),
        'DeviceDetector\\' => 
        array (
            0 => __DIR__ . '/..' . '/matomo/device-detector',
        ),
        'Composer\\CaBundle\\' => 
        array (
            0 => __DIR__ . '/..' . '/composer/ca-bundle/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'D' => 
        array (
            'Detection' => 
            array (
                0 => __DIR__ . '/..' . '/mobiledetect/mobiledetectlib/namespaced',
            ),
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Mobile_Detect' => __DIR__ . '/..' . '/mobiledetect/mobiledetectlib/Mobile_Detect.php',
        'Mojito\\ExchangeRate\\BCCR' => __DIR__ . '/..' . '/mojitowp/exchange-rate/src/providers/cr/class-bccr.php',
        'Mojito\\ExchangeRate\\Factory' => __DIR__ . '/..' . '/mojitowp/exchange-rate/src/class-factory.php',
        'Mojito\\ExchangeRate\\Gometa' => __DIR__ . '/..' . '/mojitowp/exchange-rate/src/providers/cr/class-gometa.php',
        'Mojito\\ExchangeRate\\Hacienda' => __DIR__ . '/..' . '/mojitowp/exchange-rate/src/providers/cr/class-hacienda.php',
        'Mojito\\ExchangeRate\\Provider' => __DIR__ . '/..' . '/mojitowp/exchange-rate/src/class-provider.php',
        'Mojito\\ExchangeRate\\ProviderTypes' => __DIR__ . '/..' . '/mojitowp/exchange-rate/src/enum-provider-types.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitd1d6564a8efa80ad9206e62689a7a86a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitd1d6564a8efa80ad9206e62689a7a86a::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInitd1d6564a8efa80ad9206e62689a7a86a::$prefixesPsr0;
            $loader->classMap = ComposerStaticInitd1d6564a8efa80ad9206e62689a7a86a::$classMap;

        }, null, ClassLoader::class);
    }
}
