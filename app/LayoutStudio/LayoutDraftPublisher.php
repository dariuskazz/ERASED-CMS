<?php
declare(strict_types=1);

namespace Erased\LayoutStudio;

use Erased\Homepage\HomepagePlacementRepository;
use PDO;
use RuntimeException;
use Throwable;

final class LayoutDraftPublisher
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LayoutDraftRepository $drafts,
        private readonly LayoutSerializer $serializer,
        private readonly HomepagePlacementRepository $published,
    ) {
    }

    /** @return array{revision:int,published:int} */
    public function publish(string $profileId,string $targetType='homepage',string $targetId='homepage'): array
    {
        $key=LayoutDraftService::key($profileId,$targetType,$targetId);
        $draft=$this->drafts->find($key);
        if($draft===null) throw new RuntimeException('Layout draft does not exist.');

        $json=json_encode($draft->payload(),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if(!is_string($json)) throw new RuntimeException('Could not encode layout draft for publication.');
        $placements=$this->serializer->decode($json);

        $this->pdo->beginTransaction();
        try{
            $this->published->clearProfile($profileId);
            foreach($placements as $placement){
                $this->published->save($placement);
            }
            $this->pdo->commit();
        }catch(Throwable $error){
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        return ['revision'=>$draft->revision(),'published'=>count($placements)];
    }
}
