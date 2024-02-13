<?php

declare(strict_types=1);

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

namespace TYPO3\CMS\Backend\Form\FormDataProvider;

use TYPO3\CMS\Backend\Clipboard\Clipboard;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Tree\TableConfiguration\ArrayTreeRenderer;
use TYPO3\CMS\Core\Tree\TableConfiguration\TableConfigurationTree;
use TYPO3\CMS\Core\Tree\TableConfiguration\TreeDataProviderFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Data provider for type=tag
 *
 * Used in combination with CategoryElement to create the base HTML for the tag list.
 *
 */
class TcaTag extends AbstractItemProvider implements FormDataProviderInterface
{
    /**
     * Sanitize config options and resolve tag items if requested.
     */
    public function addData(array $result): array
    {
        foreach ($result['processedTca']['columns'] as $fieldName => $fieldConfig) {
            if (empty($fieldConfig['config']['type']) || $fieldConfig['config']['type'] !== 'tag') {
                continue;
            }

            // Sanitize max items, set to 99999 if not defined
            $result['processedTca']['columns'][$fieldName]['config']['maxitems'] = MathUtility::forceIntegerInRange(
                $fieldConfig['config']['maxitems'] ?? 0,
                0,
                99999
            );
            if ($result['processedTca']['columns'][$fieldName]['config']['maxitems'] === 0) {
                $result['processedTca']['columns'][$fieldName]['config']['maxitems'] = 99999;
            }

            $databaseRowFieldContent = '';
            if (!empty($result['databaseRow'][$fieldName])) {
                $databaseRowFieldContent = (string)$result['databaseRow'][$fieldName];
            }

            $items = [];
            $sanitizedClipboardElements = [];
            if (empty($fieldConfig['config']['allowed'])) {
                throw new \RuntimeException(
                    'Mandatory TCA config setting "allowed" missing in field "' . $fieldName . '" of table "' . $result['tableName'] . '"',
                    1482250512
                );
            }

            // In case of vanilla uid, 0 is used to query relations by splitting $databaseRowFieldContent (possible defVals)
            $MMuid = MathUtility::canBeInterpretedAsInteger($result['databaseRow']['uid']) ? $result['databaseRow']['uid'] : 0;

            $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);
            $relationHandler->start(
                $databaseRowFieldContent,
                $fieldConfig['config']['allowed'] ?? '',
                $fieldConfig['config']['MM'] ?? '',
                $MMuid,
                $result['tableName'] ?? '',
                $fieldConfig['config'] ?? []
            );
            $relationHandler->getFromDB();
            $relationHandler->processDeletePlaceholder();
            $relations = $relationHandler->getResolvedItemArray();
            foreach ($relations as $relation) {
                $tableName = $relation['table'];
                $record = $relation['record'];
                BackendUtility::workspaceOL($tableName, $record);
                $title = BackendUtility::getRecordTitle($tableName, $record, false, false);
                $items[] = [
                    'table' => $tableName,
                    'uid' => $record['uid'] ?? null,
                    'title' => $title,
                    'row' => $record,
                ];
            }

            // Register elements from clipboard
            $allowed = GeneralUtility::trimExplode(',', $fieldConfig['config']['allowed'], true);
            $clipboard = GeneralUtility::makeInstance(Clipboard::class);
            $clipboard->initializeClipboard();
            if ($allowed[0] !== '*') {
                // Only some tables, filter them:
                foreach ($allowed as $tablename) {
                    foreach ($clipboard->elFromTable($tablename) as $recordUid) {
                        $record = BackendUtility::getRecordWSOL($tablename, $recordUid);
                        $sanitizedClipboardElements[] = [
                            'title' => BackendUtility::getRecordTitle($tablename, $record),
                            'value' => $tablename . '_' . $recordUid,
                        ];
                    }
                }
            } else {
                // All tables allowed for relation:
                $clipboardElements = array_keys($clipboard->elFromTable(''));
                foreach ($clipboardElements as $elementValue) {
                    [$elementTable, $elementUid] = explode('|', $elementValue);
                    $record = BackendUtility::getRecordWSOL($elementTable, (int)$elementUid);
                    $sanitizedClipboardElements[] = [
                        'title' => BackendUtility::getRecordTitle($elementTable, $record),
                        'value' => $elementTable . '_' . $elementUid,
                    ];
                }
            }

            $result['databaseRow'][$fieldName] = $items;
            $result['processedTca']['columns'][$fieldName]['config']['clipboardElements'] = $sanitizedClipboardElements;
        }

        return $result;
    }

    /**
     * A couple of tree specific config parameters can be overwritten via page TS.
     * Pick those that influence the data fetching and write them into the config
     * given to the tree data provider.
     */
    protected function overrideConfigFromPageTSconfig(
        array $result,
        string $table,
        string $fieldName,
        array $fieldConfig
    ): array {
        $pageTsConfig = $result['pageTsConfig']['TCEFORM.'][$table . '.'][$fieldName . '.']['config.'] ?? [];



        return $fieldConfig;
    }

    /**
     * Validate and sanitize the tag field value.
     */
    protected function processCategoryFieldValue(array $result, string $fieldName): array
    {
        $fieldConfig = $result['processedTca']['columns'][$fieldName];
        $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);

        $newDatabaseValueArray = [];
        $currentDatabaseValueArray = array_key_exists($fieldName, $result['databaseRow']) ? $result['databaseRow'][$fieldName] : [];

        if (!empty($fieldConfig['config']['MM']) && $result['command'] !== 'new') {
            $relationHandler->start(
                implode(',', $currentDatabaseValueArray),
                $fieldConfig['config']['foreign_table'],
                $fieldConfig['config']['MM'],
                $result['databaseRow']['uid'],
                $result['tableName'],
                $fieldConfig['config']
            );
            $newDatabaseValueArray = array_merge($newDatabaseValueArray, $relationHandler->getValueArray());
        } else {
            // If not dealing with MM relations, use default live uid, not versioned uid for record relations
            $relationHandler->start(
                implode(',', $currentDatabaseValueArray),
                $fieldConfig['config']['foreign_table'],
                '',
                $this->getLiveUid($result),
                $result['tableName'],
                $fieldConfig['config']
            );
            $databaseIds = array_merge($newDatabaseValueArray, $relationHandler->getValueArray());
            // remove all items from the current DB values if not available as relation
            $newDatabaseValueArray = array_values(array_intersect($currentDatabaseValueArray, $databaseIds));
        }

        // Since only uids are allowed, the array must be unique
        return array_unique($newDatabaseValueArray);
    }

    protected function isTargetRenderType($fieldConfig): bool
    {
        // Type tag does not support any renderType
        return !isset($fieldConfig['config']['renderType']);
    }

    protected function initializeDefaultFieldConfig(array $fieldConfig): array
    {

        // Calculate maxitems value, while 0 will fall back to 99999
        $fieldConfig['config']['maxitems'] = MathUtility::forceIntegerInRange(
            $fieldConfig['config']['maxitems'] ?? 0,
            0,
            99999
        ) ?: 99999;

        return $fieldConfig;
    }
}
