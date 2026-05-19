<?php

$EM_CONF['tagging'] = [
    'title' => 'Tagging',
    'description' => 'Adds a flexible tagging system to TYPO3 — assign colored tags to any record, similar to categories but with color support and custom styling.',
    'category' => 'fe',
    'version' => '14.0.1',
    'state' => 'stable',
    'author' => 'Sven Wappler',
    'author_email' => 'typo3YYYY@wappler.systems',
    'author_company' => 'WapplerSystems',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.4.99',
        ],
    ],
];
