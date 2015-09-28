<?
namespace Iiko;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Iiko\Config\Option;

Loader::includeModule('iblock');
Loader::includeModule('catalog');

class Import
{
    protected static $_instance;

    protected
        $CATALOG_ID = 0,
        $OFFERS_ID = 0,

        $arGroups      = array(),
        $arGroupsCache = array(),
        $arProducts    = array(),
        $arModifiers   = array(),

        $_revision = '',
        $defaultSectionID = '';

    private function getSectionByXML($xml_id, $iblock_id)
    {
        if (isset($this->arGroupsCache[$xml_id]))
        {
            return $this->arGroupsCache[$xml_id];
        }
        $params = array(
            'select' => array('ID', 'ACTIVE'),
            'filter' => array(
                '=IBLOCK_ID' => $iblock_id,
                '=XML_ID'    => $xml_id
            )
        );
        return $this->arGroupsCache[$xml_id] = SectionTable::getRow($params);
    }

    private function importSection($arItems, $IBLOCK_ID = 0, $event = 'section')
    {
        if (!$IBLOCK_ID)
        {
            $IBLOCK_ID = $this->CATALOG_ID;
        }
        static $arResult = array();

        $arChild = array();
        foreach ($arItems as $arItem)
        {
            if (!strlen($arItem['XML_ID']))
            {
                throw new \Exception('Empty section XML ID '.$arItem['name']);
            }
            if (!strlen($arItem['CODE']))
            {
                $arItem['CODE'] = \CUtil::translit($arItem['NAME'], 'ru', array(
                    "replace_space" => '-',
                    "replace_other" => '-'
                ));
            }
            $arFields = array(
                'ACTIVE'      => $arItem['ACTIVE'],
                'CODE'        => $arItem['CODE'],
                'NAME'        => $arItem['NAME'],
                'XML_ID'      => $arItem['XML_ID'],
                'TIMESTAMP_X' => new DateTime
            );
            $arSection = $this->getSectionByXML($arItem['XML_ID'], $IBLOCK_ID);
            if ($arSection['ID'] > 0)
            {
                // если раздел существует, сравним хэши
                if (!App::compareHash($arItem) || $arItem['ACTIVE'] !== $arSection['ACTIVE'])
                {
                    // если хэши не совпали, то проапдейтим найденый раздел
                    SectionTable::update($arSection['ID'], $arFields);
                    Report::update($event);
                }
            }
            else
            {
                if (strlen($arItem['SECTION_XML_ID']) > 0)
                {
                    $arParent = $this->getSectionByXML($arItem['SECTION_XML_ID'], $IBLOCK_ID);
                    if (!$arParent['ID'])
                    {
                        // тут хитрая штука, если дочерний раздел идет раньше родителя,
                        // то и создать его нужно позже, чуть ниже вызывается рекурсия
                        $arChild[] = $arItem;
                        continue;
                    }
                    // если он есть, то текущий раздел сделаем потомком
                    $arFields['IBLOCK_SECTION_ID'] = $arParent['ID'];
                }
                $arFields = array_merge($arFields, array(
                    'IBLOCK_ID'        => $IBLOCK_ID,
                    'DESCRIPTION_TYPE' => 'text'
                ));
                $arSection['ID'] = SectionTable::add($arFields)->getId();

                App::compareHash($arItem);
                Report::create($event);
            }

            // если раздел не нашли и не создали, выплюнем эксепшен
            if (!$arSection['ID'])
            {
                throw new \Exception('Cant create sections');
            }
            $arResult[$arItem['XML_ID']] = $arSection['ID'];
        }

        if (!empty($arChild))
        {
            $this->importSection($arChild, $IBLOCK_ID, $event);
        }

        \CIBlockSection::ReSort($IBLOCK_ID);
        return $arResult;
    }

    private function deactivateSections($arIDs = array(), $IBLOCK_ID = 0)
    {
        if (!$IBLOCK_ID)
        {
            $IBLOCK_ID = $this->CATALOG_ID;
        }
        $arIDs = array_values($arIDs);
        $params = array(
            'select' => array('ID'),
            'filter' => array(
                '=IBLOCK_ID' => $IBLOCK_ID,
                '!=ID'       => $arIDs,
                '=ACTIVE'    => 'Y'
            )
        );
        $result = SectionTable::getList($params);
        while ($arSect = $result->fetch())
        {
            SectionTable::update($arSect['ID'], array(
                'ACTIVE'      => 'N',
                'TIMESTAMP_X' => new DateTime
            ));
            Report::deactivate('section');
        }
    }

    private function importElement($arItems, $IBLOCK_ID = 0, $event = 'element')
    {
        if (!$IBLOCK_ID)
        {
            $IBLOCK_ID = $this->CATALOG_ID;
        }
        $arResult = array();
        $arOffers = array();

        $el = new \CIBlockElement;
        foreach ($arItems as $arItem)
        {
            if (!strlen($arItem['XML_ID']))
            {
                throw new \Exception('Empty element XML ID '.$arItem['name']);
            }
            $boolUpdate = true;

            if (!strlen($arItem['CODE']))
            {
                $arItem['CODE'] = \CUtil::translit($arItem['NAME'], 'ru', array(
                    "replace_space" => '-',
                    "replace_other" => '-'
                ));
            }
            $arLoadProduct = array(
                'ACTIVE'      => $arItem['ACTIVE'],
                'XML_ID'      => $arItem['XML_ID'],
                'NAME'        => $arItem['NAME'],
                'CODE'        => $arItem['CODE'],
                'DETAIL_TEXT' => $arItem['DESCRIPTION'],
            );
            $params = array(
                'select' => array('ID', 'ACTIVE'),
                'filter' => array(
                    '=IBLOCK_ID' => $IBLOCK_ID,
                    '=XML_ID'    => $arItem['XML_ID']
                )
            );
            $arElem = ElementTable::getRow($params);
            if ($arElem['ID'] > 0)
            {
                $boolUpdate = !App::compareHash($arItem);
                if ($boolUpdate || $arItem['ACTIVE'] !== $arElem['ACTIVE'])
                {
                    $el->Update($arElem['ID'], $arLoadProduct);
                    Report::update($event);
                }
            }
            else
            {
                $arSection = $this->getSectionByXML($arItem['SECTION_XML_ID'], $IBLOCK_ID);
                if (!$arSection['ID'])
                {
                    throw new \Exception('Cant find section with XML ID: '.$arItem['SECTION_XML_ID']);
                }
                $arLoadProduct = array_merge($arLoadProduct, array(
                    'IBLOCK_SECTION_ID' => $arSection['ID'],
                    'IBLOCK_ID'         => $IBLOCK_ID
                ));
                $arElem['ID'] = $el->Add($arLoadProduct);

                App::compareHash($arItem);
                Report::create($event);
            }

            // если элемент не нашли и не создали, выплюнем эксепшен
            if (!$arElem['ID'])
            {
                throw new \Exception('Cant create element');
            }
            $arResult[$arItem['XML_ID']] = $arElem['ID'];

            if ($boolUpdate)
            {
                $arCatalogProduct = array(
                    "ID"     => $arElem['ID'],
                    "WEIGHT" => $arItem['WEIGHT']
                );
                \CCatalogProduct::Add($arCatalogProduct);                      // добавим элемент в каталог
                \CPrice::SetBasePrice($arElem['ID'], $arItem['PRICE'], "RUB"); // установим базовую цену

                $arOfferProp = array();
                if (!empty($arItem['MODIFIERS']))
                {
                    foreach ($arItem['MODIFIERS'] as $arMod)
                    {
                        $arOfferProp[] = array(
                            'XML_ID'       => $this->defaultSectionID,
                            'VALUE_XML_ID' => $arMod['XML_ID'],
                            'REQUIRED'     => $arMod['REQUIRED']
                        );
                    }
                }
                if (!empty($arItem['GROUP_MODIFIERS'])) // тут то же самое, только нужно создать ТП из всего свойства, а не из конкретного значения
                {
                    foreach ($arItem['GROUP_MODIFIERS'] as $arMod)
                    {
                        $arOfferProp[] = array(
                            'XML_ID'       => $arMod['XML_ID'],
                            'VALUE_XML_ID' => '',
                            'REQUIRED'     => $arMod['REQUIRED']
                        );
                    }
                }

                if (!empty($arOfferProp))
                {
                    $arOffers[] = array(
                        'ID'     => $arElem['ID'],
                        'NAME'   => $arItem['NAME'],
                        'OFFERS' => $arOfferProp
                    );
                }
            }
        }

        if (!empty($arOffers)) // импорт ТП
        {
            $this->importOffers($arOffers);
        }

        return $arResult;
    }

    private function deactivateElements($arIDs = array(), $IBLOCK_ID = 0)
    {
        if (!$IBLOCK_ID)
        {
            $IBLOCK_ID = $this->CATALOG_ID;
        }
        $arIDs = array_values($arIDs);
        $el = new \CIBlockElement;

        $params = array(
            'select' => array('ID'),
            'filter' => array(
                '=IBLOCK_ID' => $IBLOCK_ID,
                '!=ID'       => $arIDs,
                '=ACTIVE'    => 'Y'
            )
        );
        $result = ElementTable::getList($params);
        while ($arElem = $result->fetch())
        {
            $el->Update($arElem['ID'], array('ACTIVE' => 'N'));
            Report::deactivate();
        }
    }

    private function importOffers($arOffers = array())
    {
        // в эту переменную будем кэшировать выборку из инфоблока модификаторов
        $arCache = array();
        foreach ($arOffers as $arElement)
        {
            foreach ($arElement['OFFERS'] as $arModifier)
            {
                $sectionKey = $arModifier['XML_ID'];
                $elementKey = $arModifier['VALUE_XML_ID'];

                $boolCached = isset($arCache[$sectionKey]);
                if (strlen($elementKey) > 0)
                {
                    $boolCached = isset($arCache[$sectionKey][$elementKey]);
                }
                if (!$boolCached)
                {
                    $params = array(
                        'select' => array('ID', 'XML_ID'),
                        'filter' => array(
                            '=IBLOCK_ID'      => $this->OFFERS_ID,
                            '=SECTION.XML_ID' => $sectionKey
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
                    if (strlen($elementKey) > 0)
                    {
                        $params['filter']['=XML_ID'] = $elementKey;
                    }

                    $result = ElementTable::getList($params);
                    while ($row = $result->fetch())
                    {
                        $arCache[$sectionKey][$row['XML_ID']] = $row['ID'];
                    }
                }

                foreach ($arCache[$sectionKey] as $modifierID)
                {
                    ElementModifiers::add($arElement['ID'], $modifierID, $arModifier['REQUIRED']);
                }
            }
        }
    }

    private function importModifiers($arModifiers = array())
    {
        $arElements = array();
        $arSections = array();
        foreach ($arModifiers as $arSection)
        {
            $arElements = array_merge($arElements, $arSection['ITEMS']);
            unset($arSection['ITEMS']);
            $arSections[] = $arSection;
        }
        $this->importSection($arSections, $this->OFFERS_ID, 'mod_section');
        $arIDs = $this->importElement($arElements, $this->OFFERS_ID, 'mod_elem');

        // добавим модификаторы в БД
        foreach ($arElements as $arElement)
        {
            Modifiers::add($arIDs[$arElement['XML_ID']], array(
                'NAME'       => $arElement['NAME'],
                'PRICE'      => $arElement['PRICE'],
                'WEIGHT'     => $arElement['WEIGHT']
            ));
        }
    }

    function sections()
    {
        $arSect = $this->importSection($this->arGroups);
        $this->deactivateSections($arSect);
        return $this;
    }

    function modifiers()
    {
        $this->importModifiers($this->arModifiers);
        return $this;
    }

    function elements()
    {
        $arElem = $this->importElement($this->arProducts);
        $this->deactivateElements($arElem);
        return $this;
    }

    function catalog()
    {
        return $this->sections()->modifiers()->elements();
    }

    function report()
    {
        return Report::write();
    }

    static function getInstance($IBLOCK_ID, $params = array())
    {
        if (self::$_instance === null)
        {
            self::$_instance = new self($IBLOCK_ID, $params);
        }
        return self::$_instance;
    }

    private function __construct($IBLOCK_ID, $params = array())
    {
        if (!isset($params['groups']) || !isset($params['products']))
        {
            throw new \Exception("Groups or Products not exist");
        }

        $rsCatalog = \CCatalog::GetList(array(), array('IBLOCK_ID' => intval($IBLOCK_ID)));
        if (!$arCatalog = $rsCatalog->Fetch())
        {
            throw new \Exception('Cant find catalog with ID = '.$IBLOCK_ID);
        }

        $this->CATALOG_ID = intval($arCatalog['IBLOCK_ID']);
        $this->OFFERS_ID = intval(Option::get('modifier_id'));

        if (!$this->OFFERS_ID)
        {
            // этот эксепшн можно заменить на создание инфоблока модификаторов
            throw new \Exception('Need set modifiers iblock');
        }

        $this->defaultSectionID = Option::getDefaultSectionID();
        $this->arModifiers[$this->defaultSectionID] = array(
            'XML_ID' => $this->defaultSectionID,
            'NAME'   => 'Другие модификаторы',
            'CODE'   => $this->defaultSectionID,
            'ACTIVE' => 'Y'
        );
        foreach ($params['groups'] as $arItem)
        {
            $arInfo = json_decode($arItem['additionalInfo'], true);
            $arGroup = array(
                "XML_ID"         => $arItem['id'],
                "SECTION_XML_ID" => $arItem['parentGroup'],
                "NAME"           => $arItem['name'],
                "CODE"           => $arInfo['CODE'],
                "ACTIVE"         => $arInfo['ACTIVE'] === 'N' ? 'N' : 'Y'
            );
            // группа модификаторов
            if (!$arItem['isIncludedInMenu'])
            {
                $this->arModifiers[$arItem['id']] = $arGroup;
                continue;
            }
            // обычная группа
            $this->arGroups[] = $arGroup;
        }

        foreach ($params['products'] as $arItem)
        {
            $arElement = array(
                'ACTIVE'         => 'Y',
                'XML_ID'         => $arItem['id'],
                'NAME'           => $arItem['name'],
                'CODE'           => $arItem['code'],
                'TYPE'           => strtoupper($arItem['type']),
                'DESCRIPTION'    => $arItem['description'],
                'SECTION_XML_ID' => $arItem['parentGroup'],
                'PRICE'          => doubleval($arItem['price']),
                'WEIGHT'         => $arItem['weight'] * 1000
            );
            if ($arItem['type'] === 'modifier')
            {
                $arElement['SECTION_XML_ID'] = $key = strlen($arItem['groupId']) > 0 ? $arItem['groupId'] : $this->defaultSectionID;
                $this->arModifiers[$key]['ITEMS'][] = $arElement;
                continue;
            }
            $arElement = array_merge($arElement, array(
                'MODIFIERS'       => array(),
                'GROUP_MODIFIERS' => array()
            ));
            if (!empty($arItem['modifiers'])) // обычные модификаторы
            {
                foreach ($arItem['modifiers'] as $arModifier)
                {
                    $arElement['MODIFIERS'][] = array(
                        'XML_ID'   => $arModifier['modifierId'],
                        'REQUIRED' => intval($arModifier['required']) > 0 ? 'Y' : 'N'
                    );
                }
            }
            if (!empty($arItem['groupModifiers'])) // групповые модификаторы
            {
                foreach ($arItem['groupModifiers'] as $arModifier)
                {
                    $arElement['GROUP_MODIFIERS'][] = array(
                        'XML_ID'   => $arModifier['modifierId'],
                        'REQUIRED' => intval($arModifier['required']) > 0 ? 'Y' : 'N'
                    );
                }
            }
            $this->arProducts[] = $arElement;
        }

        $iblockFields = \CIBlock::GetFields($this->CATALOG_ID);
        $iblockFields["XML_IMPORT_START_TIME"] = array(
            "NAME"          => "XML_IMPORT_START_TIME",
            "IS_REQUIRED"   => "N",
            "DEFAULT_VALUE" => date("Y-m-d H:i:s"),
        );
        \CIBlock::SetFields($this->CATALOG_ID, $iblockFields);
    }

    private function __clone() {}
}

class Report
{
    private static $total = array(
        'ELEMENT_DEACTIVATE' => 0,
        'ELEMENT_CREATE'     => 0,
        'ELEMENT_UPDATE'     => 0,
        'SECTION_CREATE'     => 0,
        'SECTION_DEACTIVATE' => 0,
        'SECTION_UPDATE'     => 0,
        'MOD_SECTION_CREATE' => 0,
        'MOD_SECTION_UPDATE' => 0,
        'MOD_ELEM_CREATE'    => 0,
        'MOD_ELEM_UPDATE'    => 0
    );
    private static $message = array(
        'ELEMENT_DEACTIVATE' => 'Элементов каталога деактивировано',
        'ELEMENT_CREATE'     => 'Элементов каталога создано',
        'ELEMENT_UPDATE'     => 'Элементов каталога обновлено',
        'SECTION_DEACTIVATE' => 'Разделов каталога деактивировано',
        'SECTION_CREATE'     => 'Разделов каталога создано',
        'SECTION_UPDATE'     => 'Разделов каталога обновлено',
        'MOD_SECTION_CREATE' => 'Разделов модификатора создано',
        'MOD_SECTION_UPDATE' => 'Разделов модификатора обновлено',
        'MOD_ELEM_CREATE'    => 'Элементов модификатора создано',
        'MOD_ELEM_UPDATE'    => 'Элементов модификатора обновлено'
    );
    static function write()
    {
        $message = '<table>';
        foreach (self::$message as $keyItem => $valItem)
        {
            $message .= '<tr><td>'.$valItem.':</td><td style="color: '.(self::$total[$keyItem] < 1 ? 'lightgrey' : '').'">'.self::$total[$keyItem].'</td></tr>';
        }
        $message .= '</table>';
        /*$message = '';
        foreach (self::$message as $keyItem => $valItem)
        {
            $message .= $valItem.': '.self::$total[$keyItem].'<br />';
        }*/
        return $message;
    }
    static function update($name = 'section')
    {
        $key = strtoupper($name).'_UPDATE';
        if (isset(self::$total[$key]))
        {
            self::$total[$key] += 1;
        }
    }
    static function create($name = 'section')
    {
        $key = strtoupper($name).'_CREATE';
        if (isset(self::$total[$key]))
        {
            self::$total[$key] += 1;
        }
    }
    static function deactivate($name = 'element')
    {
        $key = strtoupper($name).'_DEACTIVATE';
        if (isset(self::$total[$key]))
        {
            self::$total[$key] += 1;
        }
    }
}