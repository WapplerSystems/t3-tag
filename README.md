# A patch for TYPO3 to easily add tags just like categories to any element.

The extension automatically adds the tag field to the following elements:
- tt_content
- fe_users

There are two ways to add the tag field to your own element:

By override file:
```
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('<table>', [
    'tags' => [
        'config' => [
            'type' => 'tag',
        ],
    ],
]);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('<table>', 'tags', '', 'after:categories');
```


By TCA configuration:
```
'tags' => [
    'config' => [
        'type' => 'tag',
    ],
],
```

Please don't forget to add the field in your sql file:

`tags int(11) DEFAULT '0' NOT NULL`
