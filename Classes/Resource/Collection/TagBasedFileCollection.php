<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Core\Resource\Collection;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A collection containing a set files belonging to certain categories.
 * This collection is persisted to the database with the accordant tag identifiers.
 */
class TagBasedFileCollection extends AbstractFileCollection
{
    /**
     * @var string
     */
    protected static $storageTableName = 'sys_file_collection';

    /**
     * @var string
     */
    protected static $type = 'categories';

    /**
     * @var string
     */
    protected static $itemsCriteriaField = 'tag';

    /**
     * @var string
     */
    protected $itemTableName = 'sys_tag';

    /**
     * Populates the content-entries of the collection
     */
    public function loadContents()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_tag');
        $queryBuilder->getRestrictions()->removeAll();
        $statement = $queryBuilder->select('sys_file_metadata.file')
            ->from('sys_tag')
            ->join(
                'sys_tag',
                'sys_tag_record_mm',
                'sys_tag_record_mm',
                $queryBuilder->expr()->eq(
                    'sys_tag_record_mm.uid_local',
                    $queryBuilder->quoteIdentifier('sys_tag.uid')
                )
            )
            ->join(
                'sys_tag_record_mm',
                'sys_file_metadata',
                'sys_file_metadata',
                $queryBuilder->expr()->eq(
                    'sys_tag_record_mm.uid_foreign',
                    $queryBuilder->quoteIdentifier('sys_file_metadata.uid')
                )
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'sys_tag.uid',
                    $queryBuilder->createNamedParameter($this->getItemsCriteria(), Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_tag_record_mm.tablenames',
                    $queryBuilder->createNamedParameter('sys_file_metadata')
                )
            )
            ->executeQuery();
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        while ($record = $statement->fetchAssociative()) {
            $this->add($resourceFactory->getFileObject((int)$record['file']));
        }
    }
}
