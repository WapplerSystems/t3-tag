<?php

namespace TYPO3\CMS\Frontend\DataProcessing;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;


class TagsProcessor implements DataProcessorInterface
{
    /**
     *
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array the processed data as key/value store
     */
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData)
    {
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }

        $fieldName = $cObj->stdWrapValue('fieldName', $processorConfiguration, 'tags');
        if (($processedData['data'][$fieldName] ?? 0) === 0) {
            return $processedData;
        }

        $table = $cObj->stdWrapValue('table', $processorConfiguration, 'tt_content');
        $uid = (int)$processedData['data']['uid'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_tag');

        $tags = $queryBuilder
            ->select('sys_tag.*')
            ->from('sys_tag')
            ->join(
                'sys_tag',
                'sys_tag_record_mm',
                'mm',
                $queryBuilder->expr()->eq('sys_tag.uid', $queryBuilder->quoteIdentifier('mm.uid_local'))
            )
            ->where(
                $queryBuilder->expr()->eq('mm.tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('mm.fieldname', $queryBuilder->createNamedParameter($fieldName)),
                $queryBuilder->expr()->eq('mm.uid_foreign', $queryBuilder->createNamedParameter($uid))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $targetVariableName = $cObj->stdWrapValue('as', $processorConfiguration, 'tags');
        $processedData[$targetVariableName] = $tags;

        return $processedData;

    }

}
