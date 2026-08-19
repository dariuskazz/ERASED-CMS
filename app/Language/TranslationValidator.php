<?php
declare(strict_types=1);

namespace Erased\Language;

use JsonException;

/**
 * Validates a group's (site/admin) translation key/value content against the
 * master English defaults - the only reliable ground truth for what each key's
 * content should contain. Unrecognized keys and mismatched placeholders/HTML/
 * URLs are hard errors; missing or blank keys are a warning only, since
 * ensure_language_files() already tops those up safely with the English
 * fallback - incompleteness is never a reason to block a save or an install.
 */
final class TranslationValidator
{
    /**
     * @param array<string,string> $values
     * @return array{errors:array<int,string>,warnings:array<int,string>}
     */
    public function validate(string $group, array $values): array
    {
        $group = $group === 'admin' ? 'admin' : 'site';
        $master = erased_master_translation_defaults($group);
        $errors = [];

        foreach ($values as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                $errors[] = "Value for '{$key}' must be a string.";
                continue;
            }
            if (!array_key_exists($key, $master)) {
                $errors[] = "Unrecognized translation key '{$key}'.";
                continue;
            }
            if (trim($value) === '') {
                // Blank means "not yet translated, falls back to English" -
                // a completeness warning below, never content to validate.
                continue;
            }
            $this->checkPlaceholders($key, $master[$key], $value, $errors);
            $this->checkHtml($key, $value, $errors);
            $this->checkUrls($key, $value, $errors);
        }

        $warnings = $this->incompleteWarnings($master, $values);

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * File-upload entry point: validates raw JSON text is well-formed and
     * decodes to a flat string=>string map before delegating to validate().
     * @return array{errors:array<int,string>,warnings:array<int,string>}
     */
    public function validateJson(string $group, string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            return ['errors' => ['Invalid JSON: '.$error->getMessage()], 'warnings' => []];
        }

        if (!is_array($decoded)) {
            return ['errors' => ['Translation file must decode to a flat object.'], 'warnings' => []];
        }
        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                return ['errors' => ['Translation file must be a flat map of string keys to string values.'], 'warnings' => []];
            }
        }

        /** @var array<string,string> $decoded */
        return $this->validate($group, $decoded);
    }

    /**
     * @param array<string,string> $master
     * @param array<string,string> $values
     * @return array<int,string>
     */
    private function incompleteWarnings(array $master, array $values): array
    {
        $incomplete = [];
        foreach ($master as $key => $englishValue) {
            $value = $values[$key] ?? null;
            if ($value === null || trim($value) === '') {
                $incomplete[] = $key;
            }
        }
        if ($incomplete === []) {
            return [];
        }

        return [count($incomplete).' key(s) missing or blank, falls back to English: '.implode(', ', $incomplete)];
    }

    /** @param array<int,string> $errors */
    private function checkPlaceholders(string $key, string $englishValue, string $value, array &$errors): void
    {
        $expected = $this->tokens($englishValue);
        $actual = $this->tokens($value);
        if ($expected === $actual) {
            return;
        }

        $missing = array_diff($expected, $actual);
        $extra = array_diff($actual, $expected);
        $parts = [];
        if ($missing !== []) {
            $parts[] = 'missing '.implode(', ', $missing);
        }
        if ($extra !== []) {
            $parts[] = 'unexpected '.implode(', ', $extra);
        }
        $errors[] = "Key '{$key}' has mismatched placeholders (".implode('; ', $parts).').';
    }

    /** @return array<int,string> */
    private function tokens(string $value): array
    {
        preg_match_all('/\{[a-zA-Z0-9_]+\}/', $value, $matches);
        $tokens = array_values(array_unique($matches[0]));
        sort($tokens);

        return $tokens;
    }

    /** @param array<int,string> $errors */
    private function checkHtml(string $key, string $value, array &$errors): void
    {
        if (preg_match('/<[a-zA-Z\/!]/', $value) === 1) {
            $errors[] = "Key '{$key}' contains HTML markup, which is not allowed in translation values.";
        }
    }

    /**
     * Matches http(s):// and www. specifically (the well-formedness check
     * below), plus any other scheme://... shape - not just javascript:alert(1)
     * with no slashes, which reads as ordinary "label: text" prose and would
     * false-positive constantly, but scheme://... is essentially never
     * legitimate prose and does cover real payload shapes like javascript://.
     * @param array<int,string> $errors
     */
    private function checkUrls(string $key, string $value, array &$errors): void
    {
        $count = preg_match_all('/\b[a-zA-Z][a-zA-Z0-9+.-]*:\/\/\S*|\bwww\.\S+/i', $value, $matches);
        if (!$count) {
            return;
        }

        foreach ($matches[0] as $candidate) {
            $url = str_starts_with(strtolower($candidate), 'www.') ? 'http://'.$candidate : $candidate;
            $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?? ''));
            if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                $errors[] = "Key '{$key}' contains a malformed or disallowed URL: '{$candidate}'.";
            }
        }
    }
}
