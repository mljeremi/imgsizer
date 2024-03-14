<?php

use Bitrix\Main\ModuleManager;

if (!ModuleManager::isModuleInstalled('utils.resizer'))
    ModuleManager::registerModule('utils.resizer');

CModule::AddAutoloadClasses(
    'utils.resizer',
    [
        '\Utils\Resize\ImageChanger' => 'lib/ImageChanger.php',
        '\Utils\Resize\ImageChangerHandle' => 'lib/ImageChangerHandle.php',
        '\Utils\Resize\GdImageChanger' => 'lib/GdImageChanger.php',
        '\Utils\Resize\GdChangerHandle' => 'lib/GdChangerHandle.php',
        '\Utils\Resize\ThumborImageChanger' => 'lib/ThumborImageChanger.php',
        '\Utils\Resize\ThumborChangerHandle' => 'lib/ThumborChangerHandle.php',
        '\Utils\Resize\ResizeTable' => 'orm/resizetable.php',
        '\Utils\Resize\FileTable' => 'orm/filetable.php',
        '\Utils\Resize\Resizer' => 'lib/Resizer.php',
        '\Utils\Resize\ResizerInterface' => 'lib/ResizerInterface.php',
        '\Utils\Resize\CacheResizer' => 'lib/CacheResizer.php'
    ]
);