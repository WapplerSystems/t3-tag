<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Register the chip-based Tag form element under renderType 'tag'.
// TcaPreparationForTag sets renderType='tag' on every field with type='tag';
// the resulting type is 'group' so DataHandler and DB layout stay compatible.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1712434736] = [
    'nodeName' => 'tag',
    'priority' => 40,
    'class' => \TYPO3\CMS\Backend\Form\Element\TagElement::class,
];