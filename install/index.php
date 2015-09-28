<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
{
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

if (class_exists('ugraweb_iiko'))
{
    return;
}

class ugraweb_iiko extends CModule
{
    /** @var string */
    var $MODULE_ID = 'ugraweb.iiko';

    /** @var string */
    public $MODULE_VERSION;

    /** @var string */
    public $MODULE_VERSION_DATE;

    /** @var string */
    public $MODULE_NAME;

    /** @var string */
    public $MODULE_DESCRIPTION;

    /** @var string */
    public $MODULE_GROUP_RIGHTS;

    /** @var string */
    public $PARTNER_NAME;

    /** @var string */
    public $PARTNER_URI;

    public function __construct()
    {
        /** @var array $arModuleVersion */
        include(dirname(__FILE__)."/version.php");
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];;
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];;

        $this->MODULE_NAME = Loc::getMessage('MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('MODULE_DESCRIPTION');

        $this->PARTNER_NAME = Loc::getMessage('PARTNER_NAME');
        $this->PARTNER_URI = "http://ugraweb.ru";
    }

    public function doInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);

        $this->InstallFiles();
        $this->InstallDB();

        RegisterModuleDependences('iblock', 'OnBeforeIBlockElementDelete', $this->MODULE_ID, '\Iiko\Event', 'onIBlockElementDelete');
    }

    public function doUninstall()
    {
        ModuleManager::unregisterModule($this->MODULE_ID);
        $this->UnInstallFiles();
        $this->uninstallDB();

        UnRegisterModuleDependences('iblock', 'OnBeforeIBlockElementDelete', $this->MODULE_ID, '\Iiko\Event', 'onIBlockElementDelete');
    }
    
    function InstallFiles($arParams = array())
    {
        CopyDirFiles(
            $_SERVER["DOCUMENT_ROOT"]."/local/modules/ugraweb.iiko/install/catalog_import/",
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/php_interface/include/catalog_import",
            true, true
        );
        return true;
    }

    function UnInstallFiles()
    {
        DeleteDirFiles(
            $_SERVER["DOCUMENT_ROOT"]."/local/modules/ugraweb.iiko/install/catalog_import/",
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/php_interface/include/catalog_import"
        );
        return true;
    }

    function installDB()
    {
        if (Loader::includeModule($this->MODULE_ID))
        {
            \Iiko\DB\ModifiersTable::getEntity()->createDbTable();
            \Iiko\DB\ElementModifiersTable::getEntity()->createDbTable();
            \Iiko\DB\HashTable::getEntity()->createDbTable();
        }
    }

    function uninstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID))
        {
            
        }
    }
}
