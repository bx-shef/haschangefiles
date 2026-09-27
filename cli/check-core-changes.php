<?php declare(strict_types=1);

/**
 * Проверка правок ядра из консоли: то же, что страница «Правки ядра», но
 * кодом возврата — для CI и для шага после обновления Битрикса.
 *
 * Обходит сайт, для каждого зеркала has-change_<файл> сравнивает его с
 * оригиналом рядом и печатает состояние:
 *
 *   OK           правка на месте
 *   DRIFT        оригинал разошёлся с зеркалом — правку вернуть
 *   NO-MARKERS   совпадают, но в файле нет пометки «// change»
 *   NO-ORIGINAL  оригинала нет
 *
 * Запуск (из каталога модуля или откуда угодно):
 *
 *   php cli/check-core-changes.php                  # весь сайт
 *   BASE_DIR=/bitrix php cli/check-core-changes.php # только ядро
 *   php cli/check-core-changes.php --diff           # плюс diff -u по DRIFT
 *
 * Корень сайта — из DOCUMENT_ROOT, а без него — на четыре уровня выше этого
 * файла: <корень>/bitrix/modules/shef.haschangefiles/cli или
 * <корень>/local/modules/shef.haschangefiles/cli.
 *
 * Код возврата: 0 — всё на месте; 1 — есть что восстановить; 2 — не нашёлся
 * корень сайта. Скрипт ничего не меняет. Ядро Битрикса и базу не поднимает —
 * сравнивает только файлы, поэтому работает и на копии сайта без портала.
 */

if(PHP_SAPI !== 'cli')
{
	http_response_code(403);
	die('Forbidden: CLI only');
}

// Ядро не поднимаем, поэтому и автозагрузки нет: классы — явным require.
require_once dirname(__DIR__).'/lib/main/status.php';
require_once dirname(__DIR__).'/lib/main/utils.php';
require_once dirname(__DIR__).'/lib/main/changefile.php';
require_once dirname(__DIR__).'/lib/main/scanner.php';

use Shef\Haschangefiles\Main\ChangeFile;
use Shef\Haschangefiles\Main\Scanner;
use Shef\Haschangefiles\Main\Status;

$documentRoot = trim((string)getenv('DOCUMENT_ROOT'));
$root = realpath($documentRoot !== '' ? $documentRoot : dirname(__DIR__, 4));
if($root === false || !is_dir($root))
{
	fwrite(STDERR, 'Не найден корень сайта: задайте DOCUMENT_ROOT'.PHP_EOL);
	exit(2);
}

$showDiff = in_array('--diff', $argv, true);

$baseRel = trim((string)getenv('BASE_DIR'));
$baseDir = $baseRel !== '' ? $root.'/'.trim($baseRel, '/') : $root;
if(!is_dir($baseDir))
{
	fwrite(STDERR, 'Нет каталога BASE_DIR: '.$baseDir.PHP_EOL);
	exit(2);
}

$found = 0;
$toFix = 0;

foreach(Scanner::find($baseDir) as $mirror)
{
	$found++;
	$file = new ChangeFile($mirror);
	$status = $file->getStatus();

	printf('%-12s %s%s', $status->value, substr($file->getOriginalPath(), strlen($root)), PHP_EOL);

	if(!$status->isNeedFix())
	{
		continue;
	}

	$toFix++;

	if($showDiff && $status === Status::Drift)
	{
		echo (string)shell_exec('diff -u '.escapeshellarg($mirror).' '.escapeshellarg($file->getOriginalPath())), '----', PHP_EOL;
	}
}

echo '----', PHP_EOL;
printf('Зеркал найдено: %d; восстановить: %d%s', $found, $toFix, PHP_EOL);

exit($toFix > 0 ? 1 : 0);
