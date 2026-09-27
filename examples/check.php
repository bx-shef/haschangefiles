<?php declare(strict_types=1);

/**
 * Цикл правки ядра: пометить, сохранить зеркало, пережить обновление.
 *
 * ЦЕЛЬ
 *   Показать весь путь правки так, как его видит модуль: правка с пометкой
 *   «// change», копия has-change_<имя> рядом, обновление перезаписывает
 *   файл — модуль говорит «восстановить», правку вернули и обновили копию —
 *   снова «на месте».
 *
 * ГДЕ ПРИМЕНЯТЬ
 *   Свой скрипт проверки после обновления, шаг деплоя, агент-сторож: где
 *   нужно не страницу глазами, а ответ кодом. Классы Scanner и ChangeFile —
 *   те же, что у страницы «Правки ядра» и у cli/check-core-changes.php.
 *
 * ЧТО ДОЛЖНО ПОЛУЧИТЬСЯ
 *   Все строки «ok», последняя — «ГОТОВО: check», код возврата 0.
 *   По сути:
 *     * помеченная правка с зеркалом — «на месте» (OK);
 *     * после «обновления» — DRIFT, хотя размер файла не изменился;
 *     * правку вернули и пересохранили зеркало — снова OK;
 *     * правка без пометки — NO-MARKERS, зеркало без файла — NO-ORIGINAL.
 *
 * ЗАПУСК
 *   php examples/check.php
 *   DOCUMENT_ROOT=/var/www/portal php examples/check.php
 *
 * НА ПОРТАЛЕ ОТЛИЧАЕТСЯ
 *   Ничем: файлы примера лежат во временном каталоге системы, не на сайте,
 *   и пример их удаляет. Классы на портале даёт автозагрузка модуля.
 */

require_once __DIR__.'/_bootstrap.php';

title('Цикл правки ядра');

use Shef\Haschangefiles\Main\ChangeFile;
use Shef\Haschangefiles\Main\Scanner;
use Shef\Haschangefiles\Main\Status;

$site = sandbox('check');
$file = $site.'/bitrix/modules/crm/lib/order.php';
$mirror = $site.'/bitrix/modules/crm/lib/has-change_order.php';

step('1. Правка с пометкой и зеркало');

$edited = <<<'PHP'
<?php
function orderTitle(array $item): string
{
	// change ////
	// return $item['CODE']; ////
	return sprintf('[%s] %s', $item['ID'], $item['CODE']);
	// change stop ////
}
PHP;

put($file, $edited);
copy($file, $mirror);

check('зеркало нашлось', Scanner::find($site), [$mirror]);
check('правка на месте', (new ChangeFile($mirror))->getStatus(), Status::Ok);

step('2. Обновление перезаписало файл');

// Та же длина, другой код: до 2.0.0 модуль сравнивал только размер и
// такое обновление не замечал.
put($file, str_replace("sprintf('[%s] %s'", "sprintf('(%s) %s'", $edited));

$change = new ChangeFile($mirror);
check('размер не изменился', $change->getOriginalSize(), $change->getMirrorSize());
check('модуль видит расхождение', $change->getStatus(), Status::Drift);
check('восстановить', $change->isNeedFix(), true);

step('3. Правку вернули, зеркало пересохранили');

put($file, $edited);
copy($file, $mirror);
check('снова на месте', (new ChangeFile($mirror))->getStatus(), Status::Ok);

step('4. Чего модуль не прощает');

put($site.'/bitrix/templates/main/header.php', "<?php\n\$title = 'x';\n");
put($site.'/bitrix/templates/main/has-change_header.php', "<?php\n\$title = 'x';\n");
check(
	'правка без «// change» — не найти глазами',
	(new ChangeFile($site.'/bitrix/templates/main/has-change_header.php'))->getStatus(),
	Status::NoMarkers
);

put($site.'/bitrix/js/ui/has-change_old.js', "// change ////\n");
check(
	'зеркало есть, файла нет — ядро его убрало',
	(new ChangeFile($site.'/bitrix/js/ui/has-change_old.js'))->getStatus(),
	Status::NoOriginal
);

$toFix = array_values(array_filter(
	array_map(static fn(string $path): ChangeFile => new ChangeFile($path), Scanner::find($site)),
	static fn(ChangeFile $change): bool => $change->isNeedFix()
));
check('итого восстановить', count($toFix), 2);

done('check');
