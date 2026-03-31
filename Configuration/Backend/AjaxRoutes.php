<?php

declare(strict_types=1);

use TYPO3\CMS\Backend\Controller;

return [
    'tag_create' => [
        'path' => '/tag/create',
        'methods' => ['POST'],
        'target' => Controller\FormTagAjaxController::class . '::createAction',
    ],
];