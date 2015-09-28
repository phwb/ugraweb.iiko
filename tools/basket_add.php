<?
define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_CHECK", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

global $APPLICATION;

/**
 * переопределеить в стандартном компоненте catalog.element переменную $currentPath
 *
 * $currentPath = '/local/modules/ugraweb.iiko/tools/basket_add.php';
 * $arResult['~BUY_URL_TEMPLATE'] = $currentPath.'?'.$arParams["ACTION_VARIABLE"]."=BUY&".$arParams["PRODUCT_ID_VARIABLE"]."=#ID#";
 * $arResult['~ADD_URL_TEMPLATE'] = $currentPath.'?'.$arParams["ACTION_VARIABLE"]."=ADD2BASKET&".$arParams["PRODUCT_ID_VARIABLE"]."=#ID#";
 */

// массив должен соответствовать массиву из параметров компонента catalog
$arParams = array(
    'ACTION_VARIABLE'           => 'action',
    'PRODUCT_ID_VARIABLE'       => 'id',
    'PRODUCT_PROPS_VARIABLE'    => 'prop',
    'PRODUCT_QUANTITY_VARIABLE' => 'quantity'
);

$strError = '';
$successfulAdd = true;

if (isset($_REQUEST[$arParams["ACTION_VARIABLE"]]) && isset($_REQUEST[$arParams["PRODUCT_ID_VARIABLE"]]))
{
    $action = strtoupper($_REQUEST[$arParams["ACTION_VARIABLE"]]);
    $productID = (int) $_REQUEST[$arParams["PRODUCT_ID_VARIABLE"]];
    if (($action == "ADD2BASKET" || $action == "BUY" || $action == "SUBSCRIBE_PRODUCT") && $productID > 0)
    {
        $QUANTITY = 0;
        $product_properties = array();
        $arRewriteFields = array();

        try
        {
            Loader::includeModule("sale");
            Loader::includeModule("catalog");
            Loader::includeModule("ugraweb.iiko");

            $intProductIBlockID = (int) CIBlockElement::GetIBlockByID($productID);
            if ($intProductIBlockID > 0)
            {
                if (
                    isset($_REQUEST[$arParams["PRODUCT_PROPS_VARIABLE"]])
                    && is_array($_REQUEST[$arParams["PRODUCT_PROPS_VARIABLE"]])
                )
                {
                    $product_properties = \Iiko\ElementModifiers::checkElementModifiers(
                        $productID,
                        $_REQUEST[$arParams["PRODUCT_PROPS_VARIABLE"]]
                    );
                    if (empty($product_properties))
                    {
                        $strError = "Не все модификаторы товара, добавляемые в корзину, заполнены";
                        $successfulAdd = false;
                    }
                }

                if (isset($_REQUEST[$arParams["PRODUCT_QUANTITY_VARIABLE"]]))
                {
                    $QUANTITY = doubleval($_REQUEST[$arParams["PRODUCT_QUANTITY_VARIABLE"]]);
                }

                if (!$QUANTITY)
                {
                    $rsRatios = CCatalogMeasureRatio::getList(
                        array(),
                        array('PRODUCT_ID' => $productID),
                        false,
                        false,
                        array('PRODUCT_ID', 'RATIO')
                    );
                    if ($arRatio = $rsRatios->Fetch())
                    {
                        $intRatio = (int) $arRatio['RATIO'];
                        $dblRatio = doubleval($arRatio['RATIO']);
                        $QUANTITY = ($dblRatio > $intRatio ? $dblRatio : $intRatio);
                    }
                }

                if (!$QUANTITY)
                {
                    $QUANTITY = 1;
                }
            }
            else
            {
                $strError = "Элемент не найден";
                $successfulAdd = false;
            }
        }
        catch (\Exception $e)
        {
            $strError = $e->getMessage();
            $successfulAdd = false;
        }

        if ($successfulAdd)
        {
            $notifyOption = \Bitrix\Main\Config\Option::get('sale', 'subscribe_prod', '');
            $arNotify = unserialize($notifyOption);
            if ($action === "SUBSCRIBE_PRODUCT" && $arNotify[SITE_ID]['use'] == 'Y')
            {
                $arRewriteFields["SUBSCRIBE"] = "Y";
                $arRewriteFields["CAN_BUY"] = "N";
            }
        }

        if ($successfulAdd)
        {
            if(!Add2BasketByProductID($productID, $QUANTITY, $arRewriteFields, $product_properties))
            {
                if ($ex = $APPLICATION->GetException())
                {
                    $strError = $ex->GetString();
                }
                else
                {
                    $strError = "Ошибка добавления товара в корзину";
                }
                $successfulAdd = false;
            }
        }

        $addResult = $successfulAdd
            ? array('STATUS' => 'OK', 'MESSAGE' => "Товар успешно добавлен в корзину")
            : array('STATUS' => 'ERROR', 'MESSAGE' => $strError);

        $APPLICATION->RestartBuffer();
        echo CUtil::PhpToJSObject($addResult);
        die();
    }
}