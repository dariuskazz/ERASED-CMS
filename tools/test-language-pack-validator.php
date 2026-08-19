<?php
declare(strict_types=1);

use Erased\Language\TranslationValidator;

require_once dirname(__DIR__).'/app/Language/TranslationValidator.php';

// Minimal global-function stub matching this codebase's established harness
// convention - the validator checks submitted keys/placeholders against the
// real master English defaults, so the test supplies a small, fixed stand-in
// for erased_master_translation_defaults() rather than loading the real
// bootstrap.php (which requires a live database connection).
/** @return array<string,string> */
function erased_master_translation_defaults(string $group = 'site'): array
{
    return $group === 'admin'
        ? ['dashboard' => 'Dashboard', 'save' => 'Save', 'help_url' => 'See https://example.com/help for details.']
        : ['home' => 'Home', 'welcome' => 'Welcome, {name}! You have {count} new messages.'];
}

try {
    $validator = new TranslationValidator();

    // --- A fully correct, complete submission has no errors and no warnings ---
    $result = $validator->validate('site', ['home' => 'Hjem', 'welcome' => 'Velkommen, {name}! Du har {count} nye meldinger.']);
    if ($result['errors'] !== []) {
        throw new RuntimeException('A valid submission was rejected: '.implode(' ', $result['errors']));
    }
    if ($result['warnings'] !== []) {
        throw new RuntimeException('A complete submission should not produce a completeness warning.');
    }

    // --- Missing/blank keys warn but never error ---
    $result = $validator->validate('site', ['home' => 'Hjem', 'welcome' => '']);
    if ($result['errors'] !== []) {
        throw new RuntimeException('An incomplete submission should never be a hard error.');
    }
    if ($result['warnings'] === []) {
        throw new RuntimeException('A blank key should produce a completeness warning.');
    }

    // --- An unrecognized key is a hard error ---
    $result = $validator->validate('site', ['home' => 'Hjem', 'not_a_real_key' => 'Whatever']);
    if ($result['errors'] === [] || !str_contains($result['errors'][0], 'not_a_real_key')) {
        throw new RuntimeException('An unrecognized key was not rejected.');
    }

    // --- A missing placeholder is a hard error naming the missing token ---
    $result = $validator->validate('site', ['welcome' => 'Velkommen! Du har {count} nye meldinger.']); // dropped {name}
    if ($result['errors'] === [] || !str_contains($result['errors'][0], '{name}')) {
        throw new RuntimeException('A missing placeholder was not rejected with the missing token named.');
    }

    // --- An extra, invented placeholder is also a hard error ---
    $result = $validator->validate('site', ['welcome' => 'Welcome, {name}! You have {count} new {unexpected}.']);
    if ($result['errors'] === [] || !str_contains($result['errors'][0], '{unexpected}')) {
        throw new RuntimeException('An unexpected extra placeholder was not rejected.');
    }

    // --- Placeholder order doesn't matter, only the token set ---
    $result = $validator->validate('site', ['welcome' => '{count} new messages, {name}!']);
    if ($result['errors'] !== []) {
        throw new RuntimeException('Reordered placeholders that still match the expected set were incorrectly rejected.');
    }

    // --- HTML markup is a hard error ---
    $result = $validator->validate('site', ['home' => '<b>Hjem</b>']);
    if ($result['errors'] === []) {
        throw new RuntimeException('HTML markup in a translation value was not rejected.');
    }

    // --- A well-formed https:// URL is accepted ---
    $result = $validator->validate('admin', ['help_url' => 'Se https://example.com/hjelp for detaljer.']);
    if ($result['errors'] !== []) {
        throw new RuntimeException('A well-formed https:// URL was incorrectly rejected: '.implode(' ', $result['errors']));
    }

    // --- A malformed URL is a hard error ---
    $result = $validator->validate('admin', ['help_url' => 'Se https:///bad for detaljer.']);
    if ($result['errors'] === []) {
        throw new RuntimeException('A malformed URL was not rejected.');
    }

    // --- A disallowed scheme is a hard error (matches real payload shapes
    //     like javascript://..., not a bare "label:" that reads as prose) ---
    $result = $validator->validate('admin', ['help_url' => 'Se javascript://alert(1) for detaljer.']);
    if ($result['errors'] === []) {
        throw new RuntimeException('A disallowed URL scheme was not rejected.');
    }

    // --- Ordinary prose containing a bare "label:" is NOT treated as a URL ---
    $result = $validator->validate('admin', ['help_url' => 'Note: see the manual for details.']);
    if ($result['errors'] !== []) {
        throw new RuntimeException('Ordinary "label:" prose was incorrectly flagged as a URL: '.implode(' ', $result['errors']));
    }

    // --- validateJson() rejects malformed JSON outright ---
    $result = $validator->validateJson('site', '{"home": "Hjem",}'); // trailing comma
    if ($result['errors'] === []) {
        throw new RuntimeException('Malformed JSON was not rejected.');
    }

    // --- validateJson() rejects a non-flat structure ---
    $result = $validator->validateJson('site', '{"home": {"nested": true}}');
    if ($result['errors'] === []) {
        throw new RuntimeException('A non-flat (nested) JSON structure was not rejected.');
    }

    // --- validateJson() accepts well-formed, valid content end to end ---
    $result = $validator->validateJson('site', json_encode(['home' => 'Hjem']));
    if ($result['errors'] !== []) {
        throw new RuntimeException('Well-formed, valid JSON was incorrectly rejected: '.implode(' ', $result['errors']));
    }

    fwrite(STDOUT, "Language pack translation validator smoke test passed.\n");
    fwrite(STDOUT, "Validated placeholder parity, HTML rejection, URL scheme/well-formedness checks, unrecognized-key rejection vs missing-key warning, and malformed/non-flat JSON rejection.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
