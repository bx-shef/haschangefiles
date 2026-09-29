<?php declare(strict_types=1);

/**
 * Страница обновлений: кнопка «Правки ядра» и подтверждение перед установкой.
 *
 * Что держит:
 *
 * * кнопка и подтверждение — только на странице обновлений, только на GET и
 *   только администратору: main:OnAdminContextMenuShow зовётся на каждой
 *   странице административной части с контекстным меню;
 * * один раз за запрос, даже если на портале обе регистрации — прежняя, на
 *   класс 1.x, и новая: установщик снимает прежнюю по строке, а строки 1.x
 *   сверить не с чем;
 * * заглушка 1.x передаёт вызов новому обработчику, а не падает;
 * * тексты уходят в скрипт через json_encode. В 1.x они подставлялись в
 *   кавычки строкой: кавычка в переводе ломала скрипт, и кнопка «Установить
 *   обновления» переставала работать вовсе;
 * * скрипт — синтаксически верный JS (node --check), без кнопки не падает.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Context;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\UI\Extension;
use Shef\Haschangefiles\Integration\Main\Events;

Loc::loadLangFile($root.'/lang/ru/lib/integration/main/events.php');

$call = static function(string $page, bool $isAdmin, string $method = 'GET', string $handler = Events::class): array
{
	Events::reset();
	Asset::$strings = [];
	Extension::$loaded = [];
	Context::$requestedPage = $page;
	Context::$requestMethod = $method;
	CurrentUser::$isAdmin = $isAdmin;

	$items = [['TEXT' => 'ядро']];
	$handler::onAdminContextMenuShow($items);

	return $items;
};

Check::group('где показывается');

Check::same('другая страница — ничего', count($call('/bitrix/admin/index.php', true)), 1);
Check::same('не администратору — ничего', count($call(Events::UPDATE_PAGE, false)), 1);
Check::same('POST — ничего', count($call(Events::UPDATE_PAGE, true, 'POST')), 1);

$items = $call(Events::UPDATE_PAGE, true);
Check::same('страница обновлений — кнопка добавлена', count($items), 2);
Check::same('чужие пункты на месте', $items[0], ['TEXT' => 'ядро']);
Check::same('кнопка ведёт на отчёт', $items[1]['LINK'], '/bitrix/admin/shef_haschangefiles_list.php?lang=ru');
Check::same('в новой вкладке, без javascript:', [$items[1]['LINK_PARAM'], str_starts_with($items[1]['LINK'], 'javascript:')], ['target="_blank"', false]);
Check::same('подпись', $items[1]['TEXT'], 'Правки ядра');
Check::same('скрипт подтверждения добавлен', count(Asset::$strings), 1);
Check::same('диалог ядра подключён', Extension::$loaded, ['ui.dialogs.messagebox']);

Check::group('один раз за запрос');

$items = [];
Events::onAdminContextMenuShow($items);
\Shef\Haschangefiles\Integration\Shef\UiClear\Events::onAdminContextMenuShow($items);
Check::same('вторая регистрация ничего не добавляет', [count($items), count(Asset::$strings)], [0, 1]);

Check::group('заглушка 1.x');

$items = $call(Events::UPDATE_PAGE, true, 'GET', \Shef\Haschangefiles\Integration\Shef\UiClear\Events::class);
Check::same('передаёт вызов новому обработчику', count($items), 2);

$response = \Shef\Haschangefiles\Integration\Shef\UiClear\Events::onBitrixMenuExtInitTopPanelUserMenu(new \Bitrix\Main\Event());
Check::same('на shef.uiclear — «нечего добавить»', $response->getType(), \Bitrix\Main\EventResult::UNDEFINED);

Check::group('скрипт');

Loc::$messages['SH_HASCHANGEFILES_UPDATE_CONFIRM_TITLE'] = 'Кавычки " и \' и </script><script>alert(1)</script>';
$script = Events::getConfirmScript('ru');

Check::same('закрывающего тега из текста нет', substr_count($script, '</script>'), 1);
Check::same('кнопки ядра — те самые', [str_contains($script, "BX('install_updates_button')"), str_contains($script, "BX('install_updates_sel_button')")], [true, true]);
Check::same('без функции ядра кнопку не трогает', str_contains($script, "typeof install !== 'function'"), true);
Check::same('цвет подставлен', str_contains($script, Events::DANGER_COLOR), true);

$js = sys_get_temp_dir().'/shef-haschangefiles-events-'.getmypid().'.js';
file_put_contents($js, preg_replace('#^\s*<script>|</script>\s*$#', '', $script));

$node = trim((string)shell_exec('command -v node'));
if($node === '')
{
	Check::same('node нужен для проверки скрипта', false, true);
}
else
{
	exec(escapeshellarg($node).' --check '.escapeshellarg($js).' 2>&1', $output, $code);
	Check::same('скрипт — верный JS', [$code, implode(PHP_EOL, $output)], [0, '']);
}

unlink($js);

Check::finish();
