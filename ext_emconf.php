<?php

$EM_CONF['tagging'] = [
    'title' => 'Tag field for elements like categories',
    'description' => 'A patch for TYPO3 to easily add tags just like categories to any element',
    'category' => 'fe',
    'version' => '12.0.2',
    'state' => 'stable',
    'author' => 'Sven Wappler',
    'author_email' => 'typo3YYYY@wappler.systems',
    'author_company' => 'WapplerSystems',
    'constraints' => [
        'depends' => [
            'typo3' => '12.0.0-12.4.99',
        ],
    ],
];
