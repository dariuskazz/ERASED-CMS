<?php
declare(strict_types=1);
namespace Erased\Core;
final class Router {
    private array $routes=[];
    public function get(string $path, callable|array $handler): void {$this->add('GET',$path,$handler);}
    public function post(string $path, callable|array $handler): void {$this->add('POST',$path,$handler);}
    public function add(string $method,string $path,callable|array $handler): void {$this->routes[]=[strtoupper($method),$path,$handler];}

    /** @return array{handler:callable|array,params:array<string,string>}|null */
    public function match(string $method,string $uri): ?array {
        $path=parse_url($uri,PHP_URL_PATH) ?: '/';
        foreach($this->routes as [$verb,$pattern,$handler]){
            if($verb!==strtoupper($method)) continue;
            $regex='#^'.preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/','(?P<$1>[^/]+)',rtrim($pattern,'/')).'/?$#';
            if(preg_match($regex,$path,$matches)){
                $params=array_filter($matches,'is_string',ARRAY_FILTER_USE_KEY);
                return ['handler'=>$handler,'params'=>$params];
            }
        }
        return null;
    }

    /** @param array{handler:callable|array,params:array<string,string>} $match */
    public function call(array $match): mixed {
        $handler=$match['handler'];
        $params=array_values($match['params']);
        return is_array($handler)?(new $handler[0])->{$handler[1]}(...$params):$handler(...$params);
    }

    public function dispatch(string $method,string $uri): mixed {
        $match=$this->match($method,$uri);
        if($match===null){http_response_code(404); return null;}
        return $this->call($match);
    }
}
