<?
namespace Iiko\Config;

use \Bitrix\Main\Config\Option as BitrixOption;

class Option
{
    static function get($code)
    {
        return BitrixOption::get('ugraweb.iiko', $code, '');
    }

    /**
     * Идентификатор самовывоз
     * @return int
     * @throws \Bitrix\Main\ArgumentNullException
     */
    static function getSelfDeliveryID()
    {
        return (int) self::get('self_delivery_id');
    }

    /**
     * XML_ID Дефолтного раздела модификаторов
     * @return string
     */
    static function getDefaultSectionID()
    {
        return 'iiko-default';
    }

    static function getURL()
    {
        return 'https://iiko.net:9900';
    }

    static function getPrefix()
    {
        return '/api/0';
    }

    static function getProvider()
    {
        // переделать на настройку модуля
        return '\Iiko\OrderProvider';
    }
}

class Props
{
    static protected $_instance = null;

    protected $default = array(
        'customer_name' => array(
            'name' => 'ФИО',
            'code' => 'name',
            'require' => 'Y'
        ),
        'customer_phone' => array(
            'name' => 'Телефон',
            'code' => 'phone',
            'require' => 'Y'
        ),
        'address_city' => array(
            'name' => 'Город',
            'code' => 'city',
            'require' => 'Y'
        ),
        'address_street' => array(
            'name' => 'Улица',
            'code' => 'street',
            'require' => 'Y'
        ),
        'address_streetId' => array(
            'name' => 'ID Улицы',
            'code' => 'streetId',
        ),
        'address_home' => array(
            'name' => 'Дом',
            'code' => 'home',
            'require' => 'Y'
        ),
        'address_housing' => array(
            'name' => 'Корпус',
            'code' => 'housing',
        ),
        'address_apartment' => array(
            'name' => 'Квартира',
            'code' => 'apartment',
            'require' => 'Y'
        ),
        'address_entrance' => array(
            'name' => 'Подъезд',
            'code' => 'entrance',
        ),
        'address_floor' => array(
            'name' => 'Этаж',
            'code' => 'floor',
        ),
        'address_doorphone' => array(
            'name' => 'Код домофона',
            'code' => 'doorphone',
        )
    );

    function getList()
    {
        return $this->default;
    }

    function getCustomerList()
    {
        return $this->getListByCode();
    }

    function getAddressList()
    {
        return $this->getListByCode('address');
    }

    private function getListByCode($code = 'customer')
    {
        $arCustomer = array();
        foreach ($this->default as $keyItem => $arItem)
        {
            if (strpos($keyItem, $code) !== false)
            {
                $arCustomer[$arItem['code']] = $arItem;
            }
        }
        return $arCustomer;
    }

    static function getInstance()
    {
        if (self::$_instance === null)
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct()
    {
        foreach ($this->default as $keyProp => $arOneProp)
        {
            $this->default[$keyProp]['value'] = BitrixOption::get('ugraweb.iiko', $keyProp, '');
        }
    }

    private function __clone() {}
}