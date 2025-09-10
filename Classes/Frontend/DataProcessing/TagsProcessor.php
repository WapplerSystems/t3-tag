<?php

namespace TYPO3\CMS\Frontend\DataProcessing;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;


class TagsProcessor implements DataProcessorInterface
{
    /**
     *
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array the processed data as key/value store
     */
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData)
    {
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }

        if (
            (isset($processorConfiguration['references']) && $processorConfiguration['references'])
            || (isset($processorConfiguration['references.']) && $processorConfiguration['references.'])
        ) {


            if (!empty($processorConfiguration['references.'])) {
                $referenceConfiguration = $processorConfiguration['references.'];
                $relationField = $cObj->stdWrapValue('fieldName', $referenceConfiguration);

            }
        }

        $targetVariableName = $cObj->stdWrapValue('as', $processorConfiguration, 'tags');
        $processedData[$targetVariableName] = [];

        return $processedData;

    }

}
