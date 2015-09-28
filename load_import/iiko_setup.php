<?
/**
 * @var string $ACTION переменная запроса
 * @var string $USER_NAME
 * @var string $USER_PASSWORD
 * @var string $SITE_ID
 * @var string $REST_ID
 * @var int $CATALOG_ID
 * @var int $STEP
 * @var int $PROFILE_ID
 * @var int $SETUP_PROFILE_NAME
 */

$arSetupErrors = array();

//********************  ACTIONS  **************************************//
if (($ACTION == 'IMPORT_EDIT' || $ACTION == 'IMPORT_COPY') && $STEP == 1)
{
    // step 1
    if (isset($arOldSetupVars['USER_NAME']))
    {
        $USER_NAME = $arOldSetupVars['USER_NAME'];
    }
    if (isset($arOldSetupVars['USER_PASSWORD']))
    {
        $USER_PASSWORD = $arOldSetupVars['USER_PASSWORD'];
    }
    if (isset($arOldSetupVars['SITE_ID']))
    {
        $SITE_ID = $arOldSetupVars['SITE_ID'];
    }
    // step 2
    if (isset($arOldSetupVars['REST_ID']))
    {
        $REST_ID = $arOldSetupVars['REST_ID'];
    }
    if (isset($arOldSetupVars['CATALOG_ID']))
    {
        $CATALOG_ID = intval($arOldSetupVars['CATALOG_ID']);
    }
    // default settings
    if (isset($arOldSetupVars['SETUP_PROFILE_NAME']))
    {
        $SETUP_PROFILE_NAME = $arOldSetupVars['SETUP_PROFILE_NAME'];
    }
}

// валидация первой вкладки
if ($STEP > 1)
{
    // код сайта
    if (!strlen($SITE_ID))
    {
        $arSetupErrors[] = 'Не выбран или не введен символьный код сайт.';
    }
    if (!strlen($USER_NAME) || !strlen($USER_PASSWORD))
    {
        $arSetupErrors[] = 'Не заполнены поля "Имя пользователя" или "Пароль".';
    }
    // если прошли валидацию, то попробуем получить список ресторанов
    if (empty($arSetupErrors))
    {
        \Bitrix\Main\Loader::includeModule('ugraweb.iiko');
        $params = array(
            'USER_NAME'     => $USER_NAME,
            'USER_PASSWORD' => $USER_PASSWORD
        );
        $arOrganization = \Iiko\Connect::getInstance($params)->getOrganizationList();

        if (isset($arOrganization['httpStatusCode']) && intval($arOrganization['httpStatusCode']) > 200)
        {
            $arSetupErrors[] = 'Ошибка в запросе к IIKO: '.$arOrganization['message'];
        }
    }
    // если все плохо, то наша песня хороша начинай с начала
    if (!empty($arSetupErrors))
    {
        $STEP = 1;
    }
}

// валидация второй вкладки
if ($STEP > 2)
{
    // ID ресторана
    if (!strlen($REST_ID))
    {
        $arSetupErrors[] = 'Не выбран ресторан.';
    }
    // ID инфобока
    if (empty($arSetupErrors))
    {
        $CATALOG_ID = intval($CATALOG_ID);

        if ($CATALOG_ID > 0)
        {
            if (CIBlock::GetArrayByID($CATALOG_ID) === false)
            {
                $arSetupErrors[] = "Информационный блок каталога не выбран. Загрузка невозможна.";
            }
        }
        else
        {
            $arSetupErrors[] = "Информационные блоки не выбраны. Загрузка невозможна.";
        }
    }
    // права на инфоблок
    if (empty($arSetupErrors))
    {
        if (!CIBlockRights::UserHasRightTo($CATALOG_ID, $CATALOG_ID, 'iblock_admin_display'))
        {
            $arSetupErrors[] = "Отсутствуют права на запись в инфоблок каталога";
        }
    }
    // если все плохо, то наша песня хороша начинай с начала
    if (!empty($arSetupErrors))
    {
        $STEP = 2;
    }
}
//********************  END ACTIONS  **********************************//

$aMenu = array(
    array(
        "TEXT"  => "Вернуться в список",
        "TITLE" => "Вернуться в список профилей импорта",
        "LINK"  => "/bitrix/admin/cat_import_setup.php?lang=".LANGUAGE_ID,
        "ICON"  => "btn_list",
    )
);

$context = new CAdminContextMenu($aMenu);
$context->Show();

if (!empty($arSetupErrors))
{
    ShowError(implode('<br>', $arSetupErrors));
}
?><form method="POST" action="<? echo $APPLICATION->GetCurPage(); ?>" ENCTYPE="multipart/form-data" name="dataload"><?
$aTabs = array(
    array(
        "DIV"   => "edit1",
        "TAB"   => "Авторизация",
        "ICON"  => "store",
        "TITLE" => "Авторизационные данные в облаке"
    ),
    array(
        "DIV"   => "edit2",
        "TAB"   => "Данные",
        "ICON"  => "store",
        "TITLE" => "Источник данных"
    ),
    array(
        "DIV"   => "edit3",
        "TAB"   => "Результат",
        "ICON"  => "store",
        "TITLE" => ""
    )
);

$arSite = array();
$siteIterator = Bitrix\Main\SiteTable::getList(array(
    'select' => array('LID', 'NAME'),
    'order' => array('SORT' => 'ASC')
));
while ($oneSite = $siteIterator->fetch())
{
    $arSite[] = array('ID' => $oneSite['LID'], 'NAME' => $oneSite['NAME']);
}
unset($oneSite, $siteIterator);

$tabControl = new CAdminTabControl("tabControl", $aTabs, false, true);
$tabControl->Begin();

// первая вкладка
$tabControl->BeginNextTab();
if ($STEP == 1)
{
    ?>
    <tr class="heading">
        <td colspan="2">Авторизация</td>
    </tr>
    <tr>
        <td valign="top" width="40%">
            <label for="user_name">Пользователь:</label>
        </td>
        <td valign="top" width="60%">
            <input type="text" name="USER_NAME" id="user_name" size="35" value="<? echo $USER_NAME; ?>">
        </td>
    </tr>
    <tr>
        <td valign="top" width="40%">
            <label for="user_password">Пароль:</label>
        </td>
        <td valign="top" width="60%">
            <input type="text" name="USER_PASSWORD" id="user_password" size="35" value="<? echo $USER_PASSWORD; ?>">
        </td>
    </tr>
    <tr class="heading">
        <td colspan="2">Выбор сайта</td>
    </tr>
    <tr>
        <td valign="top" width="40%">Символьный код сайта:</td>
        <td valign="top" width="60%">
            <select name="SITE_ID" id="SITE_ID" onchange="selectSite(this.value)"><?
                foreach ($arSite as $arItem)
                {
                    $bFound = $SITE_ID == $arItem['ID'];
                    ?><option value="<? echo $arItem['ID']; ?>"<? if ($bFound): ?> selected<? endif; ?>><? echo $arItem['NAME']; ?></option><?
                }
                ?><option value=""<? if (!$bFound): ?> selected<? endif; ?>>(другой)</option>
            </select>
            <input type="text" name="SITE_ID" id="SITE_ID_text" value="<? if (!$bFound): echo $SITE_ID; endif; ?>" disabled>
            <script>
                (function selectSite(current) {
                    if (!current.length) {
                        BX('SITE_ID_text').removeAttribute('disabled');
                        BX('SITE_ID_text').focus();
                    } else {
                        BX('SITE_ID_text').setAttribute('disabled', 'disabled');
                    }
                    window.selectSite = selectSite;
                }(BX('SITE_ID').value));
            </script>
        </td>
    </tr>
    <?
}
$tabControl->EndTab();

$tabControl->BeginNextTab();
if ($STEP == 2)
{
    ?>
    <tr class="heading">
        <td colspan="2">Выбор ресторана</td>
    </tr>
    <tr>
        <td valign="top" width="40%">
            <label for="REST_ID">Доступные рестораны:</label>
        </td>
        <td valign="top" width="60%">
            <select name="REST_ID" id="REST_ID"><?
                /** @var array $arOrganization */
                foreach ($arOrganization as $arItem)
                {
                    $bFound = $REST_ID == $arItem['id'];
                    ?><option value="<? echo $arItem['id']; ?>"<? if ($bFound): ?> selected<? endif; ?>><? echo $arItem['name']; ?></option><?
                }
                ?></select>
        </td>
    </tr>
    <tr class="heading">
        <td colspan="2">Каталог</td>
    </tr>
    <tr>
        <td valign="top" width="40%">Информационный блок каталога:</td>
        <td valign="top" width="60%"><?
            if (!intval($CATALOG_ID))
            {
                $CATALOG_ID = 0;
            }
            echo GetIBlockDropDownListEx(
                $CATALOG_ID,
                'CATALOG_TYPE_ID',
                'CATALOG_ID',
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
    <?
    if ($ACTION == "IMPORT_SETUP" || $ACTION == 'IMPORT_EDIT' || $ACTION == 'IMPORT_COPY')
    {
        ?>
        <tr class="heading">
            <td colspan="2">Сохранить настройки</td>
        </tr>
        <tr>
            <td valign="top" width="40%">Имя профиля:</td>
            <td valign="top" width="60%">
                <input type="text" name="SETUP_PROFILE_NAME" size="40" value="<? echo htmlspecialcharsbx($SETUP_PROFILE_NAME); ?>">
            </td>
        </tr>
    <?
    }
}
$tabControl->EndTab();

$tabControl->BeginNextTab();
if ($STEP == 3)
{
    $FINITE = true;
}
$tabControl->EndTab();

// вкладки кончились, пришло время кнопок
$tabControl->Buttons();

echo bitrix_sessid_post();
if ($ACTION == 'IMPORT_EDIT' || $ACTION == 'IMPORT_COPY')
{
    ?><input type="hidden" name="PROFILE_ID" value="<? echo intval($PROFILE_ID); ?>"><?
}

if ($STEP < 3)
{
    ?>
    <input type="hidden" name="STEP" value="<? echo intval($STEP) + 1; ?>">
	<input type="hidden" name="lang" value="<? echo LANGUAGE_ID; ?>">
	<input type="hidden" name="ACT_FILE" value="<? echo htmlspecialcharsbx($_REQUEST["ACT_FILE"]); ?>">
	<input type="hidden" name="ACTION" value="<? echo htmlspecialcharsbx($ACTION); ?>">
	<?
    if ($STEP > 1)
    {
        ?>
        <input type="hidden" name="USER_NAME" value="<? echo htmlspecialcharsbx($USER_NAME); ?>">
        <input type="hidden" name="USER_PASSWORD" value="<? echo htmlspecialcharsbx($USER_PASSWORD); ?>">
        <input type="hidden" name="SITE_ID" value="<? echo htmlspecialcharsbx($SITE_ID); ?>">
        <?
        $arFieldsString = array(
            'USER_NAME',
            'USER_PASSWORD',
            'SITE_ID',
            'REST_ID',
            'CATALOG_ID'
        );
        ?><input type="hidden" name="SETUP_FIELDS_LIST" value="<? echo implode(',', $arFieldsString); ?>"><?
    }
    if ($STEP > 1)
    {
        ?><input type="submit" name="backButton" value="<< Назад"><?
    }

    $submitValue = ($STEP == 2)
        ? (($ACTION == "IMPORT") ? "Загрузить данные" : "Сохранить")
        : "Далее >>";
    ?><input type="submit" value="<? echo $submitValue; ?>" name="submit_btn"><?
}

$tabControl->End();
?></form>
<script>
<? if ($STEP < 2): ?>
    tabControl.SelectTab("edit1");
    tabControl.DisableTab("edit2");
    tabControl.DisableTab("edit3");
<? elseif ($STEP == 2): ?>
    tabControl.SelectTab("edit2");
    tabControl.DisableTab("edit1");
    tabControl.DisableTab("edit3");
<? elseif ($STEP == 3): ?>
    tabControl.SelectTab("edit3");
    tabControl.DisableTab("edit1");
    tabControl.DisableTab("edit2");
<? endif; ?>
</script>