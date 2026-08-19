<?php
declare(strict_types=1);
namespace Erased\Support;
final class Logger {
    public static function write(string $level,string $message,array $context=[]): void {
        $dir=dirname(__DIR__,2).'/storage/logs';if(!is_dir($dir))@mkdir($dir,0775,true);
        $line=json_encode(['time'=>date(DATE_ATOM),'level'=>$level,'message'=>$message,'context'=>$context],JSON_UNESCAPED_SLASHES).PHP_EOL;
        @file_put_contents($dir.'/erased.log',$line,FILE_APPEND|LOCK_EX);
    }
}
