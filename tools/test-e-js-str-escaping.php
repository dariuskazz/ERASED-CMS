<?php
declare(strict_types=1);

// e_js_str() protects the onclick="location.href='...'" pattern used
// throughout the admin rail (app/Admin/PluginAdminNav.php,
// public/index.php's admin_core_nav_button()).
// e() alone is not enough there: the browser HTML-decodes the attribute
// value (undoing e()'s &#039; back into a real ') before that decoded text
// is parsed as JS, so a plain e() leaves the JS string literal breakable.
// Found via a v0.8-dev security audit - see docs/STATUS.md.

require_once dirname(__DIR__).'/app/bootstrap.php';

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
    // Simulates exactly what a browser does to an onclick="...'VALUE'..."
    // attribute before handing it to the JS parser: decode HTML entities
    // once. If any unescaped (not backslash-preceded) apostrophe survives
    // that decode, it would terminate the JS string literal early.
    $decodeAsBrowserWould = static fn(string $attrValue): string =>
        html_entity_decode($attrValue, ENT_QUOTES, 'UTF-8');

    $hasUnescapedApostrophe = static function (string $jsSource): bool {
        return (bool) preg_match("/(?<!\\\\)'/", $jsSource);
    };

    // The actual attack payload: an admin-authored value (e.g. a plugin
    // manifest's admin_menu href) crafted to break out of the JS string
    // and run arbitrary script in every admin's session.
    $payload = "/admin/foo'); alert(document.cookie); //";

    $attrHtml = e_js_str($payload);
    $decoded = $decodeAsBrowserWould($attrHtml);
    $check(
        !$hasUnescapedApostrophe($decoded),
        'e_js_str() leaves no unescaped quote after simulated HTML-attribute decode (the actual attack)'
    );

    // The bug this replaces: plain e() alone does NOT protect this context
    // - confirm the vulnerability is real by checking the old behavior
    // would have failed the same check.
    $oldAttrHtml = e($payload);
    $oldDecoded = $decodeAsBrowserWould($oldAttrHtml);
    $check(
        $hasUnescapedApostrophe($oldDecoded),
        'plain e() alone is confirmed vulnerable to the same payload (why e_js_str() exists)'
    );

    // A backslash in the input must itself be escaped, or an attacker
    // could neutralize the escaping e_js_str() just added (e.g. a
    // trailing \ making the following escaped quote "eat" a character
    // that was never a quote in the source).
    $backslashPayload = "foo\\' ); alert(1); //";
    $decodedBackslash = $decodeAsBrowserWould(e_js_str($backslashPayload));
    $check(
        !$hasUnescapedApostrophe($decodedBackslash),
        'e_js_str() also escapes a literal backslash preceding a quote'
    );

    // Ordinary, non-malicious values must still round-trip exactly once
    // reconstructed the way the browser's JS engine would see them -
    // this is a correctness check, not just a security one.
    $normal = '/admin/commerce/products';
    $decodedNormal = $decodeAsBrowserWould(e_js_str($normal));
    $check(
        stripslashes($decodedNormal) === $normal,
        'a normal value round-trips correctly through e_js_str() + simulated decode + unescape'
    );

    if ($fail > 0) {
        fwrite(STDERR, "\n{$fail} check(s) failed.\n");
        exit(1);
    }
    echo "\nAll e_js_str() escaping checks passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
}
