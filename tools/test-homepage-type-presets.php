<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root.'/app/HomepageLayout.php';

try {
    $validBlockIds = array_keys(homepage_studio_blocks());
    $validRegions = ['left', 'center', 'right'];

    $presetTypes = ['news', 'blog', 'business', 'portfolio', 'gallery', 'video', 'shop'];
    foreach ($presetTypes as $typeId) {
        $preset = erased_homepage_type_preset($typeId);
        assert($preset !== null && $preset !== [], "Preset for '{$typeId}' must be a non-empty list.");
        foreach ($preset as $entry) {
            assert(in_array($entry['block_id'], $validBlockIds, true), "Preset '{$typeId}' references unknown block id '{$entry['block_id']}'.");
            assert(in_array($entry['region'], $validRegions, true), "Preset '{$typeId}' references invalid region '{$entry['region']}'.");
        }
    }

    // Types deliberately without a dedicated preset fall back to null (clone-from-default).
    foreach (['community', 'documentation', 'landing', 'custom', 'does-not-exist'] as $typeId) {
        assert(erased_homepage_type_preset($typeId) === null, "Type '{$typeId}' should not have a dedicated preset.");
    }

    // Every preset's block ids are unique within itself - no duplicate sections.
    foreach ($presetTypes as $typeId) {
        $ids = array_column(erased_homepage_type_preset($typeId), 'block_id');
        assert(count($ids) === count(array_unique($ids)), "Preset '{$typeId}' repeats a block id.");
    }

    fwrite(STDOUT, "Homepage type presets test passed.\n");
    fwrite(STDOUT, "Validated all 7 presets use only real block ids and regions, non-preset types return null, and no preset repeats a section.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
