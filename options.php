<?
use Bitrix\Main\Config\Option;

$module_id = 'ugraweb.iiko';
$mid = $_REQUEST["mid"];

\Bitrix\Main\Loader::includeModule('iblock');
\Bitrix\Main\Loader::includeModule('sale');
\Bitrix\Main\Loader::includeModule('ugraweb.iiko');

// дефолтные значения свойств заказа которые уйдут в айку
$arDefaultProp = \Iiko\Config\Props::getInstance()->getList();

if ($_SERVER["REQUEST_METHOD"] === "POST" && check_bitrix_sessid())
{
    $errorMessage = "";

    $IBLOCK_ID = intval($_POST['modifier_id']);
    if ($IBLOCK_ID > 0)
    {
        Option::set($module_id, "modifier_id", $IBLOCK_ID);

        if (is_array($_POST['HIDDEN_MODIFIERS']) && !empty($_POST['HIDDEN_MODIFIERS']))
        {
            try
            {
                \Iiko\Modifiers::updateHidden(array_map('intval', $_POST['HIDDEN_MODIFIERS']));
            }
            catch (\Exception $e)
            {
                $errorMessage = $e->getMessage();
            }
        }
    }
    else
    {
        $errorMessage = 'Выберите информационный блок';
    }

    Option::set($module_id, "self_delivery_id", intval($_POST['SELF_DELIVERY_ID']));

    foreach ($arDefaultProp as $keyProp => $arOneProp)
    {
        if ($arOneProp['require'] === 'Y' && !strlen($_POST['PROP'][$keyProp]))
        {
            $errorMessage .= 'Не заполнено обязательное свойство: '.$arOneProp['name'];
            continue;
        }
        Option::set($module_id, $keyProp, $_POST['PROP'][$keyProp]);
    }

    if (!strlen($errorMessage))
    {
        LocalRedirect($APPLICATION->GetCurPageParam("mid=".urlencode($mid)."&lang=".LANG, array("mid", "lang")));
    }
    ShowMessage($errorMessage);
}

$arParams = array(
    'MODIFIER_ID' => intval(Option::get($module_id, 'modifier_id', '')),
    'SELF_DELIVERY_ID' => intval(Option::get($module_id, 'self_delivery_id', ''))
);

$arModifiers = array();
if ($arParams['MODIFIER_ID'] > 0)
{
    $params = array(
        'select'  => array(
            'SECTION_ID'   => 'ELEMENT.IBLOCK_SECTION_ID',
            'SECTION_NAME' => 'SECTION.NAME'
        ),
        'runtime' => array(
            new \Bitrix\Main\Entity\ReferenceField(
                'ELEMENT',
                '\Bitrix\Iblock\ElementTable',
                array('=this.ELEMENT_ID' => 'ref.ID'),
                array('join_type' => 'LEFT')
            ),
            new \Bitrix\Main\Entity\ReferenceField(
                'SECTION',
                '\Bitrix\Iblock\SectionTable',
                array('=this.SECTION_ID' => 'ref.ID'),
                array('join_type' => 'LEFT')
            )
        )
    );
    $arElements = \Iiko\Modifiers::getList($params)->fetchAll();

    // разбиваем элементы по разделам
    foreach ($arElements as $arElem)
    {
        $sectionID = $arElem['SECTION_ID'];
        if (!isset($arModifiers[$sectionID]))
        {
            $arModifiers[$sectionID] = array(
                'ID'   => $arElem['SECTION_ID'],
                'NAME' => $arElem['SECTION_NAME']
            );
        }
        $arModifiers[$sectionID]['ITEMS'][] = $arElem;
    }
}

$rsDelivery = CSaleDelivery::GetList(array('SORT' => 'ASC'));
while ($arItem = $rsDelivery->Fetch())
{
    $arDelivery[] = array(
        'ID' => $arItem['ID'],
        'NAME' => $arItem['NAME']
    );
}

$rsOrderProps = CSaleOrderProps::GetList();
while ($arItem = $rsOrderProps->Fetch())
{
    $arOrderProps[] = $arItem;
}

$aTabs = array(
    array(
        "DIV"   => "edit1",
        "TAB"   => "Настройки",
        "ICON"  => "currency_settings",
        "TITLE" => "Настройка обмена с облаком"
    )
);
$tabControl = new CAdminTabControl("tabControl", $aTabs);
$tabControl->Begin();
?>
<form method="POST" action="<? echo $APPLICATION->GetCurPageParam("mid=".htmlspecialcharsbx($mid)."&lang=".LANG, array("mid", "lang")); ?>">
    <?
    echo bitrix_sessid_post();
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td valign="top" width="50%">Информационный блок модификаторов:</td>
        <td valign="top" width="50%"><?
            echo GetIBlockDropDownListEx(
                $arParams['MODIFIER_ID'],
                'modifier_type_id',
                'modifier_id',
                array(
                    'CHECK_PERMISSIONS' => 'Y',
                    'MIN_PERMISSION'    => 'W'
                ),
                "",
                "",
                'class="adm-detail-iblock-types"',
                'class="adm-detail-iblock-list"'
            );
            ?></td>
    </tr>
    <tr class="heading">
        <td colspan="2">Настройка доставки</td>
    </tr>
    <tr>
        <td valign="top" width="50%">Самовывоз:</td>
        <td valign="top" width="50%">
            <select name="SELF_DELIVERY_ID">
                <?foreach($arDelivery as $arItem):?>
                    <option value="<? echo $arItem['ID'] ?>"<?if ($arItem['ID'] == $arParams['SELF_DELIVERY_ID']):?> selected<?endif?>>
                        <? echo $arItem['NAME'] ?>
                    </option>
                <?endforeach?>
            </select>
        </td>
    </tr>
    <tr class="heading">
        <td colspan="2">Настройка свойств заказа</td>
    </tr>
    <?foreach($arDefaultProp as $keyProp => $arOneProp):?>
        <tr>
            <td valign="top" width="50%">
                <?if ($arOneProp['require'] === 'Y'):?><b><?endif?>
                <? echo $arOneProp['name'] ?>:
                <?if ($arOneProp['require'] === 'Y'):?></b><?endif?>
            </td>
            <td valign="top" width="50%">
                <select name="PROP[<? echo $keyProp ?>]">
                    <option value="">(не выбрано)</option>
                    <?foreach ($arOrderProps as $arOrderProp):?>
                        <option value="<? echo $arOrderProp['ID'] ?>"<?if ($arOrderProp['ID'] == $arOneProp['value']):?> selected<?endif?>><? echo $arOrderProp['NAME'] ?></option>
                    <?endforeach?>
                </select>
            </td>
        </tr>
    <?endforeach?>
    <tr class="heading">
        <td colspan="2">Настройка скрытых модификаторов</td>
    </tr>
    <?
    if (!empty($arModifiers)):
        foreach ($arModifiers as $arOffer):
            ?>
            <tr>
                <td valign="top" width="50%">
                    <label for="SECTION_<? echo $arOffer['ID']; ?>"><? echo $arOffer['NAME']; ?></label>:
                </td>
                <td valign="top" width="50%">
                    <input type="button" value="Выбрать всю группу" onclick="selectAll(<? echo $arOffer['ID']; ?>)">
                    <br /><br />
                    <select id="SECTION_<? echo $arOffer['ID']; ?>" name="HIDDEN_MODIFIERS[]" multiple size="5">
                        <?foreach($arOffer['ITEMS'] as $arItem):?>
                            <option value="<? echo $arItem['ID']; ?>"<?if ($arItem['IS_HIDDEN'] === 'Y'):?> selected<?endif?>><? echo $arItem['NAME'] ?></option>
                        <?endforeach?>
                    </select>
                </td>
            </tr>
            <?
        endforeach;
    else:
        ?>
        <tr>
            <td colspan="2" align="center"><b>Выберите информационный блок</b></td>
        </tr>
        <?
    endif;
    $tabControl->Buttons();
    ?>

    <input type="submit" class="adm-btn-save" name="Update" value="Сохранить">
    <input type="hidden" name="Update" value="Y">

    <?$tabControl->End();?>
</form>
<script>
    function selectAll(id) {
        var select = BX('SECTION_' + id);
        if (!select) {
            return this;
        }

        Array.prototype.forEach.call(select.options, function(item) {
            item.selected = true;
        });
    }
</script>