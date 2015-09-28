<?
namespace Iiko;

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Entity\ReferenceField;
use Iiko\DB\ModifiersTable;
use Iiko\DB\ElementModifiersTable;

/**
 * Служебный класс для работы с модификаторами
 *
 * Class Modifiers
 * @package Iiko
 */
class Modifiers
{
    /**
     * @param array $arParams
     * @return \Bitrix\Main\DB\Result
     * @throws \Bitrix\Main\ArgumentException
     */
    function getList($arParams = array())
    {
        $params = array(
            'select' => array('ID', 'NAME', 'IS_HIDDEN' => 'HIDDEN')
        );
        foreach ($arParams as $keyParam => $arOneParam)
        {
            $params[$keyParam] = isset($params[$keyParam])
                ? array_merge($params[$keyParam], $arOneParam)
                : $params[$keyParam] = $arOneParam;
        }
        return ModifiersTable::getList($params);
    }

    /**
     * @param int $elemID
     * @param array $params
     * @return \Bitrix\Main\Entity\AddResult|\Bitrix\Main\Entity\UpdateResult
     * @throws IIKOException
     * @throws \Exception
     */
    function add($elemID = 0, $params = array())
    {
        if (!$elemID)
        {
            throw new IIKOException('Require element ID');
        }
        $row = ModifiersTable::getRow(array('filter' => array('=ELEMENT_ID' => $elemID)));
        if ($row['ID'] > 0)
        {
            $result = ModifiersTable::update($row['ID'], array(
                'NAME'   => $params['NAME'],
                'PRICE'  => $params['PRICE'],
                'WEIGHT' => $params['WEIGHT']
            ));
        }
        else
        {
            $result = ModifiersTable::add(array(
                'NAME'       => $params['NAME'],
                'PRICE'      => $params['PRICE'],
                'WEIGHT'     => $params['WEIGHT'],
                'ELEMENT_ID' => $elemID
            ));
        }
        return $result;
    }

    /**
     * @param array $arElements
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Exception
     */
    function updateHidden($arElements = array())
    {
        // удалим старые скрытые модификаторы
        $params = array(
            'select' => array('ID'),
            'filter' => array(
                '!=ID' => $arElements,
                '=HIDDEN'      => 'Y'
            )
        );
        $arHidden = ModifiersTable::getList($params)->fetchAll();
        foreach ($arHidden as $arOneHidden)
        {
            ModifiersTable::update($arOneHidden['ID'], array('HIDDEN' => 'N'));
        }
        // установим новые скрытые модификаторы
        $params = array(
            'select' => array('ID'),
            'filter' => array(
                '=ID' => $arElements,
                '=HIDDEN'     => 'N'
            )
        );
        $arHidden = ModifiersTable::getList($params)->fetchAll();
        foreach ($arHidden as $arOneHidden)
        {
            ModifiersTable::update($arOneHidden['ID'], array('HIDDEN' => 'Y'));
        }
        return true;
    }

    /**
     * @param $elemID
     * @return bool
     * @throws \Exception
     */
    function delete($elemID)
    {
        $params = array(
            'select' => array('ID'),
            'filter' => array(
                '=ELEMENT_ID' => $elemID
            )
        );
        $row = ModifiersTable::getRow($params);
        if ($row['ID'] > 0)
        {
            return ModifiersTable::delete($row['ID'])->isSuccess();
        }
        return true;
    }
}

/**
 * Класс для работы с модификаторами элемента, публичная часть
 *
 * Class ElementModifiers
 * @package Iiko
 */
class ElementModifiers
{
    const PREFIX = 'MODIFIER_';

    static function getByPropCode($code)
    {
        if (strpos($code, self::PREFIX) !== false)
        {
            $id = str_replace(self::PREFIX, '', $code);
            $params = array(
                'select' => array(
                    'ELEMENT_XML_ID' => 'XML_ID',
                    'ELEMENT_NAME'   => 'NAME',
                    'SECTION_NAME'   => 'SECTION.NAME',
                    'SECTION_XML_ID' => 'SECTION.XML_ID'
                ),
                'filter' => array(
                    '=ID' => $id
                ),
                'runtime' => array(
                    new ReferenceField(
                        'SECTION',
                        '\Bitrix\Iblock\SectionTable',
                        array('=this.IBLOCK_SECTION_ID' => 'ref.ID'),
                        array('join_type' => 'LEFT')
                    )
                )
            );
            return ElementTable::getRow($params);
        }
        return '';
    }

    /**
     * Возвращает массив состоящий ID модификаторов по ID элемента
     *
     * @param $element_id
     * @param array $arParams
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     */
    static function getByElementID($element_id, $arParams = array())
    {
        $params = array(
            'select' => array(
                'ID'           => 'ID',
                'IS_REQUIRED'  => 'REQUIRED',
                'IS_HIDDEN'    => 'MODIFIER.HIDDEN',
                'NAME'         => 'MODIFIER.ELEMENT.NAME',
                'MODIFIER_ID'  => 'MODIFIER_ID',
                'SECTION_ID'   => 'MODIFIER.ELEMENT.IBLOCK_SECTION_ID',
                'SECTION_NAME' => 'SECTION.NAME'
            ),
            'filter' => array(
                '=ELEMENT_ID' => $element_id
            ),
            'runtime' => array(
                new ReferenceField(
                    'MODIFIER',
                    '\Iiko\DB\ModifiersTable',
                    array('=this.MODIFIER_ID' => 'ref.ELEMENT_ID'),
                    array('join_type' => 'LEFT')
                ),
                new ReferenceField(
                    'SECTION',
                    '\Bitrix\Iblock\SectionTable',
                    array('=this.MODIFIER.ELEMENT.IBLOCK_SECTION_ID' => 'ref.ID'),
                    array('join_type' => 'LEFT')
                )
            )
        );
        foreach ($arParams as $keyParam => $arOneParam)
        {
            $params[$keyParam] = isset($params[$keyParam])
                ? array_merge($params[$keyParam], $arOneParam)
                : $params[$keyParam] = $arOneParam;
        }
        return ElementModifiersTable::getList($params)->fetchAll();
    }

    /**
     * Вспомогательная функция получения массива свойств, которые попадут в корзину
     *
     * @param $elemID
     * @param $arModifiers
     * @return array
     */
    static function checkElementModifiers($elemID, $arModifiers)
    {
        $arResult = array();
        $sort = 1;
        foreach ($arModifiers as $itemID)
        {
            $params = array(
                'select' => array(
                    'ID'           => 'ID',
                    'MODIFIER_ID'  => 'MODIFIER_ID',
                    'NAME'         => 'MODIFIER.ELEMENT.NAME',
                    'SECTION_NAME' => 'SECTION.NAME'
                ),
                'filter' => array(
                    '=ELEMENT_ID' => $elemID
                ),
                'runtime' => array(
                    new ReferenceField(
                        'MODIFIER',
                        '\Iiko\DB\ModifiersTable',
                        array('=this.MODIFIER_ID' => 'ref.ELEMENT_ID'),
                        array('join_type' => 'LEFT')
                    ),
                    new ReferenceField(
                        'SECTION',
                        '\Bitrix\Iblock\SectionTable',
                        array('=this.MODIFIER.ELEMENT.IBLOCK_SECTION_ID' => 'ref.ID'),
                        array('join_type' => 'LEFT')
                    )
                )
            );
            if (!$arMod = self::getByID($itemID, $params))
            {
                continue;
            }
            $arResult[] = array(
                'NAME'  => $arMod['SECTION_NAME'],
                'CODE'  => self::PREFIX.$arMod['MODIFIER_ID'],
                'VALUE' => $arMod['NAME'],
                'SORT'  => $sort++
            );
        }
        return $arResult;
    }

    static function getByID($id, $arParams = array())
    {
        $params = array(
            'filter' => array(
                '=ID' => $id
            )
        );
        foreach ($arParams as $keyParam => $arOneParam)
        {
            $params[$keyParam] = isset($params[$keyParam])
                ? array_merge($params[$keyParam], $arOneParam)
                : $params[$keyParam] = $arOneParam;
        }
        return ElementModifiersTable::getRow($params);
    }

    /**
     * Функция добавляет или обновляет модификаторы у элемента
     *
     * @param int $elemID
     * @param int $modID
     * @param string $required
     * @return \Bitrix\Main\Entity\AddResult|\Bitrix\Main\Entity\UpdateResult
     * @throws IIKOException
     * @throws \Exception
     */
    static function add($elemID = 0, $modID = 0, $required = 'N')
    {
        if (!$elemID || !$modID)
        {
            throw new \Exception('Cant add/update element modifier. Empty element or modifier ID.');
        }
        $params = array(
            'select' => array('ID'),
            'filter' => array(
                '=ELEMENT_ID'  => $elemID,
                '=MODIFIER_ID' => $modID
            )
        );
        $row = ElementModifiersTable::getRow($params);
        if ($row['ID'] > 0)
        {
            return ElementModifiersTable::update($row['ID'], array('REQUIRED' => $required));
        }
        else
        {
            return ElementModifiersTable::add(array(
                'ELEMENT_ID'  => $elemID,
                'MODIFIER_ID' => $modID,
                'REQUIRED'    => $required
            ));
        }
    }

    /**
     * @param $elemID
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Exception
     */
    static function delete($elemID)
    {
        $params = array(
            'select' => array('ID'),
            'filter' => array(
                '=ELEMENT_ID' => $elemID
            )
        );
        $result = ElementModifiersTable::getList($params);
        while ($row = $result->fetch())
        {
            if (!ElementModifiersTable::delete($row['ID'])->isSuccess())
            {
                return false;
            }
        }
        return true;
    }
}