<?php
declare(strict_types=1);

namespace Erased\Website;

use Erased\Capabilities\CapabilityResolver;
use PDO;
use RuntimeException;

/**
 * Website profiles are a switchable bundle of the site-identity settings
 * listed in CONFIG_KEYS - deliberately a curated subset, not every row in
 * the settings table. Content, installed packages, and every other setting
 * are untouched by activation, so switching profiles can never destroy data.
 *
 * "Snapshot and rollback" reuses activate() itself: activating a profile
 * always saves the currently-active configuration as a new archived profile
 * first, and rolling back is just activating that archived snapshot again.
 *
 * Reads and writes the settings table directly (mirroring bootstrap.php's
 * global setting()/set_setting() functions) instead of calling those global
 * functions, which are hardwired to the MySQL-only db() singleton - this
 * keeps the service testable against sqlite like the rest of the codebase's
 * services. None of CONFIG_KEYS match erased_sensitive_setting(), so unlike
 * the global helpers this never needs the enc:v1 encryption path.
 */
final class WebsiteProfileService
{
    /** @var list<string> */
    public const CONFIG_KEYS = [
        'site_name', 'site_tagline', 'seo_description',
        'theme_accent', 'admin_theme', 'website_theme', 'header_layout',
        'footer_text', 'nav_admin_label',
    ];

    public function __construct(
        private readonly WebsiteProfileRepository $profiles,
        private readonly WebsiteTypeManager $types,
        private readonly PDO $pdo,
    ) {
    }

    /** Idempotent: creates one starter profile per registry type that doesn't already have one. @return list<string> type ids seeded */
    public function seedStarterProfiles(): array
    {
        $seeded = [];
        foreach ($this->types->all() as $type) {
            $typeId = (string)($type['id'] ?? '');
            if ($typeId === '' || $this->profiles->existsForType($typeId)) {
                continue;
            }

            $config = $this->currentConfig();
            $config['site_name'] = (string)($type['name'] ?? $typeId);
            $config['seo_description'] = (string)($type['description'] ?? '');

            $this->profiles->create($typeId, (string)($type['name'] ?? $typeId), $config, isStarter: true, status: 'draft');
            $seeded[] = $typeId;
        }

        return $seeded;
    }

    /**
     * Read-only reporting, not a gate: the active profile's website type
     * lists "recommended modules" in website-types/registry.json (e.g. Web
     * Shop -> ["commerce"]), previously pure unused metadata. This reports
     * whether each recommendation is currently satisfied by an active
     * capability provider - activating or previewing a profile is never
     * blocked by a gap here, it's only made visible (surfaced in Developer
     * Mode).
     * @return list<array{module:string,satisfied:bool}>
     */
    public function capabilityStatus(?CapabilityResolver $capabilities): array
    {
        if ($capabilities === null) {
            return [];
        }

        $active = $this->profiles->findActive();
        if ($active === null) {
            return [];
        }

        $type = $this->types->find((string)($active['type_id'] ?? ''));
        $modules = is_array($type['recommended_modules'] ?? null) ? $type['recommended_modules'] : [];

        $status = [];
        foreach ($modules as $module) {
            if (!is_string($module) || trim($module) === '') {
                continue;
            }
            $status[] = ['module' => $module, 'satisfied' => $capabilities->has($module)];
        }

        return $status;
    }

    /** @return array<string,string> the curated setting keys and their current live values */
    public function currentConfig(): array
    {
        $config = [];
        foreach (self::CONFIG_KEYS as $key) {
            $config[$key] = $this->readSetting($key);
        }

        return $config;
    }

    /**
     * Snapshots the currently-active configuration into a new archived
     * profile, applies the target profile's configuration to live settings,
     * and marks it active. Returns the id of the snapshot that was created
     * (null if there was no prior active configuration to preserve).
     */
    public function activate(int $profileId): ?int
    {
        $target = $this->profiles->find($profileId);
        if ($target === null) {
            throw new RuntimeException("Website profile not found: {$profileId}");
        }

        $snapshotId = null;
        $previouslyActive = $this->profiles->findActive();
        $snapshotConfig = $this->currentConfig();
        $hasLiveConfig = array_filter($snapshotConfig, static fn(string $v): bool => $v !== '') !== [];

        if ($hasLiveConfig) {
            $snapshotName = $previouslyActive !== null
                ? 'Snapshot before activating "'.$target['name'].'" (was: '.$previouslyActive['name'].')'
                : 'Snapshot before activating "'.$target['name'].'"';
            $snapshotId = $this->profiles->create('snapshot', $snapshotName, $snapshotConfig, isStarter: false, status: 'archived');
        }

        foreach (self::CONFIG_KEYS as $key) {
            if (array_key_exists($key, $target['config'])) {
                $this->writeSetting($key, (string)$target['config'][$key]);
            }
        }

        if ($previouslyActive !== null && $previouslyActive['id'] !== $target['id']) {
            $this->profiles->setStatus((int)$previouslyActive['id'], 'draft');
        }
        $this->profiles->setStatus($profileId, 'active');

        return $snapshotId;
    }

    private function readSetting(string $key): string
    {
        $statement = $this->pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();

        return $value === false ? '' : (string)$value;
    }

    private function writeSetting(string $key, string $value): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value'
            : 'INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)';
        $this->pdo->prepare($sql)->execute([$key, $value]);
    }
}
