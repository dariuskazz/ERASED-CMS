<?php
declare(strict_types=1);

namespace ErasedCommerce\Domain;

use DateTimeImmutable;
use PDO;

final class CouponRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM commerce_coupons ORDER BY created_at DESC')->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM commerce_coupons WHERE id=? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * Re-checked live at checkout time, never trusted from a prior read -
     * mirrors ProductRepository's own "never trust the cached copy"
     * principle. Binds a PHP-computed timestamp rather than calling NOW()
     * in SQL, since this package's SQL is otherwise driver-agnostic (this
     * is what lets its tests run against sqlite).
     *
     * @return array<string,mixed>|null
     */
    public function findValidByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'SELECT * FROM commerce_coupons WHERE code=:code AND active=1 '
            .'AND (starts_at IS NULL OR starts_at<=:now1) AND (expires_at IS NULL OR expires_at>=:now2) '
            .'AND (max_uses IS NULL OR used_count<max_uses) LIMIT 1'
        );
        $statement->execute([':code' => $code, ':now1' => $now, ':now2' => $now]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO commerce_coupons (code, type, value, max_uses, starts_at, expires_at, active) '
            .'VALUES (:code, :type, :value, :max_uses, :starts_at, :expires_at, :active)'
        );
        $statement->execute($this->bindParams($data));
        return (int)$this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE commerce_coupons SET code=:code, type=:type, value=:value, max_uses=:max_uses, '
            .'starts_at=:starts_at, expires_at=:expires_at, active=:active WHERE id=:id'
        );
        $params = $this->bindParams($data);
        $params[':id'] = $id;
        $statement->execute($params);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM commerce_coupons WHERE id=?');
        $statement->execute([$id]);
    }

    /** Payment already happened by the time this is called (only from OrderRepository::markPaid()) - a rare race here is a bookkeeping nit, not a correctness bug worth guarding against. */
    public function incrementUsage(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE commerce_coupons SET used_count=used_count+1 WHERE id=?');
        $statement->execute([$id]);
    }

    /** @param array<string,mixed> $coupon */
    public function discountFor(array $coupon, int $subtotalMinor): int
    {
        if ($coupon['type'] === 'percent') {
            return (int)round($subtotalMinor * (int)$coupon['value'] / 100);
        }
        return min((int)$coupon['value'], $subtotalMinor);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function bindParams(array $data): array
    {
        return [
            ':code' => strtoupper(trim((string)($data['code'] ?? ''))),
            ':type' => in_array($data['type'] ?? 'percent', ['percent', 'fixed'], true) ? $data['type'] : 'percent',
            ':value' => (int)($data['value'] ?? 0),
            ':max_uses' => ($data['max_uses'] ?? null) !== null && $data['max_uses'] !== '' ? (int)$data['max_uses'] : null,
            ':starts_at' => ($data['starts_at'] ?? '') !== '' ? (string)$data['starts_at'] : null,
            ':expires_at' => ($data['expires_at'] ?? '') !== '' ? (string)$data['expires_at'] : null,
            ':active' => !empty($data['active']) ? 1 : 0,
        ];
    }
}
