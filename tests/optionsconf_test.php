<?php declare(strict_types=1);

/**
 * Страница настроек: options_conf.php собирается против API shef.options 3.x.
 *
 * Что держит:
 *
 * * options_conf.php зовёт ShOptionsConfig так, как его понимает shef.options
 *   3.x. В 1.x он передавал indexDoc — параметр ушёл в 3.0.0, и страница
 *   настроек падала «Unknown named parameter». Ни php -l, ни остальные тесты
 *   этого не видели;
 * * у каждой подписи есть перевод: setName() и setTitle() принимают строку,
 *   и пропущенный ключ языкового файла — это TypeError, то есть снова
 *   неоткрывающаяся страница;
 * * со страницы настроек есть ссылка на отчёт;
 * * коды опций — те, что читает Constants: PRJ_name и PRJ_path.
 *
 * API shef.options подменяет tests/stub/options.php, файлы модуля настоящие.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/stub/options.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Localization\Loc;
use Shef\Options\Main\Options;
use Shef\Haschangefiles\Integration\Main\AdminMenu;

define('LANGUAGE_ID', 'ru');
Loc::loadLangFile($root.'/lang/ru/options.php');

Check::group('options_conf.php собирается');

$tabs = require $root.'/options_conf.php';

Check::same('вернул список вкладок', is_array($tabs), true);
Check::same('вкладка PhpStorm', array_map(static fn(Options\Tab $tab): string => $tab->getCode(), $tabs), ['PRJ']);
Check::same('у вкладки есть название', $tabs[0]->getName(), 'PhpStorm');

$options = [];
foreach($tabs[0]->getOptionList() as $option)
{
	$options[$option->getCode()] = $option;
}

Check::group('ссылка на отчёт');

$report = $options['Report'] ?? null;
Check::same('строка с отчётом есть', $report instanceof Options\RowInfo, true);
Check::same(
	'ведёт на страницу отчёта',
	str_contains((string)$report?->getDescription(), '[URL='.AdminMenu::getUrlList('ru').']'),
	true
);

Check::group('опции PhpStorm');

// Префикс вкладки + код опции = имя в b_option: PRJ_name и PRJ_path, их и
// читает Constants::getProjectSelfName() и getProjectSelfRootPath().
Check::same('имя проекта и корень — текстом', [
	($options['name'] ?? null) instanceof Options\Text,
	($options['path'] ?? null) instanceof Options\Text,
], [true, true]);

$constants = (string)file_get_contents($root.'/lib/main/constants.php');
Check::same('Constants читает именно их', [str_contains($constants, "'PRJ_name'"), str_contains($constants, "'PRJ_path'")], [true, true]);

$untitled = array_values(array_filter(
	['name', 'path'],
	static fn(string $code): bool => ($options[$code] ?? null)?->getTitle() === ''
));
Check::same('у каждой опции есть подпись', $untitled, []);
Check::same('предупреждение про Toolbox есть', ($options['WARNING_PRJ'] ?? null)?->getDescription() !== '', true);

Check::finish();
