<?php

namespace Utils\Resize;

use Bitrix\Iblock\ElementPropertyTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\File;
use Bitrix\Main\File\Image\Rectangle;
use Bitrix\Main\File\Internal\FileHashTable;
use Bitrix\Main\FileTable;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File as IOFile;
use Bitrix\Main\Type\Dictionary;
use Bitrix\Main\Web\Json;
use Utils\Traits\Logger;

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interfaces/traits/LoggerTrait.php';

class Resizer
{

    const PROPERTY_ID = 1;
    const CACHE_DIR_PLACEHOLDER = '/resize';
    const CACHE_DIR = '/../.cache/resizer';

    const CONVERT_FILE_TYPE = 'webp';
    const CONFIG_PATH = __DIR__ . '/../config/config.json';

    protected ImageChanger $changer;
    protected $changerType = 'imagick';
    private Dictionary $config;
    private ErrorCollection $errors;

    public function __construct($changer = 'imagick')
    {
        if ($changer == 'thumbor') {
            $this->changer = new ThumborImageChanger();
        } else {
            $this->changer = new GdImageChanger();
        }

        $this->errors = new ErrorCollection();
        $this->config = new Dictionary();

        $configFile = new IOFile(self::CONFIG_PATH);
        if ($configFile->isExists()) {
            try {
                $this->config->setValues(
                    Json::decode($configFile->getContents())
                );
            } catch (\Exception $e) {
                $this->processErorr(new Error('Config file cannot be loaded', 100));
            }
        }

        $this->changerType = $changer;
    }

    protected function addFileToResize($fid, $eid, $iid, $points, $file)
    {
        $file = FileTable::getRowById($fid);
        return ResizeTable::add([
            'file_id' => $fid,
            'element_id' => $eid,
            'property_id' => self::PROPERTY_ID,
            'field_name' => '',
            'from_height' => $file['HEIGHT'],
            'from_width' => $file['WIDTH'],
            'to_height' => $points[0],
            'to_width' => $points[1],
            'path' => '/upload/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'],
            'format' => self::CONVERT_FILE_TYPE,
            'iblock' => $iid
        ]);
    }

    protected function checkCache($fid, $hash, $eid, $iid, $sizes)
    {
        if (!$fid) {
            return false;
        }

        $checkComplete = true;
        $elementFilePath = self::getCacheFolder($fid);
        $dir = new Directory($elementFilePath);
        if (!$dir->isExists()) {
            foreach ($sizes as $points) {
                $this->addFileToResize($fid, $eid, $iid, $points);
            }
            return false;
        }

        $subfiles = $dir->getChildren();
        $subfiles = array_map(fn($file) => $file->getName(), $subfiles);

        foreach ($sizes as $points) {
            if (!in_array($this->getCacheFileName($hash, $points, self::CONVERT_FILE_TYPE), $subfiles)) {
                $this->addFileToResize($fid, $eid, $iid, $points);
                $checkComplete = false;
            }
        }

        return $checkComplete;
    }

    protected function getCacheFileName($hash, $points, $format)
    {
        return "${hash}_${points[0]}x${points[1]}.${format}";
    }

    public static function checkFileCache($fid, $points)
    {
        return false;
    }

    public static function onBeforeResizeHandler()
    {

    }

    public static function getCacheFolder($fid) {
        $cacheDir = self::CACHE_DIR;
        return "${_SERVER['DOCUMENT_ROOT']}${cacheDir}/${fid}";
    }

    private function getHashedIds() {
        $iterator = \Utils\Resize\FileTable::getList([
            'select' => [ 'file_id' ]
        ]);

        $hashedFiles = [];
        while($iteratorItem = $iterator->fetch()) {
            $hashedFiles[] = $iteratorItem['file_id'];
        }

        $this->hashedFiles = $hashedFiles;
    }

    private function analyzeFile($fid, $eid, $params) {
        $path = \CFile::GetPath($fid);
        $file = new IOFile($_SERVER['DOCUMENT_ROOT'] . $path);

        $sizes = $this->config->get('iblocks')[$params['iblock_id']];
        if ($file->isExists()) {
            $hash = md5_file($_SERVER['DOCUMENT_ROOT'] . $path);
            if ($fid && !in_array($fid, $this->hashedFiles) && $hash) {
                $fields = [
                    'file_id' => $fid,
                    'hash' => $hash,
                    'db_update' => true,
                    'element_id' => $eid
                ];

                \Utils\Resize\FileTable::add(
                    array_merge($fields, $params)
                );

                foreach ($sizes as $points) {
                    $this->addFileToResize($fid, $eid, $params['iblock_id'], $points);
                }
            } else {
                $this->checkCache($fid, $hash, $eid, $params['iblock_id'], $sizes);
            }
        }
    }

    protected function analyzeFiles($files) {
        $iterator = FileTable::getList([
            'select' => ['ID', 'WIDTH', 'HEIGHT', 'SUBDIR', 'FILE_NAME'],
            'filter' => [ 'ID' => array_keys($files) ]
        ]);

        while($iteratorItem = $iterator->fetch()) {
            $fid = $iteratorItem['ID'];
            $files[$fid] = array_merge($files[$fid], $iteratorItem);
        }

        $fileRows = $resizeRows = [];

        foreach ($files as $fid => $file) {
            $path = $this->getFilePath($file);
            if ((new IOFile($_SERVER['DOCUMENT_ROOT'] . $path))->isExists()) {
                $hash = md5_file($_SERVER['DOCUMENT_ROOT'] . $path);
                $fileRowParams = [
                    'file_id' => $fid,
                    'element_id' => $file['element_id'],
                    'property_id' => $file['property_id'],
                    'field_name' => $file['field'],
                    'from_height' => $file['HEIGHT'],
                    'from_width' => $file['WIDTH'],
                    'path' => $path,
                    'format' => self::CONVERT_FILE_TYPE,
                    'iblock' => $file['iblock_id']
                ];


                if (!in_array($fid, $this->hashedFiles) && $hash) {
                    $fields = [
                        'file_id' => $fid,
                        'hash' => $hash,
                        'db_update' => false,
                        'field_name' => $file['field'],
                        'property_id' => $file['property_id'],
                        'element_id' => $file['element_id'],
                        'iblock_id' => $file['iblock_id']
                    ];

                    $fileRows[] = $fields;

                    $sizes = $this->config->get('iblocks')[$file['iblock_id']]['sizes'];
                    foreach ($sizes as $points) {
                        $resizeRows[] = array_merge($fileRowParams, [
                            'to_height' => $points[0],
                            'to_width' => $points[1]
                        ]);
                    }
                } else {
                    $cacheFilePath = self::getCacheFolder($fid);
                    $dir = new Directory($cacheFilePath);
                    $sizes = $this->config->get('iblocks')[$file['iblock_id']]['sizes'];
                    if (!$dir->isExists()) {

                        foreach ($sizes as $points)
                            $resizeRows[] = array_merge($fileRowParams, [
                                'to_height' => $points[0],
                                'to_width' => $points[1]
                            ]);
                        return false;
                    }

                    $subfiles = $dir->getChildren();
                    $subfiles = array_map(fn($file) => $file->getName(), $subfiles);

                    foreach ($sizes as $points) {
                        if (!in_array($this->getCacheFileName($hash, $points, self::CONVERT_FILE_TYPE), $subfiles)) {
                            $resizeRows[] = array_merge($fileRowParams, [
                                'to_height' => $points[0],
                                'to_width' => $points[1]
                            ]);
                        }
                    }

                }
            }
        }


        if (!empty($fileRows))
            \Utils\Resize\FileTable::addMulti($fileRows);
        if (!empty($resizeRows))
            ResizeTable::addMulti($resizeRows);

    }

    public static function analyze() {
        if (Option::get('mega.resizer', 'DELETE_CACHE', 'N') == 'Y') {
            ResizeTable::clear();
            \Utils\Resize\FileTable::clear();

            $dir = new Directory($_SERVER['DOCUMENT_ROOT'] . self::CACHE_DIR);
            $dir->delete();

            Option::set('mega.resizer', 'DELETE_CACHE', 'N');
        }

        $instance = new self();
        foreach ($instance->config->get('iblocks') as $iblock) {
            if ($iblock['update_cache']) {
                $iterator = \Utils\Resize\FileTable::getList([
                   'select' => [ 'id' ],
                   'filter' => ['iblock_id' => $iblock['iblock_id']]
                ]);

                while($iteratorItem = $iterator->fetch())
                    \Utils\Resize\FileTable::delete($iteratorItem['id']);
            }
            self::analyzeIBlock($iblock);
        }

        return "\Utils\Resize\Resizer::analyze()";
    }

    /**
     * Функция для агента, анализ инфоблока
     * @param int $id - id инфоблока
     * @return string
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function analyzeIBlock($iblock)
    {
        $id = $iblock['iblock_id'];

        $instance = new self();
        $instance->getHashedIds();

        $counter = 0;
        do {
            $iterator = ElementTable::getList([
                'select' => array_merge($iblock['fields'], ['ID']),
                'filter' => ['IBLOCK_ID' => $id],
                'offset' => $counter,
                'limit' => 10240,
                'count_total' => true
            ]);

            $elements = [];
            $files = [];
            while ($iteratorItem = $iterator->fetch()) {
                $elements[] = $iteratorItem['ID'];
                foreach($iblock['fields'] as $field) {
                    if ($fid = $iteratorItem[$field]) {
                        $files[$fid] = [
                            'field' => $field,
                            'iblock_id' => $iblock['iblock_id'],
                            'element_id' => $iteratorItem['ID']
                        ];
                    }
                }

                $counter++;
            }

            $propertyIterator = ElementPropertyTable::getList(array(
                'select' => array(
                    'EID' => 'IBLOCK_ELEMENT_ID', 'PID' => 'IBLOCK_PROPERTY_ID', 'VALUE', 'DESCRIPTION'
                ),
                'filter' => array(
                    'IBLOCK_PROPERTY_ID' => $iblock['properties'],
                    'IBLOCK_ELEMENT_ID' => $elements
                )
            ));

            while ($propertyItem = $propertyIterator->fetch()) {
                if ($fid = $propertyItem['VALUE']) {
                    $files[$fid] = [
                        'property_id' => $propertyItem['PID'],
                        'iblock_id' => $iblock['iblock_id'],
                        'element_id' => $propertyItem['EID']
                    ];
                }
            }

            $instance->analyzeFiles($files);

        } while ($counter < $iterator->getCount());
    }

    /**
     * Функция для агента которая делает ресайз из таблицы
     * @return string
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function resizeFiles() : string
    {
        $skipids = [];
        $instance = new self();

        do {
            $iterator = ResizeTable::getList([
               'filter' => ['!=id' => $skipids],
               'offset' => 0,
               'limit' => 1024,
               'count_total' => true
            ]);

            $files = $fids = [];
            while($iteratorItem = $iterator->fetch()) {
                $fids[$iteratorItem['file_id']] = null;
                $files[$iteratorItem['id']] = $iteratorItem;
            }

            $fids = array_keys($fids);

            $hashIterator = \Utils\Resize\FileTable::getList([
               'filter' => [
                   'file_id' => $fids
               ]
            ]);

            $hashFiles = [];
            while($hashItem = $hashIterator->fetch())
                $hashFiles[$hashItem['file_id']] = $hashItem;

            foreach ($files as $file) {
                $hash = $hashFiles[$file['file_id']]['hash'];
                $source = new Rectangle($file['from_width'], $file['from_height']);
                $destination = new Rectangle($file['to_width'], $file['to_height']);
                $source->resize($destination, BX_RESIZE_IMAGE_PROPORTIONAL_ALT);

                $originalPath = $file['path'];
                $cacheFilePath = self::getCacheFolder($file['file_id']) . '/'
                    . $instance->getCacheFileName($hash, [$file['to_height'], $file['to_width']], self::CONVERT_FILE_TYPE);
                $cacheFileDirectory = new Directory(self::getCacheFolder($file['file_id']));

                if (!$cacheFileDirectory->isExists())
                    $cacheFileDirectory->create();

                $settings = $instance->config->get('iblocks')[$file['iblock']];

                $instance->changer->resize($_SERVER['DOCUMENT_ROOT'] . $originalPath, $source, $destination);
                $instance->changer->save($cacheFilePath, $settings['quantity'], File\Image::FORMAT_WEBP);

                if ($instance->changer->getError()) {
                    $skipids[] = $file['id'];
                }

                if (file_exists($cacheFilePath)) {
                    ResizeTable::delete($file['id']);
                }
            }

        } while (!empty($files));

        return '\Utils\Resize\Resizer::resizeFiles();';
    }

    private function updateOriginalFile(&$r_file, $h_file) {
        $source = new Rectangle($r_file['from_width'], $r_file['from_height']);
        $destination = new Rectangle($this->config->get('original_sizes')[1], $this->config->get('original_sizes')[0]);

        $source->resize($destination, BX_RESIZE_IMAGE_PROPORTIONAL_ALT);

        $path = $_SERVER['DOCUMENT_ROOT'] . $r_file['path'];
        $this->changer->resize($path, $source, $destination);
        $tmpFilePath = '/tmp/' . uniqid('_oopris') . '.' . self::CONVERT_FILE_TYPE;
        $this->changer->save($tmpFilePath,85,File\Image::FORMAT_WEBP);
        $file = new IOFile($tmpFilePath);
        if ($file->isExists()) {
            $fid = null;
            if (!empty($h_file['field_name'])) {
                (new \CIBlockElement())->Update($h_file['element_id'], [ $h_file['field_name'] => \CFile::MakeFileArray($tmpFilePath) ]);
                $fid = ElementTable::getRowById($h_file['element_id'])['DETAIL_PICTURE'];
            } elseif (!empty($h_file['property_id'])) {
                $params = \CFile::MakeFileArray($tmpFilePath);
                $fid = \CFile::SaveFile($params, 'iblock');
                $updatedRow = ElementPropertyTable::getRow([
                    'select' => [ 'ID' ],
                    'filter' => [
                        'VALUE' => $h_file['file_id'],
                        'IBLOCK_ELEMENT_ID' => $h_file['element_id'],
                        'IBLOCK_PROPERTY_ID' => $h_file['property_id']
                    ]
                ]);

                if ($updatedRow)
                    ElementPropertyTable::update($updatedRow['ID'], [ 'VALUE' => $fid, 'VALUE_NUM' => $fid ]);
            }

            $r_file['file_id'] = $fid;
            $r_file['path'] = \CFile::GetPath($fid);
            $r_file['from_height'] = $destination->getHeight();
            $r_file['from_width'] = $destination->getWidth();

            \Utils\Resize\FileTable::update($h_file['id'], [
                'file_id' => $fid,
                'db_update' => false
            ]);
            $iterator = ResizeTable::getList([
                'select' => ['id'],
                'filter' => ['file_id' => $h_file['file_id']]
            ]);

            while ($iteratorItem = $iterator->fetch())
                ResizeTable::update($iteratorItem['id'], [
                    'file_id' => $fid,
                    'from_height' => $destination->getHeight(),
                    'from_width' => $destination->getWidth(),
                    'path' => $r_file['path']
                ]);

            $cacheDir = new Directory(self::getCacheFolder($h_file['file_id']));
            $cacheDir->delete();

            \CFile::Delete($h_file['file_id']);
            $file->delete();
        }
    }

    private function getFilePath($file) {
        if ($file)
            return "/upload/${file['SUBDIR']}/${file['FILE_NAME']}";
        else return '';
    }

    private static function deleteFromResizeTable($iblock = 2) {
        $iterator = ResizeTable::getList([
            'select' => [ 'id' ],
            'filter' => [ 'iblock' => $iblock ]
        ]);

        while($iteratorItem = $iterator->fetch())
            ResizeTable::delete($iteratorItem['id']);
    }

    private static function fillHashTable() {
        $hashIterator = FileHashTable::getList([
            'select' => ['FILE_ID']
        ]);

        $hashFiles = [];
        while ($hashItem = $hashIterator->fetch())
            $hashFiles[] = $hashItem['FILE_ID'];

        $fileIterator = FileTable::getList([
            'select' => ['ID', 'FILE_SIZE', 'SUBDIR', 'FILE_NAME']
        ]);
        $files = [];
        while ($file = $fileIterator->fetch())
            $files[$file['ID']] = $file;

        $notHashed = array_diff(array_keys($files), $hashFiles);
        foreach ($notHashed as $fid) {
            $file = $files[$fid];
            $path = "${_SERVER['DOCUMENT_ROOT']}/upload/${$file['SUBDIR']}/${$file['FILE_NAME']}";
            $hash = md5_file($path);
            if ($hash)
                FileHashTable::add([
                    'FILE_ID' => $fid,
                    'FILE_SIZE' => $file['FILE_SIZE'],
                    'FILE_HASH' => $hash
                ]);
        }
    }

    public function resize()
    {
        // TODO: Implement resize() method.
    }

    /**
     * @param Error $e
     */
    private function processErorr(Error $e)
    {
        $logger = new Logger($_SERVER['DOCUMENT_ROOT'] . $this->config->get('log_file'));
        $logger->log( $e->getMessage());
    }

}