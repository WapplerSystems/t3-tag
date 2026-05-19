<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Backend\Form\Element;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Tag field element – renders a chip-based tag picker in the TYPO3 form engine.
 *
 * Selected tags are shown as removable chips. Typing in the input field
 * triggers TYPO3's built-in suggest for existing sys_tag records. Pressing
 * Enter creates a new sys_tag record in the configured storage page
 * (createNewTagPid), if set.
 */
class TagElement extends AbstractFormElement
{
    protected $defaultFieldInformation = [
        'tcaDescription' => [
            'renderType' => 'tcaDescription',
        ],
    ];

    // All standard field controls are disabled by default – the chip UI is self-contained.
    protected $defaultFieldControl = [
        'elementBrowser' => [
            'renderType' => 'elementBrowser',
            'disabled' => true,
        ],
        'insertClipboard' => [
            'renderType' => 'insertClipboard',
            'disabled' => true,
            'after' => ['elementBrowser'],
        ],
        'editPopup' => [
            'renderType' => 'editPopup',
            'disabled' => true,
            'after' => ['insertClipboard'],
        ],
        'addRecord' => [
            'renderType' => 'addRecord',
            'disabled' => true,
            'after' => ['editPopup'],
        ],
        'listModule' => [
            'renderType' => 'listModule',
            'disabled' => true,
            'after' => ['addRecord'],
        ],
    ];

    protected $defaultFieldWizard = [
        'localizationStateSelector' => [
            'renderType' => 'localizationStateSelector',
        ],
        'otherLanguageContent' => [
            'renderType' => 'otherLanguageContent',
            'after' => ['localizationStateSelector'],
        ],
        'defaultLanguageDifferences' => [
            'renderType' => 'defaultLanguageDifferences',
            'after' => ['otherLanguageContent'],
        ],
    ];

    public function render(): array
    {
        $languageService = $this->getLanguageService();
        $resultArray = $this->initializeResultArray();
        $resultArray['labelHasBeenHandled'] = true;

        $table = $this->data['tableName'];
        $fieldName = $this->data['fieldName'];
        $row = $this->data['databaseRow'];
        $parameterArray = $this->data['parameterArray'];
        $config = $parameterArray['fieldConf']['config'];
        $elementName = $parameterArray['itemFormElName'];
        $recordTypeValue = $this->data['recordTypeValue'] ?? null;

        $selectedItems = $parameterArray['itemFormElValue'];
        $maxItems = $config['maxitems'];
        $fieldId = StringUtility::getUniqueId('tceforms-tag-');

        // Resolve PID for new tag creation from TCA config or PageTSconfig
        $createPid = 0;
        if (!empty($config['createNewTagPid'])) {
            $createPid = (int)$config['createNewTagPid'];
        } elseif (!empty($parameterArray['fieldTSConfig']['createNewTagPid'])) {
            $createPid = (int)$parameterArray['fieldTSConfig']['createNewTagPid'];
        }

        // HMAC ties the create-request to this exact table/field/pid combination
        $signature = GeneralUtility::makeInstance(HashService::class)
            ->hmac($table . $fieldName . (string)$createPid, 'FormTagCreate');

        // Build existing selections
        $listOfSelectedValues = [];
        $selectedItemsData = [];
        foreach ($selectedItems as $selectedItem) {
            $value = $selectedItem['table'] . '_' . $selectedItem['uid'];
            $listOfSelectedValues[] = $value;
            $title = $selectedItem['title'] ?: '[' . $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.no_title') . ']';
            $selectedItemsData[] = [
                'value' => $value,
                'title' => $title,
            ];
        }

        $fieldInformationResult = $this->renderFieldInformation();
        $fieldInformationHtml = $fieldInformationResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        // Read-only: render a simple disabled select
        if (!empty($config['readOnly'])) {
            $html = [];
            $html[] = $this->renderLabel($fieldId);
            $html[] = '<div class="formengine-field-item t3js-formengine-field-item">';
            $html[] =   $fieldInformationHtml;
            $html[] =   '<div class="tag-element-chips-readonly">';
            foreach ($selectedItemsData as $item) {
                $html[] = '<span class="tag-chip tag-chip--readonly">';
                $html[] =   '<span class="tag-chip-title">' . htmlspecialchars($item['title']) . '</span>';
                $html[] = '</span>';
            }
            $html[] =   '</div>';
            $html[] = '</div>';
            $resultArray['html'] = implode(LF, $html);
            return $resultArray;
        }

        // Suggest minimum characters
        $suggestMinimumCharacters = 2;
        if (isset($config['suggestOptions']['default']['minimumCharacters'])) {
            $suggestMinimumCharacters = max(0, (int)$config['suggestOptions']['default']['minimumCharacters']);
        }
        if (isset($parameterArray['fieldTSConfig']['suggest.']['default.']['minimumCharacters'])) {
            $suggestMinimumCharacters = max(0, (int)$parameterArray['fieldTSConfig']['suggest.']['default.']['minimumCharacters']);
        }
        $suggestMinimumCharacters = $suggestMinimumCharacters > 0 ? $suggestMinimumCharacters : 2;

        // Placeholder label: falls back if translation key is missing
        $placeholder = $languageService->sL('LLL:EXT:tagging/Resources/Private/Language/locallang_tca.xlf:tag.suggest_placeholder');
        if (!$placeholder) {
            $placeholder = $languageService->sL('LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:search.find_record');
        }
        $removeAriaLabel = $languageService->sL('LLL:EXT:tagging/Resources/Private/Language/locallang_tca.xlf:tag.remove_aria') ?: 'Remove tag';

        // FlexForm context (keep empty – tags are not typically used inside flex forms)
        $dataStructureIdentifier = '';
        $flexFormSheetName = '';
        $flexFormFieldName = '';
        $flexFormContainerName = '';
        $flexFormContainerFieldName = '';

        $fieldWizardResult = $this->renderFieldWizard();
        $fieldWizardHtml = $fieldWizardResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);

        // Build option elements for the hidden select (FormEngine needs them on initial load)
        $selectorOptionsHtml = [];
        foreach ($selectedItemsData as $item) {
            $selectorOptionsHtml[] = '<option value="' . htmlspecialchars($item['value']) . '">'
                . htmlspecialchars($item['title'])
                . '</option>';
        }

        $hiddenElementAttrs = array_merge(
            [
                'type' => 'hidden',
                'name' => $elementName,
                'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($config),
                'value' => implode(',', $listOfSelectedValues),
            ],
            $this->getOnFieldChangeAttrs('change', $parameterArray['fieldChangeFunc'] ?? [])
        );

        $html = [];
        $html[] = $this->renderLabel($fieldId);
        $html[] = '<div class="formengine-field-item t3js-formengine-field-item">';
        $html[] =   $fieldInformationHtml;
        $html[] =   '<div class="form-wizards-wrap">';
        $html[] =     '<div class="form-wizards-element">';

        // Chip UI wrapper – carries the data attributes our JS reads
        $html[] =       '<div class="tag-element-wrap"'
            . ' id="' . htmlspecialchars($fieldId) . '-wrap"'
            . ' data-createpid="' . htmlspecialchars((string)$createPid) . '"'
            . ' data-tablename="' . htmlspecialchars($table) . '"'
            . ' data-fieldname="' . htmlspecialchars($fieldName) . '"'
            . ' data-signature="' . htmlspecialchars($signature) . '"'
            . ' data-remove-label="' . htmlspecialchars($removeAriaLabel) . '"'
            . '>';

        // Input area: chips + suggest input side by side
        $html[] =         '<div class="tag-element-input-area">';

        // Chips for pre-existing tags
        $html[] =           '<div class="tag-element-chips" id="' . htmlspecialchars($fieldId) . '-chips">';
        foreach ($selectedItemsData as $item) {
            $html[] =         '<span class="tag-chip" data-value="' . htmlspecialchars($item['value']) . '">';
            $html[] =           '<span class="tag-chip-title">' . htmlspecialchars($item['title']) . '</span>';
            $html[] =           '<button type="button" class="tag-chip-remove"'
                . ' aria-label="' . htmlspecialchars($removeAriaLabel) . '"'
                . ' data-value="' . htmlspecialchars($item['value']) . '">&#x2715;</button>';
            $html[] =         '</span>';
        }
        $html[] =           '</div>';

        // Suggest input – TYPO3's form-engine-suggest.js picks up .t3-form-suggest
        $html[] =           '<div class="tag-element-suggest-wrap">';
        $html[] =             '<div class="t3-form-suggest-container">';
        $html[] =               '<input type="search"';
        $html[] =                 ' class="t3-form-suggest"';
        $html[] =                 ' placeholder="' . htmlspecialchars($placeholder) . '"';
        $html[] =                 ' data-fieldname="' . htmlspecialchars($fieldName) . '"';
        $html[] =                 ' data-tablename="' . htmlspecialchars($table) . '"';
        $html[] =                 ' data-field="' . htmlspecialchars($elementName) . '"';
        $html[] =                 ' data-uid="' . htmlspecialchars((string)($row['uid'] ?? 0)) . '"';
        $html[] =                 ' data-pid="' . htmlspecialchars((string)($this->data['parentPageRow']['uid'] ?? 0)) . '"';
        $html[] =                 ' data-fieldtype="group"';
        $html[] =                 ' data-minchars="' . htmlspecialchars((string)$suggestMinimumCharacters) . '"';
        $html[] =                 ' data-datastructureidentifier="' . htmlspecialchars($dataStructureIdentifier) . '"';
        $html[] =                 ' data-flexformsheetname="' . htmlspecialchars($flexFormSheetName) . '"';
        $html[] =                 ' data-flexformfieldname="' . htmlspecialchars($flexFormFieldName) . '"';
        $html[] =                 ' data-flexformcontainername="' . htmlspecialchars($flexFormContainerName) . '"';
        $html[] =                 ' data-flexformcontainerfieldname="' . htmlspecialchars($flexFormContainerFieldName) . '"';
        if ($recordTypeValue !== null && $recordTypeValue !== '') {
            $html[] =             ' data-recordtypevalue="' . htmlspecialchars($recordTypeValue) . '"';
        }
        $html[] =               '/>';
        $html[] =             '</div>';
        $html[] =           '</div>';

        $html[] =         '</div>'; // .tag-element-input-area

        // Hidden select – FormEngine.setSelectOptionFromExternalSource() writes here,
        // our MutationObserver reads it and keeps the chip display in sync.
        $html[] =         '<div style="display:none" aria-hidden="true">';
        $html[] =           '<select';
        $html[] =             ' id="' . htmlspecialchars($fieldId) . '"';
        $html[] =             ' data-formengine-input-name="' . htmlspecialchars($elementName) . '"';
        $html[] =             ' data-maxitems="' . (int)$maxItems . '"';
        $html[] =             ' multiple="multiple"';
        $html[] =           '>';
        $html[] =             implode(LF, $selectorOptionsHtml);
        $html[] =           '</select>';
        // _mul flag: 0 prevents the same tag from being added twice
        $html[] =           '<input type="hidden"'
            . ' data-formengine-input-name="' . htmlspecialchars($elementName) . '"'
            . ' value="0" />';
        $html[] =         '</div>';

        $html[] =       '</div>'; // .tag-element-wrap
        $html[] =     '</div>'; // .form-wizards-element

        if (!empty($fieldWizardHtml)) {
            $html[] =   '<div class="form-wizards-items-bottom">';
            $html[] =     $fieldWizardHtml;
            $html[] =   '</div>';
        }

        $html[] =   '</div>'; // .form-wizards-wrap

        // Submission hidden field
        $html[] =   '<input ' . GeneralUtility::implodeAttributes($hiddenElementAttrs, true) . '>';
        $html[] = '</div>'; // .formengine-field-item

        // Register CSS once per page
        GeneralUtility::makeInstance(PageRenderer::class)
            ->addCssFile('EXT:tagging/Resources/Public/CSS/tag-element.css');

        // Our tag element module replaces group-element.js entirely
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            '@tagging/backend/form-engine/element/tag-element.js'
        )->instance($fieldId, $elementName, $createPid, $signature);

        $resultArray['html'] = implode(LF, $html);
        return $resultArray;
    }
}