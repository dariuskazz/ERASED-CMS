<?php
declare(strict_types=1);

namespace Erased\Packages;

use RuntimeException;

final class PackageDependencyResolver
{
    /**
     * @param array<string,PackageManifest> $available
     * @param array<string,string> $installedVersions
     * @return list<PackageManifest>
     */
    public function resolve(
        PackageManifest $target,
        array $available,
        array $installedVersions = [],
    ): array {
        $resolved = [];
        $visiting = [];
        $visited = [];

        $visit = function (PackageManifest $package) use (
            &$visit,
            &$resolved,
            &$visiting,
            &$visited,
            $available,
            $installedVersions,
        ): void {
            $id = $package->id();

            if (isset($visited[$id])) {
                return;
            }

            if (isset($visiting[$id])) {
                throw new RuntimeException("Circular package dependency detected at '{$id}'.");
            }

            $visiting[$id] = true;

            foreach ($package->dependencies() as $dependency) {
                [$dependencyId, $constraint] = $this->parseDependency($dependency);

                if (isset($installedVersions[$dependencyId])) {
                    if (!$this->matchesConstraint($installedVersions[$dependencyId], $constraint)) {
                        throw new RuntimeException(
                            "Installed dependency '{$dependencyId}' does not satisfy '{$constraint}'.",
                        );
                    }

                    continue;
                }

                if (!isset($available[$dependencyId])) {
                    throw new RuntimeException("Missing package dependency '{$dependencyId}'.");
                }

                $candidate = $available[$dependencyId];
                if (!$this->matchesConstraint($candidate->version(), $constraint)) {
                    throw new RuntimeException(
                        "Package dependency '{$dependencyId}' version {$candidate->version()} does not satisfy '{$constraint}'.",
                    );
                }

                $visit($candidate);
            }

            unset($visiting[$id]);
            $visited[$id] = true;
            $resolved[] = $package;
        };

        $visit($target);

        return $resolved;
    }

    /**
     * @param array<string,string> $installedVersions package_id => installed version
     * @return list<array{id:string,constraint:string,status:string,installed_version:?string}>
     */
    public function describeDependencies(PackageManifest $target, array $installedVersions): array
    {
        $described = [];

        foreach ($target->dependencies() as $dependency) {
            [$dependencyId, $constraint] = $this->parseDependency($dependency);
            $installedVersion = $installedVersions[$dependencyId] ?? null;

            if ($installedVersion === null) {
                $status = 'missing';
            } elseif ($this->matchesConstraint($installedVersion, $constraint)) {
                $status = 'satisfied';
            } else {
                $status = 'version-mismatch';
            }

            $described[] = [
                'id' => $dependencyId,
                'constraint' => $constraint,
                'status' => $status,
                'installed_version' => $installedVersion,
            ];
        }

        return $described;
    }

    /** @return array{0:string,1:string} */
    private function parseDependency(string $dependency): array
    {
        $parts = preg_split('/\s+/', trim($dependency), 2);
        $id = trim((string)($parts[0] ?? ''));
        $constraint = trim((string)($parts[1] ?? '*'));

        if ($id === '' || !preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id)) {
            throw new RuntimeException("Invalid dependency declaration '{$dependency}'.");
        }

        return [$id, $constraint === '' ? '*' : $constraint];
    }

    private function matchesConstraint(string $version, string $constraint): bool
    {
        if ($constraint === '*' || $constraint === '') {
            return true;
        }

        if (preg_match('/^(>=|<=|>|<|=|\^|~)?\s*(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)$/', $constraint, $matches) !== 1) {
            throw new RuntimeException("Unsupported version constraint '{$constraint}'.");
        }

        $operator = $matches[1] ?: '=';
        $required = $matches[2];

        return match ($operator) {
            '>' => version_compare($version, $required, '>'),
            '>=' => version_compare($version, $required, '>='),
            '<' => version_compare($version, $required, '<'),
            '<=' => version_compare($version, $required, '<='),
            '=' => version_compare($version, $required, '='),
            '^' => $this->matchesCaret($version, $required),
            '~' => $this->matchesTilde($version, $required),
            default => false,
        };
    }

    private function matchesCaret(string $version, string $required): bool
    {
        [$major, $minor, $patch] = array_map('intval', explode('.', $required, 3));

        if ($major > 0) {
            $upper = ($major + 1).'.0.0';
        } elseif ($minor > 0) {
            $upper = '0.'.($minor + 1).'.0';
        } else {
            $upper = '0.0.'.($patch + 1);
        }

        return version_compare($version, $required, '>=')
            && version_compare($version, $upper, '<');
    }

    private function matchesTilde(string $version, string $required): bool
    {
        [$major, $minor] = array_map('intval', array_slice(explode('.', $required), 0, 2));
        $upper = $major.'.'.($minor + 1).'.0';

        return version_compare($version, $required, '>=')
            && version_compare($version, $upper, '<');
    }
}
