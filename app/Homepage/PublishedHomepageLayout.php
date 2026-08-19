<?php
declare(strict_types=1);

namespace Erased\Homepage;

use PDO;
use Throwable;

final class PublishedHomepageLayout
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return list<BlockPlacement> */
    public function placements(string $profileId='default'): array
    {
        try {
            return (new HomepagePlacementRepository($this->pdo))->forProfile($profileId);
        } catch (Throwable) {
            return [];
        }
    }
}
