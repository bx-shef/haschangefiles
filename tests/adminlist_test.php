<?php declare(strict_types=1);

/**
 * Страница отчёта admin/list.php — подключается настоящая.
 *
 * Что держит:
 *
 * * не администратору — форма входа, и дальше страница не идёт: обход сайта
 *   и грид не строятся. В 1.x отчёт был публичной страницей без проверки
 *   прав;
 * * два раздела — два грида с разными GRID_ID: в 1.x оба брали один, и
 *   настройки одного доставались другому;
 * * в грид уходят строки Report::getRow() — экранированные, по обходу
 *   Scanner::getScope();
 * * заголовок раздела экранирован.
 *
 * Ядро — заглушки: пролог и эпилог административной части — пустые файлы
 * в песочнице, $APPLICATION запоминает, что у него просили.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Localization\Loc;

\Bitrix\Main\Config\Configuration::$settings = require $root.'/.settings.php';
Loc::loadLangFile($root.'/lang/ru/lib/main/report.php');

// region Окружение страницы ////
define('LANGUAGE_ID', 'ru');

final class AuthFormShown extends RuntimeException {}

class CUser
{
	public function __construct(private readonly bool $isAdmin) {}

	public function IsAdmin(): bool
	{
		return $this->isAdmin;
	}
}

class CMain
{
	public array $grids = [];
	public string $title = '';

	/** В ядре AuthForm() заканчивается die(): здесь — исключением. */
	public function AuthForm(mixed $message): never
	{
		throw new AuthFormShown((string)$message);
	}

	public function SetTitle(mixed $title): void
	{
		$this->title = (string)$title;
	}

	public function IncludeComponent(string $name, string $template, array $params, mixed $parent = null, array $options = []): void
	{
		$this->grids[] = ['name' => $name, 'params' => $params];
	}
}

class CAdminMessage
{
	public static function ShowMessage(mixed $message): void {}
}

$site = sys_get_temp_dir().'/shef-haschangefiles-adminlist-'.getmypid();
foreach(['prolog_admin_before', 'prolog_admin_after', 'epilog_admin'] as $file)
{
	@mkdir($site.'/bitrix/modules/main/include', 0777, true);
	file_put_contents($site.'/bitrix/modules/main/include/'.$file.'.php', '<?php');
}
$_SERVER['DOCUMENT_ROOT'] = $site;
\Bitrix\Main\Application::$documentRoot = $site;

$put = static function(string $path, string $content) use ($site): void
{
	@mkdir(dirname($site.$path), 0777, true);
	file_put_contents($site.$path, $content);
};

$edited = "<?php\n// change ////\n";
$put('/bitrix/modules/crm/a.php', $edited);
$put('/bitrix/modules/crm/has-change_a.php', $edited);
$put('/crm/<img src=x>.php', $edited);
$put('/crm/has-change_<img src=x>.php', "<?php\n");

/** Открыть страницу пользователем $user; вернуть вывод и $APPLICATION. */
$open = static function(CUser $user) use ($root): array
{
	$GLOBALS['USER'] = $user;
	$GLOBALS['APPLICATION'] = new CMain();

	ob_start();
	$denied = false;
	try
	{
		(static function() use ($root): void
		{
			global $APPLICATION, $USER;
			require $root.'/admin/list.php';
		})();
	}
	catch(AuthFormShown)
	{
		$denied = true;
	}

	return ['html' => (string)ob_get_clean(), 'app' => $GLOBALS['APPLICATION'], 'denied' => $denied];
};
// endregion ////

Check::group('права');

$page = $open(new CUser(false));
Check::same('не администратору — форма входа', $page['denied'], true);
Check::same('…грида нет', $page['app']->grids, []);
Check::same('…вывода отчёта нет', $page['html'], '');

Check::group('администратору');

$page = $open(new CUser(true));
Check::same('формы входа нет', $page['denied'], false);
Check::same('два грида', count($page['app']->grids), 2);
Check::same('оба — main.ui.grid', array_column($page['app']->grids, 'name'), ['bitrix:main.ui.grid', 'bitrix:main.ui.grid']);

$ids = array_map(static fn(array $grid): string => $grid['params']['GRID_ID'], $page['app']->grids);
Check::same('GRID_ID разные', count(array_unique($ids)), 2);

$rows = array_map(static fn(array $grid): array => $grid['params']['ROWS'], $page['app']->grids);
Check::same('публичная часть — одна правка', count($rows[0]), 1);
Check::same('ядро — одна правка', count($rows[1]), 1);
Check::same('счётчик строк сходится', $page['app']->grids[1]['params']['TOTAL_ROWS_COUNT'], 1);
Check::same('строка — от Report::getRow, экранирована', str_contains($rows[0][0]['columns']['FILE'], '&lt;img src=x&gt;.php'), true);
Check::same('в HTML страницы разметки из имён нет', str_contains($page['html'], '<img'), false);
Check::same('заголовки разделов есть', [str_contains($page['html'], 'Публичная часть'), str_contains($page['html'], 'Ядро Битрикса')], [true, true]);

// region Уборка ////
exec('rm -rf '.escapeshellarg($site));
// endregion ////

Check::finish();
