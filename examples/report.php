<?php declare(strict_types=1);

/**
 * Строка отчёта «Правки ядра» и ссылка «открыть в PhpStorm».
 *
 * ЦЕЛЬ
 *   Показать, из чего страница модуля собирает строку грида: состояние,
 *   файл с выделенным модулем, даты и размеры обоих файлов, ссылка в
 *   PhpStorm через JetBrains Toolbox. И что всё пришедшее с диска в ней
 *   экранировано.
 *
 * ГДЕ ПРИМЕНЯТЬ
 *   Свой отчёт о правках — письмо после обновления, виджет, страница в
 *   публичной части: Report::getRow() отдаёт готовые ячейки main.ui.grid.
 *   Настройки PhpStorm (имя проекта, корень сайта в нём) — на странице
 *   настроек модуля, их читает Constants.
 *
 * ЧТО ДОЛЖНО ПОЛУЧИТЬСЯ
 *   Все строки «ok», последняя — «ГОТОВО: report», код возврата 0.
 *   По сути:
 *     * имя файла с разметкой приходит в ячейку экранированным;
 *     * путь для PhpStorm — корень сайта в проекте плюс путь от корня
 *       сайта, параметры закодированы;
 *     * у каждой строки свой id, стабильный между показами.
 *
 * ЗАПУСК
 *   php examples/report.php
 *   DOCUMENT_ROOT=/var/www/portal php examples/report.php
 *
 * НА ПОРТАЛЕ ОТЛИЧАЕТСЯ
 *   Подписи состояний — переводом из языкового файла, а не кодом сообщения.
 *   Файлы примера — во временном каталоге системы, не на сайте.
 */

require_once __DIR__.'/_bootstrap.php';

title('Строка отчёта «Правки ядра»');

use Shef\Haschangefiles\Main\ChangeFile;
use Shef\Haschangefiles\Main\Report;

$site = sandbox('report');

step('Правка в шаблоне компонента');

$mirror = $site.'/bitrix/components/bitrix/news.list/templates/.default/has-change_template.php';
put(dirname($mirror).'/template.php', "<?php // change ////\n");
copy(dirname($mirror).'/template.php', $mirror);

$row = Report::getRow(new ChangeFile($mirror), 1, $site, 'portal', '/www');

note('FILE: '.$row['columns']['FILE']);
check('компонент выделен', str_contains($row['columns']['FILE'], '/components/<b class="sh-hcf-upper">bitrix</b>'), true);
check('состояние — в атрибуте строки', $row['attrs']['data-sh-status'], 'OK');
check(
	'ссылка в PhpStorm',
	str_contains($row['columns']['ORI'], 'project=portal&amp;path=%2Fwww%2Fbitrix%2Fcomponents%2Fbitrix%2Fnews.list%2Ftemplates%2F.default%2Ftemplate.php'),
	true
);
check('id строки стабилен', $row['id'], Report::getRow(new ChangeFile($mirror), 1, $site)['id']);

step('Имя файла с разметкой');

$evil = $site.'/crm/has-change_<img src=x onerror=alert(1)>.php';
put($evil, "// change ////\n");

$row = Report::getRow(new ChangeFile($evil), 2, $site);
note('FILE: '.$row['columns']['FILE']);
check('тега в ячейке нет', str_contains($row['columns']['FILE'], '<img'), false);
check('имя видно', str_contains($row['columns']['FILE'], '&lt;img src=x onerror=alert(1)&gt;.php'), true);

done('report');
