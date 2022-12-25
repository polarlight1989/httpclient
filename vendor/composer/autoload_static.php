<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit8c488916f6ea0a241b29feb7486e8f77
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'PhpAmqpLib\\' => 11,
        ),
        'O' => 
        array (
            'OSS\\' => 4,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'PhpAmqpLib\\' => 
        array (
            0 => __DIR__ . '/..' . '/meetsocial/swoolemq/PhpAmqpLib',
            1 => __DIR__ . '/..' . '/meetsocial/swoolemq/PhpAmqpLib',
        ),
        'OSS\\' => 
        array (
            0 => __DIR__ . '/..' . '/OSS',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit8c488916f6ea0a241b29feb7486e8f77::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit8c488916f6ea0a241b29feb7486e8f77::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}