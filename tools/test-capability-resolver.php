<?php
declare(strict_types=1);

use Erased\Capabilities\CapabilityRegistry;
use Erased\Capabilities\CapabilityResolver;
use Erased\Packages\PackageManifest;

require_once dirname(__DIR__).'/app/Packages/PackageManifest.php';
require_once dirname(__DIR__).'/app/Capabilities/CapabilityRegistry.php';
require_once dirname(__DIR__).'/app/Capabilities/CapabilityResolver.php';

try {
    $registry = new CapabilityRegistry();

    $nativeSearch = new PackageManifest([
        'id' => 'erased.search-native',
        'type' => 'module',
        'name' => 'Native Search',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Default SQL search provider.',
        'provides' => ['search'],
    ]);
    $fastSearch = new PackageManifest([
        'id' => 'erased.search-fast',
        'type' => 'module',
        'name' => 'Fast Search',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'Alternative search provider.',
        'provides' => ['search'],
    ]);
    $news = new PackageManifest([
        'id' => 'erased.news',
        'type' => 'website-type',
        'name' => 'News Profile',
        'version' => '1.0.0',
        'requires' => '0.3.0',
        'author' => 'ERASED CMS',
        'description' => 'News website profile.',
        'requires_capabilities' => ['search'],
    ]);

    $registry->register($nativeSearch);
    $registry->register($fastSearch);
    $registry->register($news);

    $resolver = new CapabilityResolver($registry, ['erased.search-native']);
    assert($resolver->has('search') === true);
    assert($resolver->resolve('search')->id() === 'erased.search-native');
    assert($resolver->resolveRequirements($news)['search']->id() === 'erased.search-native');

    $resolver->activate('erased.search-fast');
    $conflictRejected = false;
    try {
        $resolver->resolve('search');
    } catch (RuntimeException $error) {
        $conflictRejected = str_contains($error->getMessage(), 'Multiple active packages');
    }
    assert($conflictRejected === true);

    $resolver->prefer('search', 'erased.search-fast');
    assert($resolver->resolve('search')->id() === 'erased.search-fast');
    assert($resolver->preferences() === ['search' => 'erased.search-fast']);

    $resolver->deactivate('erased.search-fast');
    $inactivePreferenceRejected = false;
    try {
        $resolver->resolve('search');
    } catch (RuntimeException $error) {
        $inactivePreferenceRejected = str_contains($error->getMessage(), 'not an active provider');
    }
    assert($inactivePreferenceRejected === true);

    $resolver->clearPreference('search');
    assert($resolver->resolve('search')->id() === 'erased.search-native');

    $resolver->deactivate('erased.search-native');
    assert($resolver->has('search') === false);

    $missingRejected = false;
    try {
        $resolver->assertRequirementsSatisfied($news);
    } catch (RuntimeException $error) {
        $missingRejected = str_contains($error->getMessage(), 'No active package provides capability');
    }
    assert($missingRejected === true);

    fwrite(STDOUT, "Capability resolver smoke test passed.\n");
    fwrite(STDOUT, "Validated active providers, preferences, conflicts, and requirement resolution.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: '.$error->getMessage()."\n");
    exit(1);
}
