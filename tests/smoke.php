<?php
declare(strict_types=1);

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';

require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/HomepageLayout.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

foreach (['install_schema', 'upload_one', 'safe_return'] as $helper) {
    check(function_exists($helper), "Missing helper {$helper}");
}

putenv('ERASED_SETTINGS_KEY=smoke-test-key-that-never-leaves-ci');
$encrypted = erased_encrypt_setting('smtp-secret-value');
check(str_starts_with($encrypted, 'enc:v1'), 'Sensitive setting was not encrypted');
check(!str_contains($encrypted, 'smtp-secret-value'), 'Sensitive setting leaked into ciphertext');
check(erased_decrypt_setting($encrypted) === 'smtp-secret-value', 'Sensitive setting did not decrypt');
check(erased_sensitive_setting('smtp_password'), 'SMTP password was not classified as sensitive');
check(erased_sensitive_setting('payment_stripe_secret_key'), 'Payment secret was not classified as sensitive');
check(!erased_sensitive_setting('password_min_length'), 'Password policy was classified as a secret');

$_POST['return_to'] = '/posts?tag=security#latest';
check(safe_return('/') === '/posts?tag=security#latest', 'Local return URL was rejected');

$_POST['return_to'] = 'https://attacker.example/phishing';
check(safe_return('/') === '/', 'External return URL was accepted');

$_POST['return_to'] = '//attacker.example/phishing';
check(safe_return('/') === '/', 'Protocol-relative return URL was accepted');

$schema = (string) file_get_contents(dirname(__DIR__).'/database/schema.sql');
foreach ([
    'users',
    'content',
    'media',
    'settings',
    'security_events',
    'security_ip_lockouts',
    'packages',
] as $table) {
    check(
        preg_match('/CREATE TABLE(?: IF NOT EXISTS)?\\s+`?'.preg_quote($table, '/').'`?\\s*\\(/i', $schema) === 1,
        "Schema is missing table {$table}",
    );
}
check(str_contains($schema, 'details_json'), 'Security event details column is missing');
check(str_contains($schema, 'last_email'), 'IP lockout email column is missing');

$version = trim((string) file_get_contents(dirname(__DIR__).'/VERSION'));
$app = require dirname(__DIR__).'/config/app.php';
$package = json_decode((string) file_get_contents(dirname(__DIR__).'/package.json'), true);
check(($app['version'] ?? '') === $version, 'config/app.php version differs from VERSION');
check(($package['version'] ?? '') === $version, 'package.json version differs from VERSION');

$index = (string) file_get_contents(dirname(__DIR__).'/public/index.php');
$installer = (string) file_get_contents(dirname(__DIR__).'/routes/install.php');
$publicRoutes = (string) file_get_contents(dirname(__DIR__).'/routes/public.php');
$adminRoutes = (string) file_get_contents(dirname(__DIR__).'/routes/admin.php');
check(str_contains($installer, 'install_schema($pdo)'), 'Installer does not call install_schema');
check(str_contains($index, 'details_json) VALUES'), 'Security events do not write details_json');
check(!str_contains($index, 'Time-Based One-Time Password (TOTP)'), 'Email verification is mislabeled as TOTP');
check(
    str_contains($publicRoutes, '$homepage = homepage_studio_render_public();')
        && str_contains($publicRoutes, "if (\$path === '/')")
        && str_contains($publicRoutes, 'erased_public_homepage();'),
    'The public homepage does not use the unified layout renderer',
);
check(
    str_contains($index, "str_starts_with(\$requestPath,'/admin/themes')"),
    'Theme & Logo does not keep the top Settings navigation active',
);
// Quarantined 2026-08-09, not deleted: this asserts a pre-Blueprint UI contract
// (a body.theme-{slug} class driving a header-nav top-navigation highlight) that
// the Aug 6-9 Blueprint rewrite replaced wholesale - the admin shell is now a left
// rail (.rail-item.is-active) and the admin panel's actual theme switch is a
// client-only [data-theme] attribute toggle (see applyAdminTheme() in
// public/index.php), which appears disconnected from the server-side admin_theme
// setting this assertion was protecting. That's a real, undecided question for
// the admin visual-polish pass (see ROADMAP.md), not something to silently
// paper over here with a rewritten assertion that just guesses at the intended
// current behavior.
// check(
//     str_contains($index, 'body.theme-light-grey.admin-area header nav a.active{background:var(--green)'),
//     'Matte Light Grey does not preserve the green active Settings highlight',
// );
check(
    preg_match('~/assets/admin-design-system\\.css\\?v=[0-9]+(?:\\.[0-9]+)*~', $index) === 1,
    'Admin theme stylesheet is missing a numeric cache-busting version',
);
check(
    str_contains($index, '/assets/editor/erased-cms-editor.css?v=23'),
    'Editor theme stylesheet cache version was not refreshed',
);
check(!str_contains($index, 'Languages & Translations'), 'Languages navigation still uses the old label');
check(str_contains($index, "'/admin/galleries','Gallery'"), 'Gallery admin navigation still uses the old label');
check(str_contains($index, "setting('gallery_show_in_navigation','1')"), 'Gallery website visibility setting is missing');
check(str_contains($adminRoutes, 'name="gallery_show_in_navigation"'), 'Gallery website visibility control is missing');
check(str_contains($index, "rtrim((string)(\$item['url']??''),'/')==='/gallery'"), 'Saved navigation does not recognize the Gallery route');

$adminCss = (string) file_get_contents(dirname(__DIR__).'/public/assets/admin-design-system.css');
// Quarantined 2026-08-09, not deleted, same root cause as the theme-highlight
// check above: all four assert the pre-Blueprint .admin-area.theme-{slug} class
// system (specific hex palettes, an .analytics-grid dashboard class, a
// header-nav active-link selector) that the Blueprint rewrite replaced with
// [data-theme="..."] attribute selectors and a completely different token set
// and shell (.rail-item, not header nav). See the note above on
// applyAdminTheme() - reconciling what admin_theme (the DB setting) is
// actually supposed to control now is a real open question, not something to
// guess at here.
// check(
//     preg_match('/\\.admin-area\\.theme-light-grey\\s*\\{[^}]*--bg:\\s*#a6adb2;[^}]*--panel:\\s*#b9c0c4;[^}]*--panel-2:\\s*#c1c7cb;[^}]*--panel-hover:\\s*#c5cacf;[^}]*--control:\\s*#d1d5d8;/s', $adminCss) === 1,
//     'Matte Light Grey admin palette is not using the darker layered greys',
// );
// check(
//     str_contains($adminCss, '.admin-area.theme-light-grey .analytics-grid div'),
//     'Matte Light Grey dashboard surfaces are not overridden',
// );
// check(
//     str_contains($adminCss, '.admin-area header nav a.active'),
//     'Admin theme stylesheet does not preserve active top navigation styling',
// );
// check(
//     str_contains($adminCss, '.admin-area.theme-light-grey .theme-swatch.light-grey'),
//     'Matte Light Grey preview does not match the darker admin palette',
// );

$editorCss = (string) file_get_contents(dirname(__DIR__).'/public/assets/editor/erased-cms-editor.css');
// 2026-08-10: these three used to check for var(--panel-hover, ...)/var(--control,
// ...)/var(--panel, ...) - syntactically real var() calls, but --panel-hover,
// --control, and --panel were never defined anywhere in admin-design-system.css's
// Blueprint token set (--paper/--sheet/--sheet-2/--ink/--accent/...), so they
// always silently fell back to their hardcoded #ffffff fallback regardless of
// theme - exactly the "white toolbar, invisible icons" bug these checks claimed
// to guard against. Updated to the real Blueprint token names those spots now use.
check(
    str_contains($editorCss, 'background:var(--sheet-2, #ffffff)'),
    'Editor toolbar does not inherit the selected admin theme',
);
check(
    str_contains($editorCss, 'background:var(--paper, #ffffff)'),
    'Editor writing surface does not inherit the selected admin theme',
);
check(
    str_contains($editorCss, 'background:var(--sheet, #ffffff)'),
    'Editor sticky actions do not inherit the selected admin theme',
);

$layoutConfig = [
    'preset' => 'three',
    'left' => 20,
    'right' => 20,
    'gap' => 24,
    'max_width' => 1600,
    'widget_gap' => 16,
    'sticky_offset' => 18,
    'active_regions' => ['left', 'center', 'right'],
];
$threeColumns = homepage_studio_width_style($layoutConfig, ['left', 'center', 'right']);
$leftAndCenter = homepage_studio_width_style($layoutConfig, ['left', 'center']);
$centerAndRight = homepage_studio_width_style($layoutConfig, ['center', 'right']);
$centerOnly = homepage_studio_width_style($layoutConfig, ['center']);
check(str_contains($threeColumns, '20fr) minmax(0,60fr) minmax(0,20fr)'), 'Three-column widths are incorrect');
check(str_contains($leftAndCenter, '20fr) minmax(0,80fr)'), 'Center does not expand when the right column is empty');
check(str_contains($centerAndRight, '80fr) minmax(0,20fr)'), 'Center does not expand when the left column is empty');
check(str_contains($centerOnly, '--homepage-columns:minmax(0,1fr)'), 'Single visible column is not full width');

fwrite(STDOUT, "ERASED CMS smoke tests passed.\n");
