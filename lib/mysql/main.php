<?
namespace Iiko\DB;

use Bitrix\Main\Entity;

class HashTable extends Entity\DataManager
{
    static function getTableName()
    {
        return 'uw_iiko_hash';
    }

    static function getMap()
    {
        return array(
            'ID' => new Entity\IntegerField('ID', array(
                'primary'      => true,
                'autocomplete' => true
            )),
            'IIKO_ID' => new Entity\StringField('IIKO_ID', array(
                'required'   => true,
                'validation' => array(__CLASS__, 'validateElement'),
                'title'      => 'Идентификатор из IIKO'
            )),
            'HASH' => new Entity\StringField('HASH', array(
                'required'   => true,
                'title'      => 'Хэш'
            ))
        );
    }

    static function validateElement()
    {
        return array(
            new Entity\Validator\Unique
        );
    }
}