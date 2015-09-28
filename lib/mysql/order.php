<?
namespace Iiko\DB;

use Bitrix\Main\Loader;

Loader::includeModule('sale');

class OrderTable extends \Bitrix\Sale\OrderTable
{
    static function getMap()
    {
        $parent = parent::getMap();
        $child = array(
            'UPDATED_1C' => array(
                'data_type' => 'boolean',
                'values' => array('N', 'Y')
            ),
            'COMMENTS' => array(
                'data_type' => 'string'
            )
        );
        return array_merge($parent, $child);
    }
}