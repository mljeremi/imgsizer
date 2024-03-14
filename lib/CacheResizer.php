<?php


namespace Utils\Resize;


use Bitrix\Main\Config\Option;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\File\Image\Rectangle;
use Bitrix\Main\FileTable;
use Bitrix\Main\IO\File;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Utils\Orm\ItemsCountTable;

class CacheResizer implements ResizerInterface
{
    private array $files = [];
    private array $original = [];

    public function __construct($fids = [])
    {
        $this->resizer = new Resizer();
        if (!empty($fids)) {
            $iterator = \Utils\Resize\FileTable::getList([
                'select' => ['file_id', 'hash'],
                'filter' => ['file_id' => $fids]
            ]);

            while ($iteratorItem = $iterator->fetch()) {
                $cached[] = $iteratorItem['file_id'];
                $this->files[$iteratorItem['file_id']] = $iteratorItem;
            }

            $iterator = FileTable::getList([
               'select' => [ 'SUBDIR', 'FILE_NAME' ],
               'filter' => [ 'ID' => $fids ]
            ]);

            while ($iteratorItem = $iterator->fetch()) {
                $this->original[] = $iteratorItem;
            }

        }
    }

    public function resize($fid, $sizes, $prevFid = null): string
    {
        if (empty($fid) || $fid < 0 || Option::get('mega.resizer', 'USE_RESIZER', 'N') == 'N') {
            return false;
        }

        $source = new Rectangle($sizes['width'], $sizes['height']);
        if ($this->checkCache($fid, $source)) {
            return $this->getPlaceholderCachePath($fid, $source);
        } else {
            return $this->getFilePath($prevFid ?? $fid);
        }
    }

    private function getCachePath($fid, Rectangle $source)
    {
        $file = $this->files[$fid];
        $source = ['height' => $source->getHeight(), 'width' => $source->getWidth()];
        if ($file)
            return Resizer::CACHE_DIR . "/${file['file_id']}/${file['hash']}_${source['height']}x${source['width']}." . Resizer::CONVERT_FILE_TYPE;
        else return '';
    }


    private function getPlaceholderCachePath($fid, Rectangle $source) {
        $file = $this->files[$fid];
        $source = ['height' => $source->getHeight(), 'width' => $source->getWidth()];
        if ($file)
            return Resizer::CACHE_DIR_PLACEHOLDER . "/${file['file_id']}/${file['hash']}_${source['height']}x${source['width']}." . Resizer::CONVERT_FILE_TYPE;
        else return '';
    }

    private function getFilePath($fid)
    {
        $file = $this->original[$fid];
        if (!$file) {
            $file = FileTable::getRow([
                'select' => ['SUBDIR', 'FILE_NAME'],
                'filter' => ['ID' => $fid]
            ]);
        }
        if ($file) {
            return "/upload/${file['SUBDIR']}/${file['FILE_NAME']}";
        }

        return '';
    }

    private function checkCache($fid, $source)
    {
        if ($this->files[$fid]) {
            $file = $this->files[$fid];
        } else {
            $file = \Utils\Resize\FileTable::getRow([
                'select' => ['file_id', 'hash'],
                'filter' => ['file_id' => $fid]
            ]);
        }
        if ($file) {
            $this->files[$file['file_id']] = $file;
            $cacheFilePath = $this->getCachePath($fid, $source);
            $cacheFile = new File($_SERVER['DOCUMENT_ROOT'] . $cacheFilePath);
            return $cacheFile->isExists();
        } else return false;
    }

    public static function getSupportedFormats()
    {
        return [
            'ID' => 4,
            'NAME' => 'webp'
        ];
    }

    public static function provide($fields) {
        $fields['runtime'][] = new ReferenceField(
            'FILE',
            FileTable::class,
            [ '=this.DETAIL_PICTURE' => 'ref.ID' ],
            [ 'join_type' => 'LEFT' ]
        );
    }

    public static function runtimeResize($fids, $sizes) {
        if (empty($fids) || Option::get('mega.resizer', 'USE_RESIZER', 'N') == 'N') {
            return false;
        }

        $instance = new self();

        $iterator = \Utils\Resize\FileTable::getList([
            'select' => ['file_id', 'hash'],
            'filter' => ['file_id' => $fids]
        ]);

        while ($iteratorItem = $iterator->fetch()) {
            $instance->files[$iteratorItem['ID']] = $iteratorItem;
        }

        $res = [];
        $source = new Rectangle($sizes['width'], $sizes['height']);
        foreach ($fids as $id => $file) {
            if ($instance->checkCache($id, $source)) {
                $res[$id] = $instance->getPlaceholderCachePath($id, $source);
            } else {
                $res[$id] = null;
            }
        }

        return $res;
    }

    public static function enbaledResizer() {
        return Option::get('mega.resizer', 'USE_RESIZER', 'N') == 'Y';
    }
}