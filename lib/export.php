<?
namespace Iiko;

use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Sale\BasketTable;
use Iiko\DB\OrderTable;

class Export
{
    static protected $_instance = null;

    protected
        $arOrderIDs = array(),
        $orders = array();

    static function getOrderProvider($className = '')
    {
        return class_exists($className) && array_key_exists("Iiko\\IExportOrder", class_implements($className));
    }

    /**
     * @param $rest_id
     * @return $this
     */
    function prepareOrders($rest_id)
    {
        $selfDeliveryID = Config\Option::getSelfDeliveryID();
        $defaultSectionID = Config\Option::getDefaultSectionID();

        gg($this->arOrderIDs, 0, 'File: '.basename(__FILE__).', Line: '.__LINE__);
        foreach ($this->arOrderIDs as $orderID)
        {
            // выбираем свойства заказа
            $arOrderProp = array();
            $rsProps = \CSaleOrderPropsValue::GetList(
                array("SORT" => "ASC"),
                array("ORDER_ID" => $orderID)
            );
            while ($arProp = $rsProps->Fetch())
            {
                $arOrderProp[$arProp['ORDER_PROPS_ID']] = $arProp;
            }

            // свойства Пользователя по умолчанию
            $arCustomer = $this->fillProps(Config\Props::getInstance()->getCustomerList(), $arOrderProp);
            $customer = new Customer($arCustomer);

            // свойства Адреса по умолчанию
            $arAddress = $this->fillProps(Config\Props::getInstance()->getAddressList(), $arOrderProp);
            $address = new Address($arAddress);

            // содержимое заказа
            $arBasketItems = $this->getBasketItems($orderID);
            $arItems = array();
            foreach ($arBasketItems as $arBasketItem)
            {
                $modifiers = array();
                foreach ($arBasketItem['PROPS'] as $arModifier)
                {
                    $params = array(
                        'id' => $arModifier['ELEMENT_XML_ID'],
                        'name' => $arModifier['ELEMENT_NAME'],
                    );
                    $modifier = new Item($params);
                    if ($arModifier['SECTION_XML_ID'] !== $defaultSectionID)
                    {
                        $modifier->set('groupId', $arModifier['SECTION_XML_ID']);
                        $modifier->set('groupName', $arModifier['SECTION_NAME']);
                    }
                    $modifiers[] = $modifier->toArray();
                }

                $params = array(
                    'id' => $arBasketItem['XML_ID'],
                    'name' => $arBasketItem['NAME'],
                    'amount' => doubleval($arBasketItem['QUANTITY'])
                );
                $item = new Item($params);
                if (!empty($modifiers))
                {
                    $item->set('modifiers', $modifiers);
                }
                $arItems[] = $item->toArray();
            }

            $params = array(
                'select' => array('DATE_INSERT', 'COMMENTS', 'DELIVERY_ID'),
                'filter' => array(
                    '=ID' => $orderID
                )
            );
            $arOrder = OrderTable::getRow($params);

            // собственно сам заказ
            $order = new Order($address, $customer);
            $order->set('externalId', $orderID);
            $order->set('date', date('Y-m-d H:i:s', $arOrder['DATE_INSERT']->getTimestamp() + 60 * 60));
            $order->set('comment', $arOrder['COMMENTS']);
            $order->set('isSelfService', $arOrder['DELIVERY_ID'] == $selfDeliveryID);
            $order->set('items', $arItems);

            $this->orders[] = new OrderRequest($customer, $order, $rest_id);
        }
        return $this;
    }

    function registerOrders(Connect $connect)
    {
        /* @var $arOrder OrderRequest */
        foreach ($this->orders as $arOrder)
        {
            $result = $connect->sendOrder($arOrder->toArray());
            gg($result, 0, 'File: '.basename(__FILE__).', Line: '.__LINE__);
        }

        return $this;
    }

    function report()
    {
        return '<br />Тут будет отчет по отправленным заказам';
    }

    function getBasketItems($orderID)
    {
        // выбираем товар из заказа
        $params = array(
            'select' => array("ID", "QUANTITY", "PRICE", "NAME", "PRODUCT_ID", "DISCOUNT_PRICE", "XML_ID" => "ELEMENT.XML_ID"),
            'filter' => array(
                '=ORDER_ID' => $orderID
            ),
            'runtime' => array(
                new ReferenceField(
                    'ELEMENT',
                    '\Bitrix\Iblock\ElementTable',
                    array('=this.PRODUCT_ID' => 'ref.ID'),
                    array('join_type' => 'LEFT')
                )
            )
        );
        $arBasketItems = BasketTable::getList($params)->fetchAll();

        // выбираем свойства товара
        foreach ($arBasketItems as &$arBasketItem)
        {
            $rsBasketProp = \CSaleBasket::GetPropsList(
                array("SORT" => "ASC"),
                array(
                    "BASKET_ID" => $arBasketItem["ID"],
                    "!CODE" => array("CATALOG.XML_ID", "PRODUCT.XML_ID")
                )
            );
            while ($arBasketProp = $rsBasketProp->Fetch())
            {

                $arElemMod = ElementModifiers::getByPropCode($arBasketProp['CODE']);
                $arBasketItem["PROPS"][] = array_merge($arBasketProp, $arElemMod);
            }
        }

        return $arBasketItems;
    }

    private function fillProps(array $config, array $props)
    {
        $arResult = array();
        foreach ($config as $keyProp => $arProp)
        {
            $val = $props[$arProp['value']]['VALUE'];
            if (strlen($val) > 0)
            {
                $arResult[$keyProp] = $val;
            }
        }
        return $arResult;
    }

    static function getInstance(array $params)
    {
        if (self::$_instance === null)
        {
            self::$_instance = new self($params);
        }
        return self::$_instance;
    }

    private function __construct($arOrderIDs)
    {
        $this->arOrderIDs = $arOrderIDs;
    }

    private function __clone() {}
}

class OrderProvider implements IExportOrder
{
    static function getOrderIDs($site_id)
    {
        $rows = array();
        $params = array(
            'select' => array('ID'),
            'filter' => array(
                '=LID' => $site_id,
                '=UPDATED_1C' => 'N'
            )
        );
        $result = OrderTable::getList($params);
        while ($row = $result->fetch())
        {
            $rows[] = $row['ID'];
        }
        return $rows;
    }
}

interface IExportOrder
{
    /**
     * Получаем массив айдишников всех заказов
     *
     * @param $site_id string
     * @return array
     */
    static function getOrderIDs($site_id);
}

///////////////////////////////////////////////////////////////////////////////
// Вспомогательные классы для обеспечения валидации данных отправляемых в айко
///////////////////////////////////////////////////////////////////////////////

interface OrderArray
{
    /**
     * @return array
     */
    public function toArray();
}

abstract class OrderBase implements OrderArray
{
    protected $fields = array();

    protected $require = array();

    function __construct(array $params = null)
    {
        $arDefault = $this->getMap();
        foreach ($arDefault as $arItem)
        {
            $code = $arItem['code'];

            // обязательные поля
            if ($arItem['require'] === 'Y')
            {
                $this->require[] = $code;
                if (!isset($params[$code]))
                {
                    $params[$code] = !empty($arItem['default_value']) ? $arItem['default_value'] : '';
                }
            }

            // дефолтные значения, если присутствуют
            if (empty($params[$code]) && !empty($arItem['default_value']))
            {
                $params[$code] = $arItem['default_value'];
            }
        }

        foreach ($params as $keyParam => $valParam)
        {
            if (in_array($keyParam, $this->require) && empty($valParam))
            {
                throw new \Exception('Не заполнено обязательное поле: '.$keyParam);
            }
            if (!$this->validate($keyParam, $valParam))
            {
                throw new \Exception('Значение поля: '.$keyParam.' не прошло валидацию');
            }
            $this->fields[$keyParam] = $valParam;
        }
    }

    function set($key, $value = '')
    {
        $this->fields[$key] = $value;
        return $this;
    }

    function get($code)
    {
        if (isset($this->fields[$code]) && $this->fields[$code] !== null)
        {
            return $this->fields[$code];
        }
        return null;
    }

    function toArray()
    {
        return $this->fields;
    }

    /**
     * Returns entity map definition
     * @return array(
     *      'key' => array(
     *          'code' => string,
     *          'require' => bool Y,
     *          'default_value' => mixed
     *      )
     *      ....
     *  )
     */
    function getMap()
    {
        return array();
    }

    /**
     * @param $key string
     * @param $val string
     * @return bool
     */
    function validate($key, $val)
    {
        return true;
    }
}

class Customer extends OrderBase
{
    private $pattern = '/^(8|\+?\d{1,3})?[ -]?\(?(\d{3})\)?[ -]?(\d{3})[ -]?(\d{2})[ -]?(\d{2})$/';

    function getMap()
    {
        return Config\Props::getInstance()->getCustomerList();
    }

    function validate($key, $val)
    {
        $funcName = 'validate_'.strtolower($key);
        if (property_exists(__CLASS__, $funcName))
        {
            return $this->$funcName($val);
        }
        return true;
    }

    private function validate_name($val)
    {
        return strlen($val) > 0 && strlen($val) < 60;
    }

    private function validate_phone($val)
    {
        return preg_match($this->pattern, $val);
    }
}

class Address extends OrderBase
{
    function getMap()
    {
        return Config\Props::getInstance()->getAddressList();
    }
}

class Item extends OrderBase
{
    function getMap()
    {
        return array(
            'id' => array(
                'code' => 'id',
                'require' => 'Y'
            ),
            'name' => array(
                'code' => 'name',
                'require' => 'Y'
            ),
            'amount' => array(
                'code' => 'amount',
                'require' => 'Y',
                'default_value' => 1
            )
        );
    }
}

class Order extends OrderBase
{
    function __construct(Address $address, Customer $customer)
    {
        $params = array(
            'address'   => $address->toArray(),
            'phone'     => $customer->get('phone'),
        );
        parent::__construct($params);
    }

    function getMap()
    {
        return array(
            'address' => array(
                'code' => 'address',
                'require' => 'Y'
            ),
            'phone' => array(
                'code' => 'phone',
                'require' => 'Y'
            ),
            'orderType' => array(
                'code' => 'orderType',
                'require' => 'Y',
                'default_value' => 3
            )
        );
    }
}

class OrderRequest extends OrderBase
{
    function __construct(Customer $customer, Order $order, $organization)
    {
        $params = array(
            'customer'     => $customer->toArray(),
            'order'        => $order->toArray(),
            'organization' => $organization
        );
        parent::__construct($params);
    }

    function getMap()
    {
        return array(
            'customer' => array(
                'code' => 'customer',
                'require' => 'Y'
            ),
            'order' => array(
                'code' => 'order',
                'require' => 'Y'
            ),
            'organization' => array(
                'code' => 'organization',
                'require' => 'Y'
            )
        );
    }
}