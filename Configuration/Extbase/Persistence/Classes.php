<?php

declare(strict_types=1);

return [
    \TYPO3\CMS\Extbase\Domain\Model\Tag::class => [
        'tableName' => 'sys_tag',
        'properties' => [
            'title' => [
                'fieldName' => 'title',
            ],
            'description' => [
                'fieldName' => 'description',
            ],
        ],
    ],
];
