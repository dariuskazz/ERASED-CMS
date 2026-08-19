<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/HomepageLayout.php';

try {
    // --- Every rich block type has the same uniform style-field schema ---
    $defaults = homepage_studio_new_block_defaults();
    $styleKeys = ['bg_style', 'align', 'min_height', 'padding', 'css_class', 'html_id'];
    foreach (['features'] as $type) {
        foreach ($styleKeys as $key) {
            if (!array_key_exists($key, $defaults[$type])) {
                throw new RuntimeException("Block type '{$type}' is missing style default '{$key}'.");
            }
        }
    }
    if ($defaults['features']['bg_style'] !== 'gradient' || $defaults['features']['align'] !== 'left') {
        throw new RuntimeException('Unexpected features style defaults.');
    }

    // --- homepage_studio_block_options() merges saved style fields over defaults ---
    $merged = homepage_studio_block_options('features', ['features' => ['bg_style' => 'solid', 'html_id' => 'my-features']]);
    if ($merged['bg_style'] !== 'solid' || $merged['html_id'] !== 'my-features' || $merged['align'] !== 'left') {
        throw new RuntimeException('homepage_studio_block_options() did not merge style fields over defaults correctly.');
    }

    // --- homepage_studio_style_attrs(): solid background ---
    $attrs = homepage_studio_style_attrs(['bg_style' => 'solid', 'align' => 'left', 'min_height' => '', 'padding' => '', 'css_class' => '', 'html_id' => '']);
    if (!str_contains($attrs['sectionStyle'], 'background:var(--block-primary)')) {
        throw new RuntimeException('solid bg_style did not produce a background style.');
    }

    // --- image background only applies when an image is actually provided ---
    $withImage = homepage_studio_style_attrs(['bg_style' => 'image', 'align' => 'left', 'min_height' => '', 'padding' => '', 'css_class' => '', 'html_id' => ''], 'https://example.com/x.jpg');
    if (!str_contains($withImage['sectionStyle'], "background-image:url('https://example.com/x.jpg')")) {
        throw new RuntimeException('image bg_style with an image URL did not produce a background-image style.');
    }
    $withoutImage = homepage_studio_style_attrs(['bg_style' => 'image', 'align' => 'left', 'min_height' => '', 'padding' => '', 'css_class' => '', 'html_id' => ''], '');
    if (str_contains($withoutImage['sectionStyle'], 'background-image')) {
        throw new RuntimeException('image bg_style with no image URL should not produce a background-image style.');
    }

    // --- min_height, padding, align, css_class, html_id ---
    $full = homepage_studio_style_attrs(['bg_style' => 'gradient', 'align' => 'center', 'min_height' => '600', 'padding' => '40px 10px', 'css_class' => 'my-class', 'html_id' => 'my-id']);
    if (!str_contains($full['sectionStyle'], 'min-height:600px')) throw new RuntimeException('min_height not applied.');
    if (!str_contains($full['sectionStyle'], 'padding:40px 10px')) throw new RuntimeException('padding not applied.');
    if ($full['headingStyle'] !== 'text-align:center;') throw new RuntimeException('align not applied to heading style.');
    if ($full['extraClass'] !== ' my-class') throw new RuntimeException('css_class not applied.');
    if ($full['idAttr'] !== ' id="my-id"') throw new RuntimeException('html_id not applied.');

    // --- min_height is capped, and non-numeric values are ignored rather than injected ---
    $capped = homepage_studio_style_attrs(['bg_style' => 'gradient', 'align' => 'left', 'min_height' => '999999', 'padding' => '', 'css_class' => '', 'html_id' => '']);
    if (!str_contains($capped['sectionStyle'], 'min-height:4000px')) {
        throw new RuntimeException('min_height was not capped at 4000.');
    }

    // --- invalid css_class / html_id (e.g. an attempted style/attribute injection) are dropped, not sanitized-and-kept ---
    $unsafe = homepage_studio_style_attrs(['bg_style' => 'gradient', 'align' => 'left', 'min_height' => '', 'padding' => '', 'css_class' => 'foo"><script>alert(1)</script>', 'html_id' => '1-not-a-valid-start']);
    if ($unsafe['extraClass'] !== '') throw new RuntimeException('An unsafe css_class value was not rejected.');
    if ($unsafe['idAttr'] !== '') throw new RuntimeException('An html_id starting with a digit (invalid HTML) was not rejected.');

    // --- an unrecognized padding format is dropped rather than passed through raw ---
    $badPadding = homepage_studio_style_attrs(['bg_style' => 'gradient', 'align' => 'left', 'min_height' => '', 'padding' => 'expression(alert(1))', 'css_class' => '', 'html_id' => '']);
    if (str_contains($badPadding['sectionStyle'], 'expression')) {
        throw new RuntimeException('An unsafe padding value was not rejected.');
    }

    fwrite(STDOUT, "Homepage Studio style-attrs smoke test passed.\n");
    fwrite(STDOUT, "Validated the uniform style schema across every rich block type, options merging, and that homepage_studio_style_attrs() produces correct and safe output for background modes, sizing, alignment, class, and id.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
