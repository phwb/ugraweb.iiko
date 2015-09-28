<?
namespace Iiko;

use Iiko\Config\Option;

class Connect extends Base
{
    protected static $_instance;

    private function __construct($params)
    {
        $this->_userName = $params['USER_NAME'];
        $this->_userPass = $params['USER_PASSWORD'];
        if (strlen($params['REST_ID']) > 0)
        {
            $this->_restID = $params['REST_ID'];
        }
    }

    /**
     * Закрываем доступ к функции вне класса. Паттерн Singleton не допускает вызов этой функции вне класса
     */
    private function __clone() {}

    /**
     * Статическая функция, которая возвращает экземпляр класса или создает новый при необходимости
     * @param $params
     * @return Connect
     */
    static function getInstance($params)
    {
        if (self::$_instance === null)
        {
            self::$_instance= new self($params);
        }
        return self::$_instance;
    }

    /**
     * Функция возвращает список организаций
     * @return array
     */
    function getOrganizationList()
    {
        $result = $this->SendRequest('/organization/list', array('request_timeout' => 30));
        return json_decode($result, true);
    }

    /**
     * Получаем номенклатуру ресторана
     * @return mixed
     * @throws \Exception
     */
    function getNomenclature()
    {
        if (!strlen($this->_restID))
        {
            throw new \Exception("getNomenclature: empty restaurant ID");
        }
        $result = $this->sendRequest("/nomenclature/{$this->_restID}");
        return json_decode($result, true);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    function getPaymentTypes()
    {
        if (!strlen($this->_restID))
        {
            throw new \Exception("getPaymentTypes: empty restaurant ID");
        }
        $result = $this->sendRequest("/rmsSettings/getPaymentTypes", array('organization' => $this->_restID));
        return json_decode($result, true);
    }

    function sendOrder(array $order)
    {
        if (!strlen($this->token))
        {
            throw new \Exception("sendOrder: empty token");
        }
        $result = $this->sendRequest("/orders/add?access_token={$this->token}&requestTimeout=50", $order, false, true);
        return json_decode($result, true);
    }
}

class Base
{
    protected
        $_userName,
        $_userPass,
        $_restID = '';

    public $token = '';

    private function curl_get($url, array $get = NULL, array $options = array())
    {
        $defaults = array(
            CURLOPT_URL                  => $url.(strpos($url, '?') === false ? '?' : '').http_build_query($get),
            CURLOPT_HEADER               => 0,
            CURLOPT_RETURNTRANSFER       => true,
            CURLOPT_DNS_USE_GLOBAL_CACHE => false,
            CURLOPT_HTTPHEADER           => array('Content-Type: application/json; charset=utf-8'),
            CURLOPT_SSL_VERIFYHOST       => 0,
            CURLOPT_SSL_VERIFYPEER       => 0
        );
        $ch = curl_init();
        curl_setopt_array($ch, ($options + $defaults));
        if (!$result = curl_exec($ch))
        {
            throw new \Exception('Cant send GET request, description: '.curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }

    private function curl_post($url, $post = null, array $options = array())
    {
        $defaults = array(
            CURLOPT_POST           => 1,
            CURLOPT_HEADER         => 0,
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json; charset=utf-8'),
            CURLOPT_FRESH_CONNECT  => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE   => 1,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POSTFIELDS     => $post
        );
        $ch = curl_init();
        curl_setopt_array($ch, ($options + $defaults));
        if (!$result = curl_exec($ch))
        {
            throw new \Exception('Cant send POST request, description: '.curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }

    private function setToken()
    {
        $params = array(
            'user_id'     => $this->_userName,
            'user_secret' => $this->_userPass
        );
        $token = $this->sendRequest('/auth/access_token', $params, false);

        if (is_string($token))
        {
            $token = trim($token);
            $token = str_replace('"','', $token);
        }

        $this->token = $token;
    }

    function sendRequest($method, $params = array(), $checkToken = true, $isPOST = false)
    {
        if ($checkToken)
        {
            if ($this->token == '')
            {
                $this->setToken();
            }
            $params['access_token'] = $this->token;
        }

        $_prefix = Option::getPrefix();
        $_url = Option::getURL();

        $combinedURL = $_url.$_prefix.$method;

        if ($isPOST)
        {
            return $this->curl_post($combinedURL, json_encode($params));
        }
        else
        {
            return $this->curl_get($combinedURL, $params);
        }
    }
}