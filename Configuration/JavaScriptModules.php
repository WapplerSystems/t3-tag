<?php

return [
    'dependencies' => [
        'backend',
    ],
    'tags' => [
        'backend.form',
    ],
    'imports' => [
        '@typo3/backend/tag/select.js' => 'EXT:tagging/Resources/Public/JavaScript/tom-select.typo3.js',
        '@typo3/backend/tag/element.js' => 'EXT:tagging/Resources/Public/JavaScript/tag-element.js',
    ],
];
