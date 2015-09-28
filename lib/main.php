<?
namespace Iiko;

use Bitrix\Iblock\ElementTable;
use Iiko\Config\Option;
use Iiko\DB\HashTable;

class App
{
    static function compareHash($item)
    {
        $hash = md5(serialize($item));
        $params = array(
            'select' => array('ID', 'HASH'),
            'filter' => array(
                '=IIKO_ID' => $item['XML_ID']
            )
        );
        $arHash = HashTable::getRow($params);
        if ($arHash['HASH'] === $hash)
        {
            return true;
        }
        if ($arHash['ID'] > 0)
        {
            HashTable::update($arHash['ID'], array('HASH' => $hash));
            return false;
        }
        HashTable::add(array(
            'IIKO_ID' => $item['XML_ID'],
            'HASH'    => $hash
        ));
        return false;
    }
}

class Event
{
    function onIBlockElementDelete($id)
    {
        $modifier_id = intval(Option::get('modifier_id'));
        $params = array(
            'select' => array('IBLOCK_ID'),
            'filter' => array(
                '=ID' => $id
            )
        );
        $arElement = ElementTable::getRow($params);
        if (intval($arElement['IBLOCK_ID']) !== $modifier_id)
        {
            return ElementModifiers::delete($id);
        }
        return Modifiers::delete($id);
    }
}