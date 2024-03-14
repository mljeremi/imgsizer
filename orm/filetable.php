<?php
namespace Utils\Resize;

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc,
    Bitrix\Main\ORM\Data\DataManager,
    Bitrix\Main\ORM\Fields\IntegerField,
    Bitrix\Main\ORM\Fields\StringField,
    Bitrix\Main\ORM\Fields\Validators\LengthValidator;

Loc::loadMessages(__FILE__);

/**
 * Class FileTable
 *
 * Fields:
 * <ul>
 * <li> id int mandatory
 * <li> file_id int optional
 * <li> hash string(32) optional
 * <li> db_update int optional
 * <li> field_name string(50) optional
 * <li> property_id int optional
 * <li> element_id int optional
 * <li> iblock_id int optional
 * </ul>
 *
 * @package Bitrix\File
 **/

class FileTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'mgt_file';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return [
            new IntegerField(
                'id',
                [
                    'primary' => true,
                    'autocomplete' => true,
                    'title' => Loc::getMessage('FILE_ENTITY_ID_FIELD')
                ]
            ),
            new IntegerField(
                'file_id',
                [
                    'title' => Loc::getMessage('FILE_ENTITY_FILE_ID_FIELD')
                ]
            ),
            new StringField(
                'hash',
                [
                    'validation' => [__CLASS__, 'validateHash'],
                    'title' => Loc::getMessage('FILE_ENTITY_HASH_FIELD')
                ]
            ),
            new IntegerField(
                'db_update',
                [
                    'title' => Loc::getMessage('FILE_ENTITY_DB_UPDATE_FIELD')
                ]
            ),
            new StringField(
                'field_name',
                [
                    'validation' => [__CLASS__, 'validateFieldName'],
                    'title' => Loc::getMessage('FILE_ENTITY_FIELD_NAME_FIELD')
                ]
            ),
            new IntegerField(
                'property_id',
                [
                    'title' => Loc::getMessage('FILE_ENTITY_PROPERTY_ID_FIELD')
                ]
            ),
            new IntegerField(
                'element_id',
                [
                    'title' => Loc::getMessage('FILE_ENTITY_ELEMENT_ID_FIELD')
                ]
            ),
            new IntegerField(
                'iblock_id',
                [
                    'title' => Loc::getMessage('FILE_ENTITY_IBLOCK_ID_FIELD')
                ]
            ),
        ];
    }

    /**
     * Returns validators for hash field.
     *
     * @return array
     */
    public static function validateHash()
    {
        return [
            new LengthValidator(null, 32),
        ];
    }

    /**
     * Returns validators for field_name field.
     *
     * @return array
     */
    public static function validateFieldName()
    {
        return [
            new LengthValidator(null, 50),
        ];
    }

    public static function clear() {
        $connection = Application::getConnection();
        $connection->truncateTable(self::getTableName());
    }
}