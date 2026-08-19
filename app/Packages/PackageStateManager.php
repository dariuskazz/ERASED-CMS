<?php
declare(strict_types=1);

namespace Erased\Packages;

use RuntimeException;

final class PackageStateManager
{
    public function __construct(private readonly InstalledPackageRepository $repository)
    {
    }

    /** @return array<string,mixed> */
    public function assertCanEnable(string $packageId): array
    {
        $package = $this->requiredPackage($packageId);

        foreach ($this->dependencyRequirements($package) as [$dependencyId, $constraint]) {
            $dependency = $this->repository->find($dependencyId);
            if ($dependency === null) {
                throw new RuntimeException("Cannot enable '{$packageId}': dependency '{$dependencyId}' is not installed.");
            }
            if (!$dependency['enabled']) {
                throw new RuntimeException("Cannot enable '{$packageId}': dependency '{$dependencyId}' is disabled.");
            }
            if (!$this->matchesConstraint((string)$dependency['version'], $constraint)) {
                throw new RuntimeException(
                    "Cannot enable '{$packageId}': dependency '{$dependencyId}' version {$dependency['version']} does not satisfy '{$constraint}'.",
                );
            }
        }

        return $package;
    }

    /** @return array<string,mixed> */
    public function assertCanDisable(string $packageId): array
    {
        $package = $this->requiredPackage($packageId);

        foreach ($this->repository->all() as $candidate) {
            if (!$candidate['enabled'] || $candidate['package_id'] === $packageId) {
                continue;
            }

            foreach ($this->dependencyRequirements($candidate) as [$dependencyId]) {
                if ($dependencyId === $packageId) {
                    throw new RuntimeException(
                        "Cannot disable '{$packageId}': enabled package '{$candidate['package_id']}' depends on it.",
                    );
                }
            }
        }

        return $package;
    }

    /**
     * Uninstall had no equivalent to assertCanDisable() - a package could be
     * uninstalled unconditionally even while another enabled package
     * declared a dependency on it, leaving the dependent enabled with no
     * warning until it next tried to use the now-gone package's classes or
     * services. Reuses the same "does another enabled package depend on
     * this one" check, just phrased for uninstall's stronger action.
     * @return array<string,mixed>
     */
    public function assertCanUninstall(string $packageId): array
    {
        $package = $this->requiredPackage($packageId);

        foreach ($this->repository->all() as $candidate) {
            if (!$candidate['enabled'] || $candidate['package_id'] === $packageId) {
                continue;
            }

            foreach ($this->dependencyRequirements($candidate) as [$dependencyId]) {
                if ($dependencyId === $packageId) {
                    throw new RuntimeException(
                        "Cannot uninstall '{$packageId}': enabled package '{$candidate['package_id']}' depends on it.",
                    );
                }
            }
        }

        return $package;
    }

    public function enable(string $packageId): void
    {
        $this->assertCanEnable($packageId);
        $this->repository->setEnabled($packageId, true);
    }

    public function disable(string $packageId): void
    {
        $this->assertCanDisable($packageId);
        $this->repository->setEnabled($packageId, false);
    }

    public function persistEnabled(string $packageId, bool $enabled): void
    {
        $this->requiredPackage($packageId);
        $this->repository->setEnabled($packageId, $enabled);
    }

    /** @return array<string,mixed> */
    private function requiredPackage(string $packageId): array
    {
        $package = $this->repository->find($packageId);
        if ($package === null) {
            throw new RuntimeException("Installed package not found: {$packageId}");
        }
        return $package;
    }

    /** @param array<string,mixed> $package @return list<array{0:string,1:string}> */
    private function dependencyRequirements(array $package): array
    {
        $dependencies = $package['manifest']['dependencies'] ?? [];
        if (!is_array($dependencies)) {
            return [];
        }

        $requirements = [];
        foreach ($dependencies as $dependency) {
            if (!is_string($dependency)) {
                continue;
            }
            $parts = preg_split('/\s+/', trim($dependency), 2);
            $id = trim((string)($parts[0] ?? ''));
            $constraint = trim((string)($parts[1] ?? '*'));
            if ($id === '' || !preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id)) {
                throw new RuntimeException("Invalid dependency declaration '{$dependency}'.");
            }
            $requirements[] = [$id, $constraint === '' ? '*' : $constraint];
        }
        return $requirements;
    }

    private function matchesConstraint(string $version, string $constraint): bool
    {
        if ($constraint === '' || $constraint === '*') {
            return true;
        }
        if (preg_match('/^(>=|<=|>|<|=|\^|~)?\s*(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)$/', $constraint, $matches) !== 1) {
            throw new RuntimeException("Unsupported version constraint '{$constraint}'.");
        }

        $operator = $matches[1] ?: '=';
        $required = $matches[2];
        if ($operator === '^') {
            [$major, $minor, $patch] = array_map('intval', explode('.', $required, 3));
            $upper = $major > 0
                ? ($major + 1).'.0.0'
                : ($minor > 0 ? '0.'.($minor + 1).'.0' : '0.0.'.($patch + 1));
            return version_compare($version, $required, '>=') && version_compare($version, $upper, '<');
        }
        if ($operator === '~') {
            [$major, $minor] = array_map('intval', array_slice(explode('.', $required), 0, 2));
            $upper = $major.'.'.($minor + 1).'.0';
            return version_compare($version, $required, '>=') && version_compare($version, $upper, '<');
        }

        return version_compare($version, $required, $operator);
    }
}
