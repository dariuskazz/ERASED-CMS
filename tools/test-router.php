<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/Core/Router.php';

use Erased\Core\Router;

final class RouterTestHandlerTarget
{
    public function show(string $id): string
    {
        return 'shown:'.$id;
    }
}

$fail = 0;
$check = static function (bool $condition, string $label) use (&$fail): void {
    if ($condition) {
        echo "PASS: {$label}\n";
    } else {
        fwrite(STDERR, "FAIL: {$label}\n");
        $fail++;
    }
};

try {
    $router = new Router();
    $router->get('/admin/widgets', static fn(): string => 'index');
    $router->get('/admin/widgets/{id}', static fn(string $id): string => 'get:'.$id);
    $router->post('/admin/widgets/{id}', [RouterTestHandlerTarget::class, 'show']);

    $indexMatch = $router->match('GET', '/admin/widgets');
    $check($indexMatch !== null, 'match() finds a plain path with no params');
    $check($indexMatch !== null && $router->call($indexMatch) === 'index', 'call() invokes a closure handler with no params');

    $paramMatch = $router->match('GET', '/admin/widgets/42');
    $check($paramMatch !== null && $paramMatch['params'] === ['id' => '42'], 'match() extracts a {param} segment');
    $check($paramMatch !== null && $router->call($paramMatch) === 'get:42', 'call() passes the extracted param through to the closure');

    $classMatch = $router->match('POST', '/admin/widgets/7');
    $check($classMatch !== null && $router->call($classMatch) === 'shown:7', 'call() invokes a [class, method] array handler with the extracted param');

    $check($router->match('GET', '/admin/widgets/42?x=1') !== null, 'match() ignores a query string (parse_url strips it)');
    $check($router->match('DELETE', '/admin/widgets/42') === null, 'match() returns null for an unregistered method');
    $check($router->match('GET', '/admin/nothing-here') === null, 'match() returns null for an unregistered path');

    // dispatch() must keep its original public behaviour unchanged: same
    // successful call, and null on a miss (its 404 side effect is a plain
    // http_response_code() call, unchanged and not independently re-tested
    // here - asserting it would require suppressing this script's own
    // earlier CLI output, which isn't worth the complexity for a one-line
    // pass-through unaffected by this change).
    $check($router->dispatch('GET', '/admin/widgets') === 'index', 'dispatch() still returns a successful match result');
    // @-suppressed: http_response_code() warns about already-sent output
    // this far into a script that's been echoing PASS/FAIL as it goes -
    // a test-harness artifact, not something dispatch() does wrong.
    $check(@$router->dispatch('GET', '/admin/does-not-exist') === null, 'dispatch() still returns null on a miss (unchanged regression check)');

    if ($fail === 0) {
        fwrite(STDOUT, "Router match()/call() test passed.\n");
        fwrite(STDOUT, "Validated param extraction, closure and [class,method] handlers, method/path miss handling, and dispatch()'s unchanged backward-compatible behaviour.\n");
    } else {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
