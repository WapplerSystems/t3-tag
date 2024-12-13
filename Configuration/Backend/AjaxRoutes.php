<?php

use TYPO3\CMS\Backend\Controller;

return [

    'tag_create' => [
        'path' => '/tag/create',
        'target' => Controller\TagController::class . '::addAction',
    ],


];
