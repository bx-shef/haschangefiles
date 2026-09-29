<?php declare(strict_types=1);

/**
 * Отчёт «Правки ядра»: что обходится и как выглядит строка.
 *
 * Главное здесь — экранирование. Имя файла и путь попадают в ячейку грида
 * готовым HTML, а в корень сайта файлы кладут не только разработчики: файл
 * `has-change_<img onerror=...>.php` в /upload или в публичной части — это
 * скрипт на странице администратора. До 2.0.0 имя и путь подставлялись в
 * разметку как есть.
 *
 * И ссылка в PhpStorm: путь с пробелом или «&» без кодирования открыл бы не
 * тот файл.
 *
 * Подписи — из настоящего языкового файла: пропущенный ключ был бы пустой
 * ячейкой статуса.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/autoload.php';
require_once $root.'/tests/assert.php';

use Bitrix\Main\Localization\Loc;
use Shef\Haschangefiles\Main\ChangeFile;
use Shef\Haschangefiles\Main\Report;
use Shef\Haschangefiles\Main\Status;

Loc::loadLangFile($root.'/lang/ru/lib/main/report.php');

$site = sys_get_temp_dir().'/shef-haschangefiles-report-'.getmypid();

Check::group('разделы');

$sections = Report::getSections($site.'/');
Check::same(
	'обход — тот же, что у консоли (Scanner::getScope)',
	array_map(static fn(array $section): array => ['dir' => $section['dir'], 'skip' => $section['skip']], $sections),
	array_values(\Shef\Haschangefiles\Main\Scanner::getScope($site))
);
Check::same('два раздела', array_column($sections, 'code'), ['PUBLIC', 'CORE']);
Check::same(
	'публичная часть — без ядра, данных и кода проекта',
	$sections[0]['skip'],
	[$site.'/bitrix', $site.'/upload', $site.'/local', $site.'/images']
);
Check::same('ядро — /bitrix', $sections[1]['dir'], $site.'/bitrix');
Check::same('у разделов есть названия', array_filter(array_column($sections, 'title'), static fn(string $t): bool => $t === ''), []);

Check::group('колонки и подписи');

Check::same('колонок пять', array_column(Report::getColumns(), 'id'), ['NUM', 'STATUS', 'FILE', 'ORI', 'CHG']);
Check::same('у колонок есть названия', array_filter(array_column(Report::getColumns(), 'name'), static fn(string $t): bool => $t === ''), []);

$missing = [];
foreach(Status::cases() as $status)
{
	$code = 'SH_HASCHANGEFILES_STATUS_'.str_replace('-', '_', $status->value);
	if(null === Loc::getMessage($code))
	{
		$missing[] = $code;
	}
}
Check::same('у каждого состояния есть подпись', $missing, []);

Check::group('строка: экранирование');

$evil = '<img src=x onerror=alert(1)>.php';
$evilDir = '<svg onload=1>';
mkdir($site.'/crm/'.$evilDir, 0777, true);
file_put_contents($site.'/crm/'.$evilDir.'/'.$evil, "// change ////\n");
file_put_contents($site.'/crm/'.$evilDir.'/has-change_'.$evil, "// change ////\n");

$row = Report::getRow(new ChangeFile($site.'/crm/'.$evilDir.'/has-change_'.$evil), 1, $site, 'proj', '/www');
$html = implode('', array_map('strval', $row['columns']));

Check::same('разметки из имени файла нет', str_contains($html, '<img'), false);
Check::same('разметки из пути нет', str_contains($html, '<svg'), false);
Check::same(
	'ячейка файла целиком',
	$row['columns']['FILE'],
	'<b>&lt;img src=x onerror=alert(1)&gt;.php</b><br>/crm/&lt;svg onload=1&gt;'
);
Check::same('имя видно — экранированным', str_contains($row['columns']['FILE'], '&lt;img src=x onerror=alert(1)&gt;.php'), true);
Check::same('состояние — «на месте»', str_contains($row['columns']['STATUS'], 'На месте'), true);
Check::same('атрибут строки — код состояния', $row['attrs'], ['data-sh-status' => 'OK']);

Check::group('строка: файла нет');

mkdir($site.'/bitrix/js/ui', 0777, true);
file_put_contents($site.'/bitrix/js/ui/has-change_gone.js', "// change ////\n");
$row = Report::getRow(new ChangeFile($site.'/bitrix/js/ui/has-change_gone.js'), 2, $site);
Check::same('состояние — «нет файла»', str_contains($row['columns']['STATUS'], 'Нет файла'), true);
Check::same('ячейка оригинала — «нет файла»', $row['columns']['ORI'], 'нет файла');
Check::same('без проекта PhpStorm — без ссылки', str_contains($row['columns']['CHG'], '<a '), false);
Check::same('модуль в пути выделен', str_contains($row['columns']['FILE'], '/js/<b class="sh-hcf-upper">ui</b>'), true);

Check::group('ссылка в PhpStorm');

Check::same(
	'параметры закодированы',
	Report::getUrlPhpStorm('my proj', '/www/', '/bitrix/a&b c.php'),
	'jetbrains://php-storm/navigate/reference?project=my%20proj&path=%2Fwww%2Fbitrix%2Fa%26b%20c.php'
);

$row = Report::getRow(new ChangeFile($site.'/bitrix/js/ui/has-change_gone.js'), 2, $site, 'p"q', '');
Check::same(
	'в атрибуте — экранированной',
	str_contains($row['columns']['CHG'], 'href="jetbrains://php-storm/navigate/reference?project=p%22q&amp;path=%2Fbitrix%2Fjs%2Fui%2Fhas-change_gone.js"'),
	true
);

Check::group('пути');

Check::same('от корня сайта', Report::getRelativePath('/www/bitrix/a.php', '/www'), '/bitrix/a.php');
Check::same('сосед корня с тем же началом — как есть', Report::getRelativePath('/www-old/a.php', '/www'), '/www-old/a.php');
Check::same(
	'выделяется первый модуль',
	Report::makePrettyPath('/bitrix/modules/crm/lib/modules/x'),
	'/bitrix/modules/<b class="sh-hcf-upper">crm</b>/lib/modules/x'
);

// region Уборка ////
exec('rm -rf '.escapeshellarg($site));
// endregion ////

Check::finish();
