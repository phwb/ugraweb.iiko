<?
namespace Iiko\DB;

use Bitrix\Main\Entity;

class ModifiersTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'uw_iiko_modifiers';
    }

    public static function getMap()
    {
        return array(
            'ID' => new Entity\IntegerField('ID', array(
                'primary'      => true,
                'autocomplete' => true
            )),
            'NAME' => new Entity\StringField('NAME', array(
                'required'   => true,
                'validation' => array(__CLASS__, 'validateName'),
                'title'      => 'Название'
            )),
            'PRICE' => new Entity\FloatField('PRICE', array(
                'default_value' => 0,
                'title'         => 'Цена'
            )),
            'WEIGHT' => new Entity\FloatField('WEIGHT', array(
                'default_value' => 0,
                'title'         => 'Вес'
            )),
            'HIDDEN' => new Entity\BooleanField('HIDDEN', array(
                'values'        => array('N', 'Y'),
                'default_value' => 'N',
                'title'         => 'Скрытый модификатор'
            )),
            'ELEMENT_ID' => new Entity\IntegerField('ELEMENT_ID', array(
                'required'   => true,
                'title'      => 'Элемент модификатора',
                'validation' => array(__CLASS__, 'validateElement')
            )),
            'ELEMENT' => new Entity\ReferenceField(
                'ELEMENT',
                '\Bitrix\Iblock\ElementTable',
                array('=this.ELEMENT_ID' => 'ref.ID'),
                array('join_type' => 'LEFT')
            )
        );
    }

    public static function validateElement()
    {
        return array(
            new Entity\Validator\Unique
        );
    }

    public static function validateName()
    {
        return array(
            new Entity\Validator\Length(null, 255)
        );
    }
}

class ElementModifiersTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'uw_iiko_element_modifiers';
    }

    public static function getMap()
    {
        return array(
            'ID' => new Entity\IntegerField('ID', array(
                'primary'      => true,
                'autocomplete' => true
            )),
            'ELEMENT_ID' => new Entity\IntegerField('ELEMENT_ID', array(
                'required'   => true,
                'title'      => 'Элемент каталога'
            )),
            'MODIFIER_ID' => new Entity\IntegerField('MODIFIER_ID', array(
                'required'   => true,
                'title'      => 'Элемент модификатора'
            )),
            'REQUIRED' => new Entity\BooleanField('REQUIRED', array(
                'values'        => array('N', 'Y'),
                'default_value' => 'N',
                'title'         => 'Обязательный'
            ))
        );
    }
}