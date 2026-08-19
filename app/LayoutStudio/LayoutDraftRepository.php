<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use JsonException;
use PDO;
use RuntimeException;

final class LayoutDraftRepository
{
    private readonly string $table;

    public function __construct(private readonly PDO $pdo, string $table = 'layout_drafts')
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) throw new RuntimeException('Layout draft table name is invalid.');
        $this->table = $table;
    }

    public function find(string $key): ?LayoutDraft
    {
        $q = $this->pdo->prepare('SELECT * FROM `'.$this->table.'` WHERE draft_key=:draft_key LIMIT 1');
        $q->execute([':draft_key'=>$key]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function create(LayoutDraft $draft): void
    {
        if ($this->find($draft->key()) !== null) throw new RuntimeException("Layout draft '{$draft->key()}' already exists.");
        $q = $this->pdo->prepare('INSERT INTO `'.$this->table.'` (draft_key,profile_id,target_type,target_id,name,status,revision,author_id,notes,payload_json) VALUES (:draft_key,:profile_id,:target_type,:target_id,:name,:status,:revision,:author_id,:notes,:payload_json)');
        $q->execute($this->parameters($draft));
    }

    public function save(LayoutDraft $draft, int $expectedRevision): LayoutDraft
    {
        $nextRevision = $expectedRevision + 1;
        $params = $this->parameters($draft);
        $params[':expected_revision'] = $expectedRevision;
        $params[':next_revision'] = $nextRevision;
        unset($params[':revision']);
        $q = $this->pdo->prepare('UPDATE `'.$this->table.'` SET profile_id=:profile_id,target_type=:target_type,target_id=:target_id,name=:name,status=:status,revision=:next_revision,author_id=:author_id,notes=:notes,payload_json=:payload_json,updated_at=CURRENT_TIMESTAMP WHERE draft_key=:draft_key AND revision=:expected_revision');
        $q->execute($params);
        if ($q->rowCount() !== 1) throw new RuntimeException('Layout draft changed in another session. Reload before saving.');
        return new LayoutDraft($draft->key(),$draft->profileId(),$draft->targetType(),$draft->targetId(),$draft->name(),$draft->status(),$nextRevision,$draft->authorId(),$draft->notes(),$draft->payload());
    }

    public function delete(string $key): void
    {
        $q = $this->pdo->prepare('DELETE FROM `'.$this->table.'` WHERE draft_key=:draft_key');
        $q->execute([':draft_key'=>$key]);
    }

    /** @return array<string,mixed> */
    private function parameters(LayoutDraft $draft): array
    {
        try { $json = json_encode($draft->payload(), JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
        catch (JsonException $e) { throw new RuntimeException('Could not encode layout draft payload.',0,$e); }
        return [':draft_key'=>$draft->key(),':profile_id'=>$draft->profileId(),':target_type'=>$draft->targetType(),':target_id'=>$draft->targetId(),':name'=>$draft->name(),':status'=>$draft->status(),':revision'=>$draft->revision(),':author_id'=>$draft->authorId(),':notes'=>$draft->notes(),':payload_json'=>$json];
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): LayoutDraft
    {
        try { $payload = json_decode((string)$row['payload_json'], true, 512, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new RuntimeException('Stored layout draft payload is invalid.',0,$e); }
        return new LayoutDraft((string)$row['draft_key'],(string)$row['profile_id'],(string)$row['target_type'],(string)$row['target_id'],(string)$row['name'],(string)$row['status'],(int)$row['revision'],$row['author_id']===null?null:(int)$row['author_id'],(string)($row['notes']??''),is_array($payload)?$payload:[]);
    }
}
