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
 * Class ResizeTable
 *
 * Fields:
 * <ul>
 * <li> id int mandatory
 * <li> file_id int optional
 * <li> element_id int optional
 * <li> property_id int optional
 * <li> field_name string(20) optional
 * <li> from_height int optional
 * <li> from_width int optional
 * <li> to_height int optional
 * <li> to_width int optional
 * <li> format string(5) optional
 * <li> path string(255) optional
 * <li> iblock int optional
 * </ul>
 *
 * @package Bitrix\Resize
 **/

class ResizeTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'mgt_resize';
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
                    'title' => Loc::getMessage('RESIZE_ENTITY_ID_FIELD')
                ]
            ),
            new IntegerField(
                'file_id',
                [
                    'title' => Loc::getMessage('RESIZE_ENTITY_FILE_ID_FIELD')
                ]
            ),
            new IntegerField(
                'element_id',
                [
                    'title' => Loc::getMessage('RESIZE_ENTITY_ELEMENT_ID_FIELD')
                ]
            ),
            new IntegerField(
                'property_id',
                [
                    'title' => Loc::getMessage('RESIZE_ENTITY_PROPERTY_ID_FIELD')
                ]
            ),
            new StringField(
                'field_name',
                [
                    'validation' => [__CLASS__, 'validateFieldName'],
                    'title' => Loc::getMessage('RESIZE_ENTITY_FIELD_NAME_FIELD')
                ]
            ),
            new IntegerField(
                'from_height',
                [
                    'title' => Loc::getMessage('RESIZE_ENTITY_FROM_HEIGHT_FIELD')
                ]
            ),
            new IntegerField(
                'from_width',
                [
                    'title' => Loc::getMessage('RESIZE_ENTITY_FROM_WIDTH_FIELD')
                ]
            ),
            new IntegerField(
                'to_height',
                [
                    'title' => Loc::getMessage('RESIZE_ENTITY_TO_HEIGHT_FIELD')
                ]
            ),
            new IntegerField(
                'to_width',
                [
                    'title' => Loc::getMessage('RESIZE_ENTITY_TO_WIDTH_FIELD')
                ]
            ),
            new StringField(
                'format',
                [
                    'validation' => [__CLASS__, 'validateFormat'],
                    'title' => Loc::getMessage('RESIZE_ENTITY_FORMAT_FIELD')
                ]
            ),
            new StringField(
                'path',
                [
                    'validation' => [__CLASS__, 'validatePath'],
                    'title' => Loc::getMessage('RESIZE_ENTITY_PATH_FIELD')
                ]
            ),
            new IntegerField(
                'iblock',
                [
                    'title' => Loc::getMessage('RESIZE_ENTITY_IBLOCK_FIELD')
                ]
            ),
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
            new LengthValidator(null, 20),
        ];
    }

    /**
     * Returns validators for format field.
     *
     * @return array
     */
    public static function validateFormat()
    {
        return [
            new LengthValidator(null, 5),
        ];
    }

    /**
     * Returns validators for path field.
     *
     * @return array
     */
    public static function validatePath()
    {
        return [
            new LengthValidator(null, 255),
        ];
    }

    public static function clear() {
        $connection = Application::getConnection();
        $connection->truncateTable(self::getTableName());
    }
}