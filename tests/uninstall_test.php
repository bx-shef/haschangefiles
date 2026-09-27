<?php declare(strict_types=1);

/**
 * Установка и удаление: своё уносим, чужое не трогаем, прибираем за 1.x.
 *
 * * Настройки уходят вместе с модулем (решение владельца в shef.options):
 *   иначе повторная установка молча поднимает прежние значения. Проверяются
 *   обе стороны — Option::delete() работает по модулю, и ошибка в
 *   идентификаторе унесла бы настройки соседа.
 * * savedata = Y оставляет настройки — уговор ядра.
 * * Обе регистрации 1.x снимаются — на событие shef.uiclear и
 *   OnAdminContextMenuShow на прежний класс: замена файлов их не снимает, а
 *   в installEvents их больше нет.
 * * Каталоги 1.x в /local (страница без проверки прав и компонент) и
 *   скриншоты в /bitrix/images убираются и при установке, и при удалении.
 * * Страница отчёта в /bitrix/admin пишется из того каталога, где модуль
 *   стоит на самом деле, удаляется — только своя.
 *
 * Ядро подменяется заглушками, установщик подключается настоящий.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;

// region Заглушка ядра ////
class CoreCalls
{
	/** @var list<string> */
	public static array $unregistered = [];

	public static int $cacheCleaned = 0;

	/** @var list<array{0: string, 1: string}> что установщик копировал */
	public static array $copied = [];

	public static function reset(): void
	{
		static::$unregistered = [];
		static::$cacheCleaned = 0;
		static::$copied = [];
		EventManager::$unregistered = [];
		EventManager::$registered = [];
	}
}

if(!class_exists('CModule'))
{
	class CModule
	{
	}
}

function IsModuleInstalled(string $moduleId): bool
{
	return false;
}

function UnRegisterModule(string $moduleId): void
{
	CoreCalls::$unregistered[] = $moduleId;
}

function RegisterModule(string $moduleId): void
{
}

/** Копирование каталогов ядра: запоминаем откуда и куда. */
function CopyDirFiles(string $from, string $to, bool $rewrite = true, bool $recursive = false): bool
{
	CoreCalls::$copied[] = [$from, $to];
	return true;
}

$GLOBALS['APPLICATION'] = new class
{
	public function ThrowException(string $message): void {}
};

$GLOBALS['CACHE_MANAGER'] = new class
{
	public function CleanAll(): void
	{
		CoreCalls::$cacheCleaned++;
	}
};

// Установщик читает installEvents из настоящего .settings.php.
\Bitrix\Main\Config\Configuration::$settings = require $root.'/.settings.php';
// endregion ////

require_once $root.'/install/index.php';

const NEIGHBOUR = 'shef.options';

$given = static function(): shef_haschangefiles
{
	CoreCalls::reset();
	Option::$values = [];

	Option::set('shef.haschangefiles', 'PRJ_name', 'portal');
	Option::set('shef.haschangefiles', 'PRJ_path', '/www');
	Option::set(NEIGHBOUR, 'DEF_systemuserid', '9');

	return new shef_haschangefiles();
};

Check::group('удаление уносит настройки модуля');

$module = $given();
$module->UnInstallDB();

Check::same('идентификатор модуля тот самый', $module->MODULE_ID, 'shef.haschangefiles');
Check::same('настройка стёрта', Option::get('shef.haschangefiles', 'PRJ_name', 'нет'), 'нет');
Check::same('вторая тоже', Option::get('shef.haschangefiles', 'PRJ_path', 'нет'), 'нет');
Check::same('модуль снят с регистрации', CoreCalls::$unregistered, ['shef.haschangefiles']);
Check::same('кеш сброшен', CoreCalls::$cacheCleaned, 1);

Check::group('чужое не трогаем');

Check::same('настройка shef.options на месте', Option::get(NEIGHBOUR, 'DEF_systemuserid', 'нет'), '9');

Check::group('savedata');

$module = $given();
$module->UnInstallDB(['savedata' => 'Y']);
Check::same('savedata = Y оставляет настройки', Option::get('shef.haschangefiles', 'PRJ_name', 'нет'), 'portal');

$module = $given();
$module->UnInstallDB(['savedata' => 'N']);
Check::same('savedata = N стирает', Option::get('shef.haschangefiles', 'PRJ_name', 'нет'), 'нет');

Check::group('обработчики: ставится новый, снимаются все, включая 1.x');

$module = $given();
$module->InstallEvents();

$describe = static fn(array $call): string => $call[0].':'.$call[1].' -> '.ltrim($call[3], '\\').'::'.$call[4];

Check::same(
	'ставится один — на класс 2.x',
	array_map($describe, EventManager::$registered),
	['main:OnAdminContextMenuShow -> Shef\\Haschangefiles\\Integration\\Main\\Events::onAdminContextMenuShow']
);

$module = $given();
$module->UnInstallEvents();

$unregistered = array_map($describe, EventManager::$unregistered);

Check::same('снято ровно три обработчика', count($unregistered), 3);
Check::same(
	'свой OnAdminContextMenuShow',
	in_array('main:OnAdminContextMenuShow -> Shef\\Haschangefiles\\Integration\\Main\\Events::onAdminContextMenuShow', $unregistered, true),
	true
);
Check::same(
	'OnAdminContextMenuShow на класс 1.x',
	in_array('main:OnAdminContextMenuShow -> Shef\\Haschangefiles\\Integration\\Shef\\UiClear\\Events::onAdminContextMenuShow', $unregistered, true),
	true
);
Check::same(
	'обработчик shef.uiclear из 1.x',
	in_array('shef.uiclear:onBitrixMenuExtInitTopPanelUserMenu -> Shef\\Haschangefiles\\Integration\\Shef\\UiClear\\Events::onBitrixMenuExtInitTopPanelUserMenu', $unregistered, true),
	true
);

Check::group('установка файлов');

$portal = sys_get_temp_dir().'/shef-haschangefiles-uninstall-'.getmypid();
\Bitrix\Main\Application::$documentRoot = $portal.'/www';

$touch = static function(string $path, string $content = 'x'): void
{
	if(!is_dir(dirname($path)))
	{
		mkdir(dirname($path), 0777, true);
	}
	file_put_contents($path, $content);
};

// Портал, обновлённый с 1.x заменой файлов.
$touch($portal.'/www/bitrix/admin/settings.php');
$touch($portal.'/www/bitrix/images/shef.haschangefiles/docs/sf1.png');
$touch($portal.'/www/local/admin/shef.haschangefiles/list.php');
$touch($portal.'/www/local/components/shef.haschangefiles/list/class.php');
$touch($portal.'/www/local/components/shef.options/foo/class.php');
$touch($portal.'/www/local/admin/other/page.php');

$module = $given();
Check::same('InstallFiles отработал', $module->InstallFiles(), true);
Check::same('копировать нечего', CoreCalls::$copied, []);

$listPage = $portal.'/www/bitrix/admin/'.\Shef\Haschangefiles\Main\AdminPage::FILE;
Check::same(
	'страница отчёта ведёт в этот модуль',
	(string)@file_get_contents($listPage),
	\Shef\Haschangefiles\Main\AdminPage::getContent($portal.'/www', $root)
);
Check::same('страница 1.x в /local убрана', is_dir($portal.'/www/local/admin/shef.haschangefiles'), false);
Check::same('компонент 1.x убран', is_dir($portal.'/www/local/components/shef.haschangefiles'), false);
Check::same('скриншоты 1.x убраны', is_dir($portal.'/www/bitrix/images/shef.haschangefiles'), false);
Check::same('чужой компонент на месте', is_file($portal.'/www/local/components/shef.options/foo/class.php'), true);
Check::same('чужая страница на месте', is_file($portal.'/www/local/admin/other/page.php'), true);

Check::group('удаление файлов');

$touch($portal.'/www/local/admin/shef.haschangefiles/list.php');

$module = $given();
Check::same('UnInstallFiles отработал', $module->UnInstallFiles(), true);
Check::same('своя страница отчёта убрана', is_file($listPage), false);
Check::same('страницы ядра в /bitrix/admin на месте', is_file($portal.'/www/bitrix/admin/settings.php'), true);
Check::same('страница 1.x в /local убрана', is_dir($portal.'/www/local/admin/shef.haschangefiles'), false);
Check::same('чужое в /local на месте', [is_file($portal.'/www/local/components/shef.options/foo/class.php'), is_file($portal.'/www/local/admin/other/page.php')], [true, true]);

// Проект положил на место страницы свой файл — удаление его не трогает.
$touch($listPage, '<?php // своя страница проекта');
$module = $given();
$module->UnInstallFiles();
Check::same('чужой файл на месте страницы не удалён', (string)file_get_contents($listPage), '<?php // своя страница проекта');

\Bitrix\Main\IO\Directory::deleteDirectory($portal);

Check::finish();
