<?php

declare(strict_types=1);

namespace TYPO3\CMS\Extbase\Domain\Repository;


use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class TagRepository extends Repository
{


    public function findByPid($pid): QueryResultInterface|array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching(
            $query->equals('pid', $pid)
        );
        return $query->execute();
    }


}
