<?
/**
 * @global string $USER_NAME
 * @global string $USER_PASSWORD
 * @global string $SITE_ID
 * @global string $REST_ID
 * @global int $CATALOG_ID
 */

$strImportErrorMessage = "";
$strImportOKMessage = "";

\Bitrix\Main\Loader::includeModule('ugraweb.iiko');
$params = array(
    'USER_NAME'     => $USER_NAME,
    'USER_PASSWORD' => $USER_PASSWORD,
    'REST_ID'       => $REST_ID
);

$startImportExecTime = getmicrotime();

// получаем номенклатуру из облака
$iikoCloud = \Iiko\Connect::getInstance($params);
$arNomenclature = $iikoCloud->getNomenclature();

// импортируем номенклатуру в наш каталог
try
{
    $strImportOKMessage .= \Iiko\Import::getInstance($CATALOG_ID, $arNomenclature)->catalog()->report();
    $strImportOKMessage .= str_replace("#TIME#", roundEx(getmicrotime() - $startImportExecTime, 2), "<br />Загрузка заняла <b>#TIME#</b> сек<br />");
}
catch (Exception $e)
{
    $strImportErrorMessage .= $e->getMessage()."\n";
}

// делаем экспорт в айку
try
{
    /** @var $provider \Iiko\IExportOrder */
    $provider = \Iiko\Config\Option::getProvider();
    if (!\Iiko\Export::getOrderProvider($provider))
    {
        $provider = '\Iiko\OrderProvider';
    }
    $arOrderIDs = $provider::getOrderIDs($SITE_ID);
    $strImportOKMessage .= \Iiko\Export::getInstance($arOrderIDs)->prepareOrders($REST_ID)->registerOrders($iikoCloud)->report();
}
catch (Exception $e)
{
    $strImportErrorMessage .= $e->getMessage()."\n";
}

gg($strImportOKMessage, 0, 'File: '.basename(__FILE__).', Line: '.__LINE__);
die();