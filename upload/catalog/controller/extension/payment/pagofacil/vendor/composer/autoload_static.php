<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit40e4bbaed45e3dae7c27e9248ee5a0ff
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'PagoFacil\\lib\\' => 14,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'PagoFacil\\lib\\' => 
        array (
            0 => __DIR__ . '/..' . '/pagofacil/php-sdk/classes',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit40e4bbaed45e3dae7c27e9248ee5a0ff::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit40e4bbaed45e3dae7c27e9248ee5a0ff::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
