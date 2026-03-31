<?php

$EM_CONF['tagging'] = [
    'title' => 'Tag field for elements like categories',
    'description' => 'A patch for TYPO3 to easily add tags just like categories to any element',
    'category' => 'fe',
    'version' => '14.0.0',
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
