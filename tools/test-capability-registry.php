<?php
declare(strict_types=1);

use Erased\Capabilities\CapabilityRegistry;
use Erased\Packages\PackageManifest;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Capabilities/CapabilityRegistry.php';

try {
    $registry = new CapabilityRegistry();

    $galleryLite = new PackageManifest([
        'id' => 'erased.gallery-lite',
        'type' => 'module',
        'name' => 'Gallery Lite',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Gallery capability provider.',
        'provides' => ['gallery', 'media-gallery'],
        'requires_capabilities' => ['media'],
    ]);

    $media = new PackageManifest([
        'id' => 'erased.media',
        'type' => 'module',
        'name' => 'Media',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Media capability provider.',
        'provides' => ['media'],
    ]);

    $registry->register($galleryLite);
    assert($registry->has('gallery') === true);
    assert($registry->missingRequirements($galleryLite) === ['media']);

    $registry->register($media);
    $registry->assertRequirementsSatisfied($galleryLite);
    assert($registry->preferredProvider('gallery')->id() === 'erased.gallery-lite');

    $galleryPro = new PackageManifest([
        'id' => 'erased.gallery-pro',
        'type' => 'module',
        'name' => 'Gallery Pro',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Alternative gallery capability provider.',
        'provides' => ['gallery'],
        'requires_capabilities' => ['media'],
    ]);
    $registry->register($galleryPro);

    $conflictDetected = false;
    try {
        $registry->preferredProvider('gallery');
    } catch (RuntimeException $error) {
        $conflictDetected = str_contains($error->getMessage(), 'Multiple packages provide capability');
    }
    assert($conflictDetected === true);
    assert($registry->preferredProvider('gallery', 'erased.gallery-pro')->id() === 'erased.gallery-pro');

    $map = $registry->map();
    assert($map['gallery'] === ['erased.gallery-lite', 'erased.gallery-pro']);
    assert($map['media'] === ['erased.media']);

    $registry->unregister('erased.gallery-pro');
    assert($registry->preferredProvider('gallery')->id() === 'erased.gallery-lite');

    fwrite(STDOUT, "Capability registry smoke test passed.\n");
    fwrite(STDOUT, "Validated providers, requirements, conflicts, selection, and unregister cleanup.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
