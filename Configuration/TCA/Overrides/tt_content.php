<?php

$newColumns = [
    'tags' => [
        'config' => [
            'type' => 'tag',
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_content', $newColumns);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tags', '', 'after:categories');
