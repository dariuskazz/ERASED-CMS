<?php
declare(strict_types=1);
namespace Erased\Core;
final class Autoloader {
    public static function register(string $basePath): void {
        spl_autoload_register(static function (string $class) use ($basePath): void {
            $prefix='Erased\\';
            if (!str_starts_with($class,$prefix)) return;
            $relative=str_replace('\\','/',substr($class,strlen($prefix))).'.php';
            $file=rtrim($basePath,'/').'/app/'.$relative;
            if (is_file($file)) require $file;
        });
    }
}
