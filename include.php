<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
{
    die();
}

\Bitrix\Main\Loader::registerAutoLoadClasses('ugraweb.iiko', array(
    // tables
    '\Iiko\DB\ModifiersTable'        => 'lib/mysql/modifiers.php',
    '\Iiko\DB\ElementModifiersTable' => 'lib/mysql/modifiers.php',
    '\Iiko\DB\HashTable'             => 'lib/mysql/main.php',
    '\Iiko\DB\OrderTable'            => 'lib/mysql/order.php',

    // classes
    '\Iiko\App'                      => 'lib/main.php',
    '\Iiko\Event'                    => 'lib/main.php',
    '\Iiko\Connect'                  => 'lib/connect.php',

    '\Iiko\Import'                   => 'lib/import.php',
    '\Iiko\Export'                   => 'lib/export.php',
    '\Iiko\OrderProvider'            => 'lib/export.php',
    '\Iiko\IExportOrder'             => 'lib/export.php',

    '\Iiko\Modifiers'                => 'lib/modifiers.php',
    '\Iiko\ElementModifiers'         => 'lib/modifiers.php',

    '\Iiko\Config\Props'             => 'lib/option.php',
    '\Iiko\Config\Option'            => 'lib/option.php'
));