<?php

declare(strict_types=1);

namespace TYPO3\CMS\Extbase\Domain\Repository;


class TagRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{


    public function findByPid($pid)
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching(
            $query->equals('pid', $pid)
        );
        return $query->execute();
    }


}
