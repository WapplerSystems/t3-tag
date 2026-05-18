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

namespace TYPO3\CMS\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Handle FormEngine AJAX calls for the tag field element.
 *
 * @internal This class is a specific Backend controller implementation and is not considered part of the Public TYPO3 API.
 */
#[AsController]
readonly class FormTagAjaxController extends AbstractFormEngineAjaxController
{
    /**
     * Creates a new sys_tag record in the configured storage page.
     * If a tag with the same title already exists in that page it is returned instead.
     */
    public function createAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $title = trim((string)($body['title'] ?? ''));
        $pid = (int)($body['pid'] ?? 0);
        $tableName = (string)($body['tableName'] ?? '');
        $fieldName = (string)($body['fieldName'] ?? '');
        $signature = (string)($body['signature'] ?? '');

        if ($title === '' || $pid <= 0) {
            return new JsonResponse(['error' => 'Invalid parameters'], 400);
        }

        // Verify HMAC – ties the request to table/field/pid as computed in TagElement::render()
        $expectedHash = GeneralUtility::makeInstance(HashService::class)
            ->hmac($tableName . $fieldName . (string)$pid, 'FormTagCreate');
        if (!hash_equals($expectedHash, $signature)) {
            return new JsonResponse(['error' => 'Security check failed'], 403);
        }

        // Return existing tag instead of creating a duplicate
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_tag');
        $existingRecord = $queryBuilder
            ->select('uid', 'title')
            ->from('sys_tag')
            ->where(
                $queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        if ($existingRecord) {
            return new JsonResponse(['uid' => (int)$existingRecord['uid'], 'title' => $existingRecord['title']]);
        }

        // Create new tag record via DataHandler so all hooks and workspace logic are respected
        $newId = 'NEW_tag_1';
        $dataMap = [
            'sys_tag' => [
                $newId => [
                    'pid' => $pid,
                    'title' => $title,
                ],
            ],
        ];
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();

        $newUid = $dataHandler->substNEWwithIDs[$newId] ?? 0;
        if (!$newUid) {
            return new JsonResponse(['error' => 'Tag could not be created'], 500);
        }

        return new JsonResponse(['uid' => (int)$newUid, 'title' => $title]);
    }
}
