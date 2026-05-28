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

## Storage folder for newly created tags

When the editor types a name that does not yet exist and confirms with
Enter, the tag element creates a new `sys_tag` record. The page UID
used as `pid` for that new record is resolved in this order:

1. **TCA config** — `createNewTagPid` on the field's `config`:
   ```php
   'tags' => [
       'config' => [
           'type' => 'tag',
           'createNewTagPid' => 42,
       ],
   ],
   ```

2. **Page TSconfig** — `TCEFORM.<table>.<field>.createNewTagPid`,
   inherited along the page tree like any other `TCEFORM` setting:
   ```typoscript
   # All tag fields on tt_content land in sysfolder #42
   TCEFORM.tt_content.tags.createNewTagPid = 42

   # fe_users follows its own folder
   TCEFORM.fe_users.tags.createNewTagPid = 99
   ```

   Use this when the storage folder should differ per branch of the
   page tree, or when the TCA shouldn't be touched (e.g. multi-site
   setups).

If neither is set, the new tag is stored at `pid = 0` — usually not
what you want, so configure one of the two above.
