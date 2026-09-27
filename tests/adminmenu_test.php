<?php declare(strict_types=1);

/**
 * Меню «Правки ядра» в административной части.
 *
 * Заменяет пункт, который до 2.0.0 вешался на верхнюю панель через
 * shef.uiclear. Что держит тест:
 *
 * * admin/menu.php отдаёт меню только администратору: в отчёте пути к
 *   файлам ядра;
 * * отчёт открывается страницей модуля /bitrix/admin/shef_haschangefiles_list.php,
 *   а не публичной /local/admin/... из 1.x — та прав не проверяла;
 * * на портале, обновлённом заменой файлов, меню само кладёт недостающую
 *   страницу — и не трогает чужой файл на её месте.
 *
 * admin/menu.php подключается настоящий: ядро зовёт именно его.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Localization\Loc;
use Shef\Haschangefiles\Integration\Main\AdminMenu;
use Shef\Haschangefiles\Main\AdminPage;

\Bitrix\Main\Config\Configuration::$settings = require $root.'/.settings.php';
Loc::loadLangFile($root.'/lang/ru/lib/integration/main/adminmenu.php');

// region Заглушки окружения admin/menu.php ////
define('B_PROLOG_INCLUDED', true);
define('LANGUAGE_ID', 'ru');

class CUser
{
	public function __construct(private readonly bool $isAdmin) {}

	public function IsAdmin(): bool
	{
		return $this->isAdmin;
	}
}

/** Подключить admin/menu.php так, как это делает ядро. */
$includeMenu = static function(?CUser $user) use ($root): mixed
{
	$GLOBALS['USER'] = $user;

	return include $root.'/admin/menu.php';
};
// endregion ////

$portal = sys_get_temp_dir().'/shef-haschangefiles-menu-'.getmypid();
mkdir($portal.'/www/bitrix/admin', 0777, true);
\Bitrix\Main\Application::$documentRoot = $portal.'/www';
$target = $portal.'/www'.AdminMenu::LIST_PAGE;

Check::group('admin/menu.php: права');

Check::same('без пользователя — меню нет', $includeMenu(null), false);
Check::same('не администратору — меню нет', $includeMenu(new CUser(false)), false);
Check::same('…и страницу не кладёт', is_file($target), false);

$menu = $includeMenu(new CUser(true));
Check::same('администратору — раздел', is_array($menu), true);
Check::same('раздел в «Настройках»', $menu['parent_menu'] ?? null, 'global_menu_settings');
Check::same('у раздела есть название', $menu['text'] ?? '', 'Правки ядра');

Check::group('пункты');

Check::same(
	'отчёт — страницей модуля',
	$menu['items'][0]['url'],
	'/bitrix/admin/shef_haschangefiles_list.php?lang=ru'
);
Check::same('страница в меню — та, что пишет AdminPage', AdminMenu::LIST_PAGE, '/bitrix/admin/'.AdminPage::FILE);
Check::same(
	'настройки',
	$menu['items'][1]['url'],
	'/bitrix/admin/settings.php?lang=ru&mid=shef.haschangefiles'
);
Check::same(
	'ни одной ссылки в /local',
	array_filter(array_column($menu['items'], 'url'), static fn(string $url): bool => str_contains($url, '/local/')),
	[]
);

Check::group('страница отчёта на портале, обновлённом заменой файлов');

// Установщик на таком портале не запускался — страницы нет. Меню её пишет —
// с путём туда, где модуль стоит. Подробно заглушку держит adminpage_test.php.
Check::same('admin/menu.php положил страницу сам', is_file($target), true);
Check::same('ведёт в этот модуль', (string)file_get_contents($target), AdminPage::getContent($portal.'/www', $root));

file_put_contents($target, 'своя версия проекта');
Check::same(
	'чужой файл — не трогает',
	[AdminMenu::ensureListPage($portal.'/www', $root), (string)file_get_contents($target)],
	[false, 'своя версия проекта']
);
Check::same('…а меню всё равно строится', is_array($includeMenu(new CUser(true))), true);

\Bitrix\Main\IO\Directory::deleteDirectory($portal);
\Bitrix\Main\Application::$documentRoot = '';

Check::finish();
