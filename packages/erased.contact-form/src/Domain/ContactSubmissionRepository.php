<?php
declare(strict_types=1);

namespace ErasedContactForm\Domain;

use PDO;

final class ContactSubmissionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array{name:string,email:string,subject:string,message:string,ip_address:string} $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO contact_submissions (name, email, subject, message, ip_address) VALUES (:name, :email, :subject, :message, :ip_address)'
        );
        $statement->execute([
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':subject' => $data['subject'],
            ':message' => $data['message'],
            ':ip_address' => $data['ip_address'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM contact_submissions ORDER BY created_at DESC')->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM contact_submissions WHERE id=? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function markRead(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE contact_submissions SET read_at=NOW() WHERE id=? AND read_at IS NULL');
        $statement->execute([$id]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM contact_submissions WHERE id=?');
        $statement->execute([$id]);
    }

    public function unreadCount(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM contact_submissions WHERE read_at IS NULL')->fetchColumn();
    }

    /** Same recent-submission rate window shape as the comment form's own IP throttle. */
    public function recentFromIp(string $ip, int $minutes = 10): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM contact_submissions WHERE ip_address=? AND created_at>DATE_SUB(NOW(),INTERVAL ' . max(1, $minutes) . ' MINUTE)');
        $statement->execute([$ip]);
        return (int)$statement->fetchColumn();
    }
}
