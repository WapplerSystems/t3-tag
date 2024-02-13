<?php

$newColumns = [
    'tags' => [
        'config' => [
            'type' => 'tag',
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $newColumns);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_users', 'tags', '', 'after:categories');
